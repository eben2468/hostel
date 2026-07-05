<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Audit;
use App\Core\Scope;
use App\Core\Database;
use App\Models\User;
use App\Models\Hostel;

/**
 * Staff/user management with hostel isolation.
 *
 * - Super admin (`admin`): manages every user, assigns any role and any hostel.
 * - Hostel admin: manages only their own hostel's staff, may create the staff
 *   roles below, and may never touch admin/hostel_admin accounts or other hostels.
 */
class UserController extends Controller
{
    private User $users;

    public function __construct()
    {
        $this->users = new User();
    }

    /** Roles the current actor is permitted to assign. */
    private function assignableRoles(): array
    {
        return Scope::isGlobal()
            ? ['admin','hostel_admin','finance','maintenance','security']
            : ['finance','maintenance','security'];
    }

    /** Hostels the current actor may assign: all for admin, only their own otherwise. */
    private function hostelOptions(): array
    {
        if (Scope::isGlobal()) {
            return (new Hostel())->all('name');
        }
        $own = (new Hostel())->find(Scope::hostelId());
        return $own ? [$own] : [];
    }

    /**
     * Load a user for editing and enforce the actor's authority over it.
     * Hostel admins may only manage assignable-role staff within their hostel.
     */
    private function manageable($id): ?array
    {
        $user = $this->users->find($id);
        if (!$user) {
            return null;
        }
        if (!Scope::isGlobal()) {
            $this->guardHostel($user['hostel_id'] !== null ? (int) $user['hostel_id'] : null);
            if (!in_array($user['role'], $this->assignableRoles(), true)) {
                http_response_code(403);
                $this->view('errors/403', [], 'blank');
                exit;
            }
        }
        return $user;
    }

    public function index(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $filters = [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'role'   => trim((string) ($_GET['role'] ?? '')),
            'hostel' => trim((string) ($_GET['hostel'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        $this->view('users/index', [
            'pageTitle' => 'Users & Staff',
            'users'     => $this->users->filteredList($filters['q'], $filters['role'], $filters['hostel'], $filters['status']),
            'filters'   => $filters,
            'hostels'   => Scope::isGlobal() ? (new Hostel())->all('name') : null,
        ]);
    }

    public function create(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $this->view('users/form', [
            'pageTitle' => 'Add User',
            'user'      => null,
            'roles'     => $this->assignableRoles(),
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function store(): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();

        $errors = $this->validate([
            'name' => 'Name', 'email' => 'Email', 'password' => 'Password',
        ]);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/users/create');
        }

        $data = $this->data();
        if ($data === null) {
            $this->redirect('/users/create');
        }
        if ($this->duplicate($data['username'], $data['email'])) {
            Session::set('_old', $_POST);
            Session::flash('error', 'That username or email is already taken.');
            $this->redirect('/users/create');
        }

        $data['password'] = Auth::hash((string) $this->input('password'));
        $id = $this->users->create($data);
        Audit::log('create', 'users', $id);
        Session::flash('success', 'User created.');
        $this->redirect('/users');
    }

    public function edit($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        $user = $this->manageable($id);
        if (!$user) {
            $this->redirect('/users');
        }
        $this->view('users/form', [
            'pageTitle' => 'Edit User',
            'user'      => $user,
            'roles'     => $this->assignableRoles(),
            'hostels'   => $this->hostelOptions(),
        ]);
    }

    public function update($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        $user = $this->manageable($id);
        if (!$user) {
            $this->redirect('/users');
        }

        $errors = $this->validate(['name' => 'Name', 'email' => 'Email']);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/users/' . $id . '/edit');
        }

        $data = $this->data();
        if ($data === null) {
            $this->redirect('/users/' . $id . '/edit');
        }
        if ($this->duplicate($data['username'], $data['email'], (int) $id)) {
            Session::set('_old', $_POST);
            Session::flash('error', 'That username or email is already taken.');
            $this->redirect('/users/' . $id . '/edit');
        }

        // Only change the password when a new one was supplied.
        $password = (string) $this->input('password');
        if ($password !== '') {
            $data['password'] = Auth::hash($password);
        }
        $this->users->update($id, $data);
        Audit::log('update', 'users', $id);
        Session::flash('success', 'User updated.');
        $this->redirect('/users');
    }

    public function destroy($id): void
    {
        $this->requireAuth('admin', 'hostel_admin');
        Csrf::check();
        if ((int) $id === (int) Auth::id()) {
            Session::flash('error', 'You cannot delete your own account.');
            $this->redirect('/users');
        }
        $user = $this->manageable($id);
        if (!$user) {
            $this->redirect('/users');
        }
        $this->users->delete($id);
        Audit::log('delete', 'users', $id);
        Session::flash('success', 'User deleted.');
        $this->redirect('/users');
    }

    /**
     * Build the user attribute set with role/hostel authority enforced.
     * Returns null (after flashing an error) when the chosen role is not allowed.
     */
    private function data(): ?array
    {
        $role = $this->input('role', 'finance');
        if (!in_array($role, $this->assignableRoles(), true)) {
            Session::set('_old', $_POST);
            Session::flash('error', 'You are not allowed to assign that role.');
            return null;
        }

        // The super admin role is global (no hostel). Hostel-bound actors always
        // write their own hostel; the super admin picks one for other roles.
        if ($role === 'admin') {
            $hostelId = null;
        } elseif (Scope::isGlobal()) {
            $hostelId = $this->input('hostel_id') ? (int) $this->input('hostel_id') : null;
        } else {
            $hostelId = Scope::hostelId();
        }

        // Staff no longer choose a username; they sign in with their email, so we
        // mirror the email into the (still required, unique) username column.
        return [
            'name'      => $this->input('name'),
            'username'  => $this->input('email'),
            'email'     => $this->input('email'),
            'phone'     => $this->input('phone'),
            'role'      => $role,
            'hostel_id' => $hostelId,
            'is_active' => $this->input('is_active', '1') === '1' ? 1 : 0,
        ];
    }

    /** True when another user already uses the username or email. */
    private function duplicate(string $username, string $email, int $exceptId = 0): bool
    {
        $row = Database::first(
            "SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1",
            [$username, $email, $exceptId]
        );
        return $row !== null;
    }
}
