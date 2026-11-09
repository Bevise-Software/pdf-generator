<?php

declare(strict_types=1);

namespace PdfGenerator\Logging;

use RuntimeException;

/**
 * Appends log lines to a file:
 *   [2026-11-09 12:00:00] [web] [INFO] Generated dokument_001.pdf
 * The channel distinguishes CLI runs from web UI runs in a shared log.
 */
final class FileLogger implements LoggerInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $channel = 'app',
    ) {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true)) {
            throw new RuntimeException("Cannot create log directory: $dir");
        }
    }

    public function info(string $message): void
    {
        $this->write('INFO', $message);
    }

    public function warning(string $message): void
    {
        $this->write('WARNING', $message);
    }

    public function error(string $message): void
    {
        $this->write('ERROR', $message);
    }

    private function write(string $level, string $message): void
    {
        $line = sprintf("[%s] [%s] [%s] %s\n", date('Y-m-d H:i:s'), $this->channel, $level, $message);
        // LOCK_EX guards against interleaved writes from concurrent requests
        if (file_put_contents($this->path, $line, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException("Cannot write to log file: {$this->path}");
        }
    }
}
