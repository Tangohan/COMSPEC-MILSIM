<?php

declare(strict_types=1);

namespace App\Services\Tactical;

use App\Support\Utf8Text;

/**
 * Journal d’activité ATAK (fichier JSON par théâtre).
 * Fenêtre active pour le panneau latéral ; historique archivé conservé pour la page dédiée.
 * Compat lecture : entrées sans `archived_at` = non archivées.
 */
final class AtakActivityLogService
{
    /** Capacité totale (actifs + archivés) par fichier théâtre. */
    private const MAX_EVENTS = 5000;
    private const INIT_THROTTLE_SEC = 90;
    private const SESSION_TTL_SEC = 86400;
    /** Présence des visiteurs web sur la Tacmap (TTL court). */
    private const WEB_PRESENCE_TTL_SEC = 90;

    /** Présence des clients téléphone / ATAK pendant le briefing. */
    private const BRIEFING_PRESENCE_TTL_SEC = 75;

    /** Dernière détection mods compagnons (cTab / ATAK Enhanced) — fenêtre « présent ». */
    private const MOD_DETECT_PRESENT_TTL_SEC = 600;

    /** Types internes → libellés métier (FR). */
    public const TYPE_CLIENT_INIT = 'client_init';
    public const TYPE_CALLSIGN_CHANGE = 'callsign_change';
    public const TYPE_POSITION = 'position';
    public const TYPE_CHAT = 'chat';
    public const TYPE_PING = 'ping';
    public const TYPE_MARKER = 'marker';
    public const TYPE_DESIGNATOR = 'designator';
    public const TYPE_SIGINT = 'sigint';
    public const TYPE_FLIGHT = 'flight';
    public const TYPE_NINE_LINE = 'nine_line';
    public const TYPE_LASER = 'laser';
    public const TYPE_INTEL = 'intel';
    /** Ordre C2 (émission web ou réception jeu). */
    public const TYPE_ORDER = 'order';
    /** Tentative de liaison / clé d’accès (succès ou échec). */
    public const TYPE_AUTH = 'auth';
    /** Connexion téléphone (QR / code court). */
    public const TYPE_PHONE = 'phone';
    /** Joueur quitte Arma / la mission — présence ATAK hors ligne. */
    public const TYPE_DISCONNECT = 'disconnect';
    /** Rapport tactique structuré (observation, situation, SALUTE, contact). */
    public const TYPE_TACTICAL_REPORT = 'tactical_report';
    /** Entrée manuelle du journal d’opérations (TOC). */
    public const TYPE_TOC_NOTE = 'toc_note';
    /** Demande MEDEVAC 9-line. */
    public const TYPE_MEDEVAC = 'medevac';
    /** Charge à retardement ACE (minuterie posée sur le terrain). */
    public const TYPE_EXPLOSIVE_TIMER = 'explosive_timer';
    /** Équipe de feu (création, attribution, dissolution, couleur). */
    public const TYPE_FIRE_TEAM = 'fire_team';

    /** Carte « virtuelle » pour les événements d’auth / téléphone non liés à un théâtre. */
    public const AUTH_MAP_ID = 1;

    /** Types regroupés pour filtres UI (clé filtre → types techniques). */
    public const FILTER_GROUPS = [
        'connexion' => [self::TYPE_CLIENT_INIT, self::TYPE_DISCONNECT, self::TYPE_AUTH, self::TYPE_PHONE],
        'indicatif' => [self::TYPE_CALLSIGN_CHANGE],
        'position' => [self::TYPE_POSITION],
        'tchat' => [self::TYPE_CHAT],
        'ping' => [self::TYPE_PING],
        'tactique' => [
            self::TYPE_MARKER,
            self::TYPE_DESIGNATOR,
            self::TYPE_SIGINT,
            self::TYPE_FLIGHT,
            self::TYPE_NINE_LINE,
            self::TYPE_LASER,
            self::TYPE_INTEL,
            self::TYPE_ORDER,
            self::TYPE_TACTICAL_ALERT,
            self::TYPE_TACTICAL_REPORT,
            self::TYPE_TOC_NOTE,
            self::TYPE_MEDEVAC,
            self::TYPE_EXPLOSIVE_TIMER,
            self::TYPE_FIRE_TEAM,
        ],
    ];

    /**
     * Enregistre une entrée générique (libellé déjà lisible).
     *
     * @param array<string, mixed> $meta
     */
    public function record(
        int $tenantId,
        int $mapId,
        string $type,
        string $label,
        ?string $actor = null,
        array $meta = []
    ): void {
        if ($tenantId < 1 || $mapId < 1 || $label === '') {
            return;
        }
        $label = Utf8Text::normalize($label);
        if ($actor !== null && $actor !== '') {
            $actor = Utf8Text::normalize($actor);
        }
        $safeMeta = $this->sanitizeMeta($meta);
        $this->mutate($tenantId, $mapId, function (array &$data) use ($type, $label, $actor, $safeMeta): void {
            $this->appendEvent($data, $type, $label, $actor, $safeMeta);
        });
    }

    /**
     * Métadonnées sûres pour le journal (pas de secrets). Valeurs tronquées.
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public function sanitizeMeta(array $meta): array
    {
        return $this->sanitizeMetaRecursive($meta, 0);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function sanitizeMetaRecursive(array $meta, int $depth): array
    {
        if ($depth > 3) {
            return [];
        }
        $blocked = [
            'api_key', 'apikey', 'apiKey', 'token', 'session_token', 'game_session',
            'password', 'secret', 'authorization', 'cookie', 'csrf', 'csrf_token',
        ];
        $out = [];
        $i = 0;
        foreach ($meta as $key => $value) {
            if ($i >= 48) {
                break;
            }
            $k = is_string($key) ? $key : (string) $key;
            if ($k === '' || strlen($k) > 64) {
                continue;
            }
            $lk = strtolower($k);
            $blockedHit = false;
            foreach ($blocked as $b) {
                if ($lk === strtolower($b) || str_contains($lk, 'secret') || str_contains($lk, 'password') || str_contains($lk, 'api_key')) {
                    $blockedHit = true;
                    break;
                }
            }
            if ($blockedHit) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
                if (is_float($value) && !is_finite($value)) {
                    continue;
                }
                $out[$k] = $value;
                $i++;
                continue;
            }
            if (is_string($value)) {
                $v = trim($value);
                if ($v === '') {
                    continue;
                }
                if (mb_strlen($v) > 400) {
                    $v = mb_substr($v, 0, 400) . '…';
                }
                $out[$k] = $v;
                $i++;
                continue;
            }
            if (is_array($value)) {
                $nested = $this->sanitizeMetaRecursive($value, $depth + 1);
                if ($nested !== []) {
                    $out[$k] = $nested;
                    $i++;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $meta
     */
    private function appendEvent(array &$data, string $type, string $label, ?string $actor = null, array $meta = []): void
    {
        $label = Utf8Text::normalize($label);
        if ($actor !== null && $actor !== '') {
            $actor = Utf8Text::normalize($actor);
        }
        $id = (int) ($data['next_id'] ?? 1);
        $data['next_id'] = $id + 1;
        $event = [
            'id' => $id,
            'type' => strtolower($type),
            'label' => $label,
            'actor' => $actor !== null && $actor !== '' ? $actor : null,
            'at' => date('c'),
        ];
        $safe = $meta !== [] ? $this->sanitizeMeta($meta) : [];
        if ($safe !== []) {
            $event['meta'] = $safe;
        }
        $events = $data['events'] ?? [];
        if (!is_array($events)) {
            $events = [];
        }
        $events[] = $event;
        $data['events'] = $this->trimEvents($events);
    }

    /**
     * Conserve les plus récents ; privilégie la purge des archivés les plus anciens.
     *
     * @param list<mixed> $events
     * @return list<array<string, mixed>>
     */
    private function trimEvents(array $events): array
    {
        $normalized = [];
        foreach ($events as $e) {
            if (is_array($e)) {
                $normalized[] = $e;
            }
        }
        if (count($normalized) <= self::MAX_EVENTS) {
            return $normalized;
        }
        $active = [];
        $archived = [];
        foreach ($normalized as $e) {
            if ($this->isArchived($e)) {
                $archived[] = $e;
            } else {
                $active[] = $e;
            }
        }
        $overflow = count($normalized) - self::MAX_EVENTS;
        if ($overflow > 0 && $archived !== []) {
            $drop = min($overflow, count($archived));
            $archived = array_slice($archived, $drop);
            $overflow -= $drop;
        }
        if ($overflow > 0 && $active !== []) {
            $active = array_slice($active, $overflow);
        }
        $merged = array_merge($archived, $active);
        usort($merged, static function (array $a, array $b): int {
            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return $merged;
    }

    /** @param array<string, mixed> $e */
    private function isArchived(array $e): bool
    {
        $raw = $e['archived_at'] ?? null;

        return is_string($raw) && $raw !== '';
    }

    /**
     * Archive tous les événements actifs (ne supprime pas — journal d’audit).
     * Retourne le nombre d’entrées marquées.
     */
    public function archiveAll(int $tenantId, int $mapId): int
    {
        if ($tenantId < 1 || $mapId < 1) {
            return 0;
        }
        $count = 0;
        $now = date('c');
        $this->mutate($tenantId, $mapId, function (array &$data) use ($now, &$count): void {
            $events = $data['events'] ?? [];
            if (!is_array($events)) {
                return;
            }
            foreach ($events as &$e) {
                if (!is_array($e) || $this->isArchived($e)) {
                    continue;
                }
                $e['archived_at'] = $now;
                $count++;
            }
            unset($e);
            $data['events'] = $events;
        });

        return $count;
    }

    /**
     * Tentative de connexion / liaison (code, Steam, clé) — libellé métier uniquement.
     * Ne jamais passer de secrets dans $meta.
     *
     * @param array<string, mixed> $meta
     */
    public function recordAuthAttempt(
        int $tenantId,
        bool $success,
        string $label,
        array $meta = [],
        ?string $actor = null,
        int $mapId = self::AUTH_MAP_ID
    ): void {
        if ($tenantId < 1 || $label === '') {
            return;
        }
        $safeMeta = [];
        foreach (['reason', 'path_hint', 'method'] as $k) {
            if (isset($meta[$k]) && is_string($meta[$k]) && $meta[$k] !== '') {
                $safeMeta[$k] = $meta[$k];
            }
        }
        $safeMeta['ok'] = $success;
        $this->record($tenantId, $mapId > 0 ? $mapId : self::AUTH_MAP_ID, self::TYPE_AUTH, $label, $actor, $safeMeta);
    }

    /**
     * Connexion téléphone réussie (QR scanné ou code saisi) — ouverture réelle des diapos.
     */
    public function recordPhonePaired(int $tenantId, ?string $actor = null): void
    {
        if ($tenantId < 1) {
            return;
        }
        $who = $actor !== null && trim($actor) !== '' ? trim($actor) : null;
        $label = $who !== null
            ? $who . ' a ouvert les diapos'
            : 'Connexion au briefing — téléphone';
        $this->record(
            $tenantId,
            self::AUTH_MAP_ID,
            self::TYPE_PHONE,
            $label,
            $who,
            ['ok' => true]
        );
    }

    /**
     * Première prise de contact / vérification de liaison (throttlée par client).
     *
     * @param array<string, mixed> $meta
     */
    public function recordClientInit(int $tenantId, int $mapId, string $clientKey, ?string $callSign = null, array $meta = []): void
    {
        $clientKey = $this->normalizeKey($clientKey);
        if ($clientKey === '') {
            return;
        }
        $now = time();
        $safeMeta = $this->sanitizeMeta($meta);
        $this->mutate($tenantId, $mapId, function (array &$data) use ($clientKey, $callSign, $now, $safeMeta): void {
            $sessions = $this->pruneSessions($data['sessions'] ?? [], $now);
            $prev = $sessions[$clientKey] ?? null;
            $lastInit = is_array($prev) ? (int) ($prev['last_init_at'] ?? 0) : 0;
            if ($lastInit > 0 && ($now - $lastInit) < self::INIT_THROTTLE_SEC) {
                $data['sessions'] = $sessions;
                return;
            }
            $call = $callSign !== null ? trim($callSign) : '';
            $sessions[$clientKey] = [
                'call_sign' => $call !== '' ? $call : (is_array($prev) ? (string) ($prev['call_sign'] ?? '') : ''),
                'last_init_at' => $now,
                'last_seen_at' => $now,
                'last_position_at' => is_array($prev) ? (int) ($prev['last_position_at'] ?? 0) : 0,
            ];
            $data['sessions'] = $sessions;
            $label = $call !== ''
                ? 'Connexion établie — ' . $call
                : 'Client ATAK initialisé';
            $this->appendEvent($data, self::TYPE_CLIENT_INIT, $label, $call !== '' ? $call : null, $safeMeta);
        });
    }

    /**
     * Déconnexion explicite (sortie Arma / mission) : retire la session client.
     * Retourne l’indicatif résolu (body ou session) pour marquer l’unité hors ligne.
     *
     * @param array<string, mixed> $meta
     */
    public function recordDisconnect(int $tenantId, int $mapId, string $clientKey, ?string $callSign = null, array $meta = []): string
    {
        $clientKey = $this->normalizeKey($clientKey);
        $call = $callSign !== null ? trim($callSign) : '';
        $resolved = $call;
        if ($tenantId < 1 || $mapId < 1) {
            return $resolved;
        }
        $now = time();
        $safeMeta = $this->sanitizeMeta($meta);
        $this->mutate($tenantId, $mapId, function (array &$data) use ($clientKey, $call, $now, &$resolved, $safeMeta): void {
            $sessions = $this->pruneSessions($data['sessions'] ?? [], $now);
            if ($clientKey !== '' && isset($sessions[$clientKey]) && is_array($sessions[$clientKey])) {
                $prevCall = trim((string) ($sessions[$clientKey]['call_sign'] ?? ''));
                if ($resolved === '' && $prevCall !== '') {
                    $resolved = $prevCall;
                }
                unset($sessions[$clientKey]);
            }
            $data['sessions'] = $sessions;
            $label = $resolved !== ''
                ? 'Déconnexion jeu — ' . $resolved
                : 'Déconnexion jeu';
            $eventMeta = $safeMeta;
            if ($resolved !== '' && !isset($eventMeta['call_sign'])) {
                $eventMeta['call_sign'] = $resolved;
            }
            $this->appendEvent($data, self::TYPE_DISCONNECT, $label, $resolved !== '' ? $resolved : null, $eventMeta);
        });

        return $resolved;
    }

    /**
     * Position BFT reçue : journalise connexion / changement d’indicatif uniquement.
     * Les sync / heartbeats ne créent plus d’événements « Position envoyée » (spam journal).
     * La carte Effectifs reste à jour via upsertUnitPosition ; la géo des photos reste
     * sur l’entrée renseignement, sans événement POSITION.
     *
     * @param array<string, mixed> $meta
     */
    public function recordFromPosition(int $tenantId, int $mapId, string $clientKey, string $callSign, bool $isNewUnit, array $meta = []): void
    {
        $clientKey = $this->normalizeKey($clientKey);
        $callSign = trim($callSign);
        if ($callSign === '') {
            $callSign = 'Inconnu';
        }
        $now = time();
        $safeMeta = $this->sanitizeMeta($meta);
        $this->mutate($tenantId, $mapId, function (array &$data) use ($clientKey, $callSign, $isNewUnit, $now, $safeMeta): void {
            $sessions = $this->pruneSessions($data['sessions'] ?? [], $now);
            $prev = ($clientKey !== '' && isset($sessions[$clientKey]) && is_array($sessions[$clientKey]))
                ? $sessions[$clientKey]
                : null;
            $prevCall = is_array($prev) ? trim((string) ($prev['call_sign'] ?? '')) : '';
            $lastInit = is_array($prev) ? (int) ($prev['last_init_at'] ?? 0) : 0;

            if ($prevCall !== '' && strcasecmp($prevCall, $callSign) !== 0) {
                $this->appendEvent($data, self::TYPE_CALLSIGN_CHANGE, 'Indicatif mis à jour — ' . $prevCall . ' → ' . $callSign, $callSign, array_merge($safeMeta, [
                    'from' => $prevCall,
                    'to' => $callSign,
                ]));
                $lastInit = $now;
            } elseif ($prev === null || $lastInit === 0 || ($isNewUnit && ($now - $lastInit) >= self::INIT_THROTTLE_SEC)) {
                $this->appendEvent($data, self::TYPE_CLIENT_INIT, 'Connexion établie — ' . $callSign, $callSign, $safeMeta);
                $lastInit = $now;
            }

            if ($clientKey !== '') {
                $sessions[$clientKey] = [
                    'call_sign' => $callSign,
                    'last_init_at' => $lastInit > 0 ? $lastInit : $now,
                    'last_seen_at' => $now,
                    'last_position_at' => $now,
                ];
            }
            $data['sessions'] = $sessions;
        });
    }

    /**
     * Liste récente pour le panneau (exclut archivés + sync BFT « position »).
     *
     * @return list<array{id: int, type: string, label: string, actor: ?string, at: string, archived?: bool, meta?: array}>
     */
    public function listRecent(int $tenantId, int $mapId, int $limit = 50, ?int $afterId = null, bool $includeArchived = false): array
    {
        $result = $this->listFiltered($tenantId, $mapId, [
            'limit' => $limit,
            'after_id' => $afterId,
            'include_archived' => $includeArchived,
            'archived_only' => false,
            // Heartbeats BFT historiques : hors panneau Activité (filtre « position » page dédiée OK).
            'exclude_types' => [self::TYPE_POSITION],
        ]);

        return $result['events'];
    }

    /**
     * Liste paginée / filtrée pour la page dédiée (dépasse la fenêtre panneau).
     *
     * @param array{
     *   limit?: int,
     *   before_id?: int|null,
     *   after_id?: int|null,
     *   type?: string|list<string>|null,
     *   exclude_types?: list<string>|null,
     *   q?: string|null,
     *   from?: string|null,
     *   to?: string|null,
     *   include_archived?: bool,
     *   archived_only?: bool
     * } $opts
     * @return array{events: list<array<string, mixed>>, total: int, has_more: bool}
     */
    public function listFiltered(int $tenantId, int $mapId, array $opts = []): array
    {
        $limit = max(1, min(200, (int) ($opts['limit'] ?? 50)));
        $beforeId = isset($opts['before_id']) && $opts['before_id'] !== null && $opts['before_id'] !== ''
            ? (int) $opts['before_id']
            : null;
        $afterId = isset($opts['after_id']) && $opts['after_id'] !== null && $opts['after_id'] !== ''
            ? (int) $opts['after_id']
            : null;
        $includeArchived = !empty($opts['include_archived']);
        $archivedOnly = !empty($opts['archived_only']);
        $q = isset($opts['q']) ? trim((string) $opts['q']) : '';
        $fromTs = $this->parseBoundTs(isset($opts['from']) ? (string) $opts['from'] : null, false);
        $toTs = $this->parseBoundTs(isset($opts['to']) ? (string) $opts['to'] : null, true);
        $typeFilter = $this->resolveTypeFilter($opts['type'] ?? null);
        $excludeTypes = [];
        if (isset($opts['exclude_types']) && is_array($opts['exclude_types'])) {
            foreach ($opts['exclude_types'] as $ex) {
                $ex = trim((string) $ex);
                if ($ex !== '') {
                    $excludeTypes[] = strtolower($ex);
                }
            }
            $excludeTypes = array_values(array_unique($excludeTypes));
        }
        // Si le filtre demande explicitement « position », ne pas l’exclure.
        if ($typeFilter !== null && $excludeTypes !== []) {
            $excludeTypes = array_values(array_filter(
                $excludeTypes,
                static fn (string $t): bool => !in_array($t, $typeFilter, true)
            ));
        }

        $path = $this->path($tenantId, $mapId);
        $data = $this->readFile($path);
        $events = $data['events'] ?? [];
        if (!is_array($events)) {
            return ['events' => [], 'total' => 0, 'has_more' => false];
        }

        $filtered = [];
        foreach ($events as $e) {
            if (!is_array($e)) {
                continue;
            }
            $archived = $this->isArchived($e);
            if ($archivedOnly && !$archived) {
                continue;
            }
            if (!$includeArchived && !$archivedOnly && $archived) {
                continue;
            }
            $id = (int) ($e['id'] ?? 0);
            if ($afterId !== null && $afterId > 0 && $id <= $afterId) {
                continue;
            }
            if ($beforeId !== null && $beforeId > 0 && $id >= $beforeId) {
                continue;
            }
            $type = strtolower((string) ($e['type'] ?? ''));
            $e['type'] = $type;
            if ($excludeTypes !== [] && in_array($type, $excludeTypes, true)) {
                continue;
            }
            if ($typeFilter !== null && !in_array($type, $typeFilter, true)) {
                continue;
            }
            $at = (string) ($e['at'] ?? '');
            $atTs = $at !== '' ? strtotime($at) : false;
            if ($fromTs !== null && ($atTs === false || $atTs < $fromTs)) {
                continue;
            }
            if ($toTs !== null && ($atTs === false || $atTs > $toTs)) {
                continue;
            }
            if ($q !== '') {
                $hay = mb_strtolower(
                    (string) ($e['label'] ?? '') . ' ' . (string) ($e['actor'] ?? '') . ' ' . $type,
                    'UTF-8'
                );
                if (!str_contains($hay, mb_strtolower($q, 'UTF-8'))) {
                    continue;
                }
            }
            $filtered[] = $e;
        }

        usort($filtered, static function (array $a, array $b): int {
            return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
        });

        $total = count($filtered);
        $slice = array_slice($filtered, 0, $limit);
        $hasMore = $total > $limit;

        $out = [];
        foreach ($slice as $e) {
            $item = $this->normalizeEventForApi($e);
            if ($item !== null) {
                $out[] = $item;
            }
        }

        return ['events' => $out, 'total' => $total, 'has_more' => $hasMore];
    }

    /**
     * @param array<string, mixed> $e
     * @return array{id: int, type: string, label: string, actor: ?string, at: string, archived?: bool, meta?: array}|null
     */
    private function normalizeEventForApi(array $e): ?array
    {
        $id = (int) ($e['id'] ?? 0);
        $label = Utf8Text::normalize((string) ($e['label'] ?? ''));
        if ($id < 1 && $label === '') {
            return null;
        }
        $actorRaw = isset($e['actor']) && is_string($e['actor']) && $e['actor'] !== '' ? $e['actor'] : null;
        $item = [
            'id' => $id,
            'type' => (string) ($e['type'] ?? ''),
            'label' => $label,
            'actor' => $actorRaw !== null ? Utf8Text::normalize($actorRaw) : null,
            'at' => (string) ($e['at'] ?? ''),
        ];
        if ($this->isArchived($e)) {
            $item['archived'] = true;
            $item['archived_at'] = (string) $e['archived_at'];
        }
        if (isset($e['meta']) && is_array($e['meta']) && $e['meta'] !== []) {
            $meta = $this->sanitizeMeta($e['meta']);
            if ($meta !== []) {
                $item['meta'] = $meta;
            }
        }

        return $item;
    }

    /**
     * @param string|list<string>|null $type
     * @return list<string>|null
     */
    private function resolveTypeFilter(mixed $type): ?array
    {
        if ($type === null || $type === '' || $type === []) {
            return null;
        }
        $raw = is_array($type) ? $type : (preg_split('/\s*,\s*/', (string) $type) ?: []);
        $out = [];
        foreach ($raw as $t) {
            $t = trim((string) $t);
            if ($t === '') {
                continue;
            }
            if (isset(self::FILTER_GROUPS[$t])) {
                foreach (self::FILTER_GROUPS[$t] as $mapped) {
                    $out[] = $mapped;
                }
                continue;
            }
            $out[] = strtolower($t);
        }
        $out = array_values(array_unique($out));

        return $out === [] ? null : $out;
    }

    private function parseBoundTs(?string $raw, bool $endOfDay): ?int
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $raw = trim($raw);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            $raw .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
        }
        $ts = strtotime($raw);

        return $ts === false ? null : $ts;
    }

    /**
     * Seed de démo dense (multi-jours / multi-types) — uniquement hors production, ou flag explicite debug.
     * N’écrit pas si le journal a déjà des événements (sauf $force).
     */
    public function seedDemoEvents(int $tenantId, int $mapId, bool $force = false): int
    {
        if ($tenantId < 1 || $mapId < 1 || !$this->isDemoSeedAllowed()) {
            return 0;
        }
        $path = $this->path($tenantId, $mapId);
        $existing = $this->readFile($path);
        $events = $existing['events'] ?? [];
        if (!$force && is_array($events) && count($events) > 0) {
            return 0;
        }

        $samples = $this->demoSampleBlueprint();
        $added = 0;
        $this->mutate($tenantId, $mapId, function (array &$data) use ($samples, $force, &$added): void {
            if (!$force && is_array($data['events'] ?? null) && count($data['events']) > 0) {
                return;
            }
            if ($force) {
                $data['events'] = [];
                $data['next_id'] = 1;
            }
            $now = time();
            foreach ($samples as $row) {
                $id = (int) ($data['next_id'] ?? 1);
                $data['next_id'] = $id + 1;
                $offsetSec = (int) ($row['offset_sec'] ?? 0);
                $event = [
                    'id' => $id,
                    'type' => (string) $row['type'],
                    'label' => (string) $row['label'],
                    'actor' => isset($row['actor']) ? (string) $row['actor'] : null,
                    'at' => date('c', $now - $offsetSec),
                ];
                if (!empty($row['archived'])) {
                    $event['archived_at'] = date('c', $now - max(60, $offsetSec - 3600));
                }
                $data['events'][] = $event;
                $added++;
            }
        });

        return $added;
    }

    public function isDemoSeedAllowed(): bool
    {
        $env = strtolower(trim((string) (function_exists('env') ? env('APP_ENV', 'production') : ($_ENV['APP_ENV'] ?? 'production'))));
        if (in_array($env, ['local', 'development', 'dev', 'testing'], true)) {
            return true;
        }
        $debug = function_exists('env')
            ? filter_var((string) env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN)
            : filter_var((string) ($_ENV['APP_DEBUG'] ?? false), FILTER_VALIDATE_BOOLEAN);

        return $debug && $env !== 'production';
    }

    /**
     * @return list<array{type: string, label: string, actor?: string, offset_sec: int, archived?: bool}>
     */
    private function demoSampleBlueprint(): array
    {
        $day = 86400;
        $rows = [
            ['type' => self::TYPE_CLIENT_INIT, 'label' => 'Connexion établie — HAWK-1', 'actor' => 'HAWK-1', 'offset_sec' => 120],
            ['type' => self::TYPE_POSITION, 'label' => 'Position envoyée — HAWK-1', 'actor' => 'HAWK-1', 'offset_sec' => 90],
            ['type' => self::TYPE_CHAT, 'label' => 'Message radio — « En position, secteur nord »', 'actor' => 'HAWK-1', 'offset_sec' => 75],
            ['type' => self::TYPE_PING, 'label' => 'Ping carte — HAWK-1', 'actor' => 'HAWK-1', 'offset_sec' => 60],
            ['type' => self::TYPE_CALLSIGN_CHANGE, 'label' => 'Indicatif mis à jour — VIPER → VIPER-2', 'actor' => 'VIPER-2', 'offset_sec' => 40],
            ['type' => self::TYPE_ORDER, 'label' => 'Ordre émis — Se déplacer (VIPER-2)', 'actor' => 'PC', 'offset_sec' => 30],
            ['type' => self::TYPE_PHONE, 'label' => 'Connexion au briefing', 'actor' => 'Opérateur', 'offset_sec' => 20],
            ['type' => self::TYPE_AUTH, 'label' => 'Liaison en jeu réussie — code accepté', 'actor' => 'HAWK-1', 'offset_sec' => 15],
            ['type' => self::TYPE_NINE_LINE, 'label' => '9-Line CAS transmise', 'actor' => 'JTAC-1', 'offset_sec' => 10],
            ['type' => self::TYPE_DISCONNECT, 'label' => 'Déconnexion jeu — GHOST-3', 'actor' => 'GHOST-3', 'offset_sec' => 5],
            // Hier
            ['type' => self::TYPE_CLIENT_INIT, 'label' => 'Connexion établie — RAVEN', 'actor' => 'RAVEN', 'offset_sec' => $day + 3600],
            ['type' => self::TYPE_MARKER, 'label' => 'Marqueur placé — Point d’intérêt', 'actor' => 'RAVEN', 'offset_sec' => $day + 3400],
            ['type' => self::TYPE_INTEL, 'label' => 'Renseignement reçu — photo CTAB', 'actor' => 'RAVEN', 'offset_sec' => $day + 3200],
            ['type' => self::TYPE_LASER, 'label' => 'Code laser synchronisé', 'actor' => 'JTAC-1', 'offset_sec' => $day + 3000],
            ['type' => self::TYPE_FLIGHT, 'label' => 'Manifeste de vol reçu — ANGEL-1', 'actor' => 'ANGEL-1', 'offset_sec' => $day + 2800],
            ['type' => self::TYPE_CHAT, 'label' => 'Message radio — « RTB dans 5 »', 'actor' => 'ANGEL-1', 'offset_sec' => $day + 2600],
            // J-2
            ['type' => self::TYPE_CLIENT_INIT, 'label' => 'Connexion établie — WOLF', 'actor' => 'WOLF', 'offset_sec' => 2 * $day + 7200],
            ['type' => self::TYPE_POSITION, 'label' => 'Position envoyée — WOLF', 'actor' => 'WOLF', 'offset_sec' => 2 * $day + 7000],
            ['type' => self::TYPE_DESIGNATOR, 'label' => 'Désignateur laser actif', 'actor' => 'WOLF', 'offset_sec' => 2 * $day + 6800],
            ['type' => self::TYPE_SIGINT, 'label' => 'Zone SIGINT signalée', 'actor' => 'WOLF', 'offset_sec' => 2 * $day + 6600],
            ['type' => self::TYPE_AUTH, 'label' => 'Liaison en jeu refusée — compte non autorisé', 'actor' => null, 'offset_sec' => 2 * $day + 6400],
            // J-3 (dont quelques archivés pour tester le toggle)
            ['type' => self::TYPE_CLIENT_INIT, 'label' => 'Connexion établie — FALCON', 'actor' => 'FALCON', 'offset_sec' => 3 * $day + 1000, 'archived' => true],
            ['type' => self::TYPE_CHAT, 'label' => 'Message radio — « Check-in secteur Est »', 'actor' => 'FALCON', 'offset_sec' => 3 * $day + 900, 'archived' => true],
            ['type' => self::TYPE_PING, 'label' => 'Ping carte — FALCON', 'actor' => 'FALCON', 'offset_sec' => 3 * $day + 800, 'archived' => true],
            ['type' => self::TYPE_ORDER, 'label' => 'Ordre émis — Tenir la position', 'actor' => 'PC', 'offset_sec' => 3 * $day + 700, 'archived' => true],
            ['type' => self::TYPE_DISCONNECT, 'label' => 'Déconnexion jeu — FALCON', 'actor' => 'FALCON', 'offset_sec' => 3 * $day + 600, 'archived' => true],
        ];

        // Densifier aujourd’hui / hier avec des positions intercalées
        for ($i = 0; $i < 18; $i++) {
            $actor = ($i % 2 === 0) ? 'HAWK-1' : 'VIPER-2';
            $rows[] = [
                'type' => self::TYPE_POSITION,
                'label' => 'Position envoyée — ' . $actor,
                'actor' => $actor,
                'offset_sec' => 200 + ($i * 45),
            ];
        }
        for ($i = 0; $i < 12; $i++) {
            $rows[] = [
                'type' => self::TYPE_POSITION,
                'label' => 'Position envoyée — RAVEN',
                'actor' => 'RAVEN',
                'offset_sec' => $day + 2000 + ($i * 120),
            ];
        }

        return $rows;
    }

    public function clientKeyFromRequest(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'];
            $ip = is_string($forwarded) ? trim(explode(',', $forwarded)[0]) : trim((string) $forwarded[0]);
        }
        $ip = trim((string) $ip);
        if ($ip === '') {
            return '';
        }

        return hash('sha256', 'atak-client:' . $ip);
    }

    /**
     * Signal de présence d'un utilisateur connecté sur la Tacmap web (portail).
     */
    public function heartbeatWebPresence(
        int $tenantId,
        int $mapId,
        int $userId,
        string $displayName,
        string $callsign = ''
    ): void {
        if ($tenantId < 1 || $mapId < 1 || $userId < 1) {
            return;
        }
        $label = trim($callsign) !== '' ? trim($callsign) : trim($displayName);
        if ($label === '') {
            $label = 'Opérateur';
        }
        $now = time();
        $key = 'u' . $userId;
        $this->mutate($tenantId, $mapId, function (array &$data) use ($key, $userId, $label, $displayName, $callsign, $now): void {
            $web = $this->pruneWebPresence($data['web_presence'] ?? [], $now);
            $web[$key] = [
                'user_id' => $userId,
                'label' => $label,
                'display_name' => trim($displayName),
                'callsign' => trim($callsign),
                'last_seen_at' => $now,
            ];
            $data['web_presence'] = $web;
        });
    }

    /**
     * Visiteurs web encore actifs sur la Tacmap (TTL court).
     *
     * @return list<array{user_id:int,label:string,display_name:string,callsign:string,last_seen_at:int}>
     */
    public function listWebPresence(int $tenantId, int $mapId): array
    {
        if ($tenantId < 1 || $mapId < 1) {
            return [];
        }
        $data = $this->readFile($this->path($tenantId, $mapId));
        $now = time();
        $web = $this->pruneWebPresence($data['web_presence'] ?? [], $now);
        $out = [];
        foreach ($web as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'label' => (string) ($row['label'] ?? 'Opérateur'),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'callsign' => (string) ($row['callsign'] ?? ''),
                'last_seen_at' => (int) ($row['last_seen_at'] ?? 0),
            ];
        }
        usort($out, static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * Mémorise la détection des mods compagnons remontée par le jeu (handshake position).
     *
     * @param array{has_ctab?: bool, has_atak_enhanced?: bool, has_athena_ctab?: bool, mod_athena?: bool} $flags
     */
    public function touchModDetection(int $tenantId, int $mapId, array $flags): void
    {
        if ($tenantId < 1 || $mapId < 1) {
            return;
        }
        $hasCtab = !empty($flags['has_ctab']);
        $hasEnhanced = !empty($flags['has_atak_enhanced']);
        $hasAthenaCtab = !empty($flags['has_athena_ctab']);
        $hasAthena = !empty($flags['mod_athena']) || $hasCtab || $hasEnhanced || $hasAthenaCtab;
        if (!$hasAthena && !$hasCtab && !$hasEnhanced) {
            return;
        }
        $now = time();
        $this->mutate($tenantId, $mapId, function (array &$data) use ($now, $hasAthena, $hasCtab, $hasEnhanced, $hasAthenaCtab): void {
            $mods = is_array($data['mod_detection'] ?? null) ? $data['mod_detection'] : [];
            if ($hasAthena) {
                $mods['athena_at'] = $now;
            }
            if ($hasCtab) {
                $mods['ctab_at'] = $now;
            }
            if ($hasEnhanced) {
                $mods['atak_enhanced_at'] = $now;
            }
            if ($hasAthenaCtab) {
                $mods['athena_ctab_at'] = $now;
            }
            $data['mod_detection'] = $mods;
        });
    }

    /**
     * Horodatages de dernière détection mods (secondes Unix), 0 si inconnu / expiré.
     *
     * @return array{athena_at:int,ctab_at:int,atak_enhanced_at:int,athena_ctab_at:int}
     */
    public function getModDetection(int $tenantId, int $mapId): array
    {
        $empty = [
            'athena_at' => 0,
            'ctab_at' => 0,
            'atak_enhanced_at' => 0,
            'athena_ctab_at' => 0,
        ];
        if ($tenantId < 1 || $mapId < 1) {
            return $empty;
        }
        $data = $this->readFile($this->path($tenantId, $mapId));
        $mods = is_array($data['mod_detection'] ?? null) ? $data['mod_detection'] : [];
        $now = time();
        $out = $empty;
        foreach (array_keys($empty) as $key) {
            $at = (int) ($mods[$key] ?? 0);
            if ($at > 0 && ($now - $at) <= self::MOD_DETECT_PRESENT_TTL_SEC) {
                $out[$key] = $at;
            }
        }

        return $out;
    }

    /**
     * Signal de présence d'un client consultant le briefing (téléphone / ATAK).
     */
    public function heartbeatBriefingPresence(
        int $tenantId,
        string $clientKey,
        string $label = '',
        string $source = 'phone'
    ): void {
        if ($tenantId < 1) {
            return;
        }
        $clientKey = $this->normalizeKey($clientKey);
        if ($clientKey === '') {
            return;
        }
        $label = trim($label);
        if ($label === '') {
            $label = 'Opérateur';
        }
        $source = trim($source) !== '' ? trim($source) : 'phone';
        $now = time();
        $this->mutate($tenantId, self::AUTH_MAP_ID, function (array &$data) use ($clientKey, $label, $source, $now): void {
            $presence = $this->pruneBriefingPresence($data['briefing_presence'] ?? [], $now);
            $prev = $presence[$clientKey] ?? null;
            $isNew = !is_array($prev);
            $presence[$clientKey] = [
                'label' => $label,
                'source' => $source,
                'last_seen_at' => $now,
                'first_seen_at' => $isNew ? $now : (int) ($prev['first_seen_at'] ?? $now),
            ];
            $data['briefing_presence'] = $presence;
            // Une seule entrée Activité à l’arrivée (pas à chaque poll / heartbeat).
            if ($isNew) {
                $eventLabel = match ($source) {
                    'arma' => 'Connexion au briefing — tableau en jeu',
                    'admin' => 'Connexion au briefing — état-major',
                    default => ($label !== '' && $label !== 'Téléphone' && $label !== 'Opérateur')
                        ? $label . ' a ouvert les diapos'
                        : 'Connexion au briefing',
                };
                $this->appendEvent($data, self::TYPE_PHONE, $eventLabel, $label !== '' ? $label : null, [
                    'ok' => true,
                    'source' => $source,
                ]);
            }
        });
    }

    /**
     * Clients encore actifs sur le briefing (TTL court).
     *
     * @return list<array{label:string,source:string,last_seen_at:int}>
     */
    public function listBriefingPresence(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $data = $this->readFile($this->path($tenantId, self::AUTH_MAP_ID));
        $now = time();
        $presence = $this->pruneBriefingPresence($data['briefing_presence'] ?? [], $now);
        $out = [];
        foreach ($presence as $row) {
            if (!is_array($row)) {
                continue;
            }
            $out[] = [
                'label' => (string) ($row['label'] ?? 'Opérateur'),
                'source' => (string) ($row['source'] ?? 'phone'),
                'last_seen_at' => (int) ($row['last_seen_at'] ?? 0),
            ];
        }
        usort($out, static function (array $a, array $b): int {
            return strcasecmp($a['label'], $b['label']);
        });

        return $out;
    }

    /**
     * @param callable(array<string, mixed>): void $fn
     */
    private function mutate(int $tenantId, int $mapId, callable $fn): void
    {
        $path = $this->path($tenantId, $mapId);
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return;
        }
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        try {
            if (!flock($fp, LOCK_EX)) {
                return;
            }
            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                $data = ['next_id' => 1, 'events' => [], 'sessions' => [], 'web_presence' => []];
            }
            $fn($data);
            $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($payload === false) {
                return;
            }
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $payload);
            fflush($fp);
        } catch (\Throwable) {
            // Journal best-effort : ne jamais faire échouer l’API ATAK.
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    /** @return array<string, mixed> */
    private function readFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : [];
    }

    private function path(int $tenantId, int $mapId): string
    {
        return base_path('storage/cache/atak-activity/t' . $tenantId . '_m' . $mapId . '.json');
    }

    /**
     * Tous les journaux d’activité de la communauté (par théâtre).
     *
     * @return array<string, array<string, mixed>>
     */
    public function exportAllForTenant(int $tenantId): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $out = [];
        foreach ($this->filesForTenant($tenantId) as $mapId => $path) {
            $data = $this->readFile($path);
            if ($data === []) {
                continue;
            }
            $out['map_' . $mapId] = $data;
        }

        return $out;
    }

    /** Nombre total d’événements (actifs + archivés) pour la communauté. */
    public function countAllForTenant(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $total = 0;
        foreach ($this->filesForTenant($tenantId) as $path) {
            $data = $this->readFile($path);
            $events = $data['events'] ?? [];
            if (is_array($events)) {
                $total += count($events);
            }
        }

        return $total;
    }

    /** Supprime tous les fichiers journal de la communauté. Retourne le nombre de fichiers effacés. */
    public function purgeAllForTenant(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $removed = 0;
        foreach ($this->filesForTenant($tenantId) as $path) {
            if (is_file($path) && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Événements SSE du journal d’activité (tous théâtres de la communauté).
     *
     * @return list<array<string, mixed>>
     */
    public function listSseActionsForTenant(int $tenantId, int $limit = 120): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $limit = max(1, min(300, $limit));
        $typeLabels = [
            'SSE_PERSON' => 'Personne',
            'SSE_PHOTO' => 'Photo',
            'SSE_SITE' => 'Site',
            'SSE_SEIZURE' => 'Saisie',
            'SSE_WATCHLIST' => 'Surveillance',
            'SSE_AUTO' => 'Automatisme',
            'SSE_CLEARANCE' => 'Habilitation',
            'SSE_DOCUMENT' => 'Document',
        ];

        $collected = [];
        foreach ($this->filesForTenant($tenantId) as $mapId => $path) {
            $data = $this->readFile($path);
            $events = $data['events'] ?? [];
            if (!is_array($events)) {
                continue;
            }
            foreach ($events as $e) {
                if (!is_array($e)) {
                    continue;
                }
                $type = strtoupper(trim((string) ($e['type'] ?? '')));
                if ($type === '' || !str_starts_with($type, 'SSE_')) {
                    continue;
                }
                $at = (string) ($e['at'] ?? '');
                $collected[] = [
                    'source' => 'activity',
                    'event_type' => $type,
                    'event_label' => $typeLabels[$type] ?? 'Action SSE',
                    'detail' => trim((string) ($e['label'] ?? '')),
                    'actor' => trim((string) ($e['actor'] ?? '')) ?: null,
                    'created_at' => $at,
                    'ts' => $at !== '' ? (strtotime($at) ?: 0) : (int) ($e['ts'] ?? 0),
                    'map_id' => (int) $mapId,
                ];
            }
        }

        usort(
            $collected,
            static fn (array $a, array $b): int => ((int) ($b['ts'] ?? 0)) <=> ((int) ($a['ts'] ?? 0))
        );

        return array_slice($collected, 0, $limit);
    }

    /**
     * @return array<int, string> mapId => path
     */
    private function filesForTenant(int $tenantId): array
    {
        $dir = base_path('storage/cache/atak-activity');
        if (!is_dir($dir)) {
            return [];
        }
        $prefix = 't' . $tenantId . '_m';
        $out = [];
        foreach (glob($dir . '/' . $prefix . '*.json') ?: [] as $path) {
            $base = basename((string) $path, '.json');
            if (!str_starts_with($base, $prefix)) {
                continue;
            }
            $mapId = (int) substr($base, strlen($prefix));
            if ($mapId < 1) {
                continue;
            }
            $out[$mapId] = (string) $path;
        }

        return $out;
    }

    private function normalizeKey(string $key): string
    {
        $key = trim($key);

        return strlen($key) > 8 ? $key : '';
    }

    /**
     * @param mixed $sessions
     * @return array<string, array<string, mixed>>
     */
    private function pruneSessions(mixed $sessions, int $now): array
    {
        if (!is_array($sessions)) {
            return [];
        }
        $out = [];
        foreach ($sessions as $key => $row) {
            if (!is_string($key) || !is_array($row)) {
                continue;
            }
            $seen = (int) ($row['last_seen_at'] ?? 0);
            if ($seen > 0 && ($now - $seen) > self::SESSION_TTL_SEC) {
                continue;
            }
            $out[$key] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $presence
     * @return array<string, array<string, mixed>>
     */
    private function pruneWebPresence(mixed $presence, int $now): array
    {
        if (!is_array($presence)) {
            return [];
        }
        $out = [];
        foreach ($presence as $key => $row) {
            if (!is_string($key) || !is_array($row)) {
                continue;
            }
            $seen = (int) ($row['last_seen_at'] ?? 0);
            if ($seen > 0 && ($now - $seen) > self::WEB_PRESENCE_TTL_SEC) {
                continue;
            }
            $out[$key] = $row;
        }

        return $out;
    }

    /**
     * @param mixed $presence
     * @return array<string, array<string, mixed>>
     */
    private function pruneBriefingPresence(mixed $presence, int $now): array
    {
        if (!is_array($presence)) {
            return [];
        }
        $out = [];
        foreach ($presence as $key => $row) {
            if (!is_string($key) || !is_array($row)) {
                continue;
            }
            $seen = (int) ($row['last_seen_at'] ?? 0);
            if ($seen > 0 && ($now - $seen) > self::BRIEFING_PRESENCE_TTL_SEC) {
                continue;
            }
            $out[$key] = $row;
        }

        return $out;
    }
}
