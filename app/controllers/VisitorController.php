<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Visitor;
use App\Models\Student;

class VisitorController extends Controller
{
    private Visitor $visitors;

    public function __construct()
    {
        $this->visitors = new Visitor();
    }

    /**
     * Load a visitor and 403 if its host student belongs to another hostel.
     * Visitors with no host student are not hostel-bound and pass through.
     */
    private function guardedVisitor($id): ?array
    {
        $v = $this->visitors->find($id);
        if (!$v) {
            return null;
        }
        if ($v['student_id'] !== null) {
            $student = (new Student())->find($v['student_id']);
            $this->guardHostel($student && $student['hostel_id'] !== null ? (int) $student['hostel_id'] : null);
        }
        return $v;
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        $pager = $this->visitors->paginatedDetailed(\App\Core\Paginator::currentPage());
        $this->view('visitors/index', [
            'pageTitle' => 'Visitors',
            'visitors'  => $pager['rows'],
            'pager'     => $pager,
            'isStudent' => false,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        $students = Scope::isGlobal() ? (new Student())->all('full_name') : (new Student())->search('', '');
        $this->view('visitors/form', [
            'pageTitle' => 'Register Visitor',
            'students'  => $students,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        $errors = $this->validate(['visitor_name' => 'Visitor name']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/visitors/create');
        }

        $studentId = $this->input('student_id') ? (int) $this->input('student_id') : null;

        $id = $this->visitors->create([
            'student_id'   => $studentId,
            'visitor_name' => $this->input('visitor_name'),
            'phone'        => $this->input('phone'),
            'purpose'      => $this->input('purpose'),
            'visit_date'   => $this->input('visit_date') ?: null,
            'status'       => 'pending',
        ]);
        Audit::log('create', 'visitors', $id);
        Session::flash('success', 'Visitor registered. Awaiting security approval.');
        $this->redirect('/visitors');
    }

    public function approve($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        if (!$this->guardedVisitor($id)) {
            $this->redirect('/visitors');
        }
        Database::run("UPDATE visitors SET status='approved', pass_code=? WHERE id=?", [Visitor::generatePass(), $id]);
        Audit::log('approve', 'visitors', $id);
        Session::flash('success', 'Visitor approved and pass issued.');
        $this->redirect('/visitors');
    }

    public function checkin($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        if (!$this->guardedVisitor($id)) {
            $this->redirect('/visitors');
        }
        Database::run("UPDATE visitors SET status='checked_in', time_in=NOW() WHERE id=?", [$id]);
        Audit::log('checkin', 'visitors', $id);
        Session::flash('success', 'Visitor checked in.');
        $this->redirect('/visitors');
    }

    public function checkout($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        if (!$this->guardedVisitor($id)) {
            $this->redirect('/visitors');
        }
        Database::run("UPDATE visitors SET status='checked_out', time_out=NOW() WHERE id=?", [$id]);
        Audit::log('checkout', 'visitors', $id);
        Session::flash('success', 'Visitor checked out.');
        $this->redirect('/visitors');
    }

    public function blacklist($id): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'security');
        Csrf::check();
        if (!$this->guardedVisitor($id)) {
            $this->redirect('/visitors');
        }
        Database::run("UPDATE visitors SET status='blacklisted' WHERE id=?", [$id]);
        Audit::log('blacklist', 'visitors', $id);
        Session::flash('success', 'Visitor blacklisted.');
        $this->redirect('/visitors');
    }
}
