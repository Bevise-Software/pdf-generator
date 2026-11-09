<?php

declare(strict_types=1);

namespace PdfGenerator\Pdf;

use RuntimeException;
use TCPDF_FONTS;

/**
 * Converts TTF fonts to the TCPDF format once and caches the result
 * both on disk (TCPDF skips conversion when the .php definition exists)
 * and in memory for the current run.
 */
final class FontManager
{
    /** @var array<string, array{name: string, file: string}> */
    private array $cache = [];

    public function __construct(private readonly string $cacheDir)
    {
    }

    /** @return array{name: string, file: string} font name + definition file for SetFont() */
    public function prepare(string $ttfPath): array
    {
        if (isset($this->cache[$ttfPath])) {
            return $this->cache[$ttfPath];
        }
        if (!is_file($ttfPath)) {
            throw new RuntimeException("Font file not found: $ttfPath");
        }
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0777, true)) {
            throw new RuntimeException("Cannot create font cache directory: {$this->cacheDir}");
        }

        $name = TCPDF_FONTS::addTTFfont($ttfPath, 'TrueTypeUnicode', '', 96, $this->cacheDir);
        if ($name === false) {
            throw new RuntimeException("Font conversion failed: $ttfPath");
        }

        return $this->cache[$ttfPath] = [
            'name' => $name,
            'file' => $this->cacheDir . $name . '.php',
        ];
    }
}
