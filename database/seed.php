<?php
/**
 * Seed the database with demo accounts and sample data.
 * Run from the command line:  php database/seed.php
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/core/Database.php';

use App\Core\Database;

function seed_user(string $name, string $username, string $email, string $role): void
{
    $existing = Database::first("SELECT id FROM users WHERE username = ?", [$username]);
    if ($existing) {
        echo "  - user '{$username}' already exists, skipped\n";
        return;
    }
    $password = password_hash('password123', PASSWORD_BCRYPT);
    Database::insert(
        "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?,?,?,?,?,1)",
        [$name, $username, $email, $password, $role]
    );
    echo "  + created {$role}: {$username} / password123\n";
}

echo "Seeding users...\n";
seed_user('System Administrator', 'admin',       'admin@chms.test',       'admin');
// Unity Hall staff (bound to Unity Hall below).
seed_user('Hostel Administrator', 'hosteladmin', 'hosteladmin@chms.test', 'hostel_admin');
seed_user('Finance Officer',      'finance',     'finance@chms.test',     'finance');
seed_user('Maintenance Officer',  'maintenance', 'maintenance@chms.test', 'maintenance');
seed_user('Security Officer',     'security',    'security@chms.test',    'security');
// Palm Grove staff — demonstrates a second, fully isolated hostel ecosystem.
seed_user('Palm Grove Admin',     'hosteladmin2','hosteladmin2@chms.test','hostel_admin');
seed_user('Palm Grove Finance',   'finance2',    'finance2@chms.test',    'finance');

echo "Seeding academic year...\n";
if (!Database::first("SELECT id FROM academic_years WHERE name = ?", ['2025/2026'])) {
    Database::insert("INSERT INTO academic_years (name, is_current) VALUES ('2025/2026', 1)", []);
    echo "  + 2025/2026 (current)\n";
}

echo "Seeding sample hostel + rooms...\n";
$hostelId = Database::scalar("SELECT id FROM hostels WHERE code = ?", ['UNITY-A']);
if (!$hostelId) {
    $hostelId = Database::insert(
        "INSERT INTO hostels (name, code, type, address, capacity, manager, description, facilities, status)
         VALUES (?,?,?,?,?,?,?,?, 'active')",
        ['Unity Hall', 'UNITY-A', 'mixed', 'Main Campus', 120, 'Mr. Mensah',
         'A modern mixed hostel close to lecture halls.', 'WiFi,Water Supply,Security,CCTV,Generator,Laundry']
    );
    $blockId = Database::insert("INSERT INTO blocks (hostel_id, name, code, gender) VALUES (?,?,?,?)",
        [$hostelId, 'Block A', 'A', 'mixed']);
    $floorId = Database::insert("INSERT INTO floors (block_id, number) VALUES (?,?)", [$blockId, 'Ground']);
    foreach (range(1, 8) as $n) {
        $num  = 'A' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
        $cap  = 2;
        $rid  = Database::insert(
            "INSERT INTO rooms (hostel_id, block_id, floor_id, room_number, room_type, capacity, price, status, features)
             VALUES (?,?,?,?,?,?,?, 'available', ?)",
            [$hostelId, $blockId, $floorId, $num, 'double', $cap, 4500.00, 'Fan,Wardrobe,Study Desk']
        );
        for ($b = 1; $b <= $cap; $b++) {
            Database::insert("INSERT INTO beds (room_id, bed_number) VALUES (?,?)", [$rid, 'Bed ' . $b]);
        }
    }
    echo "  + Unity Hall with 8 rooms and beds\n";
}

echo "Seeding a default fee...\n";
if (!Database::first("SELECT id FROM fees WHERE name = ?", ['Standard Accommodation 2025/2026'])) {
    Database::insert(
        "INSERT INTO fees (name, amount, hostel_id, room_type, academic_year, semester, status)
         VALUES (?,?,?,?,?,?, 'active')",
        ['Standard Accommodation 2025/2026', 4500.00, $hostelId, 'double', '2025/2026', 'First']
    );
    echo "  + Standard Accommodation fee\n";
}

echo "Seeding a sample student account...\n";
if (!Database::first("SELECT id FROM users WHERE username = ?", ['student'])) {
    $uid = Database::insert(
        "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?,?,?,?, 'student', 1)",
        ['Ama Owusu', 'student', 'student@chms.test', password_hash('password123', PASSWORD_BCRYPT)]
    );
    Database::insert(
        "INSERT INTO students (user_id, hostel_id, student_id, index_number, full_name, gender, programme, department, level, phone, email, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?, 'active')",
        [$uid, $hostelId, 'STU-0001', 'IDX-1001', 'Ama Owusu', 'female', 'BSc Computer Science', 'Computer Science', '200', '0244000000', 'student@chms.test']
    );
    echo "  + student: student / password123\n";
}

// ---------------------------------------------------------------------------
// Rich demo dataset (idempotent — guarded by a 'demo_seeded' flag)
// ---------------------------------------------------------------------------
if (Database::first("SELECT id FROM settings WHERE `key` = 'demo_seeded'")) {
    echo "Rich demo data already seeded — skipping.\n";
} else {
    echo "Seeding rich demo dataset (this populates dashboards, charts & reports)...\n";

    // Create a hostel with rooms + beds (returns hostel id).
    $makeHostel = function (string $name, string $code, string $type, int $roomCount, float $price): int {
        $existing = Database::scalar("SELECT id FROM hostels WHERE code = ?", [$code]);
        if ($existing) {
            return (int) $existing;
        }
        $gender = $type === 'male' ? 'male' : ($type === 'female' ? 'female' : 'mixed');
        $hid = Database::insert(
            "INSERT INTO hostels (name, code, type, address, capacity, manager, description, facilities, status)
             VALUES (?,?,?,?,?,?,?,?, 'active')",
            [$name, $code, $type, 'Campus Road', $roomCount * 2, 'Hall Manager',
             'Comfortable, secure student accommodation.', 'WiFi,Water Supply,Security,Generator,Laundry']
        );
        $block = Database::insert("INSERT INTO blocks (hostel_id, name, code, gender) VALUES (?,?,?,?)", [$hid, 'Block A', 'A', $gender]);
        $floor = Database::insert("INSERT INTO floors (block_id, number) VALUES (?,?)", [$block, '1']);
        for ($n = 1; $n <= $roomCount; $n++) {
            $rid = Database::insert(
                "INSERT INTO rooms (hostel_id, block_id, floor_id, room_number, room_type, capacity, price, status, features)
                 VALUES (?,?,?,?, 'double', 2, ?, 'available', 'Fan,Wardrobe,Study Desk')",
                [$hid, $block, $floor, $code . '-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT), $price]
            );
            for ($b = 1; $b <= 2; $b++) {
                Database::insert("INSERT INTO beds (room_id, bed_number) VALUES (?,?)", [$rid, 'Bed ' . $b]);
            }
        }
        return (int) $hid;
    };

    $unity = (int) Database::scalar("SELECT id FROM hostels WHERE code = 'UNITY-A'");
    $palm  = $makeHostel('Palm Grove Hall', 'PALM', 'female', 8, 4200.00);
    $cedar = $makeHostel('Cedar Court', 'CEDAR', 'male', 8, 4800.00);

    // --- Students ---------------------------------------------------------
    $maleFirst   = ['Kwame','Kofi','Yaw','Kojo','Kwabena','Akwasi','Fiifi','Nana','Ebenezer','Samuel','Daniel','Emmanuel'];
    $femaleFirst = ['Ama','Akua','Abena','Adwoa','Afia','Esi','Yaa','Efua','Grace','Mary','Comfort','Linda'];
    $surnames    = ['Mensah','Owusu','Boateng','Asante','Annan','Appiah','Darko','Frimpong','Osei','Agyeman','Adjei','Bonsu','Danso','Yeboah'];
    $programmes  = [['BSc Computer Science','Computer Science'],['BSc Engineering','Engineering'],['BA Economics','Economics'],
                    ['BSc Nursing','Nursing'],['BBA Accounting','Business'],['BSc Mathematics','Mathematics']];

    $studentIds = [];
    for ($i = 2; $i <= 31; $i++) {
        $sid = 'STU-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
        $found = Database::scalar("SELECT id FROM students WHERE student_id = ?", [$sid]);
        if ($found) { $studentIds[] = (int) $found; continue; }

        $isMale = $i % 2 === 0;
        $name = ($isMale ? $maleFirst[$i % count($maleFirst)] : $femaleFirst[$i % count($femaleFirst)]) . ' ' . $surnames[$i % count($surnames)];
        [$prog, $dept] = $programmes[$i % count($programmes)];
        $level = (string) (100 * (1 + ($i % 4)));
        $email = strtolower(str_replace(' ', '.', $name)) . $i . '@chms.test';
        $uid = Database::insert(
            "INSERT INTO users (name, username, email, password, role, is_active) VALUES (?,?,?,?, 'student', 1)",
            [$name, $sid, $email, password_hash('password123', PASSWORD_BCRYPT)]
        );
        $studentIds[] = Database::insert(
            "INSERT INTO students (user_id, student_id, index_number, full_name, gender, programme, department, level, phone, email, status)
             VALUES (?,?,?,?,?,?,?,?,?,?, 'active')",
            [$uid, $sid, 'IDX' . (2000 + $i), $name, $isMale ? 'male' : 'female', $prog, $dept, $level, '02' . rand(10000000, 49999999), $email]
        );
    }

    // --- Allocations + invoices + payments (spread across 6 months) -------
    $methods  = ['cash','paystack','momo','bank_transfer'];
    $freeBeds = Database::all(
        "SELECT b.id AS bed_id, b.room_id, r.price, r.room_number
         FROM beds b JOIN rooms r ON r.id = b.room_id
         WHERE b.status = 'available' ORDER BY RAND()"
    );
    $bi = 0;
    foreach ($studentIds as $k => $stid) {
        if ($k % 4 === 3) { continue; }                 // leave ~25% unallocated
        if (!isset($freeBeds[$bi])) { break; }
        if (Database::first("SELECT id FROM allocations WHERE student_id = ? AND status IN ('active','checked_in')", [$stid])) { continue; }

        $bed = $freeBeds[$bi++];
        $allocAt = date('Y-m-d H:i:s', strtotime('-' . rand(10, 160) . ' days'));
        $allocId = Database::insert(
            "INSERT INTO allocations (student_id, room_id, bed_id, academic_year, semester, allocated_by, status, created_at)
             VALUES (?,?,?, '2025/2026', 'First', 1, ?, ?)",
            [$stid, $bed['room_id'], $bed['bed_id'], ($k % 3 === 0 ? 'checked_in' : 'active'), $allocAt]
        );
        Database::run("UPDATE beds SET status='occupied', student_id=? WHERE id=?", [$stid, $bed['bed_id']]);

        $amount = (float) $bed['price'];
        $invId = Database::insert(
            "INSERT INTO invoices (invoice_no, student_id, allocation_id, description, amount, balance, due_date, status, created_at)
             VALUES (?,?,?,?,?,?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'unpaid', ?)",
            ['INV-' . strtoupper(substr(md5($allocId . $stid), 0, 8)), $stid, $allocId,
             'Accommodation: Room ' . $bed['room_number'], $amount, $amount, $allocAt]
        );

        // 60% pay in full, 20% partial, 20% unpaid.
        $r = $k % 5;
        $paid = $r < 3 ? $amount : ($r === 3 ? round($amount * 0.5, 2) : 0.0);
        if ($paid > 0) {
            $method = $methods[$k % count($methods)];
            $paidAt = date('Y-m-d H:i:s', strtotime('-' . rand(0, 5) . ' months -' . rand(0, 27) . ' days'));
            Database::insert(
                "INSERT INTO payments (receipt_no, invoice_id, student_id, amount, method, reference, status, verified_by, paid_at, created_at)
                 VALUES (?,?,?,?,?,?, 'completed', 1, ?, ?)",
                ['RCP-' . date('Ymd', strtotime($paidAt)) . '-' . strtoupper(substr(md5($invId . $paid), 0, 5)),
                 $invId, $stid, $paid, $method, strtoupper($method) . '-' . rand(100000, 999999), $paidAt, $paidAt]
            );
            $balance = max(0, $amount - $paid);
            Database::run("UPDATE invoices SET amount_paid=?, balance=?, status=? WHERE id=?",
                [$paid, $balance, $balance <= 0 ? 'paid' : 'partial', $invId]);
        }
    }

    // Recompute room occupancy/status from the active allocations.
    Database::run("UPDATE rooms r SET occupied = (SELECT COUNT(*) FROM allocations a WHERE a.room_id = r.id AND a.status IN ('active','checked_in'))");
    Database::run("UPDATE rooms SET status = CASE WHEN status IN ('maintenance','closed') THEN status WHEN occupied >= capacity THEN 'occupied' ELSE 'available' END");

    // Give every hostel a concrete active session so allocations line up with a
    // term from the start; this is what makes term switching reversible.
    Database::run("UPDATE hostels SET academic_year = COALESCE(academic_year, '2025/2026'), semester = COALESCE(semester, 'First')");

    // --- Applications (mixed statuses) ------------------------------------
    $appStatuses = ['pending','approved','rejected','waiting'];
    foreach (array_slice($studentIds, 0, 12) as $idx => $stid) {
        Database::insert(
            "INSERT INTO applications (student_id, academic_year, semester, preferred_hostel_id, preferred_room_type, status, created_at)
             VALUES (?, '2025/2026', 'First', ?, 'double', ?, ?)",
            [$stid, $unity, $appStatuses[$idx % count($appStatuses)], date('Y-m-d H:i:s', strtotime('-' . rand(1, 90) . ' days'))]
        );
    }

    // --- Complaints -------------------------------------------------------
    $cats   = ['electrical','plumbing','furniture','internet','cleaning','water','noise','other'];
    $prio   = ['low','medium','high','urgent'];
    $cstat  = ['open','assigned','in_progress','completed','closed'];
    $titles = ['Faulty ceiling fan','Leaking tap','Broken wardrobe door','Slow internet','Blocked drainage','No water supply','Noisy neighbours','Flickering lights'];
    foreach ($titles as $ti => $title) {
        Database::insert(
            "INSERT INTO complaints (student_id, category, title, description, priority, room_number, status, created_at)
             VALUES (?,?,?,?,?,?,?, ?)",
            [$studentIds[$ti % count($studentIds)], $cats[$ti % count($cats)], $title,
             'Please attend to this issue at your earliest convenience.', $prio[$ti % count($prio)],
             'A' . str_pad((string) ($ti + 1), 3, '0', STR_PAD_LEFT), $cstat[$ti % count($cstat)],
             date('Y-m-d H:i:s', strtotime('-' . rand(1, 60) . ' days'))]
        );
    }

    // --- Notices ----------------------------------------------------------
    $noticeRows = [
        ['Welcome to 2025/2026', 'Welcome back! Please ensure your hostel dues are paid before the deadline.', 'all', 1],
        ['Water Maintenance', 'Water supply will be interrupted on Saturday from 9am to 2pm.', 'students', 0],
        ['Staff Meeting', 'All staff are to meet on Friday at 10am in the administration block.', 'staff', 0],
        ['Visiting Hours', 'Visiting hours are 8am to 8pm daily. Kindly observe them.', 'students', 1],
    ];
    foreach ($noticeRows as $n) {
        Database::insert(
            "INSERT INTO notices (title, body, audience, is_pinned, created_by, created_at) VALUES (?,?,?,?, 1, ?)",
            [$n[0], $n[1], $n[2], $n[3], date('Y-m-d H:i:s', strtotime('-' . rand(1, 40) . ' days'))]
        );
    }

    // --- Visitors ---------------------------------------------------------
    $vstat = ['pending','approved','checked_in','checked_out'];
    for ($vi = 0; $vi <= 5; $vi++) {
        $status = $vstat[$vi % count($vstat)];
        Database::insert(
            "INSERT INTO visitors (student_id, visitor_name, phone, purpose, visit_date, status, pass_code, created_at)
             VALUES (?,?,?,?,?,?,?, ?)",
            [$studentIds[$vi % count($studentIds)], 'Visitor ' . ($vi + 1), '02' . rand(10000000, 49999999),
             'Family visit', date('Y-m-d'), $status,
             $status === 'pending' ? null : 'VP-' . strtoupper(substr(md5((string) $vi), 0, 6)),
             date('Y-m-d H:i:s', strtotime('-' . rand(0, 20) . ' days'))]
        );
    }

    // --- Inventory --------------------------------------------------------
    $invRows = [
        ['Bunk Beds','bed',$unity,40,10], ['Mattresses','mattress',$unity,40,15], ['Reading Tables','table',$unity,30,8],
        ['Plastic Chairs','chair',$palm,60,20], ['Wardrobes','wardrobe',$palm,25,5],
        ['Ceiling Fans','fan',$cedar,30,6], ['Fire Extinguishers','fire_extinguisher',$cedar,8,12],
    ];
    foreach ($invRows as $it) {
        if (Database::first("SELECT id FROM inventory WHERE name=? AND hostel_id=?", [$it[0], $it[2]])) { continue; }
        Database::insert(
            "INSERT INTO inventory (name, category, hostel_id, quantity, `condition`, reorder_level) VALUES (?,?,?,?, 'good', ?)",
            [$it[0], $it[1], $it[2], $it[3], $it[4]]
        );
    }

    Database::run("INSERT INTO settings (`key`, value) VALUES ('demo_seeded', '1') ON DUPLICATE KEY UPDATE value='1'");
    echo "  + " . count($studentIds) . " students, plus allocations, invoices, payments, applications, complaints, notices, visitors & inventory\n";
}

// ---------------------------------------------------------------------------
// Bind staff accounts to their hostel (data isolation). The super admin stays
// global (hostel_id NULL). Idempotent — safe to re-run.
// ---------------------------------------------------------------------------
echo "Binding staff to hostels...\n";
$unityId = Database::scalar("SELECT id FROM hostels WHERE code = ?", ['UNITY-A']);
$palmId  = Database::scalar("SELECT id FROM hostels WHERE code = ?", ['PALM']);
$bindStaff = function (string $username, $hostelId): void {
    if ($hostelId) {
        Database::run("UPDATE users SET hostel_id = ? WHERE username = ?", [(int) $hostelId, $username]);
    }
};
$bindStaff('hosteladmin',  $unityId);
$bindStaff('finance',      $unityId);
$bindStaff('maintenance',  $unityId);
$bindStaff('security',     $unityId);
$bindStaff('hosteladmin2', $palmId);
$bindStaff('finance2',     $palmId);
Database::run("UPDATE users SET hostel_id = NULL WHERE role = 'admin'"); // super admin is global
echo "  + Unity Hall: hosteladmin, finance, maintenance, security\n";
echo "  + Palm Grove: hosteladmin2, finance2\n";

// ---------------------------------------------------------------------------
// Bind students to hostels (data isolation). Allocated students belong to the
// hostel they were allocated into; the rest are spread across the hostels so
// each has its own residents. Idempotent — only fills students still unbound.
// ---------------------------------------------------------------------------
echo "Binding students to hostels...\n";
Database::run(
    "UPDATE students s
     JOIN allocations a ON a.student_id = s.id AND a.status IN ('active','checked_in')
     JOIN rooms r       ON r.id = a.room_id
     SET s.hostel_id = r.hostel_id
     WHERE s.hostel_id IS NULL"
);
$cedarId = Database::scalar("SELECT id FROM hostels WHERE code = ?", ['CEDAR']);
$cycle = array_values(array_filter([$unityId, $palmId, $cedarId]));
if ($cycle) {
    foreach (Database::all("SELECT id FROM students WHERE hostel_id IS NULL") as $j => $row) {
        Database::run("UPDATE students SET hostel_id = ? WHERE id = ?", [(int) $cycle[$j % count($cycle)], (int) $row['id']]);
    }
}
echo "  + all students bound to a hostel\n";

echo "\nDone. Default password for all demo accounts: password123\n";
