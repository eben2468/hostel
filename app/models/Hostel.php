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
        'dues_bank_name','dues_account_name','dues_account_number','dues_branch',
        'dues_momo_network','dues_momo_name','dues_momo_number','dues_instructions',
        'dues_fresher_amount','dues_fresher_note','dues_continuing_amount','dues_continuing_note',
        'dues_reference_required',
    ];

    /** The bank / mobile-money account a hostel collects hall dues into. */
    public const DUES_ACCOUNT_FIELDS = [
        'dues_bank_name', 'dues_account_name', 'dues_account_number', 'dues_branch',
        'dues_momo_network', 'dues_momo_name', 'dues_momo_number', 'dues_instructions',
    ];

    /** What freshers vs continuing students owe, and the notes explaining it. */
    public const DUES_NOTICE_FIELDS = [
        'dues_fresher_amount', 'dues_fresher_note',
        'dues_continuing_amount', 'dues_continuing_note',
    ];

    /** The two student categories dues are quoted for. */
    public const STUDENT_TYPES = ['fresher' => 'Fresh student', 'continuing' => 'Continuing student'];

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
     * Everything a student needs in order to pay their hall dues: the account
     * details, the amounts per student category and the admin's notes. Returns
     * an empty array when the hostel is unknown, so callers can just check it.
     */
    public function dues(?int $hostelId): array
    {
        if (!$hostelId) {
            return [];
        }
        $cols = implode(', ', array_merge(self::DUES_ACCOUNT_FIELDS, self::DUES_NOTICE_FIELDS));
        $row = Database::first(
            "SELECT id AS hostel_id, name AS hostel_name, academic_year, semester,
                    dues_reference_required, {$cols}
             FROM hostels WHERE id = ?",
            [$hostelId]
        );
        return $row ?: [];
    }

    /** True once the hostel has published at least one way to pay. */
    public static function duesPublished(array $dues): bool
    {
        return trim((string) ($dues['dues_account_number'] ?? '')) !== ''
            || trim((string) ($dues['dues_momo_number'] ?? '')) !== '';
    }

    /**
     * True when applicants must submit a payment reference. Only enforced once
     * an account has actually been published, so switching the feature on never
     * blocks students of a hostel that has not set up dues collection yet.
     */
    public static function duesReferenceRequired(array $dues): bool
    {
        return self::duesPublished($dues) && (int) ($dues['dues_reference_required'] ?? 1) === 1;
    }

    /** The dues amount quoted for a student category, or null when unpriced. */
    public static function duesAmountFor(array $dues, ?string $studentType): ?float
    {
        $key = $studentType === 'continuing' ? 'dues_continuing_amount' : 'dues_fresher_amount';
        $amount = $dues[$key] ?? null;
        return $amount === null || $amount === '' ? null : (float) $amount;
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
