<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseAlertLevel;

/**
 * Alertes SSE (étape 4830) : lien de signalement d'un avis de recherche ou d'une fiche de vigilance (jeton opaque, service à
 * informer, échéance et levée d'une fiche de vigilance), signalements, propositions à valider et accusés de lecture.
 *
 * Toutes les lectures et écritures sont bornées à la communauté. Sans les tables (étape en attente), ready() est faux : les
 * documents s'émettent comme avant, sans adresse de signalement.
 */
final class SseAlertRepository
{
    public const DOC_NOTICE = 'avis';

    public const DOC_VIGILANCE = 'vigilance';

    public const DOCS = [self::DOC_NOTICE, self::DOC_VIGILANCE];

    private Database $db;

    /** @var \WeakMap<\PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    public static function ready(): bool
    {
        try {
            $pdo = Database::getPdo();
            self::$readyByConnection ??= new \WeakMap();
            $known = self::$readyByConnection[$pdo] ?? null;
            if (is_array($known) && ($known['ok'] || time() - $known['at'] < 20)) {
                return $known['ok'];
            }
            try {
                foreach (['sse_alert_links', 'sse_alert_reports', 'sse_alert_proposals', 'sse_alert_acks'] as $table) {
                    $pdo->query('SELECT 1 FROM ' . $table . ' WHERE 1 = 0');
                }
                self::$readyByConnection[$pdo] = ['ok' => true, 'at' => time()];

                return true;
            } catch (\Throwable) {
                self::$readyByConnection[$pdo] = ['ok' => false, 'at' => time()];

                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    /** Jeton opaque de 22 caractères [A-Za-z0-9], tiré au hasard (130 bits). */
    public static function newToken(): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $out = '';
        for ($i = 0; $i < 22; ++$i) {
            $out .= $alphabet[random_int(0, 61)];
        }

        return $out;
    }

    public static function validToken(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9]{22}$/D', $token) === 1;
    }

    public static function validClientId(?string $cid): bool
    {
        return $cid !== null && preg_match('/^[A-Za-z0-9_-]{8,64}$/', $cid) === 1;
    }

    // ---------------------------------------------------------------------- Liens

    /**
     * Le lien du document, créé s'il manque (jeton tiré ici). Les champs donnés ne servent qu'à la création.
     *
     * @param array{report?: array<string, mixed>|null, valid_until?: ?string, client_id?: ?string, user_id?: ?int} $fields
     * @return array<string, mixed>|null null si la table manque ou si la création échoue
     */
    public function ensureLink(int $tenantId, string $doc, int $targetId, array $fields = []): ?array
    {
        if (!in_array($doc, self::DOCS, true) || $targetId < 1 || !self::ready()) {
            return null;
        }
        $found = $this->linkFor($tenantId, $doc, $targetId);
        if ($found !== null) {
            return $found;
        }
        $cid = self::validClientId($fields['client_id'] ?? null) ? (string) $fields['client_id'] : null;
        // Jeton déjà imprimé sur le PDF (fiche de vigilance) : repris tel quel au premier essai.
        $given = is_string($fields['token'] ?? null) && self::validToken((string) $fields['token']) ? (string) $fields['token'] : null;
        for ($attempt = 0; $attempt < 4; ++$attempt) {
            try {
                $this->db->insert(
                    'INSERT INTO sse_alert_links (tenant_id, doc, target_id, token, report_json, valid_until, client_id, created_by_user_id, created_at)
                     VALUES (:t, :d, :id, :tok, :rep, :until, :cid, :u, :now)',
                    [
                        't' => $tenantId, 'd' => $doc, 'id' => $targetId, 'tok' => $attempt === 0 && $given !== null ? $given : self::newToken(),
                        'rep' => isset($fields['report']) && is_array($fields['report']) ? json_encode($fields['report'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                        'until' => isset($fields['valid_until']) && preg_match('/^\d{4}-\d{2}-\d{2}/', (string) $fields['valid_until']) === 1 ? substr((string) $fields['valid_until'], 0, 10) : null,
                        'cid' => $cid, 'u' => $fields['user_id'] ?? null, 'now' => gmdate('Y-m-d H:i:s'),
                    ]
                );

                return $this->linkFor($tenantId, $doc, $targetId);
            } catch (\PDOException) {
                // Course ou jeton déjà pris : le lien existe peut-être maintenant.
                $found = $this->linkFor($tenantId, $doc, $targetId);
                if ($found !== null) {
                    return $found;
                }
                $cid = null;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function linkFor(int $tenantId, string $doc, int $targetId): ?array
    {
        if (!self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_links WHERE tenant_id = :t AND doc = :d AND target_id = :id', ['t' => $tenantId, 'd' => $doc, 'id' => $targetId]);

        return $row !== null ? self::hydrateLink($row) : null;
    }

    /**
     * Liens de plusieurs documents du même genre, par identifiant du document.
     *
     * @param list<int> $targetIds
     * @return array<int, array<string, mixed>>
     */
    public function linksFor(int $tenantId, string $doc, array $targetIds): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $targetIds), static fn (int $i): bool => $i > 0))), 0, 400);
        if ($ids === [] || !self::ready()) {
            return [];
        }
        $params = ['t' => $tenantId, 'd' => $doc];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $id;
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT * FROM sse_alert_links WHERE tenant_id = :t AND doc = :d AND target_id IN (' . implode(', ', $marks) . ')', $params) as $row) {
            $out[(int) $row['target_id']] = self::hydrateLink($row);
        }

        return $out;
    }

    /** Lien d'un jeton, pour la communauté donnée seulement (un jeton d'une autre communauté est introuvable). @return array<string, mixed>|null */
    public function linkByToken(int $tenantId, string $token): ?array
    {
        if (!self::validToken($token) || !self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_links WHERE tenant_id = :t AND token = :tok', ['t' => $tenantId, 'tok' => $token]);

        return $row !== null ? self::hydrateLink($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function linkById(int $tenantId, int $id): ?array
    {
        if (!self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_links WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);

        return $row !== null ? self::hydrateLink($row) : null;
    }

    /** @return array<string, mixed>|null lien créé par cet identifiant client (idempotence du jeu) */
    public function linkByClient(int $tenantId, string $cid): ?array
    {
        if (!self::validClientId($cid) || !self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_links WHERE tenant_id = :t AND client_id = :c', ['t' => $tenantId, 'c' => $cid]);

        return $row !== null ? self::hydrateLink($row) : null;
    }

    /** @param array<string, mixed> $report */
    public function saveReport(int $tenantId, int $linkId, array $report): bool
    {
        return $this->db->execute('UPDATE sse_alert_links SET report_json = :r WHERE tenant_id = :t AND id = :id', ['r' => json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 't' => $tenantId, 'id' => $linkId]) > 0;
    }

    /** Lève le document du lien (une seule fois). */
    public function lift(int $tenantId, int $linkId, string $reason, ?int $userId, string $label, ?string $renewedByRef = null): bool
    {
        return $this->db->execute(
            'UPDATE sse_alert_links SET lifted_at = :now, lifted_by_user_id = :u, lifted_by_label = :l, lift_reason = :r, renewed_by_ref = :n WHERE tenant_id = :t AND id = :id AND lifted_at IS NULL',
            ['now' => gmdate('Y-m-d H:i:s'), 'u' => $userId, 'l' => mb_substr($label, 0, 80), 'r' => mb_substr($reason, 0, 500), 'n' => $renewedByRef !== null ? mb_substr($renewedByRef, 0, 40) : null, 't' => $tenantId, 'id' => $linkId]
        ) > 0;
    }

    public function revoke(int $tenantId, int $linkId): bool
    {
        return $this->db->execute('UPDATE sse_alert_links SET revoked_at = :now WHERE tenant_id = :t AND id = :id AND revoked_at IS NULL', ['now' => gmdate('Y-m-d H:i:s'), 't' => $tenantId, 'id' => $linkId]) > 0;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public static function hydrateLink(array $row): array
    {
        $report = json_decode((string) ($row['report_json'] ?? ''), true);

        return [
            'id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id'], 'doc' => (string) $row['doc'], 'target_id' => (int) $row['target_id'],
            'token' => (string) $row['token'], 'report' => is_array($report) ? $report : [],
            'valid_until' => ($row['valid_until'] ?? null) !== null ? substr((string) $row['valid_until'], 0, 10) : null,
            'lifted_at' => ($row['lifted_at'] ?? null) !== null ? (string) $row['lifted_at'] : null,
            'lifted_by_user_id' => ($row['lifted_by_user_id'] ?? null) !== null ? (int) $row['lifted_by_user_id'] : null,
            'lifted_by_label' => (string) ($row['lifted_by_label'] ?? ''), 'lift_reason' => (string) ($row['lift_reason'] ?? ''),
            'renewed_by_ref' => ($row['renewed_by_ref'] ?? null) !== null ? (string) $row['renewed_by_ref'] : null,
            'revoked_at' => ($row['revoked_at'] ?? null) !== null ? (string) $row['revoked_at'] : null,
            'client_id' => ($row['client_id'] ?? null) !== null ? (string) $row['client_id'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    // --------------------------------------------------------------- Signalements

    /**
     * Enregistre un signalement, numéroté « SIG-AAAA-NNNN » par communauté et par année.
     *
     * @param array<string, mixed> $r what, seen_at, place, grid, pos_x, pos_y, body, direction, vehicle, certainty, photo_path, source, client_id, user_id, label
     * @return array<string, mixed>|null le signalement créé, ou null (numéro non réservé après plusieurs essais)
     */
    public function createReport(int $tenantId, int $linkId, array $r): ?array
    {
        $year = (int) gmdate('Y');
        $cid = self::validClientId($r['client_id'] ?? null) ? (string) $r['client_id'] : null;
        for ($attempt = 0; $attempt < 5; ++$attempt) {
            $seq = (int) ($this->db->fetchOne('SELECT COALESCE(MAX(ref_seq), 0) AS n FROM sse_alert_reports WHERE tenant_id = :t AND ref_year = :y', ['t' => $tenantId, 'y' => $year])['n'] ?? 0) + 1 + $attempt;
            try {
                $id = $this->db->insert(
                    'INSERT INTO sse_alert_reports (tenant_id, link_id, ref, ref_year, ref_seq, what, seen_at, place, grid, pos_x, pos_y, body, direction, vehicle, certainty, photo_path, status, source, client_id, reporter_user_id, reporter_label, created_at)
                     VALUES (:t, :l, :ref, :y, :seq, :what, :seen, :place, :grid, :x, :py, :body, :dir, :veh, :sure, :photo, :st, :src, :cid, :u, :lbl, :now)',
                    [
                        't' => $tenantId, 'l' => $linkId, 'ref' => sprintf('SIG-%d-%04d', $year, $seq), 'y' => $year, 'seq' => $seq,
                        'what' => ($r['what'] ?? '') === 'info' ? 'info' : 'seen', 'seen' => (string) ($r['seen_at'] ?? gmdate('Y-m-d H:i:s')),
                        'place' => self::orNull($r['place'] ?? null, 160), 'grid' => self::orNull($r['grid'] ?? null, 24),
                        'x' => is_numeric($r['pos_x'] ?? null) ? (float) $r['pos_x'] : null, 'py' => is_numeric($r['pos_y'] ?? null) ? (float) $r['pos_y'] : null,
                        'body' => mb_substr((string) ($r['body'] ?? ''), 0, 600), 'dir' => self::orNull($r['direction'] ?? null, 120), 'veh' => self::orNull($r['vehicle'] ?? null, 160),
                        'sure' => isset(SseAlertLevel::CERTAINTY[(string) ($r['certainty'] ?? '')]) ? (string) $r['certainty'] : 'medium',
                        'photo' => self::orNull($r['photo_path'] ?? null, 255), 'st' => 'new', 'src' => ($r['source'] ?? '') === 'game' ? 'game' : 'web', 'cid' => $cid,
                        'u' => $r['user_id'] ?? null, 'lbl' => self::orNull($r['label'] ?? null, 80), 'now' => gmdate('Y-m-d H:i:s'),
                    ]
                );

                return $this->findReport($tenantId, $id);
            } catch (\PDOException) {
                if ($cid !== null) {
                    $same = $this->reportByClient($tenantId, $cid);
                    if ($same !== null) {
                        return $same;
                    }
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function findReport(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_reports WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);

        return $row !== null ? self::hydrateReport($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function reportByClient(int $tenantId, string $cid): ?array
    {
        if (!self::validClientId($cid) || !self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_reports WHERE tenant_id = :t AND client_id = :c', ['t' => $tenantId, 'c' => $cid]);

        return $row !== null ? self::hydrateReport($row) : null;
    }

    /** @return list<array<string, mixed>> signalements d'un lien, du plus récent au plus ancien */
    public function reportsFor(int $tenantId, int $linkId, int $limit = 100): array
    {
        if (!self::ready()) {
            return [];
        }

        return array_map(self::hydrateReport(...), $this->db->fetchAll('SELECT * FROM sse_alert_reports WHERE tenant_id = :t AND link_id = :l ORDER BY id DESC LIMIT ' . max(1, min(300, $limit)), ['t' => $tenantId, 'l' => $linkId]));
    }

    /**
     * Signalements de la communauté, filtrés par état, du plus récent au plus ancien.
     *
     * @return list<array<string, mixed>>
     */
    public function listReports(int $tenantId, string $status = '', int $limit = 100): array
    {
        if (!self::ready()) {
            return [];
        }
        $sql = 'SELECT r.*, l.doc AS link_doc, l.target_id AS link_target FROM sse_alert_reports r JOIN sse_alert_links l ON l.id = r.link_id AND l.tenant_id = r.tenant_id WHERE r.tenant_id = :t';
        $params = ['t' => $tenantId];
        if (isset(SseAlertLevel::REPORT_STATUSES[$status])) {
            $sql .= ' AND r.status = :s';
            $params['s'] = $status;
        }

        return array_map(self::hydrateReport(...), $this->db->fetchAll($sql . ' ORDER BY r.id DESC LIMIT ' . max(1, min(300, $limit)), $params));
    }

    /** @return array<int, int> nombre de signalements par lien */
    public function reportCounts(int $tenantId, array $linkIds, string $status = ''): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $linkIds), static fn (int $i): bool => $i > 0))), 0, 400);
        if ($ids === [] || !self::ready()) {
            return [];
        }
        $params = ['t' => $tenantId];
        $marks = [];
        foreach ($ids as $k => $id) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $id;
        }
        $sql = 'SELECT link_id, COUNT(*) AS n FROM sse_alert_reports WHERE tenant_id = :t AND link_id IN (' . implode(', ', $marks) . ')';
        if (isset(SseAlertLevel::REPORT_STATUSES[$status])) {
            $sql .= ' AND status = :s';
            $params['s'] = $status;
        }
        $out = [];
        foreach ($this->db->fetchAll($sql . ' GROUP BY link_id', $params) as $row) {
            $out[(int) $row['link_id']] = (int) $row['n'];
        }

        return $out;
    }

    /** Signalements de ce compte depuis $since (UTC) : plafond de débit. */
    public function countReportsSince(int $tenantId, int $userId, string $since): int
    {
        return (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_alert_reports WHERE tenant_id = :t AND reporter_user_id = :u AND created_at >= :s', ['t' => $tenantId, 'u' => $userId, 's' => $since])['n'] ?? 0);
    }

    public function setReportStatus(int $tenantId, int $id, string $status, string $note, ?int $userId): bool
    {
        if (!isset(SseAlertLevel::REPORT_STATUSES[$status])) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_alert_reports SET status = :s, status_note = :n, handled_by_user_id = :u, handled_at = :now WHERE tenant_id = :t AND id = :id',
            ['s' => $status, 'n' => self::orNull($note, 500), 'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'), 't' => $tenantId, 'id' => $id]
        ) > 0;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public static function hydrateReport(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id'], 'link_id' => (int) $row['link_id'], 'ref' => (string) $row['ref'],
            'what' => (string) $row['what'], 'seen_at' => (string) $row['seen_at'], 'place' => (string) ($row['place'] ?? ''), 'grid' => (string) ($row['grid'] ?? ''),
            'pos_x' => ($row['pos_x'] ?? null) !== null ? (float) $row['pos_x'] : null, 'pos_y' => ($row['pos_y'] ?? null) !== null ? (float) $row['pos_y'] : null,
            'body' => (string) $row['body'], 'direction' => (string) ($row['direction'] ?? ''), 'vehicle' => (string) ($row['vehicle'] ?? ''),
            'certainty' => (string) $row['certainty'], 'photo_path' => ($row['photo_path'] ?? null) !== null ? (string) $row['photo_path'] : null,
            'status' => (string) $row['status'], 'status_note' => (string) ($row['status_note'] ?? ''),
            'handled_by_user_id' => ($row['handled_by_user_id'] ?? null) !== null ? (int) $row['handled_by_user_id'] : null,
            'handled_at' => ($row['handled_at'] ?? null) !== null ? (string) $row['handled_at'] : null,
            'source' => (string) $row['source'], 'client_id' => ($row['client_id'] ?? null) !== null ? (string) $row['client_id'] : null,
            'reporter_user_id' => ($row['reporter_user_id'] ?? null) !== null ? (int) $row['reporter_user_id'] : null,
            'reporter_label' => (string) ($row['reporter_label'] ?? ''), 'created_at' => (string) $row['created_at'],
            'link_doc' => (string) ($row['link_doc'] ?? ''), 'link_target' => (int) ($row['link_target'] ?? 0),
        ];
    }

    // ---------------------------------------------------------------- Propositions

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function createProposal(int $tenantId, string $doc, array $payload, ?string $cid, ?int $userId, string $label, string $source = 'game'): ?array
    {
        $cid = self::validClientId($cid) ? $cid : null;
        try {
            $id = $this->db->insert(
                'INSERT INTO sse_alert_proposals (tenant_id, doc, payload_json, state, source, client_id, created_by_user_id, created_by_label, created_at) VALUES (:t, :d, :p, :s, :src, :cid, :u, :l, :now)',
                ['t' => $tenantId, 'd' => in_array($doc, self::DOCS, true) ? $doc : self::DOC_VIGILANCE, 'p' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 's' => 'pending', 'src' => $source === 'web' ? 'web' : 'game', 'cid' => $cid, 'u' => $userId, 'l' => mb_substr($label, 0, 80), 'now' => gmdate('Y-m-d H:i:s')]
            );
        } catch (\PDOException) {
            return $cid !== null ? $this->proposalByClient($tenantId, $cid) : null;
        }

        return $this->findProposal($tenantId, $id);
    }

    /** @return array<string, mixed>|null */
    public function findProposal(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_proposals WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);

        return $row !== null ? self::hydrateProposal($row) : null;
    }

    /** @return array<string, mixed>|null */
    public function proposalByClient(int $tenantId, string $cid): ?array
    {
        if (!self::validClientId($cid) || !self::ready()) {
            return null;
        }
        $row = $this->db->fetchOne('SELECT * FROM sse_alert_proposals WHERE tenant_id = :t AND client_id = :c', ['t' => $tenantId, 'c' => $cid]);

        return $row !== null ? self::hydrateProposal($row) : null;
    }

    /** @return list<array<string, mixed>> */
    public function listProposals(int $tenantId, string $state = 'pending', int $limit = 100): array
    {
        if (!self::ready()) {
            return [];
        }

        return array_map(self::hydrateProposal(...), $this->db->fetchAll('SELECT * FROM sse_alert_proposals WHERE tenant_id = :t AND state = :s ORDER BY id DESC LIMIT ' . max(1, min(300, $limit)), ['t' => $tenantId, 's' => $state]));
    }

    /**
     * Propositions faites par un compte (du jeu ou du site), les plus récentes d'abord : leur état se rend au téléphone.
     *
     * @return list<array<string, mixed>>
     */
    public function proposalsOf(int $tenantId, int $userId, int $limit = 30): array
    {
        if (!self::ready() || $userId < 1) {
            return [];
        }

        return array_map(self::hydrateProposal(...), $this->db->fetchAll('SELECT * FROM sse_alert_proposals WHERE tenant_id = :t AND created_by_user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(60, $limit)), ['t' => $tenantId, 'u' => $userId]));
    }

    /** Décide une proposition encore en attente (une seule fois). */
    public function decideProposal(int $tenantId, int $id, bool $accept, string $note, ?int $userId, ?string $resultRef = null): bool
    {
        return $this->db->execute(
            'UPDATE sse_alert_proposals SET state = :s, decision_note = :n, decided_by_user_id = :u, decided_at = :now, result_ref = :r WHERE tenant_id = :t AND id = :id AND state = :p',
            ['s' => $accept ? 'accepted' : 'rejected', 'n' => self::orNull($note, 500), 'u' => $userId, 'now' => gmdate('Y-m-d H:i:s'), 'r' => $resultRef, 't' => $tenantId, 'id' => $id, 'p' => 'pending']
        ) > 0;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    public static function hydrateProposal(array $row): array
    {
        $payload = json_decode((string) ($row['payload_json'] ?? ''), true);

        return [
            'id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id'], 'doc' => (string) $row['doc'], 'ref' => 'PROP-' . (int) $row['id'],
            'payload' => is_array($payload) ? $payload : [], 'state' => (string) $row['state'], 'result_ref' => ($row['result_ref'] ?? null) !== null ? (string) $row['result_ref'] : null,
            'decision_note' => (string) ($row['decision_note'] ?? ''), 'decided_at' => ($row['decided_at'] ?? null) !== null ? (string) $row['decided_at'] : null,
            'source' => (string) ($row['source'] ?? 'game'), 'client_id' => ($row['client_id'] ?? null) !== null ? (string) $row['client_id'] : null,
            'created_by_user_id' => ($row['created_by_user_id'] ?? null) !== null ? (int) $row['created_by_user_id'] : null,
            'created_by_label' => (string) ($row['created_by_label'] ?? ''), 'created_at' => (string) $row['created_at'],
        ];
    }

    // --------------------------------------------------------------------- Accusés

    /** Accuse lecture (une ligne par compte et par lien). Retourne la date de l'accusé. */
    public function ack(int $tenantId, int $linkId, int $userId): string
    {
        $row = $this->db->fetchOne('SELECT acked_at FROM sse_alert_acks WHERE tenant_id = :t AND link_id = :l AND user_id = :u', ['t' => $tenantId, 'l' => $linkId, 'u' => $userId]);
        if ($row !== null) {
            return (string) $row['acked_at'];
        }
        $now = gmdate('Y-m-d H:i:s');
        try {
            $this->db->insert('INSERT INTO sse_alert_acks (tenant_id, link_id, user_id, acked_at) VALUES (:t, :l, :u, :now)', ['t' => $tenantId, 'l' => $linkId, 'u' => $userId, 'now' => $now]);
        } catch (\PDOException) {
            $row = $this->db->fetchOne('SELECT acked_at FROM sse_alert_acks WHERE tenant_id = :t AND link_id = :l AND user_id = :u', ['t' => $tenantId, 'l' => $linkId, 'u' => $userId]);

            return (string) ($row['acked_at'] ?? $now);
        }

        return $now;
    }

    /** @return array<int, true> liens dont ce compte a accusé lecture */
    public function ackedBy(int $tenantId, int $userId): array
    {
        if ($userId < 1 || !self::ready()) {
            return [];
        }
        $out = [];
        foreach ($this->db->fetchAll('SELECT link_id FROM sse_alert_acks WHERE tenant_id = :t AND user_id = :u LIMIT 2000', ['t' => $tenantId, 'u' => $userId]) as $row) {
            $out[(int) $row['link_id']] = true;
        }

        return $out;
    }

    public function ackCount(int $tenantId, int $linkId): int
    {
        if (!self::ready()) {
            return 0;
        }

        return (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_alert_acks WHERE tenant_id = :t AND link_id = :l', ['t' => $tenantId, 'l' => $linkId])['n'] ?? 0);
    }

    private static function orNull(mixed $v, int $max): ?string
    {
        $s = is_scalar($v) ? trim((string) $v) : '';

        return $s !== '' ? mb_substr($s, 0, $max) : null;
    }
}
