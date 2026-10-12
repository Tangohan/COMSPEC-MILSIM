<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

/**
 * Clé de la session jouée envoyée par l'ATAK natif : « nom@carte#AAAAMMJJHHMM » (XEH_postInitServer.sqf,
 * COMSPEC_ATAK_MissionKey). Portée par les positions, le temps d'écran, les comptes rendus et les pertes.
 *
 * Le schéma (bootstrap/atak_mission_key_migration.php) n'est posé qu'à la première écriture qui échoue faute
 * de colonne ou de table : aucune requête information_schema sur le chemin chaud des positions.
 */
final class AtakMissionKey
{
    private static bool $ensured = false;

    /** Clé nettoyée (190 caractères au plus), ou '' si absente ou mal formée. */
    public static function normalize(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $key = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $value));
        if ($key === '' || !str_contains($key, '@')) {
            return '';
        }

        return mb_substr($key, 0, 190);
    }

    /** Libellé lisible : « nom (carte) ». */
    public static function label(string $key): string
    {
        $base = explode('#', $key, 2)[0];
        $parts = explode('@', $base, 2);

        return count($parts) === 2 && $parts[1] !== '' ? $parts[0] . ' (' . $parts[1] . ')' : $base;
    }

    /** Colonne ou table absente (migration pas encore passée). */
    public static function isMissingSchema(\Throwable $e): bool
    {
        $state = $e instanceof \PDOException ? (string) ($e->errorInfo[0] ?? $e->getCode()) : '';

        return in_array($state, ['42S22', '42S02'], true);
    }

    public static function ensureSchema(PDO $pdo): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;
        $path = base_path('bootstrap/atak_mission_key_migration.php');
        if (!is_file($path)) {
            return;
        }
        $migrate = require $path;
        if (!is_callable($migrate)) {
            return;
        }
        ob_start();
        try {
            $migrate($pdo);
        } catch (\Throwable) {
            // Écriture suivante sans clé de mission : rien de perdu côté jeu.
        } finally {
            ob_end_clean();
        }
    }
}
