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
define('APP_ENV', 'development');

// Currency used across the finance module.
define('CURRENCY', 'GHS');
// The cedi glyph is written as its Unicode codepoint (U+20B5) so this whole
// source file stays pure ASCII. Some upload/FTP tools corrupt raw multibyte
// characters (which showed up as "262145" in front of amounts); PHP rebuilds
// the correct UTF-8 cedi sign at runtime no matter how the file was transferred.
define('CURRENCY_SYMBOL', "GH\u{20B5}");

// Session lifetime (seconds) before forced re-login.
define('SESSION_TIMEOUT', 60 * 60 * 2); // 2 hours

// Failed login attempts before a temporary lockout.
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

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
