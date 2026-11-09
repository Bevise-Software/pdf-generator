<?php

declare(strict_types=1);

namespace PdfGenerator\Service;

/** Immutable outcome of a generation run, consumable by both CLI and web UI. */
final class GenerationResult
{
    public const STATUS_GENERATED = 'generated';
    public const STATUS_WARNING = 'generated with warning';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_ERROR = 'error';

    /**
     * @param string[] $validationErrors
     * @param array<int, array{0: string, 1: string, 2: string}> $reportRows [file, status, notes]
     * @param array{generated: int, skipped: int, warnings: int, errors: int} $stats
     */
    private function __construct(
        public readonly array $validationErrors,
        public readonly array $reportRows,
        public readonly array $stats,
    ) {
    }

    /** @param string[] $errors */
    public static function validationFailed(array $errors): self
    {
        return new self($errors, [], ['generated' => 0, 'skipped' => 0, 'warnings' => 0, 'errors' => 0]);
    }

    /**
     * @param array<int, array{0: string, 1: string, 2: string}> $reportRows
     * @param array{generated: int, skipped: int, warnings: int, errors: int} $stats
     */
    public static function completed(array $reportRows, array $stats): self
    {
        return new self([], $reportRows, $stats);
    }

    public function isSuccess(): bool
    {
        return $this->validationErrors === [] && $this->stats['errors'] === 0;
    }
}
