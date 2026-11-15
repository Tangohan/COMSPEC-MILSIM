<?php

declare(strict_types=1);

namespace App\Services\AtakToc\Purge;

use App\Core\Database;
use App\Support\Migrations\SchemaInspector;
use App\Support\SchemaReadyCache;
use PDO;

/**
 * Suppression douce de la carte du poste TOC (marqueurs et tracés) : constantes communes, sonde du schéma et fragments
 * SQL de lecture. Sans la migration 4690 (colonnes deleted_at / deleted_by / delete_batch), rien ne change pour la
 * lecture : available() renvoie false, les fragments sont vides et le poste retombe en mode dégradé.
 */
final class TocSoftDelete
{
    /** Délai d'annulation d'une suppression, en secondes (15 minutes) : au-delà, purge définitive automatique. */
    public const TRASH_TTL = 900;

    /** Un marqueur du jeu supprimé reste 48 h en pierre tombale : un relais absent le retire de la carte native à son retour. */
    public const TOMBSTONE_TTL = 172800;

    public const BATCH_PATTERN = '/^[0-9a-f]{16}$/';

    /** Fragment SQL : ligne non supprimée (colonne préfixée par $alias, sans point si vide). */
    public const LIVE_SQL = ' AND deleted_at IS NULL';

    private static ?bool $override = null;

    /** Tests : force l'état (null : sonde réelle). */
    public static function force(?bool $available): void
    {
        self::$override = $available;
    }

    public static function available(?PDO $pdo = null): bool
    {
        if (self::$override !== null) {
            return self::$override;
        }

        return SchemaReadyCache::remember('atak_toc_soft_delete.columns', static function () use ($pdo): bool {
            try {
                $schema = new SchemaInspector($pdo ?? Database::getPdo());

                return $schema->columnExists('atak_markers', 'deleted_at')
                    && $schema->columnExists('atak_map_shapes', 'deleted_at')
                    && $schema->tableExists('atak_toc_purges');
            } catch (\Throwable) {
                return false;
            }
        });
    }

    /** Fragment à ajouter aux lectures des tracés et des marqueurs : rien quand la migration manque. */
    public static function liveSql(?PDO $pdo = null): string
    {
        return self::available($pdo) ? self::LIVE_SQL : '';
    }
}
