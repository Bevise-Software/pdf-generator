<?php

declare(strict_types=1);

namespace PdfGenerator;

use PdfGenerator\Config\AppConfig;
use PdfGenerator\Csv\RecipientReader;
use PdfGenerator\Csv\RecipientValidator;
use PdfGenerator\Exception\UnsupportedPdfException;
use PdfGenerator\Pdf\DocumentGenerator;
use PdfGenerator\Pdf\FontManager;
use PdfGenerator\Pdf\GridRenderer;
use PdfGenerator\Pdf\PdfFactory;
use PdfGenerator\Pdf\TextFitter;
use PdfGenerator\Report\ReportWriter;
use RuntimeException;
use Throwable;

/** CLI orchestrator: wires components together and runs the selected mode. */
final class Application
{
    private const PREVIEW_LIMIT = 3;

    public function __construct(private readonly array $rawConfig)
    {
    }

    public function run(array $argv): int
    {
        try {
            return $this->doRun($argv);
        } catch (UnsupportedPdfException) {
            $this->printCompressionHelp();
            return 1;
        } catch (Throwable $e) {
            fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
            return 1;
        }
    }

    private function doRun(array $argv): int
    {
        $preview = in_array('--preview', $argv, true);
        $grid = in_array('--grid', $argv, true);

        $config = AppConfig::fromArray($this->rawConfig);
        $pdfFactory = new PdfFactory();

        if ($grid) {
            (new GridRenderer($pdfFactory))->render($config->templatePath, $config->gridOutputPath);
            echo "Calibration grid saved to: {$config->gridOutputPath}\n";
            echo "Read field x/y values from it and update config.php.\n";
            return 0;
        }

        $fontManager = new FontManager($config->fontCacheDir);
        $generator = new DocumentGenerator(
            $pdfFactory,
            $fontManager,
            new TextFitter(),
            $config->templatePath,
            $config->fields
        );
        // Probe the template once: unsupported compression and page-range errors surface here
        $generator->assertTemplateSupported();

        [$headers, $records] = (new RecipientReader())->read($config->csvPath);
        $errors = (new RecipientValidator())->validate($headers, $records);
        if ($errors !== []) {
            fwrite(STDERR, "Validation failed - nothing was generated. Fix the errors below and rerun:\n\n");
            foreach ($errors as $error) {
                fwrite(STDERR, "  - $error\n");
            }
            return 1;
        }

        if ($preview) {
            $records = $this->buildPreviewRecords($records);
            $config = $config->withOverwrite(true); // preview always overwrites for quick iteration
            echo 'PREVIEW MODE: generating first ' . self::PREVIEW_LIMIT . " records + _test_preview.pdf\n\n";
        }

        $this->ensureOutputDir($config->outputDir);

        $report = [];
        $stats = ['generated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0];

        foreach ($records as $record) {
            $outputPath = $config->outputDir . DIRECTORY_SEPARATOR . $record['plik'];

            if (is_file($outputPath) && !$config->overwrite) {
                $stats['skipped']++;
                $report[] = [$record['plik'], 'skipped', 'file already exists (overwrite = false)'];
                echo "SKIPPED:   {$record['plik']} (file exists)\n";
                continue;
            }

            try {
                $warnings = $generator->generate($this->mapFieldValues($record), $outputPath);
                $stats['generated']++;
                if ($warnings !== []) {
                    $stats['warnings']++;
                    $report[] = [$record['plik'], 'generated with warning', implode(' | ', $warnings)];
                    echo "WARNING:   {$record['plik']} - " . implode(' | ', $warnings) . "\n";
                } else {
                    $report[] = [$record['plik'], 'generated', ''];
                    echo "GENERATED: {$record['plik']}\n";
                }
            } catch (UnsupportedPdfException $e) {
                throw $e; // template-level problem: abort the whole run
            } catch (Throwable $e) {
                $stats['errors']++;
                $report[] = [$record['plik'], 'error', $e->getMessage()];
                fwrite(STDERR, "ERROR:     {$record['plik']} - {$e->getMessage()}\n");
            }
        }

        (new ReportWriter())->write($config->reportPath, $report);
        $this->printSummary($stats, $config->reportPath);

        return $stats['errors'] > 0 ? 1 : 0;
    }

    /** Maps a CSV record to field values keyed like config['fields']. */
    private function mapFieldValues(array $record): array
    {
        return [
            'full_name' => trim($record['imie'] . ' ' . $record['nazwisko']),
            'number'    => $record['numer'],
        ];
    }

    /** First N records plus a stress-test record with a very long name and Polish diacritics. */
    private function buildPreviewRecords(array $records): array
    {
        $records = array_slice($records, 0, self::PREVIEW_LIMIT);
        $records[] = [
            'email'    => 'test@example.com',
            'imie'     => 'Aleksandra ąćęłńóśźż',
            'nazwisko' => 'Konstantynopolitańczykiewicz-Wiśniewska ĄĆĘŁŃÓŚŹŻ',
            'numer'    => '999999',
            'plik'     => '_test_preview.pdf',
            '_row'     => 0,
        ];
        return $records;
    }

    private function ensureOutputDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException("Cannot create output directory: $dir");
        }
    }

    private function printSummary(array $stats, string $reportPath): void
    {
        echo "\n==================== SUMMARY ====================\n";
        echo "Generated:      {$stats['generated']}\n";
        echo "Skipped:        {$stats['skipped']}\n";
        echo "With warnings:  {$stats['warnings']}\n";
        echo "Errors:         {$stats['errors']}\n";
        echo "Report saved:   $reportPath\n";
    }

    private function printCompressionHelp(): void
    {
        fwrite(STDERR, "\nERROR: The template PDF uses a compression technique not supported\n");
        fwrite(STDERR, "by the free FPDI parser (cross-reference / object streams, PDF 1.5+).\n\n");
        fwrite(STDERR, "FIX: convert the template with qpdf (https://qpdf.sourceforge.io):\n\n");
        fwrite(STDERR, "    qpdf --object-streams=disable --force-version=1.4 wzor.pdf wzor14.pdf\n\n");
        fwrite(STDERR, "then point config.php to the new file: 'template' => __DIR__ . '/wzor14.pdf'\n");
    }
}
