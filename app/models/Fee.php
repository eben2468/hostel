<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

/**
 * Per-hostel room pricing. Each hostel keeps one active fee row per room type
 * (single/double/triple/quad); admins bill students from these prices.
 */
class Fee extends Model
{
    protected string $table = 'fees';
    protected array $fillable = [
        'name', 'amount', 'hostel_id', 'room_type', 'academic_year', 'semester', 'due_date', 'status',
    ];

    /** Room types a hostel prices. */
    public const ROOM_TYPES = ['single', 'double', 'triple', 'quad'];

    /**
     * Active room-type prices for a hostel, e.g. ['single' => 3000.0, ...].
     * Only room types with a price set are present.
     */
    public function scheduleFor(int $hostelId): array
    {
        $rows = Database::all(
            "SELECT room_type, amount FROM fees
             WHERE hostel_id = ? AND status = 'active' AND room_type IS NOT NULL",
            [$hostelId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['room_type']] = (float) $r['amount'];
        }
        return $out;
    }

    /**
     * Set (or clear) the price for one room type of a hostel. A null/zero amount
     * deactivates the price. Stamps the hostel's current term.
     */
    public function setPrice(int $hostelId, string $roomType, ?float $amount, array $term): void
    {
        $existing = Database::first(
            "SELECT id FROM fees WHERE hostel_id = ? AND room_type = ? AND status = 'active' LIMIT 1",
            [$hostelId, $roomType]
        );

        if ($amount === null || $amount <= 0) {
            if ($existing) {
                Database::run("UPDATE fees SET status = 'inactive' WHERE id = ?", [$existing['id']]);
            }
            return;
        }

        $name = ucfirst($roomType) . ' Room Fee';
        if ($existing) {
            Database::run(
                "UPDATE fees SET amount = ?, name = ?, academic_year = ?, semester = ? WHERE id = ?",
                [$amount, $name, $term['academic_year'], $term['semester'], $existing['id']]
            );
        } else {
            Database::run(
                "INSERT INTO fees (name, amount, hostel_id, room_type, academic_year, semester, status)
                 VALUES (?,?,?,?,?,?, 'active')",
                [$name, $amount, $hostelId, $roomType, $term['academic_year'], $term['semester']]
            );
        }
    }
}
