<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;
use App\Models\Room;

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

    /**
     * The allocations list, filtered. Every filter is optional (empty = ignored)
     * and hostel isolation is always applied on top.
     *
     * @param array{q?:string,status?:string,hostel?:string,sort?:string} $filters
     */
    public function paginatedDetailed(int $page, int $perPage = 15, array $filters = []): array
    {
        // Count and rows share the joins, or a search on a joined column would
        // filter the rows but not the total and the pager would lie.
        $from = " FROM allocations a
                  JOIN students s ON s.id = a.student_id
                  JOIN rooms r    ON r.id = a.room_id
                  LEFT JOIN hostels h ON h.id = r.hostel_id
                  LEFT JOIN beds b ON b.id = a.bed_id";

        // 'suspended' allocations belong to a term that is not the hostel's active
        // session; they are hidden here and restored when that term is re-activated.
        $where = " WHERE a.status <> 'suspended'";
        $params = [];

        if (($q = trim($filters['q'] ?? '')) !== '') {
            $where .= " AND (s.full_name LIKE ? OR s.student_id LIKE ? OR r.room_number LIKE ?
                             OR b.bed_number LIKE ? OR a.remarks LIKE ?)";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (($status = trim($filters['status'] ?? '')) !== '') {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        if (($hostel = trim($filters['hostel'] ?? '')) !== '') {
            $where .= ' AND r.hostel_id = ?';
            $params[] = (int) $hostel;
        }

        [$scope, $bind] = Scope::on('r.hostel_id');
        $where .= $scope;
        array_push($params, ...$bind);

        // Rooms read in building order by default — GF, FF, SF, TF then 1..n —
        // which is how an office walks the block. 'newest' keeps the old order.
        $order = ($filters['sort'] ?? '') === 'newest'
            ? 'a.created_at DESC'
            : 'h.name, ' . Room::ORDER_BY_NUMBER . ', s.full_name';

        return \App\Core\Paginator::make(
            "SELECT COUNT(*){$from}{$where}",
            "SELECT a.*, s.full_name, s.student_id AS student_no,
                    r.room_number, h.name AS hostel_name, b.bed_number
             {$from}{$where}
             ORDER BY {$order}",
            $params, $page, $perPage
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
