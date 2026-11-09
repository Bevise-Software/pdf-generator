<?php

declare(strict_types=1);

namespace PdfGenerator\Exception;

use RuntimeException;

/** Thrown when the template uses compression unsupported by the free FPDI parser. */
final class UnsupportedPdfException extends RuntimeException
{
    /** User-facing instructions for converting the template with qpdf. */
    public static function helpText(): string
    {
        return "The template PDF uses a compression technique not supported by the free FPDI parser "
            . "(cross-reference / object streams, PDF 1.5+).\n"
            . "FIX: convert the template with qpdf (https://qpdf.sourceforge.io):\n"
            . "    qpdf --object-streams=disable --force-version=1.4 wzor.pdf wzor14.pdf\n"
            . "then point config.php to the new file: 'template' => __DIR__ . '/wzor14.pdf'";
    }
}
