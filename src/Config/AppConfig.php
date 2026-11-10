<?php

declare(strict_types=1);

namespace PdfGenerator\Config;

use InvalidArgumentException;

/** Immutable application configuration, validated once at startup. */
final class AppConfig
{
    /**
     * @param string[] $requiredColumns
     * @param array<string, FieldConfig> $fields
     */
    public function __construct(
        public readonly string $templatePath,
        public readonly string $csvPath,
        public readonly string $csvSeparator,
        public readonly array $requiredColumns,
        public readonly string $filenameTemplate,
        public readonly string $outputDir,
        public readonly string $reportPath,
        public readonly string $gridOutputPath,
        public readonly string $testOutputPath,
        public readonly string $fontCacheDir,
        public readonly string $logPath,
        public readonly bool $overwrite,
        public readonly array $fields,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        foreach (['template', 'csv', 'required_columns', 'filename', 'output_dir', 'report', 'grid_output', 'test_output', 'font_cache', 'log', 'fields'] as $required) {
            if (!array_key_exists($required, $raw)) {
                throw new InvalidArgumentException("Missing config key \"$required\".");
            }
        }
        if (!is_array($raw['required_columns'])) {
            throw new InvalidArgumentException('Config key "required_columns" must be an array of column names.');
        }
        $separator = (string) ($raw['csv_separator'] ?? ';');
        if (strlen($separator) !== 1) {
            throw new InvalidArgumentException('Config key "csv_separator" must be a single character, e.g. \';\' or \',\'.');
        }

        $fields = [];
        foreach ($raw['fields'] as $key => $fieldRaw) {
            $fields[$key] = FieldConfig::fromArray((string) $key, $fieldRaw);
        }

        return new self(
            templatePath: (string) $raw['template'],
            csvPath: (string) $raw['csv'],
            csvSeparator: $separator,
            requiredColumns: array_values(array_map('strval', $raw['required_columns'])),
            filenameTemplate: (string) $raw['filename'],
            outputDir: rtrim((string) $raw['output_dir'], '/\\'),
            reportPath: (string) $raw['report'],
            gridOutputPath: (string) $raw['grid_output'],
            testOutputPath: (string) $raw['test_output'],
            fontCacheDir: rtrim((string) $raw['font_cache'], '/\\') . DIRECTORY_SEPARATOR,
            logPath: (string) $raw['log'],
            overwrite: (bool) ($raw['overwrite'] ?? false),
            fields: $fields,
        );
    }

    public function withOverwrite(bool $overwrite): self
    {
        return new self(
            $this->templatePath,
            $this->csvPath,
            $this->csvSeparator,
            $this->requiredColumns,
            $this->filenameTemplate,
            $this->outputDir,
            $this->reportPath,
            $this->gridOutputPath,
            $this->testOutputPath,
            $this->fontCacheDir,
            $this->logPath,
            $overwrite,
            $this->fields,
        );
    }
}
