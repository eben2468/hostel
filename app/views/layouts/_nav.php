<?php
/**
 * Returns the sidebar navigation, grouped into labelled sections and
 * filtered to the current user's role.
 * Each item: [label, path, icon (Font Awesome), roles allowed].
 * Return shape: [ ['heading' => string, 'items' => [...]], ... ]
 */
use App\Core\Auth;

$role = Auth::role();

$groups = [
    'Overview' => [
        ['Dashboard',    '/dashboard',    'fa-gauge-high',      ['admin','hostel_admin','finance','maintenance','security','student']],
        ['Notices',      '/notices',      'fa-bullhorn',        ['admin','hostel_admin','finance','maintenance','security','student']],
    ],
    'Residence' => [
        ['Students',     '/students',     'fa-user-graduate',   ['admin','hostel_admin','finance','security']],
        ['Hostels',      '/hostels',      'fa-building',        ['admin','hostel_admin']],
        ['Rooms',        '/rooms',        'fa-door-open',       ['admin','hostel_admin']],
        ['Applications', '/applications', 'fa-file-lines',      ['admin','hostel_admin','student']],
        ['Allocations',  '/allocations',  'fa-bed',             ['admin','hostel_admin']],
        ['Transfers',    '/transfers',    'fa-right-left',      ['admin','hostel_admin']],
    ],
    'Operations' => [
        ['Payments',     '/payments',     'fa-money-bill-wave', ['admin','hostel_admin','finance','student']],
        ['Complaints',   '/complaints',   'fa-screwdriver-wrench', ['admin','hostel_admin','maintenance','student']],
        ['Visitors',     '/visitors',     'fa-user-check',      ['admin','hostel_admin','security']],
        ['Inventory',    '/inventory',    'fa-boxes-stacked',   ['admin','hostel_admin','maintenance']],
    ],
    'Insights' => [
        ['Reports',      '/reports',      'fa-chart-pie',       ['admin','hostel_admin','finance']],
        ['Academic Session', '/academic', 'fa-calendar-day',    ['hostel_admin']],
    ],
    'Administration' => [
        ['Users',        '/users',        'fa-users-gear',      ['admin','hostel_admin']],
        ['Password Requests', '/password-requests', 'fa-user-lock', ['admin','hostel_admin']],
        ['Audit Logs',   '/audit',        'fa-clipboard-list',  ['admin']],
        ['Settings',     '/settings',     'fa-gear',            ['admin']],
    ],
];

$out = [];
foreach ($groups as $heading => $items) {
    $visible = array_values(array_filter($items, fn($i) => in_array($role, $i[3], true)));
    if ($visible) {
        $out[] = ['heading' => $heading, 'items' => $visible];
    }
}
return $out;
