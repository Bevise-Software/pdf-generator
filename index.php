<?php

declare(strict_types=1);

/**
 * Web UI for the PDF generator.
 * Generation never runs on page load - only on explicit button click
 * (POST request protected by a CSRF token).
 */

session_start();

require __DIR__ . '/vendor/autoload.php';

use PdfGenerator\Config\AppConfig;
use PdfGenerator\Csv\RecipientReader;
use PdfGenerator\Exception\UnsupportedPdfException;
use PdfGenerator\Logging\FileLogger;
use PdfGenerator\Service\GenerationResult;
use PdfGenerator\Service\GenerationService;

/** HTML-escapes a value for safe output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Returns the last $limit lines of the log file (newest last). */
function logTail(string $path, int $limit = 25): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_slice($lines, -$limit);
}

/** Maps an internal report status to a CSS badge class. */
function statusClass(string $status): string
{
    return match ($status) {
        GenerationResult::STATUS_GENERATED => 'ok',
        GenerationResult::STATUS_WARNING => 'warn',
        GenerationResult::STATUS_SKIPPED => 'skip',
        default => 'err',
    };
}

/** Maps an internal report status to a user-facing (Polish) label. */
function statusLabel(string $status): string
{
    return match ($status) {
        GenerationResult::STATUS_GENERATED => 'wygenerowano',
        GenerationResult::STATUS_WARNING => 'wygenerowano z ostrzeżeniem',
        GenerationResult::STATUS_SKIPPED => 'pominięto',
        default => 'błąd',
    };
}

// --- CSRF token -------------------------------------------------------------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// --- Configuration ----------------------------------------------------------
$config = null;
$fatalError = null;
try {
    $config = AppConfig::fromArray(require __DIR__ . '/config.php');
} catch (Throwable $e) {
    $fatalError = 'Błąd konfiguracji: ' . $e->getMessage();
}

// --- Action handling (POST only) --------------------------------------------
$result = null;
$gridPath = null;
$action = null;

if ($config !== null && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if (!hash_equals($csrfToken, (string) ($_POST['csrf_token'] ?? ''))) {
        $fatalError = 'Nieprawidłowy token bezpieczeństwa. Odśwież stronę i spróbuj ponownie.';
    } else {
        try {
            $service = new GenerationService($config, new FileLogger($config->logPath, 'web'));
            switch ($action) {
                case 'grid':
                    $gridPath = $service->renderGrid();
                    break;
                case 'preview':
                    $result = $service->generate(preview: true);
                    break;
                case 'generate':
                    $result = $service->generate(preview: false, forceOverwrite: isset($_POST['overwrite']));
                    break;
                default:
                    $fatalError = 'Nieznana akcja.';
            }
        } catch (UnsupportedPdfException) {
            $fatalError = UnsupportedPdfException::helpText();
        } catch (Throwable $e) {
            $fatalError = $e->getMessage();
        }
    }
}

// --- Status panel data (read-only checks, no generation) ---------------------
$templateOk = $config !== null && is_file($config->templatePath);
$csvOk = $config !== null && is_file($config->csvPath);
$fontsOk = $config !== null;
$recordCount = null;

if ($config !== null) {
    foreach ($config->fields as $field) {
        if (!is_file($field->fontPath)) {
            $fontsOk = false;
            break;
        }
    }
    if ($csvOk) {
        try {
            [, $records] = (new RecipientReader())->read($config->csvPath);
            $recordCount = count($records);
        } catch (Throwable) {
            $recordCount = null;
        }
    }
}

$ready = $templateOk && $csvOk && $fontsOk;
$logLines = $config !== null ? logTail($config->logPath) : [];
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Generator PDF</title>
<style>
    :root { --ok: #1a7f37; --warn: #9a6700; --err: #cf222e; --skip: #57606a; --accent: #2b5fb8; }
    * { box-sizing: border-box; }
    body { font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f0f3f8; margin: 0; color: #1f2328; }
    .wrap { max-width: 920px; margin: 0 auto; padding: 32px 16px 64px; }
    h1 { font-size: 26px; margin: 0 0 4px; }
    .sub { color: #57606a; margin: 0 0 24px; }
    .card { background: #fff; border: 1px solid #d8dee4; border-radius: 10px; padding: 20px 24px; margin-bottom: 20px; }
    .card h2 { font-size: 16px; margin: 0 0 14px; }
    .checks { display: flex; flex-wrap: wrap; gap: 10px; }
    .check { padding: 6px 12px; border-radius: 999px; font-size: 13px; font-weight: 600; }
    .check.ok { background: #dafbe1; color: var(--ok); }
    .check.bad { background: #ffebe9; color: var(--err); }
    .actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    button { border: 0; border-radius: 8px; padding: 10px 18px; font-size: 14px; font-weight: 600; cursor: pointer; }
    button:disabled { opacity: .45; cursor: not-allowed; }
    .btn-primary { background: var(--accent); color: #fff; }
    .btn-secondary { background: #e7ecf3; color: #1f2328; }
    .overwrite { font-size: 13px; color: #57606a; display: flex; align-items: center; gap: 6px; }
    .alert { border-radius: 8px; padding: 14px 18px; margin-bottom: 20px; white-space: pre-wrap; font-size: 14px; }
    .alert.error { background: #ffebe9; border: 1px solid #ffb3ad; color: var(--err); }
    .alert.success { background: #dafbe1; border: 1px solid #a5e8b5; color: var(--ok); }
    .stats { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 14px; }
    .stat { background: #f6f8fa; border: 1px solid #d8dee4; border-radius: 8px; padding: 10px 18px; text-align: center; min-width: 110px; }
    .stat b { display: block; font-size: 22px; }
    .stat span { font-size: 12px; color: #57606a; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #eef1f4; vertical-align: top; }
    th { color: #57606a; font-weight: 600; }
    .badge { padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
    .badge.ok { background: #dafbe1; color: var(--ok); }
    .badge.warn { background: #fff8c5; color: var(--warn); }
    .badge.skip { background: #eef1f4; color: var(--skip); }
    .badge.err { background: #ffebe9; color: var(--err); }
    ul.errors { margin: 0; padding-left: 20px; color: var(--err); font-size: 14px; }
    pre.log { background: #0d1117; color: #c9d1d9; border-radius: 8px; padding: 14px 16px; font-size: 12px; line-height: 1.6; overflow-x: auto; margin: 0; max-height: 320px; overflow-y: auto; }
    .muted { color: #57606a; font-size: 13px; }
</style>
</head>
<body>
<div class="wrap">
    <h1>Generator spersonalizowanych PDF</h1>
    <p class="sub">Nakłada imię i nazwisko oraz numer na wzór PDF dla każdego odbiorcy z pliku CSV.</p>

    <?php if ($fatalError !== null): ?>
        <div class="alert error"><?= e($fatalError) ?></div>
    <?php endif; ?>

    <?php if ($gridPath !== null): ?>
        <div class="alert success">Siatka kalibracyjna zapisana: <?= e($gridPath) ?><br>Odczytaj z niej współrzędne x/y pól i wpisz je w config.php.</div>
    <?php endif; ?>

    <div class="card">
        <h2>Stan projektu</h2>
        <div class="checks">
            <span class="check <?= $templateOk ? 'ok' : 'bad' ?>">wzor.pdf: <?= $templateOk ? 'OK' : 'BRAK' ?></span>
            <span class="check <?= $csvOk ? 'ok' : 'bad' ?>">odbiorcy.csv: <?= $csvOk ? 'OK' : 'BRAK' ?></span>
            <span class="check <?= $fontsOk ? 'ok' : 'bad' ?>">fonty: <?= $fontsOk ? 'OK' : 'BRAK' ?></span>
            <?php if ($recordCount !== null): ?>
                <span class="check ok">rekordów w CSV: <?= $recordCount ?></span>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2>Akcje</h2>
        <form method="post" class="actions">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <button class="btn-secondary" type="submit" name="action" value="grid" <?= $templateOk ? '' : 'disabled' ?>>
                Siatka kalibracyjna
            </button>
            <button class="btn-secondary" type="submit" name="action" value="preview" <?= $ready ? '' : 'disabled' ?>>
                Podgląd (<?= GenerationService::PREVIEW_LIMIT ?> rekordy + test)
            </button>
            <button class="btn-primary" type="submit" name="action" value="generate" <?= $ready ? '' : 'disabled' ?>>
                Generuj wszystkie PDF-y
            </button>
            <label class="overwrite">
                <input type="checkbox" name="overwrite" value="1"> nadpisz istniejące pliki
            </label>
        </form>
        <?php if (!$ready): ?>
            <p class="muted">Uzupełnij brakujące pliki (patrz „Stan projektu”), aby odblokować generowanie.</p>
        <?php endif; ?>
    </div>

    <?php if ($result !== null): ?>
        <div class="card">
            <h2>Wynik<?= $action === 'preview' ? ' (podgląd)' : '' ?></h2>

            <?php if ($result->validationErrors !== []): ?>
                <div class="alert error">Walidacja nie powiodła się — nic nie wygenerowano. Popraw błędy i spróbuj ponownie.</div>
                <ul class="errors">
                    <?php foreach ($result->validationErrors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="stats">
                    <div class="stat"><b><?= $result->stats['generated'] ?></b><span>wygenerowano</span></div>
                    <div class="stat"><b><?= $result->stats['skipped'] ?></b><span>pominięto</span></div>
                    <div class="stat"><b><?= $result->stats['warnings'] ?></b><span>ostrzeżenia</span></div>
                    <div class="stat"><b><?= $result->stats['errors'] ?></b><span>błędy</span></div>
                </div>
                <table>
                    <tr><th>Plik</th><th>Status</th><th>Uwagi</th></tr>
                    <?php foreach ($result->reportRows as [$file, $status, $notes]): ?>
                        <tr>
                            <td><?= e($file) ?></td>
                            <td><span class="badge <?= statusClass($status) ?>"><?= e(statusLabel($status)) ?></span></td>
                            <td><?= e($notes) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <p class="muted">Raport CSV: <?= e($config->reportPath) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Log (ostatnie wpisy)</h2>
        <?php if ($logLines === []): ?>
            <p class="muted">Brak wpisów w logu.</p>
        <?php else: ?>
            <pre class="log"><?= e(implode("\n", $logLines)) ?></pre>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
