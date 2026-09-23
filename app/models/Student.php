<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Student extends Model
{
    protected string $table = 'students';
    protected array $fillable = [
        'user_id','hostel_id','student_id','index_number','full_name','gender','date_of_birth','nationality',
        'programme','department','level','phone','email','address','guardian_name','guardian_phone',
        'guardian_relationship','blood_group','allergies','emergency_contact','status','photo',
    ];

    /**
     * The WHERE fragment every student listing shares: the search box, the
     * status dropdown, the hall-dues filter and the caller's hostel scope.
     * Kept in one place so the table, the total shown above it and the CSV
     * export can never disagree about who matched.
     *
     * @param  string $duesType '' | 'fresher' | 'continuing'
     * @return array{0:string, 1:array} the fragment (opening with " WHERE 1") and its bind params
     */
    public function filters(string $term = '', string $status = '', string $duesType = ''): array
    {
        $where  = " WHERE 1";
        $params = [];
        if ($term !== '') {
            $where .= " AND (full_name LIKE ? OR student_id LIKE ? OR email LIKE ? OR index_number LIKE ?)";
            $like = "%{$term}%";
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where .= " AND status = ?";
            $params[] = $status;
        }
        if ($duesType !== '' && self::duesFilterInstalled()) {
            // "Paid as a fresher" is a fact about the dues payment, not about how
            // the application ended — a student whose application was later
            // rejected or cancelled still paid — so the application's own status
            // is deliberately not part of this test.
            $where .= " AND EXISTS (SELECT 1 FROM applications a
                                     WHERE a.student_id = students.id
                                       AND a.student_type = ?
                                       AND a.payment_status = 'verified')";
            $params[] = $duesType;
        }
        [$scope, $bind] = Scope::on('hostel_id');
        $where .= $scope;
        array_push($params, ...$bind);
        return [$where, $params];
    }

    /**
     * True when the hall-dues migration has been run, so students can be
     * filtered by the category they paid their dues under.
     *
     * Checked so the Students screen quietly drops the filter on a deployment
     * where the code is live but `database/migration_hostel_dues.sql` has not
     * been imported yet, instead of throwing on an unknown column.
     */
    public static function duesFilterInstalled(): bool
    {
        static $installed = null;
        if ($installed === null) {
            try {
                $installed = (int) Database::scalar(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'applications'
                       AND COLUMN_NAME IN ('student_type', 'payment_status')"
                ) === 2;
            } catch (\Throwable $e) {
                $installed = false;
            }
        }
        return $installed;
    }

    public function search(string $term = '', string $status = '', string $duesType = ''): array
    {
        [$where, $params] = $this->filters($term, $status, $duesType);
        return Database::all("SELECT * FROM students{$where} ORDER BY created_at DESC", $params);
    }

    public function byUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /**
     * Push a student record's contact details onto their linked login account.
     *
     * A student's address is held twice: `students.email` is where every
     * notification is delivered, `users.email` is what they sign in and reset a
     * password with. Editing one screen used to leave the other stale, and mail
     * then went on being sent to an address nobody reads. Keeping them in step
     * on every write is what stops that.
     *
     * @return bool false when another account already holds the address
     *              (`users.email` is UNIQUE), so the caller can warn instead of
     *              failing the whole save.
     */
    public static function syncContactToUser(int $studentId): bool
    {
        $s = Database::first("SELECT user_id, email, phone FROM students WHERE id = ?", [$studentId]);
        if (!$s || empty($s['user_id'])) {
            return true; // No login account linked — nothing to keep in step.
        }
        $email = trim((string) $s['email']);
        if ($email !== '' && (int) Database::scalar(
                "SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?", [$email, $s['user_id']]) > 0) {
            return false; // Taken by someone else; leave the account untouched.
        }
        return self::applyContact('users', 'id', (int) $s['user_id'], $email, (string) $s['phone']);
    }

    /** The reverse: mirror a login account's contact details onto its student record. */
    public static function syncContactFromUser(int $userId): void
    {
        $u = Database::first("SELECT email, phone FROM users WHERE id = ?", [$userId]);
        if ($u) {
            self::applyContact('students', 'user_id', $userId, (string) $u['email'], (string) $u['phone']);
        }
    }

    /**
     * Write whichever of email/phone actually has a value. Blank fields are
     * skipped rather than written, so a screen that does not collect one of
     * them can never wipe the other record's copy.
     */
    private static function applyContact(string $table, string $key, int $id, string $email, string $phone): bool
    {
        $sets = [];
        $params = [];
        foreach (['email' => trim($email), 'phone' => trim($phone)] as $column => $value) {
            if ($value !== '') {
                $sets[] = "{$column} = ?";
                $params[] = $value;
            }
        }
        if (!$sets) {
            return true;
        }
        $params[] = $id;
        Database::run("UPDATE {$table} SET " . implode(', ', $sets) . " WHERE {$key} = ?", $params);
        return true;
    }

    /**
     * Best guess at whether a student is a fresher or a continuing student,
     * read off their academic level ("100", "Level 100" and "1" all mean a
     * fresher). Returns null when the level says nothing useful, in which case
     * the student picks their own category on the application form.
     */
    public static function typeFor(?array $student): ?string
    {
        $level = trim((string) ($student['level'] ?? ''));
        if ($level === '' || !preg_match('/\d+/', $level, $m)) {
            return null;
        }
        return (int) $m[0] <= 100 ? 'fresher' : 'continuing';
    }

    /**
     * Paginated search. Returns a Paginator::make result array whose `total`
     * counts every matching student — that is the number the Students screen
     * reports above the table, not merely the rows on the current page.
     */
    public function searchPaginated(string $term, string $status, string $duesType, int $page, int $perPage = 15): array
    {
        [$where, $params] = $this->filters($term, $status, $duesType);
        return \App\Core\Paginator::make(
            "SELECT COUNT(*) FROM students{$where}",
            "SELECT * FROM students{$where} ORDER BY created_at DESC",
            $params, $page, $perPage
        );
    }

    /**
     * Import students from a CSV file path. The first row is a header; columns
     * are matched by name in any order. Rows missing student_id/full_name or
     * duplicating an existing student_id are skipped.
     *
     * Newly created students are bound to $hostelId when provided (the importing
     * hostel admin's hostel); the global super admin may pass null.
     *
     * @return array{imported:int, skipped:int}
     */
    public function importCsv(string $path, ?int $hostelId = null): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return ['imported' => 0, 'skipped' => 0];
        }
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['imported' => 0, 'skipped' => 0];
        }
        $idx = array_flip(array_map(fn($h) => strtolower(trim((string) $h)), $header));
        $get = fn(array $row, string $name) => isset($idx[$name]) ? trim((string) ($row[$idx[$name]] ?? '')) : '';

        $imported = 0;
        $skipped  = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $studentId = $get($row, 'student_id');
            $fullName  = $get($row, 'full_name');
            if ($studentId === '' || $fullName === '' || $this->findBy('student_id', $studentId)) {
                $skipped++;
                continue;
            }
            $gender = strtolower($get($row, 'gender'));
            $this->create([
                'student_id'   => $studentId,
                'hostel_id'    => $hostelId,
                'full_name'    => $fullName,
                'gender'       => in_array($gender, ['male', 'female', 'other'], true) ? $gender : 'male',
                'programme'    => $get($row, 'programme'),
                'department'   => $get($row, 'department'),
                'level'        => $get($row, 'level'),
                'phone'        => $get($row, 'phone'),
                'email'        => $get($row, 'email'),
                'status'       => 'active',
            ]);
            $imported++;
        }
        fclose($handle);
        return ['imported' => $imported, 'skipped' => $skipped];
    }
}
