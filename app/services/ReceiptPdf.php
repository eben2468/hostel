<?php
namespace App\Services;

use App\Models\Setting;
use FPDF;

/** Builds a downloadable PDF receipt with the vendored FPDF library. */
class ReceiptPdf
{
    /**
     * Stream a PDF receipt to the browser.
     *
     * @param array  $p     payment row joined with student + hostel details
     * @param string $dest  'D' download, 'I' inline, 'S' return string
     */
    public static function render(array $p, string $dest = 'D'): string
    {
        require_once ROOT_PATH . '/vendor/fpdf/fpdf.php';

        // Brand the receipt with the student's hostel; fall back to institution.
        $institution = Setting::get('institution_name') ?: APP_NAME;
        $brand   = $p['hostel_name'] ?: $institution;
        $address = trim((string) ($p['hostel_address'] ?? ''));
        $symbol  = defined('CURRENCY') ? CURRENCY : 'GHS';

        $balance   = isset($p['invoice_balance']) ? (float) $p['invoice_balance'] : null;
        $fullyPaid = $balance === null || $balance <= 0.001;
        $term = trim(($p['academic_year'] ?? '') . ($p['semester'] ? ' - ' . $p['semester'] . ' Sem' : ''));

        $W = 148; // A5 width in mm
        $pdf = new FPDF('P', 'mm', 'A5');
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        // --- Header band -----------------------------------------------------
        $pdf->SetFillColor(37, 74, 122);           // primary-700
        $pdf->Rect(0, 0, $W, 34, 'F');
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 17);
        $pdf->SetXY(12, 8);
        $pdf->Cell(0, 8, self::ascii($brand), 0, 2);
        if ($p['hostel_code']) {
            $pdf->SetFont('Arial', '', 8);
            $pdf->SetTextColor(189, 212, 236);     // primary-200
            $pdf->Cell(0, 5, self::ascii(strtoupper((string) $p['hostel_code'])), 0, 2);
        }
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(219, 232, 245);
        $pdf->SetXY(12, 24);
        $pdf->Cell(0, 6, 'Official Payment Receipt', 0, 1);

        // --- Amount summary card --------------------------------------------
        $pdf->SetFillColor(236, 253, 245);         // green-50
        $pdf->SetDrawColor(167, 243, 208);
        $pdf->Rect(12, 42, $W - 24, 20, 'DF');
        $pdf->SetXY(16, 45);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(6, 95, 70);
        $pdf->Cell(60, 5, 'AMOUNT PAID', 0, 2);
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(22, 163, 74);           // green-600
        $pdf->Cell(60, 9, self::ascii($symbol . ' ' . number_format((float) $p['amount'], 2)), 0, 0);
        // PAID / PART-PAID pill on the right of the card
        $label = $fullyPaid ? 'PAID' : 'PART-PAID';
        $pdf->SetFont('Arial', 'B', 10);
        $pw = $pdf->GetStringWidth($label) + 8;
        $pdf->SetFillColor(22, 163, 74);
        $pdf->Rect($W - 24 - $pw, 48, $pw, 8, 'F');
        $pdf->SetXY($W - 24 - $pw, 48);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell($pw, 8, $label, 0, 0, 'C');

        // --- Detail rows -----------------------------------------------------
        $rows = [
            'Receipt No' => $p['receipt_no'],
            'Date'       => date('d M Y - H:i', strtotime($p['paid_at'])),
            'Student'    => $p['full_name'],
            'Student ID' => $p['student_no'],
        ];
        if (!empty($p['programme'])) $rows['Programme'] = $p['programme'];
        if ($term !== '')            $rows['Session']   = $term;
        if (!empty($p['invoice_no']))$rows['Invoice']   = $p['invoice_no'];
        if (!empty($p['description']))$rows['For']       = $p['description'];
        $rows['Method'] = ucwords(str_replace('_', ' ', $p['method']));
        if (!empty($p['reference'])) $rows['Reference'] = $p['reference'];
        if (!$fullyPaid)             $rows['Balance Due'] = $symbol . ' ' . number_format((float) $balance, 2);

        $y = 70;
        $pdf->SetFont('Arial', '', 10);
        foreach ($rows as $label => $value) {
            $pdf->SetXY(12, $y);
            $pdf->SetTextColor(148, 163, 184);     // gray-400
            $pdf->Cell(34, 7, self::ascii($label), 0, 0);
            $pdf->SetTextColor(30, 41, 59);        // gray-800
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->MultiCell($W - 12 - 46, 7, self::ascii((string) $value), 0, 'R');
            $pdf->SetFont('Arial', '', 10);
            $y += 7;
        }

        // --- Signature + verification ---------------------------------------
        $y = max($y + 6, 150);
        $pdf->SetDrawColor(203, 213, 225);
        $pdf->Line(12, $y, $W - 12, $y);
        $pdf->SetXY(12, $y + 2);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(80, 5, self::ascii('Verification: ' . strtoupper(substr(md5($p['receipt_no'] . ($p['reference'] ?? '')), 0, 12))), 0, 0);
        // Signature line on the right
        $pdf->Line($W - 52, $y + 12, $W - 12, $y + 12);
        $pdf->SetXY($W - 52, $y + 12);
        $pdf->Cell(40, 5, 'Authorised Signature', 0, 0, 'C');

        // --- Footer note -----------------------------------------------------
        $pdf->SetTextColor(148, 163, 184);
        $pdf->SetFont('Arial', '', 7);
        $footer = 'This is a computer-generated receipt from ' . $brand
            . ($address !== '' ? ', ' . $address : '')
            . '. Keep it as proof of payment. Generated ' . date('d M Y - H:i') . '.';
        $pdf->SetXY(12, 196);
        $pdf->MultiCell($W - 24, 4, self::ascii($footer), 0, 'C');

        $filename = 'receipt_' . preg_replace('/[^A-Za-z0-9_-]/', '', $p['receipt_no']) . '.pdf';
        return (string) $pdf->Output($dest, $filename);
    }

    /** FPDF core fonts are latin-1; transliterate to keep output clean. */
    private static function ascii(string $text): string
    {
        $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        return $converted !== false ? $converted : $text;
    }
}
