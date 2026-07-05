<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Payment extends Model
{
    protected string $table = 'payments';
    protected array $fillable = [
        'receipt_no','invoice_id','student_id','academic_year','semester','amount','method','reference',
        'status','verified_by','paid_at',
    ];

    public function allDetailed(): array
    {
        [$scope, $bind] = Scope::onStudent('p.student_id');
        return Database::all(
            "SELECT p.*, s.full_name, s.student_id AS student_no, i.invoice_no
             FROM payments p
             JOIN students s ON s.id = p.student_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE 1{$scope}
             ORDER BY p.paid_at DESC",
            $bind
        );
    }

    public function paginatedDetailed(int $page, int $perPage = 15): array
    {
        // Same single bind value applies to both the COUNT and the rows query.
        [$scopeCount, $bind] = Scope::onStudent('student_id');
        [$scopeRows] = Scope::onStudent('p.student_id');
        return \App\Core\Paginator::make(
            "SELECT COUNT(*) FROM payments WHERE 1{$scopeCount}",
            "SELECT p.*, s.full_name, s.student_id AS student_no, i.invoice_no
             FROM payments p
             JOIN students s ON s.id = p.student_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE 1{$scopeRows}
             ORDER BY p.paid_at DESC",
            $bind, $page, $perPage
        );
    }

    public function detailed(int $id): ?array
    {
        return Database::first(
            "SELECT p.*, s.full_name, s.student_id AS student_no, s.programme,
                    h.name AS hostel_name, h.code AS hostel_code, h.address AS hostel_address,
                    h.digital_address AS hostel_digital_address,
                    i.invoice_no, i.description, i.amount AS invoice_amount, i.balance AS invoice_balance
             FROM payments p
             JOIN students s ON s.id = p.student_id
             LEFT JOIN hostels h ON h.id = s.hostel_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE p.id = ?",
            [$id]
        );
    }

    public function forStudent(int $studentId): array
    {
        return Database::all(
            "SELECT p.*, i.invoice_no FROM payments p
             LEFT JOIN invoices i ON i.id = p.invoice_id
             WHERE p.student_id = ? ORDER BY p.paid_at DESC",
            [$studentId]
        );
    }

    public static function generateReceiptNo(): string
    {
        return 'RCP-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }
}
