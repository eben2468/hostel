<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

/**
 * Admin-assisted password reset requests.
 *
 * A student who cannot self-verify (Full Name + Student ID + Date of Birth)
 * leaves a request here; a super admin or the student's hostel admin then
 * resets the password on their behalf.
 */
class PasswordResetRequest extends Model
{
    protected string $table = 'password_reset_requests';
    protected array $fillable = [
        'student_id', 'hostel_id', 'student_code', 'full_name', 'contact', 'message',
        'status', 'handled_by', 'handled_at',
    ];

    /**
     * Requests visible to the current admin, most recent first. The super admin
     * sees every request; a hostel admin sees only their own hostel's (plus
     * unmatched requests that carry no hostel, so they are never lost).
     */
    public function scopedList(): array
    {
        if (Scope::isGlobal()) {
            $where = '';
            $params = [];
        } else {
            $where = ' WHERE (r.hostel_id = ? OR r.hostel_id IS NULL)';
            $params = [Scope::hostelId()];
        }
        return Database::all(
            "SELECT r.*, s.user_id AS student_user_id, h.name AS hostel_name
             FROM password_reset_requests r
             LEFT JOIN students s ON s.id = r.student_id
             LEFT JOIN hostels  h ON h.id = r.hostel_id
             {$where}
             ORDER BY (r.status = 'pending') DESC, r.created_at DESC",
            $params
        );
    }

    /** Number of pending requests within the current admin's scope. */
    public function pendingCount(): int
    {
        [$scope, $bind] = Scope::on('hostel_id');
        // Include unmatched (hostel-less) requests for hostel admins too.
        if (!Scope::isGlobal()) {
            $scope = ' AND (hostel_id = ? OR hostel_id IS NULL)';
        }
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM password_reset_requests WHERE status = 'pending'{$scope}",
            $bind
        );
    }
}
