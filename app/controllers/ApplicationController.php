<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Models\Application;
use App\Models\Student;
use App\Models\Room;
use App\Services\Notify;

class ApplicationController extends Controller
{
    private Application $apps;

    public function __construct()
    {
        $this->apps = new Application();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'student');

        if (Auth::role() === 'student') {
            $student = (new Student())->byUserId(Auth::id());
            // A student can only apply when their hostel is accepting applications.
            $applicationsOpen = $student && !empty($student['hostel_id'])
                && (bool) Database::scalar("SELECT applications_open FROM hostels WHERE id=?", [$student['hostel_id']]);
            $applications = $student
                ? Database::all(
                    "SELECT a.*, h.name AS hostel_name, pr.room_number AS preferred_room_number
                     FROM applications a
                     LEFT JOIN hostels h ON h.id=a.preferred_hostel_id
                     LEFT JOIN rooms pr ON pr.id=a.preferred_room_id
                     WHERE a.student_id=? ORDER BY a.created_at DESC", [$student['id']])
                : [];
            $this->view('applications/index', [
                'pageTitle' => 'My Applications', 'applications' => $applications,
                'isStudent' => true, 'status' => '', 'applicationsOpen' => $applicationsOpen,
            ]);
            return;
        }

        $status = trim($_GET['status'] ?? '');
        $pager = $this->apps->paginatedWithStudent($status, \App\Core\Paginator::currentPage());
        // Hostel admins get an open/close toggle for their own hostel; the global
        // admin has no single hostel, so no toggle is shown (null).
        $hostelId = \App\Core\Scope::hostelId();
        $applicationsOpen = (Auth::role() === 'hostel_admin' && $hostelId)
            ? (bool) Database::scalar("SELECT applications_open FROM hostels WHERE id=?", [$hostelId])
            : null;
        $this->view('applications/index', [
            'pageTitle'        => 'Applications',
            'applications'     => $pager['rows'],
            'pager'            => $pager,
            'isStudent'        => false,
            'status'           => $status,
            'applicationsOpen' => $applicationsOpen,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin');
        $students = [];
        $preferredRooms = [];
        if (Auth::hasRole('student')) {
            // A student's hostel is fixed at registration, so we only offer the
            // available rooms within their own hostel (no hostel choice here).
            $me = (new Student())->byUserId(Auth::id());
            if (!$this->studentCanApply($me)) {
                Session::flash('error', 'Applications are currently closed for your hostel.');
                $this->redirect('/applications');
            }
            $preferredRooms = (new Room())->availableForHostel((int) $me['hostel_id']);
        } else {
            // Staff pick a student; hostel admins only see their own students/rooms.
            $students = \App\Core\Scope::isGlobal()
                ? (new Student())->all('full_name')
                : (new Student())->search('', '');
            $preferredRooms = (new Room())->available(); // Scope-filtered to their hostel(s)
        }
        $this->view('applications/form', [
            'pageTitle'      => 'New Application',
            'students'       => $students,
            'preferredRooms' => $preferredRooms,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin');
        Csrf::check();

        // Resolve the student: their own record if a student, else from the form.
        if (Auth::hasRole('student')) {
            $student = (new Student())->byUserId(Auth::id());
            if (!$student) {
                Session::flash('error', 'No student profile linked to your account.');
                $this->redirect('/dashboard');
            }
            // Enforce the hostel's applications-open toggle for students.
            if (!$this->studentCanApply($student)) {
                Session::flash('error', 'Applications are currently closed for your hostel.');
                $this->redirect('/applications');
            }
            $studentId = (int) $student['id'];
        } else {
            $studentId = (int) $this->input('student_id');
            if (!$studentId) {
                Session::flash('error', 'Please choose a student.');
                $this->redirect('/applications/create');
            }
            $student = (new Student())->find($studentId);
        }

        // Resolve the preferred room and derive the hostel from it (the hostel is
        // no longer chosen on the form — it comes from membership / the room).
        $roomId = (int) $this->input('preferred_room_id') ?: null;
        $room   = $roomId ? (new Room())->find($roomId) : null;

        $studentHostel = !empty($student['hostel_id']) ? (int) $student['hostel_id'] : null;
        // A student may only prefer a room inside their own hostel; drop mismatches.
        if ($room && $studentHostel !== null && (int) $room['hostel_id'] !== $studentHostel) {
            $room = null; $roomId = null;
        }
        // Preferred hostel follows the room when picked, else the student's membership.
        $preferredHostelId = $room ? (int) $room['hostel_id'] : $studentHostel;

        // Academic year & semester are no longer entered per application — they
        // come from the hostel admin's settings for the relevant hostel.
        $settings = $preferredHostelId
            ? Database::first("SELECT academic_year, semester FROM hostels WHERE id=?", [$preferredHostelId])
            : null;

        $id = $this->apps->create([
            'student_id'          => $studentId,
            'academic_year'       => $settings['academic_year'] ?? null,
            'semester'            => $settings['semester'] ?? null,
            'preferred_hostel_id' => $preferredHostelId,
            'preferred_room_type' => $this->input('preferred_room_type') ?: null,
            'preferred_room_id'   => $roomId,
            'medical_conditions'  => $this->input('medical_conditions'),
            'special_needs'       => $this->input('special_needs'),
            'remarks'             => $this->input('remarks'),
            'status'              => 'pending',
        ]);
        Audit::log('create', 'applications', $id);
        Notify::toRole(['admin', 'hostel_admin'], 'New hostel application',
            'A student submitted a hostel application.', '/applications', 'fa-file-lines');
        Session::flash('success', 'Application submitted successfully.');
        $this->redirect('/applications');
    }

    /** A student may apply only if bound to a hostel that is accepting applications. */
    private function studentCanApply(?array $student): bool
    {
        if (!$student || empty($student['hostel_id'])) {
            return false;
        }
        return (bool) Database::scalar("SELECT applications_open FROM hostels WHERE id=?", [$student['hostel_id']]);
    }

    /** Hostel admin toggles whether students in their hostel may apply. */
    public function toggleOpen(): void
    {
        $this->requireAuth('hostel_admin');
        Csrf::check();
        $hostelId = \App\Core\Scope::hostelId();
        if (!$hostelId) {
            $this->redirect('/applications');
        }
        $new = (int) Database::scalar("SELECT applications_open FROM hostels WHERE id=?", [$hostelId]) ? 0 : 1;
        Database::run("UPDATE hostels SET applications_open=? WHERE id=?", [$new, $hostelId]);
        Audit::log('update', 'hostels', $hostelId, 'applications_open=' . $new);
        Session::flash('success', $new
            ? 'Applications are now open — students can apply for rooms.'
            : 'Applications are now closed — students cannot apply for rooms.');
        $this->redirect('/applications');
    }

    public function approve($id): void
    {
        $this->setStatus($id, 'approved', 'Application approved.');
    }

    public function reject($id): void
    {
        $this->setStatus($id, 'rejected', 'Application rejected.');
    }

    public function waiting($id): void
    {
        $this->setStatus($id, 'waiting', 'Application moved to waiting list.');
    }

    private function setStatus($id, string $status, string $message): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $app = $this->apps->find($id);
        if (!$app) {
            $this->redirect('/applications');
        }
        // A hostel admin may only review applications directed at their hostel.
        $this->guardHostel($app['preferred_hostel_id'] !== null ? (int) $app['preferred_hostel_id'] : null);
        Database::run(
            "UPDATE applications SET status=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?",
            [$status, Auth::id(), $id]
        );
        Audit::log('update', 'applications', $id, 'status=' . $status);
        if ($app) {
            Notify::student((int) $app['student_id'], 'Application ' . ucfirst($status),
                'Your hostel application has been ' . $status . '.', '/applications', 'fa-file-lines');
        }
        Session::flash('success', $message);
        $this->redirect('/applications');
    }
}
