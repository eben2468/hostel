<?php
namespace App\Services;

use App\Models\Setting;

require_once ROOT_PATH . '/vendor/fpdf/fpdf.php';

/**
 * FPDF subclass with a branded header/footer and a simple table helper.
 * Shared by the report and statement generators.
 */
class Pdf extends \FPDF
{
    public string $docTitle = '';
    public string $docSubtitle = '';

    public function Header(): void
    {
        $brand = Setting::get('institution_name') ?: APP_NAME;
        $this->SetFillColor(37, 74, 122); // primary-700 (navy)
        $this->Rect(0, 0, $this->GetPageWidth(), 26, 'F');
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 15);
        $this->SetXY(12, 7);
        $this->Cell(0, 8, $this->ascii($brand), 0, 1);
        $this->SetFont('Arial', '', 10);
        $this->SetX(12);
        $this->Cell(0, 5, $this->ascii($this->docTitle), 0, 1);
        $this->SetTextColor(33, 37, 41);
        $this->SetY(32);
        if ($this->docSubtitle !== '') {
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(120, 120, 120);
            $this->Cell(0, 5, $this->ascii($this->docSubtitle), 0, 1);
            $this->SetTextColor(33, 37, 41);
            $this->Ln(2);
        }
    }

    public function Footer(): void
    {
        $brand = Setting::get('institution_name') ?: APP_NAME;
        $this->SetY(-15);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 6, $this->ascii('Generated ' . date('d M Y H:i') . '  -  ' . $brand), 0, 0, 'L');
        $this->Cell(0, 6, 'Page ' . $this->PageNo(), 0, 0, 'R');
    }

    /** Section heading. */
    public function section(string $title): void
    {
        // Avoid orphaning a heading at the very bottom of a page.
        if ($this->GetY() > $this->GetPageHeight() - 40) {
            $this->AddPage();
        }
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(37, 74, 122); // primary-700 (navy)
        $this->Cell(0, 7, $this->ascii($title), 0, 1);
        $this->SetTextColor(33, 37, 41);
    }

    /**
     * Render a table.
     *
     * @param array      $headers list of column labels
     * @param array      $rows    list of rows (each a list of cell strings)
     * @param array      $widths  column widths in mm (sum should fit the page)
     * @param array|null $align   per-column alignment ('L','C','R')
     */
    public function table(array $headers, array $rows, array $widths, ?array $align = null): void
    {
        $align = $align ?: array_fill(0, count($headers), 'L');

        // Header row.
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(243, 244, 246);
        $this->SetTextColor(75, 85, 99);
        foreach ($headers as $i => $h) {
            $this->Cell($widths[$i], 8, $this->ascii($h), 1, 0, $align[$i], true);
        }
        $this->Ln();

        // Body rows.
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(33, 37, 41);
        $fill = false;
        foreach ($rows as $row) {
            // Page-break guard.
            if ($this->GetY() > $this->GetPageHeight() - 25) {
                $this->AddPage();
            }
            $this->SetFillColor(249, 250, 251);
            foreach ($row as $i => $cell) {
                $this->Cell($widths[$i], 7, $this->ascii((string) $cell), 1, 0, $align[$i] ?? 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }
    }

    /** Two-column key/value block. */
    public function keyValues(array $pairs): void
    {
        $this->SetFont('Arial', '', 10);
        foreach ($pairs as $label => $value) {
            $this->SetTextColor(120, 120, 120);
            $this->Cell(45, 7, $this->ascii($label), 0, 0);
            $this->SetTextColor(33, 37, 41);
            $this->Cell(0, 7, $this->ascii((string) $value), 0, 1);
        }
    }

    public function ascii(string $text): string
    {
        $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        return $c !== false ? $c : $text;
    }
}
