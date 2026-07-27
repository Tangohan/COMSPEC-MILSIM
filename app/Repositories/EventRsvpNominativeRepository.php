<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Agrège les réponses nominatives d’un créneau : effectif, RSVP, poste prévu, ATAK et historique de présence.
 */
final class EventRsvpNominativeRepository
{
    private PDO $pdo;
    private bool $ensured = false;

    public function __construct(
        ?PDO $pdo = null,
        private ?CommunityEventRepository $events = null,
    ) {
        $this->pdo = $pdo ?? Database::getPdo();
        $this->events ??= new CommunityEventRepository();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->ensured) {
            return;
        }
        $this->ensured = true;
        $migration = dirname(__DIR__, 2) . '/bootstrap/community_event_rsvp_nominative_migration.php';
        if (!is_file($migration)) {
            return;
        }
        try {
            $runner = require $migration;
            if (is_callable($runner)) {
                $runner($this->pdo);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @param array{q?: string, response?: string, section?: string, atak?: string} $filters
     * @return array{rows: list<array<string,mixed>>, stats: array<string,mixed>, sections: list<string>}
     */
    public function listForEvent(int $tenantId, int $eventId, array $filters = [], int $historyDays = 180): array
    {
        if ($tenantId < 1 || $eventId < 1 || !$this->events->belongsToTenant($eventId, $tenantId)) {
            return ['rows' => [], 'stats' => $this->emptyStats(), 'sections' => []];
        }

        $hasMatricule = $this->columnExists('personnel_profiles', 'matricule_internal');
        $hasPrimaryUnit = $this->columnExists('personnel_profiles', 'primary_unit_id');
        $hasAvailability = $this->columnExists('community_event_rsvps', 'availability_from');
        $hasAdminComment = $this->columnExists('community_event_rsvps', 'admin_comment');
        $hasReminderCount = $this->columnExists('community_event_rsvps', 'reminder_count');

        $matriculeSelect = $hasMatricule ? 'pp.matricule_internal' : 'NULL';
        $unitJoin = $hasPrimaryUnit
            ? 'LEFT JOIN personnel_profiles pp ON pp.user_id = u.id LEFT JOIN units un ON un.id = pp.primary_unit_id'
            : 'LEFT JOIN personnel_profiles pp ON pp.user_id = u.id LEFT JOIN units un ON 1=0';
        $availabilitySelect = $hasAvailability
            ? 'r.availability_from, r.availability_to'
            : 'NULL AS availability_from, NULL AS availability_to';
        $adminCommentSelect = $hasAdminComment ? 'r.admin_comment' : 'NULL AS admin_comment';
        $reminderCountSelect = $hasReminderCount
            ? 'COALESCE(r.reminder_count, 0) AS reminder_count'
            : 'CASE WHEN r.reminder_sent_at IS NOT NULL THEN 1 ELSE 0 END AS reminder_count';

        $sql = "SELECT u.id AS user_id, u.display_name, u.callsign, u.email,
                       {$matriculeSelect} AS matricule_internal,
                       un.id AS unit_id, un.name AS section_name,
                       r.status AS rsvp_status, r.updated_at AS rsvp_updated_at, r.created_at AS rsvp_created_at,
                       r.absence_note, r.checked_in_at, r.reminder_sent_at,
                       {$availabilitySelect}, {$adminCommentSelect}, {$reminderCountSelect},
                       slot.label AS planned_role_label, slot.id AS slot_id
                FROM users u
                {$unitJoin}
                LEFT JOIN community_event_rsvps r ON r.event_id = ? AND r.user_id = u.id
                LEFT JOIN community_event_slot_assignments sa ON sa.event_id = ? AND sa.user_id = u.id AND sa.status = 'confirmed'
                LEFT JOIN community_event_slots slot ON slot.id = sa.slot_id
                WHERE u.tenant_id = ? AND u.status = 'active'
                ORDER BY COALESCE(un.name, 'zzz'), u.display_name ASC";

        $st = $this->pdo->prepare($sql);
        $st->execute([$eventId, $eventId, $tenantId]);
        $rawRows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $userIds = array_values(array_filter(array_map(static fn (array $r): int => (int) ($r['user_id'] ?? 0), $rawRows)));
        $atakByUser = $this->loadAtakContextByUserIds($tenantId, $userIds);
        $presenceByUser = $this->loadHistoricalPresenceRates($tenantId, $userIds, $historyDays);

        $sections = [];
        $rows = [];
        foreach ($rawRows as $raw) {
            $uid = (int) ($raw['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $section = trim((string) ($raw['section_name'] ?? ''));
            if ($section !== '' && !in_array($section, $sections, true)) {
                $sections[] = $section;
            }
            $atak = $atakByUser[$uid] ?? null;
            $row = [
                'user_id' => $uid,
                'matricule' => $this->formatMatricule($raw),
                'callsign' => trim((string) ($raw['callsign'] ?? '')),
                'display_name' => trim((string) ($raw['display_name'] ?? '')),
                'section' => $section !== '' ? $section : '—',
                'section_key' => $section !== '' ? $section : '__none',
                'planned_role' => trim((string) ($raw['planned_role_label'] ?? '')) ?: '—',
                'rsvp_status' => (string) ($raw['rsvp_status'] ?? ''),
                'rsvp_updated_at' => $raw['rsvp_updated_at'] ?? null,
                'rsvp_created_at' => $raw['rsvp_created_at'] ?? null,
                'availability_from' => $raw['availability_from'] ?? null,
                'availability_to' => $raw['availability_to'] ?? null,
                'admin_comment' => trim((string) ($raw['admin_comment'] ?? $raw['absence_note'] ?? '')),
                'reminder_count' => (int) ($raw['reminder_count'] ?? 0),
                'historical_presence_pct' => (int) round(($presenceByUser[$uid] ?? 0) * 100),
                'atak_terminal_label' => $atak['terminal_label'] ?? '—',
                'atak_status' => $atak['status'] ?? 'missing',
            ];
            $rows[] = $row;
        }

        sort($sections);
        $rows = $this->applyFilters($rows, $filters);

        return [
            'rows' => $rows,
            'stats' => $this->buildStats($rows),
            'sections' => $sections,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    public function exportCsv(array $rows): string
    {
        $headers = [
            'Matricule', 'Indicatif', 'Nom', 'Section', 'Rôle prévu', 'Réponse', 'Répondu le',
            'Dispo. horaire', 'ATAK', 'Terminal', 'Relances', 'Présence hist.', 'Commentaires',
        ];
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return '';
        }
        fputcsv($out, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($out, [
                (string) ($row['matricule'] ?? ''),
                (string) ($row['callsign'] ?? ''),
                (string) ($row['display_name'] ?? ''),
                (string) ($row['section'] ?? ''),
                (string) ($row['planned_role'] ?? ''),
                (string) ($row['response_label'] ?? ''),
                (string) ($row['responded_label'] ?? ''),
                (string) ($row['availability_label'] ?? ''),
                (string) ($row['atak_label'] ?? ''),
                (string) ($row['atak_terminal_label'] ?? ''),
                (string) ($row['reminder_count'] ?? 0),
                isset($row['historical_presence_pct']) ? ((string) $row['historical_presence_pct'] . ' %') : '',
                (string) ($row['admin_comment'] ?? ''),
            ], ';');
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return is_string($csv) ? $csv : '';
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function updateRowMeta(int $tenantId, int $eventId, int $userId, array $payload): bool
    {
        if ($tenantId < 1 || $eventId < 1 || $userId < 1 || !$this->events->belongsToTenant($eventId, $tenantId)) {
            return false;
        }
        if (!$this->events->getRsvp($eventId, $userId)) {
            return false;
        }

        $sets = [];
        $params = [];
        if ($this->columnExists('community_event_rsvps', 'availability_from') && array_key_exists('availability_from', $payload)) {
            $sets[] = 'availability_from = ?';
            $params[] = $this->timeOrNull($payload['availability_from']);
        }
        if ($this->columnExists('community_event_rsvps', 'availability_to') && array_key_exists('availability_to', $payload)) {
            $sets[] = 'availability_to = ?';
            $params[] = $this->timeOrNull($payload['availability_to']);
        }
        if ($this->columnExists('community_event_rsvps', 'admin_comment') && array_key_exists('admin_comment', $payload)) {
            $sets[] = 'admin_comment = ?';
            $comment = trim((string) ($payload['admin_comment'] ?? ''));
            $params[] = $comment === '' ? null : mb_substr($comment, 0, 255);
        }
        if ($sets === []) {
            return true;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = $eventId;
        $params[] = $userId;
        $st = $this->pdo->prepare(
            'UPDATE community_event_rsvps SET ' . implode(', ', $sets) . ' WHERE event_id = ? AND user_id = ?'
        );
        $st->execute($params);

        return true;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, array{terminal_label: string, status: string}>
     */
    private function loadAtakContextByUserIds(int $tenantId, array $userIds): array
    {
        if ($tenantId < 1 || $userIds === [] || !$this->tableExists('atak_terminals')) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT t.user_id, t.terminal_label, t.terminal_uid, t.status AS terminal_status,
                       c.status AS cert_status, c.expires_at AS cert_expires_at
                FROM atak_terminals t
                LEFT JOIN atak_certificates c ON c.terminal_id = t.id AND c.tenant_id = t.tenant_id
                    AND c.revoked_at IS NULL
                WHERE t.tenant_id = ? AND t.user_id IN ({$ph})
                ORDER BY COALESCE(t.last_seen_at, t.updated_at) DESC";
        $st = $this->pdo->prepare($sql);
        $st->execute(array_merge([$tenantId], $userIds));
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1 || isset($out[$uid])) {
                continue;
            }
            $label = trim((string) ($row['terminal_label'] ?? $row['terminal_uid'] ?? ''));
            $out[$uid] = [
                'terminal_label' => $label !== '' ? $label : '—',
                'status' => $this->resolveAtakStatus($row),
            ];
        }

        return $out;
    }

    /**
     * @param list<int> $userIds
     * @return array<int, float> 0..1
     */
    private function loadHistoricalPresenceRates(int $tenantId, array $userIds, int $windowDays): array
    {
        if ($tenantId < 1 || $userIds === []) {
            return [];
        }
        $days = max(30, min(365, $windowDays));
        $ph = implode(',', array_fill(0, count($userIds), '?'));
        $sql = "SELECT r.user_id,
                       AVG(CASE WHEN r.status IN ('yes', 'maybe')
                                THEN CASE WHEN r.checked_in_at IS NOT NULL THEN 1 ELSE 0 END
                           END) AS presence_rate
                FROM community_event_rsvps r
                INNER JOIN community_events ce ON ce.id = r.event_id
                WHERE ce.tenant_id = ?
                  AND ce.cancelled_at IS NULL
                  AND ce.starts_at < NOW()
                  AND ce.starts_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
                  AND r.user_id IN ({$ph})
                GROUP BY r.user_id";
        $st = $this->pdo->prepare($sql);
        $st->execute(array_merge([$tenantId], $userIds));
        $out = [];
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid < 1) {
                continue;
            }
            $out[$uid] = isset($row['presence_rate']) ? (float) $row['presence_rate'] : 0.0;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveAtakStatus(array $row): string
    {
        $terminalStatus = strtolower(trim((string) ($row['terminal_status'] ?? '')));
        $certStatus = strtolower(trim((string) ($row['cert_status'] ?? '')));
        $expiresAt = $row['cert_expires_at'] ?? null;

        if ($certStatus === 'revoked' || $terminalStatus === 'revoked' || $terminalStatus === 'lost') {
            return 'missing';
        }
        if ($expiresAt !== null && trim((string) $expiresAt) !== '') {
            $expTs = strtotime((string) $expiresAt);
            if ($expTs !== false && $expTs < time()) {
                return 'expired';
            }
        }
        if ($terminalStatus === 'pending' || $certStatus === 'issued') {
            return 'pending';
        }
        if ($terminalStatus === 'active' || $certStatus === 'active') {
            return 'active';
        }
        if ($terminalStatus === 'inactive' || $terminalStatus === 'offline') {
            return 'expired';
        }

        return 'pending';
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array{q?: string, response?: string, section?: string, atak?: string} $filters
     * @return list<array<string,mixed>>
     */
    private function applyFilters(array $rows, array $filters): array
    {
        $q = strtolower(trim((string) ($filters['q'] ?? '')));
        $response = trim((string) ($filters['response'] ?? ''));
        $section = trim((string) ($filters['section'] ?? ''));
        $atak = trim((string) ($filters['atak'] ?? ''));

        return array_values(array_filter($rows, static function (array $row) use ($q, $response, $section, $atak): bool {
            if ($q !== '') {
                $hay = strtolower(implode(' ', [
                    (string) ($row['matricule'] ?? ''),
                    (string) ($row['callsign'] ?? ''),
                    (string) ($row['display_name'] ?? ''),
                    (string) ($row['section'] ?? ''),
                    (string) ($row['planned_role'] ?? ''),
                    (string) ($row['admin_comment'] ?? ''),
                ]));
                if (!str_contains($hay, $q)) {
                    return false;
                }
            }
            if ($response !== '' && $response !== 'all') {
                $status = (string) ($row['rsvp_status'] ?? '');
                $match = match ($response) {
                    'confirmed' => $status === 'yes',
                    'maybe' => $status === 'maybe',
                    'declined' => $status === 'no',
                    'no_response' => $status === '',
                    default => true,
                };
                if (!$match) {
                    return false;
                }
            }
            if ($section !== '' && $section !== 'all') {
                if ((string) ($row['section_key'] ?? '') !== $section && (string) ($row['section'] ?? '') !== $section) {
                    return false;
                }
            }
            if ($atak !== '' && $atak !== 'all' && (string) ($row['atak_status'] ?? '') !== $atak) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function buildStats(array $rows): array
    {
        $confirmed = 0;
        $maybe = 0;
        $declined = 0;
        $noResponse = 0;
        $atakActive = 0;
        foreach ($rows as $row) {
            $status = (string) ($row['rsvp_status'] ?? '');
            if ($status === 'yes') {
                $confirmed++;
            } elseif ($status === 'maybe') {
                $maybe++;
            } elseif ($status === 'no') {
                $declined++;
            } else {
                $noResponse++;
            }
            if (($row['atak_status'] ?? '') === 'active') {
                $atakActive++;
            }
        }

        return [
            'total' => count($rows),
            'confirmed' => $confirmed,
            'maybe' => $maybe,
            'declined' => $declined,
            'no_response' => $noResponse,
            'atak_active' => $atakActive,
            'updated_at_label' => date('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyStats(): array
    {
        return [
            'total' => 0,
            'confirmed' => 0,
            'maybe' => 0,
            'declined' => 0,
            'no_response' => 0,
            'atak_active' => 0,
            'updated_at_label' => date('d/m/Y H:i'),
        ];
    }

    /**
     * @param array<string,mixed> $raw
     */
    private function formatMatricule(array $raw): string
    {
        $internal = trim((string) ($raw['matricule_internal'] ?? ''));
        if ($internal !== '') {
            return $internal;
        }

        return '—';
    }

    private function columnExists(string $table, string $column): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $st->execute([$table, $column]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            $st = $this->pdo->prepare(
                'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $st->execute([$table]);

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function timeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        return null;
    }
}
