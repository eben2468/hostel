<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Application extends Model
{
    protected string $table = 'applications';
    protected array $fillable = [
        'student_id','academic_year','semester','student_type','preferred_hostel_id','preferred_room_type','preferred_room_id',
        'medical_conditions','special_needs','remarks','priority','status','reviewed_by','reviewed_at',
        'payment_reference','payment_amount','payment_status','payment_verified_by','payment_verified_at','review_note',
    ];

    /**
     * Counts other applications quoting the same dues reference. A reference
     * should only ever appear once, so anything above zero is worth a second
     * look before the application is approved.
     */
    private const REF_DUPLICATES = "(SELECT COUNT(*) FROM applications d
                 WHERE d.payment_reference = a.payment_reference
                   AND d.payment_reference IS NOT NULL AND d.payment_reference <> ''
                   AND d.id <> a.id) AS ref_duplicates";

    public function allWithStudent(string $status = ''): array
    {
        $sql = "SELECT a.*, s.full_name, s.student_id AS student_no, h.name AS hostel_name, pr.room_number AS preferred_room_number,
                       " . self::REF_DUPLICATES . "
                FROM applications a
                JOIN students s ON s.id = a.student_id
                LEFT JOIN hostels h ON h.id = a.preferred_hostel_id
                LEFT JOIN rooms pr ON pr.id = a.preferred_room_id WHERE 1";
        $params = [];
        if ($status !== '') {
            $sql .= " AND a.status = ?";
            $params[] = $status;
        }
        // Hostel admins review applications directed at their own hostel.
        [$scope, $bind] = Scope::on('a.preferred_hostel_id');
        $sql .= $scope;
        array_push($params, ...$bind);
        $sql .= " ORDER BY a.priority DESC, a.created_at DESC";
        return Database::all($sql, $params);
    }

    /**
     * The review list, filtered. Every filter is optional (empty = ignored);
     * hostel isolation is always applied on top, so a hostel-bound admin can
     * never widen past their own hostel.
     *
     * @param array{q?:string,status?:string,payment?:string,hostel?:string} $filters
     */
    public function paginatedWithStudent(array $filters, int $page, int $perPage = 15): array
    {
        // Count and rows must share the joins, or a search on a joined column
        // would filter the rows but not the total, and the pager would lie.
        $from = " FROM applications a
                  JOIN students s ON s.id = a.student_id
                  LEFT JOIN hostels h ON h.id = a.preferred_hostel_id
                  LEFT JOIN rooms pr ON pr.id = a.preferred_room_id";

        $where = ' WHERE 1';
        $params = [];

        if (($q = trim($filters['q'] ?? '')) !== '') {
            $where .= " AND (s.full_name LIKE ? OR s.student_id LIKE ? OR a.payment_reference LIKE ?
                             OR pr.room_number LIKE ? OR a.remarks LIKE ?)";
            $like = "%{$q}%";
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (($status = trim($filters['status'] ?? '')) !== '') {
            $where .= ' AND a.status = ?';
            $params[] = $status;
        }
        if (($payment = trim($filters['payment'] ?? '')) !== '') {
            $where .= ' AND a.payment_status = ?';
            $params[] = $payment;
        }
        if (($hostel = trim($filters['hostel'] ?? '')) !== '') {
            $where .= ' AND a.preferred_hostel_id = ?';
            $params[] = (int) $hostel;
        }
        if (($term = trim($filters['term'] ?? '')) !== '') {
            $where .= ' AND a.academic_year = ?';
            $params[] = $term;
        }

        // Hostel admins review applications directed at their own hostel.
        [$scope, $bind] = Scope::on('a.preferred_hostel_id');
        $where .= $scope;
        array_push($params, ...$bind);

        return \App\Core\Paginator::make(
            "SELECT COUNT(*){$from}{$where}",
            "SELECT a.*, s.full_name, s.student_id AS student_no, h.name AS hostel_name, pr.room_number AS preferred_room_number,
                    " . self::REF_DUPLICATES . "
             {$from}{$where}
             ORDER BY a.priority DESC, a.created_at DESC",
            $params, $page, $perPage
        );
    }

    /** Distinct academic years present on applications, for the filter dropdown. */
    public function termOptions(): array
    {
        [$scope, $bind] = Scope::on('preferred_hostel_id');
        return array_column(Database::all(
            "SELECT DISTINCT academic_year FROM applications
             WHERE academic_year IS NOT NULL AND academic_year <> ''{$scope}
             ORDER BY academic_year DESC",
            $bind
        ), 'academic_year');
    }
}
