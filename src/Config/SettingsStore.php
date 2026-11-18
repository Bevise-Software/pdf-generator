<?php

declare(strict_types=1);

namespace PdfGenerator\Config;

use RuntimeException;

/**
 * User settings edited in the web UI and persisted to a JSON file
 * (e.g. settings.json). Values here override config.php defaults.
 */
final class SettingsStore
{
    public function __construct(private readonly string $path)
    {
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        if (!is_file($this->path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($this->path), true);

        return is_array($data) ? $data : [];
    }

    public function set(string $key, mixed $value): void
    {
        $data = $this->load();
        $data[$key] = $value;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false || file_put_contents($this->path, $json . "\n", LOCK_EX) === false) {
            throw new RuntimeException("Cannot save settings file: {$this->path}");
        }
    }
}
