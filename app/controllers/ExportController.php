<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csv;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Room;

class ExportController extends Controller
{
    public function students(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        // The Students screen passes its filters through, so what downloads is
        // the list the admin is looking at — freshers who have paid their hall
        // dues, say — and not silently the whole hall.
        $duesType = trim($_GET['dues_type'] ?? '');
        if (!isset(\App\Models\Hostel::STUDENT_TYPES[$duesType])) {
            $duesType = '';
        }
        [$where, $params] = (new \App\Models\Student())->filters(
            trim($_GET['q'] ?? ''), trim($_GET['status'] ?? ''), $duesType
        );
        $rows = Database::all(
            "SELECT student_id, full_name, gender, programme, department, level, phone, email,
                    guardian_name, guardian_phone, guardian_relationship, status
             FROM students{$where} ORDER BY full_name", $params
        );
        Audit::log('export', 'students', null, count($rows) . ' rows'
            . ($duesType !== '' ? " (dues_type={$duesType})" : ''));
        // Guardian details ride along so an office can print a contact list.
        Csv::download('students', $rows, [
            'student_id' => 'Student ID', 'full_name' => 'Full Name', 'gender' => 'Gender',
            'programme' => 'Programme', 'department' => 'Department', 'level' => 'Level',
            'phone' => 'Phone', 'email' => 'Email',
            'guardian_name' => 'Guardian', 'guardian_phone' => 'Guardian Phone',
            'guardian_relationship' => 'Relationship', 'status' => 'Status',
        ]);
    }

    public function payments(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        [$scope, $bind] = Scope::onStudent('p.student_id');
        [$term, $termB] = $this->termFilter('p');
        $rows = Database::all(
            "SELECT p.receipt_no, s.full_name, s.student_id, i.invoice_no, p.amount, p.method, p.reference, p.status, p.paid_at
             FROM payments p JOIN students s ON s.id=p.student_id
             LEFT JOIN invoices i ON i.id=p.invoice_id WHERE 1{$scope}{$term} ORDER BY p.paid_at DESC",
            array_merge($bind, $termB)
        );
        Audit::log('export', 'payments', null, count($rows) . ' rows');
        Csv::download('payments', $rows, [
            'receipt_no' => 'Receipt No', 'full_name' => 'Student', 'student_id' => 'Student ID',
            'invoice_no' => 'Invoice', 'amount' => 'Amount', 'method' => 'Method',
            'reference' => 'Reference', 'status' => 'Status', 'paid_at' => 'Date',
        ]);
    }

    public function invoices(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        [$scope, $bind] = Scope::onStudent('i.student_id');
        [$term, $termB] = $this->termFilter('i');
        $rows = Database::all(
            "SELECT i.invoice_no, s.full_name, s.student_id, i.description, i.amount, i.amount_paid, i.balance, i.status, i.due_date
             FROM invoices i JOIN students s ON s.id=i.student_id WHERE 1{$scope}{$term} ORDER BY i.created_at DESC",
            array_merge($bind, $termB)
        );
        Audit::log('export', 'invoices', null, count($rows) . ' rows');
        Csv::download('invoices', $rows, [
            'invoice_no' => 'Invoice No', 'full_name' => 'Student', 'student_id' => 'Student ID',
            'description' => 'Description', 'amount' => 'Amount', 'amount_paid' => 'Paid',
            'balance' => 'Balance', 'status' => 'Status', 'due_date' => 'Due Date',
        ]);
    }

    /** Room applications, scoped and optionally filtered to the report's term. */
    public function applications(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        [$scope, $bind] = Scope::on('a.preferred_hostel_id');
        [$term, $termB] = $this->termFilter('a');
        $rows = Database::all(
            "SELECT s.full_name, s.student_id, h.name AS hostel, a.preferred_room_type, a.status,
                    a.student_type, a.payment_reference, a.payment_amount, a.payment_status, a.review_note,
                    a.academic_year, a.semester, a.created_at
             FROM applications a JOIN students s ON s.id=a.student_id
             LEFT JOIN hostels h ON h.id=a.preferred_hostel_id
             WHERE 1{$scope}{$term} ORDER BY a.created_at DESC",
            array_merge($bind, $termB)
        );
        Audit::log('export', 'applications', null, count($rows) . ' rows');
        // The dues columns are here so an admin can reconcile the references
        // against a bank statement in a spreadsheet.
        Csv::download('applications', $rows, [
            'full_name' => 'Student', 'student_id' => 'Student ID', 'hostel' => 'Preferred Hostel',
            'preferred_room_type' => 'Room Type', 'status' => 'Status',
            'student_type' => 'Student Category', 'payment_reference' => 'Dues Reference ID',
            'payment_amount' => 'Dues Expected', 'payment_status' => 'Dues Check',
            'review_note' => 'Review Note',
            'academic_year' => 'Year', 'semester' => 'Semester', 'created_at' => 'Applied',
        ]);
    }

    /** Complaints, scoped through the host student (general/unbound ones included). */
    public function complaints(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        $scope = ''; $bind = [];
        if (!Scope::isGlobal()) {
            $scope = " AND (c.student_id IN (SELECT id FROM students WHERE hostel_id = ?) OR c.student_id IS NULL)";
            $bind  = [Scope::hostelId()];
        }
        $rows = Database::all(
            "SELECT s.full_name, s.student_id, c.category, c.title, c.priority, c.status, c.room_number, c.created_at
             FROM complaints c LEFT JOIN students s ON s.id=c.student_id
             WHERE 1{$scope} ORDER BY c.created_at DESC",
            $bind
        );
        Audit::log('export', 'complaints', null, count($rows) . ' rows');
        Csv::download('complaints', $rows, [
            'full_name' => 'Student', 'student_id' => 'Student ID', 'category' => 'Category',
            'title' => 'Title', 'priority' => 'Priority', 'status' => 'Status',
            'room_number' => 'Room', 'created_at' => 'Reported',
        ]);
    }

    /**
     * The room roster — which room each student sleeps in, and who they are.
     *
     * Ordered the way an office walks the building: hostel, then room in floor
     * order (Ground, First, Second, Top, numerically within each), then student
     * name, so the file can be read against the doors themselves.
     *
     * This is tonight's roster: only the allocations that actually put a student
     * in a bed. The Reports page links here *without* its term, unlike the other
     * exports — an allocation is only stamped with a term when one was recorded,
     * so filtering by the selected term would quietly drop most of the hall.
     *
     * A caller that does pass ?year=&sem= gets that term's record instead, which
     * additionally needs the students who have since checked out and the
     * allocations parked when the term ended; without those a past term would
     * download as an empty file.
     */
    public function allocations(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        [$scope, $bind]  = Scope::on('r.hostel_id');
        [$term, $termB]  = $this->termFilter('a');

        // 'cancelled' and 'transferred' allocations never describe where someone
        // sleeps, so they are left out of both variants.
        $states = $term === ''
            ? ['active', 'checked_in']
            : ['active', 'checked_in', 'checked_out', 'suspended'];
        $placeholders = implode(', ', array_fill(0, count($states), '?'));

        $rows = Database::all(
            "SELECT h.name AS hostel, bl.name AS block, f.number AS floor,
                    r.room_number, r.room_type, b.bed_number,
                    s.full_name, s.student_id, s.gender, s.level, s.phone,
                    s.guardian_name, s.guardian_phone,
                    a.status, a.check_in_at, a.academic_year, a.semester
             FROM allocations a
             JOIN students s ON s.id = a.student_id
             JOIN rooms r    ON r.id = a.room_id
             LEFT JOIN hostels h ON h.id = r.hostel_id
             LEFT JOIN blocks bl ON bl.id = r.block_id
             LEFT JOIN floors f  ON f.id = r.floor_id
             LEFT JOIN beds b    ON b.id = a.bed_id
             WHERE a.status IN ({$placeholders}){$scope}{$term}
             ORDER BY h.name, " . Room::ORDER_BY_NUMBER . ", b.bed_number, s.full_name",
            array_merge($states, $bind, $termB)
        );
        Audit::log('export', 'allocations', null, count($rows) . ' rows');
        // Guardian details ride along for the same reason they do on the students
        // export: a night-duty roster is also the sheet you reach for a next of kin.
        Csv::download('allocations', $rows, [
            'hostel' => 'Hostel', 'block' => 'Block', 'floor' => 'Floor',
            'room_number' => 'Room', 'room_type' => 'Room Type', 'bed_number' => 'Bed',
            'full_name' => 'Student', 'student_id' => 'Student ID', 'gender' => 'Gender',
            'level' => 'Level', 'phone' => 'Phone',
            'guardian_name' => 'Guardian', 'guardian_phone' => 'Guardian Phone',
            'status' => 'Status', 'check_in_at' => 'Checked In',
            'academic_year' => 'Year', 'semester' => 'Semester',
        ]);
    }

    /** Occupancy summary per hostel, optionally for the report's term. */
    public function occupancy(): void
    {
        $this->requireAuth('admin', 'hostel_admin', 'finance');
        [$ho, $hoB] = Scope::on('h.id');
        $year = trim($_GET['year'] ?? '');
        $sem  = trim($_GET['sem'] ?? '');
        if ($year !== '' && $sem !== '') {
            $rows = Database::all(
                "SELECT h.name AS hostel,
                        COALESCE((SELECT COUNT(*) FROM rooms r WHERE r.hostel_id=h.id),0) AS rooms,
                        COALESCE((SELECT SUM(r.capacity) FROM rooms r WHERE r.hostel_id=h.id),0) AS capacity,
                        (SELECT COUNT(*) FROM allocations a JOIN rooms r ON r.id=a.room_id
                          WHERE r.hostel_id=h.id AND a.academic_year=? AND a.semester=?
                            AND a.status IN ('active','checked_in','checked_out')) AS occupied
                 FROM hostels h WHERE 1{$ho} GROUP BY h.id, h.name ORDER BY h.name",
                array_merge([$year, $sem], $hoB)
            );
        } else {
            $rows = Database::all(
                "SELECT h.name AS hostel, COUNT(r.id) AS rooms,
                        COALESCE(SUM(r.capacity),0) AS capacity, COALESCE(SUM(r.occupied),0) AS occupied
                 FROM hostels h LEFT JOIN rooms r ON r.hostel_id=h.id
                 WHERE 1{$ho} GROUP BY h.id, h.name ORDER BY h.name",
                $hoB
            );
        }
        foreach ($rows as &$r) {
            $r['occupancy'] = (int) $r['capacity'] > 0 ? round($r['occupied'] / $r['capacity'] * 100) . '%' : '0%';
        }
        unset($r);
        Audit::log('export', 'occupancy', null, count($rows) . ' rows');
        Csv::download('occupancy', $rows, [
            'hostel' => 'Hostel', 'rooms' => 'Rooms', 'capacity' => 'Bed Capacity',
            'occupied' => 'Occupied', 'occupancy' => 'Occupancy',
        ]);
    }

    /** Optional academic-term WHERE fragment from ?year=&sem= (empty = all terms). */
    private function termFilter(string $prefix = ''): array
    {
        $year = trim($_GET['year'] ?? '');
        $sem  = trim($_GET['sem'] ?? '');
        if ($year === '' || $sem === '') {
            return ['', []];
        }
        $p = $prefix !== '' ? $prefix . '.' : '';
        return [" AND {$p}academic_year = ? AND {$p}semester = ?", [$year, $sem]];
    }

    public function audit(): void
    {
        $this->requireAuth('admin');
        $rows = Database::all(
            "SELECT a.created_at, u.name AS user_name, a.role, a.action, a.module, a.record_id, a.details, a.ip_address
             FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 5000"
        );
        Csv::download('audit_logs', $rows, [
            'created_at' => 'When', 'user_name' => 'User', 'role' => 'Role', 'action' => 'Action',
            'module' => 'Module', 'record_id' => 'Record', 'details' => 'Details', 'ip_address' => 'IP',
        ]);
    }
}
