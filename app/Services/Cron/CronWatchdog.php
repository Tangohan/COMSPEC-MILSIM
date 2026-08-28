<?php

declare(strict_types=1);

namespace App\Services\Cron;

/**
 * Relance le passage des tâches si le planificateur serveur est absent ou en retard.
 * Un fichier tampon évite de relancer à chaque visite.
 */
final class CronWatchdog
{
    public static function maybeKick(string $requestPath = ''): void
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return;
        }
        $disabled = strtolower(trim((string) env('CRON_WATCHDOG', '1')));
        if (in_array($disabled, ['0', 'false', 'off', 'no'], true)) {
            return;
        }
        if (self::pathShouldSkip($requestPath)) {
            return;
        }

        $root = base_path();
        $logDir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($logDir) && !@mkdir($logDir, 0775, true) && !is_dir($logDir)) {
            return;
        }
        $stamp = $logDir . DIRECTORY_SEPARATOR . 'cron-watchdog.stamp';
        $lockFile = $logDir . DIRECTORY_SEPARATOR . 'cron-watchdog.lock';
        $minAge = CronSchedule::TICK_MINUTES * 60;
        if (is_file($stamp) && (time() - (int) filemtime($stamp)) < $minAge) {
            return;
        }

        $fp = @fopen($lockFile, 'c');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX | LOCK_NB)) {
                return;
            }
            if (is_file($stamp) && (time() - (int) filemtime($stamp)) < $minAge) {
                return;
            }
            @touch($stamp);
            self::spawn($root);
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    private static function pathShouldSkip(string $path): bool
    {
        $path = '/' . ltrim($path, '/');
        if ($path === '/cron/run' || str_starts_with($path, '/cron/')) {
            return true;
        }
        if (str_starts_with($path, '/assets/') || str_starts_with($path, '/uploads/')) {
            return true;
        }
        if (str_starts_with($path, '/api/atak/') || $path === '/api/health' || $path === '/api/atak/ping') {
            return true;
        }

        return false;
    }

    private static function spawn(string $root): void
    {
        $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'cron-run.php';
        if (!is_file($script)) {
            return;
        }
        $php = self::phpCli();
        $log = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'cron.log';

        if (PHP_OS_FAMILY === 'Windows') {
            if (!function_exists('popen')) {
                return;
            }
            $cmd = 'cd /d ' . escapeshellarg($root) . ' && start /B "" '
                . escapeshellarg($php) . ' ' . escapeshellarg($script);
            $h = @popen($cmd, 'r');
            if (is_resource($h)) {
                pclose($h);
            }

            return;
        }

        if (!function_exists('exec')) {
            return;
        }
        $cmd = sprintf(
            'cd %s && %s %s >> %s 2>&1 &',
            escapeshellarg($root),
            escapeshellarg($php),
            escapeshellarg($script),
            escapeshellarg($log)
        );
        @exec($cmd);
    }

    private static function phpCli(): string
    {
        $env = trim((string) env('PHP_CLI', ''));
        if ($env !== '' && is_executable($env)) {
            return $env;
        }
        $bin = (string) PHP_BINARY;
        $lower = strtolower($bin);
        if ($bin !== '' && !str_contains($lower, 'fpm') && !str_contains($lower, 'cgi')) {
            return $bin;
        }
        foreach (['/usr/bin/php', '/usr/local/bin/php', '/usr/bin/php8.3', '/usr/bin/php8.2'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }
}
