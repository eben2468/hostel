<?php
namespace App\Core;

/** Authentication and current-user helpers. */
class Auth
{
    public static function attempt(string $login, string $password): bool
    {
        $user = Database::first(
            "SELECT * FROM users WHERE (username = ? OR email = ?) LIMIT 1",
            [$login, $login]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }
        if ((int) $user['is_active'] !== 1) {
            return false;
        }

        self::login($user);
        return true;
    }

    public static function login(array $user): void
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
        Database::run("UPDATE users SET last_login_at = NOW() WHERE id = ?", [$user['id']]);
    }

    public static function logout(): void
    {
        Session::destroy();
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
