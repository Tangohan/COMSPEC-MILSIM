<?php

declare(strict_types=1);

namespace App\Support\Migrations;

/**
 * File active des nouvelles migrations (database/steps/registry.php), vide après la baseline du 29 octobre 2026.
 *
 * Chaque étape est un fichier PHP de database/steps/ exécuté par run-migrations.php dans la portée globale
 * du lanceur. Seules ces entrées explicites sont exécutables ; le registre historique reste une archive.
 */
final class MigrationRegistry
{
    public const GROUPS = [
        'schema' => 'Schéma',
        'migrations' => 'Migrations',
        'referentiels' => 'Référentiels',
        'maintenance' => 'Maintenance',
    ];

    public const GROUP_HINTS = [
        'schema' => 'Tables et colonnes créées directement : schema.sql, fichiers SQL, colonnes manquantes.',
        'migrations' => 'Migrations PHP de bootstrap/ : chaque module crée ou fait évoluer ses tables.',
        'referentiels' => 'Données de référence indispensables : cartes ATAK, grades, formats courrier, communauté système.',
        'maintenance' => 'Corrections et rattrapages de données (backfill, nettoyage, doublons).',
    ];

    /** @var array<string, list<array<string, mixed>>> */
    private static array $cache = [];

    /**
     * @return list<array{id: string, label: string, group: string, file: string, rerun: bool, path: string, index: int}>
     */
    public static function load(string $root): array
    {
        $root = rtrim($root, '/');
        if (isset(self::$cache[$root])) {
            return self::$cache[$root];
        }
        $path = $root . '/database/steps/registry.php';
        $raw = is_file($path) ? require $path : [];
        $out = [];
        $seen = [];
        foreach (is_array($raw) ? $raw : [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (string) ($row['id'] ?? '');
            $file = (string) ($row['file'] ?? '');
            if ($id === '' || $file === '' || isset($seen[$id]) || !preg_match('/^[a-z0-9_]+$/', $id) || basename($file) !== $file) {
                continue;
            }
            $group = (string) ($row['group'] ?? 'migrations');
            $seen[$id] = true;
            $out[] = [
                'id' => $id,
                'label' => (string) ($row['label'] ?? $id),
                'group' => isset(self::GROUPS[$group]) ? $group : 'migrations',
                'file' => $file,
                'rerun' => !empty($row['rerun']),
                'path' => $root . '/database/steps/' . $file,
                'index' => count($out) + 1,
            ];
        }

        return self::$cache[$root] = $out;
    }

    /** @return array{id: string, label: string, group: string, file: string, rerun: bool, path: string, index: int}|null */
    public static function find(string $root, string $id): ?array
    {
        foreach (self::load($root) as $step) {
            if ($step['id'] === $id) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @return array<string, list<array<string, mixed>>>
     */
    public static function byGroup(array $steps): array
    {
        $out = array_fill_keys(array_keys(self::GROUPS), []);
        foreach ($steps as $step) {
            $out[(string) $step['group']][] = $step;
        }

        return $out;
    }
}
