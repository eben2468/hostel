<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Scope;
use App\Core\Database;
use App\Models\Student;
use App\Models\Hostel;

class StudentController extends Controller
{
    private Student $students;

    public function __construct()
    {
        $this->students = new Student();
    }

    /** Hostels for the super admin's selector, or null when hostel-bound. */
    private function hostelOptions(): ?array
    {
        return Scope::isGlobal() ? (new Hostel())->all('name') : null;
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance', 'security');
        $term   = trim($_GET['q'] ?? '');
        $status = trim($_GET['status'] ?? '');
        $pager  = $this->students->searchPaginated($term, $status, \App\Core\Paginator::currentPage());
        $this->view('students/index', [
            'pageTitle' => 'Students',
            'students'  => $pager['rows'],
            'pager'     => $pager,
            'term'      => $term,
            'status'    => $status,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('students/form', [
            'pageTitle' => 'Add Student',
            'student'   => null,
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $errors = $this->validate(['student_id' => 'Student ID', 'full_name' => 'Full name']);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/students/create');
        }
        $id = $this->students->create($this->data());
        $this->handlePhoto($id);
        Audit::log('create', 'students', $id);
        Session::flash('success', 'Student added successfully.');
        $this->redirect('/students/' . $id);
    }

    public function show($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance', 'security');
        $student = $this->students->find($id);
        if (!$student) {
            $this->notFound();
        }
        $this->guardHostel($student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
        $this->view('students/show', [
            'pageTitle' => $student['full_name'],
            'student'   => $student,
            // The login account behind the record, so the page can show whether
            // they can still sign in (a student may have no account at all).
            'account'   => $student['user_id']
                ? Database::first("SELECT id, email, is_active FROM users WHERE id = ?", [$student['user_id']])
                : null,
        ]);
    }

    /**
     * Turn a student's login access on or off.
     *
     * Deactivating flips both halves so they cannot drift apart: `users.is_active`
     * is what the login check reads, and `students.status` is what the rest of
     * the app displays. The record itself is untouched — allocations, invoices
     * and history all stay put, unlike Delete.
     */
    public function toggleActive($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $student = $this->students->find($id);
        if (!$student) {
            $this->notFound();
        }
        $this->guardHostel($student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);

        $activate = $this->input('action') === 'activate';

        if (empty($student['user_id'])) {
            Session::flash('error', 'This student has no login account, so there is nothing to '
                . ($activate ? 'activate' : 'deactivate') . '.');
            $this->redirect('/students/' . $id);
        }

        Database::run("UPDATE users SET is_active = ? WHERE id = ?", [$activate ? 1 : 0, $student['user_id']]);
        Database::run("UPDATE students SET status = ? WHERE id = ?", [$activate ? 'active' : 'inactive', $id]);

        Audit::log('update', 'students', $id, $activate ? 'account activated' : 'account deactivated');
        Session::flash('success', $activate
            ? e($student['full_name']) . " can sign in again."
            : e($student['full_name']) . " has been deactivated and can no longer sign in. Their records are kept.");
        $this->redirect('/students/' . $id);
    }

    public function edit($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $student = $this->students->find($id);
        if (!$student) {
            $this->notFound();
        }
        $this->guardHostel($student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
        $this->view('students/form', [
            'pageTitle' => 'Edit Student',
            'student'   => $student,
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $existing = $this->students->find($id);
        if (!$existing) {
            $this->notFound();
        }
        $this->guardHostel($existing['hostel_id'] !== null ? (int) $existing['hostel_id'] : null);
        $this->students->update($id, $this->data($existing));
        $this->handlePhoto($id, $existing['photo'] ?? null);
        // Keep the login account's address in step, or mail keeps going to the
        // old one — notifications are sent to students.email, not users.email.
        $synced = Student::syncContactToUser((int) $id);
        Audit::log('update', 'students', $id);
        Session::flash($synced ? 'success' : 'error', $synced
            ? 'Student updated.'
            : 'Student updated, but another account already uses that email — their sign-in address was left unchanged.');
        $this->redirect('/students/' . $id);
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $existing = $this->students->find($id);
        if (!$existing) {
            $this->notFound();
        }
        $this->guardHostel($existing['hostel_id'] !== null ? (int) $existing['hostel_id'] : null);
        $this->students->delete($id);
        Audit::log('delete', 'students', $id);
        Session::flash('success', 'Student deleted.');
        $this->redirect('/students');
    }

    /** Download a student's account statement as a PDF. */
    public function statement($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        $student = $this->students->find($id);
        if (!$student) {
            $this->notFound();
        }
        $this->guardHostel($student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
        $invoices = \App\Core\Database::all("SELECT * FROM invoices WHERE student_id=? ORDER BY created_at DESC", [$id]);
        $payments = \App\Core\Database::all("SELECT * FROM payments WHERE student_id=? AND status='completed' ORDER BY paid_at DESC", [$id]);
        \App\Services\ReportPdf::statement($student, $invoices, $payments, 'D');
        exit;
    }

    public function importForm(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('students/import', ['pageTitle' => 'Import Students']);
    }

    /** Bulk-create students from an uploaded CSV file. */
    public function import(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        if (empty($_FILES['file']['tmp_name']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            Session::flash('error', 'Please choose a CSV file to upload.');
            $this->redirect('/students/import');
        }

        $result = $this->students->importCsv($_FILES['file']['tmp_name'], Scope::hostelId());

        Audit::log('import', 'students', null, "imported={$result['imported']} skipped={$result['skipped']}");
        Session::flash('success', "Import complete: {$result['imported']} added, {$result['skipped']} skipped (duplicates/invalid).");
        $this->redirect('/students');
    }

    /**
     * Build the student attribute set. Hostel binding is resolved server-side:
     * a hostel-bound user always writes their own hostel; the global super admin
     * may pick one (or leave it unassigned). On update with no new value, the
     * existing binding is preserved.
     */
    private function data(?array $existing = null): array
    {
        if (Scope::isGlobal()) {
            $hostelId = $this->input('hostel_id');
            $hostelId = ($hostelId === '' || $hostelId === null) ? ($existing['hostel_id'] ?? null) : (int) $hostelId;
        } else {
            $hostelId = Scope::hostelId();
        }
        return [
            'hostel_id'    => $hostelId,
            'student_id'   => $this->input('student_id'),
            'full_name'    => $this->input('full_name'),
            'gender'       => $this->input('gender', 'male'),
            'date_of_birth'=> $this->input('date_of_birth') ?: null,
            'nationality'  => $this->input('nationality'),
            'programme'    => $this->input('programme'),
            'department'   => $this->input('department'),
            'level'        => $this->input('level'),
            'phone'        => $this->input('phone'),
            'email'        => $this->input('email'),
            'address'      => $this->input('address'),
            'guardian_name'         => $this->input('guardian_name'),
            'guardian_phone'        => $this->input('guardian_phone'),
            'guardian_relationship' => $this->input('guardian_relationship'),
            'blood_group'  => $this->input('blood_group'),
            'allergies'    => $this->input('allergies'),
            'emergency_contact' => $this->input('emergency_contact'),
            'status'       => $this->input('status', 'active'),
        ];
    }

    /** Process an optional student photo upload, replacing any previous one. */
    private function handlePhoto(int $id, ?string $existing = null): void
    {
        if (empty($_FILES['photo']['name'])) {
            return;
        }
        $result = \App\Core\Upload::image($_FILES['photo'], 'students');
        if (!$result['ok']) {
            Session::flash('error', $result['error']);
            return;
        }
        \App\Core\Upload::remove($existing);
        $this->students->update($id, ['photo' => $result['path']]);
    }

    private function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404', [], 'blank');
        exit;
    }
}
