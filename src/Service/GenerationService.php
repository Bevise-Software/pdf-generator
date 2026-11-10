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
use PdfGenerator\Pdf\TestSheetRenderer;
use PdfGenerator\Pdf\TextFitter;
use PdfGenerator\Report\ReportWriter;
use PdfGenerator\Support\FilenameResolver;
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
     * Renders test.pdf: the template overlaid with the calibration grid
     * and/or field boxes with sample values, according to the flags.
     */
    public function renderTestSheet(bool $withGrid, bool $withFields): string
    {
        $this->logger->info(sprintf(
            'Rendering test sheet (grid: %s, fields: %s)',
            $withGrid ? 'yes' : 'no',
            $withFields ? 'yes' : 'no'
        ));

        try {
            $sampleRecord = $this->sampleRecord();
            $sampleValues = [];
            foreach ($this->config->fields as $key => $field) {
                $sampleValues[$key] = $field->resolveValue($sampleRecord);
            }

            $pdfFactory = new PdfFactory();
            (new TestSheetRenderer(
                $pdfFactory,
                new FontManager($this->config->fontCacheDir),
                new TextFitter(),
                new GridRenderer($pdfFactory)
            ))->render(
                $this->config->templatePath,
                $this->config->fields,
                $sampleValues,
                $withGrid,
                $withFields,
                $this->config->testOutputPath
            );
        } catch (Throwable $e) {
            $this->logger->error('Test sheet rendering failed: ' . $e->getMessage());
            throw $e;
        }

        $this->logger->info('Test sheet saved to ' . $this->config->testOutputPath);

        return $this->config->testOutputPath;
    }

    /** Realistic sample values (with Polish diacritics) for the test sheet. */
    private function sampleRecord(): array
    {
        $record = [
            'imie'     => 'Aleksandra',
            'nazwisko' => 'Wiśniewska-Kowalska',
            'numer'    => '999999',
        ];
        foreach ($this->requiredColumns() as $column) {
            if (!isset($record[$column])) {
                $record[$column] = 'Przykładowa wartość ąćęłńóśźż';
            }
        }

        return $record;
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

            // Resolve output filenames up front so the validator can check them
            $resolver = new FilenameResolver($config->filenameTemplate);
            foreach ($records as $i => $record) {
                $records[$i][RecipientValidator::FILENAME_KEY] = $resolver->resolve($record);
            }

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
            $filename = (string) $record[RecipientValidator::FILENAME_KEY];
            $outputPath = $config->outputDir . DIRECTORY_SEPARATOR . $filename;

            if (is_file($outputPath) && !$config->overwrite) {
                $stats['skipped']++;
                $reportRows[] = [$filename, GenerationResult::STATUS_SKIPPED, 'file already exists (overwrite = false)'];
                $this->logger->info("Skipped $filename: file already exists");
                continue;
            }

            try {
                $warnings = $generator->generate($this->mapFieldValues($record), $outputPath);
                $stats['generated']++;
                if ($warnings !== []) {
                    $stats['warnings']++;
                    $reportRows[] = [$filename, GenerationResult::STATUS_WARNING, implode(' | ', $warnings)];
                    $this->logger->warning("Generated $filename with warnings: " . implode(' | ', $warnings));
                } else {
                    $reportRows[] = [$filename, GenerationResult::STATUS_GENERATED, ''];
                    $this->logger->info("Generated $filename");
                }
            } catch (UnsupportedPdfException $e) {
                $this->logger->error('Generation aborted: unsupported PDF compression');
                throw $e; // template-level problem: abort the whole run
            } catch (Throwable $e) {
                $stats['errors']++;
                $reportRows[] = [$filename, GenerationResult::STATUS_ERROR, $e->getMessage()];
                $this->logger->error("Failed to generate $filename: " . $e->getMessage());
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
     * Required CSV columns = 'required_columns' from config.php + every column
     * referenced by field value templates and the filename template.
     *
     * @return string[]
     */
    private function requiredColumns(): array
    {
        $columns = array_merge(
            $this->config->requiredColumns,
            (new FilenameResolver($this->config->filenameTemplate))->referencedColumns()
        );
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
            '_row'     => 0,
            // Fixed name so the stress-test file is easy to find and never collides
            RecipientValidator::FILENAME_KEY => self::PREVIEW_TEST_FILE,
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
