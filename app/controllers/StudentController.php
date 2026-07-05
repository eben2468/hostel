<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Audit;
use App\Core\Scope;
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
        $this->view('students/show', ['pageTitle' => $student['full_name'], 'student' => $student]);
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
        Audit::log('update', 'students', $id);
        Session::flash('success', 'Student updated.');
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
