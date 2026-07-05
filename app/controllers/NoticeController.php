<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Scope;
use App\Models\Notice;
use App\Services\Notify;

class NoticeController extends Controller
{
    private Notice $notices;

    public function __construct()
    {
        $this->notices = new Notice();
    }

    public function index(): void
    {
        $this->requireAuth();
        $audience = Auth::hasRole('student') ? 'students' : 'staff';
        $notices = Auth::hasRole('admin', 'hostel_admin')
            ? $this->notices->forManagement()
            : $this->notices->visibleFor($audience);
        $this->view('notices/index', [
            'pageTitle' => 'Notice Board',
            'notices'   => $notices,
            'canManage' => Auth::hasRole('admin', 'hostel_admin'),
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('notices/form', ['pageTitle' => 'New Notice']);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $errors = $this->validate(['title' => 'Title', 'body' => 'Message']);
        if ($errors) {
            Session::flash('error', reset($errors));
            $this->redirect('/notices/create');
        }
        // Hostel admins post notices to their own hostel; the super admin posts
        // global notices (hostel_id NULL) visible across all hostels.
        $id = $this->notices->create([
            'title'      => $this->input('title'),
            'body'       => $this->input('body'),
            'audience'   => $this->input('audience', 'all'),
            'hostel_id'  => Scope::isGlobal() ? null : Scope::hostelId(),
            'is_pinned'  => isset($_POST['is_pinned']) ? 1 : 0,
            'expires_at' => $this->input('expires_at') ?: null,
            'created_by' => Auth::id(),
        ]);
        Audit::log('create', 'notices', $id);
        $audience = $this->input('audience', 'all');
        $roles = match ($audience) {
            'students' => ['student'],
            'staff'    => ['admin', 'hostel_admin', 'finance', 'maintenance', 'security'],
            default    => ['student', 'admin', 'hostel_admin', 'finance', 'maintenance', 'security'],
        };
        Notify::toRole($roles, 'New notice: ' . $this->input('title'),
            'A new notice has been posted.', '/notices', 'fa-bullhorn');
        Session::flash('success', 'Notice published.');
        $this->redirect('/notices');
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $notice = $this->notices->find($id);
        if (!$notice) {
            $this->redirect('/notices');
        }
        // A hostel admin may only delete their own hostel's notices (not global ones).
        if (!Scope::isGlobal()) {
            $this->guardHostel($notice['hostel_id'] !== null ? (int) $notice['hostel_id'] : null);
        }
        $this->notices->delete($id);
        Audit::log('delete', 'notices', $id);
        Session::flash('success', 'Notice deleted.');
        $this->redirect('/notices');
    }
}
