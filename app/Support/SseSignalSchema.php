<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Database;
use PDO;

/**
 * Colonnes de suivi de sse_engine_signals ajoutées par l'étape 4740 (clé de déduplication, dernière observation, résolution,
 * fusion). Chaque colonne est sondée une fois par connexion : sans l'étape, le moteur déduplique et ferme quand même les
 * signaux, avec les seuls champs d'origine (statut, date de mise à jour).
 */
final class SseSignalSchema
{
    public const COLUMNS = ['dedup_key', 'last_seen_at', 'seen_count', 'changed_at', 'resolved_at', 'resolved_by', 'merged_into'];

    private static ?\WeakMap $known = null;

    /** @return array<string, bool> */
    public static function columns(?PDO $pdo = null): array
    {
        try {
            $pdo ??= Database::getPdo();
        } catch (\Throwable) {
            return array_fill_keys(self::COLUMNS, false);
        }
        self::$known ??= new \WeakMap();
        if (isset(self::$known[$pdo])) {
            return self::$known[$pdo];
        }
        $found = [];
        foreach (self::COLUMNS as $column) {
            try {
                $pdo->query('SELECT ' . $column . ' FROM sse_engine_signals LIMIT 0')->closeCursor();
                $found[$column] = true;
            } catch (\Throwable) {
                $found[$column] = false;
            }
        }

        return self::$known[$pdo] = $found;
    }

    public static function has(string $column, ?PDO $pdo = null): bool
    {
        return self::columns($pdo)[$column] ?? false;
    }

    public static function forget(): void
    {
        self::$known = null;
    }
}
