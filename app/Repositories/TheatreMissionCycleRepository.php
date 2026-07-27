<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Cycle de mission théâtre (préparation → en cours → clôturée).
 * Distinct des missions inter-unités (cooperation) et de l’ID replay mission_{tenant}_map_{map}.
 */
final class TheatreMissionCycleRepository
{
    public const STATUS_PREPARATION = 'preparation';
    public const STATUS_EN_COURS = 'en_cours';
    public const STATUS_CLOTUREE = 'cloturee';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_PREPARATION,
        self::STATUS_EN_COURS,
        self::STATUS_CLOTUREE,
    ];

    private PDO $pdo;

    private bool $ensured = false;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->ensured = true;
        try {
            $migration = dirname(__DIR__, 2) . '/bootstrap/theatre_mission_cycle_migration.php';
            if (is_file($migration)) {
                require_once $migration;
                if (function_exists('run_theatre_mission_cycle_migration')) {
                    run_theatre_mission_cycle_migration($this->pdo);
                }
            }
        } catch (\Throwable) {
            // Table via run-migrations ; ne pas casser le boot.
        }
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'theatre_mission_cycles' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function buildReplayMissionId(int $tenantId, int $mapId): string
    {
        return 'mission_' . $tenantId . '_map_' . max(1, $mapId);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_PREPARATION => 'Préparation',
            self::STATUS_EN_COURS => 'En cours',
            self::STATUS_CLOTUREE => 'Clôturée',
            default => 'Inconnu',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, int $limit = 40): array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $st = $this->pdo->prepare(
            'SELECT * FROM theatre_mission_cycles
             WHERE tenant_id = ?
             ORDER BY
               CASE status
                 WHEN \'en_cours\' THEN 0
                 WHEN \'preparation\' THEN 1
                 ELSE 2
               END,
               updated_at DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId]);

        return $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForTenant(int $tenantId, int $id): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1 || $id < 1) {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM theatre_mission_cycles WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Mission ouverte (en cours) pour une carte, sinon null.
     *
     * @return array<string, mixed>|null
     */
    public function findOpenForMap(int $tenantId, int $mapId): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return null;
        }
        $mapId = max(1, $mapId);
        $st = $this->pdo->prepare(
            'SELECT * FROM theatre_mission_cycles
             WHERE tenant_id = ? AND map_id = ? AND status = ?
             ORDER BY started_at DESC, id DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $mapId, self::STATUS_EN_COURS]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Mission la plus pertinente pour badge / hub (ouverte, sinon en préparation, sinon dernière clôturée).
     *
     * @return array<string, mixed>|null
     */
    public function findCurrentForMap(int $tenantId, int $mapId): ?array
    {
        if (!$this->tablesReady() || $tenantId < 1) {
            return null;
        }
        $mapId = max(1, $mapId);
        $open = $this->findOpenForMap($tenantId, $mapId);
        if ($open !== null) {
            return $open;
        }
        $st = $this->pdo->prepare(
            'SELECT * FROM theatre_mission_cycles
             WHERE tenant_id = ? AND map_id = ?
             ORDER BY
               CASE status
                 WHEN \'preparation\' THEN 0
                 WHEN \'cloturee\' THEN 1
                 ELSE 2
               END,
               updated_at DESC
             LIMIT 1'
        );
        $st->execute([$tenantId, $mapId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{ok:bool,mission?:array<string,mixed>,error?:string}
     */
    public function create(int $tenantId, int $mapId, string $title, ?int $userId): array
    {
        if (!$this->tablesReady()) {
            return ['ok' => false, 'error' => 'Le cycle de mission n’est pas encore disponible. Relancez les migrations.'];
        }
        $title = trim($title);
        if ($title === '') {
            return ['ok' => false, 'error' => 'Indiquez un titre pour la mission.'];
        }
        if (mb_strlen($title) > 200) {
            $title = mb_substr($title, 0, 200);
        }
        $mapId = max(1, $mapId);
        $replayId = self::buildReplayMissionId($tenantId, $mapId);

        $st = $this->pdo->prepare(
            'INSERT INTO theatre_mission_cycles
             (tenant_id, map_id, title, status, replay_mission_id, created_by_user_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $st->execute([
            $tenantId,
            $mapId,
            $title,
            self::STATUS_PREPARATION,
            $replayId,
            $userId,
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $mission = $this->findForTenant($tenantId, $id);

        return $mission
            ? ['ok' => true, 'mission' => $mission]
            : ['ok' => false, 'error' => 'La mission a été créée mais n’a pas pu être relue.'];
    }

    /**
     * Passe en « En cours ». Une seule mission ouverte par carte.
     *
     * @return array{ok:bool,mission?:array<string,mixed>,error?:string}
     */
    public function open(int $tenantId, int $id): array
    {
        $mission = $this->findForTenant($tenantId, $id);
        if ($mission === null) {
            return ['ok' => false, 'error' => 'Mission introuvable.'];
        }
        $status = (string) ($mission['status'] ?? '');
        if ($status === self::STATUS_EN_COURS) {
            return ['ok' => true, 'mission' => $mission];
        }
        if ($status === self::STATUS_CLOTUREE) {
            return ['ok' => false, 'error' => 'Cette mission est déjà clôturée. Créez-en une nouvelle.'];
        }

        $mapId = (int) ($mission['map_id'] ?? 1);
        $existing = $this->findOpenForMap($tenantId, $mapId);
        if ($existing !== null && (int) ($existing['id'] ?? 0) !== $id) {
            $otherTitle = (string) ($existing['title'] ?? 'une autre mission');

            return [
                'ok' => false,
                'error' => '« ' . $otherTitle . ' » est déjà en cours sur cette carte. Clôturez-la avant d’en ouvrir une autre.',
            ];
        }

        $st = $this->pdo->prepare(
            'UPDATE theatre_mission_cycles
             SET status = ?, started_at = COALESCE(started_at, NOW()), updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status = ?
             LIMIT 1'
        );
        $st->execute([self::STATUS_EN_COURS, $tenantId, $id, self::STATUS_PREPARATION]);
        $fresh = $this->findForTenant($tenantId, $id);

        return $fresh
            ? ['ok' => true, 'mission' => $fresh]
            : ['ok' => false, 'error' => 'Impossible d’ouvrir la mission.'];
    }

    /**
     * Clôture la mission et fige la fenêtre de relecture (started_at → ended_at).
     *
     * @return array{ok:bool,mission?:array<string,mixed>,error?:string}
     */
    public function close(int $tenantId, int $id, ?int $userId, ?string $aarSummary = null): array
    {
        $mission = $this->findForTenant($tenantId, $id);
        if ($mission === null) {
            return ['ok' => false, 'error' => 'Mission introuvable.'];
        }
        $status = (string) ($mission['status'] ?? '');
        if ($status === self::STATUS_CLOTUREE) {
            return ['ok' => true, 'mission' => $mission];
        }
        if ($status === self::STATUS_PREPARATION) {
            return ['ok' => false, 'error' => 'Ouvrez d’abord la mission avant de la clôturer.'];
        }

        $summary = $aarSummary !== null ? trim($aarSummary) : null;
        if ($summary === '') {
            $summary = null;
        }

        $st = $this->pdo->prepare(
            'UPDATE theatre_mission_cycles
             SET status = ?,
                 ended_at = NOW(),
                 started_at = COALESCE(started_at, NOW()),
                 aar_summary = COALESCE(?, aar_summary),
                 closed_by_user_id = ?,
                 updated_at = NOW()
             WHERE tenant_id = ? AND id = ? AND status = ?
             LIMIT 1'
        );
        $st->execute([
            self::STATUS_CLOTUREE,
            $summary,
            $userId,
            $tenantId,
            $id,
            self::STATUS_EN_COURS,
        ]);
        $fresh = $this->findForTenant($tenantId, $id);

        return $fresh
            ? ['ok' => true, 'mission' => $fresh]
            : ['ok' => false, 'error' => 'Impossible de clôturer la mission.'];
    }

    /**
     * Présentation métier pour API / UI (sans jargon technique).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function present(array $row): array
    {
        $status = (string) ($row['status'] ?? self::STATUS_PREPARATION);
        $started = $row['started_at'] ?? null;
        $ended = $row['ended_at'] ?? null;
        $replayMissionId = (string) ($row['replay_mission_id'] ?? '');
        $from = is_string($started) && $started !== '' ? $started : null;
        $to = is_string($ended) && $ended !== '' ? $ended : null;

        $aarUrl = null;
        $replayUrl = null;
        if ($status === self::STATUS_CLOTUREE && $replayMissionId !== '') {
            $q = http_build_query(array_filter([
                'from' => $from,
                'to' => $to,
            ], static fn ($v) => $v !== null && $v !== ''));
            $aarUrl = url('api/replay/aar/' . rawurlencode($replayMissionId)) . ($q !== '' ? '?' . $q : '');
            $replayUrl = url('atak') . '#replay';
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'map_id' => (int) ($row['map_id'] ?? 1),
            'title' => (string) ($row['title'] ?? ''),
            'status' => $status,
            'status_label' => self::statusLabel($status),
            'started_at' => $from,
            'ended_at' => $to,
            'aar_summary' => $row['aar_summary'] ?? null,
            'replay_mission_id' => $replayMissionId,
            'window' => [
                'from' => $from,
                'to' => $to,
            ],
            'links' => [
                'briefing' => url('back-office/atak/briefing-slides'),
                'execution' => url('tacmap'),
                'toc' => url('atak'),
                'aar' => $aarUrl,
                'replay' => $replayUrl,
            ],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
