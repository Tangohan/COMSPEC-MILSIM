<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Ordres C2 ATAK (alignés sur le modèle jeu COMSPEC_Orders + cycle WEB enrichi).
 *
 * Statuts :
 * - PENDING   : Émis (en transit radio éventuel)
 * - DELIVERED : Reçu (visible destinataire)
 * - ACK       : Confirmé (accusé de réception)
 * - EXEC      : En cours d’exécution
 * - FAILED    : Échec
 * - CANCELLED : Annulé
 *
 * « En retard » = drapeau calculé (is_overdue) si past ack_deadline_at sans ACK.
 */
class AtakOrderRepository
{
    public const TYPES = ['MOVE', 'HOLD', 'RECON', 'CAS', 'QRF', 'CUSTOM'];
    public const PRIORITIES = ['ROUTINE', 'IMPORTANT', 'URGENT', 'CONTACT'];
    public const STATUSES = ['PENDING', 'DELIVERED', 'ACK', 'EXEC', 'FAILED', 'CANCELLED'];
    public const TARGET_TYPES = ['all', 'user', 'group', 'fire_team', 'channel', 'solo'];
    public const CHANNELS = ['GLOBAL', 'COMMAND', 'SQUAD', 'JTAC', 'AIR'];
    public const SIM_STATES = ['queued', 'transmitting', 'jammed', 'retransmit', 'delivered', 'lost'];

    private PDO $pdo;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tablesReady(): bool
    {
        try {
            $st = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_orders' LIMIT 1"
            );

            return $st !== false && (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    public function v2ColumnsReady(): bool
    {
        return $this->hasColumn('target_type') && $this->hasColumn('deliver_at');
    }

    private function hasColumn(string $column): bool
    {
        if (isset($this->columnCache[$column])) {
            return $this->columnCache[$column];
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'atak_orders' AND COLUMN_NAME = ? LIMIT 1"
            );
            $st->execute([$column]);
            $this->columnCache[$column] = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->columnCache[$column] = false;
        }

        return $this->columnCache[$column];
    }

    /**
     * Liste les ordres d’une carte.
     * Avec $since (datetime SQL), ne renvoie que les lignes créées/modifiées depuis
     * (y compris CANCELLED = tombstone d’annulation). En vue émetteur, inclut aussi
     * les PENDING encore en transit radio pour que le client puisse animer l’état.
     *
     * @return list<array<string, mixed>>
     */
    public function listForMap(
        int $tenantId,
        int $mapId,
        int $limit = 80,
        bool $issuerView = true,
        ?string $since = null
    ): array {
        if (!$this->tablesReady()) {
            return [];
        }
        $since = $this->normalizeSince($since);
        $isDelta = $since !== null;
        // Delta : plafond plus haut (petits paquets attendus). Snapshot : limite métier.
        $limit = max(1, min($isDelta ? 500 : 200, $limit));

        $sql = 'SELECT * FROM atak_orders WHERE tenant_id = ? AND map_id = ?';
        $params = [$tenantId, $mapId];
        if ($isDelta) {
            if ($issuerView && $this->v2ColumnsReady()) {
                $sql .= ' AND (
                    updated_at >= ? OR created_at >= ?
                    OR (UPPER(status) = \'PENDING\' AND deliver_at IS NOT NULL AND deliver_at > NOW())
                )';
            } else {
                $sql .= ' AND (updated_at >= ? OR created_at >= ?)';
            }
            $params[] = $since;
            $params[] = $since;
        }
        $sql .= ' ORDER BY updated_at DESC, id DESC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $row = $this->progressDelivery($row, $issuerView);
            if ($row === null) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Compteurs carte (SQL léger, indépendants du delta) pour badge / résumé.
     * N’applique pas le filtre destinataire jeu — réservé à la vue web émetteur.
     *
     * @return array{total: int, pending: int, overdue: int}
     */
    public function countStatsForMap(int $tenantId, int $mapId): array
    {
        if (!$this->tablesReady()) {
            return ['total' => 0, 'pending' => 0, 'overdue' => 0];
        }

        $hasDeadline = $this->hasColumn('ack_deadline_at');
        $overdueExpr = $hasDeadline
            ? "SUM(CASE WHEN UPPER(status) IN ('PENDING','DELIVERED')
                    AND ack_deadline_at IS NOT NULL AND ack_deadline_at < NOW() THEN 1 ELSE 0 END)"
            : '0';

        try {
            $stmt = $this->pdo->prepare(
                "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN UPPER(status) IN ('PENDING','DELIVERED') THEN 1 ELSE 0 END) AS pending,
                    {$overdueExpr} AS overdue
                 FROM atak_orders
                 WHERE tenant_id = ? AND map_id = ?"
            );
            $stmt->execute([$tenantId, $mapId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'total' => (int) ($row['total'] ?? 0),
                'pending' => (int) ($row['pending'] ?? 0),
                'overdue' => (int) ($row['overdue'] ?? 0),
            ];
        } catch (\Throwable) {
            return ['total' => 0, 'pending' => 0, 'overdue' => 0];
        }
    }

    /**
     * @return non-empty-string|null
     */
    private function normalizeSince(?string $since): ?string
    {
        if ($since === null) {
            return null;
        }
        $since = trim($since);
        if ($since === '') {
            return null;
        }
        // Accepte "Y-m-d H:i:s", ISO-8601, timestamp unix.
        if (ctype_digit($since)) {
            $ts = (int) $since;
            if ($ts > 0) {
                return date('Y-m-d H:i:s', $ts);
            }

            return null;
        }
        $ts = strtotime($since);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByExternalId(int $tenantId, int $mapId, string $externalId): ?array
    {
        if (!$this->tablesReady() || $externalId === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM atak_orders WHERE tenant_id = ? AND map_id = ? AND external_id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $mapId, $externalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{
     *   external_id: string,
     *   parent_external_id?: string|null,
     *   order_type?: string,
     *   type_label?: string|null,
     *   target?: string|null,
     *   target_type?: string,
     *   target_ref?: string|null,
     *   target_label?: string|null,
     *   payload?: string|null,
     *   priority?: string,
     *   issuer: string,
     *   issuer_user_id?: int|null,
     *   status?: string,
     *   note?: string|null,
     *   status_by?: string|null,
     *   source?: string,
     *   radio_sim?: bool,
     *   sim_latency_sec?: int|null,
     *   sim_event?: string|null
     * } $data
     * @return array<string, mixed>|null
     */
    public function upsertByExternalId(int $tenantId, int $mapId, array $data): ?array
    {
        if (!$this->tablesReady()) {
            return null;
        }
        $externalId = trim((string) ($data['external_id'] ?? ''));
        if ($externalId === '') {
            return null;
        }

        $type = $this->normalizeType((string) ($data['order_type'] ?? 'MOVE'));
        $typeLabel = mb_substr(trim((string) ($data['type_label'] ?? '')), 0, 120);
        if (($type === 'CUSTOM' || str_starts_with($type, 'TYP_')) && $typeLabel === '') {
            $typeLabel = 'Ordre personnalisé';
        }
        $priority = $this->normalizePriority((string) ($data['priority'] ?? 'IMPORTANT'));
        $status = $this->normalizeStatus((string) ($data['status'] ?? 'PENDING'));
        $issuer = mb_substr(trim((string) ($data['issuer'] ?? 'Inconnu')), 0, 128);
        $targetType = $this->normalizeTargetType((string) ($data['target_type'] ?? 'all'));
        $targetRef = mb_substr(trim((string) ($data['target_ref'] ?? '')), 0, 128);
        $targetLabel = mb_substr(trim((string) ($data['target_label'] ?? '')), 0, 160);
        $target = mb_substr(trim((string) ($data['target'] ?? '')), 0, 128);
        if ($target === '' && $targetLabel !== '') {
            $target = mb_substr($targetLabel, 0, 128);
        }
        if ($target === '' && $targetType !== 'all') {
            $target = $targetRef !== '' ? $targetRef : $targetType;
        }
        $payload = (string) ($data['payload'] ?? '');
        $parent = trim((string) ($data['parent_external_id'] ?? ''));
        $note = mb_substr(trim((string) ($data['note'] ?? '')), 0, 500);
        $statusBy = mb_substr(trim((string) ($data['status_by'] ?? '')), 0, 128);
        $source = (($data['source'] ?? '') === 'game') ? 'game' : 'web';
        $issuerUserId = isset($data['issuer_user_id']) ? (int) $data['issuer_user_id'] : null;
        if ($issuerUserId !== null && $issuerUserId < 1) {
            $issuerUserId = null;
        }

        $radioSim = array_key_exists('radio_sim', $data) ? (bool) $data['radio_sim'] : true;
        $simLatency = isset($data['sim_latency_sec']) ? (int) $data['sim_latency_sec'] : null;
        $simEvent = isset($data['sim_event']) ? trim((string) $data['sim_event']) : null;
        [$deliverAt, $simState, $simLatency, $simEvent] = $this->computeDeliveryPlan(
            $radioSim,
            $simLatency,
            $simEvent,
            $source
        );
        $ackDeadline = $this->computeAckDeadline($priority, $deliverAt);

        $existing = $this->findByExternalId($tenantId, $mapId, $externalId);
        if ($existing) {
            // Chaîne vide → null en PHP (évite NULLIF + mix collation PDO/table en prod).
            $noteParam = $note !== '' ? $note : null;
            $statusByParam = $statusBy !== '' ? $statusBy : null;
            if ($this->v2ColumnsReady()) {
                $this->pdo->prepare(
                    'UPDATE atak_orders SET
                        parent_external_id = ?, order_type = ?, target = ?, target_type = ?, target_ref = ?,
                        target_label = ?, payload = ?, priority = ?, issuer = ?,
                        issuer_user_id = COALESCE(?, issuer_user_id),
                        status = ?, note = COALESCE(?, note),
                        status_by = COALESCE(?, status_by),
                        updated_at = NOW()
                     WHERE id = ?'
                )->execute([
                    $parent !== '' ? $parent : null,
                    $type,
                    $target !== '' ? $target : null,
                    $targetType,
                    $targetRef !== '' ? $targetRef : null,
                    $targetLabel !== '' ? $targetLabel : null,
                    $payload !== '' ? $payload : null,
                    $priority,
                    $issuer !== '' ? $issuer : (string) ($existing['issuer'] ?? 'Inconnu'),
                    $issuerUserId,
                    $status,
                    $noteParam,
                    $statusByParam,
                    (int) $existing['id'],
                ]);
            } else {
                $this->pdo->prepare(
                    'UPDATE atak_orders SET
                        parent_external_id = ?, order_type = ?, target = ?, payload = ?, priority = ?,
                        issuer = ?, status = ?, note = COALESCE(?, note),
                        status_by = COALESCE(?, status_by),
                        updated_at = NOW()
                     WHERE id = ?'
                )->execute([
                    $parent !== '' ? $parent : null,
                    $type,
                    $target !== '' ? $target : null,
                    $payload !== '' ? $payload : null,
                    $priority,
                    $issuer !== '' ? $issuer : (string) ($existing['issuer'] ?? 'Inconnu'),
                    $status,
                    $noteParam,
                    $statusByParam,
                    (int) $existing['id'],
                ]);
            }
            $this->persistTypeLabel((int) $existing['id'], $type, $typeLabel);

            return $this->findByExternalId($tenantId, $mapId, $externalId);
        }

        if ($this->v2ColumnsReady()) {
            $this->pdo->prepare(
                'INSERT INTO atak_orders
                    (tenant_id, map_id, external_id, parent_external_id, order_type, target, target_type, target_ref,
                     target_label, payload, priority, issuer, issuer_user_id, status, note, status_by, source,
                     deliver_at, ack_deadline_at, radio_sim, sim_state, sim_latency_sec, sim_event)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId,
                $mapId,
                $externalId,
                $parent !== '' ? $parent : null,
                $type,
                $target !== '' ? $target : null,
                $targetType,
                $targetRef !== '' ? $targetRef : null,
                $targetLabel !== '' ? $targetLabel : null,
                $payload !== '' ? $payload : null,
                $priority,
                $issuer !== '' ? $issuer : 'Inconnu',
                $issuerUserId,
                $status,
                $note !== '' ? $note : null,
                $statusBy !== '' ? $statusBy : null,
                $source,
                $deliverAt,
                $ackDeadline,
                $radioSim ? 1 : 0,
                $simState,
                $simLatency,
                $simEvent,
            ]);
        } else {
            $this->pdo->prepare(
                'INSERT INTO atak_orders
                    (tenant_id, map_id, external_id, parent_external_id, order_type, target, payload, priority, issuer, status, note, status_by, source)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $tenantId,
                $mapId,
                $externalId,
                $parent !== '' ? $parent : null,
                $type,
                $target !== '' ? $target : null,
                $payload !== '' ? $payload : null,
                $priority,
                $issuer !== '' ? $issuer : 'Inconnu',
                $status,
                $note !== '' ? $note : null,
                $statusBy !== '' ? $statusBy : null,
                $source,
            ]);
        }

        $created = $this->findByExternalId($tenantId, $mapId, $externalId);
        if (is_array($created)) {
            $this->persistTypeLabel((int) ($created['id'] ?? 0), $type, $typeLabel);
            $refreshed = $this->findByExternalId($tenantId, $mapId, $externalId);
            if (is_array($refreshed)) {
                return $refreshed;
            }
        }

        return $created;
    }

    private function persistTypeLabel(int $orderId, string $type, string $typeLabel): void
    {
        if ($orderId < 1 || !$this->hasColumn('type_label')) {
            return;
        }
        $value = $typeLabel;
        if ($type !== 'CUSTOM' && !str_starts_with($type, 'CUSTOM_') && !str_starts_with($type, 'TPL_')) {
            // Les types prédéfinis gardent leur libellé calculé ; on n’écrase pas.
            if ($value === '') {
                return;
            }
        }
        $this->pdo->prepare(
            'UPDATE atak_orders SET type_label = ? WHERE id = ?'
        )->execute([
            $value !== '' ? $value : null,
            $orderId,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function updateStatus(
        int $tenantId,
        int $mapId,
        string $externalId,
        string $status,
        string $by = '',
        string $note = ''
    ): ?array {
        if (!$this->tablesReady()) {
            return null;
        }
        $existing = $this->findByExternalId($tenantId, $mapId, $externalId);
        if (!$existing) {
            return null;
        }
        $status = $this->normalizeStatus($status);
        $current = strtoupper((string) ($existing['status'] ?? 'PENDING'));
        if ($current === 'CANCELLED' && $status !== 'CANCELLED') {
            return $existing;
        }

        $by = mb_substr(trim($by), 0, 128);
        $note = mb_substr(trim($note), 0, 500);
        // Chaîne vide → null en PHP (évite NULLIF + mix collation PDO/table en prod).
        $byParam = $by !== '' ? $by : null;
        $noteParam = $note !== '' ? $note : null;

        if ($status === 'CANCELLED' && $this->hasColumn('cancelled_at')) {
            $this->pdo->prepare(
                'UPDATE atak_orders SET status = ?, status_by = COALESCE(?, status_by),
                    note = COALESCE(?, note),
                    cancelled_at = COALESCE(cancelled_at, NOW()),
                    cancelled_by = COALESCE(?, cancelled_by),
                    updated_at = NOW()
                 WHERE id = ?'
            )->execute([$status, $byParam, $noteParam, $byParam, (int) $existing['id']]);
        } elseif ($status === 'ACK' && $this->hasColumn('ack_at')) {
            $this->pdo->prepare(
                'UPDATE atak_orders SET status = ?, status_by = COALESCE(?, status_by),
                    note = COALESCE(?, note),
                    ack_at = COALESCE(ack_at, NOW()),
                    ack_by = COALESCE(?, ack_by),
                    sim_state = \'delivered\',
                    updated_at = NOW()
                 WHERE id = ?'
            )->execute([$status, $byParam, $noteParam, $byParam, (int) $existing['id']]);
        } else {
            $this->pdo->prepare(
                'UPDATE atak_orders SET status = ?, status_by = COALESCE(?, status_by),
                    note = COALESCE(?, note), updated_at = NOW()
                 WHERE id = ?'
            )->execute([$status, $byParam, $noteParam, (int) $existing['id']]);
        }

        return $this->findByExternalId($tenantId, $mapId, $externalId);
    }

    /**
     * Fait progresser l’état radio / livraison. Retourne null si l’ordre n’est pas encore
     * visible côté destinataire (vue non-émetteur).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public function progressDelivery(array $row, bool $issuerView = true): ?array
    {
        if (!$this->v2ColumnsReady()) {
            return $row;
        }

        $status = strtoupper((string) ($row['status'] ?? 'PENDING'));
        if (in_array($status, ['CANCELLED', 'ACK', 'EXEC', 'FAILED'], true)) {
            $row['is_overdue'] = false;
            $row['visible_to_recipient'] = true;

            return $row;
        }

        $deliverAt = (string) ($row['deliver_at'] ?? '');
        $now = time();
        $deliverTs = $deliverAt !== '' ? strtotime($deliverAt) : false;
        $delivered = $deliverTs === false || $deliverTs <= $now;

        $radioSim = (int) ($row['radio_sim'] ?? 0) === 1;
        $simState = (string) ($row['sim_state'] ?? 'delivered');
        $simEvent = (string) ($row['sim_event'] ?? '');

        if (!$delivered && $radioSim) {
            $createdTs = strtotime((string) ($row['created_at'] ?? '')) ?: $now;
            $latency = max(1, (int) ($row['sim_latency_sec'] ?? 5));
            $elapsed = max(0, $now - $createdTs);
            $ratio = $elapsed / $latency;
            if ($simEvent === 'jamming' && $ratio < 0.55) {
                $simState = 'jammed';
            } elseif ($simEvent === 'retransmit' && $ratio >= 0.35 && $ratio < 0.75) {
                $simState = 'retransmit';
            } elseif ($simEvent === 'lost_retry' && $ratio < 0.7) {
                $simState = 'lost';
            } else {
                $simState = 'transmitting';
            }
            if ($simState !== (string) ($row['sim_state'] ?? '')) {
                try {
                    $this->pdo->prepare(
                        'UPDATE atak_orders SET sim_state = ?, updated_at = updated_at WHERE id = ?'
                    )->execute([$simState, (int) $row['id']]);
                } catch (\Throwable) {
                }
                $row['sim_state'] = $simState;
            }
            $row['visible_to_recipient'] = false;
            $row['is_overdue'] = false;
            if (!$issuerView) {
                return null;
            }

            return $row;
        }

        // Livré : passer PENDING → DELIVERED
        if ($status === 'PENDING' && $delivered) {
            try {
                $this->pdo->prepare(
                    "UPDATE atak_orders SET status = 'DELIVERED', sim_state = 'delivered', updated_at = NOW()
                     WHERE id = ? AND status = 'PENDING'"
                )->execute([(int) $row['id']]);
                $row['status'] = 'DELIVERED';
                $row['sim_state'] = 'delivered';
                $status = 'DELIVERED';
            } catch (\Throwable) {
            }
        }

        $row['visible_to_recipient'] = true;
        $row['is_overdue'] = $this->computeIsOverdue($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function computeIsOverdue(array $row): bool
    {
        $status = strtoupper((string) ($row['status'] ?? ''));
        if (!in_array($status, ['PENDING', 'DELIVERED'], true)) {
            return false;
        }
        $deadline = (string) ($row['ack_deadline_at'] ?? '');
        if ($deadline === '') {
            return false;
        }
        $ts = strtotime($deadline);

        return $ts !== false && $ts < time();
    }

    /**
     * @return array{0: string, 1: string, 2: int, 3: string|null}
     */
    private function computeDeliveryPlan(bool $radioSim, ?int $simLatency, ?string $simEvent, string $source): array
    {
        if (!$radioSim || $source === 'game') {
            return [date('Y-m-d H:i:s'), 'delivered', 0, null];
        }

        $latency = $simLatency;
        if ($latency === null || $latency < 0) {
            $latency = random_int(2, 15);
        }
        $latency = max(0, min(120, $latency));

        $event = $simEvent;
        if ($event === null || $event === '') {
            $roll = random_int(1, 100);
            if ($roll <= 12) {
                $event = 'jamming';
                $latency = max($latency, random_int(8, 18));
            } elseif ($roll <= 22) {
                $event = 'retransmit';
                $latency = max($latency, random_int(6, 16));
            } elseif ($roll <= 28) {
                $event = 'lost_retry';
                $latency = max($latency, random_int(10, 20));
            } else {
                $event = 'nominal';
            }
        }

        $deliverAt = date('Y-m-d H:i:s', time() + $latency);
        $simState = $latency > 0 ? 'queued' : 'delivered';

        return [$deliverAt, $simState, $latency, $event === 'none' ? null : $event];
    }

    private function computeAckDeadline(string $priority, string $deliverAt): string
    {
        $base = strtotime($deliverAt) ?: time();
        $extra = match (strtoupper($priority)) {
            'CONTACT' => 180,
            'URGENT' => 300,
            'IMPORTANT' => 900,
            default => 1800,
        };

        return date('Y-m-d H:i:s', $base + $extra);
    }

    public function normalizeType(string $type): string
    {
        $t = strtoupper(trim($type));
        if (in_array($t, self::TYPES, true)) {
            return $t;
        }
        // Types / modèles personnalisés (ex. TYP_12, CUSTOM_AB12, TPL_9)
        if (preg_match('/^TYP_\d+$/', $t) === 1) {
            return mb_substr($t, 0, 32);
        }
        if (preg_match('/^(CUSTOM|TPL)_[A-Z0-9_]{1,24}$/', $t) === 1) {
            return mb_substr($t, 0, 32);
        }

        return 'MOVE';
    }

    public function typeLabelColumnReady(): bool
    {
        return $this->hasColumn('type_label');
    }

    public function normalizePriority(string $priority): string
    {
        $p = strtoupper(trim($priority));

        return in_array($p, self::PRIORITIES, true) ? $p : 'IMPORTANT';
    }

    public function normalizeStatus(string $status): string
    {
        $s = strtoupper(trim($status));
        // Alias historiques / UX
        if ($s === 'RECEIVED' || $s === 'RECU') {
            $s = 'DELIVERED';
        }
        if ($s === 'CONFIRMED' || $s === 'CONFIRME') {
            $s = 'ACK';
        }
        if ($s === 'CANCELED') {
            $s = 'CANCELLED';
        }

        return in_array($s, self::STATUSES, true) ? $s : 'PENDING';
    }

    public function normalizeTargetType(string $type): string
    {
        $t = strtolower(trim($type));
        if ($t === 'fireteam' || $t === 'fire-team') {
            $t = 'fire_team';
        }
        if ($t === 'team' || $t === 'equipe' || $t === '') {
            $t = 'all';
        }
        if ($t === 'unit' || $t === 'groupe') {
            $t = 'group';
        }
        if ($t === 'atak' || $t === 'device') {
            $t = 'solo';
        }

        return in_array($t, self::TARGET_TYPES, true) ? $t : 'all';
    }

    public function channelLabelFr(string $channel): string
    {
        return match (strtoupper($channel)) {
            'GLOBAL' => 'Canal général',
            'COMMAND' => 'Canal commandement',
            'SQUAD' => 'Canal escouade',
            'JTAC' => 'Canal JTAC',
            'AIR' => 'Canal air',
            default => $channel,
        };
    }

    /**
     * Parse le corps messagerie jeu : ORDER|id|type|target|priority|issuer|payload
     *
     * @return array<string, mixed>|null
     */
    public function parseOrderChatBody(string $body): ?array
    {
        $body = trim($body);
        if ($body === '' || strncasecmp($body, 'ORDER|', 6) !== 0) {
            return null;
        }
        $parts = explode('|', $body);
        // ORDER, id, type, target, priority, issuer, payload...
        if (count($parts) < 6) {
            return null;
        }
        $payload = count($parts) > 6 ? implode('|', array_slice($parts, 6)) : '';

        // fn_issueOrder.sqf préfixe optionnellement le payload par "TT:<type>|" quand le type de
        // cible est connu à l'émission (ex. menu ACE self-interact : "group" si le joueur a un
        // groupe, sinon "solo"). Rétrocompatible : un payload sans ce préfixe garde 'all' comme
        // avant (aucune régression sur les messages déjà en base ou émis par un ancien build).
        $targetType = 'all';
        if (strncmp($payload, 'TT:', 3) === 0) {
            $sep = strpos($payload, '|');
            if ($sep !== false) {
                $targetType = $this->normalizeTargetType(substr($payload, 3, $sep - 3));
                $payload = substr($payload, $sep + 1);
            } else {
                $targetType = $this->normalizeTargetType(substr($payload, 3));
                $payload = '';
            }
        }

        return [
            'external_id' => trim((string) ($parts[1] ?? '')),
            'order_type' => $this->normalizeType((string) ($parts[2] ?? 'MOVE')),
            'target' => trim((string) ($parts[3] ?? '')),
            'target_type' => $targetType,
            'priority' => $this->normalizePriority((string) ($parts[4] ?? 'IMPORTANT')),
            'issuer' => trim((string) ($parts[5] ?? '')),
            'payload' => $payload,
            'status' => 'PENDING',
            'source' => 'game',
            'radio_sim' => false,
        ];
    }
}
