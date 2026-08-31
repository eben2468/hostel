<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Payment;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Hostel;
use App\Models\Fee;
use App\Services\PaystackService;
use App\Services\Notify;

class PaymentController extends Controller
{
    private Payment $payments;
    private Invoice $invoices;

    public function __construct()
    {
        $this->payments = new Payment();
        $this->invoices = new Invoice();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance', 'student');
        if (Auth::role() === 'student') {
            $student = (new Student())->byUserId(Auth::id());
            $rows = $student ? $this->payments->forStudent((int) $student['id']) : [];
            $invoices = $student ? $this->invoices->forStudent((int) $student['id']) : [];
            $hostelId = $student && $student['hostel_id'] !== null ? (int) $student['hostel_id'] : null;
            $this->view('payments/student', [
                'pageTitle' => 'My Payments', 'payments' => $rows, 'invoices' => $invoices,
                'paystackEnabled' => $this->hostelPaystackEnabled($hostelId),
                'feeSchedule'     => $hostelId ? (new Fee())->scheduleFor($hostelId) : [],
                'dues'            => (new Hostel())->dues($hostelId),
                'arrears'         => \App\Models\DuesDebtor::outstandingFor($student),
                'duesStudentType' => Student::typeFor($student),
            ]);
            return;
        }
        $pager = $this->payments->paginatedDetailed(\App\Core\Paginator::currentPage());
        $this->view('payments/index', [
            'pageTitle' => 'Payments',
            'payments'  => $pager['rows'],
            'pager'     => $pager,
            'totalToday'=> (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND DATE(paid_at)=CURDATE()"),
        ]);
    }

    public function invoices(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        $this->view('payments/invoices', [
            'pageTitle' => 'Invoices',
            'invoices'  => $this->invoices->allWithStudent(),
        ]);
    }

    /**
     * Manage everything a hostel charges for: room-type prices, the account
     * students pay hall dues into, and the dues notice for freshers/continuing
     * students. Hostel admins manage their own hostel's; the super admin picks
     * a hostel first.
     */
    public function feeSchedule(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $hostels  = Scope::isGlobal() ? (new Hostel())->all('name') : null;
        $hostelId = Scope::isGlobal() ? (int) ($_GET['hostel_id'] ?? 0) : (int) Scope::hostelId();
        if ($hostelId) {
            $this->guardHostel($hostelId);
        }
        $this->view('payments/fees', [
            'pageTitle' => 'Fees & Hall Dues',
            'hostels'   => $hostels,
            'hostelId'  => $hostelId,
            'schedule'  => $hostelId ? (new Fee())->scheduleFor($hostelId) : [],
            'roomTypes' => Fee::ROOM_TYPES,
            'dues'      => $hostelId ? (new Hostel())->dues($hostelId) : [],
        ]);
    }

    /**
     * Save the bank / mobile-money account students pay hall dues into, plus the
     * step-by-step instructions shown alongside it.
     */
    public function saveDuesAccount(): void
    {
        $hostelId = $this->duesHostelId();
        $data = [];
        foreach (Hostel::DUES_ACCOUNT_FIELDS as $field) {
            $data[$field] = $this->input($field) ?: null;
        }
        // Absent checkbox means off; only meaningful once an account exists.
        $data['dues_reference_required'] = isset($_POST['dues_reference_required']) ? 1 : 0;
        (new Hostel())->update($hostelId, $data);
        Audit::log('update', 'hostels', $hostelId, 'dues account');
        Session::flash('success', 'Payment account saved. Students can now see where to pay their hall dues.');
        $this->redirectToDues($hostelId);
    }

    /**
     * Save the dues notice: what fresh and continuing students owe this term,
     * plus the note explaining each amount.
     */
    public function saveDuesNotice(): void
    {
        $hostelId = $this->duesHostelId();
        $data = [];
        foreach (Hostel::DUES_NOTICE_FIELDS as $field) {
            $raw = (string) $this->input($field, '');
            // Amount fields are numeric; a blank clears the published figure.
            $data[$field] = $raw === ''
                ? null
                : (str_ends_with($field, '_amount') ? (float) $raw : $raw);
        }
        (new Hostel())->update($hostelId, $data);
        Audit::log('update', 'hostels', $hostelId, 'dues notice');
        Session::flash('success', 'Dues notice saved. Students will see it when they apply for a room.');
        $this->redirectToDues($hostelId);
    }

    /** Resolve and authorise the hostel a dues form was submitted for. */
    private function duesHostelId(): int
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
        if (!$hostelId) {
            Session::flash('error', 'Please choose a hostel.');
            $this->redirect('/fees');
        }
        $this->guardHostel($hostelId);
        return $hostelId;
    }

    /** Back to the fees page, keeping the super admin's hostel selection. */
    private function redirectToDues(int $hostelId): void
    {
        $this->redirect('/fees' . (Scope::isGlobal() ? '?hostel_id=' . $hostelId : ''));
    }

    /** Save a hostel's room-type prices. */
    public function saveFeeSchedule(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
        if (!$hostelId) {
            Session::flash('error', 'Please choose a hostel.');
            $this->redirect('/fees');
        }
        $this->guardHostel($hostelId);

        $term = (new Hostel())->term($hostelId);
        $fee  = new Fee();
        foreach (Fee::ROOM_TYPES as $rt) {
            $raw = (string) $this->input('price_' . $rt, '');
            $fee->setPrice($hostelId, $rt, $raw === '' ? null : (float) $raw, $term);
        }
        Audit::log('update', 'fees', $hostelId, 'room pricing');
        Session::flash('success', 'Room pricing saved.');
        $this->redirect('/fees' . (Scope::isGlobal() ? '?hostel_id=' . $hostelId : ''));
    }

    /**
     * Form for billing students a custom charge (e.g. hostel dues, utilities).
     * Hostel admins bill their own hostel; the super admin picks a hostel.
     */
    public function chargeForm(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $students = Scope::isGlobal() ? (new Student())->all('full_name') : (new Student())->search('', '');
        $hostels  = Scope::isGlobal()
            ? (new Hostel())->all('name')
            : array_values(array_filter([(new Hostel())->find(Scope::hostelId())]));
        // Room prices for a hostel admin, used to quick-fill the amount.
        $feeSchedule = Scope::isGlobal() ? [] : (new Fee())->scheduleFor((int) Scope::hostelId());
        $this->view('payments/charge', [
            'pageTitle'   => 'New Charge',
            'students'    => $students,
            'hostels'     => $hostels,
            'feeSchedule' => $feeSchedule,
        ]);
    }

    /**
     * Create invoices for a charge, either for one student or for every active
     * resident of a hostel. Students then settle them like any other invoice.
     */
    public function storeCharge(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $errors = $this->validate(['description' => 'Charge name']);
        $amount = (float) $this->input('amount', 0);
        if (!$errors && $amount <= 0) {
            $errors['amount'] = 'Enter a valid amount greater than zero.';
        }
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/charges/create');
        }

        $description = $this->input('description');
        $dueDate     = $this->input('due_date') ?: null;

        // Resolve who is billed.
        if ($this->input('target', 'all') === 'one') {
            $student = (new Student())->find((int) $this->input('student_id'));
            if (!$student) {
                Session::flash('error', 'Please select a valid student.');
                $this->redirect('/charges/create');
            }
            $this->guardHostel($student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
            $students = [$student];
        } else {
            // Hostel-bound admins can only bill their own hostel.
            $hostelId = Scope::isGlobal() ? (int) $this->input('hostel_id') : (int) Scope::hostelId();
            if (!$hostelId) {
                Session::flash('error', 'Please choose a hostel to bill.');
                $this->redirect('/charges/create');
            }
            $this->guardHostel($hostelId);
            $students = Database::all(
                "SELECT id, hostel_id FROM students WHERE hostel_id = ? AND status = 'active' ORDER BY full_name",
                [$hostelId]
            );
        }

        if (!$students) {
            Session::flash('error', 'No matching students to bill.');
            $this->redirect('/charges/create');
        }

        $count = 0;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($students as $s) {
                $hid  = $s['hostel_id'] !== null ? (int) $s['hostel_id'] : null;
                $term = $hid ? (new Hostel())->term($hid) : ['academic_year' => null, 'semester' => null];
                // Extra entropy keeps invoice numbers unique across a large batch.
                $invoiceNo = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
                Database::insert(
                    "INSERT INTO invoices (invoice_no, student_id, academic_year, semester, description, amount, balance, due_date, status)
                     VALUES (?,?,?,?,?,?,?,?, 'unpaid')",
                    [$invoiceNo, (int) $s['id'], $term['academic_year'], $term['semester'], $description, $amount, $amount, $dueDate]
                );
                Notify::send(Notify::studentUserId((int) $s['id']), 'New charge: ' . $description,
                    'A charge of ' . money($amount) . ' has been added to your account.', '/payments', 'fa-file-invoice-dollar');
                $count++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Could not create the charge: ' . $e->getMessage());
            $this->redirect('/charges/create');
        }

        Audit::log('create', 'invoices', null, 'charge "' . $description . '" x' . $count);
        Session::flash('success', "Charge \"{$description}\" billed to {$count} student(s).");
        $this->redirect('/invoices');
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        $students = Scope::isGlobal() ? (new Student())->all('full_name') : (new Student())->search('', '');
        [$scope, $bind] = Scope::onStudent('i.student_id');
        $this->view('payments/form', [
            'pageTitle' => 'Record Payment',
            'students'  => $students,
            'invoices'  => Database::all(
                "SELECT i.*, s.full_name FROM invoices i JOIN students s ON s.id=i.student_id
                 WHERE i.status IN ('unpaid','partial'){$scope} ORDER BY i.created_at DESC",
                $bind
            ),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        Csrf::check();

        $studentId = (int) $this->input('student_id');
        $invoiceId = (int) $this->input('invoice_id') ?: null;
        $amount    = (float) $this->input('amount', 0);

        if (!$studentId || $amount <= 0) {
            Session::flash('error', 'A student and a positive amount are required.');
            $this->redirect('/payments/create');
        }
        // Staff may only record payments for students in their own hostel.
        $student = (new Student())->find($studentId);
        $this->guardHostel($student && $student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);

        $id = $this->recordPayment($studentId, $invoiceId, $amount, $this->input('method', 'cash'), $this->input('reference'));
        Session::flash('success', 'Payment recorded. Receipt generated.');
        $this->redirect('/payments/' . $id . '/receipt');
    }

    /**
     * Student pays an outstanding invoice. Uses the live Paystack gateway when a
     * secret key is configured; otherwise falls back to a simulated payment.
     */
    public function payInvoice($id): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin', 'finance');
        Csrf::check();
        $invoice = $this->invoices->find($id);
        if (!$invoice || $invoice['status'] === 'paid') {
            Session::flash('error', 'Invoice not found or already paid.');
            $this->redirect('/payments');
        }
        // Students may only pay their own invoices; staff only within their hostel.
        $student = null;
        if (Auth::hasRole('student')) {
            $student = (new Student())->byUserId(Auth::id());
            if (!$student || (int) $student['id'] !== (int) $invoice['student_id']) {
                Session::flash('error', 'You can only pay your own invoices.');
                $this->redirect('/payments');
            }
        } else {
            $invStudent = (new Student())->find($invoice['student_id']);
            $this->guardHostel($invStudent && $invStudent['hostel_id'] !== null ? (int) $invStudent['hostel_id'] : null);
        }
        $amount = (float) $invoice['balance'];

        // Collect payment through the student's hostel Paystack account (falls
        // back to the global keys when the hostel has none of its own).
        $student = $student ?: (new Student())->find($invoice['student_id']);
        $hostelId = $student && $student['hostel_id'] !== null ? (int) $student['hostel_id'] : null;

        // Online payment must be switched on for the hostel.
        if (!$this->hostelPaystackEnabled($hostelId)) {
            Session::flash('error', 'Online payment is not enabled for this hostel. Please pay at the office.');
            $this->redirect('/payments');
        }

        if (!PaystackService::isConfigured($hostelId)) {
            // No gateway keys — simulate an instant successful payment.
            $pid = $this->recordPayment((int) $invoice['student_id'], (int) $id, $amount, 'paystack', 'SIMULATED-' . strtoupper(bin2hex(random_bytes(3))));
            Session::flash('success', 'Payment successful (simulated). Invoice cleared.');
            $this->redirect('/payments/' . $pid . '/receipt');
        }

        // Live Paystack: create a pending payment, then redirect to the gateway.
        $reference = PaystackService::reference();
        $term = $this->paymentTerm((int) $invoice['student_id'], (int) $id);
        $pid = $this->payments->create([
            'receipt_no' => Payment::generateReceiptNo(),
            'invoice_id' => (int) $id,
            'student_id' => (int) $invoice['student_id'],
            'academic_year' => $term['academic_year'],
            'semester'      => $term['semester'],
            'amount'     => $amount,
            'method'     => 'paystack',
            'reference'  => $reference,
            'status'     => 'pending',
        ]);

        $init = PaystackService::initialize(
            $student['email'] ?? '',
            $amount,
            $reference,
            $this->absoluteUrl('/payments/callback'),
            ['invoice_id' => (int) $id, 'payment_id' => $pid],
            $hostelId
        );

        if (!($init['status'] ?? false) || empty($init['authorization_url'])) {
            $this->payments->update($pid, ['status' => 'failed']);
            Session::flash('error', 'Could not start payment: ' . ($init['message'] ?? 'unknown error'));
            $this->redirect('/payments');
        }

        Audit::log('payment_init', 'payments', $pid, $reference);
        $this->redirect($init['authorization_url']); // external Paystack URL
    }

    /** Paystack redirects here after checkout; verify and finalize. */
    public function callback(): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin', 'finance');
        $reference = trim($_GET['reference'] ?? $_GET['trxref'] ?? '');

        $payment = $reference !== ''
            ? Database::first("SELECT * FROM payments WHERE reference = ? LIMIT 1", [$reference])
            : null;

        if (!$payment) {
            Session::flash('error', 'Payment reference not found.');
            $this->redirect('/payments');
        }
        if ($payment['status'] === 'completed') {
            $this->redirect('/payments/' . $payment['id'] . '/receipt'); // already processed
        }

        // Verify with the same hostel account the transaction was initialized on.
        $hostelId = Database::scalar("SELECT hostel_id FROM students WHERE id = ?", [$payment['student_id']]);
        $hostelId = $hostelId !== false && $hostelId !== null ? (int) $hostelId : null;
        $result = PaystackService::verify($reference, $hostelId);
        if (!($result['status'] ?? false) || !($result['paid'] ?? false)) {
            $this->payments->update($payment['id'], ['status' => 'failed']);
            Audit::log('payment_failed', 'payments', $payment['id'], $reference);
            Session::flash('error', 'Payment was not completed.');
            $this->redirect('/payments');
        }

        // Mark completed and apply to the invoice in one transaction.
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                "UPDATE payments SET status='completed', verified_by=?, paid_at=NOW() WHERE id=?",
                [Auth::id(), $payment['id']]
            );
            if ($payment['invoice_id']) {
                $this->invoices->applyPayment((int) $payment['invoice_id'], (float) $payment['amount']);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Could not finalize payment: ' . $e->getMessage());
            $this->redirect('/payments');
        }

        Audit::log('payment', 'payments', $payment['id'], money($payment['amount']));
        Notify::student((int) $payment['student_id'], 'Payment received',
            'We received your payment of ' . money($payment['amount']) . '. View your receipt.',
            '/payments/' . $payment['id'] . '/receipt', 'fa-money-bill-wave');
        Session::flash('success', 'Payment verified and invoice updated.');
        $this->redirect('/payments/' . $payment['id'] . '/receipt');
    }

    /** True when the given hostel has online (Paystack) payment switched on. */
    private function hostelPaystackEnabled(?int $hostelId): bool
    {
        if (!$hostelId) {
            return false;
        }
        return (int) Database::scalar("SELECT paystack_enabled FROM hostels WHERE id = ?", [$hostelId]) === 1;
    }

    /** Build an absolute URL (scheme + host + base path) for gateway callbacks. */
    private function absoluteUrl(string $path): string
    {
        $https  = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        $scheme = $https ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host . BASE_URL . $path;
    }

    public function receipt($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance', 'student');
        $payment = $this->payments->detailed((int) $id);
        if (!$payment) {
            $this->redirect('/payments');
        }
        $this->guardReceiptAccess($payment);
        $this->view('payments/receipt', ['pageTitle' => 'Receipt', 'p' => $payment], 'blank');
    }

    /** Download the receipt as a PDF (FPDF). */
    public function receiptPdf($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance', 'student');
        $payment = $this->payments->detailed((int) $id);
        if (!$payment) {
            $this->redirect('/payments');
        }
        $this->guardReceiptAccess($payment);
        \App\Services\ReceiptPdf::render($payment, 'D');
        exit;
    }

    /**
     * Restrict a receipt to its owner (a student) or staff in the matching hostel.
     * Sends a 403 and stops the request when access is not allowed.
     */
    private function guardReceiptAccess(array $payment): void
    {
        if (Auth::hasRole('student')) {
            $student = (new Student())->byUserId(Auth::id());
            if (!$student || (int) $student['id'] !== (int) $payment['student_id']) {
                http_response_code(403);
                $this->view('errors/403', [], 'blank');
                exit;
            }
            return;
        }
        $student = (new Student())->find($payment['student_id']);
        $this->guardHostel($student && $student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
    }

    /**
     * The academic term to stamp a payment with: the invoice's term when paying
     * an invoice (keeps invoiced vs collected in the same term bucket), else the
     * student's hostel active term.
     */
    private function paymentTerm(int $studentId, ?int $invoiceId): array
    {
        if ($invoiceId) {
            $inv = Database::first("SELECT academic_year, semester FROM invoices WHERE id = ?", [$invoiceId]);
            if ($inv && $inv['academic_year'] !== null) {
                return ['academic_year' => $inv['academic_year'], 'semester' => $inv['semester']];
            }
        }
        $row = Database::first(
            "SELECT h.academic_year, h.semester FROM students s LEFT JOIN hostels h ON h.id = s.hostel_id WHERE s.id = ?",
            [$studentId]
        );
        return ['academic_year' => $row['academic_year'] ?? null, 'semester' => $row['semester'] ?? null];
    }

    /** Shared payment-recording routine: creates payment + updates invoice. */
    private function recordPayment(int $studentId, ?int $invoiceId, float $amount, string $method, ?string $reference): int
    {
        $term = $this->paymentTerm($studentId, $invoiceId);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $receiptNo = Payment::generateReceiptNo();
            $id = $this->payments->create([
                'receipt_no' => $receiptNo,
                'invoice_id' => $invoiceId,
                'student_id' => $studentId,
                'academic_year' => $term['academic_year'],
                'semester'      => $term['semester'],
                'amount'     => $amount,
                'method'     => $method,
                'reference'  => $reference,
                'status'     => 'completed',
                'verified_by'=> Auth::id(),
            ]);
            if ($invoiceId) {
                $this->invoices->applyPayment($invoiceId, $amount);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Session::flash('error', 'Payment failed: ' . $e->getMessage());
            $this->redirect('/payments');
        }
        Audit::log('payment', 'payments', $id, money($amount));
        Notify::student($studentId, 'Payment received',
            'We received your payment of ' . money($amount) . '. View your receipt.',
            '/payments/' . $id . '/receipt', 'fa-money-bill-wave');
        return $id;
    }
}
