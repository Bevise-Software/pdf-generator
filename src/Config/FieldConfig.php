<?php

declare(strict_types=1);

namespace PdfGenerator\Config;

use InvalidArgumentException;
use PdfGenerator\Support\Alignment;
use PdfGenerator\Support\Color;
use PdfGenerator\Support\TextTemplate;

/** Immutable, pre-validated definition of a single text field. */
final class FieldConfig
{
    /** @param array{0:int,1:int,2:int} $color */
    public function __construct(
        public readonly string $key,
        public readonly string $value,     // template, e.g. '{imie} {nazwisko}'
        public readonly int $page,
        public readonly float $x,
        public readonly float $y,
        public readonly float $width,
        public readonly string $align,     // TCPDF code: L/C/R
        public readonly string $fontPath,
        public readonly float $size,
        public readonly float $minSize,
        public readonly array $color,
    ) {
    }

    public static function fromArray(string $key, array $raw): self
    {
        foreach (['value', 'page', 'x', 'y', 'width', 'align', 'font', 'size', 'min_size', 'color'] as $required) {
            if (!array_key_exists($required, $raw)) {
                throw new InvalidArgumentException("Field \"$key\": missing config key \"$required\".");
            }
        }
        if ((float) $raw['min_size'] > (float) $raw['size']) {
            throw new InvalidArgumentException("Field \"$key\": min_size must not exceed size.");
        }

        return new self(
            key: $key,
            value: (string) $raw['value'],
            page: (int) $raw['page'],
            x: (float) $raw['x'],
            y: (float) $raw['y'],
            width: (float) $raw['width'],
            align: Alignment::normalize((string) $raw['align']),
            fontPath: (string) $raw['font'],
            size: (float) $raw['size'],
            minSize: (float) $raw['min_size'],
            color: Color::parse($raw['color']),
        );
    }

    /**
     * Resolves the value template against a CSV record:
     * '{imie} {nazwisko}' + ['imie' => 'Jan', 'nazwisko' => 'Kowalski'] => 'Jan Kowalski'.
     */
    public function resolveValue(array $record): string
    {
        return TextTemplate::resolve($this->value, $record);
    }

    /** @return string[] CSV column names referenced by the value template */
    public function referencedColumns(): array
    {
        return TextTemplate::referencedColumns($this->value);
    }
}
