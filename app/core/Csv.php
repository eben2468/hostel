<?php
namespace App\Core;

/** Streams an array of associative rows to the browser as a CSV download. */
class Csv
{
    /**
     * @param string $filename download filename (without extension)
     * @param array  $rows      list of associative arrays (all same keys)
     * @param array  $headers   optional explicit column order/labels [key => Label]
     */
    public static function download(string $filename, array $rows, array $headers = []): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Ymd_His') . '.csv"');
        header('Cache-Control: no-store, no-cache');

        $out = fopen('php://output', 'w');

        if (!$rows && !$headers) {
            fputcsv($out, ['No data']);
            fclose($out);
            return;
        }

        // Determine columns.
        if ($headers) {
            $keys   = array_keys($headers);
            $labels = array_values($headers);
        } else {
            $keys   = array_keys($rows[0]);
            $labels = $keys;
        }
        fputcsv($out, $labels);

        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $k) {
                $line[] = $row[$k] ?? '';
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }
}
