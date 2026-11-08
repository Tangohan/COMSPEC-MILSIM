<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Tables de la surveillance. Source unique : l'étape 3970 de database/steps/ (file active des migrations) et le filet
 * ensure() des dépôts. Données courtes par construction : voir RETENTION_* et WatchFlusher::purge().
 */
final class WatchSchema
{
    /** Conservation (jours) : minutes de charge, charge par communauté et heure, alertes (toutes, traitées ou non). */
    public const RETENTION_MINUTE_DAYS = 3;
    public const RETENTION_HOUR_DAYS = 8;
    public const RETENTION_ALERT_DAYS = 30;

    /** @return array<string, string> table => CREATE TABLE IF NOT EXISTS … */
    public static function tables(): array
    {
        return [
            'platform_load_minute' => "CREATE TABLE IF NOT EXISTS platform_load_minute (
                minute_ts DATETIME NOT NULL,
                route VARCHAR(120) NOT NULL,
                requests INT UNSIGNED NOT NULL DEFAULT 0,
                errors_5xx INT UNSIGNED NOT NULL DEFAULT 0,
                shed INT UNSIGNED NOT NULL DEFAULT 0,
                dur_sum_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
                dur_max_ms INT UNSIGNED NOT NULL DEFAULT 0,
                b100 INT UNSIGNED NOT NULL DEFAULT 0,
                b300 INT UNSIGNED NOT NULL DEFAULT 0,
                b1000 INT UNSIGNED NOT NULL DEFAULT 0,
                b3000 INT UNSIGNED NOT NULL DEFAULT 0,
                bmore INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (minute_ts, route),
                KEY idx_plm_route (route, minute_ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'platform_load_tenant_hour' => "CREATE TABLE IF NOT EXISTS platform_load_tenant_hour (
                hour_ts DATETIME NOT NULL,
                tenant_id INT UNSIGNED NOT NULL,
                route VARCHAR(120) NOT NULL,
                requests INT UNSIGNED NOT NULL DEFAULT 0,
                errors_5xx INT UNSIGNED NOT NULL DEFAULT 0,
                shed INT UNSIGNED NOT NULL DEFAULT 0,
                dur_sum_ms BIGINT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (hour_ts, tenant_id, route),
                KEY idx_plth_tenant (tenant_id, hour_ts)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'platform_abuse_alerts' => "CREATE TABLE IF NOT EXISTS platform_abuse_alerts (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                kind VARCHAR(12) NOT NULL,
                rule VARCHAR(48) NOT NULL,
                fingerprint CHAR(40) NOT NULL,
                tenant_id INT UNSIGNED NULL,
                user_id INT UNSIGNED NULL,
                steam_uid VARCHAR(32) NULL,
                measured_json TEXT NOT NULL,
                occurrences INT UNSIGNED NOT NULL DEFAULT 1,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                status VARCHAR(12) NOT NULL DEFAULT 'open',
                handled_by INT UNSIGNED NULL,
                handled_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_paa_status (status, last_seen_at),
                KEY idx_paa_fingerprint (fingerprint, last_seen_at),
                KEY idx_paa_tenant (tenant_id, last_seen_at),
                KEY idx_paa_last (last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }

    /** Crée les tables absentes (idempotent). */
    public static function ensure(\PDO $pdo): void
    {
        foreach (self::tables() as $sql) {
            $pdo->exec($sql);
        }
    }
}
