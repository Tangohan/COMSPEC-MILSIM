<?php

declare(strict_types=1);

namespace App\Services\Intel;

use App\Controllers\Admin\AdminAtakReportRoutingController;
use App\Repositories\SseCaseRepository;
use App\Support\AtakIcemanReportCatalog;
use App\Support\LazyDatabaseConnection;
use App\Support\SseFieldNoteCatalog;
use PDO;

/**
 * Remontées du terrain réunies dans un seul fil de suivi, comme des pull requests :
 *   cr    : comptes rendus (atak_tactical_reports : SPOTREP, SALUTE, TIC, PATROLREP…)
 *   fiche : fiches de renseignement (sse_field_notes : FRM, FRO, FRC, FRA, FRT — famille FRS)
 *
 * Chaque remontée a un état de suivi (intel_intake_state), un fil (intel_intake_events),
 * des passages caviardés (intel_intake_redactions) et un registre de lectures (intel_intake_reads).
 * Les données d'origine restent dans leur table : changer le type ou corriger le texte écrit dans la table source,
 * et le statut d'origine suit l'état (exploitée, sans suite…) pour que le portail SSE et le jeu restent cohérents.
 */
class IntelIntakeService
{
    use LazyDatabaseConnection;

    public const SOURCES = ['cr' => 'Compte rendu', 'fiche' => 'Fiche de renseignement'];

    public const STATES = [
        'a_traiter' => 'À traiter',
        'en_cours' => 'En exploitation',
        'exploitee' => 'Exploitée',
        'non_exploitable' => 'Non exploitable',
        'close' => 'Close',
    ];

    public const OPEN_STATES = ['a_traiter', 'en_cours'];

    public const EVENT_LABELS = [
        'comment' => 'a commenté',
        'state' => 'a changé l’état',
        'assign' => 'a attribué',
        'type' => 'a changé le type',
        'edit' => 'a modifié les données',
        'redact' => 'a caviardé un passage',
        'unredact' => 'a levé un caviardage',
        'blur' => 'a flouté une pièce jointe',
        'unblur' => 'a retiré le flou d’une pièce jointe',
        'delete' => 'a supprimé la remontée',
        'restore' => 'a restauré la remontée',
    ];

    /** Champs modifiables depuis le back-office, par source. */
    public const EDITABLE = [
        'fiche' => ['title' => 'Titre', 'body' => 'Texte', 'place_label' => 'Lieu', 'grid_reference' => 'Carroyage', 'urgency' => 'Urgence', 'classification' => 'Diffusion'],
        'cr' => ['summary' => 'Résumé', 'details' => 'Détails', 'remarks' => 'Remarques', 'location_description' => 'Lieu', 'grid_reference' => 'Carroyage', 'priority' => 'Priorité'],
    ];

    private static ?bool $ready = null;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo;
    }

    public function schemaReady(): bool
    {
        if (self::$ready === null) {
            try {
                $st = $this->pdo()->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('intel_intake_state', 'intel_intake_events', 'intel_intake_redactions', 'intel_intake_reads')");
                self::$ready = $st !== false && (int) $st->fetchColumn() === 4;
            } catch (\Throwable) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    // ---------------------------------------------------------------- catalogues

    /** @return array<string, string> code => libellé */
    public static function typeOptions(string $source): array
    {
        if ($source === 'fiche') {
            return array_map(static fn (array $k): string => $k['label'], SseFieldNoteCatalog::KINDS);
        }
        $types = AdminAtakReportRoutingController::REPORT_TYPES;
        foreach (AtakIcemanReportCatalog::knownTypeCodes() as $code) {
            $types[$code] ??= AtakIcemanReportCatalog::labelFr($code);
        }

        return $types;
    }

    public static function typeLabel(string $source, string $code): string
    {
        return self::typeOptions($source)[$code] ?? $code;
    }

    /**
     * État de suivi par défaut d'une remontée qui n'a pas encore été touchée, d'après son statut d'origine.
     */
    public static function defaultState(string $source, string $status): string
    {
        $status = strtolower(trim($status));

        return match (true) {
            $source === 'fiche' && $status === 'exploitee', $source === 'cr' && $status === 'actioned' => 'exploitee',
            $source === 'fiche' && $status === 'sans_suite' => 'non_exploitable',
            $source === 'cr' && $status === 'archived' => 'close',
            $source === 'fiche' && $status === 'prise_en_compte', $source === 'cr' && $status === 'acknowledged' => 'en_cours',
            default => 'a_traiter',
        };
    }

    /** Statut d'origine qui reflète l'état de suivi (null : ne pas toucher). */
    public static function sourceStatusFor(string $source, string $state): ?string
    {
        $map = $source === 'fiche'
            ? ['a_traiter' => 'transmise', 'en_cours' => 'prise_en_compte', 'exploitee' => 'exploitee', 'non_exploitable' => 'sans_suite', 'close' => 'sans_suite']
            : ['a_traiter' => 'SUBMITTED', 'en_cours' => 'ACKNOWLEDGED', 'exploitee' => 'ACTIONED', 'non_exploitable' => 'ARCHIVED', 'close' => 'ARCHIVED'];

        return $map[$state] ?? null;
    }

    // ---------------------------------------------------------------- lecture

    /**
     * Liste unifiée, la plus récente d'abord.
     *
     * @param array{source?: string, state?: string, type?: string, assignee?: string, q?: string, deleted?: bool} $f
     * @return list<array<string, mixed>>
     */
    public function listItems(int $tenantId, array $f = [], int $viewerUserId = 0, int $limit = 300): array
    {
        $items = [];
        $source = (string) ($f['source'] ?? '');
        if ($source === '' || $source === 'cr') {
            $items = array_merge($items, $this->listReports($tenantId, $f));
        }
        if ($source === '' || $source === 'fiche') {
            $items = array_merge($items, $this->listNotes($tenantId, $f));
        }
        $state = (string) ($f['state'] ?? 'open');
        $assignee = (string) ($f['assignee'] ?? '');
        $items = array_values(array_filter($items, static function (array $it) use ($state, $assignee, $viewerUserId): bool {
            if ($state === 'open' && !in_array($it['state'], self::OPEN_STATES, true)) {
                return false;
            }
            if ($state === 'closed' && in_array($it['state'], self::OPEN_STATES, true)) {
                return false;
            }
            if (isset(self::STATES[$state]) && $it['state'] !== $state) {
                return false;
            }
            if ($assignee === 'me' && $it['assignee_user_id'] !== $viewerUserId) {
                return false;
            }
            if ($assignee === 'none' && $it['assignee_user_id'] !== null) {
                return false;
            }

            return true;
        }));
        usort($items, static fn (array $a, array $b): int => strcmp((string) $b['at'], (string) $a['at']));

        return array_slice($items, 0, $limit);
    }

    /**
     * Compteurs des onglets (sans filtre d'état).
     *
     * @param array<string, mixed> $f
     * @return array{open: int, closed: int, mine: int, deleted: int}
     */
    public function counts(int $tenantId, array $f, int $viewerUserId): array
    {
        $all = $this->listItems($tenantId, ['state' => 'all'] + $f, $viewerUserId, 100000);
        $deleted = $this->listItems($tenantId, ['state' => 'all', 'deleted' => true] + $f, $viewerUserId, 100000);
        $open = count(array_filter($all, static fn (array $i): bool => in_array($i['state'], self::OPEN_STATES, true)));

        return [
            'open' => $open,
            'closed' => count($all) - $open,
            'mine' => count(array_filter($all, static fn (array $i): bool => $i['assignee_user_id'] === $viewerUserId && in_array($i['state'], self::OPEN_STATES, true))),
            'deleted' => count($deleted),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listReports(int $tenantId, array $f): array
    {
        $where = ['r.tenant_id = :t'];
        $args = ['t' => $tenantId];
        $where[] = !empty($f['deleted']) ? '(r.deleted_at IS NOT NULL OR s.deleted_at IS NOT NULL)' : '(r.deleted_at IS NULL AND s.deleted_at IS NULL)';
        if (($f['type'] ?? '') !== '') {
            $where[] = 'r.report_type = :type';
            $args['type'] = (string) $f['type'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(r.report_number LIKE :q OR r.summary LIKE :q OR r.details LIKE :q OR r.submitter_callsign LIKE :q)';
            $args['q'] = '%' . $f['q'] . '%';
        }
        if (ctype_digit((string) ($f['assignee'] ?? ''))) {
            $where[] = 's.assignee_user_id = :as';
            $args['as'] = (int) $f['assignee'];
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT r.id, r.report_type, r.report_number, r.priority, r.classification, r.submitter_callsign, r.submitter_unit,
                        r.summary, r.details, r.status, r.created_at, r.deleted_at AS src_deleted_at,
                        s.state, s.assignee_user_id, s.deleted_at,
                        a.display_name AS assignee_name, a.callsign AS assignee_callsign,
                        (SELECT COUNT(*) FROM intel_intake_events e WHERE e.tenant_id = r.tenant_id AND e.source = 'cr' AND e.source_id = r.id AND e.kind = 'comment') AS comments
                 FROM atak_tactical_reports r
                 LEFT JOIN intel_intake_state s ON s.tenant_id = r.tenant_id AND s.source = 'cr' AND s.source_id = r.id
                 LEFT JOIN users a ON a.id = s.assignee_user_id
                 WHERE " . implode(' AND ', $where) . '
                 ORDER BY r.created_at DESC LIMIT 1000'
            );
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn (array $r): array => $this->item('cr', $r, [
            'ref' => (string) ($r['report_number'] ?: 'CR-' . $r['id']),
            'type' => (string) $r['report_type'],
            'title' => trim((string) ($r['summary'] ?? '')) !== '' ? (string) $r['summary'] : mb_substr(trim((string) ($r['details'] ?? '')), 0, 140),
            'author' => (string) ($r['submitter_callsign'] ?: ($r['submitter_unit'] ?: 'Terrain')),
            'priority' => (string) ($r['priority'] ?? ''),
            'classification' => (string) ($r['classification'] ?? ''),
            'at' => (string) $r['created_at'],
        ]), $rows);
    }

    /** @return list<array<string, mixed>> */
    private function listNotes(int $tenantId, array $f): array
    {
        $where = ['n.tenant_id = :t'];
        $args = ['t' => $tenantId];
        $where[] = !empty($f['deleted']) ? 's.deleted_at IS NOT NULL' : 's.deleted_at IS NULL';
        if (($f['type'] ?? '') !== '') {
            $where[] = 'n.note_kind = :type';
            $args['type'] = (string) $f['type'];
        }
        if (($f['q'] ?? '') !== '') {
            $where[] = '(n.reference_code LIKE :q OR n.title LIKE :q OR n.body LIKE :q OR n.author_label LIKE :q)';
            $args['q'] = '%' . $f['q'] . '%';
        }
        if (ctype_digit((string) ($f['assignee'] ?? ''))) {
            $where[] = 's.assignee_user_id = :as';
            $args['as'] = (int) $f['assignee'];
        }
        try {
            $st = $this->pdo()->prepare(
                "SELECT n.id, n.reference_code, n.note_kind, n.title, n.body, n.urgency, n.classification, n.author_label, n.author_unit,
                        n.status, n.origin, n.observed_at, n.created_at,
                        s.state, s.assignee_user_id, s.deleted_at,
                        a.display_name AS assignee_name, a.callsign AS assignee_callsign,
                        (SELECT COUNT(*) FROM intel_intake_events e WHERE e.tenant_id = n.tenant_id AND e.source = 'fiche' AND e.source_id = n.id AND e.kind = 'comment') AS comments
                 FROM sse_field_notes n
                 LEFT JOIN intel_intake_state s ON s.tenant_id = n.tenant_id AND s.source = 'fiche' AND s.source_id = n.id
                 LEFT JOIN users a ON a.id = s.assignee_user_id
                 WHERE " . implode(' AND ', $where) . "
                   AND n.status <> 'brouillon'
                 ORDER BY n.created_at DESC LIMIT 1000"
            );
            $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }

        return array_map(fn (array $r): array => $this->item('fiche', $r, [
            'ref' => (string) $r['reference_code'],
            'type' => (string) $r['note_kind'],
            'title' => trim((string) ($r['title'] ?? '')) !== '' ? (string) $r['title'] : mb_substr(trim((string) ($r['body'] ?? '')), 0, 140),
            'author' => (string) ($r['author_label'] ?: ($r['author_unit'] ?: 'Terrain')),
            'priority' => (string) ($r['urgency'] ?? ''),
            'classification' => (string) ($r['classification'] ?? ''),
            'at' => (string) $r['created_at'],
        ]), $rows);
    }

    /**
     * @param array<string, mixed> $r
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function item(string $source, array $r, array $base): array
    {
        $state = (string) ($r['state'] ?? '');
        if (!isset(self::STATES[$state])) {
            $state = self::defaultState($source, (string) ($r['status'] ?? ''));
        }
        $assignee = $r['assignee_user_id'] !== null ? (int) $r['assignee_user_id'] : null;

        return $base + [
            'source' => $source,
            'id' => (int) $r['id'],
            'type_label' => self::typeLabel($source, (string) $base['type']),
            'state' => $state,
            'state_label' => self::STATES[$state],
            'assignee_user_id' => $assignee,
            'assignee_label' => $assignee !== null ? (string) (($r['assignee_callsign'] ?? '') ?: ($r['assignee_name'] ?? '') ?: 'Membre #' . $assignee) : '',
            'comments' => (int) ($r['comments'] ?? 0),
            'deleted' => !empty($r['deleted_at']) || !empty($r['src_deleted_at']),
            'origin' => (string) ($r['origin'] ?? 'atak'),
        ];
    }

    /**
     * Une remontée complète : données d'origine, suivi, fil, caviardages, lectures, pièces jointes.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $tenantId, string $source, int $id): ?array
    {
        if (!isset(self::SOURCES[$source]) || $id < 1) {
            return null;
        }
        $table = $source === 'cr' ? 'atak_tactical_reports' : 'sse_field_notes';
        $st = $this->pdo()->prepare("SELECT * FROM {$table} WHERE tenant_id = ? AND id = ? LIMIT 1");
        $st->execute([$tenantId, $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $state = $this->stateRow($tenantId, $source, $id);
        $current = $state !== null && isset(self::STATES[(string) $state['state']])
            ? (string) $state['state']
            : self::defaultState($source, (string) ($row['status'] ?? ''));
        $assignee = null;
        if ($state !== null && $state['assignee_user_id'] !== null) {
            $assignee = $this->userCard((int) $state['assignee_user_id']);
        }
        $attachments = [];
        if ($source === 'fiche') {
            $at = $this->pdo()->prepare('SELECT * FROM sse_field_note_attachments WHERE tenant_id = ? AND note_id = ? ORDER BY id');
            $at->execute([$tenantId, $id]);
            foreach ($at->fetchAll(PDO::FETCH_ASSOC) ?: [] as $a) {
                $path = (string) ($a['file_path'] ?? '');
                $attachments[] = [
                    'id' => (int) $a['id'],
                    'caption' => (string) ($a['caption'] ?? ''),
                    'original_name' => (string) ($a['original_name'] ?? ''),
                    'kind_label' => SseFieldNoteCatalog::attachmentKindLabel((string) ($a['kind'] ?? 'photo')),
                    'url' => $path !== '' && function_exists('user_media_public_url') ? user_media_public_url($path) : null,
                    'is_image' => str_starts_with((string) ($a['mime_type'] ?? ''), 'image/'),
                    'blurred' => !empty($a['blurred']),
                ];
            }
        }
        $structured = [];
        if ($source === 'cr' && !empty($row['structured_data'])) {
            $decoded = json_decode((string) $row['structured_data'], true);
            $structured = is_array($decoded) ? $decoded : [];
        }

        return [
            'source' => $source,
            'source_label' => self::SOURCES[$source],
            'id' => $id,
            'row' => $row,
            'ref' => $source === 'cr' ? (string) ($row['report_number'] ?: 'CR-' . $id) : (string) $row['reference_code'],
            'type' => $source === 'cr' ? (string) $row['report_type'] : (string) $row['note_kind'],
            'type_label' => self::typeLabel($source, $source === 'cr' ? (string) $row['report_type'] : (string) $row['note_kind']),
            'author' => $source === 'cr' ? (string) ($row['submitter_callsign'] ?? '') : (string) ($row['author_label'] ?? ''),
            'at' => (string) $row['created_at'],
            'state' => $current,
            'state_label' => self::STATES[$current],
            'assignee' => $assignee,
            'deleted' => ($state !== null && !empty($state['deleted_at'])) || ($source === 'cr' && !empty($row['deleted_at'])),
            'events' => $this->events($tenantId, $source, $id),
            'redactions' => $this->redactions($tenantId, $source, [$id])[$id] ?? [],
            'reads' => $this->reads($tenantId, $source, $id),
            'attachments' => $attachments,
            'structured' => $structured,
        ];
    }

    /** @return array<string, mixed>|null */
    private function stateRow(int $tenantId, string $source, int $id): ?array
    {
        $st = $this->pdo()->prepare('SELECT * FROM intel_intake_state WHERE tenant_id = ? AND source = ? AND source_id = ? LIMIT 1');
        $st->execute([$tenantId, $source, $id]);
        $r = $st->fetch(PDO::FETCH_ASSOC);

        return $r ?: null;
    }

    /** @return array{id: int, label: string, name: string}|null */
    private function userCard(int $userId): ?array
    {
        $st = $this->pdo()->prepare('SELECT id, display_name, callsign FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);
        $u = $st->fetch(PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }

        return ['id' => (int) $u['id'], 'label' => (string) (($u['callsign'] ?? '') ?: ($u['display_name'] ?? '') ?: 'Membre #' . $u['id']), 'name' => (string) ($u['display_name'] ?? '')];
    }

    /**
     * Membres de la communauté pour l'attribution et les exceptions de caviardage.
     *
     * @return list<array{id: int, label: string}>
     */
    public function members(int $tenantId): array
    {
        try {
            $st = $this->pdo()->prepare("SELECT id, display_name, callsign FROM users WHERE tenant_id = ? AND (status IS NULL OR status NOT IN ('banned', 'disabled', 'deleted')) ORDER BY COALESCE(NULLIF(callsign, ''), display_name) LIMIT 600");
            $st->execute([$tenantId]);
        } catch (\Throwable) {
            return [];
        }

        return array_map(static fn (array $u): array => [
            'id' => (int) $u['id'],
            'label' => trim((string) (($u['callsign'] ?? '') !== '' ? $u['callsign'] . ' · ' . ($u['display_name'] ?? '') : ($u['display_name'] ?? '')), ' ·') ?: 'Membre #' . $u['id'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return list<array<string, mixed>> */
    public function events(int $tenantId, string $source, int $id): array
    {
        $st = $this->pdo()->prepare(
            'SELECT e.*, u.display_name, u.callsign FROM intel_intake_events e LEFT JOIN users u ON u.id = e.actor_user_id
             WHERE e.tenant_id = ? AND e.source = ? AND e.source_id = ? ORDER BY e.created_at, e.id'
        );
        $st->execute([$tenantId, $source, $id]);

        return array_map(static function (array $e): array {
            $meta = json_decode((string) ($e['meta_json'] ?? ''), true);

            return [
                'id' => (int) $e['id'],
                'kind' => (string) $e['kind'],
                'actor' => (string) (($e['callsign'] ?? '') ?: ($e['display_name'] ?? '') ?: ($e['actor_label'] ?? '') ?: 'Système'),
                'body' => (string) ($e['body'] ?? ''),
                'meta' => is_array($meta) ? $meta : [],
                'at' => (string) $e['created_at'],
            ];
        }, $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Caviardages par remontée.
     *
     * @param list<int> $ids
     * @return array<int, list<array<string, mixed>>>
     */
    public function redactions(int $tenantId, string $source, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0));
        if ($ids === [] || !$this->schemaReady()) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $this->pdo()->prepare("SELECT * FROM intel_intake_redactions WHERE tenant_id = ? AND source = ? AND source_id IN ({$in}) ORDER BY id");
        $st->execute(array_merge([$tenantId, $source], $ids));
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
            $r['id'] = (int) $r['id'];
            $r['allowed'] = IntelRedaction::allowedIds($r['allowed_user_ids'] ?? '[]');
            $out[(int) $r['source_id']][] = $r;
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    public function reads(int $tenantId, string $source, int $id): array
    {
        $st = $this->pdo()->prepare(
            'SELECT r.user_id, r.channel, r.read_count, r.first_read_at, r.last_read_at, u.display_name, u.callsign
             FROM intel_intake_reads r LEFT JOIN users u ON u.id = r.user_id
             WHERE r.tenant_id = ? AND r.source = ? AND r.source_id = ? ORDER BY r.last_read_at DESC LIMIT 200'
        );
        $st->execute([$tenantId, $source, $id]);

        return array_map(static fn (array $r): array => [
            'user_id' => (int) $r['user_id'],
            'label' => (string) (($r['callsign'] ?? '') ?: ($r['display_name'] ?? '') ?: 'Membre #' . $r['user_id']),
            'channel' => (string) $r['channel'],
            'count' => (int) $r['read_count'],
            'first' => (string) $r['first_read_at'],
            'last' => (string) $r['last_read_at'],
        ], $st->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** Note une lecture (back-office « bo », portail SSE « web », téléphone en jeu « jeu »). Ne lève jamais. */
    public function recordRead(int $tenantId, string $source, int $id, int $userId, string $channel = 'web'): void
    {
        if ($tenantId < 1 || $userId < 1 || $id < 1 || !isset(self::SOURCES[$source]) || !$this->schemaReady()) {
            return;
        }
        try {
            $this->pdo()->prepare(
                'INSERT INTO intel_intake_reads (tenant_id, source, source_id, user_id, channel) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE read_count = read_count + 1, last_read_at = CURRENT_TIMESTAMP'
            )->execute([$tenantId, $source, $id, $userId, substr($channel, 0, 12)]);
        } catch (\Throwable) {
        }
    }

    // ---------------------------------------------------------------- lecture ailleurs (portail SSE, jeu)

    /**
     * Remontées supprimées depuis le back-office : à masquer partout.
     *
     * @return list<int>
     */
    public function hiddenIds(int $tenantId, string $source): array
    {
        if (!$this->schemaReady()) {
            return [];
        }
        try {
            $st = $this->pdo()->prepare('SELECT source_id FROM intel_intake_state WHERE tenant_id = ? AND source = ? AND deleted_at IS NOT NULL');
            $st->execute([$tenantId, $source]);

            return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Fiches telles que ce lecteur a le droit de les lire : passages caviardés remplacés par ███,
     * pièces floutées marquées (« blurred ») — retirées si $dropBlurred (téléphone en jeu), fiches supprimées ôtées.
     * L'auteur lit toujours sa propre fiche en clair.
     *
     * @param list<array<string, mixed>> $notes
     * @return list<array<string, mixed>>
     */
    public function presentNotes(int $tenantId, array $notes, string $viewerLevel, int $viewerUserId, bool $dropBlurred = false): array
    {
        if ($notes === [] || !$this->schemaReady()) {
            return $notes;
        }
        $hidden = array_flip($this->hiddenIds($tenantId, 'fiche'));
        $notes = array_values(array_filter($notes, static fn (array $n): bool => !isset($hidden[(int) ($n['id'] ?? 0)])));
        $red = $this->redactions($tenantId, 'fiche', array_map(static fn (array $n): int => (int) ($n['id'] ?? 0), $notes));
        foreach ($notes as $i => $n) {
            $id = (int) ($n['id'] ?? 0);
            $own = $viewerUserId > 0 && (int) ($n['author_user_id'] ?? 0) === $viewerUserId;
            if (!$own && isset($red[$id])) {
                $notes[$i] = IntelRedaction::applyRow('fiche', $n, $red[$id], $viewerLevel, $viewerUserId);
                $notes[$i]['redacted'] = true;
            }
            if (isset($n['attachments']) && is_array($n['attachments'])) {
                $atts = array_map(static fn (array $a): array => ['blurred' => !empty($a['blurred'])] + $a, $n['attachments']);
                $notes[$i]['attachments'] = $dropBlurred ? array_values(array_filter($atts, static fn (array $a): bool => !$a['blurred'])) : $atts;
            }
        }

        return $notes;
    }

    /**
     * Comptes rendus tels que ce lecteur a le droit de les lire (même règle que presentNotes).
     *
     * @param list<array<string, mixed>> $reports
     * @return list<array<string, mixed>>
     */
    public function presentReports(int $tenantId, array $reports, string $viewerLevel, int $viewerUserId): array
    {
        if ($reports === [] || !$this->schemaReady()) {
            return $reports;
        }
        $red = $this->redactions($tenantId, 'cr', array_map(static fn (array $r): int => (int) ($r['id'] ?? 0), $reports));
        if ($red === []) {
            return $reports;
        }
        foreach ($reports as $i => $r) {
            $id = (int) ($r['id'] ?? 0);
            $own = $viewerUserId > 0 && (int) ($r['submitter_user_id'] ?? 0) === $viewerUserId;
            if (!$own && isset($red[$id])) {
                $reports[$i] = IntelRedaction::applyRow('cr', $r, $red[$id], $viewerLevel, $viewerUserId);
            }
        }

        return $reports;
    }

    // ---------------------------------------------------------------- actions

    /**
     * @param array{id: int, label: string} $actor
     */
    public function comment(int $tenantId, string $source, int $id, array $actor, string $body): bool
    {
        $body = trim($body);
        if ($body === '') {
            return false;
        }
        $this->log($tenantId, $source, $id, 'comment', $actor, mb_substr($body, 0, 8000));

        return true;
    }

    /** @param array{id: int, label: string} $actor */
    public function setState(int $tenantId, string $source, int $id, array $actor, string $state, string $note = ''): bool
    {
        if (!isset(self::STATES[$state])) {
            return false;
        }
        $item = $this->find($tenantId, $source, $id);
        if ($item === null || $item['state'] === $state) {
            return false;
        }
        $exploited = $state === 'exploitee';
        $this->upsertState($tenantId, $source, $id, [
            'state' => $state,
            'exploited_by' => $exploited ? $actor['id'] : null,
            'exploited_at' => $exploited ? date('Y-m-d H:i:s') : null,
        ]);
        $status = self::sourceStatusFor($source, $state);
        if ($status !== null) {
            try {
                if ($source === 'fiche') {
                    $this->pdo()->prepare('UPDATE sse_field_notes SET status = ?, triaged_by = ?, triaged_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND id = ?')
                        ->execute([$status, $actor['id'] ?: null, $tenantId, $id]);
                } else {
                    $this->pdo()->prepare('UPDATE atak_tactical_reports SET status = ?, acknowledged_by_user_id = COALESCE(acknowledged_by_user_id, ?), acknowledged_at = COALESCE(acknowledged_at, CURRENT_TIMESTAMP) WHERE tenant_id = ? AND id = ?')
                        ->execute([$status, $actor['id'] ?: null, $tenantId, $id]);
                }
            } catch (\Throwable) {
                // Statut d'origine hors des valeurs permises : le suivi reste juste.
            }
        }
        $this->log($tenantId, $source, $id, 'state', $actor, trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null, ['from' => $item['state'], 'to' => $state]);

        return true;
    }

    /** @param array{id: int, label: string} $actor */
    public function assign(int $tenantId, string $source, int $id, array $actor, ?int $userId): bool
    {
        $card = $userId !== null && $userId > 0 ? $this->userCard($userId) : null;
        if ($userId !== null && $userId > 0 && $card === null) {
            return false;
        }
        $this->upsertState($tenantId, $source, $id, [
            'assignee_user_id' => $card['id'] ?? null,
            'assigned_by' => $actor['id'] ?: null,
            'assigned_at' => date('Y-m-d H:i:s'),
        ]);
        $this->log($tenantId, $source, $id, 'assign', $actor, null, ['to' => $card['label'] ?? null, 'to_id' => $card['id'] ?? null]);

        return true;
    }

    /** @param array{id: int, label: string} $actor */
    public function changeType(int $tenantId, string $source, int $id, array $actor, string $type): bool
    {
        $type = strtoupper(trim($type));
        if (!isset(self::typeOptions($source)[$type])) {
            return false;
        }
        $item = $this->find($tenantId, $source, $id);
        if ($item === null || $item['type'] === $type) {
            return false;
        }
        $sql = $source === 'cr'
            ? 'UPDATE atak_tactical_reports SET report_type = ? WHERE tenant_id = ? AND id = ?'
            : 'UPDATE sse_field_notes SET note_kind = ? WHERE tenant_id = ? AND id = ?';
        $this->pdo()->prepare($sql)->execute([$type, $tenantId, $id]);
        $this->log($tenantId, $source, $id, 'type', $actor, null, ['from' => $item['type_label'], 'to' => self::typeLabel($source, $type)]);

        return true;
    }

    /**
     * @param array{id: int, label: string} $actor
     * @param array<string, mixed> $input
     * @param string $viewerLevel habilitation de l'auteur de la modification : un champ qui porte un passage
     *                            qu'il ne lit pas en clair n'est pas modifiable
     * @return list<string> champs modifiés
     */
    public function updateData(int $tenantId, string $source, int $id, array $actor, array $input, string $viewerLevel = 'tres_restreint'): array
    {
        $item = $this->find($tenantId, $source, $id);
        if ($item === null) {
            return [];
        }
        $changes = self::dataChanges($source, $item['row'], $input);
        foreach ($item['redactions'] as $r) {
            if (!IntelRedaction::readable($r, $viewerLevel, (int) $actor['id'])) {
                unset($changes[(string) $r['field']]);
            }
        }
        if ($changes === []) {
            return [];
        }
        $table = $source === 'cr' ? 'atak_tactical_reports' : 'sse_field_notes';
        $set = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($changes)));
        $this->pdo()->prepare("UPDATE {$table} SET {$set} WHERE tenant_id = ? AND id = ?")
            ->execute(array_merge(array_values($changes), [$tenantId, $id]));
        $labels = array_map(static fn (string $c): string => self::EDITABLE[$source][$c] ?? $c, array_keys($changes));
        $before = [];
        foreach (array_keys($changes) as $c) {
            $before[$c] = mb_substr((string) ($item['row'][$c] ?? ''), 0, 600);
        }
        $this->log($tenantId, $source, $id, 'edit', $actor, null, ['fields' => $labels, 'before' => $before]);

        return $labels;
    }

    /**
     * Champs réellement changés et valides (texte nettoyé, valeurs de listes contrôlées).
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $input
     * @return array<string, string|null>
     */
    public static function dataChanges(string $source, array $row, array $input): array
    {
        $out = [];
        foreach (self::EDITABLE[$source] ?? [] as $col => $_) {
            if (!array_key_exists($col, $input)) {
                continue;
            }
            $v = str_replace("\r\n", "\n", trim((string) $input[$col]));
            $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
            switch ($col) {
                case 'urgency':
                    $v = SseFieldNoteCatalog::normalizeUrgency($v);
                    break;
                case 'classification':
                    $v = SseCaseRepository::normalizeClassification($v);
                    break;
                case 'priority':
                    $v = strtoupper($v);
                    if (!isset(AdminAtakReportRoutingController::PRIORITIES[$v])) {
                        continue 2;
                    }
                    break;
                case 'title':
                    $v = mb_substr($v, 0, SseFieldNoteCatalog::TITLE_MAX_LENGTH);
                    break;
                case 'grid_reference':
                    $v = mb_substr($v, 0, 32);
                    break;
                case 'place_label':
                    $v = mb_substr($v, 0, 180);
                    break;
                case 'body':
                    if ($v === '') {
                        continue 2;
                    }
                    $v = mb_substr($v, 0, 20000);
                    break;
                default:
                    $v = mb_substr($v, 0, 20000);
            }
            $old = (string) ($row[$col] ?? '');
            if ($v === $old) {
                continue;
            }
            $out[$col] = $v === '' && !in_array($col, ['body'], true) ? null : $v;
        }

        return $out;
    }

    /** @param array{id: int, label: string} $actor */
    public function delete(int $tenantId, string $source, int $id, array $actor, string $reason = ''): bool
    {
        $this->upsertState($tenantId, $source, $id, ['deleted_by' => $actor['id'] ?: null, 'deleted_at' => date('Y-m-d H:i:s')]);
        if ($source === 'cr') {
            $this->pdo()->prepare('UPDATE atak_tactical_reports SET deleted_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $id]);
        }
        $this->log($tenantId, $source, $id, 'delete', $actor, trim($reason) !== '' ? mb_substr(trim($reason), 0, 1000) : null);

        return true;
    }

    /** @param array{id: int, label: string} $actor */
    public function restore(int $tenantId, string $source, int $id, array $actor): bool
    {
        $this->upsertState($tenantId, $source, $id, ['deleted_by' => null, 'deleted_at' => null]);
        if ($source === 'cr') {
            $this->pdo()->prepare('UPDATE atak_tactical_reports SET deleted_at = NULL WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $id]);
        }
        $this->log($tenantId, $source, $id, 'restore', $actor);

        return true;
    }

    /**
     * @param array{id: int, label: string} $actor
     * @param list<int> $allowedUserIds
     */
    public function addRedaction(int $tenantId, string $source, int $id, array $actor, string $field, string $phrase, string $clearLevel, array $allowedUserIds, string $reason = ''): bool
    {
        $phrase = trim($phrase);
        if ($phrase === '' || mb_strlen($phrase) > 2000 || !isset(IntelRedaction::FIELDS[$source][$field])) {
            return false;
        }
        $item = $this->find($tenantId, $source, $id);
        if ($item === null || mb_stripos((string) ($item['row'][$field] ?? ''), $phrase) === false) {
            return false;
        }
        $clearLevel = isset(\App\Services\Sse\SseRedactionService::LEVELS[$clearLevel]) ? $clearLevel : SseCaseRepository::CLASS_RESTRICTED;
        $allowed = IntelRedaction::allowedIds($allowedUserIds);
        $this->pdo()->prepare(
            'INSERT INTO intel_intake_redactions (tenant_id, source, source_id, field, phrase, clear_level, allowed_user_ids, reason, created_by, created_by_label)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $source, $id, $field, $phrase, $clearLevel, json_encode($allowed), trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null, $actor['id'] ?: null, $actor['label']]);
        $this->log($tenantId, $source, $id, 'redact', $actor, trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null, [
            'field' => IntelRedaction::FIELDS[$source][$field],
            'level' => \App\Services\Sse\SseRedactionService::levelLabel($clearLevel),
            'people' => count($allowed),
            'length' => mb_strlen($phrase),
        ]);

        return true;
    }

    /** @param array{id: int, label: string} $actor */
    public function removeRedaction(int $tenantId, string $source, int $id, array $actor, int $redactionId): bool
    {
        $st = $this->pdo()->prepare('DELETE FROM intel_intake_redactions WHERE tenant_id = ? AND source = ? AND source_id = ? AND id = ?');
        $st->execute([$tenantId, $source, $id, $redactionId]);
        if ($st->rowCount() < 1) {
            return false;
        }
        $this->log($tenantId, $source, $id, 'unredact', $actor);

        return true;
    }

    /** @param array{id: int, label: string} $actor */
    public function setBlur(int $tenantId, int $noteId, array $actor, int $attachmentId, bool $blurred): bool
    {
        try {
            $st = $this->pdo()->prepare('UPDATE sse_field_note_attachments SET blurred = ? WHERE tenant_id = ? AND note_id = ? AND id = ?');
            $st->execute([$blurred ? 1 : 0, $tenantId, $noteId, $attachmentId]);
        } catch (\Throwable) {
            return false;
        }
        if ($st->rowCount() < 1) {
            return false;
        }
        $this->log($tenantId, 'fiche', $noteId, $blurred ? 'blur' : 'unblur', $actor);

        return true;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function upsertState(int $tenantId, string $source, int $id, array $values): void
    {
        $current = $this->stateRow($tenantId, $source, $id);
        if ($current === null) {
            $item = $this->find($tenantId, $source, $id);
            $values += ['state' => $item['state'] ?? 'a_traiter'];
            $cols = array_merge(['tenant_id', 'source', 'source_id'], array_keys($values));
            $this->pdo()->prepare('INSERT INTO intel_intake_state (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')')
                ->execute(array_merge([$tenantId, $source, $id], array_values($values)));

            return;
        }
        $set = implode(', ', array_map(static fn (string $c): string => "{$c} = ?", array_keys($values)));
        $this->pdo()->prepare("UPDATE intel_intake_state SET {$set} WHERE id = ?")->execute(array_merge(array_values($values), [(int) $current['id']]));
    }

    /**
     * @param array{id: int, label: string} $actor
     * @param array<string, mixed> $meta
     */
    private function log(int $tenantId, string $source, int $id, string $kind, array $actor, ?string $body = null, array $meta = []): void
    {
        $this->pdo()->prepare(
            'INSERT INTO intel_intake_events (tenant_id, source, source_id, kind, actor_user_id, actor_label, body, meta_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $source, $id, $kind, $actor['id'] ?: null, mb_substr($actor['label'], 0, 120), $body, $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null]);
    }
}
