<?php

declare(strict_types=1);

namespace PdfGenerator\Pdf;

use RuntimeException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Calibration mode: overlays a 10 mm grid with labelled coordinates on the
 * template so field x/y values can be read off easily.
 */
final class GridRenderer
{
    private const STEP = 10;       // mm between grid lines
    private const MAJOR_STEP = 50; // mm between bold lines and coordinate labels

    public function __construct(private readonly PdfFactory $pdfFactory)
    {
    }

    public function render(string $templatePath, string $outputPath): void
    {
        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read template PDF: $templatePath");
        }

        $pdf = $this->pdfFactory->create();
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($content));

        for ($page = 1; $page <= $pageCount; $page++) {
            $tpl = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);
            $this->drawGrid($pdf, $size['width'], $size['height']);
        }

        $pdf->Output($outputPath, 'F');
    }

    /** Draws the labelled 10 mm calibration grid onto the current page. */
    public function drawGrid(Fpdi $pdf, float $width, float $height): void
    {
        $pdf->SetFont('helvetica', '', 5); // built-in font is enough for numeric labels
        $pdf->SetTextColor(255, 0, 0);

        // Vertical lines + X labels along the top edge
        for ($x = 0; $x <= $width; $x += self::STEP) {
            $this->setLineStyle($pdf, $x % self::MAJOR_STEP === 0);
            $pdf->Line((float) $x, 0, (float) $x, $height);
            $pdf->Text((float) $x + 0.4, 0.4, (string) $x);
        }
        // Horizontal lines + Y labels along the left edge
        for ($y = 0; $y <= $height; $y += self::STEP) {
            $this->setLineStyle($pdf, $y % self::MAJOR_STEP === 0);
            $pdf->Line(0, (float) $y, $width, (float) $y);
            $pdf->Text(0.4, (float) $y + 0.4, (string) $y);
        }
        // "x,y" pairs at every major intersection for easy mid-page reading
        for ($x = self::MAJOR_STEP; $x < $width; $x += self::MAJOR_STEP) {
            for ($y = self::MAJOR_STEP; $y < $height; $y += self::MAJOR_STEP) {
                $pdf->Text((float) $x + 0.6, (float) $y + 0.6, $x . ',' . $y);
            }
        }
    }

    private function setLineStyle(Fpdi $pdf, bool $major): void
    {
        $pdf->SetDrawColor(255, $major ? 0 : 170, $major ? 0 : 170);
        $pdf->SetLineWidth($major ? 0.25 : 0.1);
    }
}
