<?php

declare(strict_types=1);

namespace App\Services\Sse;

use App\Core\Database;
use App\Repositories\SseFileRecordRepository;
use App\Support\SseFileText;

/**
 * Extractions de téléphones du jeu (« rapport d'exploitation » façon extraction judiciaire) : réception depuis le mod
 * ATAK (kind phone_extraction de POST /api/sse/identity/checks, ICD-SSE-PHONE-001), stockage, lecture par onglets,
 * recherche dans l'extraction, export, rattachement à une fiche, modération.
 *
 * Données du téléphone du personnage seulement ; envoi refusé sans l'accord du joueur, si le membre l'a retiré pour ce
 * téléphone, ou si la communauté a coupé la fonction. Le compte est toujours celui du jeton de jeu.
 */
final class SsePhoneExtractionService
{
    public const KIND = 'phone_extraction';

    public const CATEGORIES = [
        'contacts' => 'Contacts',
        'calls' => 'Journal d’appels',
        'messages' => 'Messages',
        'notes' => 'Notes',
        'photos' => 'Photos',
        'apps' => 'Applications',
        'positions' => 'Positions',
        'places' => 'Lieux enregistrés',
    ];

    public const MAX_PER_CATEGORY = 300;
    public const MAX_ITEMS = 2000;
    public const MAX_BODY_BYTES = 262144;
    public const MAX_TEXT = 1000;
    public const MAX_PARTS = 20;

    private Database $db;

    public function __construct(private ?SseFileRecordRepository $records = null, private ?SseFileXrefService $xref = null)
    {
        $this->records ??= new SseFileRecordRepository();
        $this->db = $this->records->db();
        $this->xref ??= new SseFileXrefService($this->records);
    }

    public static function ref(int $id): string
    {
        return 'EXT-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Réception d'une partie d'extraction.
     *
     * @param array<string, mixed> $body
     * @return array{id: int, ref: string, duplicate: bool}|array{error: string, status: int}
     */
    public function ingest(int $tenantId, ?int $userId, array $body, int $rawBytes): array
    {
        if ($userId === null || $userId < 1) {
            return ['error' => 'unlinked', 'status' => 403];
        }
        if ($rawBytes > self::MAX_BODY_BYTES) {
            return ['error' => 'too_large', 'status' => 422];
        }
        if (empty($this->records->settings($tenantId)['extractions_enabled'])) {
            return ['error' => 'extractions_disabled', 'status' => 422];
        }
        if (($body['consent'] ?? null) !== true) {
            return ['error' => 'consent_required', 'status' => 422];
        }
        $key = self::str($body['idempotency_key'] ?? null, 80);
        $extKey = self::str($body['extraction_key'] ?? null, 80) ?? $key;
        if ($key === null || strlen($key) < 4 || $extKey === null || strlen($extKey) < 4) {
            return ['error' => 'idempotency_key_required', 'status' => 422];
        }
        $device = is_array($body['device'] ?? null) ? $body['device'] : [];
        $uid = self::str($device['uid'] ?? null, 64);
        if ($uid === null || preg_match('/^[A-Za-z0-9_.:\-]{3,64}$/', $uid) !== 1) {
            return ['error' => 'invalid_device', 'status' => 422];
        }
        $part = is_array($body['part'] ?? null) ? array_values($body['part']) : [1, 1];
        $pi = (int) ($part[0] ?? 1);
        $pn = (int) ($part[1] ?? 1);
        if ($pi < 1 || $pn < 1 || $pi > $pn || $pn > self::MAX_PARTS) {
            return ['error' => 'invalid_part', 'status' => 422];
        }
        // Accord retiré par le membre pour ce téléphone : rien n'est enregistré.
        $link = $this->db->fetchOne('SELECT * FROM sse_phone_links WHERE tenant_id = :t AND user_id = :u AND device_uid = :d', ['t' => $tenantId, 'u' => $userId, 'd' => $uid]);
        if ($link !== null && $link['revoked_at'] !== null) {
            return ['error' => 'consent_revoked', 'status' => 422];
        }
        $items = self::items(is_array($body['data'] ?? null) ? $body['data'] : []);
        if (isset($items['error'])) {
            return ['error' => $items['error'], 'status' => 422];
        }

        $existing = $this->db->fetchOne('SELECT * FROM sse_phone_extractions WHERE tenant_id = :t AND idempotency_key = :k', ['t' => $tenantId, 'k' => $extKey]);
        $now = gmdate('Y-m-d H:i:s');
        $counts = $existing !== null ? (json_decode((string) $existing['counts_json'], true) ?: []) : [];
        $parts = is_array($counts['_parts'] ?? null) ? $counts['_parts'] : [];
        if (in_array($pi, $parts, true)) {
            return ['id' => (int) $existing['id'], 'ref' => self::ref((int) $existing['id']), 'duplicate' => true];
        }
        if ($existing !== null && (int) $existing['uploader_user_id'] !== $userId) {
            // Une extraction ne se complète que par le compte qui l'a commencée.
            return ['error' => 'not_owner', 'status' => 403];
        }
        $already = array_sum(array_map('intval', array_intersect_key($counts, self::CATEGORIES)));
        if ($already + count($items['rows']) > self::MAX_ITEMS) {
            return ['error' => 'too_large', 'status' => 422];
        }
        $owner = is_array($body['owner'] ?? null) ? $body['owner'] : [];
        $network = is_array($body['data']['network'] ?? null) ? self::network($body['data']['network']) : null;
        if ($existing === null) {
            $aiUid = self::str($owner['ai_uid'] ?? null, 64);
            $identityId = $aiUid !== null ? $this->records->identityIdByAiUid($tenantId, $aiUid) : null;
            $id = $this->db->insert(
                'INSERT INTO sse_phone_extractions (tenant_id, idempotency_key, uploader_user_id, device_uid, device_label, device_model, owner_label, owner_ai_uid,
                    owner_kind, identity_id, op_key, grid, game_time, sha256, bytes, counts_json, network_json, status, created_at)
                 VALUES (:t, :k, :u, :d, :dl, :dm, :ol, :oa, :ok, :i, :op, :g, :gt, :sha, :b, :c, :n, :st, :now)',
                [
                    't' => $tenantId, 'k' => $extKey, 'u' => $userId, 'd' => $uid,
                    'dl' => self::str($device['label'] ?? null, 80), 'dm' => self::str($device['model'] ?? null, 80),
                    'ol' => self::str($owner['label'] ?? null, 120), 'oa' => $aiUid,
                    'ok' => ($owner['kind'] ?? '') === 'ai' ? 'ai' : 'player', 'i' => $identityId,
                    'op' => self::str($body['op'] ?? null, 64), 'g' => self::str($body['grid'] ?? null, 32), 'gt' => self::str($body['game_time'] ?? null, 32),
                    'sha' => hash('sha256', ''), 'b' => 0, 'c' => '{}',
                    'n' => $network !== null ? (string) json_encode($network, JSON_UNESCAPED_UNICODE) : null,
                    'st' => ($this->records->settings($tenantId)['extractions_visibility'] ?? 'portal') === 'managers' ? 'review' : 'visible',
                    'now' => $now,
                ]
            );
            $this->db->execute('UPDATE sse_phone_extractions SET ref = :r WHERE id = :id AND tenant_id = :t', ['r' => self::ref($id), 'id' => $id, 't' => $tenantId]);
        } else {
            $id = (int) $existing['id'];
            if ($network !== null) {
                $this->db->execute('UPDATE sse_phone_extractions SET network_json = :n WHERE id = :id AND tenant_id = :t', ['n' => (string) json_encode($network, JSON_UNESCAPED_UNICODE), 'id' => $id, 't' => $tenantId]);
            }
        }

        $hashCtx = (string) ($existing['sha256'] ?? '');
        foreach ($items['rows'] as $row) {
            $itemId = $this->db->insert(
                'INSERT INTO sse_phone_extraction_items (tenant_id, extraction_id, category, at, title, body, number, number_norm, grid, direction, extra_json, norm)
                 VALUES (:t, :e, :c, :at, :ti, :b, :n, :nn, :g, :dir, :x, :norm)',
                ['t' => $tenantId, 'e' => $id] + $row
            );
            $this->xref->index($tenantId, 'ext_item', $itemId, trim(implode("\n", array_filter([(string) $row['ti'], (string) $row['b'], (string) $row['n'], (string) $row['g']]))));
            $counts[$row['c']] = (int) ($counts[$row['c']] ?? 0) + 1;
        }
        $parts[] = $pi;
        sort($parts);
        $counts['_parts'] = $parts;
        $counts['_total_parts'] = $pn;
        // Empreinte de la copie : SHA-256 chaîné des parties reçues (contenu canonique de chaque partie).
        $sha = hash('sha256', $hashCtx . '|' . $pi . '|' . json_encode($items['rows'], JSON_UNESCAPED_UNICODE));
        $this->db->execute(
            'UPDATE sse_phone_extractions SET counts_json = :c, sha256 = :s, bytes = bytes + :b WHERE id = :id AND tenant_id = :t',
            ['c' => (string) json_encode($counts), 's' => $sha, 'b' => $rawBytes, 'id' => $id, 't' => $tenantId]
        );
        $this->rememberDevice($tenantId, $userId, $uid, self::str($device['label'] ?? null, 80));

        return ['id' => $id, 'ref' => self::ref($id), 'duplicate' => false];
    }

    /**
     * Éléments validés d'une partie (tailles et types bornés).
     *
     * @param array<string, mixed> $data
     * @return array{rows: list<array<string, mixed>>}|array{error: string}
     */
    public static function items(array $data): array
    {
        $rows = [];
        foreach (self::CATEGORIES as $cat => $_) {
            $list = $data[$cat] ?? [];
            if (!is_array($list)) {
                return ['error' => 'invalid_' . $cat];
            }
            if (count($list) > self::MAX_PER_CATEGORY) {
                return ['error' => 'too_large'];
            }
            foreach ($list as $it) {
                if (!is_array($it)) {
                    continue;
                }
                $row = match ($cat) {
                    'contacts' => ['ti' => self::str($it['name'] ?? null, 190), 'b' => self::str($it['note'] ?? null, self::MAX_TEXT), 'n' => self::str($it['number'] ?? null, 40), 'at' => null, 'g' => null, 'dir' => null],
                    'calls' => ['ti' => self::str($it['name'] ?? null, 190), 'b' => is_numeric($it['duration_s'] ?? null) ? 'Durée ' . max(0, min(86400, (int) $it['duration_s'])) . ' s' : null,
                        'n' => self::str($it['number'] ?? null, 40), 'at' => self::str($it['at'] ?? null, 32), 'g' => null, 'dir' => in_array($it['dir'] ?? '', ['in', 'out', 'missed'], true) ? $it['dir'] : null],
                    'messages' => ['ti' => self::str(trim(((string) ($it['from'] ?? '')) . ' → ' . ((string) ($it['to'] ?? '')), ' →'), 190), 'b' => self::str($it['body'] ?? null, self::MAX_TEXT),
                        'n' => self::str($it['channel'] ?? null, 40), 'at' => self::str($it['at'] ?? null, 32), 'g' => null, 'dir' => !empty($it['outgoing']) ? 'out' : null],
                    'notes' => ['ti' => self::str($it['title'] ?? null, 190), 'b' => self::str($it['body'] ?? null, self::MAX_TEXT), 'n' => null, 'at' => self::str($it['at'] ?? null, 32), 'g' => null, 'dir' => null],
                    'photos' => ['ti' => self::str($it['file'] ?? null, 190), 'b' => self::str($it['caption'] ?? null, self::MAX_TEXT), 'n' => null, 'at' => self::str($it['at'] ?? null, 32), 'g' => self::str($it['grid'] ?? null, 32), 'dir' => null],
                    'apps' => ['ti' => self::str($it['name'] ?? null, 190), 'b' => self::str($it['version'] ?? null, 40), 'n' => null, 'at' => null, 'g' => null, 'dir' => null],
                    'positions' => ['ti' => self::str($it['note'] ?? null, 190), 'b' => null, 'n' => null, 'at' => self::str($it['at'] ?? null, 32), 'g' => self::str($it['grid'] ?? null, 32), 'dir' => null],
                    default => ['ti' => self::str($it['name'] ?? null, 190), 'b' => self::str($it['note'] ?? null, self::MAX_TEXT), 'n' => null, 'at' => null, 'g' => self::str($it['grid'] ?? null, 32), 'dir' => null],
                };
                if ($row['ti'] === null && $row['b'] === null && $row['n'] === null && $row['g'] === null) {
                    continue;
                }
                $rows[] = [
                    'c' => $cat, 'at' => $row['at'], 'ti' => $row['ti'], 'b' => $row['b'], 'n' => $row['n'],
                    'nn' => $row['n'] !== null && $cat !== 'messages' ? (SseFileText::phoneKey($row['n']) ?: null) : null,
                    'g' => $row['g'], 'dir' => $row['dir'], 'x' => null,
                    'norm' => SseFileText::norm(implode(' ', array_filter([$row['ti'], $row['b'], $row['n'], $row['g']]))),
                ];
                if (count($rows) > self::MAX_ITEMS) {
                    return ['error' => 'too_large'];
                }
            }
        }

        return ['rows' => $rows];
    }

    /** @param array<string, mixed> $n @return array<string, mixed> */
    private static function network(array $n): array
    {
        $wifi = [];
        foreach (is_array($n['wifi'] ?? null) ? array_slice($n['wifi'], 0, 30) : [] as $w) {
            if (($s = self::str($w, 64)) !== null) {
                $wifi[] = $s;
            }
        }

        return array_filter([
            'carrier' => self::str($n['carrier'] ?? null, 60),
            'number' => self::str($n['number'] ?? null, 40),
            'imei' => self::str($n['imei'] ?? null, 40),
            'mac' => self::str($n['mac'] ?? null, 40),
            'wifi' => $wifi,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }

    private function rememberDevice(int $tenantId, int $userId, string $uid, ?string $label): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $row = $this->db->fetchOne('SELECT id FROM sse_phone_links WHERE tenant_id = :t AND user_id = :u AND device_uid = :d', ['t' => $tenantId, 'u' => $userId, 'd' => $uid]);
        if ($row === null) {
            $this->db->insert(
                'INSERT INTO sse_phone_links (tenant_id, user_id, device_uid, device_label, consent, linked_at, last_sync_at, created_at) VALUES (:t, :u, :d, :l, 1, :now, :now, :now)',
                ['t' => $tenantId, 'u' => $userId, 'd' => $uid, 'l' => $label, 'now' => $now]
            );

            return;
        }
        $this->db->execute('UPDATE sse_phone_links SET last_sync_at = :now, device_label = COALESCE(:l, device_label) WHERE id = :id AND tenant_id = :t', ['now' => $now, 'l' => $label, 'id' => (int) $row['id'], 't' => $tenantId]);
    }

    // ------------------------------------------------------------- Lecture

    /**
     * @param array{mine?: int, q?: string, status?: string, identity_id?: int, page?: int} $f
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(int $tenantId, array $f, bool $moderator): array
    {
        $where = ['e.tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!$moderator) {
            $where[] = "e.status = 'visible'";
        } elseif (in_array($f['status'] ?? '', ['visible', 'hidden', 'review'], true)) {
            $where[] = 'e.status = :st';
            $params['st'] = $f['status'];
        }
        if (($f['mine'] ?? 0) > 0) {
            $where[] = 'e.uploader_user_id = :u';
            $params['u'] = (int) $f['mine'];
        }
        if (($f['identity_id'] ?? 0) > 0) {
            $where[] = 'e.identity_id = :i';
            $params['i'] = (int) $f['identity_id'];
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(e.ref LIKE :q1 OR e.device_label LIKE :q2 OR e.owner_label LIKE :q3 OR e.device_uid LIKE :q4)';
            $params['q1'] = $params['q2'] = $params['q3'] = $params['q4'] = '%' . SseFileRecordRepository::likeEscape($q) . '%';
        }
        $w = implode(' AND ', $where);
        $total = (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_phone_extractions e WHERE ' . $w, $params)['n'] ?? 0);
        $page = max(1, (int) ($f['page'] ?? 1));
        $rows = $this->db->fetchAll('SELECT e.* FROM sse_phone_extractions e WHERE ' . $w . ' ORDER BY e.id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30), $params);

        return ['rows' => array_map([self::class, 'hydrate'], $rows), 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id, bool $moderator): ?array
    {
        $r = $this->db->fetchOne('SELECT * FROM sse_phone_extractions WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]);
        if ($r === null || (!$moderator && $r['status'] !== 'visible')) {
            return null;
        }

        return self::hydrate($r);
    }

    /**
     * Éléments d'une catégorie, filtrés par un texte (recherche dans l'extraction), avec le sujet des fichiers qui porte
     * le même numéro (contacts et appels connus aux fichiers).
     *
     * @return list<array<string, mixed>>
     */
    public function listItems(int $tenantId, int $extractionId, string $category, string $q = ''): array
    {
        $params = ['t' => $tenantId, 'e' => $extractionId];
        $where = 'tenant_id = :t AND extraction_id = :e';
        if (isset(self::CATEGORIES[$category])) {
            $where .= ' AND category = :c';
            $params['c'] = $category;
        }
        $nq = SseFileText::norm($q);
        if ($nq !== '') {
            $where .= ' AND norm LIKE :q';
            $params['q'] = '%' . SseFileRecordRepository::likeEscape($nq) . '%';
        }
        $rows = $this->db->fetchAll('SELECT * FROM sse_phone_extraction_items WHERE ' . $where . ' ORDER BY id ASC LIMIT 2000', $params);
        $numbers = array_values(array_unique(array_filter(array_map(static fn (array $r): string => (string) ($r['number_norm'] ?? ''), $rows))));
        $known = [];
        foreach ($numbers as $n) {
            foreach ($this->records->linksByValue($tenantId, 'phone', $n, 3) as $l) {
                if (($l['visibility'] ?? 'known') === 'known') {
                    $known[$n] = $l['identity_id'];
                }
            }
        }

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'category' => (string) $r['category'],
            'at' => $r['at'],
            'title' => $r['title'],
            'body' => $r['body'],
            'number' => $r['number'],
            'grid' => $r['grid'],
            'direction' => $r['direction'],
            'known_identity_id' => isset($known[(string) $r['number_norm']]) ? (int) $known[(string) $r['number_norm']] : null,
        ], $rows);
    }

    /** @return array<string, int> */
    public function counts(array $extraction): array
    {
        $c = [];
        foreach (self::CATEGORIES as $k => $_) {
            $c[$k] = (int) ($extraction['counts'][$k] ?? 0);
        }

        return $c;
    }

    public function attach(int $tenantId, int $id, ?int $identityId): bool
    {
        return $this->db->execute(
            'UPDATE sse_phone_extractions SET identity_id = :i WHERE id = :id AND tenant_id = :t',
            ['i' => $identityId !== null && $identityId > 0 ? $identityId : null, 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    public function moderate(int $tenantId, int $id, string $status, ?int $userId, string $note): bool
    {
        if (!in_array($status, ['visible', 'hidden'], true)) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_phone_extractions SET status = :s, moderated_by_user_id = :u, moderation_note = :n WHERE id = :id AND tenant_id = :t',
            ['s' => $status, 'u' => $userId, 'n' => trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null, 'id' => $id, 't' => $tenantId]
        ) > 0;
    }

    /** Suppression définitive (modération) : extraction et éléments. */
    public function purge(int $tenantId, int $id): bool
    {
        foreach ($this->db->fetchAll('SELECT id FROM sse_phone_extraction_items WHERE tenant_id = :t AND extraction_id = :e', ['t' => $tenantId, 'e' => $id]) as $it) {
            $this->db->execute("DELETE FROM sse_file_xref WHERE tenant_id = :t AND source_type = 'ext_item' AND source_id = :i", ['t' => $tenantId, 'i' => (int) $it['id']]);
        }
        $this->db->execute('DELETE FROM sse_phone_extraction_items WHERE tenant_id = :t AND extraction_id = :e', ['t' => $tenantId, 'e' => $id]);

        return $this->db->execute('DELETE FROM sse_phone_extractions WHERE id = :id AND tenant_id = :t', ['id' => $id, 't' => $tenantId]) > 0;
    }

    /** @return list<array<string, mixed>> téléphones synchronisés par le membre */
    public function devices(int $tenantId, int $userId): array
    {
        return $this->db->fetchAll('SELECT * FROM sse_phone_links WHERE tenant_id = :t AND user_id = :u ORDER BY COALESCE(last_sync_at, created_at) DESC LIMIT 50', ['t' => $tenantId, 'u' => $userId]);
    }

    public function setConsent(int $tenantId, int $userId, int $linkId, bool $allowed): bool
    {
        return $this->db->execute(
            'UPDATE sse_phone_links SET revoked_at = :r, consent = :c WHERE id = :id AND tenant_id = :t AND user_id = :u',
            ['r' => $allowed ? null : gmdate('Y-m-d H:i:s'), 'c' => $allowed ? 1 : 0, 'id' => $linkId, 't' => $tenantId, 'u' => $userId]
        ) > 0;
    }

    /** @param array<string, mixed> $r @return array<string, mixed> */
    public static function hydrate(array $r): array
    {
        $counts = json_decode((string) ($r['counts_json'] ?? ''), true);
        $network = json_decode((string) ($r['network_json'] ?? ''), true);
        $counts = is_array($counts) ? $counts : [];

        return [
            'id' => (int) $r['id'],
            'ref' => (string) ($r['ref'] ?? self::ref((int) $r['id'])),
            'uploader_user_id' => ($r['uploader_user_id'] ?? null) !== null ? (int) $r['uploader_user_id'] : null,
            'device_uid' => (string) $r['device_uid'],
            'device_label' => $r['device_label'] ?? null,
            'device_model' => $r['device_model'] ?? null,
            'owner_label' => $r['owner_label'] ?? null,
            'owner_kind' => (string) ($r['owner_kind'] ?? 'player'),
            'owner_ai_uid' => $r['owner_ai_uid'] ?? null,
            'identity_id' => ($r['identity_id'] ?? null) !== null ? (int) $r['identity_id'] : null,
            'op_key' => $r['op_key'] ?? null,
            'grid' => $r['grid'] ?? null,
            'game_time' => $r['game_time'] ?? null,
            'sha256' => (string) $r['sha256'],
            'bytes' => (int) ($r['bytes'] ?? 0),
            'counts' => $counts,
            'complete' => (int) ($counts['_total_parts'] ?? 1) <= count(is_array($counts['_parts'] ?? null) ? $counts['_parts'] : [1]),
            'network' => is_array($network) ? $network : [],
            'status' => (string) $r['status'],
            'moderation_note' => $r['moderation_note'] ?? null,
            'created_at' => (string) $r['created_at'],
        ];
    }

    private static function str(mixed $v, int $max): ?string
    {
        $s = trim(str_replace(["\r", "\t"], ' ', is_scalar($v) ? (string) $v : ''));

        return $s === '' ? null : mb_substr($s, 0, $max);
    }
}
