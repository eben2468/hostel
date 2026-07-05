<?php
/**
 * Front controller — all web requests route through here.
 */
require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Router;
use App\Core\Auth;
use App\Core\View;
use App\Models\Setting;

// Maintenance mode: when enabled, students are shown an offline page.
// Staff and admins continue to work normally; logout is always reachable.
if (Auth::role() === 'student'
    && Setting::get('maintenance_mode') === '1'
    && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/logout')) {
    http_response_code(503);
    View::render('errors/maintenance', [], 'blank');
    exit;
}

$router = new Router();
require_once APP_PATH . '/../config/routes.php';

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
