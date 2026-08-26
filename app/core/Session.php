<?php
namespace App\Core;

/** Session helpers including flash messages and CSRF storage. */
class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Refuse a session id the browser was not given by us, so an
            // attacker cannot plant one and wait for the victim to sign in.
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');

            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                // Over TLS the cookie must never be sent in clear: a single
                // plain-HTTP request would otherwise hand the session id to
                // anyone on the network.
                'secure'   => self::isHttps(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
        self::enforceTimeout();
    }

    /**
     * True when this request arrived over TLS.
     *
     * The forwarded header is honoured because shared hosting usually
     * terminates TLS at a proxy. A client that forges it only makes its own
     * cookie stricter, so it cannot be used against anyone else.
     */
    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
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
