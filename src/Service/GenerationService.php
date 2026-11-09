<?php

declare(strict_types=1);

namespace PdfGenerator\Service;

use PdfGenerator\Config\AppConfig;
use PdfGenerator\Csv\RecipientReader;
use PdfGenerator\Csv\RecipientValidator;
use PdfGenerator\Exception\UnsupportedPdfException;
use PdfGenerator\Logging\LoggerInterface;
use PdfGenerator\Pdf\DocumentGenerator;
use PdfGenerator\Pdf\FontManager;
use PdfGenerator\Pdf\GridRenderer;
use PdfGenerator\Pdf\PdfFactory;
use PdfGenerator\Pdf\TextFitter;
use PdfGenerator\Report\ReportWriter;
use RuntimeException;
use Throwable;

/** Mode-agnostic generation workflow shared by the CLI (generuj.php) and the web UI (index.php). */
final class GenerationService
{
    public const PREVIEW_LIMIT = 3;
    public const PREVIEW_TEST_FILE = '_test_preview.pdf';

    public function __construct(
        private readonly AppConfig $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** Renders the calibration grid and returns its output path. */
    public function renderGrid(): string
    {
        $this->logger->info('Rendering calibration grid');
        try {
            (new GridRenderer(new PdfFactory()))->render($this->config->templatePath, $this->config->gridOutputPath);
        } catch (Throwable $e) {
            $this->logger->error('Grid rendering failed: ' . $e->getMessage());
            throw $e;
        }
        $this->logger->info('Calibration grid saved to ' . $this->config->gridOutputPath);

        return $this->config->gridOutputPath;
    }

    /**
     * Runs the full generation workflow: validate, generate, write report.
     *
     * @param bool $preview generate only the first records plus a long-name stress test
     * @param bool $forceOverwrite overwrite existing files regardless of config
     */
    public function generate(bool $preview, bool $forceOverwrite = false): GenerationResult
    {
        $config = ($preview || $forceOverwrite) ? $this->config->withOverwrite(true) : $this->config;
        $this->logger->info(sprintf(
            'Generation started (mode: %s, overwrite: %s)',
            $preview ? 'preview' : 'full',
            $config->overwrite ? 'yes' : 'no'
        ));

        try {
            $generator = new DocumentGenerator(
                new PdfFactory(),
                new FontManager($config->fontCacheDir),
                new TextFitter(),
                $config->templatePath,
                $config->fields
            );
            // Probe the template once: unsupported compression and page-range errors surface here
            $generator->assertTemplateSupported();

            [$headers, $records] = (new RecipientReader())->read($config->csvPath);
            $validationErrors = (new RecipientValidator($this->requiredColumns()))->validate($headers, $records);
            if ($validationErrors !== []) {
                foreach ($validationErrors as $error) {
                    $this->logger->warning('Validation: ' . $error);
                }
                $this->logger->warning('Generation aborted: validation failed, nothing was generated');

                return GenerationResult::validationFailed($validationErrors);
            }

            if ($preview) {
                $records = $this->buildPreviewRecords($records);
            }
            $this->ensureOutputDir($config->outputDir);
        } catch (Throwable $e) {
            $this->logger->error('Generation aborted: ' . $e->getMessage());
            throw $e;
        }

        $reportRows = [];
        $stats = ['generated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0];

        foreach ($records as $record) {
            $outputPath = $config->outputDir . DIRECTORY_SEPARATOR . $record['plik'];

            if (is_file($outputPath) && !$config->overwrite) {
                $stats['skipped']++;
                $reportRows[] = [$record['plik'], GenerationResult::STATUS_SKIPPED, 'file already exists (overwrite = false)'];
                $this->logger->info("Skipped {$record['plik']}: file already exists");
                continue;
            }

            try {
                $warnings = $generator->generate($this->mapFieldValues($record), $outputPath);
                $stats['generated']++;
                if ($warnings !== []) {
                    $stats['warnings']++;
                    $reportRows[] = [$record['plik'], GenerationResult::STATUS_WARNING, implode(' | ', $warnings)];
                    $this->logger->warning("Generated {$record['plik']} with warnings: " . implode(' | ', $warnings));
                } else {
                    $reportRows[] = [$record['plik'], GenerationResult::STATUS_GENERATED, ''];
                    $this->logger->info("Generated {$record['plik']}");
                }
            } catch (UnsupportedPdfException $e) {
                $this->logger->error('Generation aborted: unsupported PDF compression');
                throw $e; // template-level problem: abort the whole run
            } catch (Throwable $e) {
                $stats['errors']++;
                $reportRows[] = [$record['plik'], GenerationResult::STATUS_ERROR, $e->getMessage()];
                $this->logger->error("Failed to generate {$record['plik']}: " . $e->getMessage());
            }
        }

        (new ReportWriter())->write($config->reportPath, $reportRows);
        $this->logger->info(sprintf(
            'Generation finished: %d generated, %d skipped, %d with warnings, %d errors (report: %s)',
            $stats['generated'],
            $stats['skipped'],
            $stats['warnings'],
            $stats['errors'],
            $config->reportPath
        ));

        return GenerationResult::completed($reportRows, $stats);
    }

    /**
     * Required CSV columns = base columns (email, plik) + every column
     * referenced by field value templates in config.php.
     *
     * @return string[]
     */
    private function requiredColumns(): array
    {
        $columns = RecipientValidator::BASE_COLUMNS;
        foreach ($this->config->fields as $field) {
            $columns = array_merge($columns, $field->referencedColumns());
        }

        return array_values(array_unique($columns));
    }

    /**
     * Resolves every field's value template against a CSV record.
     * Adding a field in config.php requires no changes here.
     */
    private function mapFieldValues(array $record): array
    {
        $values = [];
        foreach ($this->config->fields as $key => $field) {
            $values[$key] = $field->resolveValue($record);
        }

        return $values;
    }

    /** First N records plus a stress-test record with a very long name and Polish diacritics. */
    private function buildPreviewRecords(array $records): array
    {
        $records = array_slice($records, 0, self::PREVIEW_LIMIT);

        $testRecord = [
            'email'    => 'test@example.com',
            'imie'     => 'Aleksandra ąćęłńóśźż',
            'nazwisko' => 'Konstantynopolitańczykiewicz-Wiśniewska ĄĆĘŁŃÓŚŹŻ',
            'numer'    => '999999',
            'plik'     => self::PREVIEW_TEST_FILE,
            '_row'     => 0,
        ];
        // Fill any extra template-referenced column with a diacritics sample
        foreach ($this->requiredColumns() as $column) {
            if (!isset($testRecord[$column])) {
                $testRecord[$column] = 'Przykładowa wartość ąćęłńóśźż ĄĆĘŁŃÓŚŹŻ';
            }
        }
        $records[] = $testRecord;

        return $records;
    }

    private function ensureOutputDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException("Cannot create output directory: $dir");
        }
    }
}
