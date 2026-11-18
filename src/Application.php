<?php

declare(strict_types=1);

namespace PdfGenerator;

use PdfGenerator\Config\AppConfig;
use PdfGenerator\Exception\UnsupportedPdfException;
use PdfGenerator\Logging\FileLogger;
use PdfGenerator\Service\GenerationResult;
use PdfGenerator\Service\GenerationService;
use Throwable;

/** CLI entry point: thin console wrapper around GenerationService. */
final class Application
{
    public function __construct(private readonly array $rawConfig)
    {
    }

    public function run(array $argv): int
    {
        try {
            $config = AppConfig::fromArray($this->rawConfig);
            $service = new GenerationService($config, new FileLogger($config->logPath, 'cli'));

            if (in_array('--grid', $argv, true)) {
                $path = $service->renderGrid();
                echo "Calibration grid saved to: $path\n";
                echo "Read field x/y values from it and update settings.json.\n";

                return 0;
            }

            $preview = in_array('--preview', $argv, true);
            if ($preview) {
                echo 'PREVIEW MODE: generating first ' . GenerationService::PREVIEW_LIMIT
                    . ' records + ' . GenerationService::PREVIEW_TEST_FILE . "\n\n";
            }

            $result = $service->generate($preview);

            if ($result->validationErrors !== []) {
                fwrite(STDERR, "Validation failed - nothing was generated. Fix the errors below and rerun:\n\n");
                foreach ($result->validationErrors as $error) {
                    fwrite(STDERR, "  - $error\n");
                }

                return 1;
            }

            foreach ($result->reportRows as [$file, $status, $notes]) {
                $line = strtoupper($status) . ': ' . $file . ($notes !== '' ? " - $notes" : '') . "\n";
                fwrite($status === GenerationResult::STATUS_ERROR ? STDERR : STDOUT, $line);
            }

            $this->printSummary($result->stats, $config->reportPath);

            return $result->isSuccess() ? 0 : 1;
        } catch (UnsupportedPdfException) {
            fwrite(STDERR, "\nERROR: " . UnsupportedPdfException::helpText() . "\n");

            return 1;
        } catch (Throwable $e) {
            fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");

            return 1;
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
}
