<?php

declare(strict_types=1);

namespace App\Services\GameEquipment\Configurator;

/**
 * Tendances de l'équipement d'une communauté (configurateur et page Équipement) : objets les plus portés en jeu, les
 * plus ajoutés aux tenues et les plus mis en favori sur une période, tenues les plus mises en favori.
 *
 * Pur : agrège des lignes déjà lues par EquipmentConfiguratorSocialRepository (toujours filtrées par communauté en
 * SQL) et refait le filtre par communauté par sécurité. Aucune donnée personnelle ne sort : seulement des classes
 * Arma, des noms de tenues visibles par toute la communauté et des nombres de membres distincts. Un objet ou une
 * tenue choisi par moins de MIN_MEMBERS membres n'apparaît pas (on ne devine pas le choix d'une seule personne).
 */
final class EquipmentTrends
{
    public const DAYS = 30;

    public const MIN_MEMBERS = 2;

    public const LIMIT = 30;

    /**
     * Objets présents dans des tenues enregistrées ou modifiées sur la période : membres distincts par classe.
     *
     * @param iterable<array<string, mixed>> $rows lignes {tenant_id, user_id, payload_text}
     * @return list<array{class: string, members: int}>
     */
    public static function fromLoadouts(iterable $rows, int $tenantId, int $min = self::MIN_MEMBERS, int $limit = self::LIMIT): array
    {
        $members = [];
        $names = [];
        foreach ($rows as $r) {
            if ((int) ($r['tenant_id'] ?? 0) !== $tenantId || $tenantId < 1) {
                continue;
            }
            $user = (int) ($r['user_id'] ?? 0);
            if ($user < 1) {
                continue;
            }
            $doc = LoadoutDocument::fromText((string) ($r['payload_text'] ?? ''));
            if (!$doc['ok']) {
                continue;
            }
            foreach (LoadoutDocument::classes($doc['loadout']) as $class) {
                $key = strtolower($class);
                $members[$key][$user] = true;
                $names[$key] ??= $class;
            }
        }
        $counts = [];
        foreach ($members as $key => $users) {
            $counts[$names[$key]] = count($users);
        }

        return self::top($counts, $min, $limit);
    }

    /**
     * Agrégats déjà comptés en SQL (portés en jeu, mis en favori) : {tenant_id, class_name, members}.
     *
     * @param iterable<array<string, mixed>> $rows
     * @return list<array{class: string, members: int}>
     */
    public static function fromAggregates(iterable $rows, int $tenantId, int $min = self::MIN_MEMBERS, int $limit = self::LIMIT): array
    {
        $counts = [];
        foreach ($rows as $r) {
            if ((int) ($r['tenant_id'] ?? 0) !== $tenantId || $tenantId < 1) {
                continue;
            }
            $class = (string) ($r['class_name'] ?? '');
            if (preg_match(ItemCatalogImport::CLASS_RE, $class) !== 1) {
                continue;
            }
            $counts[$class] = max($counts[$class] ?? 0, (int) ($r['members'] ?? 0));
        }

        return self::top($counts, $min, $limit);
    }

    /**
     * Tenues les plus mises en favori : seulement celles visibles par toute la communauté, sans leur propriétaire.
     *
     * @param iterable<array<string, mixed>> $rows {tenant_id, id, name, visibility, members}
     * @return list<array{ref: string, name: string, members: int}>
     */
    public static function wardrobes(iterable $rows, int $tenantId, int $min = self::MIN_MEMBERS, int $limit = 12): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ((int) ($r['tenant_id'] ?? 0) !== $tenantId || $tenantId < 1) {
                continue;
            }
            if ((string) ($r['visibility'] ?? 'tenant') === 'personal' || (int) ($r['members'] ?? 0) < $min) {
                continue;
            }
            $out[] = ['ref' => 'w:' . (int) $r['id'], 'name' => (string) ($r['name'] ?? ''), 'members' => (int) $r['members']];
        }
        usort($out, static fn (array $a, array $b): int => $b['members'] <=> $a['members'] ?: strcmp($a['name'], $b['name']));

        return array_slice($out, 0, max(0, $limit));
    }

    /**
     * Forme compacte pour le navigateur : [classe en minuscules => {w: portés en jeu, t: ajoutés aux tenues, f: favoris}].
     *
     * @param list<array{class: string, members: int}> $worn
     * @param list<array{class: string, members: int}> $added
     * @param list<array{class: string, members: int}> $favored
     * @return array<string, array{w?: int, t?: int, f?: int}>
     */
    public static function byClass(array $worn, array $added, array $favored): array
    {
        $out = [];
        foreach (['w' => $worn, 't' => $added, 'f' => $favored] as $key => $list) {
            foreach ($list as $row) {
                $out[strtolower($row['class'])][$key] = $row['members'];
            }
        }

        return $out;
    }

    /**
     * @param array<string, int> $counts
     * @return list<array{class: string, members: int}>
     */
    private static function top(array $counts, int $min, int $limit): array
    {
        $out = [];
        foreach ($counts as $class => $n) {
            if ($n >= max(1, $min)) {
                $out[] = ['class' => (string) $class, 'members' => $n];
            }
        }
        usort($out, static fn (array $a, array $b): int => $b['members'] <=> $a['members'] ?: strcmp($a['class'], $b['class']));

        return array_slice($out, 0, max(0, $limit));
    }
}
