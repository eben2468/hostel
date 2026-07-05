<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Notice extends Model
{
    protected string $table = 'notices';
    protected array $fillable = [
        'title','body','audience','hostel_id','is_pinned','expires_at','created_by',
    ];

    /**
     * Hostel filter for notices: a hostel-bound user sees notices for their own
     * hostel plus global (hostel_id IS NULL) ones. Global users see everything.
     */
    private function hostelScope(): array
    {
        if (Scope::isGlobal()) {
            return ['', []];
        }
        return [' AND (hostel_id = ? OR hostel_id IS NULL)', [Scope::hostelId()]];
    }

    /** Active notices visible to the given audience (and the caller's hostel). */
    public function visibleFor(string $audience): array
    {
        [$scope, $bind] = $this->hostelScope();
        return Database::all(
            "SELECT * FROM notices
             WHERE (expires_at IS NULL OR expires_at >= CURDATE())
               AND (audience = 'all' OR audience = ?){$scope}
             ORDER BY is_pinned DESC, created_at DESC",
            array_merge([$audience], $bind)
        );
    }

    /** All notices for the management view, scoped to the caller's hostel. */
    public function forManagement(): array
    {
        [$scope, $bind] = $this->hostelScope();
        return Database::all(
            "SELECT * FROM notices WHERE 1{$scope} ORDER BY is_pinned DESC, created_at DESC",
            $bind
        );
    }
}
