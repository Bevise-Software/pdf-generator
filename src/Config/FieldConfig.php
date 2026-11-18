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
        public readonly string $transform = self::TRANSFORM_NONE,
    ) {
    }

    public const TRANSFORM_NONE = 'none';
    public const TRANSFORM_UPPERCASE = 'uppercase';
    public const TRANSFORM_LOWERCASE = 'lowercase';
    private const TRANSFORMS = [self::TRANSFORM_NONE, self::TRANSFORM_UPPERCASE, self::TRANSFORM_LOWERCASE];

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
        $transform = strtolower(trim((string) ($raw['transform'] ?? self::TRANSFORM_NONE)));
        if (!in_array($transform, self::TRANSFORMS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Field "%s": unknown transform "%s" (allowed: %s).',
                $key,
                $transform,
                implode(', ', self::TRANSFORMS)
            ));
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
            transform: $transform,
        );
    }

    /**
     * Resolves the value template against a CSV record and applies the transform:
     * '{imie} {nazwisko}' + ['imie' => 'Jan', 'nazwisko' => 'Kowalski'] => 'Jan Kowalski'.
     */
    public function resolveValue(array $record): string
    {
        $text = TextTemplate::resolve($this->value, $record);

        return match ($this->transform) {
            self::TRANSFORM_UPPERCASE => mb_strtoupper($text, 'UTF-8'),
            self::TRANSFORM_LOWERCASE => mb_strtolower($text, 'UTF-8'),
            default => $text,
        };
    }

    /** @return string[] CSV column names referenced by the value template */
    public function referencedColumns(): array
    {
        return TextTemplate::referencedColumns($this->value);
    }
}
