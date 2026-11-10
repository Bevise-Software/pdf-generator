<?php

declare(strict_types=1);

namespace PdfGenerator\Csv;

use RuntimeException;

final class RecipientReader
{
    public function __construct(private readonly string $separator = ';')
    {
    }

    /**
     * Reads a UTF-8, semicolon-separated CSV. Strips the BOM from the first
     * header (Excel exports) and trims every value.
     *
     * @return array{0: string[], 1: array<int, array<string, string|int>>} [headers, records]
     */
    public function read(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("CSV file not found: $path");
        }
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open CSV file: $path");
        }

        try {
            $headers = fgetcsv($handle, 0, $this->separator, '"', '\\');
            if ($headers === false) {
                throw new RuntimeException("CSV file is empty: $path");
            }
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(static fn($h) => trim((string) $h), $headers);

            $records = [];
            $rowNumber = 1;
            while (($row = fgetcsv($handle, 0, $this->separator, '"', '\\')) !== false) {
                $rowNumber++;
                if ($row === [null] || (count($row) === 1 && trim((string) $row[0]) === '')) {
                    continue; // skip blank lines
                }
                $record = [];
                foreach ($headers as $i => $column) {
                    $record[$column] = trim((string) ($row[$i] ?? ''));
                }
                $record['_row'] = $rowNumber; // source line number for error messages
                $records[] = $record;
            }

            return [$headers, $records];
        } finally {
            fclose($handle);
        }
    }
}
