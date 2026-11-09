<?php

declare(strict_types=1);

namespace PdfGenerator\Support;

/**
 * Shared {column} placeholder template logic, used by field values
 * and the output filename template.
 */
final class TextTemplate
{
    private const PLACEHOLDER_PATTERN = '/\{([a-zA-Z0-9_]+)\}/';

    /**
     * Resolves a template against a CSV record:
     * '{imie} {nazwisko}' + ['imie' => 'Jan', 'nazwisko' => 'Kowalski'] => 'Jan Kowalski'.
     * Collapses repeated whitespace left behind by empty columns.
     */
    public static function resolve(string $template, array $record): string
    {
        $text = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            static fn(array $match) => trim((string) ($record[$match[1]] ?? '')),
            $template
        );

        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }

    /** @return string[] CSV column names referenced by the template */
    public static function referencedColumns(string $template): array
    {
        preg_match_all(self::PLACEHOLDER_PATTERN, $template, $matches);

        return $matches[1];
    }
}
