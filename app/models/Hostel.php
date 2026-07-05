<?php
namespace App\Models;

use App\Core\Model;
use App\Core\Database;

class Hostel extends Model
{
    protected string $table = 'hostels';
    protected array $fillable = [
        'name','code','type','address','digital_address','capacity','manager',
        'description','facilities','image','status',
        'academic_year','semester','applications_open',
        'paystack_public_key','paystack_secret_key','paystack_enabled',
    ];

    /** The hostel's active term, for stamping new records. */
    public function term(int $hostelId): array
    {
        $row = Database::first("SELECT academic_year, semester FROM hostels WHERE id = ?", [$hostelId]);
        return [
            'academic_year' => $row['academic_year'] ?? null,
            'semester'      => $row['semester'] ?? null,
        ];
    }

    /**
     * Rolling list of academic-year options (e.g. 2025/2026 …) generated from the
     * current calendar year. $current is always included so a saved value shows.
     */
    public static function yearOptions(?string $current = null): array
    {
        $base = (int) date('Y');
        $years = [];
        for ($y = $base - 1; $y <= $base + 4; $y++) {
            $years[] = $y . '/' . ($y + 1);
        }
        if ($current && !in_array($current, $years, true)) {
            array_unshift($years, $current);
        }
        return $years;
    }
}
