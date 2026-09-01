<?php
namespace App\Services;

use App\Models\DuesDebtor;

/**
 * Turns the raw rows of an uploaded debtors sheet into debtor records.
 *
 * Real hall lists are not tidy CSVs: they carry title banners, a "2ND SEMESTER,
 * 2025/2026" heading that applies to every row beneath it, a serial-number
 * column, merged/blank cells and phone numbers whose leading zero Excel ate.
 * Rather than demand a fixed column order, each cell is classified by what it
 * looks like, so a list typed in a different order still imports.
 *
 * Every row that could not be read is returned in `skipped` with its line
 * number, so the admin sees exactly what was ignored instead of silently
 * losing people.
 */
class DebtorListParser
{
    /**
     * @param array<int,array<int,string>> $rows raw cells from SheetReader
     * @return array{records:array<int,array>, skipped:array<int,array{line:int,text:string}>,
     *               warnings:array<int,string>}
     */
    public static function parse(array $rows): array
    {
        $records = [];
        $skipped = [];
        $year = null;
        $semester = null;

        foreach ($rows as $i => $cells) {
            $cells = array_values(array_filter(
                array_map(fn($c) => trim((string) $c), $cells),
                fn($c) => $c !== ''
            ));
            if (!$cells) {
                continue; // blank spacer row
            }

            $line = implode(' ', $cells);

            // A heading such as "2ND SEMESTER, 2025/2026" sets the term for
            // every row that follows, until the next heading.
            $term = self::readTerm($line);
            if ($term !== null) {
                [$year, $semester] = $term;
                continue;
            }

            $record = self::readRecord($cells);
            if ($record === null) {
                // Banners and column headings are expected noise, not failures.
                if (!self::isNoise($line)) {
                    $skipped[] = ['line' => $i + 1, 'text' => mb_substr($line, 0, 120)];
                }
                continue;
            }

            $record['academic_year'] = $year;
            $record['semester']      = $semester;
            $records[] = $record;
        }

        return ['records' => $records, 'skipped' => $skipped, 'warnings' => self::warnings($records)];
    }

    /**
     * Problems that still import but will not match anybody, so the admin can
     * correct the source list. A typo'd phone or ID is worse than a missing one:
     * it looks like the student is covered when nothing will ever match them.
     */
    private static function warnings(array $records): array
    {
        $out = [];
        $seen = [];

        // A row with no term still blocks correctly, but the student is shown
        // "a previous semester" instead of which one, so it is worth saying.
        $noTerm = count(array_filter($records, fn($r) => empty($r['semester'])));
        if ($noTerm > 0) {
            $out[] = "{$noTerm} row(s) have no semester — the heading above them did not say which one. "
                . 'They still block the student; edit a row to set its semester, or add a heading '
                . 'like "1ST SEMESTER, 2025/2026" above that block and re-upload.';
        }

        foreach ($records as $r) {
            $name = $r['full_name'] ?: ($r['student_no'] ?: 'a row');

            // A 10-digit local number must start with 0; anything else is a slip
            // (an extra digit, or a digit dropped from the middle).
            $digits = preg_replace('/\D/', '', (string) $r['phone']);
            if ($digits !== '' && strlen($digits) === 10 && $digits[0] !== '0') {
                $out[] = "{$name}: phone \"{$r['phone']}\" does not look like a valid number — check it, or this person will never be matched.";
            } elseif ($digits !== '' && (strlen($digits) < 9 || strlen($digits) > 12)) {
                $out[] = "{$name}: phone \"{$r['phone']}\" is not a usable length.";
            }

            if ($r['student_no'] === null && $r['phone'] === null) {
                $out[] = "{$name}: no student ID or phone number — cannot be matched.";
            }

            // The same ID against two different names means one of them is wrong.
            $key = $r['student_no_norm'];
            if ($key !== null) {
                if (isset($seen[$key]) && $seen[$key] !== $r['full_name']) {
                    $out[] = "Student ID {$r['student_no']} is listed for both \"{$seen[$key]}\" and \"{$r['full_name']}\" — one of them is wrong.";
                }
                $seen[$key] = $r['full_name'];
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * Read "1ST SEMESTER, 2025/2026" (and looser variants) out of a heading.
     *
     * @return array{0:?string,1:?string}|null [academic year, semester]
     */
    private static function readTerm(string $line): ?array
    {
        $upper = strtoupper($line);
        if (!str_contains($upper, 'SEMESTER') && !str_contains($upper, 'TRIMESTER')
            && !preg_match('/\bSEM\b/', $upper)) {
            return null;
        }
        // A heading names a term but carries no student ID; a data row might
        // mention "semester" in a name field, so require the absence of one.
        if (self::findStudentNo(preg_split('/\s+/', $upper) ?: []) !== null) {
            return null;
        }

        $year = null;
        $rest = $upper;
        if (preg_match('~(20\d{2})\s*/\s*(20\d{2}|\d{2})~', $upper, $m)) {
            $year = strlen($m[2]) === 2 ? $m[1] . '/20' . $m[2] : $m[1] . '/' . $m[2];
            // Take the year out before hunting for the ordinal, so its digits
            // cannot be mistaken for a semester number.
            $rest = str_replace($m[0], ' ', $upper);
        }

        // Halls write this every which way: "1ST SEMESTER", "FIRST SEMESTER",
        // "SEMESTER 1", "SEM 2", "SEMESTER II". Match the ordinal wherever it
        // sits rather than assuming it comes first.
        $semester = null;
        if (preg_match('/\b(FIRST|1ST|ONE|I|1)\b/', $rest)) {
            $semester = 'First';
        }
        if (preg_match('/\b(SECOND|2ND|TWO|II|2)\b/', $rest)) {
            $semester = 'Second';
        }
        if (preg_match('/\b(THIRD|3RD|THREE|III|3)\b/', $rest)) {
            $semester = 'Third';
        }
        return ($year === null && $semester === null) ? null : [$year, $semester];
    }

    /** Title banners and column-header rows we deliberately do not report. */
    private static function isNoise(string $line): bool
    {
        $u = strtoupper($line);
        foreach (['UNIVERSITY', 'DEBTOR', 'HALL DUES', 'HOSTEL', 'NAME', 'STUDENT', 'TOTAL', 'AMOUNT', 'S/N', 'ROOM'] as $word) {
            if (str_contains($u, $word)) {
                return true;
            }
        }
        return trim($line) === '' || preg_match('/^[\d\s.,-]+$/', $line) === 1;
    }

    /**
     * Classify the cells of one row into a debtor record.
     * Returns null when the row carries neither a student ID nor a phone —
     * without one of those there is nothing to match a student on.
     */
    private static function readRecord(array $cells): ?array
    {
        $studentNo = self::findStudentNo($cells);
        $phone     = self::findPhone($cells, $studentNo);
        if ($studentNo === null && $phone === null) {
            return null;
        }

        return [
            'full_name'       => self::findName($cells),
            'student_no'      => $studentNo,
            'student_no_norm' => $studentNo !== null ? DuesDebtor::normaliseStudentNo($studentNo) : null,
            'phone'           => $phone,
            'phone_norm'      => $phone !== null ? DuesDebtor::normalisePhone($phone) : null,
            'room_label'      => $room = self::findRoom($cells, $studentNo, $phone),
            'amount'          => self::findAmount($cells, $studentNo, $phone, $room),
        ];
    }

    /**
     * A student ID mixes letters and digits — "226TR02000104", "25CS02000225".
     * Requiring both rules out phone numbers, room labels and serial numbers.
     */
    private static function findStudentNo(array $cells): ?string
    {
        foreach ($cells as $c) {
            $v = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $c));
            if (strlen($v) >= 8 && preg_match('/[A-Z]/', $v) && preg_match('/\d{4,}/', $v)) {
                return trim($c);
            }
        }
        return null;
    }

    /**
     * A phone is 9-12 bare digits. Ghanaian numbers are 10 with the leading
     * zero, but a sheet that stored them as numbers drops it, leaving 9.
     */
    private static function findPhone(array $cells, ?string $studentNo): ?string
    {
        foreach ($cells as $c) {
            if ($studentNo !== null && $c === $studentNo) {
                continue;
            }
            $digits = preg_replace('/\D/', '', $c);
            // Reject anything with a decimal point: that is an amount (150.00).
            if (str_contains($c, '.') || $digits === '') {
                continue;
            }
            if (strlen($digits) >= 9 && strlen($digits) <= 12) {
                return trim($c);
            }
        }
        return null;
    }

    /** The longest mostly-alphabetic cell is the name. */
    private static function findName(array $cells): ?string
    {
        $best = null;
        foreach ($cells as $c) {
            $letters = preg_match_all('/[A-Za-z]/', $c);
            if ($letters >= 4 && $letters >= strlen($c) * 0.6) {
                if ($best === null || strlen($c) > strlen($best)) {
                    $best = $c;
                }
            }
        }
        return $best !== null ? preg_replace('/\s+/', ' ', trim($best)) : null;
    }

    /** A short token like GF1 / SF18 / "GF 21" that is not the ID or phone. */
    private static function findRoom(array $cells, ?string $studentNo, ?string $phone): ?string
    {
        foreach ($cells as $c) {
            if ($c === $studentNo || $c === $phone) {
                continue;
            }
            $v = strtoupper(trim($c));
            if (preg_match('/^[A-Z]{1,3}\s?\d{1,3}$/', $v)) {
                return preg_replace('/\s+/', '', $v);
            }
        }
        return null;
    }

    /**
     * The amount owed.
     *
     * A spreadsheet holds a currency cell as the bare number 150 — the ".00" is
     * display formatting and never reaches us — so plain integers have to be
     * accepted. The only other bare integer on these rows is the leading serial
     * number, which is dropped by position: after blank cells are stripped, it
     * is always the row's first cell.
     */
    private static function findAmount(array $cells, ?string $studentNo, ?string $phone, ?string $room): ?float
    {
        $candidates = [];
        foreach ($cells as $i => $c) {
            if ($c === $studentNo || $c === $phone) {
                continue;
            }
            if ($room !== null && preg_replace('/\s+/', '', strtoupper($c)) === $room) {
                continue;
            }
            $v = str_ireplace(['GHS', 'GH₵', '₵', ',', ' '], '', trim($c));
            if ($v !== '' && preg_match('/^\d{1,9}(\.\d{1,2})?$/', $v)) {
                $candidates[$i] = ['value' => (float) $v, 'decimal' => str_contains($v, '.')];
            }
        }
        // The row's first cell is the serial number, not money.
        unset($candidates[0]);
        if (!$candidates) {
            return null;
        }
        // A written-out decimal is unambiguous; otherwise the last number wins,
        // since the amount sits at the end of every list of this shape.
        foreach ($candidates as $c) {
            if ($c['decimal']) {
                return $c['value'];
            }
        }
        return end($candidates)['value'];
    }
}
