<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\AarCustomForm;
use App\Support\AtakDebriefSpec;
use App\Support\AtakMissionKey;
use App\Support\SteamId;
use PDO;

/**
 * CR de fin d'opération imposé sur le téléphone ATAK (bootstrap/atak_debrief_migration.php).
 *
 * Une demande (atak_debriefs) part du site (back-office, suivi des CR) ou du jeu (Zeus, UID autorisé) ; chaque joueur visé
 * a sa ligne (atak_debrief_recipients). Du site, le joueur reçoit un avis ATAK de genre « debrief » (atak_admin_messages)
 * que son téléphone récupère par « AdminControl.Poll » ; ses réponses reviennent par POST /api/atak/reports (type DEBRIEF).
 * Le suivi croise ces lignes avec la dernière synchro de l'ATAK (atak_terminals) et les remontées de la session.
 */
final class AtakDebriefRepository
{
    /** États d'un joueur dans le suivi, dans l'ordre de la frise. */
    public const STATES = [
        'sent' => 'Envoyé',
        'delivered' => 'Affiché',
        'opened' => 'Ouvert',
        'submitted' => 'Remis',
        'read' => 'Lu',
        'expired' => 'Expiré',
        'cancelled' => 'Annulé',
    ];

    private ?bool $ready = null;

    public function __construct(private ?PDO $pdo = null)
    {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    /** Crée les tables au premier usage. */
    public function ready(): bool
    {
        if ($this->ready !== null) {
            return $this->ready;
        }
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/atak_debrief_migration.php';
            ob_start();
            try {
                run_atak_debrief_migration($this->pdo());
            } finally {
                ob_end_clean();
            }
            $this->ready = true;
        } catch (\Throwable) {
            $this->ready = false;
        }

        return $this->ready;
    }

    // ── Réglages de la communauté ───────────────────────────────────────────

    /** @return array{default_mode: string, template_id: int, intro: string, zeus_allowed: bool, authorized_uids: list<string>} */
    public function settings(int $tenantId): array
    {
        $row = $this->ready() ? ($this->rows('SELECT * FROM atak_debrief_settings WHERE tenant_id = ? LIMIT 1', [$tenantId])[0] ?? null) : null;

        return [
            'default_mode' => isset(AtakDebriefSpec::MODES[(string) ($row['default_mode'] ?? '')]) ? (string) $row['default_mode'] : 'fill',
            'template_id' => (int) ($row['template_id'] ?? 0),
            'intro' => (string) ($row['intro'] ?? ''),
            'zeus_allowed' => $row === null || (int) ($row['zeus_allowed'] ?? 1) === 1,
            'authorized_uids' => AtakDebriefSpec::parseUids((string) ($row['authorized_uids'] ?? '')),
        ];
    }

    /** @param array{default_mode: string, template_id: int, intro: string, zeus_allowed: bool, authorized_uids: list<string>} $s */
    public function saveSettings(int $tenantId, array $s, ?int $actorId): bool
    {
        if ($tenantId < 1 || !$this->ready()) {
            return false;
        }

        $this->exec(
            'INSERT INTO atak_debrief_settings (tenant_id, default_mode, template_id, intro, zeus_allowed, authorized_uids, updated_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE default_mode = VALUES(default_mode), template_id = VALUES(template_id), intro = VALUES(intro),
               zeus_allowed = VALUES(zeus_allowed), authorized_uids = VALUES(authorized_uids), updated_by_user_id = VALUES(updated_by_user_id)',
            [
                $tenantId,
                isset(AtakDebriefSpec::MODES[$s['default_mode']]) ? $s['default_mode'] : 'fill',
                $s['template_id'] > 0 ? $s['template_id'] : null,
                mb_substr(trim($s['intro']), 0, 600) ?: null,
                $s['zeus_allowed'] ? 1 : 0,
                implode(',', array_slice($s['authorized_uids'], 0, 100)) ?: null,
                $actorId,
            ]
        );

        return true;
    }

    /**
     * Le joueur peut-il imposer le CR depuis son téléphone : administrateur de la plateforme, gestionnaire de la communauté
     * (admin.organization / admin.access) ou UID Steam autorisé dans les réglages. Les Zeus le peuvent en jeu de toute façon
     * (contrôlé par le serveur Arma), sauf si la communauté l'a retiré.
     */
    public function playerMayForce(int $tenantId, ?int $userId, ?string $steam): bool
    {
        $settings = $this->settings($tenantId);
        if ($steam !== null && in_array($steam, $settings['authorized_uids'], true)) {
            return true;
        }
        if ($userId === null || $userId < 1) {
            return false;
        }
        try {
            $row = $this->rows('SELECT is_platform_admin FROM users WHERE id = ? LIMIT 1', [$userId])[0] ?? null;
            if ((int) ($row['is_platform_admin'] ?? 0) === 1) {
                return true;
            }
        } catch (\Throwable) {
        }
        $perm = "p.slug IN ('admin.organization', 'admin.access', 'admin.system')";
        foreach ([
            "SELECT 1 FROM users u JOIN role_permissions rp ON rp.role_id = u.role_id JOIN permissions p ON p.id = rp.permission_id
             WHERE u.id = ? AND {$perm} LIMIT 1",
            "SELECT 1 FROM tenant_user_roles tur JOIN role_permissions rp ON rp.role_id = tur.role_id JOIN permissions p ON p.id = rp.permission_id
             WHERE tur.user_id = ? AND tur.tenant_id = ? AND {$perm} LIMIT 1",
        ] as $i => $sql) {
            if ($this->rows($sql, $i === 0 ? [$userId] : [$userId, $tenantId]) !== []) {
                return true;
            }
        }

        return false;
    }

    // ── Demandes ────────────────────────────────────────────────────────────

    /**
     * @param array{tenant_id: int, mission_key: string, label: string, mode: string, template_id?: ?int, aar_report_id?: ?int,
     *   intro?: string, read_text?: string, questions?: list<array<string, mixed>>, source?: string, game_ref?: ?string,
     *   actor_id?: ?int, actor_label?: string, expires_at?: ?string} $d
     */
    public function create(array $d): int
    {
        if ($d['tenant_id'] < 1 || !$this->ready()) {
            return 0;
        }
        $st = $this->pdo()->prepare(
            'INSERT INTO atak_debriefs (tenant_id, mission_key, label, mode, template_id, aar_report_id, intro, read_text, questions_json,
               source, game_ref, created_by_user_id, created_by_label, created_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ?)'
        );
        $st->execute([
            $d['tenant_id'],
            AtakMissionKey::normalize($d['mission_key']),
            mb_substr(trim($d['label']) ?: 'Fin d’opération', 0, 200),
            isset(AtakDebriefSpec::MODES[$d['mode']]) ? $d['mode'] : 'fill',
            ($d['template_id'] ?? 0) ?: null,
            ($d['aar_report_id'] ?? 0) ?: null,
            mb_substr(trim((string) ($d['intro'] ?? '')), 0, 600) ?: null,
            trim((string) ($d['read_text'] ?? '')) ?: null,
            json_encode(array_values($d['questions'] ?? []), JSON_UNESCAPED_UNICODE),
            ($d['source'] ?? 'site') === 'game' ? 'game' : 'site',
            isset($d['game_ref']) && $d['game_ref'] !== '' ? mb_substr((string) $d['game_ref'], 0, 64) : null,
            $d['actor_id'] ?? null,
            mb_substr((string) ($d['actor_label'] ?? ''), 0, 120) ?: null,
            $d['expires_at'] ?? null,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        if ($id < 1 || !$this->ready()) {
            return null;
        }
        $row = $this->rows('SELECT * FROM atak_debriefs WHERE id = ? AND tenant_id = ? LIMIT 1', [$id, $tenantId])[0] ?? null;

        return $row !== null ? $this->present($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function findByGameRef(int $tenantId, string $ref): ?array
    {
        if ($ref === '' || !$this->ready()) {
            return null;
        }
        $row = $this->rows('SELECT * FROM atak_debriefs WHERE tenant_id = ? AND game_ref = ? LIMIT 1', [$tenantId, mb_substr($ref, 0, 64)])[0] ?? null;

        return $row !== null ? $this->present($row) : null;
    }

    /**
     * Demandes de la communauté, plus récentes d'abord, avec leurs compteurs.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, int $limit = 60): array
    {
        if (!$this->ready()) {
            return [];
        }
        $rows = $this->rows(
            'SELECT d.*, COUNT(r.id) AS n_total,
                    SUM(r.submitted_at IS NOT NULL) AS n_submitted, SUM(r.read_at IS NOT NULL) AS n_read,
                    SUM(r.delivered_at IS NOT NULL OR m.delivered_at IS NOT NULL OR r.opened_at IS NOT NULL) AS n_delivered
             FROM atak_debriefs d
             LEFT JOIN atak_debrief_recipients r ON r.debrief_id = d.id
             LEFT JOIN atak_admin_messages m ON m.id = r.message_id
             WHERE d.tenant_id = ?
             GROUP BY d.id ORDER BY d.id DESC LIMIT ' . max(1, min(200, $limit)),
            [$tenantId]
        );

        return array_map(fn (array $r): array => $this->present($r), $rows);
    }

    public function close(int $tenantId, int $id): bool
    {
        if (!$this->ready() || $this->find($tenantId, $id) === null) {
            return false;
        }
        $this->exec('UPDATE atak_debriefs SET closed_at = UTC_TIMESTAMP() WHERE id = ? AND tenant_id = ? AND closed_at IS NULL', [$id, $tenantId]);
        // Les avis pas encore validés ne s'afficheront plus.
        $this->exec(
            'UPDATE atak_admin_messages m JOIN atak_debrief_recipients r ON r.message_id = m.id
             SET m.cancelled_at = UTC_TIMESTAMP() WHERE r.debrief_id = ? AND m.acked_at IS NULL AND m.cancelled_at IS NULL',
            [$id]
        );

        return true;
    }

    // ── Joueurs visés ───────────────────────────────────────────────────────

    public function addRecipient(int $debriefId, int $tenantId, ?int $userId, ?string $steam, string $callsign, ?int $messageId = null): int
    {
        $steam = $steam !== null ? SteamId::normalize($steam) : null;
        $who = self::whoKey($userId, $steam);
        if ($who === '' || !$this->ready()) {
            return 0;
        }
        // Même joueur connu par son compte et par son Steam (demande du jeu puis réponse authentifiée) : une seule ligne.
        $match = array_filter([
            $userId ? ['(r.who_key = ? OR r.user_id = ?)', ['u:' . $userId, $userId]] : null,
            $steam !== null ? ['(r.who_key = ? OR r.steam_uid = ?)', ['s:' . $steam, $steam]] : null,
        ]);
        $existing = $this->rows(
            'SELECT r.id FROM atak_debrief_recipients r WHERE r.debrief_id = ? AND (' . implode(' OR ', array_column($match, 0)) . ') ORDER BY r.id LIMIT 1',
            array_merge([$debriefId], ...array_column($match, 1))
        )[0]['id'] ?? null;
        if ($existing !== null) {
            $this->exec(
                'UPDATE atak_debrief_recipients SET user_id = COALESCE(?, user_id), steam_uid = COALESCE(?, steam_uid),
                   callsign = COALESCE(NULLIF(?, \'\'), callsign), message_id = COALESCE(?, message_id) WHERE id = ?',
                [$userId ?: null, $steam, mb_substr(trim($callsign), 0, 80), $messageId ?: null, (int) $existing]
            );

            return (int) $existing;
        }
        $this->exec(
            'INSERT INTO atak_debrief_recipients (debrief_id, tenant_id, who_key, user_id, steam_uid, callsign, message_id, sent_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE user_id = COALESCE(VALUES(user_id), user_id), steam_uid = COALESCE(VALUES(steam_uid), steam_uid),
               callsign = COALESCE(NULLIF(VALUES(callsign), \'\'), callsign), message_id = COALESCE(VALUES(message_id), message_id)',
            [$debriefId, $tenantId, $who, $userId ?: null, $steam, mb_substr(trim($callsign), 0, 80), $messageId ?: null]
        );

        return (int) ($this->rows('SELECT id FROM atak_debrief_recipients WHERE debrief_id = ? AND who_key = ? LIMIT 1', [$debriefId, $who])[0]['id'] ?? 0);
    }

    public function setRecipientMessage(int $recipientId, int $messageId): void
    {
        $this->exec('UPDATE atak_debrief_recipients SET message_id = ?, sent_at = UTC_TIMESTAMP() WHERE id = ?', [$messageId, $recipientId]);
    }

    /**
     * Joueurs d'une demande avec l'état de leur avis ATAK et la dernière synchro de leur téléphone.
     *
     * @return list<array<string, mixed>>
     */
    public function recipients(int $tenantId, int $debriefId): array
    {
        if (!$this->ready()) {
            return [];
        }
        $rows = $this->rows(
            'SELECT r.*, m.delivered_at AS msg_delivered_at, m.acked_at AS msg_acked_at, m.cancelled_at AS msg_cancelled_at, m.expires_at AS msg_expires_at,
                    u.display_name, u.callsign AS user_callsign, u.steam_id AS user_steam
             FROM atak_debrief_recipients r
             LEFT JOIN atak_admin_messages m ON m.id = r.message_id
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.debrief_id = ? AND r.tenant_id = ?
             ORDER BY r.submitted_at IS NULL, r.read_at IS NULL, r.callsign, r.id',
            [$debriefId, $tenantId]
        );
        foreach ($rows as &$r) {
            $r['answers'] = is_string($r['answers_json'] ?? null) ? (json_decode((string) $r['answers_json'], true) ?: []) : [];
        }
        unset($r);

        return $rows;
    }

    /**
     * Demande et joueur liés à des avis ATAK (pour la fiche envoyée au téléphone).
     *
     * @param list<int> $messageIds
     * @return array<int, array<string, mixed>> id d'avis → demande
     */
    public function debriefsForMessages(array $messageIds): array
    {
        $ids = array_values(array_filter(array_map('intval', $messageIds), static fn (int $i): bool => $i > 0));
        if ($ids === [] || !$this->ready()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $out = [];
        foreach ($this->rows(
            "SELECT r.message_id, d.* FROM atak_debrief_recipients r JOIN atak_debriefs d ON d.id = r.debrief_id WHERE r.message_id IN ({$in})",
            $ids
        ) as $row) {
            $out[(int) $row['message_id']] = $this->present($row);
        }

        return $out;
    }

    /** L'ATAK a affiché (delivered) ou validé (acked) l'avis : CR lu pour une demande « à lire ». @param list<int> $messageIds */
    public function markMessages(array $messageIds, string $state): void
    {
        $ids = array_values(array_filter(array_map('intval', $messageIds), static fn (int $i): bool => $i > 0));
        if ($ids === [] || !$this->ready()) {
            return;
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        if ($state === 'acked') {
            $this->exec(
                "UPDATE atak_debrief_recipients r JOIN atak_debriefs d ON d.id = r.debrief_id
                 SET r.read_at = COALESCE(r.read_at, UTC_TIMESTAMP()), r.opened_at = COALESCE(r.opened_at, UTC_TIMESTAMP())
                 WHERE d.mode = 'read' AND r.message_id IN ({$in})",
                $ids
            );
        }
        $this->exec("UPDATE atak_debrief_recipients SET delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP()) WHERE message_id IN ({$in})", $ids);
    }

    /**
     * Ce que le téléphone remonte : « opened » (fiche ouverte), « read » (lu), « submitted » (réponses).
     *
     * @param array<string, string> $answers
     */
    public function recordEvent(int $tenantId, int $debriefId, ?int $userId, ?string $steam, string $callsign, string $event, array $answers = [], ?int $reportId = null): bool
    {
        $rid = $this->addRecipient($debriefId, $tenantId, $userId, $steam, $callsign);
        if ($rid < 1) {
            return false;
        }
        $set = match ($event) {
            'submitted' => 'submitted_at = UTC_TIMESTAMP(), opened_at = COALESCE(opened_at, UTC_TIMESTAMP()), answers_json = ?, report_id = COALESCE(?, report_id)',
            'read' => 'read_at = COALESCE(read_at, UTC_TIMESTAMP()), opened_at = COALESCE(opened_at, UTC_TIMESTAMP())',
            default => 'opened_at = COALESCE(opened_at, UTC_TIMESTAMP())',
        };
        $args = $event === 'submitted' ? [json_encode($answers, JSON_UNESCAPED_UNICODE), $reportId] : [];
        $args[] = $rid;
        $this->exec("UPDATE atak_debrief_recipients SET delivered_at = COALESCE(delivered_at, UTC_TIMESTAMP()), {$set} WHERE id = ?", $args);
        if (in_array($event, ['submitted', 'read'], true)) {
            // Avis du site validé : il ne revient plus sur le téléphone.
            $this->exec(
                'UPDATE atak_admin_messages m JOIN atak_debrief_recipients r ON r.message_id = m.id
                 SET m.acked_at = COALESCE(m.acked_at, UTC_TIMESTAMP()), m.delivered_at = COALESCE(m.delivered_at, UTC_TIMESTAMP()) WHERE r.id = ?',
                [$rid]
            );
        }

        return true;
    }

    // ── Opérations et joueurs ───────────────────────────────────────────────

    /**
     * Sessions jouées récentes (clé de mission), d'après le temps d'écran et les remontées.
     *
     * @return list<array{mission_key: string, label: string, day: string, players: int}>
     */
    public function recentOperations(int $tenantId, int $limit = 30): array
    {
        $out = [];
        foreach ($this->rows(
            "SELECT mission_key, MAX(day) AS day, COUNT(DISTINCT user_id) AS players FROM atak_screen_time
             WHERE tenant_id = ? AND mission_key <> '' GROUP BY mission_key ORDER BY MAX(day) DESC, mission_key DESC LIMIT " . max(1, min(100, $limit)),
            [$tenantId]
        ) as $r) {
            $out[(string) $r['mission_key']] = ['mission_key' => (string) $r['mission_key'], 'label' => AtakMissionKey::label((string) $r['mission_key']), 'day' => (string) $r['day'], 'players' => (int) $r['players']];
        }
        foreach ($this->rows(
            "SELECT mission_key, DATE(MAX(created_at)) AS day, COUNT(DISTINCT COALESCE(submitter_user_id, submitter_steam_id)) AS players FROM atak_tactical_reports
             WHERE tenant_id = ? AND mission_key IS NOT NULL AND mission_key <> '' AND created_at > (UTC_TIMESTAMP() - INTERVAL 60 DAY)
             GROUP BY mission_key ORDER BY MAX(created_at) DESC LIMIT 30",
            [$tenantId]
        ) as $r) {
            $out[(string) $r['mission_key']] ??= ['mission_key' => (string) $r['mission_key'], 'label' => AtakMissionKey::label((string) $r['mission_key']), 'day' => (string) $r['day'], 'players' => (int) $r['players']];
        }
        uasort($out, static fn (array $a, array $b): int => strcmp(self::keyStamp($b['mission_key']) ?: $b['day'], self::keyStamp($a['mission_key']) ?: $a['day']));

        return array_values(array_slice($out, 0, $limit));
    }

    /**
     * Joueurs d'une session : temps d'écran (rôle le plus tenu), remontées envoyées.
     *
     * @return list<array{user_id: ?int, steam_uid: ?string, callsign: string, role: string}>
     */
    public function participants(int $tenantId, string $missionKey): array
    {
        $missionKey = AtakMissionKey::normalize($missionKey);
        if ($missionKey === '') {
            return [];
        }
        $out = [];
        foreach ($this->rows(
            "SELECT t.user_id, MAX(u.steam_id) AS steam_id, MAX(COALESCE(NULLIF(u.callsign, ''), u.display_name)) AS callsign,
                    MAX(CASE WHEN t.kind = 'role' THEN t.item_label END) AS role
             FROM atak_screen_time t LEFT JOIN users u ON u.id = t.user_id
             WHERE t.tenant_id = ? AND t.mission_key = ? GROUP BY t.user_id LIMIT 300",
            [$tenantId, $missionKey]
        ) as $r) {
            $who = self::whoKey((int) $r['user_id'], null);
            $out[$who] = ['user_id' => (int) $r['user_id'], 'steam_uid' => SteamId::normalize((string) ($r['steam_id'] ?? '')), 'callsign' => (string) ($r['callsign'] ?? ''), 'role' => self::roleLabel((string) ($r['role'] ?? ''))];
        }
        foreach ($this->rows(
            'SELECT submitter_user_id, MAX(submitter_steam_id) AS steam, MAX(submitter_callsign) AS callsign, MAX(submitter_unit) AS unit
             FROM atak_tactical_reports WHERE tenant_id = ? AND mission_key = ? GROUP BY submitter_user_id, submitter_steam_id LIMIT 300',
            [$tenantId, $missionKey]
        ) as $r) {
            $uid = (int) ($r['submitter_user_id'] ?? 0);
            $steam = SteamId::normalize((string) ($r['steam'] ?? ''));
            $who = self::whoKey($uid ?: null, $steam);
            if ($who === '' || isset($out[$who])) {
                continue;
            }
            $out[$who] = ['user_id' => $uid ?: null, 'steam_uid' => $steam, 'callsign' => (string) ($r['callsign'] ?? ''), 'role' => (string) ($r['unit'] ?? '')];
        }

        return array_values($out);
    }

    /**
     * Joueurs dont un ATAK s'est synchronisé récemment (pour une demande hors session).
     *
     * @return list<array{user_id: ?int, steam_uid: ?string, callsign: string, role: string}>
     */
    public function recentlySynced(int $tenantId, int $minutes): array
    {
        $out = [];
        foreach ($this->rows(
            "SELECT t.user_id, MAX(u.steam_id) AS steam_id, MAX(COALESCE(NULLIF(u.callsign, ''), NULLIF(t.operator_callsign, ''), u.display_name)) AS callsign
             FROM atak_terminals t LEFT JOIN users u ON u.id = t.user_id
             WHERE t.tenant_id = ? AND t.user_id IS NOT NULL AND t.terminal_type <> 'web' AND t.terminal_uid NOT LIKE 'WEB-%' AND t.terminal_uid NOT LIKE 'SESSION-%'
               AND t.last_seen_at > (UTC_TIMESTAMP() - INTERVAL " . max(1, min(60 * 24 * 30, $minutes)) . ' MINUTE)
             GROUP BY t.user_id LIMIT 300',
            [$tenantId]
        ) as $r) {
            $out[] = ['user_id' => (int) $r['user_id'], 'steam_uid' => SteamId::normalize((string) ($r['steam_id'] ?? '')), 'callsign' => (string) ($r['callsign'] ?? ''), 'role' => ''];
        }

        return $out;
    }

    /**
     * Dernière synchro des ATAK de ces joueurs (atak_terminals.last_seen_at), par compte puis par Steam.
     *
     * @param list<int> $userIds
     * @param list<string> $steams
     * @return array{users: array<int, array{at: string, version: string}>, steams: array<string, array{at: string, version: string}>}
     */
    public function lastSync(int $tenantId, array $userIds, array $steams): array
    {
        $out = ['users' => [], 'steams' => []];
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn (int $i): bool => $i > 0)));
        if ($userIds !== []) {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            foreach ($this->rows(
                "SELECT t.user_id, MAX(t.last_seen_at) AS at, MAX(t.mod_version) AS version FROM atak_terminals t
                 WHERE t.tenant_id = ? AND t.user_id IN ({$in}) AND t.terminal_type <> 'web' AND t.terminal_uid NOT LIKE 'WEB-%' GROUP BY t.user_id",
                array_merge([$tenantId], $userIds)
            ) ?: $this->rows(
                "SELECT t.user_id, MAX(t.last_seen_at) AS at, '' AS version FROM atak_terminals t
                 WHERE t.tenant_id = ? AND t.user_id IN ({$in}) AND t.terminal_type <> 'web' GROUP BY t.user_id",
                array_merge([$tenantId], $userIds)
            ) as $r) {
                $out['users'][(int) $r['user_id']] = ['at' => (string) ($r['at'] ?? ''), 'version' => (string) ($r['version'] ?? '')];
            }
        }
        $steams = array_values(array_unique(array_filter(array_map(static fn ($s): ?string => SteamId::normalize((string) $s), $steams))));
        if ($steams !== []) {
            $in = implode(',', array_fill(0, count($steams), '?'));
            foreach ($this->rows(
                "SELECT u.steam_id, MAX(t.last_seen_at) AS at FROM atak_terminals t JOIN users u ON u.id = t.user_id
                 WHERE t.tenant_id = ? AND u.steam_id IN ({$in}) AND t.terminal_type <> 'web' GROUP BY u.steam_id",
                array_merge([$tenantId], $steams)
            ) as $r) {
                $out['steams'][(string) $r['steam_id']] = ['at' => (string) ($r['at'] ?? ''), 'version' => ''];
            }
        }

        return $out;
    }

    /**
     * Remontées envoyées pendant la session, par joueur (hors CR de fin d'opération).
     *
     * @return array<string, array{n: int, last: string}> clé whoKey
     */
    public function reportCounts(int $tenantId, string $missionKey): array
    {
        $missionKey = AtakMissionKey::normalize($missionKey);
        if ($missionKey === '') {
            return [];
        }
        $out = [];
        foreach ($this->rows(
            "SELECT submitter_user_id, submitter_steam_id, COUNT(*) AS n, MAX(created_at) AS last FROM atak_tactical_reports
             WHERE tenant_id = ? AND mission_key = ? AND report_type <> 'DEBRIEF' GROUP BY submitter_user_id, submitter_steam_id",
            [$tenantId, $missionKey]
        ) as $r) {
            $n = ['n' => (int) $r['n'], 'last' => (string) ($r['last'] ?? '')];
            $uid = (int) ($r['submitter_user_id'] ?? 0);
            $steam = SteamId::normalize((string) ($r['submitter_steam_id'] ?? ''));
            foreach (array_filter([$uid > 0 ? self::whoKey($uid, null) : '', $steam !== null ? self::whoKey(null, $steam) : '']) as $k) {
                $out[$k] = isset($out[$k]) ? ['n' => $out[$k]['n'] + $n['n'], 'last' => max($out[$k]['last'], $n['last'])] : $n;
            }
        }

        return $out;
    }

    /** État d'un joueur dans le suivi. @param array<string, mixed> $r ligne de recipients() */
    public static function state(array $r, string $mode): string
    {
        return match (true) {
            !empty($r['submitted_at']) => 'submitted',
            !empty($r['read_at']) || ($mode === 'read' && !empty($r['msg_acked_at'])) => 'read',
            !empty($r['msg_cancelled_at']) => 'cancelled',
            !empty($r['msg_expires_at']) && \App\Support\UtcDateTime::isPast($r['msg_expires_at']) && empty($r['delivered_at']) && empty($r['msg_delivered_at']) => 'expired',
            !empty($r['opened_at']) => 'opened',
            !empty($r['delivered_at']) || !empty($r['msg_delivered_at']) => 'delivered',
            default => 'sent',
        };
    }

    public static function whoKey(?int $userId, ?string $steam): string
    {
        if ($userId !== null && $userId > 0) {
            return 'u:' . $userId;
        }
        $steam = $steam !== null ? SteamId::normalize($steam) : null;

        return $steam !== null ? 's:' . $steam : '';
    }

    /** @param array<string, mixed> $row */
    private function present(array $row): array
    {
        $q = is_string($row['questions_json'] ?? null) ? json_decode((string) $row['questions_json'], true) : [];
        $row['questions'] = AarCustomForm::normalizeFields(is_array($q) ? $q : []);
        $row['id'] = (int) $row['id'];
        $row['mission_label'] = (string) ($row['mission_key'] ?? '') !== '' ? AtakMissionKey::label((string) $row['mission_key']) : '';
        foreach (['n_total', 'n_submitted', 'n_read', 'n_delivered'] as $k) {
            if (array_key_exists($k, $row)) {
                $row[$k] = (int) $row[$k];
            }
        }

        return $row;
    }

    /** « SLOT:Fusilier » → « Fusilier ». */
    private static function roleLabel(string $role): string
    {
        return str_starts_with($role, 'SLOT:') ? substr($role, 5) : $role;
    }

    /** Horodatage AAAAMMJJHHMM de la clé « nom@carte#AAAAMMJJHHMM », '' sinon. */
    private static function keyStamp(string $key): string
    {
        $parts = explode('#', $key, 2);

        return isset($parts[1]) && preg_match('/^\d{8,14}$/', $parts[1]) ? substr($parts[1], 0, 4) . '-' . substr($parts[1], 4, 2) . '-' . substr($parts[1], 6, 2) . substr($parts[1], 8) : '';
    }

    /** @param list<mixed> $args @return list<array<string, mixed>> */
    private function rows(string $sql, array $args = []): array
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);

            return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /** @param list<mixed> $args */
    private function exec(string $sql, array $args = []): int
    {
        try {
            $st = $this->pdo()->prepare($sql);
            $st->execute($args);

            return $st->rowCount();
        } catch (\Throwable) {
            return 0;
        }
    }
}
