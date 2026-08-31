<?php
/**
 * TEMPORARY deployment diagnostic — DELETE THIS FILE once you have the answer.
 *
 * Production hides errors, so a 500 tells you nothing. This reports what the
 * server actually has: PHP version, which migrations have run, whether the
 * feature's files made it up, and the real exception text from the queries the
 * Debtors page runs.
 *
 * Read-only: it never writes, alters or drops anything.
 * Reachable only with the key below, so a stray copy is not a free schema dump.
 *
 *     https://your-site/public/_diag.php?key=826715c007c304e0
 */
const DIAG_KEY = '826715c007c304e0';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!hash_equals(DIAG_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(404);
    exit("Not found\n");
}

// Show everything for this request only; the app's own config hides errors.
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Database;

function line(string $label, $value): void { printf("%-34s %s\n", $label . ':', $value); }
function head(string $t): void { echo "\n== {$t} ==\n"; }

echo "CHMS deployment diagnostic — " . date('Y-m-d H:i:s') . "\n";

head('Environment');
line('PHP version', PHP_VERSION);
line('PHP 8.0+ required', version_compare(PHP_VERSION, '8.0', '>=') ? 'yes — OK' : '** NO — this alone breaks the app **');
line('APP_ENV', APP_ENV);
line('BASE_URL', BASE_URL);
line('zip extension', extension_loaded('zip') ? 'on' : 'off (xlsx uses the built-in fallback)');
line('zlib extension', extension_loaded('zlib') ? 'on — OK' : '** off — xlsx upload cannot work **');
line('logs dir writable', is_writable(STORAGE_PATH . '/logs') ? 'yes' : 'NO — that is why the log looked empty');

head('Files this feature needs');
$files = [
    'app/controllers/DebtorController.php',
    'app/models/DuesDebtor.php',
    'app/services/SheetReader.php',
    'app/services/DebtorListParser.php',
    'app/views/debtors/index.php',
    'app/views/debtors/form.php',
    'app/views/debtors/upload.php',
    'app/views/partials/_dues_panel.php',
    'app/views/partials/_arrears_panel.php',
];
$missingFiles = 0;
foreach ($files as $f) {
    $ok = is_file(ROOT_PATH . '/' . $f);
    if (!$ok) { $missingFiles++; }
    line($f, $ok ? 'present' : '** MISSING — upload it **');
}

head('Database');
try {
    Database::pdo();
    line('connection', 'OK');
    line('server version', Database::scalar('SELECT VERSION()'));
    line('database name', Database::scalar('SELECT DATABASE()'));
} catch (\Throwable $e) {
    line('connection', '** FAILED: ' . $e->getMessage() . ' **');
    exit("\nCannot continue without a database connection.\n");
}

head('Migrations');
$checks = [
    'dues_debtors table'        => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dues_debtors'",
    'dues_debtor_batches table' => "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dues_debtor_batches'",
    'hostels dues_* columns'    => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='hostels' AND COLUMN_NAME LIKE 'dues%'",
    'applications.payment_reference' => "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='applications' AND COLUMN_NAME='payment_reference'",
];
$expected = ['dues_debtors table' => 1, 'dues_debtor_batches table' => 1,
             'hostels dues_* columns' => 13, 'applications.payment_reference' => 1];
$missingMigration = false;
foreach ($checks as $label => $sql) {
    $got = (int) Database::scalar($sql);
    $ok = $got >= $expected[$label];
    if (!$ok) { $missingMigration = true; }
    line($label, $got . ($ok ? ' — OK' : ' ** expected ' . $expected[$label] . ' — MIGRATION NOT RUN **'));
}

head('Collations (must match for debtor matching)');
foreach (['students', 'dues_debtors'] as $t) {
    $c = Database::scalar("SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?", [$t]);
    line($t, $c ?: '(table absent)');
}

head('The actual queries the Debtors page runs');
try {
    $rows = \App\Models\DuesDebtor::batchesFor(null);
    line('batchesFor()', 'OK — ' . count($rows) . ' batch(es)');
} catch (\Throwable $e) {
    line('batchesFor()', '** ' . get_class($e) . ': ' . $e->getMessage() . ' **');
}
try {
    $rows = \App\Models\DuesDebtor::listFor(null);
    line('listFor()', 'OK — ' . count($rows) . ' row(s)');
} catch (\Throwable $e) {
    line('listFor()', '** ' . get_class($e) . ': ' . $e->getMessage() . ' **');
}
try {
    $s = Database::first("SELECT id, student_id, phone, hostel_id FROM students LIMIT 1");
    $d = \App\Models\DuesDebtor::outstandingFor($s ?: []);
    line('outstandingFor()', 'OK — ' . count($d) . ' debt(s) for the first student');
} catch (\Throwable $e) {
    line('outstandingFor()', '** ' . get_class($e) . ': ' . $e->getMessage() . ' **');
}

head('Verdict');
if ($missingFiles) {
    echo "Some files above are MISSING. Upload them, then reload this page.\n";
} elseif ($missingMigration) {
    echo "The migration(s) marked above have not been run on this database.\n"
       . "Import these once via phpMyAdmin (select the database in the sidebar FIRST,\n"
       . "then Import -> choose file -> Go):\n"
       . "    database/migration_hostel_dues.sql\n"
       . "    database/migration_dues_debtors.sql\n";
} else {
    echo "Files and migrations all look correct. If /debtors still errors, copy the\n"
       . "'** ... **' line above (if any) — that is the real exception.\n";
}

echo "\n---\nDELETE public/_diag.php from the server now that you have this.\n";
