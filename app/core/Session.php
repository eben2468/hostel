<?php
namespace App\Core;

/** Session helpers including flash messages and CSRF storage. */
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        self::enforceTimeout();
    }

    private static function enforceTimeout(): void
    {
        $now = time();
        if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            self::destroy();
            return;
        }
        $_SESSION['last_activity'] = $now;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    // --- Flash messages -----------------------------------------------------
    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    public static function getFlashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }
}
