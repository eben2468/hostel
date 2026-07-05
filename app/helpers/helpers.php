<?php

use App\Core\Csrf;
use App\Core\Session;
use App\Core\Auth;

/** Escape output for safe HTML rendering. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Build a URL relative to the public base. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Asset URL helper. */
function asset(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/** Memoized read of a branding setting (avoids repeat queries per request). */
function brand_setting(string $key): ?string
{
    static $cache = [];
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = \App\Models\Setting::get($key);
    }
    return $cache[$key];
}

/** Configured system name shown across the app; falls back to the app constant. */
function brand_name(): string
{
    $name = brand_setting('institution_name');
    return $name !== null && $name !== '' ? $name : APP_NAME;
}

/** Short brand label for tight spaces (sidebar, page title); falls back to APP_SHORT. */
function brand_short(): string
{
    $name = brand_setting('institution_name');
    return $name !== null && $name !== '' ? $name : APP_SHORT;
}

/** URL of the uploaded system logo, or null when none is set. */
function brand_logo(): ?string
{
    $logo = brand_setting('system_logo');
    return $logo !== null && $logo !== '' ? url('/uploads/' . $logo) : null;
}

/** Render the CSRF hidden field. */
function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

/** Old input value for repopulating forms after validation errors. */
function old(string $key, $default = '')
{
    $old = Session::get('_old', []);
    return e($old[$key] ?? $default);
}

/** Format an amount as currency. */
function money($amount): string
{
    return CURRENCY_SYMBOL . ' ' . number_format((float) $amount, 2);
}

/** Format a datetime nicely. */
function datef(?string $date, string $format = 'd M Y'): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '—';
}

/** True if the current path matches (for active nav highlighting). */
function is_active(string $segment): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_contains($uri, $segment);
}

function auth(): ?array
{
    return Auth::user();
}

/** A human-friendly label and colour for a status string. */
function status_badge(string $status): string
{
    $map = [
        'active'      => 'bg-green-100 text-green-700',
        'approved'    => 'bg-green-100 text-green-700',
        'paid'        => 'bg-green-100 text-green-700',
        'completed'   => 'bg-green-100 text-green-700',
        'available'   => 'bg-green-100 text-green-700',
        'pending'     => 'bg-yellow-100 text-yellow-700',
        'partial'     => 'bg-yellow-100 text-yellow-700',
        'waiting'     => 'bg-yellow-100 text-yellow-700',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'assigned'    => 'bg-blue-100 text-blue-700',
        'reserved'    => 'bg-blue-100 text-blue-700',
        'occupied'    => 'bg-indigo-100 text-indigo-700',
        'rejected'    => 'bg-red-100 text-red-700',
        'unpaid'      => 'bg-red-100 text-red-700',
        'closed'      => 'bg-gray-200 text-gray-700',
        'maintenance' => 'bg-orange-100 text-orange-700',
        'inactive'    => 'bg-gray-200 text-gray-700',
        'suspended'   => 'bg-red-100 text-red-700',
    ];
    $cls = $map[strtolower($status)] ?? 'bg-gray-100 text-gray-700';
    $label = ucwords(str_replace('_', ' ', $status));
    return '<span class="px-2 py-1 rounded-full text-xs font-medium ' . $cls . '">' . e($label) . '</span>';
}

/** Roles available in the system with display labels. */
function roles_list(): array
{
    return [
        'admin'       => 'System Administrator',
        'hostel_admin' => 'Hostel Administrator',
        'finance'     => 'Finance Officer',
        'maintenance' => 'Maintenance Officer',
        'security'    => 'Security Officer',
        'student'     => 'Student',
    ];
}

function role_label(?string $role): string
{
    return roles_list()[$role] ?? ucfirst((string) $role);
}
