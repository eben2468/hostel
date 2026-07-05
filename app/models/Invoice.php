<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;
use App\Core\Scope;

class Invoice extends Model
{
    protected string $table = 'invoices';
    protected array $fillable = [
        'invoice_no','student_id','allocation_id','academic_year','semester','description','amount',
        'amount_paid','balance','due_date','status',
    ];

    public function allWithStudent(): array
    {
        [$scope, $bind] = Scope::onStudent('i.student_id');
        return Database::all(
            "SELECT i.*, s.full_name, s.student_id AS student_no
             FROM invoices i JOIN students s ON s.id = i.student_id
             WHERE 1{$scope}
             ORDER BY i.created_at DESC",
            $bind
        );
    }

    public function forStudent(int $studentId): array
    {
        return $this->where('student_id', $studentId);
    }

    /** Apply a payment to an invoice and recompute its status. */
    public function applyPayment(int $invoiceId, float $amount): void
    {
        $inv = $this->find($invoiceId);
        if (!$inv) {
            return;
        }
        $paid = (float) $inv['amount_paid'] + $amount;
        $balance = max(0, (float) $inv['amount'] - $paid);
        $status = $balance <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid');
        Database::run(
            "UPDATE invoices SET amount_paid = ?, balance = ?, status = ? WHERE id = ?",
            [$paid, $balance, $status, $invoiceId]
        );
    }

    public static function generateNumber(): string
    {
        return 'INV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    }
}
