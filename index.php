<?php
/**
 * Root entry point.
 *
 * The application's real web root is still /public — it holds the assets,
 * uploads and the security boundary that keeps app/, config/ and storage/ out
 * of web reach. This thin file lets index.php live OUTSIDE public/ by simply
 * forwarding bare requests at the project root into the public front
 * controller. Canonical application URLs remain under /public, so nothing about
 * routing, assets or security changes.
 */

require_once __DIR__ . '/config/config.php';

// When reached at the bare project root ("/hostel" or "/hostel/index.php"),
// normalise the request to the public base so the router resolves the home
// route ("/"). Deep links continue to be served directly under /public.
$basePath  = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
$appPrefix = rtrim(dirname($basePath), '/');                       // e.g. "/hostel"
$path      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$rest      = ($appPrefix !== '' && str_starts_with($path, $appPrefix))
                ? substr($path, strlen($appPrefix))
                : $path;

if (in_array(trim($rest, '/'), ['', 'index.php'], true)) {
    $_SERVER['REQUEST_URI'] = rtrim($basePath, '/') . '/';
}

require __DIR__ . '/public/index.php';
