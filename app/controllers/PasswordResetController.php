<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Database;
use App\Models\PasswordResetRequest;
use App\Services\Notify;

/**
 * Admin handling of student password-reset requests (the admin-assisted flow).
 *
 * Super admins act on every request; hostel admins act only on requests for
 * their own hostel (and unmatched requests carrying no hostel). Resolving a
 * request sets the student's user password directly.
 */
class PasswordResetController extends Controller
{
    private PasswordResetRequest $requests;

    public function __construct()
    {
        $this->requests = new PasswordResetRequest();
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('password_requests/index', [
            'pageTitle' => 'Password Requests',
            'requests'  => $this->requests->scopedList(),
        ]);
    }

    /** Resolve a request by setting a new password on the linked user account. */
    public function resolve($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $req = $this->guarded($id);

        // The linked student must have a login account to receive the password.
        $userId = $req['student_id']
            ? Database::scalar("SELECT user_id FROM students WHERE id = ?", [$req['student_id']])
            : null;
        if (!$userId) {
            Session::flash('error', 'This request has no linked login account; reset is not possible.');
            $this->redirect('/password-requests');
        }

        $password = $_POST['password'] ?? '';
        if (strlen($password) < 6) {
            Session::flash('error', 'The new password must be at least 6 characters.');
            $this->redirect('/password-requests');
        }

        Database::run("UPDATE users SET password = ? WHERE id = ?", [Auth::hash($password), (int) $userId]);
        $this->requests->update($id, [
            'status'     => 'resolved',
            'handled_by' => Auth::id(),
            'handled_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('reset_password', 'users', (int) $userId, 'admin request #' . $id);
        Notify::send((int) $userId, 'Password reset',
            'An administrator has reset your password. Please sign in and change it.', '/profile', 'fa-user-lock');
        Session::flash('success', 'Password reset. Share the new password with the student securely.');
        $this->redirect('/password-requests');
    }

    /** Dismiss a request without resetting (e.g. could not verify the requester). */
    public function reject($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $this->guarded($id);
        $this->requests->update($id, [
            'status'     => 'rejected',
            'handled_by' => Auth::id(),
            'handled_at' => date('Y-m-d H:i:s'),
        ]);
        Audit::log('reject', 'password_reset_requests', (int) $id);
        Session::flash('success', 'Request dismissed.');
        $this->redirect('/password-requests');
    }

    /** Load a request and enforce the actor's hostel authority over it. */
    private function guarded($id): array
    {
        $req = $this->requests->find($id);
        if (!$req) {
            Session::flash('error', 'Request not found.');
            $this->redirect('/password-requests');
        }
        // Hostel admins may only touch their own hostel's requests. Unmatched
        // (hostel-less) requests remain actionable by any admin.
        if (!\App\Core\Scope::isGlobal() && $req['hostel_id'] !== null) {
            $this->guardHostel((int) $req['hostel_id']);
        }
        return $req;
    }
}
