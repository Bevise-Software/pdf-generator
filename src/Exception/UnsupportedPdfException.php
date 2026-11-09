<?php

declare(strict_types=1);

namespace PdfGenerator\Exception;

use RuntimeException;

/** Thrown when the template uses compression unsupported by the free FPDI parser. */
final class UnsupportedPdfException extends RuntimeException
{
}
