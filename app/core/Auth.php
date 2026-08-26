<?php
namespace App\Core;

/** Authentication and current-user helpers. */
class Auth
{
    /**
     * Check a login/password pair without starting a session.
     *
     * Kept separate from attempt() so two-factor authentication can verify the
     * password first and only sign the user in once the emailed code is
     * confirmed.
     *
     * @return array|null the user row on success, null on bad or inactive credentials
     */
    public static function verifyCredentials(string $login, string $password): ?array
    {
        $user = Database::first(
            "SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1",
            [$login, $login]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }
        if ((int) $user['is_active'] !== 1) {
            return null;
        }

        return $user;
    }

    public static function attempt(string $login, string $password): bool
    {
        $user = self::verifyCredentials($login, $password);
        if ($user === null) {
            return false;
        }

        self::login($user);
        return true;
    }

    /**
     * Put a user into the session.
     *
     * @param bool $recordLogin false when a super admin is stepping into the
     *        account rather than the user signing in — stamping last_login_at
     *        then would misreport when the person themselves was last here.
     */
    public static function login(array $user, bool $recordLogin = true): void
    {
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::set('user_role', $user['role']);
        Session::set('user_name', $user['name']);
        // Bind the session to a hostel. Staff carry it on the user row; a student
        // inherits it from their student record (set when they are allocated).
        $hostelId = isset($user['hostel_id']) && $user['hostel_id'] !== null ? (int) $user['hostel_id'] : null;
        if ($hostelId === null && ($user['role'] ?? '') === 'student') {
            $studentHostel = Database::scalar("SELECT hostel_id FROM students WHERE user_id = ? LIMIT 1", [$user['id']]);
            $hostelId = $studentHostel !== false && $studentHostel !== null ? (int) $studentHostel : null;
        }
        Session::set('hostel_id', $hostelId);
        if ($recordLogin) {
            Database::run("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
        }
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    // --- Impersonation ------------------------------------------------------
    //
    // A super admin can step into another account to see exactly what that user
    // sees. The originating admin id is parked in the session so the trip is
    // always reversible, and the session carries the target's role — so while
    // impersonating a student the admin really is limited to a student's rights.

    /** True while the session belongs to someone a super admin stepped into. */
    public static function impersonating(): bool
    {
        return Session::has('impersonator_id');
    }

    /** The super admin behind the current session, or null when not impersonating. */
    public static function impersonatorId(): ?int
    {
        $id = Session::get('impersonator_id');
        return $id === null ? null : (int) $id;
    }

    /** Display name of the super admin behind the current session. */
    public static function impersonatorName(): string
    {
        return (string) Session::get('impersonator_name', 'your account');
    }

    /** Remember who to return to, then step into the target account. */
    public static function beginImpersonation(array $admin, array $target): void
    {
        self::login($target, false);
        // Set after login(): regenerate() keeps session data, but writing these
        // afterwards makes the order independent of that behaviour.
        Session::set('impersonator_id', (int) $admin['id']);
        Session::set('impersonator_name', (string) $admin['name']);
    }

    /** Return to the originating super admin account. */
    public static function endImpersonation(array $admin): void
    {
        Session::forget('impersonator_id');
        Session::forget('impersonator_name');
        self::login($admin, false);
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function role(): ?string
    {
        return Session::get('user_role');
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return Database::first("SELECT * FROM users WHERE id = ? LIMIT 1", [self::id()]);
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::role(), $roles, true);
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT);
    }
}
