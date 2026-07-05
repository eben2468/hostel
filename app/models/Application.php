<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Application extends Model
{
    protected string $table = 'applications';
    protected array $fillable = [
        'student_id','academic_year','semester','preferred_hostel_id','preferred_room_type','preferred_room_id',
        'medical_conditions','special_needs','remarks','priority','status','reviewed_by','reviewed_at',
    ];

    public function allWithStudent(string $status = ''): array
    {
        $sql = "SELECT a.*, s.full_name, s.student_id AS student_no, h.name AS hostel_name, pr.room_number AS preferred_room_number
                FROM applications a
                JOIN students s ON s.id = a.student_id
                LEFT JOIN hostels h ON h.id = a.preferred_hostel_id
                LEFT JOIN rooms pr ON pr.id = a.preferred_room_id WHERE 1";
        $params = [];
        if ($status !== '') {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }
        // Hostel admins review applications directed at their own hostel.
        [$scope, $bind] = Scope::on('a.preferred_hostel_id');
        $sql .= $scope;
        array_push($params, ...$bind);
        $sql .= " ORDER BY a.priority DESC, a.created_at DESC";
        return Database::all($sql, $params);
    }

    public function paginatedWithStudent(string $status, int $page, int $perPage = 15): array
    {
        $where = ' WHERE 1';
        $params = [];
        if ($status !== '') {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        // Hostel admins review applications directed at their own hostel.
        [$scope, $bind] = Scope::on('a.preferred_hostel_id');
        $where .= $scope;
        array_push($params, ...$bind);
        return \App\Core\Paginator::make(
            "SELECT COUNT(*) FROM applications a{$where}",
            "SELECT a.*, s.full_name, s.student_id AS student_no, h.name AS hostel_name, pr.room_number AS preferred_room_number
             FROM applications a
             JOIN students s ON s.id = a.student_id
             LEFT JOIN hostels h ON h.id = a.preferred_hostel_id
             LEFT JOIN rooms pr ON pr.id = a.preferred_room_id{$where}
             ORDER BY a.priority DESC, a.created_at DESC",
            $params, $page, $perPage
        );
    }
}
