<?php

declare(strict_types=1);

namespace PdfGenerator\Support;

use InvalidArgumentException;

final class Color
{
    /**
     * Parses '#RRGGBB' or [R, G, B] into an [R, G, B] int array.
     *
     * @return array{0:int,1:int,2:int}
     */
    public static function parse(array|string $color): array
    {
        if (is_array($color)) {
            if (count($color) !== 3) {
                throw new InvalidArgumentException('Array color must have exactly 3 components [R, G, B].');
            }
            return array_map('intval', array_values($color));
        }

        $hex = ltrim(trim($color), '#');
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            throw new InvalidArgumentException("Invalid HEX color \"$color\" (expected #RRGGBB).");
        }

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
