<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Visitor extends Model
{
    protected string $table = 'visitors';
    protected array $fillable = [
        'student_id','visitor_name','phone','purpose','visit_date',
        'time_in','time_out','pass_code','status',
    ];

    /**
     * Scope visitors to the caller's hostel via the host student. Visitors with
     * no host student are not hostel-bound and stay visible to all staff.
     */
    private function hostelScope(string $col = 'v.student_id'): array
    {
        if (Scope::isGlobal()) {
            return ['', []];
        }
        return [" AND ({$col} IN (SELECT id FROM students WHERE hostel_id = ?) OR {$col} IS NULL)", [Scope::hostelId()]];
    }

    public function allDetailed(): array
    {
        [$scope, $bind] = $this->hostelScope();
        return Database::all(
            "SELECT v.*, s.full_name AS host_name, s.student_id AS host_no
             FROM visitors v LEFT JOIN students s ON s.id = v.student_id
             WHERE 1{$scope}
             ORDER BY v.created_at DESC",
            $bind
        );
    }

    public function paginatedDetailed(int $page, int $perPage = 15): array
    {
        [$scope, $bind] = $this->hostelScope('v.student_id');
        return \App\Core\Paginator::make(
            "SELECT COUNT(*) FROM visitors v WHERE 1{$scope}",
            "SELECT v.*, s.full_name AS host_name, s.student_id AS host_no
             FROM visitors v LEFT JOIN students s ON s.id = v.student_id
             WHERE 1{$scope}
             ORDER BY v.created_at DESC",
            $bind, $page, $perPage
        );
    }

    public function forStudent(int $studentId): array
    {
        return Database::all(
            "SELECT * FROM visitors WHERE student_id=? ORDER BY created_at DESC",
            [$studentId]
        );
    }

    public static function generatePass(): string
    {
        return 'VP-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
