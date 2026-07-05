<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Complaint extends Model
{
    protected string $table = 'complaints';
    protected array $fillable = [
        'student_id','category','title','description','priority','room_number',
        'photo','status','assigned_to','technician_notes',
    ];

    /**
     * Scope a complaint to the caller's hostel via the linked student. Complaints
     * with no student (general, staff-reported) are not tied to any hostel and
     * stay visible to all staff. Returns [fragment, params].
     */
    private function hostelScope(string $col = 'c.student_id'): array
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
            "SELECT c.*, s.full_name, s.student_id AS student_no
             FROM complaints c LEFT JOIN students s ON s.id = c.student_id
             WHERE 1{$scope}
             ORDER BY FIELD(c.priority,'urgent','high','medium','low'), c.created_at DESC",
            $bind
        );
    }

    public function paginatedDetailed(int $page, int $perPage = 15): array
    {
        [$scope, $bind] = $this->hostelScope('c.student_id');
        return \App\Core\Paginator::make(
            "SELECT COUNT(*) FROM complaints c WHERE 1{$scope}",
            "SELECT c.*, s.full_name, s.student_id AS student_no
             FROM complaints c LEFT JOIN students s ON s.id = c.student_id
             WHERE 1{$scope}
             ORDER BY FIELD(c.priority,'urgent','high','medium','low'), c.created_at DESC",
            $bind, $page, $perPage
        );
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId);
    }
}
