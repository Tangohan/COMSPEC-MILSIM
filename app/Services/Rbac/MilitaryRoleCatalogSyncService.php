<?php

declare(strict_types=1);

namespace App\Services\Rbac;

/**
 * Ancien sync catalogue militaire → emplois de dossier.
 * Conservé pour la clé visuelle des catégories ; n’écrit plus en base.
 * Les emplois naissent des unités de l’ORBAT ({@see \App\Services\Personnel\UnitJobRoleSyncService}).
 */
final class MilitaryRoleCatalogSyncService
{
    public static function syncAllTenants(\PDO $pdo): void
    {
        unset($pdo);
    }

    public static function syncForTenant(\PDO $pdo, int $tenantId): void
    {
        unset($pdo, $tenantId);
    }

    public static function categoryKeyFromLabel(string $label): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($t === false) {
            $t = $label;
        }
        $t = strtolower((string) $t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t);

        return trim((string) $t, '-') ?: 'cat';
    }
}
