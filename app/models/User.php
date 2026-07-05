<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'name','username','email','phone','password','role','hostel_id','is_active',
    ];

    /** Staff roles a hostel admin is allowed to create within their hostel. */
    public const HOSTEL_STAFF_ROLES = ['hostel_admin','finance','maintenance','security'];

    /** All assignable roles (super admin view). */
    public const ALL_ROLES = ['admin','hostel_admin','finance','maintenance','security','student'];

    /**
     * Users visible in the management list, joined with their hostel name.
     * A hostel admin sees only their own hostel's staff; the super admin sees all.
     */
    public function scopedList(): array
    {
        return $this->filteredList();
    }

    /**
     * Management list with optional filters, always confined to the caller's
     * hostel scope. Every filter is optional (empty = ignored).
     */
    public function filteredList(string $q = '', string $role = '', string $hostelId = '', string $status = ''): array
    {
        $sql = "SELECT u.*, h.name AS hostel_name
                FROM users u LEFT JOIN hostels h ON h.id = u.hostel_id
                WHERE 1";
        $params = [];
        if ($q !== '') {
            $sql .= " AND (u.name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like);
        }
        if ($role !== '') {
            $sql .= " AND u.role = ?";
            $params[] = $role;
        }
        if ($hostelId !== '') {
            $sql .= " AND u.hostel_id = ?";
            $params[] = (int) $hostelId;
        }
        if ($status !== '') {
            $sql .= " AND u.is_active = ?";
            $params[] = $status === 'active' ? 1 : 0;
        }
        // Hostel isolation is always enforced on top so a hostel admin can never
        // widen past their own staff.
        [$scope, $bind] = Scope::on('u.hostel_id');
        $sql .= $scope;
        array_push($params, ...$bind);
        $sql .= " ORDER BY u.role, u.name";
        return Database::all($sql, $params);
    }
}
