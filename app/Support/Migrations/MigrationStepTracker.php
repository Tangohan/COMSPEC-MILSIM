<?php

declare(strict_types=1);

namespace App\Support\Migrations;

use PDO;
use Throwable;

/**
 * Suivi de l'étape en cours dans run-migrations.php : en-tête, capture de la sortie (sans la retenir :
 * l'affichage reste en direct), statut (ok / warn / error), durée, et ligne dans schema_migration_runs.
 *
 * Les étapes s'exécutent dans la portée globale du pipeline : ce suivi ne fait qu'encadrer le require.
 */
final class MigrationStepTracker
{
    /** @var callable():?PDO|null */
    private static $pdo = null;

    /** @var callable():int|null nombre d'échecs déjà notés par le moteur (ComspecMigrationRunner) */
    private static $failures = null;

    private static int $failuresBefore = 0;

    private static string $runId = '';

    private static string $mode = 'cli';

    private static int $total = 0;

    /** @var array<string, mixed>|null */
    private static ?array $current = null;

    private static string $capture = '';

    private static int $obLevel = 0;

    private static float $startedAt = 0.0;

    /** @var array{ok: int, warn: int, error: int} */
    private static array $counts = ['ok' => 0, 'warn' => 0, 'error' => 0];

    /** @var list<array{id: string, label: string, status: string, message: string}> */
    private static array $problems = [];

    /**
     * @param callable():?PDO $pdo connexion courante (elle peut être recréée entre deux étapes)
     * @param callable():int|null $failures échecs notés par le moteur : une étape qui en ajoute est en erreur
     */
    public static function start(callable $pdo, string $runId, string $mode, int $total, ?callable $failures = null): void
    {
        self::$pdo = $pdo;
        self::$failures = $failures;
        self::$runId = $runId;
        self::$mode = $mode;
        self::$total = $total;
        self::$counts = ['ok' => 0, 'warn' => 0, 'error' => 0];
        self::$problems = [];
        register_shutdown_function(static function (): void {
            if (self::$current !== null) {
                $err = error_get_last();
                self::end(null, 'Pipeline interrompu' . ($err !== null ? ' : ' . (string) $err['message'] : ''));
            }
        });
    }

    /** @param array{id: string, label: string, index: int} $step */
    public static function begin(array $step): void
    {
        self::$current = $step;
        self::$capture = '';
        self::$startedAt = microtime(true);
        self::$failuresBefore = self::$failures !== null ? (int) (self::$failures)() : 0;
        echo "\n» [" . $step['index'] . '/' . self::$total . '] ' . $step['label'] . ' (' . $step['id'] . ")\n";
        ob_start(static function (string $chunk): string {
            if (strlen(self::$capture) < 200000) {
                self::$capture .= $chunk;
            }

            return $chunk;
        }, 1);
        self::$obLevel = ob_get_level();
        // La fonction d'affichage du pipeline ne descend pas sous ce niveau (voir $migrationFlush).
        $GLOBALS['__migrations_ob_floor'] = self::$obLevel;
    }

    public static function end(?Throwable $error = null, ?string $fatal = null): void
    {
        $step = self::$current;
        if ($step === null) {
            return;
        }
        self::$current = null;
        if (ob_get_level() >= self::$obLevel && self::$obLevel > 0) {
            while (ob_get_level() > self::$obLevel) {
                @ob_end_flush();
            }
            @ob_end_flush();
        }
        $GLOBALS['__migrations_ob_floor'] = max(0, self::$obLevel - 1);
        $ms = (int) round((microtime(true) - self::$startedAt) * 1000);
        $output = self::$capture;
        if ($error !== null) {
            $line = '[ERREUR] Étape ' . $step['id'] . ' : ' . $error->getMessage();
            echo '  ' . $line . "\n";
            $output .= $line . "\n";
        }
        if ($fatal !== null) {
            $output .= '[ERREUR] ' . $fatal . "\n";
        }
        $engineFailed = self::$failures !== null && (int) (self::$failures)() > self::$failuresBefore;
        [$status, $message] = self::classify($output, $error !== null || $fatal !== null || $engineFailed);
        $message = self::redact($message);
        self::$counts[$status]++;
        if ($status !== 'ok') {
            self::$problems[] = ['id' => (string) $step['id'], 'label' => (string) $step['label'], 'status' => $status, 'message' => $message];
        }
        $pdo = self::$pdo !== null ? (self::$pdo)() : null;
        if ($pdo instanceof PDO && self::$runId !== '') {
            MigrationRunLog::record($pdo, self::$runId, self::$mode, (string) $step['id'], $status, $ms, $message, self::redact($output));
        }
    }

    /**
     * Statut d'une étape d'après sa sortie : [ERREUR] ou exception → error, [ATTENTION] → warn, sinon ok.
     * Le message retenu est la première ligne en erreur (ou en avertissement), sinon la dernière ligne utile.
     *
     * @return array{0: string, 1: string}
     */
    public static function classify(string $output, bool $threw = false): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $output) ?: []), static fn (string $l): bool => $l !== ''));
        $firstError = null;
        $firstWarn = null;
        foreach ($lines as $l) {
            if ($firstError === null && (str_contains($l, '[ERREUR') || str_starts_with($l, 'Connexion impossible'))) {
                $firstError = $l;
            } elseif ($firstWarn === null && str_contains($l, '[ATTENTION]')) {
                $firstWarn = $l;
            }
        }
        $clean = static fn (?string $l): string => mb_strimwidth(trim((string) preg_replace('/^\[[A-ZÉ ]+\]\s*/u', '', (string) $l)), 0, 300, '…');
        if ($threw || $firstError !== null) {
            return ['error', $clean($firstError ?? $firstWarn ?? 'Erreur')];
        }
        if ($firstWarn !== null) {
            return ['warn', $clean($firstWarn)];
        }
        $last = $lines !== [] ? $lines[count($lines) - 1] : 'Rien à faire : déjà à jour.';

        return ['ok', $clean($last)];
    }

    /** Masque ce qui ne doit pas rester dans les journaux (mot de passe généré du premier administrateur). */
    public static function redact(string $text): string
    {
        return (string) preg_replace('/(Mot de passe généré[^:\n]*:\s*)\S+/u', '$1••••••••', $text);
    }

    /** @return array{ok: int, warn: int, error: int} */
    public static function counts(): array
    {
        return self::$counts;
    }

    /** @return list<array{id: string, label: string, status: string, message: string}> */
    public static function problems(): array
    {
        return self::$problems;
    }

    public static function printSummary(): void
    {
        $c = self::$counts;
        echo "\nÉtapes : " . array_sum($c) . ' exécutée(s) — ' . $c['ok'] . ' OK, ' . $c['warn'] . ' avec avertissement, ' . $c['error'] . " en erreur.\n";
        foreach (self::$problems as $p) {
            echo '  [' . ($p['status'] === 'error' ? 'ERREUR' : 'ATTENTION') . '] ' . $p['id'] . ' — ' . $p['message'] . "\n";
        }
    }
}
