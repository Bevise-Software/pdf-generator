<?php

declare(strict_types=1);

namespace PdfGenerator\Support;

/**
 * Builds output filenames from the 'filename' config template,
 * e.g. '{imie}_{nazwisko}_{numer}.pdf' => 'Jan_Kowalski_001.pdf'.
 * Polish diacritics are transliterated and unsafe characters removed,
 * so the result is always filesystem- and e-mail-safe.
 */
final class FilenameResolver
{
    private const TRANSLITERATION = [
        'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
        'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z',
        'Ą' => 'A', 'Ć' => 'C', 'Ę' => 'E', 'Ł' => 'L', 'Ń' => 'N',
        'Ó' => 'O', 'Ś' => 'S', 'Ź' => 'Z', 'Ż' => 'Z',
    ];

    public function __construct(private readonly string $template)
    {
    }

    /** @return string[] CSV columns required by the filename template */
    public function referencedColumns(): array
    {
        return TextTemplate::referencedColumns($this->template);
    }

    /** Resolves and sanitizes the output filename for a CSV record. */
    public function resolve(array $record): string
    {
        $name = TextTemplate::resolve($this->template, $record);
        $name = strtr($name, self::TRANSLITERATION);
        $name = (string) preg_replace('/\s+/u', '_', $name);
        $name = (string) preg_replace('/[^A-Za-z0-9._-]/', '', $name); // drop remaining unsafe characters
        $name = (string) preg_replace('/_{2,}/', '_', $name);

        return $name;
    }
}
