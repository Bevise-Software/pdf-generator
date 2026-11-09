<?php

declare(strict_types=1);

namespace PdfGenerator\Config;

use InvalidArgumentException;

/** Immutable application configuration, validated once at startup. */
final class AppConfig
{
    /** @param array<string, FieldConfig> $fields */
    public function __construct(
        public readonly string $templatePath,
        public readonly string $csvPath,
        public readonly string $outputDir,
        public readonly string $reportPath,
        public readonly string $gridOutputPath,
        public readonly string $fontCacheDir,
        public readonly bool $overwrite,
        public readonly array $fields,
    ) {
    }

    public static function fromArray(array $raw): self
    {
        foreach (['template', 'csv', 'output_dir', 'report', 'grid_output', 'font_cache', 'fields'] as $required) {
            if (!array_key_exists($required, $raw)) {
                throw new InvalidArgumentException("Missing config key \"$required\".");
            }
        }

        $fields = [];
        foreach ($raw['fields'] as $key => $fieldRaw) {
            $fields[$key] = FieldConfig::fromArray((string) $key, $fieldRaw);
        }

        return new self(
            templatePath: (string) $raw['template'],
            csvPath: (string) $raw['csv'],
            outputDir: rtrim((string) $raw['output_dir'], '/\\'),
            reportPath: (string) $raw['report'],
            gridOutputPath: (string) $raw['grid_output'],
            fontCacheDir: rtrim((string) $raw['font_cache'], '/\\') . DIRECTORY_SEPARATOR,
            overwrite: (bool) ($raw['overwrite'] ?? false),
            fields: $fields,
        );
    }

    public function withOverwrite(bool $overwrite): self
    {
        return new self(
            $this->templatePath,
            $this->csvPath,
            $this->outputDir,
            $this->reportPath,
            $this->gridOutputPath,
            $this->fontCacheDir,
            $overwrite,
            $this->fields,
        );
    }
}
