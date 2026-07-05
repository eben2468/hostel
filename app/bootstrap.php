<?php
/**
 * Application bootstrap: config, autoloader, helpers, session.
 * Required once by the public front controller.
 */

require_once dirname(__DIR__) . '/config/config.php';

/**
 * PSR-4-ish autoloader.
 * Namespace "App\" maps to the /app directory; every namespace segment except
 * the class name is lowercased (App\Core\Database -> app/core/Database.php).
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    $className = array_pop($parts);
    $dir = array_map('strtolower', $parts);
    $path = APP_PATH . '/' . implode('/', $dir) . '/' . $className . '.php';
    if (is_file($path)) {
        require $path;
    }
});

// Vendored PHPMailer (namespace PHPMailer\PHPMailer\*) lives in /vendor/phpmailer.
spl_autoload_register(function (string $class): void {
    $prefix = 'PHPMailer\\PHPMailer\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = ROOT_PATH . '/vendor/phpmailer/' . substr($class, strlen($prefix)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once APP_PATH . '/helpers/helpers.php';

App\Core\Session::start();
