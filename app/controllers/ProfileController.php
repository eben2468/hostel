<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;
use App\Core\Database;
use App\Models\Student;

class ProfileController extends Controller
{
    public function edit(): void
    {
        $this->requireAuth();
        // Students maintain their own next-of-kin details here, so the record
        // is loaded to prefill them and to know whether they are still missing.
        $student = Auth::hasRole('student') ? (new Student())->byUserId(Auth::id()) : null;
        $this->view('profile/edit', [
            'pageTitle' => 'My Profile',
            'user'      => Auth::user(),
            'student'   => $student,
        ]);
    }

    public function update(): void
    {
        $this->requireAuth();
        Csrf::check();

        $name  = (string) $this->input('name');
        $email = (string) $this->input('email');
        $phone = (string) $this->input('phone');

        // None of this was checked before, so a duplicate address reached the
        // UNIQUE index on users.email and came back as an uncaught 500.
        $error = null;
        if (trim($name) === '') {
            $error = 'Your name cannot be empty.';
        } elseif (trim($email) === '') {
            $error = 'An email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($this->emailTaken($email, (int) Auth::id())) {
            $error = 'That email address is already used by another account.';
        }
        if ($error !== null) {
            Session::set('_old', $_POST);
            Session::flash('error', $error);
            $this->redirect('/profile');
        }

        $isStudent = Auth::hasRole('student');
        $guardianName  = (string) $this->input('guardian_name');
        $guardianPhone = (string) $this->input('guardian_phone');
        if ($isStudent && (trim($guardianName) === '' || trim($guardianPhone) === '')) {
            Session::set('_old', $_POST);
            Session::flash('error', "Your parent or guardian's name and phone number are both required.");
            $this->redirect('/profile');
        }

        Database::run(
            "UPDATE users SET name=?, phone=?, email=? WHERE id=?",
            [$name, $phone, $email, Auth::id()]
        );
        Session::set('user_name', $name);
        // A student changing their own address must also change where their
        // notifications go — those are sent to the student record, not here.
        Student::syncContactFromUser((int) Auth::id());

        if ($isStudent) {
            Database::run(
                "UPDATE students SET guardian_name=?, guardian_phone=?, guardian_relationship=? WHERE user_id=?",
                [$guardianName, $guardianPhone, $this->input('guardian_relationship') ?: null, Auth::id()]
            );
        }
        Session::forget('_old');

        // Optional avatar upload.
        if (!empty($_FILES['avatar']['name'])) {
            $result = \App\Core\Upload::image($_FILES['avatar'], 'avatars');
            if ($result['ok']) {
                $user = Auth::user();
                \App\Core\Upload::remove($user['avatar'] ?? null);
                Database::run("UPDATE users SET avatar=? WHERE id=?", [$result['path'], Auth::id()]);
            } else {
                Session::flash('error', $result['error']);
                $this->redirect('/profile');
            }
        }

        Session::flash('success', 'Profile updated.');
        $this->redirect('/profile');
    }

    /**
     * True when another account already holds this address.
     *
     * Usernames are checked as well: staff sign in with their email mirrored
     * into the username column, so an address can collide there too.
     */
    private function emailTaken(string $email, int $exceptId): bool
    {
        return Database::first(
            "SELECT id FROM users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1",
            [$email, $email, $exceptId]
        ) !== null;
    }

    public function password(): void
    {
        $this->requireAuth();
        Csrf::check();
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $user = Auth::user();
        if (!password_verify($current, $user['password'])) {
            Session::flash('error', 'Current password is incorrect.');
            $this->redirect('/profile');
        }
        if (strlen($new) < MIN_PASSWORD_LENGTH) {
            Session::flash('error', 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters.');
            $this->redirect('/profile');
        }
        if ($new !== $confirm) {
            Session::flash('error', 'New passwords do not match.');
            $this->redirect('/profile');
        }
        Database::run("UPDATE users SET password=? WHERE id=?", [Auth::hash($new), Auth::id()]);
        Session::flash('success', 'Password changed successfully.');
        $this->redirect('/profile');
    }
}
