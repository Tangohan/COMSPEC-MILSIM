<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Container;
use App\Core\Database;
use App\Support\DataCampaignBlocks;
use App\Support\DataCampaignCalendar;
use PDO;

/**
 * Accès aux campagnes de fiabilisation des données (campagnes, réponses des membres, signalements, rappels).
 * Tout est borné par communauté (tenant_id). Classe non finale : les tests remplacent l'accès base par une version
 * en mémoire. Les écrans tolèrent l'absence des tables (schemaReady).
 */
class DataCampaignRepository
{
    public const TABLES = ['data_campaign_settings', 'data_campaigns', 'data_campaign_members', 'data_campaign_flags', 'data_campaign_reminders'];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const FLAG_OPEN = 'open';
    public const FLAG_HANDLED = 'handled';
    public const FLAG_WITHDRAWN = 'withdrawn';

    private ?PDO $pdo;

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    protected function db(): PDO
    {
        return $this->pdo ??= Database::getPdo();
    }

    public function schemaReady(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            $marks = implode(',', array_fill(0, count(self::TABLES), '?'));
            $st = $this->db()->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (' . $marks . ')');
            $st->execute(self::TABLES);

            return self::$ready = ((int) $st->fetchColumn() === count(self::TABLES));
        } catch (\Throwable) {
            return self::$ready = false;
        }
    }

    // ---------------------------------------------------------------- réglages

    /**
     * @return array{auto_enabled: bool, open_md: string, close_md: string, blocks: list<string>, unit_ids: list<int>, reminder_days: list<int>, last_auto_year: int}
     */
    public function settings(int $tenantId): array
    {
        $defaults = [
            'auto_enabled' => false,
            'open_md' => DataCampaignCalendar::DEFAULT_OPEN_MD,
            'close_md' => DataCampaignCalendar::DEFAULT_CLOSE_MD,
            'blocks' => DataCampaignBlocks::keys(),
            'unit_ids' => [],
            'reminder_days' => DataCampaignCalendar::DEFAULT_REMINDER_DAYS,
            'last_auto_year' => 0,
        ];
        if (!$this->schemaReady()) {
            return $defaults;
        }
        try {
            $st = $this->db()->prepare('SELECT * FROM data_campaign_settings WHERE tenant_id = ?');
            $st->execute([$tenantId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return $defaults;
        }

        return is_array($row) ? self::normalizeSettings($row) : $defaults;
    }

    /**
     * @param array<string, mixed> $row
     * @return array{auto_enabled: bool, open_md: string, close_md: string, blocks: list<string>, unit_ids: list<int>, reminder_days: list<int>, last_auto_year: int}
     */
    public static function normalizeSettings(array $row): array
    {
        return [
            'auto_enabled' => !empty($row['auto_enabled']),
            'open_md' => DataCampaignCalendar::normalizeMd((string) ($row['open_md'] ?? ''), DataCampaignCalendar::DEFAULT_OPEN_MD),
            'close_md' => DataCampaignCalendar::normalizeMd((string) ($row['close_md'] ?? ''), DataCampaignCalendar::DEFAULT_CLOSE_MD),
            'blocks' => DataCampaignBlocks::normalize($row['blocks_json'] ?? null),
            'unit_ids' => self::ids($row['unit_ids_json'] ?? null),
            'reminder_days' => DataCampaignCalendar::normalizeReminderDays($row['reminder_days_json'] ?? null),
            'last_auto_year' => (int) ($row['last_auto_year'] ?? 0),
        ];
    }

    /** @param array{auto_enabled: bool, open_md: string, close_md: string, blocks: list<string>, unit_ids: list<int>, reminder_days: list<int>} $s */
    public function saveSettings(int $tenantId, array $s, int $userId): void
    {
        $st = $this->db()->prepare(
            'INSERT INTO data_campaign_settings (tenant_id, auto_enabled, open_md, close_md, blocks_json, unit_ids_json, reminder_days_json, updated_by, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE auto_enabled = VALUES(auto_enabled), open_md = VALUES(open_md), close_md = VALUES(close_md),
               blocks_json = VALUES(blocks_json), unit_ids_json = VALUES(unit_ids_json), reminder_days_json = VALUES(reminder_days_json),
               updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $st->execute([
            $tenantId,
            $s['auto_enabled'] ? 1 : 0,
            $s['open_md'],
            $s['close_md'],
            json_encode($s['blocks']),
            json_encode($s['unit_ids']),
            json_encode($s['reminder_days']),
            $userId > 0 ? $userId : null,
        ]);
    }

    public function markAutoYear(int $tenantId, int $year): void
    {
        $this->db()->prepare('UPDATE data_campaign_settings SET last_auto_year = ? WHERE tenant_id = ?')->execute([$year, $tenantId]);
    }

    /** @return list<int> communautés qui ont au moins une campagne ouverte */
    public function tenantsWithOpenCampaign(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->db()->prepare('SELECT DISTINCT tenant_id FROM data_campaigns WHERE status = ? ORDER BY tenant_id ASC');
        $st->execute([self::STATUS_OPEN]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** Unité principale active d'un membre (0 si aucune). */
    public function primaryUnitId(int $userId): int
    {
        $st = $this->db()->prepare(
            "SELECT unit_id FROM personnel_assignments WHERE user_id = ? AND status = 'active' AND (ended_at IS NULL OR ended_at > CURDATE())
             ORDER BY is_primary DESC, started_at DESC LIMIT 1"
        );
        $st->execute([$userId]);

        return (int) ($st->fetchColumn() ?: 0);
    }

    /** @return list<int> communautés qui ont activé l'ouverture automatique */
    public function tenantsWithAutoEnabled(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->db()->query('SELECT tenant_id FROM data_campaign_settings WHERE auto_enabled = 1 ORDER BY tenant_id ASC');

        return $st === false ? [] : array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // ---------------------------------------------------------------- campagnes

    /**
     * @param array{year: int, title: string, blocks: list<string>, unit_ids: list<int>, reminder_days: list<int>, opens_on: ?string, due_on: ?string, auto: bool} $c
     */
    public function createCampaign(int $tenantId, array $c, int $userId): int
    {
        $st = $this->db()->prepare(
            'INSERT INTO data_campaigns (tenant_id, campaign_year, title, status, blocks_json, unit_ids_json, reminder_days_json, opens_on, due_on, auto_created, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([
            $tenantId,
            $c['year'],
            $c['title'],
            self::STATUS_DRAFT,
            json_encode($c['blocks']),
            json_encode($c['unit_ids']),
            json_encode($c['reminder_days']),
            $c['opens_on'],
            $c['due_on'],
            $c['auto'] ? 1 : 0,
            $userId > 0 ? $userId : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findCampaign(int $id, int $tenantId): ?array
    {
        if ($id < 1) {
            return null;
        }
        $st = $this->db()->prepare('SELECT * FROM data_campaigns WHERE id = ? AND tenant_id = ?');
        $st->execute([$id, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrateCampaign($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listCampaigns(int $tenantId, int $limit = 50): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->db()->prepare('SELECT * FROM data_campaigns WHERE tenant_id = ? ORDER BY campaign_year DESC, id DESC LIMIT ' . max(1, min(200, $limit)));
        $st->execute([$tenantId]);

        return array_map([self::class, 'hydrateCampaign'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Campagne ouverte la plus récente de la communauté (null si aucune). @return array<string, mixed>|null */
    public function currentOpenCampaign(int $tenantId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->db()->prepare('SELECT * FROM data_campaigns WHERE tenant_id = ? AND status = ? ORDER BY campaign_year DESC, id DESC LIMIT 1');
        $st->execute([$tenantId, self::STATUS_OPEN]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrateCampaign($row) : null;
    }

    /** @return list<array<string, mixed>> toutes les campagnes ouvertes de la communauté */
    public function openCampaigns(int $tenantId): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->db()->prepare('SELECT * FROM data_campaigns WHERE tenant_id = ? AND status = ? ORDER BY id ASC');
        $st->execute([$tenantId, self::STATUS_OPEN]);

        return array_map([self::class, 'hydrateCampaign'], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Campagne créée par la tâche planifiée pour cette année, ouverte ou close (null si aucune). @return array<string, mixed>|null */
    public function autoCampaignForYear(int $tenantId, int $year): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM data_campaigns WHERE tenant_id = ? AND campaign_year = ? AND auto_created = 1 ORDER BY id DESC LIMIT 1');
        $st->execute([$tenantId, $year]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrateCampaign($row) : null;
    }

    public function setStatus(int $id, int $tenantId, string $status, int $userId): bool
    {
        if ($status === self::STATUS_OPEN) {
            $sql = 'UPDATE data_campaigns SET status = ?, opened_at = COALESCE(opened_at, NOW()), closed_at = NULL, closed_by = NULL WHERE id = ? AND tenant_id = ?';
            $params = [$status, $id, $tenantId];
        } elseif ($status === self::STATUS_CLOSED) {
            $sql = 'UPDATE data_campaigns SET status = ?, closed_at = NOW(), closed_by = ? WHERE id = ? AND tenant_id = ?';
            $params = [$status, $userId > 0 ? $userId : null, $id, $tenantId];
        } else {
            return false;
        }
        $st = $this->db()->prepare($sql);
        $st->execute($params);

        return $st->rowCount() > 0;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateCampaign(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['tenant_id'] = (int) ($row['tenant_id'] ?? 0);
        $row['campaign_year'] = (int) ($row['campaign_year'] ?? 0);
        $row['blocks'] = DataCampaignBlocks::normalize($row['blocks_json'] ?? null);
        $row['unit_ids'] = self::ids($row['unit_ids_json'] ?? null);
        $row['reminder_days'] = DataCampaignCalendar::normalizeReminderDays($row['reminder_days_json'] ?? null);

        return $row;
    }

    // ---------------------------------------------------------------- membres

    /**
     * Membres actifs de la communauté (hors comptes de service), avec leur unité principale.
     *
     * @return list<array{user_id: int, unit_id: int, display_name: string, callsign: string}>
     */
    public function roster(int $tenantId): array
    {
        $users = Container::get(UserRepository::class);
        $member = $users->sqlMemberOfTenantPredicate('u', $tenantId);
        $st = $this->db()->query(
            "SELECT u.id, u.display_name, u.callsign,
                    (SELECT pa.unit_id FROM personnel_assignments pa WHERE pa.user_id = u.id AND pa.status = 'active'
                       AND (pa.ended_at IS NULL OR pa.ended_at > CURDATE()) ORDER BY pa.is_primary DESC, pa.started_at DESC LIMIT 1) AS unit_id
             FROM users u
             WHERE {$member} AND u.deleted_at IS NULL AND COALESCE(u.is_service_account, 0) = 0 AND COALESCE(u.status, 'active') = 'active'
             ORDER BY u.id ASC"
        );
        $out = [];
        foreach ($st === false ? [] : ($st->fetchAll(PDO::FETCH_ASSOC) ?: []) as $r) {
            $out[] = [
                'user_id' => (int) $r['id'],
                'unit_id' => (int) ($r['unit_id'] ?? 0),
                'display_name' => (string) ($r['display_name'] ?? ''),
                'callsign' => (string) ($r['callsign'] ?? ''),
            ];
        }

        return $out;
    }

    /** @param list<array{user_id: int, unit_id: int}> $members */
    public function addMembers(int $campaignId, int $tenantId, array $members): int
    {
        $n = 0;
        $st = $this->db()->prepare('INSERT IGNORE INTO data_campaign_members (campaign_id, tenant_id, user_id, unit_id, updated_at) VALUES (?, ?, ?, ?, NOW())');
        foreach ($members as $m) {
            $st->execute([$campaignId, $tenantId, $m['user_id'], $m['unit_id'] > 0 ? $m['unit_id'] : null]);
            $n += $st->rowCount();
        }

        return $n;
    }

    /** @return array<string, mixed>|null */
    public function memberRow(int $campaignId, int $userId): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM data_campaign_members WHERE campaign_id = ? AND user_id = ?');
        $st->execute([$campaignId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? self::hydrateMember($row) : null;
    }

    /**
     * Lignes de suivi d'une campagne, avec nom, indicatif, unité et nombre de signalements ouverts.
     *
     * @param list<int>|null $onlyUsers null : tous les membres de la campagne (périmètre complet)
     * @return list<array<string, mixed>>
     */
    public function members(int $campaignId, int $tenantId, ?array $onlyUsers = null): array
    {
        if ($onlyUsers !== null && $onlyUsers === []) {
            return [];
        }
        $where = '';
        $params = [$campaignId, $tenantId];
        if ($onlyUsers !== null) {
            $where = ' AND m.user_id IN (' . implode(',', array_map('intval', $onlyUsers)) . ')';
        }
        $requests = $this->requestsTable();
        $st = $this->db()->prepare(
            "SELECT m.*, u.display_name, u.callsign,
                    (SELECT COUNT(*) FROM data_campaign_flags f LEFT JOIN {$requests} r ON r.id = f.correction_request_id AND r.tenant_id = f.tenant_id
                      WHERE f.campaign_id = m.campaign_id AND f.user_id = m.user_id AND f.status = 'open'
                        AND (f.correction_request_id IS NULL OR r.id IS NULL OR r.status = 'pending')) AS open_flags,
                    (SELECT MAX(r.created_at) FROM data_campaign_reminders r WHERE r.campaign_id = m.campaign_id AND r.user_id = m.user_id) AS last_reminded_at
             FROM data_campaign_members m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.campaign_id = ? AND m.tenant_id = ?{$where}
             ORDER BY u.display_name ASC, m.user_id ASC"
        );
        $st->execute($params);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $row = self::hydrateMember($r);
            $row['display_name'] = (string) ($r['display_name'] ?? '');
            $row['callsign'] = (string) ($r['callsign'] ?? '');
            $row['open_flags'] = (int) ($r['open_flags'] ?? 0);
            $row['last_reminded_at'] = (string) ($r['last_reminded_at'] ?? '');
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function hydrateMember(array $row): array
    {
        $blocks = json_decode((string) ($row['blocks_json'] ?? ''), true);
        $snapshot = json_decode((string) ($row['snapshot_json'] ?? ''), true);
        $row['campaign_id'] = (int) ($row['campaign_id'] ?? 0);
        $row['user_id'] = (int) ($row['user_id'] ?? 0);
        $row['unit_id'] = (int) ($row['unit_id'] ?? 0);
        $row['answers'] = is_array($blocks) ? $blocks : [];
        $row['snapshot'] = is_array($snapshot) ? $snapshot : [];
        $row['confirmed'] = trim((string) ($row['confirmed_at'] ?? '')) !== '';

        return $row;
    }

    /** @param array<string, mixed> $answers */
    public function saveAnswers(int $campaignId, int $userId, array $answers): void
    {
        $this->db()->prepare(
            'UPDATE data_campaign_members SET blocks_json = ?, first_seen_at = COALESCE(first_seen_at, NOW()), updated_at = NOW() WHERE campaign_id = ? AND user_id = ?'
        )->execute([json_encode($answers, JSON_UNESCAPED_UNICODE), $campaignId, $userId]);
    }

    /**
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $snapshot
     */
    public function confirm(int $campaignId, int $userId, array $answers, array $snapshot, string $hash): void
    {
        $this->db()->prepare(
            'UPDATE data_campaign_members SET blocks_json = ?, snapshot_json = ?, snapshot_hash = ?, confirmed_at = NOW(),
               first_seen_at = COALESCE(first_seen_at, NOW()), updated_at = NOW() WHERE campaign_id = ? AND user_id = ?'
        )->execute([json_encode($answers, JSON_UNESCAPED_UNICODE), json_encode($snapshot, JSON_UNESCAPED_UNICODE), $hash, $campaignId, $userId]);
    }

    /**
     * Dernière confirmation du membre (campagne la plus récente), pour la fiche et la chronologie.
     *
     * @return array<string, mixed>|null
     */
    public function latestConfirmation(int $tenantId, int $userId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->db()->prepare(
            'SELECT m.*, c.campaign_year, c.title FROM data_campaign_members m JOIN data_campaigns c ON c.id = m.campaign_id
             WHERE m.tenant_id = ? AND m.user_id = ? AND m.confirmed_at IS NOT NULL ORDER BY m.confirmed_at DESC LIMIT 1'
        );
        $st->execute([$tenantId, $userId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $out = self::hydrateMember($row);
        $out['campaign_year'] = (int) ($row['campaign_year'] ?? 0);
        $out['title'] = (string) ($row['title'] ?? '');

        return $out;
    }

    /**
     * Historique des campagnes du membre (confirmées ou non), de la plus récente à la plus ancienne.
     *
     * @return list<array<string, mixed>>
     */
    public function historyForUser(int $tenantId, int $userId, int $limit = 20): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        $st = $this->db()->prepare(
            'SELECT m.campaign_id, m.confirmed_at, c.campaign_year, c.title, c.status
             FROM data_campaign_members m JOIN data_campaigns c ON c.id = m.campaign_id
             WHERE m.tenant_id = ? AND m.user_id = ? ORDER BY c.campaign_year DESC, c.id DESC LIMIT ' . max(1, min(100, $limit))
        );
        $st->execute([$tenantId, $userId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ---------------------------------------------------------------- signalements

    /** @param array{campaign_id: int, tenant_id: int, user_id: int, block_key: string, message: string, correction_request_id: int|null} $f */
    public function addFlag(array $f): int
    {
        $st = $this->db()->prepare(
            'INSERT INTO data_campaign_flags (campaign_id, tenant_id, user_id, block_key, message, correction_request_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $st->execute([$f['campaign_id'], $f['tenant_id'], $f['user_id'], $f['block_key'], $f['message'], $f['correction_request_id'], self::FLAG_OPEN]);

        return (int) $this->db()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findFlag(int $flagId, int $tenantId): ?array
    {
        $st = $this->db()->prepare('SELECT * FROM data_campaign_flags WHERE id = ? AND tenant_id = ?');
        $st->execute([$flagId, $tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Signalements d'une campagne avec l'état de la demande de correction liée.
     *
     * @param list<int>|null $onlyUsers null : tous
     * @return list<array<string, mixed>>
     */
    public function flags(int $campaignId, int $tenantId, ?string $status = null, ?array $onlyUsers = null): array
    {
        if ($onlyUsers !== null && $onlyUsers === []) {
            return [];
        }
        $where = $status !== null ? ' AND f.status = ?' : '';
        $params = $status !== null ? [$campaignId, $tenantId, $status] : [$campaignId, $tenantId];
        if ($onlyUsers !== null) {
            $where .= ' AND f.user_id IN (' . implode(',', array_map('intval', $onlyUsers)) . ')';
        }
        $requests = $this->requestsTable();
        $st = $this->db()->prepare(
            "SELECT f.*, u.display_name, u.callsign, r.status AS request_status, r.resolution_note AS request_note,
                    (f.status = 'open' AND (f.correction_request_id IS NULL OR r.id IS NULL OR r.status = 'pending')) AS is_open
             FROM data_campaign_flags f
             LEFT JOIN users u ON u.id = f.user_id
             LEFT JOIN {$requests} r ON r.id = f.correction_request_id AND r.tenant_id = f.tenant_id
             WHERE f.campaign_id = ? AND f.tenant_id = ?{$where} ORDER BY f.created_at ASC, f.id ASC"
        );
        $st->execute($params);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Signalements d'un membre dans une campagne, avec l'état de la demande de correction liée (suivi côté membre).
     *
     * @return list<array<string, mixed>>
     */
    public function flagsForUser(int $campaignId, int $tenantId, int $userId): array
    {
        $requests = $this->requestsTable();
        $st = $this->db()->prepare(
            "SELECT f.*, r.status AS request_status, r.resolution_note AS request_note
             FROM data_campaign_flags f
             LEFT JOIN {$requests} r ON r.id = f.correction_request_id AND r.tenant_id = f.tenant_id
             WHERE f.campaign_id = ? AND f.tenant_id = ? AND f.user_id = ? AND f.status <> ? ORDER BY f.created_at ASC, f.id ASC"
        );
        $st->execute([$campaignId, $tenantId, $userId, self::FLAG_WITHDRAWN]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Table des demandes de correction de fiche, ou une table vide de même forme tant qu'elle n'existe pas : les écrans de la
     * campagne restent lisibles même si personne n'a encore ouvert de demande de correction sur ce serveur.
     */
    private function requestsTable(): string
    {
        static $exists = null;
        if ($exists === null) {
            try {
                $st = $this->db()->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_correction_requests' LIMIT 1");
                $exists = $st !== false && (bool) $st->fetchColumn();
            } catch (\Throwable) {
                $exists = false;
            }
        }

        return $exists ? 'personnel_correction_requests' : '(SELECT NULL AS id, NULL AS tenant_id, NULL AS status, NULL AS resolution_note) ';
    }

    public function setFlagStatus(int $flagId, int $tenantId, string $status, int $userId, string $answer = ''): bool
    {
        $st = $this->db()->prepare(
            'UPDATE data_campaign_flags SET status = ?, handled_by = ?, handled_at = NOW(), answer = ? WHERE id = ? AND tenant_id = ? AND status = ?'
        );
        $st->execute([$status, $userId > 0 ? $userId : null, $answer !== '' ? $answer : null, $flagId, $tenantId, self::FLAG_OPEN]);

        return $st->rowCount() > 0;
    }

    // ---------------------------------------------------------------- rappels

    /** @return list<string> rappels automatiques déjà terminés pour tous (repère user_id = 0) */
    public function doneSlots(int $campaignId): array
    {
        $st = $this->db()->prepare('SELECT slot FROM data_campaign_reminders WHERE campaign_id = ? AND user_id = 0');
        $st->execute([$campaignId]);

        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** Réserve l'envoi d'un rappel à un membre : faux s'il est déjà parti (clé unique). */
    public function reserveReminder(int $campaignId, int $tenantId, int $userId, string $slot, int $sentBy = 0): bool
    {
        $st = $this->db()->prepare('INSERT IGNORE INTO data_campaign_reminders (campaign_id, tenant_id, user_id, slot, sent_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $st->execute([$campaignId, $tenantId, $userId, $slot, $sentBy > 0 ? $sentBy : null]);

        return $st->rowCount() > 0;
    }

    /** Dernier rappel (tout type) reçu par ce membre dans cette campagne, ou chaîne vide. */
    public function lastReminderAt(int $campaignId, int $userId): string
    {
        $st = $this->db()->prepare('SELECT MAX(created_at) FROM data_campaign_reminders WHERE campaign_id = ? AND user_id = ?');
        $st->execute([$campaignId, $userId]);

        return (string) ($st->fetchColumn() ?: '');
    }

    /** @return list<int> membres déjà servis pour ce rappel automatique */
    public function usersWithSlot(int $campaignId, string $slot): array
    {
        $st = $this->db()->prepare('SELECT user_id FROM data_campaign_reminders WHERE campaign_id = ? AND slot = ? AND user_id > 0');
        $st->execute([$campaignId, $slot]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // ---------------------------------------------------------------- unités

    /**
     * Unités de la communauté : identifiant => [nom, parent].
     *
     * @return array<int, array{name: string, parent_id: int}>
     */
    public function units(int $tenantId): array
    {
        $st = $this->db()->prepare('SELECT id, name, parent_id FROM units WHERE tenant_id = ?');
        $st->execute([$tenantId]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $out[(int) $r['id']] = ['name' => (string) $r['name'], 'parent_id' => (int) ($r['parent_id'] ?? 0)];
        }

        return $out;
    }

    /** @return list<int> unités commandées par ce membre */
    public function commandedUnitIds(int $tenantId, int $userId): array
    {
        $st = $this->db()->prepare('SELECT id FROM units WHERE tenant_id = ? AND commander_user_id = ?');
        $st->execute([$tenantId, $userId]);

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** @return list<int> */
    public static function ids(mixed $raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            if (is_numeric($v) && (int) $v > 0) {
                $out[(int) $v] = (int) $v;
            }
        }

        return array_values($out);
    }
}
