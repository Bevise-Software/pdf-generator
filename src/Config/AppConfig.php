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
        public readonly ?string $settingsPath = null,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        foreach (['template', 'csv', 'required_columns', 'filename', 'output_dir', 'report', 'grid_output', 'test_output', 'font_cache', 'log'] as $required) {
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

        $settingsPath = isset($raw['settings']) ? (string) $raw['settings'] : null;
        $settings = $settingsPath !== null ? (new SettingsStore($settingsPath))->load() : [];

        $filename = (string) $raw['filename'];
        if (is_string($settings['filename'] ?? null) && trim($settings['filename']) !== '') {
            $filename = $settings['filename'];
        }

        // Fields come from settings.json (font paths relative to its folder); config.php 'fields' is a fallback
        if (isset($settings['fields'])) {
            if (!is_array($settings['fields'])) {
                throw new InvalidArgumentException("Key \"fields\" in $settingsPath must be an object.");
            }
            $fieldsRaw = $settings['fields'];
            $baseDir = dirname((string) $settingsPath);
        } elseif (isset($raw['fields']) && is_array($raw['fields'])) {
            $fieldsRaw = $raw['fields'];
            $baseDir = null;
        } else {
            throw new InvalidArgumentException('No "fields" defined (expected in settings.json or config.php).');
        }

        $fields = [];
        foreach ($fieldsRaw as $key => $fieldRaw) {
            if (!is_array($fieldRaw)) {
                throw new InvalidArgumentException("Field \"$key\" must be an object.");
            }
            if ($baseDir !== null && isset($fieldRaw['font']) && !self::isAbsolutePath((string) $fieldRaw['font'])) {
                $fieldRaw['font'] = $baseDir . DIRECTORY_SEPARATOR . $fieldRaw['font'];
            }
            $fields[$key] = FieldConfig::fromArray((string) $key, $fieldRaw);
        }

        return new self(
            templatePath: (string) $raw['template'],
            csvPath: (string) $raw['csv'],
            csvSeparator: $separator,
            requiredColumns: array_values(array_map('strval', $raw['required_columns'])),
            filenameTemplate: self::filenameBase($filename) . '.pdf',
            outputDir: rtrim((string) $raw['output_dir'], '/\\'),
            reportPath: (string) $raw['report'],
            gridOutputPath: (string) $raw['grid_output'],
            testOutputPath: (string) $raw['test_output'],
            fontCacheDir: rtrim((string) $raw['font_cache'], '/\\') . DIRECTORY_SEPARATOR,
            logPath: (string) $raw['log'],
            overwrite: (bool) ($raw['overwrite'] ?? false),
            fields: $fields,
            settingsPath: $settingsPath,
        );
    }

    private static function isAbsolutePath(string $path): bool
    {
        return (bool) preg_match('#^([A-Za-z]:)?[\\\\/]#', $path);
    }

    /** Strips surrounding whitespace and a trailing .pdf: 'voucher-{KOD}.pdf' => 'voucher-{KOD}'. */
    public static function filenameBase(string $template): string
    {
        return (string) preg_replace('/\.pdf$/i', '', trim($template));
    }

    /** Filename template without the .pdf extension (as edited in the web UI). */
    public function filenameTemplateBase(): string
    {
        return self::filenameBase($this->filenameTemplate);
    }

    public function withFilenameTemplate(string $template): self
    {
        return new self(
            $this->templatePath,
            $this->csvPath,
            $this->csvSeparator,
            $this->requiredColumns,
            self::filenameBase($template) . '.pdf',
            $this->outputDir,
            $this->reportPath,
            $this->gridOutputPath,
            $this->testOutputPath,
            $this->fontCacheDir,
            $this->logPath,
            $this->overwrite,
            $this->fields,
            $this->settingsPath,
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
            $this->settingsPath,
        );
    }
}
