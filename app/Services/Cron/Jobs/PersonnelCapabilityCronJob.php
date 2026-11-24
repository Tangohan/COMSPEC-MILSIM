<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Core\Database;
use App\Services\Cron\CronJobInterface;
use App\Services\Personnel\OperationalCapabilityService;
use PDO;

/**
 * Recalcule currencies puis snapshots de capacité (axes 3 & 4).
 * Une erreur sur un membre n’interrompt pas le tenant.
 */
final class PersonnelCapabilityCronJob implements CronJobInterface
{
    public function __construct(
        private OperationalCapabilityService $capability,
    ) {}

    public function key(): string
    {
        return 'personnel_capability_refresh';
    }

    public function label(): string
    {
        return 'Currency & capacité opérationnelle';
    }

    public function description(): string
    {
        return 'Recalcule CURRENT/NON_CURRENT des qualifications et les snapshots de déployabilité (axes séparés du grade).';
    }

    public function run(): array
    {
        $pdo = Database::getPdo();
        $details = [];
        $users = 0;
        $nonCurrent = 0;
        $errors = 0;
        $firstError = null;

        if (!$this->schemaReady($pdo)) {
            return [
                'ok' => true,
                'summary' => 'Schéma axes capacité absent — no-op.',
                'details' => ['Migration personnel_capability_axes non appliquée.'],
            ];
        }

        $tenantIds = $pdo->query(
            'SELECT DISTINCT tenant_id FROM personnel_qualifications WHERE tenant_id IS NOT NULL AND tenant_id > 0'
        )->fetchAll(PDO::FETCH_COLUMN);
        if (!is_array($tenantIds)) {
            $tenantIds = [];
        }

        foreach ($tenantIds as $tenantIdRaw) {
            $tenantId = (int) $tenantIdRaw;
            if ($tenantId < 1) {
                continue;
            }
            try {
                $st = $pdo->prepare(
                    'SELECT DISTINCT user_id FROM personnel_qualifications WHERE tenant_id = ? AND user_id > 0'
                );
                $st->execute([$tenantId]);
                while ($uidRaw = $st->fetchColumn()) {
                    $userId = (int) $uidRaw;
                    if ($userId < 1) {
                        continue;
                    }
                    try {
                        $axes = $this->capability->recomputeAndPersist($tenantId, $userId, false);
                        $nonCurrent += (int) ($axes->capability['non_current_qualifications'] ?? 0);
                        ++$users;
                    } catch (\Throwable $e) {
                        ++$errors;
                        $firstError ??= self::describeError($e);
                        $details[] = 'membre #' . $userId . ' : ' . self::describeError($e);
                    }
                }
            } catch (\Throwable $e) {
                ++$errors;
                $firstError ??= self::describeError($e);
                $details[] = 'tenant #' . $tenantId . ' : ' . self::describeError($e);
            }
        }

        return [
            'ok' => $errors === 0,
            'summary' => self::buildSummary($users, $nonCurrent, $errors, $firstError),
            'details' => $details,
        ];
    }

    /**
     * « non-current » est une information métier (qualifications à repratiquer), pas une erreur.
     */
    public static function buildSummary(int $users, int $nonCurrentQualifications, int $errors, ?string $firstError): string
    {
        $out = sprintf(
            '%d membre(s) recalculé(s), %d qualification(s) non-current (info), %d erreur(s)',
            $users,
            $nonCurrentQualifications,
            $errors
        );
        if ($errors > 0 && $firstError !== null && $firstError !== '') {
            $out .= ' — 1re cause : ' . $firstError;
        }

        return $out . '.';
    }

    /**
     * Nom de la cause + message tronqué, sans valeur de donnée (valeurs entre quotes masquées).
     */
    public static function describeError(\Throwable $e): string
    {
        $msg = preg_replace("/(value|valeur):\s*'[^']*'/i", "$1: '…'", $e->getMessage()) ?? '';
        $msg = preg_replace('/\s+/', ' ', trim($msg)) ?? '';
        if (mb_strlen($msg) > 160) {
            $msg = mb_substr($msg, 0, 157) . '…';
        }
        $parts = explode('\\', $e::class);
        $class = (string) end($parts);

        return $class . ' : ' . $msg;
    }

    private function schemaReady(PDO $pdo): bool
    {
        try {
            $st = $pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'personnel_qualifications'
                   AND COLUMN_NAME = 'currency_status'
                 LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }
}
