<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Allocation extends Model
{
    protected string $table = 'allocations';
    protected array $fillable = [
        'student_id','room_id','bed_id','application_id','academic_year','semester','allocated_by',
        'status','check_in_at','check_out_at','remarks',
    ];

    public function allDetailed(): array
    {
        [$scope, $bind] = Scope::on('r.hostel_id');
        return Database::all(
            "SELECT a.*, s.full_name, s.student_id AS student_no,
                    r.room_number, h.name AS hostel_name, b.bed_number
             FROM allocations a
             JOIN students s ON s.id = a.student_id
             JOIN rooms r    ON r.id = a.room_id
             LEFT JOIN hostels h ON h.id = r.hostel_id
             LEFT JOIN beds b ON b.id = a.bed_id
             WHERE a.status <> 'suspended'{$scope}
             ORDER BY a.created_at DESC",
            $bind
        );
    }

    public function paginatedDetailed(int $page, int $perPage = 15): array
    {
        [$scope, $bind] = Scope::on('r.hostel_id');
        // 'suspended' allocations belong to a term that is not the hostel's active
        // session; they are hidden here and restored when that term is re-activated.
        return \App\Core\Paginator::make(
            "SELECT COUNT(*) FROM allocations a JOIN rooms r ON r.id = a.room_id WHERE a.status <> 'suspended'{$scope}",
            "SELECT a.*, s.full_name, s.student_id AS student_no,
                    r.room_number, h.name AS hostel_name, b.bed_number
             FROM allocations a
             JOIN students s ON s.id = a.student_id
             JOIN rooms r    ON r.id = a.room_id
             LEFT JOIN hostels h ON h.id = r.hostel_id
             LEFT JOIN beds b ON b.id = a.bed_id
             WHERE a.status <> 'suspended'{$scope}
             ORDER BY a.created_at DESC",
            $bind, $page, $perPage
        );
    }

    public function activeForStudent(int $studentId): ?array
    {
        return Database::first(
            "SELECT a.*, r.room_number, h.name AS hostel_name, b.bed_number
             FROM allocations a
             JOIN rooms r ON r.id = a.room_id
             LEFT JOIN hostels h ON h.id = r.hostel_id
             LEFT JOIN beds b ON b.id = a.bed_id
             WHERE a.student_id = ? AND a.status IN ('active','checked_in')
             ORDER BY a.created_at DESC LIMIT 1",
            [$studentId]
        );
    }
}
