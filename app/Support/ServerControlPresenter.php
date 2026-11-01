<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Données de la page Contrôle de mission : réglages regroupés par thème avec leur branchement réel en jeu
 * (ServerControlWiring), aperçu joueur, état du serveur de jeu et journal des changements.
 * Fonctions pures : la page et les tests leur passent les catalogues et les lignes déjà lues.
 */
final class ServerControlPresenter
{
    /** Téléphone vu il y a moins de 10 min : considéré en jeu. */
    public const ONLINE_SECONDS = 600;

    /** Versions comparées aux versions minimales : téléphones vus depuis 7 jours. */
    public const RECENT_SECONDS = 7 * 86400;

    /**
     * @param list<array<string, mixed>> $ruleRows   AtakExperienceService::catalogWithState()
     * @param list<array<string, mixed>> $moduleRows AtakBridgeModulesService::catalogWithState()
     * @param list<string> $recentVersions versions du mod des téléphones vus récemment
     * @return array{themes: array<string, array{label: string, lead: string, items: list<array<string, mixed>>}>, soon: list<array<string, mixed>>, counts: array<string, int>}
     */
    public static function themes(array $ruleRows, array $moduleRows, array $recentVersions = []): array
    {
        $themes = [];
        foreach (ServerControlWiring::THEMES as $id => [$label, $lead]) {
            $themes[$id] = ['label' => $label, 'lead' => $lead, 'items' => []];
        }
        $soon = [];
        $counts = array_fill_keys(array_keys(ServerControlWiring::STATES), 0);
        $rules = ServerControlWiring::rules();
        $modules = ServerControlWiring::modules();

        $place = static function (array $item) use (&$themes, &$soon, &$counts, $recentVersions): void {
            $item['state_label'] = ServerControlWiring::STATES[$item['state']] ?? ServerControlWiring::STATES['todo'];
            $item['delay_label'] = $item['delay'] !== null ? (ServerControlWiring::DELAYS[$item['delay']] ?? '') : '';
            $item['below_min'] = $item['min'] !== null ? self::countBelow($recentVersions, (string) $item['min']) : 0;
            $counts[$item['state']] = ($counts[$item['state']] ?? 0) + 1;
            if ($item['state'] === 'todo') {
                $soon[] = $item;
            } else {
                $themes[$item['theme']]['items'][] = $item;
            }
        };

        foreach ($ruleRows as $row) {
            $id = (string) ($row['id'] ?? '');
            $wiring = $rules[$id] ?? null;
            if ($id === '' || $wiring === null) {
                continue;
            }
            $place($wiring + [
                'kind' => 'rule',
                'id' => $id,
                'label' => (string) ($row['label'] ?? $id),
                'description' => (string) ($row['description'] ?? ''),
                'type' => (string) ($row['type'] ?? 'bool'),
                'value' => $row['value'] ?? ($row['default'] ?? null),
                'default' => $row['default'] ?? null,
                'choices' => is_array($row['choices'] ?? null) ? $row['choices'] : [],
                'imposed' => self::isImposed($row),
                'note' => $wiring['note'] ?? '',
            ]);
        }
        foreach ($moduleRows as $row) {
            $id = (string) ($row['id'] ?? '');
            $wiring = $modules[$id] ?? null;
            if ($id === '' || $wiring === null) {
                continue;
            }
            $place($wiring + [
                'kind' => 'module',
                'id' => $id,
                'label' => (string) ($row['label'] ?? $id),
                'description' => (string) ($row['description'] ?? ''),
                'type' => 'module',
                'value' => !empty($row['enabled']),
                'default' => true,
                'choices' => [],
                'imposed' => empty($row['enabled']),
                'note' => $wiring['note'] ?? '',
            ]);
        }

        return ['themes' => array_filter($themes, static fn (array $t): bool => $t['items'] !== []), 'soon' => $soon, 'counts' => $counts];
    }

    /** Une règle s'impose aux joueurs quand elle s'écarte de « au choix » / de sa valeur par défaut. */
    public static function isImposed(array $row): bool
    {
        $value = $row['value'] ?? ($row['default'] ?? null);
        if (($row['type'] ?? 'bool') === 'bool') {
            return !empty($value);
        }

        return (string) $value !== (string) ($row['default'] ?? 'player');
    }

    /** Libellé lisible d'une valeur (choix de liste, Activé / Désactivé). */
    public static function valueLabel(array $item, mixed $value): string
    {
        $type = (string) ($item['type'] ?? 'bool');
        if ($type === 'bool' || $type === 'module') {
            return !empty($value) ? 'Activé' : 'Désactivé';
        }
        foreach (($item['choices'] ?? []) as $choice) {
            if ((string) ($choice['value'] ?? '') === (string) $value) {
                return (string) ($choice['label'] ?? $value);
            }
        }

        return (string) $value;
    }

    /**
     * Ce que verront les joueurs : règles imposées, fonctions coupées, apps imposées ou interdites.
     *
     * @param array{themes: array<string, array{items: list<array<string, mixed>>}>, soon: list<array<string, mixed>>} $view
     * @param array<string, string> $appsPolicy
     * @param list<array{id: string, name: string}> $phoneApps
     * @return list<array{label: string, value: string, state: string}>
     */
    public static function preview(array $view, array $appsPolicy, array $phoneApps): array
    {
        $out = [];
        foreach ($view['themes'] as $theme) {
            foreach ($theme['items'] as $item) {
                if (!empty($item['imposed'])) {
                    $out[] = ['label' => (string) $item['label'], 'value' => self::valueLabel($item, $item['value']), 'state' => (string) $item['state']];
                }
            }
        }
        $names = array_column($phoneApps, 'name', 'id');
        foreach (['required' => 'Apps obligatoires', 'forbidden' => 'Apps interdites'] as $rule => $label) {
            $list = [];
            foreach ($appsPolicy as $id => $r) {
                if ($r === $rule) {
                    $list[] = (string) ($names[$id] ?? $id);
                }
            }
            if ($list !== []) {
                $out[] = ['label' => $label, 'value' => implode(', ', $list), 'state' => 'live'];
            }
        }

        return $out;
    }

    /**
     * État du serveur de jeu à partir des téléphones de la communauté et des présences remontées.
     *
     * @param list<array<string, mixed>> $terminals AtakAdminControlRepository::listTerminals()
     * @param list<array<string, mixed>> $presence  TenantGameServerRepository::recentPresence()
     * @return array{has_data: bool, online: int, recent: int, last_seen: ?int, server: string, mission: string, world: string, mod_top: string, mod_oldest: string, link_top: string, versions: list<string>}
     */
    public static function presence(array $terminals, array $presence, int $now): array
    {
        $online = 0;
        $recent = 0;
        $last = null;
        $versions = [];
        $links = [];
        foreach ($terminals as $t) {
            $seen = UtcDateTime::timestamp($t['last_seen_at'] ?? null);
            if ($seen === null) {
                continue;
            }
            $last = max($last ?? 0, $seen);
            $age = $now - $seen;
            if ($age <= self::ONLINE_SECONDS) {
                $online++;
            }
            if ($age > self::RECENT_SECONDS) {
                continue;
            }
            $recent++;
            $v = trim((string) ($t['mod_version'] ?? ''));
            if ($v !== '') {
                $versions[] = $v;
            }
            $l = trim((string) ($t['extension_version'] ?? ''));
            if ($l !== '') {
                $links[] = $l;
            }
        }
        $top = static function (array $list): string {
            if ($list === []) {
                return '';
            }
            $c = array_count_values($list);
            arsort($c);

            return (string) array_key_first($c);
        };
        $oldest = '';
        foreach ($versions as $v) {
            if ($oldest === '' || version_compare(self::numeric($v), self::numeric($oldest), '<')) {
                $oldest = $v;
            }
        }
        $p = $presence[0] ?? [];
        $pSeen = UtcDateTime::timestamp($p['seen_at'] ?? null);
        if ($pSeen !== null) {
            $last = max($last ?? 0, $pSeen);
        }
        $server = trim((string) ($p['matched_label'] ?? '')) ?: trim((string) ($p['server_name'] ?? ''));

        return [
            'has_data' => $last !== null,
            'online' => $online,
            'recent' => $recent,
            'last_seen' => $last,
            'server' => $server,
            'mission' => trim((string) ($p['mission'] ?? '')),
            'world' => trim((string) ($p['world'] ?? '')),
            'mod_top' => $top($versions),
            'mod_oldest' => $oldest,
            'link_top' => $top($links),
            'versions' => $versions,
        ];
    }

    /** « 2.9.21.AG » → « 2.9.21 » pour comparer les versions. */
    public static function numeric(string $version): string
    {
        return preg_match('/^\d+(?:\.\d+)*/', trim($version), $m) === 1 ? $m[0] : '0';
    }

    /** @param list<string> $versions */
    public static function countBelow(array $versions, string $min): int
    {
        return count(array_filter($versions, static fn (string $v): bool => version_compare(self::numeric($v), $min, '<')));
    }

    /**
     * Différences lisibles entre deux états (règles, fonctions, apps) pour le journal et le message d'enregistrement.
     *
     * @param array<string, array<string, mixed>> $items id préfixé (rule:x, module:x) => item avec type et choices
     * @param array<string, mixed> $before id préfixé => valeur
     * @param array<string, mixed> $after  id préfixé => valeur
     * @return list<array{label: string, from: string, to: string}>
     */
    public static function changes(array $items, array $before, array $after): array
    {
        $out = [];
        foreach ($items as $key => $item) {
            if (!array_key_exists($key, $after)) {
                continue;
            }
            $old = $before[$key] ?? ($item['default'] ?? null);
            $new = $after[$key];
            if (self::valueLabel($item, $old) === self::valueLabel($item, $new)) {
                continue;
            }
            $out[] = ['label' => (string) ($item['label'] ?? $key), 'from' => self::valueLabel($item, $old), 'to' => self::valueLabel($item, $new)];
        }

        return $out;
    }

    /**
     * Journal des changements de la page (entrées d'audit atak.server_control.*).
     *
     * @param list<array<string, mixed>> $rows AuditLogRepository::listForTenant()['rows']
     * @return list<array{at: string, who: string, title: string, changes: list<string>, more: int}>
     */
    public static function journal(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $old = json_decode((string) ($row['old_value'] ?? ''), true);
            $new = json_decode((string) ($row['new_value'] ?? ''), true);
            $old = is_array($old) ? $old : [];
            $new = is_array($new) ? $new : [];
            $lines = [];
            foreach ($new as $label => $to) {
                $lines[] = $label . ' : ' . (isset($old[$label]) && is_scalar($old[$label]) ? $old[$label] . ' → ' : '') . (is_scalar($to) ? (string) $to : '');
            }
            $who = trim((string) ($row['actor_callsign'] ?? '')) ?: trim((string) ($row['actor_display_name'] ?? ''));
            $ts = UtcDateTime::timestamp($row['created_at'] ?? null);
            $out[] = [
                'at' => $ts !== null ? date('d/m/Y à H:i', $ts) : '',
                'who' => $who !== '' ? $who : 'Un administrateur',
                'title' => ($row['action'] ?? '') === 'atak.server_control.network_adopted'
                    ? 'a repris les conditions réseau appliquées en jeu'
                    : 'a modifié ' . count($lines) . ' réglage' . (count($lines) > 1 ? 's' : ''),
                'changes' => array_slice($lines, 0, 4),
                'more' => max(0, count($lines) - 4),
            ];
        }

        return $out;
    }

    /** « il y a 3 min », « il y a 2 h », sinon la date. */
    public static function ago(?int $ts, int $now): string
    {
        if ($ts === null) {
            return '';
        }
        $d = max(0, $now - $ts);

        return match (true) {
            $d < 60 => 'à l’instant',
            $d < 3600 => 'il y a ' . intdiv($d, 60) . ' min',
            $d < 86400 => 'il y a ' . intdiv($d, 3600) . ' h',
            default => 'le ' . date('d/m/Y à H:i', $ts),
        };
    }
}
