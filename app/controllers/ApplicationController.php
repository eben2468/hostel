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
use App\Models\Hostel;
use App\Models\DuesDebtor;
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
                'dues' => (new Hostel())->dues($student['hostel_id'] ?? null),
                // Arrears from past semesters bar them from applying at all.
                'arrears' => DuesDebtor::outstandingFor($student),
            ]);
            return;
        }

        $filters = [
            'q'       => trim($_GET['q'] ?? ''),
            'status'  => trim($_GET['status'] ?? ''),
            'payment' => trim($_GET['payment'] ?? ''),
            'hostel'  => trim($_GET['hostel'] ?? ''),
            'term'    => trim($_GET['term'] ?? ''),
        ];
        $pager = $this->apps->paginatedWithStudent($filters, \App\Core\Paginator::currentPage());
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
            'filters'          => $filters,
            // Only the super admin can span hostels, so only they get the picker.
            'hostels'          => \App\Core\Scope::isGlobal() ? (new Hostel())->all('name') : null,
            'terms'            => $this->apps->termOptions(),
            'applicationsOpen' => $applicationsOpen,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('student', 'admin', 'hostel_admin');
        $students = [];
        $preferredRooms = [];
        $dues = [];
        $studentType = null;
        if (Auth::hasRole('student')) {
            // A student's hostel is fixed at registration, so we only offer the
            // available rooms within their own hostel (no hostel choice here).
            $me = (new Student())->byUserId(Auth::id());
            if (!$this->studentCanApply($me)) {
                Session::flash('error', 'Applications are currently closed for your hostel.');
                $this->redirect('/applications');
            }
            // Arrears bar the form outright; the applications list carries the
            // panel explaining what is owed and how to clear it.
            if ($arrears = DuesDebtor::outstandingFor($me)) {
                Session::flash('error', $this->arrearsMessage($arrears));
                $this->redirect('/applications');
            }
            $preferredRooms = (new Room())->availableForHostel((int) $me['hostel_id']);
            // The dues notice + account the student must pay into before applying.
            $dues = (new Hostel())->dues((int) $me['hostel_id']);
            $studentType = Student::typeFor($me);
        } else {
            // Staff pick a student; hostel admins only see their own students/rooms.
            $students = \App\Core\Scope::isGlobal()
                ? (new Student())->all('full_name')
                : (new Student())->search('', '');
            $preferredRooms = (new Room())->available(); // Scope-filtered to their hostel(s)
            // A hostel-bound admin sees their own hostel's dues details; the
            // super admin has no single hostel, so the panel is skipped.
            $dues = (new Hostel())->dues(\App\Core\Scope::hostelId());
        }
        $this->view('applications/form', [
            'pageTitle'      => 'New Application',
            'students'       => $students,
            'preferredRooms' => $preferredRooms,
            'dues'           => $dues,
            'studentType'    => $studentType,
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
            // Re-checked on submit, not just when the form was opened: the debt
            // may have been added while the student had the page sitting open.
            if ($arrears = DuesDebtor::outstandingFor($student)) {
                Session::flash('error', $this->arrearsMessage($arrears));
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

        // The room may have filled up between opening the form and submitting it.
        // Send the student back with their answers intact so they only have to
        // change the room; staff are told, but their application still goes in
        // with the preference cleared.
        if ($room && ($full = Room::unavailableReason($room)) !== null) {
            if (Auth::hasRole('student')) {
                Session::set('_old', $_POST);
                Session::flash('error', $full);
                $this->redirect('/applications/create');
            }
            $roomFullNotice = $full;
            $room = null; $roomId = null;
        }
        // Preferred hostel follows the room when picked, else the student's membership.
        $preferredHostelId = $room ? (int) $room['hostel_id'] : $studentHostel;

        // Academic year & semester are no longer entered per application — they
        // come from the hostel admin's settings for the relevant hostel.
        $settings = $preferredHostelId
            ? Database::first("SELECT academic_year, semester FROM hostels WHERE id=?", [$preferredHostelId])
            : null;

        // Hall dues: the student pays into the hostel's published account and
        // submits the reference their bank/MoMo transfer returned. We store the
        // amount owed at submission time too, so a reviewer can see what the
        // reference is meant to cover even if the notice changes later.
        $dues        = (new Hostel())->dues($preferredHostelId);
        $studentType = $this->input('student_type');
        if (!array_key_exists((string) $studentType, Hostel::STUDENT_TYPES)) {
            $studentType = Student::typeFor($student);
        }
        $reference = $this->input('payment_reference') ?: null;

        // Students must supply the reference when their hostel asks for one.
        // Staff recording an application on someone's behalf may leave it out
        // and fill it in once the student produces their receipt.
        if ($reference === null && Auth::hasRole('student') && Hostel::duesReferenceRequired($dues)) {
            Session::set('_old', $_POST);
            Session::flash('error', 'Please enter the Reference ID from your hall dues payment.');
            $this->redirect('/applications/create');
        }

        $id = $this->apps->create([
            'student_id'          => $studentId,
            'academic_year'       => $settings['academic_year'] ?? null,
            'semester'            => $settings['semester'] ?? null,
            'student_type'        => $studentType,
            'preferred_hostel_id' => $preferredHostelId,
            'preferred_room_type' => $this->input('preferred_room_type') ?: null,
            'preferred_room_id'   => $roomId,
            'medical_conditions'  => $this->input('medical_conditions'),
            'special_needs'       => $this->input('special_needs'),
            'remarks'             => $this->input('remarks'),
            'payment_reference'   => $reference,
            'payment_amount'      => Hostel::duesAmountFor($dues, $studentType),
            'payment_status'      => 'unverified',
            'status'              => 'pending',
        ]);
        Audit::log('create', 'applications', $id);
        Notify::toRole(['admin', 'hostel_admin'], 'New hostel application',
            $reference
                ? 'A student applied and submitted dues reference ' . $reference . ' for checking.'
                : 'A student submitted a hostel application.',
            '/applications', 'fa-file-lines');
        // Drop any repopulated form data so a later application never starts
        // out pre-filled with a reference that has already been submitted.
        Session::forget('_old');
        Session::flash('success', 'Application submitted successfully.');
        // Staff are not blocked by arrears — they may be recording an
        // application for someone who has just paid at the desk — but they are
        // told, so an unpaid debt is never approved by accident.
        $notes = [];
        if (isset($roomFullNotice)) {
            $notes[] = $roomFullNotice . ' The application was saved without a preferred room.';
        }
        if (!Auth::hasRole('student') && ($arrears = DuesDebtor::outstandingFor($student))) {
            $notes[] = 'This student has ' . $this->arrearsMessage($arrears, false);
        }
        if ($notes) {
            Session::flash('warning', implode(' ', $notes));
        }
        $this->redirect('/applications');
    }

    /**
     * Spell out what is owed and for which semesters, so the student is not
     * left guessing why they were turned away.
     *
     * @param bool $forStudent false phrases it for staff about the applicant
     */
    private function arrearsMessage(array $arrears, bool $forStudent = true): string
    {
        $terms = [];
        foreach ($arrears as $d) {
            $terms[] = DuesDebtor::termLabel($d);
        }
        $terms = array_slice(array_unique($terms), 0, 3);
        $total = DuesDebtor::totalOwed($arrears);
        $amount = $total > 0 ? ' of ' . money($total) : '';

        return $forStudent
            ? 'You have unpaid hall dues' . $amount . ' from ' . implode(' and ', $terms)
                . '. Please settle at the hostel office before applying for a room.'
            : 'this student has unpaid hall dues' . $amount . ' from ' . implode(' and ', $terms) . '.';
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
        $note = trim((string) $this->input('review_note'));
        $this->setStatus($id, 'rejected', 'Application rejected.', $note !== '' ? $note : null);
    }

    public function waiting($id): void
    {
        $this->setStatus($id, 'waiting', 'Application moved to waiting list.');
    }

    /**
     * Cancel an application — what a hostel admin does when the dues reference
     * does not check out. The note is mandatory: it is the only thing that
     * tells the student what went wrong, and it rides along with the
     * notification they receive.
     */
    public function cancel($id): void
    {
        $note = trim((string) $this->input('review_note'));
        if ($note === '') {
            Session::flash('error', 'Please write a note telling the student why the application was cancelled.');
            $this->redirect('/applications');
        }
        $this->setStatus($id, 'cancelled', 'Application cancelled — the student has been notified.', $note);
    }

    /**
     * Record the outcome of checking a dues reference against the hostel's
     * account. Marking one "not found" deliberately leaves the application's
     * own status alone, so the admin can still let the student correct the
     * reference before cancelling.
     */
    public function verifyPayment($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $app = $this->apps->find($id);
        if (!$app) {
            $this->redirect('/applications');
        }
        $this->guardHostel($app['preferred_hostel_id'] !== null ? (int) $app['preferred_hostel_id'] : null);

        $state = (string) $this->input('payment_status');
        if (!in_array($state, ['unverified', 'verified', 'not_found'], true)) {
            $state = 'verified';
        }
        Database::run(
            "UPDATE applications SET payment_status=?, payment_verified_by=?, payment_verified_at=NOW() WHERE id=?",
            [$state, Auth::id(), $id]
        );
        Audit::log('update', 'applications', $id, 'payment_status=' . $state);

        $reference = $app['payment_reference'] ?: 'not provided';
        if ($state === 'verified') {
            Notify::student((int) $app['student_id'], 'Hall dues payment confirmed',
                'We traced your payment (reference ' . $reference . '). Your application is now awaiting a room.',
                '/applications', 'fa-circle-check');
            Session::flash('success', 'Payment confirmed for this application.');
        } elseif ($state === 'not_found') {
            Notify::student((int) $app['student_id'], 'Hall dues payment not found',
                'We could not trace a payment for reference ' . $reference
                . '. Please visit the hostel office with your receipt.',
                '/applications', 'fa-triangle-exclamation');
            Session::flash('success', 'Marked as not found — the student has been asked to follow up.');
        } else {
            Session::flash('success', 'Payment check reset to unverified.');
        }
        $this->redirect('/applications');
    }

    private function setStatus($id, string $status, string $message, ?string $note = null): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $app = $this->apps->find($id);
        if (!$app) {
            $this->redirect('/applications');
        }
        // A hostel admin may only review applications directed at their hostel.
        $this->guardHostel($app['preferred_hostel_id'] !== null ? (int) $app['preferred_hostel_id'] : null);
        // The note is always written, so a fresh decision without one clears the
        // previous decision's note rather than leaving stale text on the record.
        Database::run(
            "UPDATE applications SET status=?, review_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?",
            [$status, $note, Auth::id(), $id]
        );
        Audit::log('update', 'applications', $id, 'status=' . $status);
        if ($app) {
            Notify::student((int) $app['student_id'], 'Application ' . ucfirst($status),
                'Your hostel application has been ' . $status . '.'
                    . ($note !== null ? ' Reason: ' . $note : ''),
                '/applications', 'fa-file-lines');
        }
        Session::flash('success', $message);
        $this->redirect('/applications');
    }
}
