<?php

declare(strict_types=1);

namespace PdfGenerator\Pdf;

use setasign\Fpdi\Tcpdf\Fpdi;

/**
 * Fits text into a given width by shrinking the font size in 0.5 pt steps.
 * Glyph widths scale linearly with font size, so the target size is computed
 * analytically and snapped down to the 0.5 pt grid instead of looping.
 */
final class TextFitter
{
    private const STEP = 0.5;

    /**
     * Sets the font on $pdf at the fitted size and reports overflow.
     *
     * @param array{name: string, file: string} $font
     * @return array{0: float, 1: bool} [applied size, still too wide at min size]
     */
    public function fit(Fpdi $pdf, string $text, array $font, float $size, float $minSize, float $maxWidth): array
    {
        $pdf->SetFont($font['name'], '', $size, $font['file']);
        $width = $pdf->GetStringWidth($text);
        if ($width <= $maxWidth) {
            return [$size, false];
        }

        // Analytical target snapped down to a 0.5 pt step, clamped to the minimum
        $target = floor(($size * $maxWidth / $width) / self::STEP) * self::STEP;
        $target = max($target, $minSize);
        $pdf->SetFont($font['name'], '', $target, $font['file']);

        // Safety loop for rounding edge cases in glyph metrics
        while ($pdf->GetStringWidth($text) > $maxWidth && $target - self::STEP >= $minSize) {
            $target -= self::STEP;
            $pdf->SetFont($font['name'], '', $target, $font['file']);
        }

        return [$target, $pdf->GetStringWidth($text) > $maxWidth];
    }
}
