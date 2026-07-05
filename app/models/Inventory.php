<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Inventory extends Model
{
    protected string $table = 'inventory';
    protected array $fillable = [
        'name','category','hostel_id','room_id','quantity','condition','reorder_level','notes',
    ];

    public function allWithHostel(): array
    {
        [$scope, $bind] = Scope::on('i.hostel_id');
        return Database::all(
            "SELECT i.*, h.name AS hostel_name, r.room_number
             FROM inventory i
             LEFT JOIN hostels h ON h.id = i.hostel_id
             LEFT JOIN rooms r   ON r.id = i.room_id
             WHERE 1{$scope}
             ORDER BY i.category, i.name",
            $bind
        );
    }

    /** Items at or below their reorder level (scoped to the caller's hostel). */
    public function lowStock(): array
    {
        [$scope, $bind] = Scope::on('hostel_id');
        return Database::all(
            "SELECT * FROM inventory WHERE reorder_level > 0 AND quantity <= reorder_level{$scope}",
            $bind
        );
    }
}
