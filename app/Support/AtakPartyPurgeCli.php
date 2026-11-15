<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Corps de tools/atak-purge-default-units.php (hors du script pour être testé) : purge des pistes périmées des
 * communautés par défaut, en simulation tant que $apply est faux, avec une ligne de journal par passage.
 */
final class AtakPartyPurgeCli
{
    /**
     * @param callable(string): void $out
     * @return int code de retour (0 sans erreur, 1 si une communauté a échoué, 2 si l'appel est invalide)
     */
    public static function run(PDO $pdo, bool $apply, ?int $onlyTenant, string $cacheDir, string $logFile, callable $out, ?int $now = null): int
    {
        $now ??= time();
        $tenants = AtakPartyScope::defaultTenantIds($pdo);
        if ($onlyTenant !== null) {
            if (!in_array($onlyTenant, $tenants, true)) {
                $out("La communauté {$onlyTenant} n'est pas une communauté par défaut : rien n'est fait.");

                return 2;
            }
            $tenants = [$onlyTenant];
        }
        $out($apply
            ? 'MODE --apply : les pistes périmées de la communauté par défaut sont supprimées.'
            : 'SIMULATION (aucune écriture). Ajoutez --apply pour supprimer.');
        if ($tenants === []) {
            $out('Aucune communauté par défaut.');

            return 0;
        }
        $failed = 0;
        foreach ($tenants as $tenantId) {
            try {
                $r = AtakPartyScope::purgeStaleDefaultUnits($pdo, $tenantId, $apply, $now);
            } catch (\Throwable $e) {
                $failed++;
                $out("  ERREUR communauté {$tenantId} : " . mb_substr($e->getMessage(), 0, 160));
                continue;
            }
            $line = sprintf(
                'communauté %d : %d piste(s) sans signe de vie depuis plus de 24 h (IA alliées %d, comptes U-… %d, indicatifs %d), supprimées : %d',
                $tenantId,
                $r['found'],
                $r['by_kind']['ia_alliee'],
                $r['by_kind']['compte'],
                $r['by_kind']['indicatif'],
                $r['deleted']
            );
            $out('  ' . $line);
            self::log($logFile, $now, $apply, $line);
        }
        $files = AtakPartyScope::sweepReservedMapFiles($cacheDir, $tenants, $apply, $now);
        $line = sprintf('fichiers d\'état de cartes réservées non modifiés depuis 24 h : %d, supprimés : %d', $files['found'], $files['deleted']);
        $out('  ' . $line);
        self::log($logFile, $now, $apply, $line);

        return $failed > 0 ? 1 : 0;
    }

    private static function log(string $logFile, int $now, bool $apply, string $line): void
    {
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($logFile, gmdate('Y-m-d\TH:i:s\Z', $now) . ' ' . ($apply ? 'apply' : 'simulation') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
    }
}
