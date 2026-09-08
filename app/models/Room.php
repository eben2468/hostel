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
        // `claimed` is the beds held by live applications nobody has allocated
        // yet — the list shows it so a room that looks half empty but cannot be
        // applied for explains itself.
        $sql = "SELECT r.*, h.name AS hostel_name, " . self::claimSql() . " AS claimed
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
     * Application states that hold a bed in the room they name.
     *
     * A bed is spoken for the moment it is applied for, not when an admin gets
     * round to approving it — otherwise a four-bed room collects twenty
     * applications and nineteen students are disappointed later. 'waiting' is
     * excluded on purpose: the waiting list is the admin saying "not yet", so
     * it must not hold a bed. Rejected, cancelled and expired release theirs.
     */
    public const CLAIM_STATUSES = ['pending', 'approved'];

    /**
     * Correlated subquery counting live applications holding a bed in room `r`.
     *
     * Applicants who already hold an allocation anywhere are excluded — they are
     * counted by rooms.occupied instead, and counting both would take the bed
     * twice. Pass a placeholder when excluding one student's own application.
     */
    public static function claimSql(bool $exceptStudent = false): string
    {
        $states = "'" . implode("','", self::CLAIM_STATUSES) . "'";
        return "(SELECT COUNT(*) FROM applications ap
                  WHERE ap.preferred_room_id = r.id
                    AND ap.status IN ({$states})"
                    . ($exceptStudent ? " AND ap.student_id <> ?" : "") . "
                    AND NOT EXISTS (
                        SELECT 1 FROM allocations al
                        WHERE al.student_id = ap.student_id
                          AND al.status IN ('active','checked_in')))";
    }

    /** How many live applications are holding beds in one room. */
    public static function claims(int $roomId, ?int $exceptStudentId = null): int
    {
        $states = "'" . implode("','", self::CLAIM_STATUSES) . "'";
        $params = [$roomId];
        $except = '';
        if ($exceptStudentId !== null) {
            $except = ' AND ap.student_id <> ?';
            $params[] = $exceptStudentId;
        }
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM applications ap
              WHERE ap.preferred_room_id = ?
                AND ap.status IN ({$states}){$except}
                AND NOT EXISTS (
                    SELECT 1 FROM allocations al
                    WHERE al.student_id = ap.student_id
                      AND al.status IN ('active','checked_in'))",
            $params
        );
    }

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
     * @param int $claims live applications already holding beds in this room
     * @return string|null a message naming the room, ready to show
     */
    public static function unavailableReason(array $room, int $claims = 0): ?string
    {
        $label    = 'Room ' . ($room['room_number'] ?? '');
        $capacity = (int) $room['capacity'];
        $occupied = (int) $room['occupied'];

        if (!in_array($room['status'], self::OPEN_STATUSES, true)) {
            return $label . ' is not open for applications (' . str_replace('_', ' ', $room['status'])
                . '). Please choose a different room.';
        }
        if ($occupied >= $capacity) {
            return $label . ' is now full — all ' . $capacity
                . ' bed(s) have been taken. Please choose a different room.';
        }
        // Beds held by applications nobody has approved yet still count: the
        // room is spoken for, and the student needs to be told that plainly
        // rather than joining a queue that cannot fit them.
        if ($occupied + $claims >= $capacity) {
            return $label . ' has no beds left — all ' . $capacity
                . ' have already been applied for and are awaiting approval. Please choose a different room.';
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

    /**
     * Rooms a student may still apply for in a hostel.
     *
     * Unlike available(), this counts beds already spoken for by live
     * applications, so a room whose beds are all applied for disappears from
     * the picker even though nobody has been allocated into it yet.
     *
     * @param ?int $exceptStudentId ignore this student's own application, so
     *                              re-applying is not blocked by their own claim
     */
    public function availableForHostel(int $hostelId, ?int $exceptStudentId = null): array
    {
        $except = $exceptStudentId !== null;
        $claims = self::claimSql($except);

        // The claim subquery appears twice and each copy carries its own
        // placeholder, so bind in the order the placeholders appear in the SQL:
        // [claims in SELECT] , hostel_id , [claims in WHERE].
        $params = [];
        if ($except) { $params[] = $exceptStudentId; }
        $params[] = $hostelId;
        if ($except) { $params[] = $exceptStudentId; }

        return Database::all(
            "SELECT r.*, h.name AS hostel_name, {$claims} AS claimed
             FROM rooms r LEFT JOIN hostels h ON h.id = r.hostel_id
             WHERE r.hostel_id = ?
               AND r.status IN ('available','reserved')
               AND (r.occupied + {$claims}) < r.capacity
             ORDER BY " . self::ORDER_BY_NUMBER,
            $params
        );
    }

    /**
     * Who is currently living in a room, with enough of each profile to be
     * useful at a glance — contact details, programme, bed and guardian.
     *
     * Ordered by bed so the list reads the way the room is laid out.
     */
    public function occupants(int $roomId): array
    {
        return Database::all(
            "SELECT s.id, s.student_id, s.full_name, s.gender, s.photo, s.phone, s.email,
                    s.programme, s.department, s.level, s.status AS student_status,
                    s.guardian_name, s.guardian_phone,
                    a.id AS allocation_id, a.status AS allocation_status,
                    a.check_in_at, a.created_at AS allocated_at,
                    b.bed_number
             FROM allocations a
             JOIN students s ON s.id = a.student_id
             LEFT JOIN beds b ON b.id = a.bed_id
             WHERE a.room_id = ? AND a.status IN ('active','checked_in')
             ORDER BY b.bed_number, s.full_name",
            [$roomId]
        );
    }

    /**
     * Students whose live application is holding a bed in this room but who
     * have not been allocated yet — the queue an admin needs to work through.
     */
    public function applicants(int $roomId): array
    {
        $states = "'" . implode("','", self::CLAIM_STATUSES) . "'";
        return Database::all(
            "SELECT ap.id AS application_id, ap.status AS application_status,
                    ap.payment_status, ap.payment_reference, ap.created_at AS applied_at,
                    s.id, s.student_id, s.full_name, s.phone, s.level, s.programme
             FROM applications ap
             JOIN students s ON s.id = ap.student_id
             WHERE ap.preferred_room_id = ?
               AND ap.status IN ({$states})
               AND NOT EXISTS (
                   SELECT 1 FROM allocations al
                   WHERE al.student_id = ap.student_id
                     AND al.status IN ('active','checked_in'))
             ORDER BY ap.created_at",
            [$roomId]
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
