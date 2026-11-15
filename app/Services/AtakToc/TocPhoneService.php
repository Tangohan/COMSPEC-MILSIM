<?php

declare(strict_types=1);

namespace App\Services\AtakToc;

use PDO;

/**
 * Actions du poste TOC web sur les téléphones ATAK EN JEU : notifier, faire sonner ou vibrer, localiser, trianguler.
 * Uniquement des téléphones et des positions du monde Arma 3 (coordonnées de jeu) : aucune adresse IP, aucun appareil
 * réel, aucune position réelle d'un joueur ne passe ici. Les comptes Steam ne sortent jamais vers le navigateur : un
 * téléphone est désigné par une référence opaque (HMAC) propre à la communauté.
 *
 * Acheminement : la file existante des commandes web (atak_web_commands, genre « phone », fil WC1) lue par le sondage
 * du mod ; le téléphone exécute et rend compte par le compte rendu existant (web_cmd_ack). Aucun toast ni popup en jeu :
 * le message arrive dans la boîte du téléphone (Messages et Alertes), le téléphone vibre ou sonne.
 *
 * Garde-fous : fonction désactivée par défaut dans chaque communauté, droit dédié (atak.toc.phone, contrôlé par les
 * contrôleurs), un téléphone hors de la communauté est introuvable, pas de notification ni de sonnerie vers un PNJ ou un
 * hostile, 1 localisation par 15 s et par téléphone, 10 notifications par minute et par opérateur, 3 triangulations
 * simultanées par communauté (2 par opérateur), journal complet de chaque demande.
 */
final class TocPhoneService
{
    public const LOCATE_GAP_S = 15;
    public const NOTIFY_PER_MIN = 10;
    public const RING_PER_MIN = 6;
    public const RING_STOP_PER_MIN = 20;
    public const TRI_RUNNING_COMMUNITY = 3;
    public const TRI_RUNNING_OPERATOR = 2;
    public const TRI_GAP_S = 30;
    public const NOTIFY_OPEN_PER_TARGET = 3;
    public const MESSAGE_MAX = 160;
    public const RING_MIN_S = 3;
    public const RING_MAX_S = 30;
    public const TTL_NOTIFY = 86400;
    public const TTL_REALTIME = 60;
    public const STALE_POSITION_S = 900;
    public const MESH_RANGE_M = 3000;
    public const LEVELS = ['info', 'alert', 'urgent'];
    public const ACTIONS = ['notify', 'ring', 'ring_stop', 'locate', 'triangulate'];

    /** @var callable(int, int, string, string, string, string, array<string, mixed>, array<string, scalar>, int, string, ?int): ?int */
    private $enqueue;

    /** @var callable(int, int): ?array<string, mixed> */
    private $commandInfo;

    /** @var callable(int, int, int): ?list<array<string, mixed>> */
    private $units;

    /** @var callable(int, int): list<array<string, mixed>> */
    private $relays;

    /** @var callable(string, int, ?int, ?int, array<string, mixed>): void */
    private $audit;

    /** @var callable(): int */
    private $clock;

    private TocPhoneStore $store;

    /**
     * @param callable|null $enqueue (tenant, carte, commande, uid de la cible, réf, libellé, arguments lisibles, arguments du fil, délai s, auteur, membre) => id de commande
     * @param callable|null $commandInfo (tenant, id) => commande présentée (status, result_text, result) ou null
     * @param callable|null $units (tenant, carte, membre) => lignes de /api/atak/units?toc=1 vues par ce membre, ou null si la vue est brouillée
     * @param callable|null $relays (tenant, carte) => lignes de atak_relays
     * @param callable|null $audit (action, tenant, membre, id, détails)
     * @param callable|null $clock () => instant Unix
     */
    public function __construct(
        ?PDO $pdo = null,
        ?callable $enqueue = null,
        ?callable $commandInfo = null,
        ?callable $units = null,
        ?callable $relays = null,
        ?callable $audit = null,
        ?callable $clock = null,
        private string $secret = ''
    ) {
        $this->store = new TocPhoneStore($pdo);
        $this->clock = $clock ?? static fn (): int => time();
        $this->enqueue = $enqueue ?? static function (int $tenantId, int $mapId, string $cmd, string $uid, string $ref, string $label, array $args, array $wire, int $ttl, string $who, ?int $userId): ?int {
            $row = (new \App\Repositories\AtakWebCommandRepository())->enqueue($tenantId, $mapId, 'phone', $cmd, $uid, $ref, $label, $args, $wire, $who, $userId, $ttl);

            return is_array($row) ? (int) $row['id'] : null;
        };
        $this->commandInfo = $commandInfo ?? static function (int $tenantId, int $id): ?array {
            $row = (new \App\Repositories\AtakWebCommandRepository())->find($tenantId, $id);

            return is_array($row) ? $row : null;
        };
        $this->units = $units ?? static fn (int $t, int $m, int $u): ?array => [];
        $this->relays = $relays ?? static fn (int $t, int $m): array => [];
        $this->audit = $audit ?? static function (string $action, int $tenantId, ?int $userId, ?int $id, array $details): void {
            try {
                (new \App\Services\Audit\AuditService())->logChange($action, $tenantId, $userId, 'atak_toc_phone', $id, [], $details);
            } catch (\Throwable $e) {
                error_log('[atak-toc-phone audit] ' . $action . ' : ' . $e->getMessage());
            }
        };
    }

    public function store(): TocPhoneStore
    {
        return $this->store;
    }

    private function now(): int
    {
        return (int) ($this->clock)();
    }

    /* ------------------------------------------------------------------ */
    /* Références, texte du fil, terrain                                    */
    /* ------------------------------------------------------------------ */

    /** Référence opaque d'un téléphone (jamais le compte Steam) : 12 hexadécimaux propres à la communauté. */
    public function refFor(int $tenantId, string $stableId): string
    {
        return 'p' . substr(hash_hmac('sha256', $tenantId . '|' . $stableId, $this->secret !== '' ? $this->secret : self::defaultSecret()), 0, 12);
    }

    private static function defaultSecret(): string
    {
        foreach (['APP_KEY', 'COMSPEC_API_KEY'] as $k) {
            $v = $_ENV[$k] ?? getenv($k);
            if (is_string($v) && $v !== '') {
                return $v;
            }
        }

        return 'athena-toc-phone';
    }

    /**
     * Texte au format du fil WC1 : lettres, chiffres, espace, « . , : - » tels quels ; tout autre caractère devient
     * « @<code Unicode en décimal>_ » (le mod le décode). Le fil ne porte jamais d'accent ni de ponctuation brute.
     */
    public static function wireText(string $s): string
    {
        $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
        $out = '';
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (preg_match('/^[A-Za-z0-9 .,:\-]$/', $ch) === 1) {
                $out .= $ch;
                continue;
            }
            $cp = mb_ord($ch, 'UTF-8');
            $out .= '@' . ($cp !== false && $cp <= 0xFFFF ? $cp : 63) . '_';
        }

        return $out;
    }

    /** Comme wireText, coupé à $max caractères du fil sans jamais couper un « @code_ » en deux. */
    public static function wireTextMax(string $s, int $max): string
    {
        $out = '';
        foreach (preg_split('//u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            $piece = self::wireText($ch);
            if (strlen($out) + strlen($piece) > $max) {
                break;
            }
            $out .= $piece;
        }

        return $out;
    }

    public static function unwireText(string $s): string
    {
        return preg_replace_callback('/@(\d{1,5})_/', static fn (array $m): string => (string) mb_chr((int) $m[1], 'UTF-8'), $s) ?? $s;
    }

    /** Part du terrain couverte par la végétation et le bâti (0 dégagé, 1 très couvert) : approximation par carte. */
    public static function terrainFactor(string $world): float
    {
        $w = strtolower(trim($world));
        foreach ([
            'stratis' => 0.15, 'altis' => 0.25, 'malden' => 0.30, 'tanoa' => 0.50, 'enoch' => 0.45, 'livonia' => 0.45,
            'chernarus' => 0.40, 'takistan' => 0.20, 'zargabad' => 0.35, 'kimmirut' => 0.30, 'sahrani' => 0.25,
        ] as $needle => $f) {
            if (str_contains($w, $needle)) {
                return $f;
            }
        }

        return 0.30;
    }

    /** Affiliation vue du camp du poste (comme TocTracks côté navigateur) : F ami, H hostile, N neutre, U inconnu. */
    public static function affiliation(string $trackSide, string $mySide, bool $hostileFlag): string
    {
        if ($hostileFlag) {
            return 'H';
        }
        $norm = static function (string $v): string {
            return [
                'WEST' => 'WEST', 'BLUFOR' => 'WEST', 'BLUE' => 'WEST', 'B' => 'WEST', 'EAST' => 'EAST', 'OPFOR' => 'EAST', 'RED' => 'EAST', 'O' => 'EAST',
                'GUER' => 'GUER', 'INDEP' => 'GUER', 'INDFOR' => 'GUER', 'INDEPENDENT' => 'GUER', 'RESISTANCE' => 'GUER', 'GREEN' => 'GUER', 'I' => 'GUER',
                'CIV' => 'CIV', 'CIVILIAN' => 'CIV', 'C' => 'CIV',
            ][strtoupper(trim($v))] ?? '';
        };
        $s = $norm($trackSide);
        $me = $norm($mySide) ?: 'WEST';
        if ($s === '' || $s === $me) {
            return 'F';
        }
        if ($s === 'CIV') {
            return 'N';
        }
        if ($me === 'CIV' || $s === 'GUER' || $me === 'GUER') {
            return 'U';
        }

        return 'H';
    }

    /* ------------------------------------------------------------------ */
    /* État, réglage                                                        */
    /* ------------------------------------------------------------------ */

    public function ready(): bool
    {
        return $this->store->ready();
    }

    /** @return array{ready: bool, enabled: bool, hide_hostile_journal: bool, updated_at: ?string} */
    public function status(int $tenantId): array
    {
        $ready = $this->store->ready();
        $s = $ready ? $this->store->settings($tenantId) : ['enabled' => false, 'hide_hostile_journal' => false, 'updated_at' => null];

        return ['ready' => $ready, 'enabled' => $s['enabled'], 'hide_hostile_journal' => $s['hide_hostile_journal'], 'updated_at' => $s['updated_at']];
    }

    /** @return array{ok: bool, error?: string} */
    public function saveSettings(int $tenantId, int $userId, string $operator, bool $enabled, bool $hideHostileJournal): array
    {
        if (!$this->store->ready()) {
            return ['ok' => false, 'error' => 'not_ready'];
        }
        $before = $this->store->settings($tenantId);
        $this->store->saveSettings($tenantId, $enabled, $hideHostileJournal, $userId, $this->now());
        ($this->audit)('atak_toc.phone.settings', $tenantId, $userId, null, [
            'operator' => $operator,
            'enabled' => ['from' => $before['enabled'], 'to' => $enabled],
            'hide_hostile_journal' => ['from' => $before['hide_hostile_journal'], 'to' => $hideHostileJournal],
        ]);

        return ['ok' => true];
    }

    /* ------------------------------------------------------------------ */
    /* Annuaire des téléphones                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>> entrées internes (avec « uid », jamais envoyées telles quelles au navigateur)
     */
    private function buildDirectory(int $tenantId, array $rows, string $side): array
    {
        $out = [];
        foreach (TocTracks::dedupe($rows, $this->now()) as $row) {
            $row = TocTracks::decorateUnit($row, $this->now());
            $toc = is_array($row['toc'] ?? null) ? $row['toc'] : [];
            $extra = is_array($row['extra'] ?? null) ? $row['extra'] : (json_decode((string) ($row['extra'] ?? ''), true) ?: []);
            $stable = TocTracks::stableId($row);
            $steam = '';
            foreach (['steam_uid', 'steamId', 'player_uid'] as $k) {
                $v = preg_replace('/\D/', '', (string) ($extra[$k] ?? '')) ?? '';
                if ($v !== '' && $v !== '0') {
                    $steam = $v;
                    break;
                }
            }
            $isAi = !empty($toc['ai']);
            $hostileFlag = !empty($toc['hostile']);
            $affil = self::affiliation((string) ($extra['side'] ?? $extra['affiliation'] ?? ''), $side, $hostileFlag);
            $status = (string) ($row['status'] ?? '');
            $age = isset($toc['age']) && $toc['age'] !== null ? (int) $toc['age'] : null;
            $state = match (true) {
                $status === 'offline', $status === 'destroyed' => 'offline',
                $status === 'delayed' => 'no_coverage',
                $age !== null && $age > 300 => 'offline',
                default => 'online',
            };
            $px = isset($row['pos_x']) && is_numeric($row['pos_x']) ? (float) $row['pos_x'] : null;
            $py = isset($row['pos_y']) && is_numeric($row['pos_y']) ? (float) $row['pos_y'] : null;
            $hasPos = $px !== null && $py !== null && (abs($px) >= 1 || abs($py) >= 1);
            $isPlayer = $steam !== '' && !$isAi;
            $callsign = trim((string) ($row['display_call_sign'] ?? $row['call_sign'] ?? ''));
            $out[] = [
                'ref' => $this->refFor($tenantId, $stable),
                'uid' => $steam,
                'callsign' => $callsign !== '' ? mb_substr($callsign, 0, 60) : '?',
                'group' => mb_substr(trim((string) ($row['group_name'] ?? $extra['group_name'] ?? $extra['group'] ?? '')), 0, 60),
                'kind' => $isPlayer ? 'player' : 'npc',
                'hostile' => $affil === 'H',
                'affil' => $affil,
                'state' => $state,
                'pos' => $hasPos ? [round($px, 1), round($py, 1)] : null,
                'age' => $age,
                'battery' => isset($toc['battery']) && $toc['battery'] !== null ? max(0, min(100, (int) $toc['battery'])) : null,
                'role' => mb_substr(trim((string) ($row['role'] ?? $extra['role'] ?? '')), 0, 40),
                'map_id' => (int) ($row['map_id'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Téléphones connus du poste : une entrée par identifiant stable, avec la dernière position, son âge, la dernière
     * localisation obtenue par le téléphone et les actions permises. Sans compte Steam ni identifiant de ligne.
     *
     * @return array{ok: bool, error?: string, phones?: list<array<string, mixed>>}
     */
    public function directory(int $tenantId, int $mapId, int $userId, string $side): array
    {
        $rows = ($this->units)($tenantId, $mapId, $userId);
        if ($rows === null) {
            return ['ok' => false, 'error' => 'view_degraded'];
        }
        $phones = $this->buildDirectory($tenantId, $rows, $side);
        $fixes = [];
        $pending = [];
        if ($this->store->ready()) {
            $this->refresh($tenantId);
            foreach ($this->store->events($tenantId, ['since' => $this->now() - 7200, 'actions' => ['locate', 'triangulate'], 'statuses' => ['done']], 200) as $e) {
                $ref = (string) $e['target_ref'];
                if (!isset($fixes[$ref]) && $e['pos_x'] !== null && $e['pos_y'] !== null) {
                    $fixes[$ref] = $this->presentFix($e);
                }
            }
            foreach ($this->store->openEvents($tenantId, 120) as $e) {
                $pending[(string) $e['target_ref']] = ($pending[(string) $e['target_ref']] ?? 0) + 1;
            }
        }
        $out = [];
        foreach ($phones as $p) {
            $isPlayer = $p['kind'] === 'player';
            $canMessage = $isPlayer && !$p['hostile'];
            $out[] = [
                'ref' => $p['ref'],
                'callsign' => $p['callsign'],
                'group' => $p['group'],
                'kind' => $p['kind'],
                'hostile' => $p['hostile'],
                'affil' => $p['affil'],
                'state' => $p['state'],
                'pos' => $p['pos'],
                'age' => $p['age'],
                'battery' => $p['battery'],
                'role' => $p['role'],
                'fix' => $fixes[$p['ref']] ?? null,
                'pending' => $pending[$p['ref']] ?? 0,
                'can' => [
                    'notify' => $canMessage,
                    'ring' => $canMessage,
                    'locate' => $isPlayer,
                    'triangulate' => $p['pos'] !== null,
                ],
            ];
        }
        usort($out, static fn (array $a, array $b): int => (($a['state'] === 'online' ? 0 : ($a['state'] === 'no_coverage' ? 1 : 2)) <=> ($b['state'] === 'online' ? 0 : ($b['state'] === 'no_coverage' ? 1 : 2))) ?: strnatcasecmp($a['callsign'], $b['callsign']));

        return ['ok' => true, 'phones' => $out];
    }

    /**
     * Une cible par sa référence, dans la communauté et la vue de l'opérateur. Introuvable = null (jamais de précision
     * sur l'existence d'un téléphone d'une autre communauté).
     *
     * @return array{ok: true, target: array<string, mixed>, rows: list<array<string, mixed>>}|array{ok: false, error: string}
     */
    private function resolve(int $tenantId, int $mapId, int $userId, string $side, string $ref): array
    {
        if (preg_match('/^p[0-9a-f]{12}$/', $ref) !== 1) {
            return ['ok' => false, 'error' => 'target_unknown'];
        }
        $rows = ($this->units)($tenantId, $mapId, $userId);
        if ($rows === null) {
            return ['ok' => false, 'error' => 'view_degraded'];
        }
        $phones = $this->buildDirectory($tenantId, $rows, $side);
        foreach ($phones as $p) {
            if ($p['ref'] === $ref) {
                return ['ok' => true, 'target' => $p, 'rows' => $phones];
            }
        }

        return ['ok' => false, 'error' => 'target_unknown'];
    }

    /* ------------------------------------------------------------------ */
    /* Actions                                                              */
    /* ------------------------------------------------------------------ */

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function refuse(int $tenantId, int $mapId, int $userId, string $operator, string $action, string $ref, string $label, string $kind, string $code, array $extra = []): array
    {
        $now = $this->now();
        $id = $this->store->insertEvent([
            'tenant_id' => $tenantId, 'map_id' => $mapId, 'user_id' => $userId, 'operator' => $operator, 'target_ref' => $ref !== '' ? $ref : 'inconnu',
            'target_label' => $label, 'target_kind' => $kind, 'action' => $action, 'status' => 'refused', 'result_text' => $code,
            'level' => $extra['level'] ?? null,
        ], $now);
        ($this->audit)('atak_toc.phone.' . $action . '.refused', $tenantId, $userId, $id, ['target' => $ref, 'reason' => $code] + $extra);

        return ['ok' => false, 'error' => $code] + $extra;
    }

    /** Contrôles communs : fonction prête et activée, cible dans la communauté. */
    private function gate(int $tenantId, int $mapId, int $userId, string $operator, string $side, string $action, string $ref): array
    {
        if (!$this->store->ready()) {
            return ['ok' => false, 'error' => 'not_ready'];
        }
        if (!$this->store->settings($tenantId)['enabled']) {
            return ['ok' => false, 'error' => 'disabled'];
        }
        $r = $this->resolve($tenantId, $mapId, $userId, $side, $ref);
        if (!$r['ok']) {
            // Pas d'événement pour une cible inconnue : rien à rattacher, et pas d'écriture déclenchée par une référence quelconque.
            return $r;
        }

        return $r;
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatch(int $tenantId, int $mapId, int $userId, string $operator, array $target, string $cmd, string $action, int $ttl, array $args, array $wire, array $event): array
    {
        $now = $this->now();
        // La commande part sur la carte où le téléphone est suivi (le mod sonde par carte).
        $cmdMap = (int) ($target['map_id'] ?? 0) > 0 ? (int) $target['map_id'] : $mapId;
        $cmdId = ($this->enqueue)($tenantId, $cmdMap, $cmd, (string) $target['uid'], '', (string) $target['callsign'], $args, $wire, $ttl, $operator, $userId);
        if ($cmdId === null) {
            return $this->refuse($tenantId, $mapId, $userId, $operator, $action, (string) $target['ref'], (string) $target['callsign'], (string) $target['kind'], 'send_failed');
        }
        $id = $this->store->insertEvent($event + [
            'tenant_id' => $tenantId, 'map_id' => $mapId, 'user_id' => $userId, 'operator' => $operator, 'target_ref' => $target['ref'],
            'target_label' => $target['callsign'], 'target_kind' => $target['kind'], 'action' => $action, 'command_id' => $cmdId,
            'status' => 'queued', 'expires_at' => TocPhoneStore::dt($now + $ttl),
        ], $now);
        ($this->audit)('atak_toc.phone.' . $action, $tenantId, $userId, $id, [
            'target' => $target['ref'], 'label' => $target['callsign'], 'command' => $cmdId, 'level' => $event['level'] ?? null,
            'message' => $event['message'] ?? null, 'ack_requested' => !empty($event['ack_requested']),
        ]);

        return ['ok' => true, 'event_id' => $id, 'command_id' => $cmdId, 'expires_in' => $ttl];
    }

    /** Argument « journal visible par la cible » du fil : jamais masqué pour un allié ; masqué pour un hostile si la communauté l'a réglé. */
    private function journalVisible(int $tenantId, array $target): bool
    {
        if ($target['affil'] === 'H') {
            return !$this->store->settings($tenantId)['hide_hostile_journal'];
        }

        return true;
    }

    /** @return array<string, mixed> */
    public function notify(int $tenantId, int $mapId, int $userId, string $operator, string $side, string $ref, string $message, string $level, bool $ackRequested, bool $confirmed): array
    {
        $g = $this->gate($tenantId, $mapId, $userId, $operator, $side, 'notify', $ref);
        if (!$g['ok']) {
            return $g;
        }
        $t = $g['target'];
        $refuse = fn (string $code, array $extra = []): array => $this->refuse($tenantId, $mapId, $userId, $operator, 'notify', $ref, (string) $t['callsign'], (string) $t['kind'], $code, ['level' => $level] + $extra);
        if ($t['kind'] !== 'player' || $t['hostile']) {
            return $refuse('target_not_player');
        }
        $message = trim(preg_replace('/\s+/u', ' ', strip_tags($message)) ?? '');
        if ($message === '') {
            return $refuse('message_required');
        }
        if (mb_strlen($message) > self::MESSAGE_MAX) {
            return $refuse('message_too_long');
        }
        if (!in_array($level, self::LEVELS, true)) {
            return $refuse('level_invalid');
        }
        if ($level === 'urgent' && !$confirmed) {
            return ['ok' => false, 'error' => 'confirm_required'];
        }
        $now = $this->now();
        if ($this->store->countRecent($tenantId, 'notify', $now - 60, ['user_id' => $userId]) >= self::NOTIFY_PER_MIN) {
            return $refuse('rate_limited', ['retry_after' => 60]);
        }
        if ($this->store->countOpenNotifies($tenantId, $ref) >= self::NOTIFY_OPEN_PER_TARGET) {
            return $refuse('too_many_pending');
        }
        $enc = self::wireText($message);
        if (strlen($enc) > 330) {
            return $refuse('message_too_long_encoded');
        }
        $by = self::wireTextMax($operator, 40);

        return $this->dispatch($tenantId, $mapId, $userId, $operator, $t, 'notify', 'notify', self::TTL_NOTIFY, ['level' => $level, 'ack' => $ackRequested],
            ['lv' => substr($level, 0, 1), 'm' => $enc, 'ak' => $ackRequested ? 1 : 0, 'by' => $by],
            ['level' => $level, 'message' => $message, 'ack_requested' => $ackRequested]);
    }

    /** @return array<string, mixed> */
    public function ring(int $tenantId, int $mapId, int $userId, string $operator, string $side, string $ref, int $seconds, string $mode, bool $confirmed): array
    {
        $g = $this->gate($tenantId, $mapId, $userId, $operator, $side, 'ring', $ref);
        if (!$g['ok']) {
            return $g;
        }
        $t = $g['target'];
        $refuse = fn (string $code, array $extra = []): array => $this->refuse($tenantId, $mapId, $userId, $operator, 'ring', $ref, (string) $t['callsign'], (string) $t['kind'], $code, $extra);
        if ($t['kind'] !== 'player' || $t['hostile']) {
            return $refuse('target_not_player');
        }
        if ($seconds < self::RING_MIN_S || $seconds > self::RING_MAX_S) {
            return $refuse('duration_invalid');
        }
        if (!in_array($mode, ['ring', 'vib'], true)) {
            return $refuse('mode_invalid');
        }
        if (!$confirmed) {
            return ['ok' => false, 'error' => 'confirm_required'];
        }
        if ($this->store->countRecent($tenantId, 'ring', $this->now() - 60, ['user_id' => $userId]) >= self::RING_PER_MIN) {
            return $refuse('rate_limited', ['retry_after' => 60]);
        }

        return $this->dispatch($tenantId, $mapId, $userId, $operator, $t, 'ring', 'ring', self::TTL_REALTIME, ['seconds' => $seconds, 'mode' => $mode],
            ['d' => $seconds, 'md' => $mode, 'by' => self::wireTextMax($operator, 40)], ['message' => $mode . ' ' . $seconds . ' s']);
    }

    /** @return array<string, mixed> */
    public function ringStop(int $tenantId, int $mapId, int $userId, string $operator, string $side, string $ref): array
    {
        $g = $this->gate($tenantId, $mapId, $userId, $operator, $side, 'ring_stop', $ref);
        if (!$g['ok']) {
            return $g;
        }
        $t = $g['target'];
        if ($t['kind'] !== 'player') {
            return $this->refuse($tenantId, $mapId, $userId, $operator, 'ring_stop', $ref, (string) $t['callsign'], (string) $t['kind'], 'target_not_player');
        }
        if ($this->store->countRecent($tenantId, 'ring_stop', $this->now() - 60, ['user_id' => $userId]) >= self::RING_STOP_PER_MIN) {
            return $this->refuse($tenantId, $mapId, $userId, $operator, 'ring_stop', $ref, (string) $t['callsign'], (string) $t['kind'], 'rate_limited', ['retry_after' => 60]);
        }

        return $this->dispatch($tenantId, $mapId, $userId, $operator, $t, 'stop', 'ring_stop', self::TTL_REALTIME, [], [], []);
    }

    /** @return array<string, mixed> */
    public function locate(int $tenantId, int $mapId, int $userId, string $operator, string $side, string $ref): array
    {
        $g = $this->gate($tenantId, $mapId, $userId, $operator, $side, 'locate', $ref);
        if (!$g['ok']) {
            return $g;
        }
        $t = $g['target'];
        if ($t['kind'] !== 'player') {
            // Un PNJ n'a pas de téléphone qui réponde : jamais de position inventée.
            return $this->refuse($tenantId, $mapId, $userId, $operator, 'locate', $ref, (string) $t['callsign'], (string) $t['kind'], 'target_not_player');
        }
        $now = $this->now();
        $last = $this->store->events($tenantId, ['target_ref' => $ref, 'actions' => ['locate'], 'since' => $now - self::LOCATE_GAP_S], 5);
        foreach ($last as $e) {
            if ($e['status'] !== 'refused') {
                $wait = max(1, self::LOCATE_GAP_S - ($now - (TocPhoneStore::ts((string) $e['created_at']) ?? $now)));

                return $this->refuse($tenantId, $mapId, $userId, $operator, 'locate', $ref, (string) $t['callsign'], (string) $t['kind'], 'rate_limited', ['retry_after' => $wait]);
            }
        }

        return $this->dispatch($tenantId, $mapId, $userId, $operator, $t, 'locate', 'locate', self::TTL_REALTIME, [],
            ['vis' => $this->journalVisible($tenantId, $t) ? 1 : 0, 'by' => self::wireTextMax($operator, 40)], []);
    }

    /**
     * Sources d'écoute d'une triangulation : relais intacts de la carte, puis téléphones en ligne du camp du poste
     * (sauf la cible), comme la triangulation par relais de l'ATAK en jeu.
     *
     * @param list<array<string, mixed>> $phones
     * @return list<array{id: string, label: string, kind: string, x: float, y: float, range: float}>
     */
    private function sources(int $tenantId, int $mapId, array $phones, string $targetRef): array
    {
        $src = [];
        foreach (($this->relays)($tenantId, $mapId) as $r) {
            $alive = !isset($r['alive']) || (int) $r['alive'] === 1;
            $x = isset($r['pos_x']) ? (float) $r['pos_x'] : 0.0;
            $y = isset($r['pos_y']) ? (float) $r['pos_y'] : 0.0;
            if (!$alive || (abs($x) < 1 && abs($y) < 1)) {
                continue;
            }
            $uid = (string) ($r['relay_uid'] ?? $r['id'] ?? '');
            $src[] = [
                'id' => 'r:' . substr(hash('sha256', $tenantId . '|' . $uid), 0, 8),
                'label' => mb_substr(trim((string) ($r['display_name'] ?? '')) ?: 'Relais', 0, 40),
                'kind' => 'relay', 'x' => $x, 'y' => $y, 'range' => max(200.0, (float) ($r['range_m'] ?? 2000)),
            ];
        }
        foreach ($phones as $p) {
            if ($p['ref'] === $targetRef || $p['pos'] === null || $p['state'] !== 'online' || $p['affil'] !== 'F' || $p['kind'] !== 'player') {
                continue;
            }
            $src[] = ['id' => 'u:' . $p['ref'], 'label' => (string) $p['callsign'], 'kind' => 'phone', 'x' => (float) $p['pos'][0], 'y' => (float) $p['pos'][1], 'range' => (float) self::MESH_RANGE_M];
        }

        return array_slice($src, 0, 63);
    }

    /** @return array<string, mixed> */
    public function triangulate(int $tenantId, int $mapId, int $userId, string $operator, string $side, string $ref, string $world = ''): array
    {
        $g = $this->gate($tenantId, $mapId, $userId, $operator, $side, 'triangulate', $ref);
        if (!$g['ok']) {
            return $g;
        }
        $t = $g['target'];
        $refuse = fn (string $code, array $extra = []): array => $this->refuse($tenantId, $mapId, $userId, $operator, 'triangulate', $ref, (string) $t['callsign'], (string) $t['kind'], $code, $extra);
        if ($t['pos'] === null) {
            return $refuse('no_position');
        }
        if ($t['age'] === null || $t['age'] > self::STALE_POSITION_S) {
            return $refuse('stale_position');
        }
        $now = $this->now();
        if ($this->store->countRunning($tenantId, null, $now) >= self::TRI_RUNNING_COMMUNITY || $this->store->countRunning($tenantId, $userId, $now) >= self::TRI_RUNNING_OPERATOR) {
            return $refuse('too_many_triangulations');
        }
        foreach ($this->store->events($tenantId, ['target_ref' => $ref, 'actions' => ['triangulate'], 'since' => $now - self::TRI_GAP_S], 5) as $e) {
            if ($e['status'] !== 'refused') {
                return $refuse('rate_limited', ['retry_after' => max(1, self::TRI_GAP_S - ($now - (TocPhoneStore::ts((string) $e['created_at']) ?? $now)))]);
            }
        }
        $sources = $this->sources($tenantId, $mapId, $g['rows'], $ref);
        $seed = PhoneTriangulation::seedFor($tenantId, $ref, $now);
        $result = PhoneTriangulation::estimate([$t['pos'][0], $t['pos'][1]], $sources, $seed, self::terrainFactor($world));
        $listen = 6 + ($seed % 5);
        $result['listen_s'] = $listen;
        $result['sources_available'] = count($sources);
        $id = $this->store->insertEvent([
            'tenant_id' => $tenantId, 'map_id' => $mapId, 'user_id' => $userId, 'operator' => $operator, 'target_ref' => $ref,
            'target_label' => $t['callsign'], 'target_kind' => $t['kind'], 'action' => 'triangulate', 'status' => 'running',
            'detail_json' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}',
            'ready_at' => TocPhoneStore::dt($now + $listen), 'expires_at' => TocPhoneStore::dt($now + $listen + 600),
        ], $now);
        $cmdId = null;
        if ($t['kind'] === 'player' && $this->journalVisible($tenantId, $t)) {
            // Transparence : l'entrée « localisé par le TOC » du journal du téléphone de la cible (si elle est en ligne).
            $cmdId = ($this->enqueue)($tenantId, (int) ($t['map_id'] ?? 0) > 0 ? (int) $t['map_id'] : $mapId, 'trace', (string) $t['uid'], '', (string) $t['callsign'], ['kind' => 'triangulate'], ['kd' => 'tri', 'by' => self::wireTextMax($operator, 40)], self::TTL_REALTIME, $operator, $userId);
            if ($cmdId !== null) {
                $this->store->updateEvent($tenantId, $id, ['command_id' => $cmdId], $now);
            }
        }
        ($this->audit)('atak_toc.phone.triangulate', $tenantId, $userId, $id, [
            'target' => $ref, 'label' => $t['callsign'], 'sources' => count($sources), 'heard' => $result['heard'], 'status' => $result['status'], 'trace_visible_to_target' => $cmdId !== null,
        ]);

        return ['ok' => true, 'event_id' => $id, 'listen_s' => $listen, 'ready_in' => $listen];
    }

    /* ------------------------------------------------------------------ */
    /* Suivi, journal, historique                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Rapproche les demandes ouvertes de l'état de leur commande (file des commandes web) : reçu, lu, position rendue,
     * expirée. Les triangulations livrent leur résultat à l'heure d'écoute prévue.
     */
    public function refresh(int $tenantId): void
    {
        if (!$this->store->ready()) {
            return;
        }
        $now = $this->now();
        foreach ($this->store->openEvents($tenantId, 120) as $e) {
            $id = (int) $e['id'];
            if ($e['action'] === 'triangulate') {
                $ready = TocPhoneStore::ts((string) $e['ready_at']);
                if ($ready !== null && $ready <= $now) {
                    $d = json_decode((string) $e['detail_json'], true);
                    $pos = is_array($d) && is_array($d['position'] ?? null) ? $d['position'] : null;
                    $this->store->updateEvent($tenantId, $id, [
                        'status' => 'done',
                        'result_text' => (string) ($d['status'] ?? 'none'),
                        'pos_x' => $pos[0] ?? null, 'pos_y' => $pos[1] ?? null, 'accuracy_m' => is_array($d) ? ($d['radius_m'] ?? null) : null,
                    ], $now);
                }
                continue;
            }
            $cid = $e['command_id'] !== null ? (int) $e['command_id'] : 0;
            $exp = TocPhoneStore::ts((string) $e['expires_at']);
            $cmd = $cid > 0 ? ($this->commandInfo)($tenantId, $cid) : null;
            $status = is_array($cmd) ? (string) $cmd['status'] : 'pending';
            $fields = null;
            if ($status === 'accepted' && $e['status'] === 'queued') {
                $fields = ['status' => 'delivered', 'result_text' => (string) ($cmd['result_text'] ?? '')];
            } elseif ($status === 'done') {
                $fields = $this->applyDone($e, is_array($cmd) ? $cmd : []);
            } elseif (in_array($status, ['failed', 'expired', 'cancelled'], true)) {
                $fields = ['status' => $status, 'result_text' => (string) ($cmd['result_text'] ?? '')];
            } elseif ($exp !== null && $exp < $now && in_array($status, ['pending', 'accepted'], true)) {
                $fields = ['status' => 'expired', 'result_text' => $e['action'] === 'locate' ? 'position_unknown' : 'no_answer'];
            }
            if ($fields !== null) {
                $this->store->updateEvent($tenantId, $id, $fields, $now);
            }
        }
    }

    /**
     * @param array<string, mixed> $e
     * @param array<string, mixed> $cmd
     * @return array<string, mixed>
     */
    private function applyDone(array $e, array $cmd): array
    {
        if ($e['action'] !== 'locate') {
            return ['status' => 'done', 'result_text' => (string) ($cmd['result_text'] ?? '')];
        }
        $r = is_array($cmd['result'] ?? null) ? $cmd['result'] : [];
        $x = $r['x'] ?? null;
        $y = $r['y'] ?? null;
        $acc = $r['acc'] ?? ($r['accuracy'] ?? null);
        $ok = is_numeric($x) && is_numeric($y) && is_finite((float) $x) && is_finite((float) $y) && abs((float) $x) <= 100000 && abs((float) $y) <= 100000 && (abs((float) $x) >= 1 || abs((float) $y) >= 1);
        if (!$ok) {
            // Le téléphone a répondu sans position utilisable : « position inconnue », jamais une position inventée.
            return ['status' => 'failed', 'result_text' => 'position_unknown'];
        }

        return [
            'status' => 'done', 'result_text' => 'located', 'pos_x' => round((float) $x, 1), 'pos_y' => round((float) $y, 1),
            'accuracy_m' => is_numeric($acc) ? max(1.0, min(2000.0, round((float) $acc, 1))) : null,
        ];
    }

    /** @param array<string, mixed> $e @return array<string, mixed> */
    private function presentFix(array $e): array
    {
        $at = TocPhoneStore::ts((string) $e['updated_at']) ?? $this->now();

        return [
            'x' => (float) $e['pos_x'], 'y' => (float) $e['pos_y'],
            'accuracy_m' => $e['accuracy_m'] !== null ? (float) $e['accuracy_m'] : null,
            'source' => $e['action'] === 'locate' ? 'phone' : 'estimate',
            'at' => $at, 'age' => max(0, $this->now() - $at), 'event_id' => (int) $e['id'],
        ];
    }

    /**
     * Une demande présentée au navigateur. Le résultat d'une triangulation n'est livré qu'à l'heure d'écoute prévue.
     *
     * @param array<string, mixed> $e
     * @return array<string, mixed>
     */
    public function present(array $e): array
    {
        $now = $this->now();
        $created = TocPhoneStore::ts((string) $e['created_at']) ?? $now;
        $out = [
            'id' => (int) $e['id'],
            'at' => $created,
            'operator' => (string) $e['operator'],
            'target' => (string) $e['target_ref'],
            'label' => (string) $e['target_label'],
            'kind' => (string) $e['target_kind'],
            'action' => (string) $e['action'],
            'level' => $e['level'],
            'message' => $e['message'],
            'ack_requested' => (int) $e['ack_requested'] === 1,
            'status' => (string) $e['status'],
            'result' => (string) $e['result_text'],
            'fix' => null,
            'triangulation' => null,
        ];
        if ($e['status'] === 'running') {
            $ready = TocPhoneStore::ts((string) $e['ready_at']) ?? $now;
            $out['ready_in'] = max(0, $ready - $now);
        }
        if (in_array($e['action'], ['locate', 'triangulate'], true) && $e['status'] === 'done' && $e['pos_x'] !== null) {
            $out['fix'] = $this->presentFix($e);
        }
        if ($e['action'] === 'triangulate' && $e['status'] === 'done') {
            $d = json_decode((string) $e['detail_json'], true);
            $out['triangulation'] = is_array($d) ? $d : null;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function events(int $tenantId, ?string $ref = null, int $limit = 60): array
    {
        if (!$this->store->ready()) {
            return [];
        }
        $this->refresh($tenantId);
        $filter = $ref !== null ? ['target_ref' => $ref] : [];

        return array_map([$this, 'present'], $this->store->events($tenantId, $filter, $limit));
    }

    /**
     * Fiche d'un téléphone : entrée de l'annuaire et son historique (dernières demandes, positions obtenues).
     *
     * @return array<string, mixed>
     */
    public function sheet(int $tenantId, int $mapId, int $userId, string $side, string $ref): array
    {
        $r = $this->resolve($tenantId, $mapId, $userId, $side, $ref);
        if (!$r['ok']) {
            return $r;
        }
        $events = $this->events($tenantId, $ref, 40);
        $fixes = array_values(array_filter($events, static fn (array $e): bool => $e['fix'] !== null));

        return ['ok' => true, 'events' => $events, 'fixes' => array_map(static fn (array $e): array => $e['fix'] + ['action' => $e['action'], 'accuracy_label' => $e['triangulation']['accuracy'] ?? null], array_slice($fixes, 0, 12))];
    }
}
