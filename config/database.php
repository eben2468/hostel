<?php
/**
 * Database connection settings (XAMPP / MariaDB defaults).
 */
return [
    'host'     => '127.0.0.1',
    'port'     => '3306',
    'database' => 'chms_hostel',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
