<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Allocation;
use App\Models\Notice;
use App\Models\Student;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        match (Auth::role()) {
            'student'     => $this->studentDashboard(),
            'finance'     => $this->financeDashboard(),
            'maintenance' => $this->maintenanceDashboard(),
            'security'    => $this->securityDashboard(),
            default       => $this->staffDashboard(), // admin, hostel_admin
        };
    }

    private function financeDashboard(): void
    {
        // Scope finance figures to the caller's hostel via the linked student.
        [$pay, $pb]  = Scope::onStudent('student_id');   // payments.student_id
        [$inv, $ib]  = Scope::onStudent('student_id');   // invoices.student_id
        [$payP, $pbP] = Scope::onStudent('p.student_id'); // payments p
        [$invI, $ibI] = Scope::onStudent('i.student_id'); // invoices i

        $stats = [
            'revenue_today' => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND DATE(paid_at)=CURDATE(){$pay}", $pb),
            'revenue_month' => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE()){$pay}", $pb),
            'collected'     => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed'{$pay}", $pb),
            'outstanding'   => (float) Database::scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN ('unpaid','partial'){$inv}", $ib),
            'unpaid_invoices' => (int) Database::scalar("SELECT COUNT(*) FROM invoices WHERE status IN ('unpaid','partial'){$inv}", $ib),
            'payments_count'  => (int) Database::scalar("SELECT COUNT(*) FROM payments WHERE status='completed'{$pay}", $pb),
        ];
        $trend = Database::all(
            "SELECT DATE_FORMAT(paid_at,'%b') AS label, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE status='completed' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH){$pay}
             GROUP BY YEAR(paid_at), MONTH(paid_at) ORDER BY YEAR(paid_at), MONTH(paid_at)", $pb
        );
        $methods = Database::all(
            "SELECT method, COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total
             FROM payments WHERE status='completed'{$pay} GROUP BY method ORDER BY total DESC", $pb
        );
        $recent = Database::all(
            "SELECT p.*, s.full_name FROM payments p JOIN students s ON s.id=p.student_id WHERE 1{$payP} ORDER BY p.paid_at DESC LIMIT 8", $pbP
        );
        $topDebtors = Database::all(
            "SELECT s.full_name, s.student_id, SUM(i.balance) AS owed
             FROM invoices i JOIN students s ON s.id=i.student_id
             WHERE i.status IN ('unpaid','partial'){$invI} GROUP BY s.id ORDER BY owed DESC LIMIT 5", $ibI
        );
        $this->view('dashboard/finance', [
            'pageTitle' => 'Finance Dashboard', 'stats' => $stats, 'trend' => $trend,
            'methods' => $methods, 'recent' => $recent, 'topDebtors' => $topDebtors,
        ]);
    }

    private function maintenanceDashboard(): void
    {
        // Scope complaints to the caller's hostel via the linked student; general
        // (student-less) complaints stay visible to all maintenance staff.
        $cScope = ''; $cBind = [];
        $cScopeC = ''; $cBindC = [];
        if (!Scope::isGlobal()) {
            $cScope  = " AND (student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR student_id IS NULL)";
            $cBind   = [Scope::hostelId()];
            $cScopeC = " AND (c.student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR c.student_id IS NULL)";
            $cBindC  = [Scope::hostelId()];
        }
        $stats = [
            'open'        => (int) Database::scalar("SELECT COUNT(*) FROM complaints WHERE status IN ('open','assigned'){$cScope}", $cBind),
            'in_progress' => (int) Database::scalar("SELECT COUNT(*) FROM complaints WHERE status IN ('in_progress','waiting_parts'){$cScope}", $cBind),
            'completed'   => (int) Database::scalar("SELECT COUNT(*) FROM complaints WHERE status IN ('completed','closed'){$cScope}", $cBind),
            'urgent'      => (int) Database::scalar("SELECT COUNT(*) FROM complaints WHERE priority='urgent' AND status NOT IN ('completed','closed','rejected'){$cScope}", $cBind),
        ];
        $byCategory = Database::all("SELECT category, COUNT(*) AS cnt FROM complaints WHERE 1{$cScope} GROUP BY category ORDER BY cnt DESC", $cBind);
        $queue = Database::all(
            "SELECT c.*, s.full_name FROM complaints c LEFT JOIN students s ON s.id=c.student_id
             WHERE c.status NOT IN ('completed','closed','rejected'){$cScopeC}
             ORDER BY FIELD(c.priority,'urgent','high','medium','low'), c.created_at ASC LIMIT 10", $cBindC
        );
        $this->view('dashboard/maintenance', [
            'pageTitle' => 'Maintenance Dashboard', 'stats' => $stats,
            'byCategory' => $byCategory, 'queue' => $queue,
        ]);
    }

    private function securityDashboard(): void
    {
        // Scope visitors to the caller's hostel via the host student; visitors
        // with no host student stay visible to all security staff.
        $vScope = ''; $vBind = [];
        $vScopeV = ''; $vBindV = [];
        if (!Scope::isGlobal()) {
            $vScope  = " AND (student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR student_id IS NULL)";
            $vBind   = [Scope::hostelId()];
            $vScopeV = " AND (v.student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR v.student_id IS NULL)";
            $vBindV  = [Scope::hostelId()];
        }
        $stats = [
            'today'      => (int) Database::scalar("SELECT COUNT(*) FROM visitors WHERE visit_date=CURDATE(){$vScope}", $vBind),
            'pending'    => (int) Database::scalar("SELECT COUNT(*) FROM visitors WHERE status='pending'{$vScope}", $vBind),
            'checked_in' => (int) Database::scalar("SELECT COUNT(*) FROM visitors WHERE status='checked_in'{$vScope}", $vBind),
            'blacklisted'=> (int) Database::scalar("SELECT COUNT(*) FROM visitors WHERE status='blacklisted'{$vScope}", $vBind),
        ];
        $pending = Database::all(
            "SELECT v.*, s.full_name AS host_name FROM visitors v LEFT JOIN students s ON s.id=v.student_id
             WHERE v.status IN ('pending','approved','checked_in'){$vScopeV} ORDER BY v.created_at DESC LIMIT 10", $vBindV
        );
        $recent = Database::all(
            "SELECT v.*, s.full_name AS host_name FROM visitors v LEFT JOIN students s ON s.id=v.student_id
             WHERE 1{$vScopeV} ORDER BY v.created_at DESC LIMIT 8", $vBindV
        );
        $this->view('dashboard/security', [
            'pageTitle' => 'Security Dashboard', 'stats' => $stats,
            'pending' => $pending, 'recent' => $recent,
        ]);
    }

    private function staffDashboard(): void
    {
        // Per-table scope fragments. A hostel admin sees only their hostel; the
        // super admin sees everything (all fragments collapse to no-ops).
        [$st, $stB]   = Scope::on('hostel_id');          // students
        [$rm, $rmB]   = Scope::on('hostel_id');          // rooms
        [$ho, $hoB]   = Scope::on('id');                 // hostels
        [$pay, $payB] = Scope::onStudent('student_id');  // payments
        [$inv, $invB] = Scope::onStudent('student_id');  // invoices
        [$app, $appB] = Scope::on('preferred_hostel_id'); // applications (by target hostel)
        [$payP, $payPB] = Scope::onStudent('p.student_id');
        [$appA, $appAB] = Scope::on('a.preferred_hostel_id');
        // Complaints (NULL-inclusive for general items).
        $cmp = ''; $cmpB = [];
        if (!Scope::isGlobal()) {
            $cmp  = " AND (student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR student_id IS NULL)";
            $cmpB = [Scope::hostelId()];
        }

        $stats = [
            'students'       => (int) Database::scalar("SELECT COUNT(*) FROM students WHERE 1{$st}", $stB),
            'hostels'        => (int) Database::scalar("SELECT COUNT(*) FROM hostels WHERE status='active'{$ho}", $hoB),
            'rooms'          => (int) Database::scalar("SELECT COUNT(*) FROM rooms WHERE 1{$rm}", $rmB),
            'available_rooms'=> (int) Database::scalar("SELECT COUNT(*) FROM rooms WHERE status='available'{$rm}", $rmB),
            'occupied_rooms' => (int) Database::scalar("SELECT COUNT(*) FROM rooms WHERE status='occupied'{$rm}", $rmB),
            'applications'   => (int) Database::scalar("SELECT COUNT(*) FROM applications WHERE 1{$app}", $appB),
            'pending_apps'   => (int) Database::scalar("SELECT COUNT(*) FROM applications WHERE status='pending'{$app}", $appB),
            'revenue_today'  => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND DATE(paid_at)=CURDATE(){$pay}", $payB),
            'revenue_month'  => (float) Database::scalar("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='completed' AND MONTH(paid_at)=MONTH(CURDATE()) AND YEAR(paid_at)=YEAR(CURDATE()){$pay}", $payB),
            'outstanding'    => (float) Database::scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE status IN ('unpaid','partial'){$inv}", $invB),
            'open_complaints'=> (int) Database::scalar("SELECT COUNT(*) FROM complaints WHERE status NOT IN ('completed','closed','rejected'){$cmp}", $cmpB),
        ];

        // Total bed capacity vs occupied for the occupancy gauge.
        $capacity = (int) Database::scalar("SELECT COALESCE(SUM(capacity),0) FROM rooms WHERE 1{$rm}", $rmB);
        $occupied = (int) Database::scalar("SELECT COALESCE(SUM(occupied),0) FROM rooms WHERE 1{$rm}", $rmB);
        $stats['occupancy_rate'] = $capacity > 0 ? round($occupied / $capacity * 100) : 0;

        // Revenue trend — last 6 months.
        $trend = Database::all(
            "SELECT DATE_FORMAT(paid_at,'%b') AS label, COALESCE(SUM(amount),0) AS total
             FROM payments
             WHERE status='completed' AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH){$pay}
             GROUP BY YEAR(paid_at), MONTH(paid_at) ORDER BY YEAR(paid_at), MONTH(paid_at)", $payB
        );

        $recentPayments = Database::all(
            "SELECT p.*, s.full_name FROM payments p JOIN students s ON s.id=p.student_id
             WHERE 1{$payP} ORDER BY p.paid_at DESC LIMIT 5", $payPB
        );
        $recentApps = Database::all(
            "SELECT a.*, s.full_name FROM applications a JOIN students s ON s.id=a.student_id
             WHERE 1{$appA} ORDER BY a.created_at DESC LIMIT 5", $appAB
        );

        $this->view('dashboard/staff', [
            'pageTitle'      => 'Dashboard',
            'stats'          => $stats,
            'trend'          => $trend,
            'recentPayments' => $recentPayments,
            'recentApps'     => $recentApps,
        ]);
    }

    private function studentDashboard(): void
    {
        $studentModel = new Student();
        $student = $studentModel->byUserId(Auth::id());

        $allocation = null;
        $invoices = [];
        $payments = [];
        if ($student) {
            $allocation = (new Allocation())->activeForStudent((int) $student['id']);
            $invoices = Database::all("SELECT * FROM invoices WHERE student_id=? ORDER BY created_at DESC", [$student['id']]);
            $outstanding = (float) Database::scalar("SELECT COALESCE(SUM(balance),0) FROM invoices WHERE student_id=? AND status IN ('unpaid','partial')", [$student['id']]);
        } else {
            $outstanding = 0;
        }

        $notices = (new Notice())->visibleFor('students');

        $this->view('dashboard/student', [
            'pageTitle'   => 'My Dashboard',
            'student'     => $student,
            'allocation'  => $allocation,
            'invoices'    => $invoices,
            'outstanding' => $outstanding,
            'notices'     => array_slice($notices, 0, 5),
        ]);
    }
}
