<?php
/**
 * Application configuration.
 * Adjust these values for your environment.
 */

// ---------------------------------------------------------------------------
// Application
// ---------------------------------------------------------------------------
define('APP_NAME', 'Complete Hostel Management System');
define('APP_SHORT', 'CHMS');

// Base URL of the public folder.
//
// Auto-detected from the web root so the same code works whether the app lives
// in a subfolder (XAMPP serves it at /hostel/public) or at the domain root on a
// live host (where it resolves to /public). It does this by taking the public
// folder's real path and stripping the server's document root off the front.
//
// If your host has an unusual layout, you can hardcode it instead, e.g.:
//     define('BASE_URL', '/public');
if (!defined('BASE_URL')) {
    $publicFs = str_replace('\\', '/', realpath(__DIR__ . '/../public') ?: __DIR__ . '/../public');
    $docRoot  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
    if ($docRoot !== '' && str_starts_with($publicFs, $docRoot)) {
        define('BASE_URL', rtrim(substr($publicFs, strlen($docRoot)), '/'));
    } else {
        // Fallback for CLI or unusual setups.
        define('BASE_URL', '/hostel/public');
    }
}

// Absolute filesystem paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('VIEW_PATH', APP_PATH . '/views');
define('UPLOAD_PATH', ROOT_PATH . '/public/uploads');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// Environment: 'development' shows errors, 'production' hides them.
// Auto-detected from the host so this file needs NO per-server editing (which
// is what caused git-pull conflicts before): localhost/XAMPP runs as
// development; any real domain runs as production and hides errors from users.
$__host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
define('APP_ENV', in_array($__host, ['localhost', '127.0.0.1', '::1'], true) ? 'development' : 'production');

// Currency used across the finance module.
define('CURRENCY', 'GHS');
// NOTE: this is CURRENCY_SIGN, not CURRENCY_SYMBOL. Some PHP builds (e.g. PHP 8.4
// with certain extensions) already define a global constant named CURRENCY_SYMBOL
// (value 262145), and PHP's define() cannot override an existing constant — so
// that name silently kept the extension's value and displayed "262145" before
// every amount. Using our own name sidesteps the collision entirely.
// The cedi glyph is written as its codepoint (U+20B5) so the file stays ASCII.
define('CURRENCY_SIGN', "GH\u{20B5}");

// Session lifetime (seconds) before forced re-login.
define('SESSION_TIMEOUT', 60 * 60 * 2); // 2 hours

// Failed login attempts before a temporary lockout.
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// Two-factor authentication (email one-time codes).
// Which roles must use it, and where the codes are sent, are configured by an
// administrator under System Settings — these are only the mechanics.
define('TWOFA_CODE_LENGTH', 6);
define('TWOFA_EXPIRY_MINUTES', 10);   // how long an emailed code stays valid
define('TWOFA_MAX_ATTEMPTS', 5);      // wrong codes allowed before the code dies
define('TWOFA_RESEND_SECONDS', 60);   // cooldown between "resend code" requests

// Payment gateway (Paystack). Fill in real keys in System Settings later.
define('PAYSTACK_PUBLIC_KEY', '');
define('PAYSTACK_SECRET_KEY', '');

// ---------------------------------------------------------------------------
// Error reporting
// ---------------------------------------------------------------------------
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

date_default_timezone_set('Africa/Accra');
