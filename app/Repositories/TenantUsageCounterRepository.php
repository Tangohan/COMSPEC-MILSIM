<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Compteurs de quotas par tenant et période (ex. créations mensuelles).
 * Fuseau : `APP_TIMEZONE` (env) ou UTC.
 */
final class TenantUsageCounterRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public static function appTimezone(): \DateTimeZone
    {
        $tz = '';
        if (function_exists('config')) {
            $tz = (string) config('app.timezone', '');
        }
        if ($tz === '') {
            $tz = (string) (getenv('APP_TIMEZONE') ?: ($_ENV['APP_TIMEZONE'] ?? ''));
        }
        if ($tz !== '') {
            try {
                return new \DateTimeZone($tz);
            } catch (\Throwable) {
            }
        }

        return new \DateTimeZone('UTC');
    }

    /** Début de période calendaire (fuseau app). */
    public static function periodStartForReset(string $resetPeriod): string
    {
        $tz = self::appTimezone();
        if ($resetPeriod === 'monthly') {
            return (new \DateTimeImmutable('first day of this month 00:00:00', $tz))->format('Y-m-d');
        }
        if ($resetPeriod === 'weekly') {
            $d = new \DateTimeImmutable('now', $tz);

            return $d->modify('monday this week')->format('Y-m-d');
        }
        if ($resetPeriod === 'daily') {
            return (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        }

        return (new \DateTimeImmutable('first day of this month 00:00:00', $tz))->format('Y-m-d');
    }

    public function getAmount(int $tenantId, string $metricKey, string $periodStartYmd): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT amount FROM tenant_usage_counters WHERE tenant_id = ? AND metric_key = ? AND period_start = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $metricKey, $periodStartYmd]);
        $v = $stmt->fetchColumn();

        return $v !== false ? (int) $v : 0;
    }

    public function increment(int $tenantId, string $metricKey, string $periodStartYmd, int $delta = 1): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_usage_counters (tenant_id, metric_key, period_start, amount, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount), updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $metricKey, $periodStartYmd, $delta]);
    }

    /** Aligne le compteur au minimum observé (ex. réconciliation avec la table métier). */
    public function raiseAmountToAtLeast(int $tenantId, string $metricKey, string $periodStartYmd, int $minimum): void
    {
        $current = $this->getAmount($tenantId, $metricKey, $periodStartYmd);
        if ($current >= $minimum) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO tenant_usage_counters (tenant_id, metric_key, period_start, amount, updated_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE amount = GREATEST(amount, VALUES(amount)), updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $metricKey, $periodStartYmd, $minimum]);
    }
}
