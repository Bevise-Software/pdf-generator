<?php

declare(strict_types=1);

namespace PdfGenerator\Logging;

/** Minimal logging contract (PSR-3 inspired, dependency-free). */
interface LoggerInterface
{
    public function info(string $message): void;

    public function warning(string $message): void;

    public function error(string $message): void;
}
