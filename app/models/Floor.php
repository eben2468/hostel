<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Floor extends Model
{
    protected string $table = 'floors';
    protected array $fillable = ['block_id', 'number', 'description'];

    public function forBlock(int $blockId): array
    {
        return Database::all(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM rooms r WHERE r.floor_id=f.id) AS room_count
             FROM floors f WHERE f.block_id=? ORDER BY f.number",
            [$blockId]
        );
    }

    /**
     * Floors for a filter dropdown, with block + hostel context, scoped to the
     * caller's hostel. Optionally narrowed to a single hostel.
     */
    public function options(string $hostelId = ''): array
    {
        $sql = "SELECT f.id, f.number, b.name AS block_name, h.name AS hostel_name
                FROM floors f
                JOIN blocks b ON b.id = f.block_id
                LEFT JOIN hostels h ON h.id = b.hostel_id
                WHERE 1";
        $params = [];
        if ($hostelId !== '') {
            $sql .= " AND b.hostel_id = ?";
            $params[] = (int) $hostelId;
        }
        [$scope, $bind] = Scope::on('b.hostel_id');
        $sql .= $scope;
        array_push($params, ...$bind);
        $sql .= " ORDER BY h.name, b.name, f.number";
        return Database::all($sql, $params);
    }
}
