<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Session;
use App\Core\Database;
use App\Core\Audit;
use App\Services\Notify;

class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', ['pageTitle' => 'Sign In'], 'blank');
    }

    public function login(): void
    {
        Csrf::check();
        $login    = $this->input('login');
        $password = $_POST['password'] ?? '';

        // Account lockout check.
        if ($this->isLockedOut($login)) {
            Session::flash('error', 'Too many failed attempts. Please try again in ' . LOCKOUT_MINUTES . ' minutes.');
            $this->redirect('/login');
        }

        if ($login === '' || $password === '') {
            Session::flash('error', 'Please enter your Student ID / email and password.');
            $this->redirect('/login');
        }

        if (Auth::attempt($login, $password)) {
            $this->recordAttempt($login, true);
            Audit::log('login', 'auth');
            $this->redirect('/dashboard');
        }

        $this->recordAttempt($login, false);
        Session::flash('error', 'Invalid credentials or inactive account.');
        $this->redirect('/login');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $hostels = Database::all("SELECT id, name FROM hostels WHERE status = 'active' ORDER BY name");
        $this->view('auth/register', ['pageTitle' => 'Student Registration', 'hostels' => $hostels], 'blank');
    }

    public function register(): void
    {
        Csrf::check();
        $errors = $this->validate([
            'name'       => 'Full name',
            'email'      => 'Email',
            'student_id' => 'Student ID',
            'hostel_id'  => 'Hostel',
            'password'   => 'Password',
        ]);

        $name      = $this->input('name');
        $email     = $this->input('email');
        $studentId = $this->input('student_id');
        $phone     = $this->input('phone');
        $gender    = $this->input('gender', 'male');
        $hostelId  = (int) $this->input('hostel_id');
        $nationality = $this->input('nationality');
        $programme   = $this->input('programme');
        $department  = $this->input('department');
        $level       = $this->input('level');
        $password  = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Password must be at least 6 characters.';
        }
        // The chosen hostel must exist and be active.
        if ($hostelId && !Database::first("SELECT id FROM hostels WHERE id = ? AND status = 'active'", [$hostelId])) {
            $errors['hostel_id'] = 'Please choose a valid hostel.';
        }
        if (Database::first("SELECT id FROM users WHERE email = ? OR username = ?", [$email, $studentId])) {
            $errors['email'] = 'An account with this email or student ID already exists.';
        }

        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/register');
        }

        // Bind the new student to their chosen hostel on both the user (for session
        // scope) and the student record (their hostel membership).
        $userId = Database::insert(
            "INSERT INTO users (name, username, email, phone, password, role, hostel_id, is_active) VALUES (?,?,?,?,?, 'student', ?, 1)",
            [$name, $studentId, $email, $phone, Auth::hash($password), $hostelId]
        );
        Database::insert(
            "INSERT INTO students (user_id, hostel_id, student_id, full_name, gender, phone, email, nationality, programme, department, level, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?, 'active')",
            [$userId, $hostelId, $studentId, $name, $gender, $phone, $email, $nationality, $programme, $department, $level]
        );

        Session::forget('_old');
        Session::flash('success', 'Registration successful. You can now sign in.');
        $this->redirect('/login');
    }

    public function showForgot(): void
    {
        $this->view('auth/forgot', ['pageTitle' => 'Forgot Password'], 'blank');
    }

    /**
     * Option 1 — self-service verification.
     * A student proves their identity with Full Name + Student ID + Date of
     * Birth. On a match we grant a short-lived, session-bound reset window.
     */
    public function verifyIdentity(): void
    {
        Csrf::check();
        $fullName  = $this->input('full_name');
        $studentId = $this->input('student_code');
        $dob       = $this->input('date_of_birth');

        $errors = $this->validate([
            'full_name'     => 'Full name',
            'student_code'  => 'Student ID',
            'date_of_birth' => 'Date of birth',
        ]);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/forgot-password');
        }

        // Throttle brute-forcing of the date of birth, keyed on the student ID.
        $key = 'reset:' . $studentId;
        if ($this->isLockedOut($key)) {
            Session::flash('error', 'Too many failed attempts. Please try again in ' . LOCKOUT_MINUTES . ' minutes.');
            $this->redirect('/forgot-password');
        }

        $student = Database::first(
            "SELECT id, user_id FROM students
             WHERE student_id = ? AND date_of_birth = ? AND LOWER(full_name) = LOWER(?)
             LIMIT 1",
            [$studentId, $dob, $fullName]
        );

        if (!$student) {
            $this->recordAttempt($key, false);
            Session::set('_old', $_POST);
            Session::flash('error', 'We could not match those details. Check them, or request an admin reset below.');
            $this->redirect('/forgot-password');
        }
        if ($student['user_id'] === null) {
            Session::set('_old', $_POST);
            Session::flash('error', 'No login account is linked to this student. Please request an admin reset below.');
            $this->redirect('/forgot-password');
        }

        $this->recordAttempt($key, true);
        // Grant a 10-minute window to set a new password.
        Session::forget('_old');
        Session::set('pw_reset_user', (int) $student['user_id']);
        Session::set('pw_reset_exp', time() + 600);
        $this->redirect('/forgot-password/reset');
    }

    /** Show the new-password form, only while a verification window is open. */
    public function showReset(): void
    {
        if (!$this->resetWindowOpen()) {
            Session::flash('error', 'Your reset session has expired. Please verify your identity again.');
            $this->redirect('/forgot-password');
        }
        $this->view('auth/reset', ['pageTitle' => 'Set New Password'], 'blank');
    }

    /** Apply the new password chosen after a successful self-verification. */
    public function resetSelf(): void
    {
        Csrf::check();
        if (!$this->resetWindowOpen()) {
            Session::flash('error', 'Your reset session has expired. Please verify your identity again.');
            $this->redirect('/forgot-password');
        }

        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['password_confirm'] ?? '';
        if (strlen($password) < 6) {
            Session::flash('error', 'Password must be at least 6 characters.');
            $this->redirect('/forgot-password/reset');
        }
        if ($password !== $confirm) {
            Session::flash('error', 'Passwords do not match.');
            $this->redirect('/forgot-password/reset');
        }

        $userId = (int) Session::get('pw_reset_user');
        Database::run("UPDATE users SET password = ? WHERE id = ?", [Auth::hash($password), $userId]);
        Session::forget('pw_reset_user');
        Session::forget('pw_reset_exp');
        Audit::log('reset_password', 'users', $userId, 'self-service');
        Session::flash('success', 'Your password has been reset. You can now sign in.');
        $this->redirect('/login');
    }

    /**
     * Option 2 — request an admin reset.
     * The student leaves their details for a super/hostel admin to action.
     */
    public function requestReset(): void
    {
        Csrf::check();
        $errors = $this->validate([
            'full_name'    => 'Full name',
            'student_code' => 'Student ID',
        ]);
        if ($errors) {
            Session::set('_old', $_POST);
            Session::flash('error', reset($errors));
            $this->redirect('/forgot-password');
        }

        $fullName  = $this->input('full_name');
        $studentId = $this->input('student_code');
        $contact   = $this->input('contact');
        $message   = $this->input('message');

        // Best-effort match so the request lands in the right hostel's queue.
        $student = Database::first(
            "SELECT id, hostel_id FROM students WHERE student_id = ? LIMIT 1",
            [$studentId]
        );

        Database::run(
            "INSERT INTO password_reset_requests (student_id, hostel_id, student_code, full_name, contact, message)
             VALUES (?,?,?,?,?,?)",
            [$student['id'] ?? null, $student['hostel_id'] ?? null, $studentId, $fullName, $contact, $message]
        );

        Notify::toRole(['admin', 'hostel_admin'], 'Password reset requested',
            $fullName . ' (' . $studentId . ') requested a password reset.', '/password-requests', 'fa-user-lock');

        Session::forget('_old');
        Session::flash('success', 'Your request has been sent. An administrator will reset your password shortly.');
        $this->redirect('/login');
    }

    /** True while a verified, unexpired self-service reset window is held. */
    private function resetWindowOpen(): bool
    {
        return Session::has('pw_reset_user')
            && (int) Session::get('pw_reset_exp') > time();
    }

    public function logout(): void
    {
        Audit::log('logout', 'auth');
        Auth::logout();
        Session::start();
        Session::flash('success', 'You have been signed out.');
        $this->redirect('/login');
    }

    // --- Lockout helpers ----------------------------------------------------
    private function recordAttempt(string $login, bool $success): void
    {
        Database::run(
            "INSERT INTO login_attempts (login, ip_address, success) VALUES (?,?,?)",
            [$login, $_SERVER['REMOTE_ADDR'] ?? null, $success ? 1 : 0]
        );
    }

    private function isLockedOut(string $login): bool
    {
        $count = (int) Database::scalar(
            "SELECT COUNT(*) FROM login_attempts
             WHERE login = ? AND success = 0 AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$login, LOCKOUT_MINUTES]
        );
        return $count >= MAX_LOGIN_ATTEMPTS;
    }
}
