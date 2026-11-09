<?php

declare(strict_types=1);

namespace PdfGenerator\Support;

use InvalidArgumentException;

final class Alignment
{
    private const MAP = [
        'left' => 'L', 'center' => 'C', 'right' => 'R',
        'l' => 'L', 'c' => 'C', 'r' => 'R',
        // Polish aliases kept for convenience in config files
        'lewo' => 'L', 'srodek' => 'C', 'środek' => 'C', 'prawo' => 'R',
    ];

    /** Normalizes an alignment keyword to a TCPDF code (L/C/R). */
    public static function normalize(string $align): string
    {
        $key = mb_strtolower(trim($align), 'UTF-8');
        if (!isset(self::MAP[$key])) {
            throw new InvalidArgumentException("Unknown alignment \"$align\" (allowed: left / center / right).");
        }
        return self::MAP[$key];
    }
}
