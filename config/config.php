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

// Base URL of the public folder. The app lives under /hostel/public in XAMPP.
define('BASE_URL', '/hostel/public');

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
define('CURRENCY_SYMBOL', 'GH₵');

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
