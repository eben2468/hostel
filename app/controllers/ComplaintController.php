<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Models\Complaint;
use App\Models\Student;
use App\Services\Notify;

class ComplaintController extends Controller
{
    private Complaint $complaints;

    public function __construct()
    {
        $this->complaints = new Complaint();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'maintenance', 'student');
        if (Auth::role() === 'student') {
            $student = (new Student())->byUserId(Auth::id());
            $rows = $student ? $this->complaints->forStudent((int) $student['id']) : [];
            $this->view('complaints/index', ['pageTitle' => 'My Complaints', 'complaints' => $rows, 'isStudent' => true]);
            return;
        }
        $pager = $this->complaints->paginatedDetailed(\App\Core\Paginator::currentPage());
        $this->view('complaints/index', [
            'pageTitle'  => 'Complaints',
            'complaints' => $pager['rows'],
            'pager'      => $pager,
            'isStudent'  => false,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin');
        $this->view('complaints/form', ['pageTitle' => 'Report Complaint']);
    }

    public function store(): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin');
        Csrf::check();
        $errors = $this->validate(['title' => 'Title', 'category' => 'Category']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/complaints/create');
        }

        $studentId = null;
        if (Auth::hasRole('student')) {
            $student = (new Student())->byUserId(Auth::id());
            $studentId = $student['id'] ?? null;
        }

        $id = $this->complaints->create([
            'student_id'  => $studentId,
            'category'    => $this->input('category', 'other'),
            'title'       => $this->input('title'),
            'description' => $this->input('description'),
            'priority'    => $this->input('priority', 'medium'),
            'room_number' => $this->input('room_number'),
            'status'      => 'open',
        ]);
        Audit::log('create', 'complaints', $id);
        Notify::toRole(['admin', 'hostel_admin', 'maintenance'], 'New complaint: ' . $this->input('title'),
            'A ' . $this->input('priority', 'medium') . ' priority complaint was submitted.', '/complaints', 'fa-screwdriver-wrench');
        Session::flash('success', 'Complaint submitted. We will look into it shortly.');
        $this->redirect('/complaints');
    }

    public function updateStatus($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'maintenance');
        Csrf::check();
        $status = $this->input('status', 'open');
        $notes  = $this->input('technician_notes');
        $complaint = $this->complaints->find($id);
        if (!$complaint) {
            $this->redirect('/complaints');
        }
        // Guard student-linked complaints to their hostel; general (student-less)
        // complaints are not hostel-bound and remain actionable by any staff.
        if ($complaint['student_id'] !== null) {
            $student = (new Student())->find($complaint['student_id']);
            $this->guardHostel($student && $student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
        }
        Database::run(
            "UPDATE complaints SET status=?, technician_notes=?, assigned_to=? WHERE id=?",
            [$status, $notes, Auth::id(), $id]
        );
        Audit::log('update', 'complaints', $id, 'status=' . $status);
        if ($complaint && $complaint['student_id']) {
            Notify::send(Notify::studentUserId((int) $complaint['student_id']),
                'Complaint ' . ucwords(str_replace('_', ' ', $status)),
                'Your complaint "' . $complaint['title'] . '" was updated.', '/complaints', 'fa-screwdriver-wrench');
        }
        Session::flash('success', 'Complaint updated.');
        $this->redirect('/complaints');
    }
}
