<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Room extends Model
{
    protected string $table = 'rooms';
    protected array $fillable = [
        'hostel_id','block_id','floor_id','room_number','room_type','capacity',
        'occupied','price','features','status',
    ];

    /** Rooms joined with hostel name (scoped to the caller's hostel). */
    public function allWithHostel(): array
    {
        [$scope, $bind] = Scope::on('r.hostel_id');
        return Database::all(
            "SELECT r.*, h.name AS hostel_name
             FROM rooms r LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE 1{$scope}
             ORDER BY h.name, r.room_number",
            $bind
        );
    }

    /**
     * Rooms filtered by number search, hostel, type and status. Every filter is
     * optional (empty = ignored). Hostel isolation is always enforced on top via
     * Scope, so a hostel-bound user can never widen past their own hostel.
     */
    public function filtered(string $q = '', string $hostelId = '', string $type = '', string $status = '', string $floorId = ''): array
    {
        $sql = "SELECT r.*, h.name AS hostel_name
                FROM rooms r LEFT JOIN hostels h ON h.id = r.hostel_id
                WHERE 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND r.room_number LIKE ?";
            $params[] = "%{$q}%";
        }
        if ($hostelId !== '') {
            $sql .= " AND r.hostel_id = ?";
            $params[] = (int) $hostelId;
        }
        if ($floorId !== '') {
            $sql .= " AND r.floor_id = ?";
            $params[] = (int) $floorId;
        }
        if ($type !== '') {
            $sql .= " AND r.room_type = ?";
            $params[] = $type;
        }
        if ($status !== '') {
            $sql .= " AND r.status = ?";
            $params[] = $status;
        }
        [$scope, $bind] = Scope::on('r.hostel_id');
        $sql .= $scope;
        array_push($params, ...$bind);
        $sql .= " ORDER BY h.name, r.room_number";
        return Database::all($sql, $params);
    }

    /** Rooms with at least one free bed (scoped to the caller's hostel). */
    public function available(): array
    {
        [$scope, $bind] = Scope::on('r.hostel_id');
        return Database::all(
            "SELECT r.*, h.name AS hostel_name
             FROM rooms r LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE r.status IN ('available','reserved') AND r.occupied < r.capacity{$scope}
             ORDER BY h.name, r.room_number",
            $bind
        );
    }

    /** Rooms with a free bed in a specific hostel (for the student application picker). */
    public function availableForHostel(int $hostelId): array
    {
        return Database::all(
            "SELECT r.*, h.name AS hostel_name
             FROM rooms r LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE r.hostel_id = ? AND r.status IN ('available','reserved') AND r.occupied < r.capacity
             ORDER BY r.room_number",
            [$hostelId]
        );
    }

    /** Recalculate occupied count and status from active allocations. */
    public function syncOccupancy(int $roomId): void
    {
        $room = $this->find($roomId);
        if (!$room) {
            return;
        }
        $occupied = (int) Database::scalar(
            "SELECT COUNT(*) FROM allocations WHERE room_id = ? AND status IN ('active','checked_in')",
            [$roomId]
        );
        $status = $room['status'];
        if (!in_array($status, ['maintenance', 'closed'], true)) {
            $status = $occupied >= (int) $room['capacity'] ? 'occupied' : 'available';
        }
        Database::run("UPDATE rooms SET occupied = ?, status = ? WHERE id = ?", [$occupied, $status, $roomId]);
    }
}
