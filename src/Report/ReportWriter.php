<?php

declare(strict_types=1);

namespace PdfGenerator\Report;

use RuntimeException;

final class ReportWriter
{
    private const HEADERS = ['plik', 'status', 'uwagi'];

    /**
     * Writes the generation report as UTF-8 CSV with BOM (Excel-friendly).
     *
     * @param array<int, array{0: string, 1: string, 2: string}> $rows
     */
    public function write(string $path, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            throw new RuntimeException("Cannot write report file: $path");
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, self::HEADERS, ';', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';', '"', '\\');
            }
        } finally {
            fclose($handle);
        }
    }
}
