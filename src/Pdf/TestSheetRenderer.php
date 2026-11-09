<?php

declare(strict_types=1);

namespace PdfGenerator\Pdf;

use PdfGenerator\Config\FieldConfig;
use RuntimeException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Renders a single calibration sheet (test.pdf): the template overlaid with
 * the 10 mm grid and/or every configured field - drawn as a dashed box with
 * its key, coordinates and a sample value rendered exactly like in real
 * generation. Which overlays are included is controlled by flags.
 */
final class TestSheetRenderer
{
    private const BOX_COLOR = [0, 90, 220];

    public function __construct(
        private readonly PdfFactory $pdfFactory,
        private readonly FontManager $fontManager,
        private readonly TextFitter $textFitter,
        private readonly GridRenderer $gridRenderer,
    ) {
    }

    /**
     * @param array<string, FieldConfig> $fields
     * @param array<string, string> $sampleValues field key => sample text
     */
    public function render(
        string $templatePath,
        array $fields,
        array $sampleValues,
        bool $withGrid,
        bool $withFields,
        string $outputPath,
    ): void {
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

            if ($withGrid) {
                $this->gridRenderer->drawGrid($pdf, $size['width'], $size['height']);
            }
            if ($withFields) {
                foreach ($fields as $key => $field) {
                    if ($field->page === $page) {
                        $this->drawField($pdf, $field, $sampleValues[$key] ?? '');
                    }
                }
            }
        }

        $pdf->Output($outputPath, 'F');
    }

    private function drawField(Fpdi $pdf, FieldConfig $field, string $text): void
    {
        // Dashed outline of the text box (height = starting font size)
        $boxHeight = $field->size * 25.4 / 72; // pt -> mm
        $pdf->SetLineStyle(['width' => 0.3, 'dash' => '2,1.5', 'color' => self::BOX_COLOR]);
        $pdf->Rect($field->x, $field->y, $field->width, $boxHeight);

        // Label with the field key and its config values, just above the box
        $pdf->SetFont('helvetica', '', 6);
        $pdf->SetTextColor(...self::BOX_COLOR);
        $pdf->Text(
            $field->x + 1,
            max(0.0, $field->y - 3),
            sprintf('%s  x=%s  y=%s  w=%s', $field->key, $field->x, $field->y, $field->width)
        );

        // Sample value rendered exactly like in real generation
        if ($text === '') {
            return;
        }
        $font = $this->fontManager->prepare($field->fontPath);
        $pdf->SetTextColor(...$field->color);
        $this->textFitter->fit($pdf, $text, $font, $field->size, $field->minSize, $field->width);
        $pdf->SetXY($field->x, $field->y);
        $pdf->Cell($field->width, 0, $text, 0, 0, $field->align);
    }
}
