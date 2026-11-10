<?php

declare(strict_types=1);

namespace PdfGenerator\Csv;

final class RecipientValidator
{
    /** Key under which the resolved output filename is stored in each record. */
    public const FILENAME_KEY = '_filename';

    private const FILENAME_PATTERN = '/^[A-Za-z0-9._-]+$/';

    /** @param string[] $requiredColumns full list, including template-referenced columns */
    public function __construct(private readonly array $requiredColumns)
    {
    }

    /**
     * Validates headers and records before any generation happens.
     *
     * @return string[] error messages (empty array = valid)
     */
    public function validate(array $headers, array $records): array
    {
        $errors = [];

        $missing = array_diff($this->requiredColumns, $headers);
        if ($missing !== []) {
            // Without required columns further validation is pointless
            return ['Missing required CSV columns: ' . implode(', ', $missing)];
        }
        if ($records === []) {
            return ['CSV file contains no data rows.'];
        }

        $usedFilenames = [];
        foreach ($records as $record) {
            $row = $record['_row'];

            foreach ($this->requiredColumns as $column) {
                if (($record[$column] ?? '') === '') {
                    $errors[] = "Row $row: empty value in column \"$column\".";
                }
            }

            $filename = (string) ($record[self::FILENAME_KEY] ?? '');
            if ($filename === '' || preg_replace('/\.pdf$/i', '', $filename) === '') {
                $errors[] = "Row $row: output filename resolves to an empty name "
                          . "(check the 'filename' template in config.php).";
                continue;
            }
            if (!preg_match(self::FILENAME_PATTERN, $filename)) {
                $errors[] = "Row $row: filename \"$filename\" contains forbidden characters "
                          . '(allowed: A-Z a-z 0-9 . _ -).';
            } elseif (!preg_match('/\.pdf$/i', $filename)) {
                $errors[] = "Row $row: filename \"$filename\" must end with .pdf "
                          . "(check the 'filename' template in config.php).";
            }

            // Case-insensitive duplicate check (Windows filesystems)
            $key = mb_strtolower($filename, 'UTF-8');
            if (isset($usedFilenames[$key])) {
                $errors[] = "Row $row: duplicate filename \"$filename\" (first used in row {$usedFilenames[$key]}).";
            } else {
                $usedFilenames[$key] = $row;
            }
        }

        return $errors;
    }
}
