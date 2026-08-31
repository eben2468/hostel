<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

/**
 * A student carried over from a previous semester still owing hall dues.
 *
 * Rows come from a list the hall admin uploads. A student is matched against
 * them by student ID *or* phone number, because hall lists are typed by hand
 * and either field may be wrong on any given row.
 */
class DuesDebtor extends Model
{
    protected string $table = 'dues_debtors';
    protected array $fillable = [
        'batch_id', 'hostel_id', 'full_name', 'student_no', 'student_no_norm',
        'phone', 'phone_norm', 'room_label', 'amount', 'academic_year', 'semester',
        'status', 'cleared_by', 'cleared_at', 'note',
    ];

    /** Semesters a debt can be recorded against, matching the Academic screen. */
    public const SEMESTERS = ['First', 'Second', 'Third'];

    /**
     * True when this feature's tables have been migrated in.
     *
     * Checked so the Debtors screen can explain itself instead of throwing on a
     * deployment where the code is live but the migration has not been run yet.
     */
    public static function installed(): bool
    {
        static $installed = null;
        if ($installed === null) {
            try {
                $installed = (int) Database::scalar(
                    "SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME IN ('dues_debtors', 'dues_debtor_batches')"
                ) === 2;
            } catch (\Throwable $e) {
                $installed = false;
            }
        }
        return $installed;
    }

    /**
     * "Base table or view not found" — the one error we tolerate, and only so a
     * half-finished deployment cannot take down pages that merely *consult*
     * this feature. Everything else is re-thrown untouched.
     */
    private static function isMissingTable(\PDOException $e): bool
    {
        return ($e->getCode() === '42S02')
            && (str_contains($e->getMessage(), 'dues_debtors') || str_contains($e->getMessage(), 'dues_debtor_batches'));
    }

    /** Run a debtors query, degrading to $fallback when the tables are absent. */
    private static function guarded(callable $query, array $fallback = [])
    {
        try {
            return $query();
        } catch (\PDOException $e) {
            if (self::isMissingTable($e)) {
                error_log('Hall dues debtors tables are missing — run database/migration_dues_debtors.sql. '
                    . 'Arrears checks are inactive until then.');
                return $fallback;
            }
            throw $e;
        }
    }

    /** Marks the batch that collects rows typed in by hand rather than uploaded. */
    private const MANUAL_FILE = '(added by hand)';

    /**
     * The batch manually-added debtors belong to, created on first use.
     *
     * batch_id is NOT NULL because every row should be traceable to where it
     * came from; hand-typed rows get their own batch per hostel so they are
     * just as traceable, and are never swept away when an upload is deleted.
     */
    public static function manualBatchId(int $hostelId, ?int $userId): int
    {
        $id = Database::scalar(
            "SELECT id FROM dues_debtor_batches WHERE hostel_id = ? AND filename = ? LIMIT 1",
            [$hostelId, self::MANUAL_FILE]
        );
        if ($id) {
            return (int) $id;
        }
        return Database::insert(
            "INSERT INTO dues_debtor_batches (hostel_id, filename, label, row_count, uploaded_by)
             VALUES (?,?,?,0,?)",
            [$hostelId, self::MANUAL_FILE, 'Added by hand', $userId]
        );
    }

    /** True for the hand-typed batch, which reads differently in the UI. */
    public static function isManualBatch(array $batch): bool
    {
        return ($batch['filename'] ?? '') === self::MANUAL_FILE;
    }

    /**
     * Build the stored column set from admin-entered form values, deriving the
     * normalised match keys so a hand-typed row behaves exactly like an
     * imported one.
     */
    public static function fromInput(array $in): array
    {
        $studentNo = trim((string) ($in['student_no'] ?? '')) ?: null;
        $phone     = trim((string) ($in['phone'] ?? '')) ?: null;
        $amount    = trim((string) ($in['amount'] ?? ''));

        return [
            'full_name'       => trim((string) ($in['full_name'] ?? '')) ?: null,
            'student_no'      => $studentNo,
            'student_no_norm' => self::normaliseStudentNo($studentNo),
            'phone'           => $phone,
            'phone_norm'      => self::normalisePhone($phone),
            'room_label'      => trim((string) ($in['room_label'] ?? '')) ?: null,
            'amount'          => $amount === '' ? null : (float) $amount,
            'academic_year'   => trim((string) ($in['academic_year'] ?? '')) ?: null,
            'semester'        => trim((string) ($in['semester'] ?? '')) ?: null,
        ];
    }

    /**
     * Reasons a hand-entered row would not work, in the admin's words.
     * Returned as [fatal errors, non-blocking warnings].
     *
     * @return array{0:array<int,string>,1:array<int,string>}
     */
    public static function checkInput(array $data): array
    {
        $errors = [];
        $warnings = [];

        if ($data['student_no'] === null && $data['phone'] === null) {
            $errors[] = 'Enter a student ID or a phone number — without one there is nothing to match a student on.';
        }
        if ($data['phone'] !== null && $data['phone_norm'] === null) {
            $errors[] = 'That phone number is too short to be usable. Enter at least 9 digits.';
        }
        // A 10-digit local number must start with 0; anything else is a slip
        // that would silently never match anybody.
        $digits = preg_replace('/\D/', '', (string) $data['phone']);
        if (strlen($digits) === 10 && $digits[0] !== '0') {
            $warnings[] = 'The phone number does not look like a valid local number — double-check it.';
        }
        if ($data['amount'] !== null && $data['amount'] < 0) {
            $errors[] = 'The amount cannot be negative.';
        }
        return [$errors, $warnings];
    }

    /**
     * Students already registered who this row would match. Lets the admin see,
     * while typing, whether the entry will actually block anybody.
     */
    public static function matchesFor(array $data, ?int $hostelId): array
    {
        $keys = [];
        $params = [];
        if (!empty($data['student_no_norm'])) {
            $keys[] = "UPPER(REPLACE(REPLACE(student_id,'-',''),' ','')) = ?";
            $params[] = $data['student_no_norm'];
        }
        if (!empty($data['phone_norm'])) {
            $keys[] = "RIGHT(REPLACE(REPLACE(phone,' ',''),'-',''), 9) = ?";
            $params[] = $data['phone_norm'];
        }
        if (!$keys) {
            return [];
        }
        $sql = "SELECT id, student_id, full_name FROM students WHERE (" . implode(' OR ', $keys) . ")";
        if ($hostelId !== null) {
            $sql .= " AND hostel_id = ?";
            $params[] = $hostelId;
        }
        return Database::all($sql, $params);
    }

    /** Student IDs differ only by case and punctuation between systems. */
    public static function normaliseStudentNo(?string $value): ?string
    {
        $v = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
        return $v === '' ? null : $v;
    }

    /**
     * Compare phones on their last 9 digits.
     *
     * A Ghanaian mobile is 10 digits ("0548811774"), but a spreadsheet that
     * held the column as a number drops the leading zero ("548811774"), and a
     * number typed with a country code gains three ("233548811774"). The last
     * 9 digits are the part that is always the same.
     */
    public static function normalisePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if (strlen($digits) < 9) {
            return null;
        }
        return substr($digits, -9);
    }

    /**
     * Outstanding debts for one student, newest term first.
     *
     * Matching is scoped to the student's own hall: another hall's arrears are
     * not this hall's business, and a global list uploaded without a hostel
     * (hostel_id IS NULL) applies to everyone.
     */
    public static function outstandingFor(?array $student): array
    {
        if (!$student) {
            return [];
        }
        $no    = self::normaliseStudentNo($student['student_id'] ?? null);
        $phone = self::normalisePhone($student['phone'] ?? null);
        if ($no === null && $phone === null) {
            return [];
        }

        $keys = [];
        $params = [];
        if ($no !== null) {
            $keys[] = 'student_no_norm = ?';
            $params[] = $no;
        }
        if ($phone !== null) {
            $keys[] = 'phone_norm = ?';
            $params[] = $phone;
        }

        $hostelSql = '';
        if (!empty($student['hostel_id'])) {
            $hostelSql = ' AND (hostel_id = ? OR hostel_id IS NULL)';
            $params[] = (int) $student['hostel_id'];
        }

        return self::guarded(fn() => Database::all(
            "SELECT * FROM dues_debtors
             WHERE status = 'outstanding' AND (" . implode(' OR ', $keys) . "){$hostelSql}
             ORDER BY academic_year DESC, semester DESC, id",
            $params
        ));
    }

    /** Total still owed across the matched rows. */
    public static function totalOwed(array $debts): float
    {
        $total = 0.0;
        foreach ($debts as $d) {
            $total += (float) ($d['amount'] ?? 0);
        }
        return $total;
    }

    /** A short "Second 2025/2026" style label for one debt row. */
    public static function termLabel(array $debt): string
    {
        $parts = array_filter([$debt['semester'] ?? null, $debt['academic_year'] ?? null]);
        return $parts ? implode(' semester, ', $parts) : 'a previous semester';
    }

    /**
     * Insert one uploaded batch of parsed records.
     * Runs in a transaction so a failure part-way never leaves half a list.
     */
    public static function importBatch(int $batchId, ?int $hostelId, array $records): int
    {
        if (!$records) {
            return 0;
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($records as $r) {
                Database::run(
                    "INSERT INTO dues_debtors
                        (batch_id, hostel_id, full_name, student_no, student_no_norm,
                         phone, phone_norm, room_label, amount, academic_year, semester)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                    [
                        $batchId, $hostelId, $r['full_name'], $r['student_no'], $r['student_no_norm'],
                        $r['phone'], $r['phone_norm'], $r['room_label'], $r['amount'],
                        $r['academic_year'], $r['semester'],
                    ]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return count($records);
    }

    /**
     * Rows of a hostel's list, annotated with whether the person actually has
     * an account on the system yet — that is what tells the admin whether the
     * block will ever fire for them.
     */
    public static function listFor(?int $hostelId, string $status = '', string $search = ''): array
    {
        $sql = "SELECT d.*, b.filename, b.created_at AS uploaded_at,
                       s.id AS matched_student_id, s.full_name AS matched_name
                FROM dues_debtors d
                JOIN dues_debtor_batches b ON b.id = d.batch_id
                LEFT JOIN students s
                       ON (d.student_no_norm IS NOT NULL
                           AND UPPER(REPLACE(REPLACE(s.student_id,'-',''),' ','')) = d.student_no_norm)
                       OR (d.phone_norm IS NOT NULL
                           AND RIGHT(REPLACE(REPLACE(s.phone,' ',''),'-',''), 9) = d.phone_norm)
                WHERE 1";
        $params = [];
        if ($hostelId !== null) {
            $sql .= " AND (d.hostel_id = ? OR d.hostel_id IS NULL)";
            $params[] = $hostelId;
        }
        if ($status !== '') {
            $sql .= " AND d.status = ?";
            $params[] = $status;
        }
        if ($search !== '') {
            $sql .= " AND (d.full_name LIKE ? OR d.student_no LIKE ? OR d.phone LIKE ?)";
            $like = "%{$search}%";
            array_push($params, $like, $like, $like);
        }
        $sql .= " GROUP BY d.id ORDER BY d.status, d.academic_year DESC, d.semester DESC, d.full_name";
        return self::guarded(fn() => Database::all($sql, $params));
    }

    /** Upload batches for a hostel, newest first. */
    public static function batchesFor(?int $hostelId): array
    {
        $sql = "SELECT b.*, u.name AS uploaded_by_name,
                       (SELECT COUNT(*) FROM dues_debtors d WHERE d.batch_id = b.id) AS rows_now,
                       (SELECT COUNT(*) FROM dues_debtors d WHERE d.batch_id = b.id AND d.status='cleared') AS cleared_now
                FROM dues_debtor_batches b
                LEFT JOIN users u ON u.id = b.uploaded_by
                WHERE 1";
        $params = [];
        if ($hostelId !== null) {
            $sql .= " AND b.hostel_id = ?";
            $params[] = $hostelId;
        }
        $sql .= " ORDER BY b.created_at DESC";
        return self::guarded(fn() => Database::all($sql, $params));
    }
}
