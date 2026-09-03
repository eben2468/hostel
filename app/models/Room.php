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
             ORDER BY h.name, " . self::ORDER_BY_NUMBER . "",
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
        $sql .= " ORDER BY h.name, " . self::ORDER_BY_NUMBER;
        return Database::all($sql, $params);
    }

    /** Room statuses that still accept an application. */
    public const OPEN_STATUSES = ['available', 'reserved'];

    /**
     * How a room list should be ordered, for an ORDER BY on an `r` alias.
     *
     * Plain `ORDER BY room_number` is wrong twice over: it sorts alphabetically,
     * so FF lands before GF even though Ground comes first, and it sorts the
     * numbers as text, so GF10 lands before GF2. This ranks the floor prefix in
     * building order (Ground, First, Second, Top) and the rest numerically.
     * Anything with an unrecognised prefix sorts last rather than disappearing.
     */
    public const ORDER_BY_NUMBER = "
        IF(FIELD(UPPER(LEFT(r.room_number, 2)), 'GF', 'FF', 'SF', 'TF') = 0, 99,
           FIELD(UPPER(LEFT(r.room_number, 2)), 'GF', 'FF', 'SF', 'TF')),
        CAST(NULLIF(REGEXP_REPLACE(r.room_number, '[^0-9]', ''), '') AS UNSIGNED),
        r.room_number";

    /**
     * Why a room cannot be applied for, or null when it can.
     *
     * The room lists already filter these out, but a room fills up while a
     * student is still deciding — so the same rule has to be re-checked when
     * the form is submitted, and this keeps both places using one definition.
     *
     * @return string|null a message naming the room, ready to show
     */
    public static function unavailableReason(array $room): ?string
    {
        $label = 'Room ' . ($room['room_number'] ?? '');

        if (!in_array($room['status'], self::OPEN_STATUSES, true)) {
            return $label . ' is not open for applications (' . str_replace('_', ' ', $room['status'])
                . '). Please choose a different room.';
        }
        $free = (int) $room['capacity'] - (int) $room['occupied'];
        if ($free <= 0) {
            return $label . ' is now full — all ' . (int) $room['capacity']
                . ' bed(s) have been taken. Please choose a different room.';
        }
        return null;
    }

    /** Rooms with at least one free bed (scoped to the caller's hostel). */
    public function available(): array
    {
        [$scope, $bind] = Scope::on('r.hostel_id');
        return Database::all(
            "SELECT r.*, h.name AS hostel_name
             FROM rooms r LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE r.status IN ('available','reserved') AND r.occupied < r.capacity{$scope}
             ORDER BY h.name, " . self::ORDER_BY_NUMBER . "",
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
             ORDER BY " . self::ORDER_BY_NUMBER,
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
