<?php
namespace App\Services;

/** Generates the financial/occupancy report and per-student statement PDFs. */
class ReportPdf
{
    private static function cur(): string
    {
        return defined('CURRENCY') ? CURRENCY : 'GHS';
    }

    private static function money($n): string
    {
        return self::cur() . ' ' . number_format((float) $n, 2);
    }

    /**
     * Financial + occupancy summary report.
     *
     * @param array $data keys: finance, methods, occupancy, applications, complaints
     */
    public static function financial(array $data, string $dest = 'D'): string
    {
        $term   = $data['term'] ?? ['year' => '', 'sem' => ''];
        $period = ($term['year'] ?? '') !== '' ? $term['year'] . ' - ' . $term['sem'] . ' Semester' : 'All terms';

        $pdf = new Pdf('P', 'mm', 'A4');
        $pdf->docTitle = 'Financial & Occupancy Report';
        $pdf->docSubtitle = 'Period: ' . $period . '   |   Generated ' . date('d M Y H:i');
        $pdf->AddPage();

        // Financial summary
        $pdf->section('Financial Summary');
        $f = $data['finance'];
        $invoiced  = (float) $f['total_invoiced'];
        $collected = (float) $f['total_collected'];
        $rate = $invoiced > 0 ? round($collected / $invoiced * 100) . '%' : '-';
        $pdf->keyValues([
            'Total Invoiced'  => self::money($f['total_invoiced']),
            'Total Collected' => self::money($f['total_collected']),
            'Outstanding'     => self::money($f['outstanding']),
            'Refunded'        => self::money($f['refunded']),
            'Collection Rate' => $rate,
        ]);

        // Payment methods
        $pdf->section('Collections by Payment Method');
        if (!empty($data['methods'])) {
            $rows = array_map(fn($m) => [
                ucfirst(str_replace('_', ' ', $m['method'])), (int) $m['cnt'], self::money($m['total']),
            ], $data['methods']);
            $pdf->table(['Method', 'Count', 'Total'], $rows, [90, 40, 60], ['L', 'C', 'R']);
        } else {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 6, 'No payments recorded.', 0, 1);
        }

        // Occupancy
        $pdf->section('Occupancy by Hostel');
        if (!empty($data['occupancy'])) {
            $rows = array_map(function ($o) {
                $pct = $o['capacity'] > 0 ? round($o['occupied'] / $o['capacity'] * 100) : 0;
                return [$o['name'], (int) $o['rooms'], (int) $o['occupied'] . ' / ' . (int) $o['capacity'], $pct . '%'];
            }, $data['occupancy']);
            $pdf->table(['Hostel', 'Rooms', 'Beds (occ/cap)', 'Occupancy'], $rows, [90, 30, 40, 30], ['L', 'C', 'C', 'R']);
        } else {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 6, 'No hostels configured.', 0, 1);
        }

        // Applications funnel
        $pdf->section('Applications by Status');
        self::countTable($pdf, $data['applications'] ?? [], 'status', 'No applications.');

        // Complaints
        $pdf->section('Complaints by Status (all-time)');
        self::countTable($pdf, $data['complaints'] ?? [], 'status', 'No complaints.');

        // Gender split
        $pdf->section('Students by Gender (all-time)');
        self::countTable($pdf, $data['gender'] ?? [], 'gender', 'No students.');

        return (string) $pdf->Output($dest, 'financial_report_' . date('Ymd') . '.pdf');
    }

    /** Render a simple label/count table (with total), or an empty note. */
    private static function countTable(Pdf $pdf, array $rows, string $labelKey, string $emptyNote): void
    {
        if (!$rows) {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 6, $emptyNote, 0, 1);
            return;
        }
        $total = 0;
        $body = [];
        foreach ($rows as $r) {
            $total += (int) $r['cnt'];
            $body[] = [ucwords(str_replace('_', ' ', (string) $r[$labelKey])), (int) $r['cnt']];
        }
        $body[] = ['Total', $total];
        $pdf->table(['Status', 'Count'], $body, [130, 60], ['L', 'R']);
    }

    /**
     * Per-student account statement.
     *
     * @param array $student  student row
     * @param array $invoices invoice rows
     * @param array $payments payment rows
     */
    public static function statement(array $student, array $invoices, array $payments, string $dest = 'D'): string
    {
        $pdf = new Pdf('P', 'mm', 'A4');
        $pdf->docTitle = 'Student Account Statement';
        $pdf->docSubtitle = 'Generated ' . date('d M Y H:i');
        $pdf->AddPage();

        $pdf->section('Student');
        $pdf->keyValues([
            'Name'       => $student['full_name'],
            'Student ID' => $student['student_id'],
            'Programme'  => $student['programme'] ?: '-',
            'Email'      => $student['email'] ?: '-',
            'Phone'      => $student['phone'] ?: '-',
        ]);

        $totalBalance = 0.0;
        $pdf->section('Invoices');
        if ($invoices) {
            $rows = [];
            foreach ($invoices as $i) {
                $totalBalance += (float) $i['balance'];
                $rows[] = [
                    $i['invoice_no'],
                    substr((string) ($i['description'] ?? ''), 0, 32),
                    self::money($i['amount']),
                    self::money($i['amount_paid']),
                    self::money($i['balance']),
                    ucfirst($i['status']),
                ];
            }
            $pdf->table(['Invoice', 'Description', 'Amount', 'Paid', 'Balance', 'Status'],
                $rows, [28, 50, 25, 25, 25, 24], ['L', 'L', 'R', 'R', 'R', 'C']);
        } else {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 6, 'No invoices.', 0, 1);
        }

        $pdf->section('Payments');
        if ($payments) {
            $rows = array_map(fn($p) => [
                $p['receipt_no'],
                date('d M Y', strtotime($p['paid_at'])),
                ucfirst(str_replace('_', ' ', $p['method'])),
                self::money($p['amount']),
            ], $payments);
            $pdf->table(['Receipt', 'Date', 'Method', 'Amount'], $rows, [45, 35, 45, 40], ['L', 'L', 'L', 'R']);
        } else {
            $pdf->SetFont('Arial', 'I', 9);
            $pdf->Cell(0, 6, 'No payments.', 0, 1);
        }

        // Balance box
        $pdf->Ln(4);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetFillColor($totalBalance > 0 ? 254 : 236, $totalBalance > 0 ? 226 : 253, $totalBalance > 0 ? 226 : 245);
        $pdf->Cell(0, 12, $pdf->ascii('Outstanding Balance: ' . self::money($totalBalance)), 1, 1, 'R', true);

        return (string) $pdf->Output($dest, 'statement_' . preg_replace('/[^A-Za-z0-9_-]/', '', $student['student_id']) . '.pdf');
    }
}
