<?php
namespace App\Services;

/**
 * Reads a spreadsheet-ish file into a plain array of rows of cell strings.
 *
 * Supports .txt / .tsv (tab separated), .csv (comma or semicolon) and .xlsx.
 *
 * XLSX is a ZIP of XML parts. The `zip` PHP extension is often switched off on
 * shared hosting, so this falls back to reading the archive by hand — the
 * central directory gives the entry offsets and `gzinflate()` (zlib, which is
 * effectively always present) does the decompression. That keeps uploads
 * working without asking anyone to change php.ini.
 */
class SheetReader
{
    public const EXTENSIONS = ['txt', 'tsv', 'csv', 'xlsx'];
    private const MAX_ROWS  = 20000;

    /**
     * @return array{ok:bool, rows?:array<int,array<int,string>>, error?:string}
     */
    public static function read(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($ext === 'xls') {
            return ['ok' => false, 'error' =>
                'Old-style .xls files are not supported. Open the file in Excel and use '
                . 'File → Save As → "Excel Workbook (.xlsx)" or "CSV", then upload that.'];
        }
        if (!in_array($ext, self::EXTENSIONS, true)) {
            return ['ok' => false, 'error' =>
                'Unsupported file type ".' . $ext . '". Upload a .xlsx, .csv, .txt or .tsv file.'];
        }
        if (!is_readable($path)) {
            return ['ok' => false, 'error' => 'The uploaded file could not be read.'];
        }

        return $ext === 'xlsx' ? self::readXlsx($path) : self::readDelimited($path);
    }

    // ---------------------------------------------------------------- text --

    /** Tab / comma / semicolon separated text, delimiter detected from the body. */
    private static function readDelimited(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'The uploaded file could not be read.'];
        }
        $raw = self::toUtf8($raw);
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $delimiter = self::sniffDelimiter($raw);
        $rows = [];
        foreach (explode("\n", $raw) as $line) {
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            // str_getcsv handles quoted cells such as "2ND SEMESTER, 2025/2026",
            // which must not be split on its internal comma.
            $cells = str_getcsv($line, $delimiter, '"', '\\');
            $rows[] = array_map(fn($c) => trim((string) $c), $cells);
        }
        return ['ok' => true, 'rows' => $rows];
    }

    /** Pick the delimiter that yields the most columns across the sample. */
    private static function sniffDelimiter(string $raw): string
    {
        $sample = array_slice(explode("\n", $raw), 0, 40);
        $best = "\t";
        $bestScore = 0;
        foreach (["\t", ',', ';', '|'] as $d) {
            $score = 0;
            foreach ($sample as $line) {
                $score += substr_count($line, $d);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $d;
            }
        }
        return $best;
    }

    private static function toUtf8(string $s): string
    {
        // Strip a UTF-8 BOM, then convert only when the bytes are not valid UTF-8.
        $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
        }
        return $s;
    }

    // ---------------------------------------------------------------- xlsx --

    private static function readXlsx(string $path): array
    {
        $sheet  = self::zipEntry($path, 'xl/worksheets/sheet1.xml');
        if ($sheet === null) {
            return ['ok' => false, 'error' =>
                'That .xlsx file could not be opened. Re-save it from Excel, or export it as CSV.'];
        }
        $shared = self::sharedStrings(self::zipEntry($path, 'xl/sharedStrings.xml'));

        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($sheet);
        libxml_use_internal_errors($prev);
        if ($xml === false || !isset($xml->sheetData)) {
            return ['ok' => false, 'error' => 'That .xlsx file is not readable. Export it as CSV instead.'];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            if (count($rows) >= self::MAX_ROWS) {
                break;
            }
            $cells = [];
            foreach ($row->c as $c) {
                // The cell reference (A3, B3, ...) carries the real column, so
                // blank cells that Excel omits do not shift the row leftwards.
                $col = self::columnIndex((string) $c['r']);
                $cells[$col >= 0 ? $col : count($cells)] = self::cellValue($c, $shared);
            }
            if ($cells) {
                ksort($cells);
                $rows[] = array_values(array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells));
            } else {
                $rows[] = [];
            }
        }
        return ['ok' => true, 'rows' => $rows];
    }

    /** Resolve one cell to text, following shared strings and inline strings. */
    private static function cellValue(\SimpleXMLElement $c, array $shared): string
    {
        $type = (string) $c['t'];
        if ($type === 's') {
            $i = (int) $c->v;
            return $shared[$i] ?? '';
        }
        if ($type === 'inlineStr') {
            return trim(self::plainText($c->is));
        }
        if (isset($c->v)) {
            return trim((string) $c->v);
        }
        return '';
    }

    /** 'BC12' -> 54 (zero-based column index); -1 when the ref is unusable. */
    private static function columnIndex(string $ref): int
    {
        if (!preg_match('/^([A-Z]+)/', strtoupper($ref), $m)) {
            return -1;
        }
        $n = 0;
        foreach (str_split($m[1]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** The <si> entries of sharedStrings.xml, flattened to plain text. */
    private static function sharedStrings(?string $xmlText): array
    {
        if ($xmlText === null || $xmlText === '') {
            return [];
        }
        $prev = libxml_use_internal_errors(true);
        $xml  = simplexml_load_string($xmlText);
        libxml_use_internal_errors($prev);
        if ($xml === false) {
            return [];
        }
        $out = [];
        foreach ($xml->si as $si) {
            $out[] = trim(self::plainText($si));
        }
        return $out;
    }

    /** Concatenate <t> runs inside a shared/inline string node. */
    private static function plainText(\SimpleXMLElement $node): string
    {
        if (isset($node->t)) {
            return (string) $node->t;
        }
        $text = '';
        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }
        return $text;
    }

    // ----------------------------------------------------------------- zip --

    /** Read one entry out of a ZIP, using the extension when it is available. */
    private static function zipEntry(string $path, string $entry): ?string
    {
        if (class_exists('\ZipArchive')) {
            $zip = new \ZipArchive();
            if ($zip->open($path) === true) {
                $data = $zip->getFromName($entry);
                $zip->close();
                return $data === false ? null : $data;
            }
            return null;
        }
        return self::zipEntryFallback($path, $entry);
    }

    /**
     * Minimal ZIP reader for hosts without the `zip` extension.
     *
     * Walks the central directory (located from the end-of-central-directory
     * record) to find the entry, then inflates its local record. Only the two
     * compression methods a real .xlsx uses are handled: stored and deflate.
     */
    private static function zipEntryFallback(string $path, string $entry): ?string
    {
        $data = file_get_contents($path);
        if ($data === false || strlen($data) < 22) {
            return null;
        }

        // The EOCD sits at the very end, after an optional comment.
        $eocd = strrpos($data, "PK\x05\x06");
        if ($eocd === false) {
            return null;
        }
        $count  = self::u16($data, $eocd + 10);
        $offset = self::u32($data, $eocd + 16);

        // Central directory header layout (offsets from the signature):
        //   10 method, 20 compressed size, 28 name len, 30 extra len,
        //   32 comment len, 42 local header offset, 46 name.
        for ($i = 0; $i < $count; $i++) {
            if ($offset + 46 > strlen($data) || substr($data, $offset, 4) !== "PK\x01\x02") {
                return null;
            }
            $method     = self::u16($data, $offset + 10);
            $csize      = self::u32($data, $offset + 20);
            $nameLen    = self::u16($data, $offset + 28);
            $extraLen   = self::u16($data, $offset + 30);
            $commentLen = self::u16($data, $offset + 32);
            $local      = self::u32($data, $offset + 42);
            $name       = substr($data, $offset + 46, $nameLen);

            if ($name === $entry) {
                return self::inflateLocal($data, $local, $method, $csize);
            }
            $offset += 46 + $nameLen + $extraLen + $commentLen;
        }
        return null;
    }

    private static function u16(string $data, int $at): int
    {
        return unpack('v', substr($data, $at, 2))[1];
    }

    private static function u32(string $data, int $at): int
    {
        return unpack('V', substr($data, $at, 4))[1];
    }

    /** Inflate one local file record whose position the directory gave us. */
    private static function inflateLocal(string $data, int $local, int $method, int $csize): ?string
    {
        if ($local + 30 > strlen($data) || substr($data, $local, 4) !== "PK\x03\x04") {
            return null;
        }
        // The local header repeats the name/extra lengths, and they can differ
        // from the central directory's, so they must be read again here.
        $nameLen  = self::u16($data, $local + 26);
        $extraLen = self::u16($data, $local + 28);
        $start    = $local + 30 + $nameLen + $extraLen;
        $body     = substr($data, $start, $csize);

        if ($method === 0) {
            return $body;
        }
        if ($method !== 8) {
            return null; // bzip2/lzma are never produced by Excel
        }
        $out = @gzinflate($body);
        return $out === false ? null : $out;
    }
}
