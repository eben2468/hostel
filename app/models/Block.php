<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Block extends Model
{
    protected string $table = 'blocks';
    protected array $fillable = ['hostel_id', 'name', 'code', 'gender', 'description', 'status'];

    public function forHostel(int $hostelId): array
    {
        return Database::all(
            "SELECT b.*,
                    (SELECT COUNT(*) FROM floors f WHERE f.block_id=b.id) AS floor_count,
                    (SELECT COUNT(*) FROM rooms r WHERE r.block_id=b.id) AS room_count
             FROM blocks b WHERE b.hostel_id=? ORDER BY b.name",
            [$hostelId]
        );
    }
}
