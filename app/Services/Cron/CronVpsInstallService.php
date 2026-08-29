<?php

declare(strict_types=1);

namespace App\Services\Cron;

/**
 * Détecte / installe / retire la ligne crontab Athena sur le VPS (utilisateur PHP).
 * N’accepte aucune commande libre : uniquement le script scripts/install-system-cron.sh.
 */
final class CronVpsInstallService
{
    public const MARKER = '# athena-cron-run';

    /**
     * @return array{
     *   supported: bool,
     *   reason: string|null,
     *   installed: bool,
     *   crontab_readable: bool,
     *   line: string|null,
     *   crontab_preview: string|null,
     *   php_bin: string,
     *   script_path: string,
     *   install_script: string
     * }
     */
    public function status(): array
    {
        $root = base_path();
        $installScript = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'install-system-cron.sh';
        $scriptPath = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'cron-run.php';
        $phpBin = $this->phpCli();
        $logPath = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'cron.log';
        $expectedLine = CronSchedule::crontabLine($phpBin, $scriptPath, $logPath);

        $base = [
            'supported' => false,
            'reason' => null,
            'installed' => false,
            'crontab_readable' => false,
            'line' => null,
            'crontab_preview' => null,
            'php_bin' => $phpBin,
            'script_path' => $scriptPath,
            'install_script' => $installScript,
        ];

        if (PHP_OS_FAMILY === 'Windows') {
            $base['reason'] = 'Installation crontab indisponible sous Windows — utilisez install-system-cron.ps1 en PowerShell.';

            return $base;
        }
        if (!function_exists('exec') || !function_exists('shell_exec')) {
            $base['reason'] = 'Les fonctions exec/shell_exec sont désactivées sur ce PHP — installez en SSH.';

            return $base;
        }
        if (!$this->commandExists('crontab')) {
            $base['reason'] = 'La commande crontab est introuvable sur ce serveur.';

            return $base;
        }
        if (!is_file($installScript)) {
            $base['reason'] = 'Script d’installation introuvable.';

            return $base;
        }

        $base['supported'] = true;
        $list = $this->readCrontab();
        $base['crontab_readable'] = $list['ok'];
        if (!$list['ok']) {
            $base['reason'] = $list['error'] ?? 'Impossible de lire la crontab de l’utilisateur PHP.';
            // crontab -l exit 1 when empty is common — still treat as readable empty
            if (($list['empty'] ?? false) === true) {
                $base['crontab_readable'] = true;
                $base['reason'] = null;
                $base['crontab_preview'] = '';
            }

            return $base;
        }

        $raw = (string) ($list['raw'] ?? '');
        $base['crontab_preview'] = $this->previewCrontab($raw);
        $installed = str_contains($raw, self::MARKER) || str_contains($raw, 'scripts/cron-run.php');
        $base['installed'] = $installed;
        if ($installed) {
            foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (str_contains($line, 'scripts/cron-run.php') || str_contains($line, 'cron-run.php')) {
                    $base['line'] = $line;
                    break;
                }
            }
            if ($base['line'] === null) {
                $base['line'] = $expectedLine;
            }
        }

        return $base;
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function install(): array
    {
        $before = $this->status();
        if (empty($before['supported'])) {
            return [
                'ok' => false,
                'message' => (string) ($before['reason'] ?? 'Installation non supportée sur ce serveur.'),
                'status' => $before,
            ];
        }
        if (!empty($before['installed'])) {
            return [
                'ok' => true,
                'message' => 'Le passage automatique est déjà installé dans la crontab de cet utilisateur.',
                'status' => $before,
            ];
        }

        $script = (string) $before['install_script'];
        $php = (string) $before['php_bin'];
        $cmd = sprintf(
            'PHP_CLI=%s bash %s 2>&1',
            escapeshellarg($php),
            escapeshellarg($script)
        );
        $output = [];
        $code = 1;
        @exec($cmd, $output, $code);
        $after = $this->status();
        if ($code !== 0 || empty($after['installed'])) {
            $detail = trim(implode("\n", $output));

            return [
                'ok' => false,
                'message' => 'Échec de l’installation crontab'
                    . ($detail !== '' ? ' : ' . $detail : '. Vérifiez les droits de l’utilisateur PHP (crontab).'),
                'status' => $after,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Passage automatique installé (toutes les 5 minutes) dans la crontab VPS.',
            'status' => $after,
        ];
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function uninstall(): array
    {
        $before = $this->status();
        if (empty($before['supported'])) {
            return [
                'ok' => false,
                'message' => (string) ($before['reason'] ?? 'Désinstallation non supportée.'),
                'status' => $before,
            ];
        }
        if (empty($before['installed'])) {
            return [
                'ok' => true,
                'message' => 'Aucune ligne Athena n’était présente dans la crontab.',
                'status' => $before,
            ];
        }

        $list = $this->readCrontab();
        $raw = (string) ($list['raw'] ?? '');
        $kept = [];
        $skipNextJobLine = false;
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $trim = trim($line);
            if ($trim === self::MARKER) {
                $skipNextJobLine = true;
                continue;
            }
            if ($skipNextJobLine && $trim !== '' && !str_starts_with($trim, '#')) {
                $skipNextJobLine = false;
                continue;
            }
            $skipNextJobLine = false;
            if (str_contains($line, 'scripts/cron-run.php') || str_contains($line, 'cron-run.php')) {
                continue;
            }
            $kept[] = $line;
        }
        // Drop trailing empty noise
        while ($kept !== [] && trim((string) end($kept)) === '') {
            array_pop($kept);
        }
        $payload = $kept === [] ? '' : (implode("\n", $kept) . "\n");
        $tmp = tempnam(sys_get_temp_dir(), 'athena-cron-');
        if ($tmp === false) {
            return [
                'ok' => false,
                'message' => 'Impossible de préparer le fichier temporaire crontab.',
                'status' => $before,
            ];
        }
        file_put_contents($tmp, $payload);
        $output = [];
        $code = 1;
        if ($payload === '') {
            @exec('crontab -r 2>&1', $output, $code);
            // empty crontab removal may fail if already empty — treat as ok if no longer installed
            $code = 0;
        } else {
            @exec('crontab ' . escapeshellarg($tmp) . ' 2>&1', $output, $code);
        }
        @unlink($tmp);
        $after = $this->status();
        if ($code !== 0 || !empty($after['installed'])) {
            $detail = trim(implode("\n", $output));

            return [
                'ok' => false,
                'message' => 'Échec du retrait crontab'
                    . ($detail !== '' ? ' : ' . $detail : '.'),
                'status' => $after,
            ];
        }

        return [
            'ok' => true,
            'message' => 'Ligne Athena retirée de la crontab VPS.',
            'status' => $after,
        ];
    }

    /**
     * @return array{ok: bool, raw?: string, empty?: bool, error?: string}
     */
    private function readCrontab(): array
    {
        $output = [];
        $code = 1;
        @exec('crontab -l 2>&1', $output, $code);
        $raw = implode("\n", $output);
        if ($code !== 0) {
            $lower = strtolower($raw);
            if (str_contains($lower, 'no crontab') || trim($raw) === '') {
                return ['ok' => true, 'raw' => '', 'empty' => true];
            }

            return ['ok' => false, 'error' => trim($raw) !== '' ? trim($raw) : 'crontab -l a échoué'];
        }

        return ['ok' => true, 'raw' => $raw];
    }

    private function previewCrontab(string $raw): string
    {
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        $out = [];
        foreach ($lines as $line) {
            if (str_contains($line, 'cron-run.php') || str_contains($line, self::MARKER) || str_contains($line, 'athena')) {
                $out[] = $line;
            }
        }
        if ($out === []) {
            return '(aucune ligne Athena)';
        }

        return implode("\n", $out);
    }

    private function commandExists(string $cmd): bool
    {
        $output = [];
        $code = 1;
        @exec('command -v ' . escapeshellarg($cmd) . ' 2>/dev/null', $output, $code);

        return $code === 0 && trim((string) ($output[0] ?? '')) !== '';
    }

    private function phpCli(): string
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

        return 'php';
    }
}
