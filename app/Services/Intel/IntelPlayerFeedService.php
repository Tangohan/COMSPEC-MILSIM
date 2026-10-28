<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Support\LazyDatabaseConnection;
use App\Support\TacticalAlertParser;
use PDO;

/**
 * Tout ce qu'un joueur a remonté du jeu, toutes sources confondues : comptes rendus, fiches SSE, MEDEVAC et 9-line,
 * SIGINT et détections RF, photos et notes de reconnaissance, POI, pings, demandes de QRF, événements de zone,
 * pertes signalées et alertes SALUTE / TIC passées par la messagerie.
 *
 * Chaque table est facultative (une installation peut ne pas l'avoir) et chaque colonne lue est d'abord vérifiée :
 * une source absente compte simplement zéro. Toutes les lectures sont limitées à la communauté (tenant_id).
 */
class IntelPlayerFeedService
{
    use LazyDatabaseConnection;

    /** Catégories affichées, dans l'ordre des colonnes du tableau. */
    public const CATEGORIES = [
        'cr' => ['label' => 'Comptes rendus tactiques', 'short' => 'CR'],
        'fiche' => ['label' => 'Fiches de renseignement SSE', 'short' => 'Fiches'],
        'medevac' => ['label' => 'MEDEVAC et 9-line (CAS / évacuation)', 'short' => 'MEDEVAC'],
        'sigint' => ['label' => 'SIGINT et détections RF', 'short' => 'SIGINT'],
        'photo' => ['label' => 'Photos et notes de reconnaissance', 'short' => 'Photos'],
        'poi' => ['label' => 'Points d’intérêt signalés', 'short' => 'POI'],
        'ping' => ['label' => 'Pings carte', 'short' => 'Pings'],
        'qrf' => ['label' => 'Demandes de QRF', 'short' => 'QRF'],
        'zone' => ['label' => 'Événements de zone', 'short' => 'Zones'],
        'perte' => ['label' => 'Pertes signalées (KIA / blessés)', 'short' => 'Pertes'],
        'alerte' => ['label' => 'Alertes de la messagerie (SALUTE, TIC…)', 'short' => 'SALUTE'],
    ];

    public const PERIODS = ['24h' => '24 h', '7j' => '7 jours', '30j' => '30 jours', 'tout' => 'Tout'];

    /**
     * Sources : table, catégorie, colonnes d'identité, horodatage, sous-type (infobulle), colonnes lues pour le fil.
     * « callsign » peut être une liste : la première valeur non vide l'emporte (ex. pertes : déclarant puis victime).
     */
    private const SOURCES = [
        'atak_tactical_reports' => ['cat' => 'cr', 'label' => 'Compte rendu', 'time' => 'created_at', 'user' => 'submitter_user_id', 'steam' => 'submitter_steam_id', 'callsign' => ['submitter_callsign'], 'sub' => 'report_type', 'not_null' => ['deleted_at'],
            'cols' => ['report_type', 'report_number', 'priority', 'summary', 'details', 'grid_reference', 'pos_x', 'pos_y', 'mission_key', 'status', 'attachment_count']],
        'sse_field_notes' => ['cat' => 'fiche', 'label' => 'Fiche SSE', 'time' => 'created_at', 'user' => 'author_user_id', 'steam' => 'author_steam_id', 'callsign' => ['author_label'], 'sub' => 'note_kind', 'where' => "status <> 'brouillon'",
            'cols' => ['reference_code', 'note_kind', 'title', 'body', 'urgency', 'grid_reference', 'place_label', 'pos_x', 'pos_y', 'status', 'triaged_at']],
        'atak_nine_line' => ['cat' => 'medevac', 'label' => '9-line', 'time' => 'created_at', 'callsign' => ['author'], 'sub' => 'mission_kind',
            'cols' => ['mission_kind', 'status', 'line1', 'line2', 'line3', 'line4', 'line5', 'line6', 'line7', 'line8', 'line9']],
        'atak_medevac_requests' => ['cat' => 'medevac', 'label' => 'Demande MEDEVAC', 'time' => 'requested_at', 'user' => 'requested_by_user_id', 'callsign' => ['requested_by_callsign'], 'sub_const' => 'MEDEVAC',
            'cols' => ['medevac_number', 'priority', 'pickup_grid', 'pickup_pos_x', 'pickup_pos_y', 'total_patients', 'status', 'remarks']],
        'atak_sigint_reports' => ['cat' => 'sigint', 'label' => 'Relèvement SIGINT', 'time' => 'created_at', 'callsign' => ['call_sign'], 'sub_const' => 'SIGINT',
            'cols' => ['bearing', 'pos_x', 'pos_y']],
        'atak_rf_hits' => ['cat' => 'sigint', 'label' => 'Détection RF', 'time' => 'created_at', 'callsign' => ['sensor_callsign'], 'sub_const' => 'RF',
            'cols' => ['label', 'band', 'signal_dbm', 'emitter_uid', 'pos_x', 'pos_y']],
        'atak_intel_photos' => ['cat' => 'photo', 'label' => 'Photo renseignement', 'time' => 'created_at', 'callsign' => ['author'], 'sub_const' => 'Photo ATAK',
            'cols' => ['filename', 'path', 'pos_x', 'pos_y']],
        'recon_images' => ['cat' => 'photo', 'label' => 'Image de reconnaissance', 'time' => 'created_at', 'callsign' => ['author_callsign'], 'sub_const' => 'Image recon', 'not_null' => ['deleted_at'],
            'cols' => ['title', 'caption', 'image_path', 'thumb_path', 'grid_ref', 'pos_x', 'pos_y', 'mission_id', 'is_blurred']],
        'sse_field_photos' => ['cat' => 'photo', 'label' => 'Photo SSE', 'time' => 'created_at', 'callsign' => ['author_callsign'], 'sub_const' => 'Photo SSE',
            'cols' => ['photo_type', 'caption', 'image_path', 'grid_reference', 'target_ref', 'pos_x', 'pos_y']],
        'recon_notes' => ['cat' => 'photo', 'label' => 'Note de reconnaissance', 'time' => 'created_at', 'steam' => 'author_uid', 'callsign' => ['author'], 'sub_const' => 'Note recon',
            'cols' => ['text', 'tag', 'note_type', 'confidence', 'pos_x', 'pos_y', 'mission_id']],
        'atak_poi' => ['cat' => 'poi', 'label' => 'POI', 'time' => 'created_at', 'user' => 'reported_by_user_id', 'callsign' => ['reported_by_callsign'], 'sub' => 'category', 'not_null' => ['deleted_at'],
            'cols' => ['poi_name', 'poi_code', 'category', 'affiliation', 'threat_level', 'grid_reference', 'pos_x', 'pos_y', 'description']],
        'atak_pings' => ['cat' => 'ping', 'label' => 'Ping', 'time' => 'created_at', 'callsign' => ['author'],
            'cols' => ['message', 'pos_x', 'pos_y']],
        'atak_qrf_requests' => ['cat' => 'qrf', 'label' => 'Demande de QRF', 'time' => 'requested_at', 'user' => 'requesting_user_id', 'steam' => 'requesting_steam_id', 'callsign' => ['requesting_callsign'], 'sub' => 'priority',
            'cols' => ['qrf_number', 'priority', 'threat_type', 'grid_reference', 'location_description', 'contact_pos_x', 'contact_pos_y', 'status']],
        'atak_zone_events' => ['cat' => 'zone', 'label' => 'Événement de zone', 'time' => 'reported_at', 'user' => 'reported_by_user_id', 'callsign' => ['reported_by_callsign'], 'sub' => 'event_type',
            'cols' => ['event_type', 'event_severity', 'event_description', 'event_pos_x', 'event_pos_y']],
        'atak_casualties' => ['cat' => 'perte', 'label' => 'Perte signalée', 'time' => 'killed_at', 'callsign' => ['reported_by', 'call_sign'], 'sub' => 'kind',
            'cols' => ['call_sign', 'kind', 'pos_x', 'pos_y', 'mission_key', 'reported_by']],
        'atak_chat_messages' => ['cat' => 'alerte', 'label' => 'Alerte messagerie', 'time' => 'created_at', 'callsign' => ['author'], 'sub_alert' => true, 'where' => "body LIKE '%ALERTE TACTIQUE|%'",
            'cols' => ['body', 'source', 'channel_key']],
    ];

    /** @var array<string, array<string, true>>|null */
    private ?array $columns = null;

    /** @var array<int, IntelPlayerIdentity> */
    private array $identities = [];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public static function normalizePeriod(string $period): string
    {
        return isset(self::PERIODS[$period]) ? $period : '30j';
    }

    /** Borne basse de la période (null : tout). */
    public static function periodStart(string $period, ?int $now = null): ?string
    {
        $now ??= time();
        $sec = match ($period) {
            '24h' => 86400,
            '7j' => 7 * 86400,
            '30j' => 30 * 86400,
            default => null,
        };

        return $sec === null ? null : date('Y-m-d H:i:s', $now - $sec);
    }

    // ---------------------------------------------------------------- identité

    public function identity(int $tenantId): IntelPlayerIdentity
    {
        if (isset($this->identities[$tenantId])) {
            return $this->identities[$tenantId];
        }
        $users = $this->rows('SELECT id, display_name, callsign, steam_id FROM users WHERE tenant_id = ?', [$tenantId])
            ?: $this->rows('SELECT id, display_name, callsign FROM users WHERE tenant_id = ?', [$tenantId]);
        $profiles = $this->rows('SELECT steam_id, user_id, callsign FROM operator_game_profiles WHERE tenant_id = ?', [$tenantId]);
        $sessions = $this->rows('SELECT DISTINCT user_id, steam_id FROM game_sessions WHERE tenant_id = ? AND steam_id IS NOT NULL', [$tenantId]);

        return $this->identities[$tenantId] = new IntelPlayerIdentity($users, $profiles, $sessions);
    }

    /** Clé joueur d'un émetteur (pour relier une remontée à la vue « Par joueur »). */
    public function keyFor(int $tenantId, mixed $userId, mixed $steam, mixed $callsign): string
    {
        return $this->identity($tenantId)->resolve($userId, $steam, $callsign);
    }

    // ---------------------------------------------------------------- tableau par joueur

    /**
     * Une ligne par joueur : identité, compteurs par catégorie (avec détail par sous-type), dernière remontée.
     *
     * @return array{players: list<array<string, mixed>>, sources: array<string, bool>}
     */
    public function players(int $tenantId, string $period = '30j'): array
    {
        return $this->playersBetween($tenantId, self::periodStart(self::normalizePeriod($period)), null);
    }

    /**
     * Comme {@see players()}, sur une plage de dates libre [$since, $until[ (bornes facultatives).
     *
     * @return array{players: list<array<string, mixed>>, sources: array<string, bool>}
     */
    public function playersBetween(int $tenantId, ?string $since, ?string $until): array
    {
        $identity = $this->identity($tenantId);
        $players = [];
        $sources = [];
        foreach ($this->groups($tenantId, $since, $until) as $table => $groups) {
            $sources[$table] = $groups !== null;
            $def = self::SOURCES[$table];
            foreach ($groups ?? [] as $g) {
                $key = $identity->resolve($g['u'], $g['s'], $g['c']);
                $players[$key] ??= self::emptySummary();
                self::accumulate($players[$key], (string) $def['cat'], $g);
            }
        }

        $out = [];
        foreach ($players as $key => $p) {
            $out[] = $this->decorate($identity, (string) $key, $p);
        }

        return ['players' => $out, 'sources' => $sources];
    }

    /** @return array<string, mixed> */
    public static function emptySummary(): array
    {
        return ['counts' => array_fill_keys(array_keys(self::CATEGORIES), 0), 'subs' => [], 'total' => 0, 'last' => '', 'callsigns' => [], 'steams' => []];
    }

    /**
     * Ajoute un regroupement brut (u, s, c, sub, n, last) au résumé d'un joueur.
     *
     * @param array<string, mixed> $p
     * @param array<string, mixed> $g
     */
    public static function accumulate(array &$p, string $cat, array $g): void
    {
        $n = (int) ($g['n'] ?? 0);
        $p['counts'][$cat] = ($p['counts'][$cat] ?? 0) + $n;
        $p['total'] += $n;
        $sub = trim((string) ($g['sub'] ?? ''));
        if ($sub !== '') {
            $p['subs'][$cat][$sub] = ($p['subs'][$cat][$sub] ?? 0) + $n;
        }
        if ((string) ($g['last'] ?? '') > $p['last']) {
            $p['last'] = (string) $g['last'];
        }
        $cs = trim((string) ($g['c'] ?? ''));
        if ($cs !== '') {
            $p['callsigns'][$cs] = ($p['callsigns'][$cs] ?? 0) + $n;
        }
        if (($st = IntelPlayerIdentity::normalizeSteam($g['s'] ?? null)) !== null) {
            $p['steams'][$st] = true;
        }
    }

    /**
     * @param array<string, mixed> $p
     * @return array<string, mixed>
     */
    private function decorate(IntelPlayerIdentity $identity, string $key, array $p): array
    {
        $card = $identity->card($key);
        arsort($p['callsigns']);
        $seen = array_keys($p['callsigns']);
        if ($card['callsign'] === '' && $seen !== []) {
            $card['callsign'] = (string) $seen[0];
        }
        if ($card['steam'] === '' && $p['steams'] !== []) {
            $card['steam'] = (string) array_key_first($p['steams']);
        }
        if ($key === IntelPlayerIdentity::UNKNOWN) {
            $card['callsign'] = 'Émetteur non identifié';
        }

        return $card + [
            'counts' => $p['counts'],
            'subs' => $p['subs'],
            'total' => $p['total'],
            'last' => $p['last'],
            'callsigns_seen' => array_slice(array_map('strval', $seen), 0, 8),
        ];
    }

    /**
     * Regroupements bruts (émetteur, sous-type) par table ; null si la table n'existe pas.
     *
     * @return array<string, list<array<string, mixed>>|null>
     */
    private function groups(int $tenantId, ?string $since, ?string $until = null): array
    {
        $out = [];
        foreach (self::SOURCES as $table => $def) {
            $sql = $this->identitySql($table, $def);
            if ($sql === null) {
                $out[$table] = null;
                continue;
            }
            [$where, $args] = $this->baseWhere($table, $def, $tenantId, $since, $until);
            $sub = $this->subSql($table, $def);
            try {
                $st = $this->pdo()->prepare(
                    "SELECT {$sql['u']} AS u, {$sql['s']} AS s, {$sql['c']} AS c, {$sub} AS sub, COUNT(*) AS n, MAX({$def['time']}) AS last
                     FROM {$table} WHERE {$where} GROUP BY u, s, c, sub"
                );
                $st->execute($args);
                $out[$table] = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable) {
                $out[$table] = null;
            }
        }

        return $out;
    }

    // ---------------------------------------------------------------- fil d'un joueur

    /**
     * Fiche du joueur et fil chronologique (le plus récent d'abord) de tout ce qu'il a remonté.
     *
     * @return array{player: array<string, mixed>|null, items: list<array<string, mixed>>, truncated: bool}
     */
    public function timeline(int $tenantId, string $key, string $period = 'tout', int $perSource = 400): array
    {
        if (!IntelPlayerIdentity::validKey($key)) {
            return ['player' => null, 'items' => [], 'truncated' => false];
        }
        $since = self::periodStart(self::normalizePeriod($period));
        $identity = $this->identity($tenantId);
        $items = [];
        $truncated = false;
        $summary = null;
        foreach ($this->groups($tenantId, $since) as $table => $groups) {
            if ($groups === null) {
                continue;
            }
            $def = self::SOURCES[$table];
            $mine = array_values(array_filter($groups, static fn (array $g): bool => $identity->resolve($g['u'], $g['s'], $g['c']) === $key));
            if ($mine === []) {
                continue;
            }
            foreach ($mine as $g) {
                $summary ??= self::emptySummary();
                self::accumulate($summary, (string) $def['cat'], $g);
            }
            $rows = $this->rowsFor($table, $def, $tenantId, $since, $mine, $perSource);
            if (count($rows) >= $perSource) {
                $truncated = true;
            }
            foreach ($rows as $r) {
                if ($identity->resolve($r['_u'], $r['_s'], $r['_c']) !== $key) {
                    continue;
                }
                $items[] = $this->present($table, $def, $r);
            }
        }
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']) ?: strcmp($b['table'], $a['table']) ?: $b['id'] <=> $a['id']);
        $player = $summary !== null ? $this->decorate($identity, $key, $summary) : null;
        if ($player === null && $key !== IntelPlayerIdentity::UNKNOWN) {
            // Joueur connu sans remontée sur la période : on affiche quand même sa fiche.
            $player = $this->decorate($identity, $key, self::emptySummary());
        }

        return ['player' => $player, 'items' => $items, 'truncated' => $truncated];
    }

    /**
     * Lignes d'une table émises par les identités brutes données (re-filtrées ensuite par clé).
     *
     * @param array<string, mixed> $def
     * @param list<array<string, mixed>> $tuples
     * @return list<array<string, mixed>>
     */
    private function rowsFor(string $table, array $def, int $tenantId, ?string $since, array $tuples, int $limit): array
    {
        $sql = $this->identitySql($table, $def);
        if ($sql === null) {
            return [];
        }
        [$where, $args] = $this->baseWhere($table, $def, $tenantId, $since);
        $or = [];
        $blank = static fn (mixed $v): bool => $v === null || trim((string) $v) === '' || (string) $v === '0';
        $anonymous = false;
        foreach ($tuples as $t) {
            if ($blank($t['u'] ?? null) && $blank($t['s'] ?? null) && $blank($t['c'] ?? null)) {
                $anonymous = true;
            }
        }
        foreach (['u', 's', 'c'] as $k) {
            $vals = [];
            foreach ($tuples as $t) {
                if (!$blank($t[$k] ?? null)) {
                    $vals[(string) $t[$k]] = true;
                }
            }
            if ($vals !== [] && $sql[$k] !== 'NULL') {
                $or[] = $sql[$k] . ' IN (' . implode(',', array_fill(0, count($vals), '?')) . ')';
                array_push($args, ...array_keys($vals));
            }
        }
        if ($anonymous) {
            $or[] = "(COALESCE({$sql['c']}, '') = '')";
        }
        if ($or === []) {
            return [];
        }
        $cols = array_values(array_filter($def['cols'], fn (string $c): bool => $this->hasColumn($table, $c)));
        $select = implode(', ', array_merge(
            ['id', "{$def['time']} AS at", "{$sql['u']} AS _u", "{$sql['s']} AS _s", "{$sql['c']} AS _c"],
            $cols
        ));
        try {
            $st = $this->pdo()->prepare("SELECT {$select} FROM {$table} WHERE {$where} AND (" . implode(' OR ', $or) . ") ORDER BY {$def['time']} DESC, id DESC LIMIT " . max(1, $limit));
            $st->execute($args);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Une ligne source mise en forme pour le fil.
     *
     * @param array<string, mixed> $def
     * @param array<string, mixed> $r
     * @return array<string, mixed>
     */
    private function present(string $table, array $def, array $r): array
    {
        $v = static fn (string $k): string => trim((string) ($r[$k] ?? ''));
        $join = static fn (array $parts): string => implode(' · ', array_values(array_filter(array_map('trim', array_map('strval', $parts)), static fn (string $s): bool => $s !== '')));
        $clip = static fn (string $s, int $n = 220): string => mb_strlen($s) > $n ? mb_substr($s, 0, $n - 1) . '…' : $s;
        $xy = static function (mixed $x, mixed $y): string {
            if ($x === null || $y === null || $x === '' || $y === '' || ((float) $x === 0.0 && (float) $y === 0.0)) {
                return '';
            }

            return 'X ' . (int) round((float) $x) . ' / Y ' . (int) round((float) $y);
        };
        $media = static function (string $path): ?string {
            if ($path === '' || !function_exists('user_media_public_url')) {
                return null;
            }

            return user_media_public_url($path);
        };
        $id = (int) $r['id'];
        $item = [
            'table' => $table,
            'id' => $id,
            'cat' => $def['cat'],
            'cat_label' => self::CATEGORIES[$def['cat']]['short'],
            'label' => $def['label'],
            'at' => (string) $r['at'],
            'callsign' => (string) ($r['_c'] ?? ''),
            'title' => '',
            'summary' => '',
            'grid' => '',
            'position' => $xy($r['pos_x'] ?? null, $r['pos_y'] ?? null),
            'mission' => $v('mission_key') !== '' ? $v('mission_key') : $v('mission_id'),
            'status' => $v('status'),
            'image' => null,
            'href' => null,
            'tags' => [],
        ];
        switch ($table) {
            case 'atak_tactical_reports':
                $item['title'] = $join([$v('report_type'), $v('report_number') !== '' ? $v('report_number') : 'CR-' . $id]);
                $item['summary'] = $clip($v('summary') !== '' ? $v('summary') : $v('details'));
                $item['grid'] = $v('grid_reference');
                $item['tags'] = array_filter([$v('priority') !== '' && $v('priority') !== 'ROUTINE' ? $v('priority') : '', (int) ($r['attachment_count'] ?? 0) > 0 ? (int) $r['attachment_count'] . ' pièce(s) jointe(s)' : '']);
                $item['href'] = 'back-office/remontees/cr/' . $id;
                break;
            case 'sse_field_notes':
                $item['title'] = $join([$v('note_kind'), $v('reference_code')]);
                $item['summary'] = $clip($v('title') !== '' ? $v('title') . ' — ' . $v('body') : $v('body'));
                $item['grid'] = $join([$v('grid_reference'), $v('place_label')]);
                $item['tags'] = array_filter([in_array($v('urgency'), ['urgent', 'critique'], true) ? ucfirst($v('urgency')) : '']);
                $item['href'] = 'back-office/remontees/fiche/' . $id;
                break;
            case 'atak_nine_line':
                $item['title'] = '9-line ' . strtoupper($v('mission_kind') !== '' ? $v('mission_kind') : 'CAS');
                $lines = [];
                for ($i = 1; $i <= 9; $i++) {
                    if ($v('line' . $i) !== '') {
                        $lines[] = $i . ' : ' . $v('line' . $i);
                    }
                }
                $item['summary'] = $clip(implode(' · ', $lines));
                break;
            case 'atak_medevac_requests':
                $item['title'] = $join(['MEDEVAC', $v('medevac_number')]);
                $item['summary'] = $clip($join([$v('total_patients') !== '' ? $v('total_patients') . ' blessé(s)' : '', $v('remarks')]));
                $item['grid'] = $v('pickup_grid');
                $item['position'] = $xy($r['pickup_pos_x'] ?? null, $r['pickup_pos_y'] ?? null);
                $item['tags'] = array_filter([$v('priority')]);
                break;
            case 'atak_sigint_reports':
                $item['title'] = 'Relèvement SIGINT';
                $item['summary'] = $v('bearing') !== '' ? 'Azimut ' . (int) round((float) $v('bearing')) . '°' : '';
                break;
            case 'atak_rf_hits':
                $item['title'] = $join(['Détection RF', $v('label')]);
                $item['summary'] = $join([$v('band'), $v('signal_dbm') !== '' ? $v('signal_dbm') . ' dBm' : '', $v('emitter_uid') !== '' ? 'émetteur ' . $v('emitter_uid') : '']);
                break;
            case 'atak_intel_photos':
                $item['title'] = 'Photo de renseignement';
                $item['summary'] = $v('filename');
                $item['image'] = $media($v('path'));
                break;
            case 'recon_images':
                $item['title'] = $v('title') !== '' ? $v('title') : 'Image de reconnaissance';
                $item['summary'] = $clip($v('caption'));
                $item['grid'] = $v('grid_ref');
                $item['image'] = $media($v('thumb_path') !== '' ? $v('thumb_path') : $v('image_path'));
                $item['blurred'] = !empty($r['is_blurred']);
                break;
            case 'sse_field_photos':
                $item['title'] = $join(['Photo SSE', $v('photo_type')]);
                $item['summary'] = $clip($join([$v('caption'), $v('target_ref')]));
                $item['grid'] = $v('grid_reference');
                $item['image'] = $media($v('image_path'));
                break;
            case 'recon_notes':
                $item['title'] = $join(['Note de reconnaissance', $v('tag')]);
                $item['summary'] = $clip($join([$v('text'), $v('confidence') !== '' ? 'confiance ' . $v('confidence') : '']));
                break;
            case 'atak_poi':
                $item['title'] = $join([$v('poi_name') !== '' ? $v('poi_name') : 'POI', $v('poi_code')]);
                $item['summary'] = $clip($join([$v('category'), $v('affiliation'), $v('description')]));
                $item['grid'] = $v('grid_reference');
                $item['tags'] = array_filter([in_array($v('threat_level'), ['HIGH', 'CRITICAL'], true) ? 'Menace ' . $v('threat_level') : '']);
                break;
            case 'atak_pings':
                $item['title'] = 'Ping';
                $item['summary'] = $clip($v('message'));
                break;
            case 'atak_qrf_requests':
                $item['title'] = $join(['QRF', $v('qrf_number')]);
                $item['summary'] = $clip($join([$v('threat_type'), $v('location_description')]));
                $item['grid'] = $v('grid_reference');
                $item['position'] = $xy($r['contact_pos_x'] ?? null, $r['contact_pos_y'] ?? null);
                $item['tags'] = array_filter([$v('priority')]);
                break;
            case 'atak_zone_events':
                $item['title'] = $join(['Zone', $v('event_type')]);
                $item['summary'] = $clip($v('event_description'));
                $item['position'] = $xy($r['event_pos_x'] ?? null, $r['event_pos_y'] ?? null);
                $item['tags'] = array_filter([$v('event_severity')]);
                break;
            case 'atak_casualties':
                $item['title'] = $join([strtoupper($v('kind') !== '' ? $v('kind') : 'KIA'), $v('call_sign')]);
                $item['summary'] = $v('reported_by') !== '' && $v('reported_by') !== $v('call_sign') ? 'Signalé par ' . $v('reported_by') : 'Perte déclarée';
                break;
            case 'atak_chat_messages':
                $parsed = TacticalAlertParser::parse($v('body'));
                $kind = (string) ($parsed['kind'] ?? '');
                $item['title'] = $kind !== '' ? TacticalAlertParser::kindLabelFr($kind) : 'Alerte tactique';
                $item['summary'] = $clip((string) ($parsed['summary'] ?? $v('body')));
                $item['grid'] = (string) ($parsed['grid'] ?? '');
                $item['position'] = $xy($parsed['pos_x'] ?? null, $parsed['pos_y'] ?? null);
                $item['tags'] = array_filter([$v('source') === 'game' ? 'en jeu' : ($v('source') !== '' ? $v('source') : '')]);
                break;
        }
        $item['tags'] = array_values(array_map('strval', $item['tags']));

        return $item;
    }

    // ---------------------------------------------------------------- SQL

    /**
     * Expressions SQL de l'émetteur (u : compte, s : Steam, c : indicatif) ; null si la table ou une colonne manque.
     *
     * @param array<string, mixed> $def
     * @return array{u: string, s: string, c: string}|null
     */
    private function identitySql(string $table, array $def): ?array
    {
        if (!$this->hasTable($table) || !$this->hasColumn($table, (string) $def['time']) || !$this->hasColumn($table, 'tenant_id')) {
            return null;
        }
        $u = isset($def['user']) && $this->hasColumn($table, $def['user']) ? $def['user'] : 'NULL';
        $s = isset($def['steam']) && $this->hasColumn($table, $def['steam']) ? $def['steam'] : 'NULL';
        $cs = array_values(array_filter((array) ($def['callsign'] ?? []), fn (string $c): bool => $this->hasColumn($table, $c)));
        if ($cs === [] && $u === 'NULL' && $s === 'NULL') {
            return null;
        }
        $c = match (count($cs)) {
            0 => 'NULL',
            1 => $cs[0],
            default => 'COALESCE(' . implode(', ', array_map(static fn (string $x): string => "NULLIF(TRIM({$x}), '')", $cs)) . ')',
        };

        return ['u' => $u, 's' => $s, 'c' => $c];
    }

    /** @param array<string, mixed> $def */
    private function subSql(string $table, array $def): string
    {
        if (!empty($def['sub_alert'])) {
            return "UPPER(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING(body, LOCATE('ALERTE TACTIQUE|', body)), '|', 2), '|', -1))";
        }
        if (isset($def['sub']) && $this->hasColumn($table, $def['sub'])) {
            return $def['sub'];
        }
        if (isset($def['sub_const'])) {
            return $this->pdo()->quote((string) $def['sub_const']);
        }

        return "''";
    }

    /**
     * @param array<string, mixed> $def
     * @return array{0: string, 1: list<mixed>}
     */
    private function baseWhere(string $table, array $def, int $tenantId, ?string $since, ?string $until = null): array
    {
        $where = ['tenant_id = ?'];
        $args = [$tenantId];
        if ($since !== null) {
            $where[] = "{$def['time']} >= ?";
            $args[] = $since;
        }
        if ($until !== null) {
            $where[] = "{$def['time']} < ?";
            $args[] = $until;
        }
        foreach ((array) ($def['not_null'] ?? []) as $col) {
            if ($this->hasColumn($table, $col)) {
                $where[] = "{$col} IS NULL";
            }
        }
        if (!empty($def['where'])) {
            $where[] = (string) $def['where'];
        }

        return [implode(' AND ', $where), $args];
    }

    private function hasTable(string $table): bool
    {
        return isset($this->columns()[$table]);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return isset($this->columns()[$table][$column]);
    }

    /** @return array<string, array<string, true>> */
    private function columns(): array
    {
        if ($this->columns !== null) {
            return $this->columns;
        }
        $this->columns = [];
        $tables = array_keys(self::SOURCES);
        try {
            $st = $this->pdo()->prepare(
                'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('
                . implode(',', array_fill(0, count($tables), '?')) . ')'
            );
            $st->execute($tables);
            foreach ($st->fetchAll(PDO::FETCH_NUM) ?: [] as [$t, $c]) {
                $this->columns[(string) $t][(string) $c] = true;
            }
        } catch (\Throwable) {
        }

        return $this->columns;
    }

    /**
     * @param list<mixed> $args
     * @return list<array<string, mixed>>
     */
    private function rows(string $sql, array $args): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** Tables lues, pour l'aide de la page. @return array<string, string> table => libellé */
    public static function sourceLabels(): array
    {
        return array_map(static fn (array $d): string => $d['label'], self::SOURCES);
    }

    /** @return array<string, string> table => catégorie */
    public static function sourceCategories(): array
    {
        return array_map(static fn (array $d): string => $d['cat'], self::SOURCES);
    }
}
