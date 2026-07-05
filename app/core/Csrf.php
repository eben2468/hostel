<?php
namespace App\Core;

/** CSRF token generation and verification. */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    /** Hidden input field for forms. */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . self::token() . '">';
    }

    public static function verify(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** Abort with 419 when the submitted token is invalid. */
    public static function check(): void
    {
        $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::verify($token)) {
            http_response_code(419);
            Session::flash('error', 'Your session expired or the request was invalid. Please try again.');
            $back = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/';
            header('Location: ' . $back);
            exit;
        }
    }
}
