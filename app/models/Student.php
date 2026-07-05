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

    public function search(string $term = '', string $status = ''): array
    {
        $sql = "SELECT * FROM students WHERE 1";
        $params = [];
        if ($term !== '') {
            $sql .= " AND (full_name LIKE ? OR student_id LIKE ? OR email LIKE ? OR index_number LIKE ?)";
            $like = "%{$term}%";
            array_push($params, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        [$scope, $bind] = Scope::on('hostel_id');
        $sql .= $scope;
        array_push($params, ...$bind);
        $sql .= " ORDER BY created_at DESC";
        return Database::all($sql, $params);
    }

    public function byUserId(int $userId): ?array
    {
        return $this->findBy('user_id', $userId);
    }

    /** Paginated search. Returns a Paginator::make result array. */
    public function searchPaginated(string $term, string $status, int $page, int $perPage = 15): array
    {
        $where = ' WHERE 1';
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
        [$scope, $bind] = Scope::on('hostel_id');
        $where .= $scope;
        array_push($params, ...$bind);
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
