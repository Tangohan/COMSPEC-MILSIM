<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseSheetCatalog as D;

/**
 * Fiches PDF : documents émis (texte imprimé et instantané des données), brouillons et trames de phrases. Toutes les
 * lectures et écritures sont bornées à la communauté ; rien n'est jamais effacé d'un document émis (ajout seul).
 */
final class SseSheetRepository
{
    public const MAX_DRAFTS_PER_SUBJECT = 20;

    public const MAX_PHRASES = 200;

    private Database $db;

    /** @var \WeakMap<\PDO, array{ok: bool, at: int}>|null */
    private static ?\WeakMap $readyByConnection = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** Les trois tables existent-elles ? (étape 4660 pas encore passée : seul l'avis de recherche reste émettable) */
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
                $pdo->query('SELECT 1 FROM sse_doc_issues WHERE 1 = 0');
                $pdo->query('SELECT 1 FROM sse_doc_drafts WHERE 1 = 0');
                $pdo->query('SELECT 1 FROM sse_doc_phrases WHERE 1 = 0');
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

    // ------------------------------------------------------------------ Émissions

    /**
     * Prochaine référence libre : « FV-2026-0007 » (préfixe du modèle, année, compteur par communauté, modèle et année).
     * Une référence de base imposée (avis de recherche) reçoit un suffixe d'émission « -E2 ».
     *
     * @return array{ref: string, year: int, seq: int}
     */
    public function nextRef(int $tenantId, string $model, ?string $baseRef = null, int $skip = 0): array
    {
        $year = (int) gmdate('Y');
        $seq = (int) ($this->db->fetchOne('SELECT COALESCE(MAX(ref_seq), 0) AS n FROM sse_doc_issues WHERE tenant_id = :t AND model = :m AND ref_year = :y', ['t' => $tenantId, 'm' => $model, 'y' => $year])['n'] ?? 0) + 1 + $skip;
        $prefix = D::MODELS[$model]['prefix'] ?? 'FX';
        $ref = $baseRef !== null && $baseRef !== '' ? mb_substr($baseRef, 0, 34) . '-E' . $seq : sprintf('%s-%d-%04d', $prefix, $year, $seq);

        return ['ref' => $ref, 'year' => $year, 'seq' => $seq];
    }

    /**
     * Enregistre une émission (ajout seul) sous la référence réservée par nextRef(). La clé unique refuse un doublon (course) :
     * l'appelant réserve alors une autre référence.
     *
     * @param array{person_id: ?int, identity_id: ?int, model: string, lang: string, classification: string, faces: string, texts: array<string, string>, snapshot: array<string, mixed>, sensitive: list<string>, user_id: ?int, label: string} $data
     * @param array{ref: string, year: int, seq: int} $ref
     * @throws \PDOException
     */
    public function createIssue(int $tenantId, array $data, array $ref): int
    {
        return $this->db->insert(
            'INSERT INTO sse_doc_issues (tenant_id, person_id, identity_id, model, ref, ref_year, ref_seq, lang, classification, faces, texts_json, snapshot_json, sensitive_json, issued_by_user_id, issued_by_label, issued_at)
             VALUES (:t, :p, :i, :m, :ref, :y, :seq, :lang, :cl, :faces, :texts, :snap, :sens, :u, :lbl, :now)',
            [
                't' => $tenantId, 'p' => $data['person_id'], 'i' => $data['identity_id'], 'm' => $data['model'], 'ref' => $ref['ref'], 'y' => $ref['year'], 'seq' => $ref['seq'],
                'lang' => D::lang($data['lang']), 'cl' => mb_substr($data['classification'], 0, 20), 'faces' => mb_substr($data['faces'], 0, 4),
                'texts' => json_encode($data['texts'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'snap' => json_encode($data['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
                'sens' => json_encode($data['sensitive'], JSON_UNESCAPED_UNICODE),
                'u' => $data['user_id'], 'lbl' => mb_substr(str_replace(["", "
"], ' ', $data['label']), 0, 80), 'now' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** Émissions de cet utilisateur depuis $since (UTC, « Y-m-d H:i:s ») : plafond de débit. */
    public function countIssuesSince(int $tenantId, int $userId, string $since): int
    {
        return (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_doc_issues WHERE tenant_id = :t AND issued_by_user_id = :u AND issued_at >= :s', ['t' => $tenantId, 'u' => $userId, 's' => $since])['n'] ?? 0);
    }

    /** @return array<string, mixed>|null */
    public function findIssue(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_doc_issues WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);

        return $row !== null ? self::hydrateIssue($row) : null;
    }

    /**
     * Documents émis pour une personne et/ou les identités du registre qui lui sont rattachées, du plus récent au plus ancien.
     *
     * @param list<int> $identityIds
     * @return list<array<string, mixed>>
     */
    public function listIssues(int $tenantId, ?int $personId, array $identityIds, int $limit = 50): array
    {
        [$where, $params] = $this->subjectWhere($tenantId, $personId, $identityIds);
        if ($where === '') {
            return [];
        }
        $rows = $this->db->fetchAll('SELECT * FROM sse_doc_issues WHERE tenant_id = :t AND ' . $where . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)), $params);

        return array_map(self::hydrateIssue(...), $rows);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function hydrateIssue(array $row): array
    {
        $texts = json_decode((string) ($row['texts_json'] ?? ''), true);
        $snap = json_decode((string) ($row['snapshot_json'] ?? ''), true);
        $sens = json_decode((string) ($row['sensitive_json'] ?? ''), true);

        return [
            'id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id'],
            'person_id' => ($row['person_id'] ?? null) !== null ? (int) $row['person_id'] : null,
            'identity_id' => ($row['identity_id'] ?? null) !== null ? (int) $row['identity_id'] : null,
            'model' => (string) $row['model'], 'ref' => (string) $row['ref'], 'lang' => D::lang($row['lang'] ?? 'fr'),
            'classification' => (string) $row['classification'], 'faces' => (string) $row['faces'],
            'texts' => is_array($texts) ? $texts : [], 'snapshot' => is_array($snap) ? $snap : [], 'sensitive' => is_array($sens) ? array_values($sens) : [],
            'issued_by_user_id' => ($row['issued_by_user_id'] ?? null) !== null ? (int) $row['issued_by_user_id'] : null,
            'issued_by_label' => (string) ($row['issued_by_label'] ?? ''), 'issued_at' => (string) $row['issued_at'],
        ];
    }

    // ----------------------------------------------------------------- Brouillons

    /**
     * @param list<int> $identityIds
     * @return list<array<string, mixed>>
     */
    public function listDrafts(int $tenantId, ?int $personId, array $identityIds, string $model = ''): array
    {
        [$where, $params] = $this->subjectWhere($tenantId, $personId, $identityIds);
        if ($where === '') {
            return [];
        }
        if ($model !== '') {
            $where .= ' AND model = :m';
            $params['m'] = $model;
        }

        return array_map(self::hydrateDraft(...), $this->db->fetchAll('SELECT * FROM sse_doc_drafts WHERE tenant_id = :t AND ' . $where . ' ORDER BY updated_at DESC, id DESC LIMIT 100', $params));
    }

    /** @return array<string, mixed>|null */
    public function findDraft(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_doc_drafts WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);

        return $row !== null ? self::hydrateDraft($row) : null;
    }

    /**
     * Crée (id nul) ou met à jour un brouillon de la communauté. Le brouillon garde son sujet et son modèle.
     *
     * @param array<string, mixed> $fields {texts: array<string,string>, params: array<string,mixed>}
     */
    public function saveDraft(int $tenantId, ?int $id, ?int $personId, ?int $identityId, string $model, string $lang, string $title, array $fields, ?int $userId): int
    {
        $json = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
        $title = mb_substr(trim(str_replace(["\r", "\n"], ' ', $title)), 0, 80);
        $now = gmdate('Y-m-d H:i:s');
        if ($id !== null && $id > 0) {
            $this->db->execute(
                'UPDATE sse_doc_drafts SET lang = :lang, title = :title, fields_json = :f, updated_by_user_id = :u, updated_at = :now WHERE tenant_id = :t AND id = :id',
                ['lang' => D::lang($lang), 'title' => $title, 'f' => $json, 'u' => $userId, 'now' => $now, 't' => $tenantId, 'id' => $id]
            );

            return $id;
        }

        return $this->db->insert(
            'INSERT INTO sse_doc_drafts (tenant_id, person_id, identity_id, model, lang, title, fields_json, created_by_user_id, updated_by_user_id, created_at, updated_at)
             VALUES (:t, :p, :i, :m, :lang, :title, :f, :u, :u2, :now, :now2)',
            ['t' => $tenantId, 'p' => $personId, 'i' => $identityId, 'm' => $model, 'lang' => D::lang($lang), 'title' => $title, 'f' => $json, 'u' => $userId, 'u2' => $userId, 'now' => $now, 'now2' => $now]
        );
    }

    public function deleteDraft(int $tenantId, int $id): bool
    {
        return $this->db->execute('DELETE FROM sse_doc_drafts WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]) > 0;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function hydrateDraft(array $row): array
    {
        $f = json_decode((string) ($row['fields_json'] ?? ''), true);
        $f = is_array($f) ? $f : [];

        return [
            'id' => (int) $row['id'], 'tenant_id' => (int) $row['tenant_id'],
            'person_id' => ($row['person_id'] ?? null) !== null ? (int) $row['person_id'] : null,
            'identity_id' => ($row['identity_id'] ?? null) !== null ? (int) $row['identity_id'] : null,
            'model' => (string) $row['model'], 'lang' => D::lang($row['lang'] ?? 'fr'), 'title' => (string) ($row['title'] ?? ''),
            'texts' => is_array($f['texts'] ?? null) ? $f['texts'] : [], 'params' => is_array($f['params'] ?? null) ? $f['params'] : [],
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /** @param list<int> $identityIds @return array{0: string, 1: array<string, mixed>} */
    private function subjectWhere(int $tenantId, ?int $personId, array $identityIds): array
    {
        $parts = [];
        $params = ['t' => $tenantId];
        if ($personId !== null && $personId > 0) {
            $parts[] = 'person_id = :p';
            $params['p'] = $personId;
        }
        $marks = [];
        foreach (array_slice(array_values(array_filter($identityIds, static fn (int $i): bool => $i > 0)), 0, 20) as $k => $i) {
            $marks[] = ':i' . $k;
            $params['i' . $k] = $i;
        }
        if ($marks !== []) {
            $parts[] = 'identity_id IN (' . implode(', ', $marks) . ')';
        }

        return [$parts === [] ? '' : '(' . implode(' OR ', $parts) . ')', $params];
    }

    // -------------------------------------------------------------------- Phrases

    /** @return list<array<string, mixed>> trames du modèle (et celles de tous les modèles), dans la langue si elle est donnée */
    public function listPhrases(int $tenantId, string $model = '', string $lang = ''): array
    {
        $sql = 'SELECT * FROM sse_doc_phrases WHERE tenant_id = :t';
        $params = ['t' => $tenantId];
        if ($model !== '') {
            $sql .= " AND (model = :m OR model = '')";
            $params['m'] = $model;
        }
        if ($lang !== '') {
            $sql .= ' AND lang = :l';
            $params['l'] = D::lang($lang);
        }

        return array_map(self::hydratePhrase(...), $this->db->fetchAll($sql . ' ORDER BY label ASC, id ASC LIMIT ' . self::MAX_PHRASES, $params));
    }

    /** @return array<string, mixed>|null */
    public function findPhrase(int $tenantId, int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT * FROM sse_doc_phrases WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);

        return $row !== null ? self::hydratePhrase($row) : null;
    }

    public function savePhrase(int $tenantId, ?int $id, string $model, string $lang, string $label, string $body, ?int $userId): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $model = isset(D::MODELS[$model]) ? $model : '';
        if ($id !== null && $id > 0) {
            $this->db->execute(
                'UPDATE sse_doc_phrases SET model = :m, lang = :l, label = :lbl, body = :b, updated_at = :now WHERE tenant_id = :t AND id = :id',
                ['m' => $model, 'l' => D::lang($lang), 'lbl' => $label, 'b' => $body, 'now' => $now, 't' => $tenantId, 'id' => $id]
            );

            return $id;
        }

        return $this->db->insert(
            'INSERT INTO sse_doc_phrases (tenant_id, model, lang, label, body, created_by_user_id, created_at, updated_at) VALUES (:t, :m, :l, :lbl, :b, :u, :now, :now2)',
            ['t' => $tenantId, 'm' => $model, 'l' => D::lang($lang), 'lbl' => $label, 'b' => $body, 'u' => $userId, 'now' => $now, 'now2' => $now]
        );
    }

    public function deletePhrase(int $tenantId, int $id): bool
    {
        return $this->db->execute('DELETE FROM sse_doc_phrases WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]) > 0;
    }

    public function countPhrases(int $tenantId): int
    {
        return (int) ($this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_doc_phrases WHERE tenant_id = :t', ['t' => $tenantId])['n'] ?? 0);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function hydratePhrase(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'model' => (string) ($row['model'] ?? ''), 'lang' => D::lang($row['lang'] ?? 'fr'),
            'label' => (string) $row['label'], 'body' => (string) $row['body'], 'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
}
