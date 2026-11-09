<?php

declare(strict_types=1);

namespace PdfGenerator\Pdf;

use PdfGenerator\Config\FieldConfig;
use PdfGenerator\Exception\UnsupportedPdfException;
use RuntimeException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfParser\StreamReader;

final class DocumentGenerator
{
    /** Template bytes kept in memory to avoid re-reading the file per record. */
    private readonly string $templateContent;

    private int $pageCount = 0;

    /** @param array<string, FieldConfig> $fields */
    public function __construct(
        private readonly PdfFactory $pdfFactory,
        private readonly FontManager $fontManager,
        private readonly TextFitter $textFitter,
        string $templatePath,
        private readonly array $fields,
    ) {
        if (!is_file($templatePath)) {
            throw new RuntimeException("Template PDF not found: $templatePath");
        }
        $content = file_get_contents($templatePath);
        if ($content === false) {
            throw new RuntimeException("Cannot read template PDF: $templatePath");
        }
        $this->templateContent = $content;
    }

    /**
     * Probes the template once: detects unsupported compression early and
     * validates that configured field pages exist. Returns the page count.
     */
    public function assertTemplateSupported(): int
    {
        try {
            $pdf = $this->pdfFactory->create();
            $this->pageCount = $pdf->setSourceFile(StreamReader::createByString($this->templateContent));
            for ($page = 1; $page <= $this->pageCount; $page++) {
                $pdf->importPage($page);
            }
        } catch (PdfParserException $e) {
            if (stripos($e->getMessage(), 'compression technique') !== false) {
                throw new UnsupportedPdfException($e->getMessage(), 0, $e);
            }
            throw $e;
        }

        foreach ($this->fields as $field) {
            if ($field->page < 1 || $field->page > $this->pageCount) {
                throw new RuntimeException(
                    "Field \"{$field->key}\": page {$field->page} is out of range "
                    . "(template has {$this->pageCount} pages)."
                );
            }
        }

        return $this->pageCount;
    }

    /**
     * Generates one personalized PDF.
     *
     * @param array<string, string> $values field key => text to render
     * @return string[] warnings (e.g. text wider than the field at minimum size)
     */
    public function generate(array $values, string $outputPath): array
    {
        $warnings = [];

        $pdf = $this->pdfFactory->create();
        $pageCount = $pdf->setSourceFile(StreamReader::createByString($this->templateContent));

        // Import every template page, preserving original size and orientation
        for ($page = 1; $page <= $pageCount; $page++) {
            $tpl = $pdf->importPage($page);
            $size = $pdf->getTemplateSize($tpl); // ['width', 'height', 'orientation'] in mm

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

            foreach ($this->fields as $field) {
                if ($field->page !== $page) {
                    continue;
                }
                $text = $values[$field->key] ?? '';
                if ($text === '') {
                    continue;
                }

                $font = $this->fontManager->prepare($field->fontPath);
                $pdf->SetTextColor(...$field->color);

                [, $overflow] = $this->textFitter->fit(
                    $pdf,
                    $text,
                    $font,
                    $field->size,
                    $field->minSize,
                    $field->width
                );
                if ($overflow) {
                    $warnings[] = "field \"{$field->key}\": text \"$text\" does not fit in "
                                . "{$field->width} mm even at the minimum size of {$field->minSize} pt";
                }

                // Cell height 0 = current font height; y is the top edge of the field
                $pdf->SetXY($field->x, $field->y);
                $pdf->Cell($field->width, 0, $text, 0, 0, $field->align);
            }
        }

        $pdf->Output($outputPath, 'F');
        return $warnings;
    }
}
