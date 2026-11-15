<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseAttachCatalog as A;

/**
 * Rattachements enrichis (sse_link_attachments), catalogue des étiquettes (sse_attach_tags) et pistes (sse_leads) de l'étape
 * sse_attachments. Toute requête porte la communauté. Sans les tables, ready() est faux : la fenêtre « Rattacher » passe en
 * mode dégradé et rien n'est écrit ici.
 */
final class SseAttachmentRepository
{
    private static ?\WeakMap $readyByConnection = null;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /** Les trois tables de l'étape existent-elles ? Résultat positif gardé par connexion. */
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
                $pdo->query('SELECT 1 FROM sse_link_attachments WHERE 1 = 0');
                $pdo->query('SELECT 1 FROM sse_attach_tags WHERE 1 = 0');
                $pdo->query('SELECT 1 FROM sse_leads WHERE 1 = 0');
                $ok = true;
            } catch (\Throwable) {
                $ok = false;
            }
            self::$readyByConnection[$pdo] = ['ok' => $ok, 'at' => time()];

            return $ok;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function forgetReady(): void
    {
        self::$readyByConnection = null;
    }

    // ------------------------------------------------------------------ Rattachements

    /**
     * @param array<string, mixed> $row link_id, source_type, source_id, target_type, target_id, nature, nature_label, label, reason,
     *                                  note, tags (liste d'identifiants), align_requested, align_from, align_to, user_id
     */
    public function create(int $tenantId, array $row): int
    {
        return $this->db->insert(
            'INSERT INTO sse_link_attachments (tenant_id, link_id, source_type, source_id, target_type, target_id, nature, nature_label, label, reason, note, tags,
                align_requested, align_from, align_to, status, created_by_user_id, created_at)
             VALUES (:t, :link, :st, :si, :tt, :ti, :nature, :nl, :label, :reason, :note, :tags, :ar, :af, :at2, :status, :uid, :at)',
            [
                't' => $tenantId, 'link' => (int) $row['link_id'], 'st' => (string) $row['source_type'], 'si' => (string) $row['source_id'],
                'tt' => (string) $row['target_type'], 'ti' => (string) $row['target_id'], 'nature' => (string) $row['nature'],
                'nl' => mb_substr((string) ($row['nature_label'] ?? ''), 0, 80), 'label' => mb_substr((string) ($row['label'] ?? ''), 0, A::LABEL_MAX),
                'reason' => mb_substr((string) ($row['reason'] ?? ''), 0, A::REASON_MAX), 'note' => mb_substr((string) ($row['note'] ?? ''), 0, A::NOTE_MAX),
                'tags' => self::packTags((array) ($row['tags'] ?? [])), 'ar' => !empty($row['align_requested']) ? 1 : 0,
                'af' => $row['align_from'] ?? null, 'at2' => $row['align_to'] ?? null, 'status' => 'active',
                'uid' => (int) ($row['user_id'] ?? 0) ?: null, 'at' => gmdate('Y-m-d H:i:s'),
            ]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $tenantId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sse_link_attachments WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);
    }

    /** @return array<string, mixed>|null le rattachement actif d'un lien */
    public function findByLink(int $tenantId, int $linkId): ?array
    {
        return $this->db->fetchOne("SELECT * FROM sse_link_attachments WHERE tenant_id = :t AND link_id = :l AND status = 'active'", ['t' => $tenantId, 'l' => $linkId]);
    }

    /**
     * Rattachements actifs d'une cible.
     *
     * @return list<array<string, mixed>>
     */
    public function forTarget(int $tenantId, string $type, string $id, int $limit = 300): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM sse_link_attachments WHERE tenant_id = :t AND target_type = :ty AND target_id = :id AND status = 'active' ORDER BY created_at DESC, id DESC LIMIT " . max(1, min(500, $limit)),
            ['t' => $tenantId, 'ty' => $type, 'id' => $id]
        );
    }

    /**
     * Rattachements actifs d'une pièce (pour « déjà rattaché »).
     *
     * @return list<array<string, mixed>>
     */
    public function forSource(int $tenantId, string $type, string $id): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM sse_link_attachments WHERE tenant_id = :t AND source_type = :ty AND source_id = :id AND status = 'active' ORDER BY id DESC LIMIT 300",
            ['t' => $tenantId, 'ty' => $type, 'id' => $id]
        );
    }

    /**
     * Nombre de rattachements actifs par cible d'un type.
     *
     * @return array<string, int> identifiant de cible => nombre
     */
    public function countsByTarget(int $tenantId, string $type): array
    {
        $out = [];
        foreach ($this->db->fetchAll(
            "SELECT target_id, COUNT(*) AS n FROM sse_link_attachments WHERE tenant_id = :t AND target_type = :ty AND status = 'active' GROUP BY target_id",
            ['t' => $tenantId, 'ty' => $type]
        ) as $row) {
            $out[(string) $row['target_id']] = (int) $row['n'];
        }

        return $out;
    }

    /**
     * Étiquettes posées sur les rattachements des cibles d'un type (filtre et recherche par étiquette).
     *
     * @return array<string, list<int>> identifiant de cible => identifiants d'étiquettes
     */
    public function tagsByTarget(int $tenantId, string $type): array
    {
        $out = [];
        foreach ($this->db->fetchAll(
            "SELECT target_id, tags FROM sse_link_attachments WHERE tenant_id = :t AND target_type = :ty AND status = 'active' AND tags <> ''",
            ['t' => $tenantId, 'ty' => $type]
        ) as $row) {
            $key = (string) $row['target_id'];
            $out[$key] = array_values(array_unique(array_merge($out[$key] ?? [], self::unpackTags((string) $row['tags']))));
        }

        return $out;
    }

    /** @param array<string, mixed> $fields label, reason, note, tags (liste), nature, nature_label */
    public function update(int $tenantId, int $id, array $fields, int $userId): bool
    {
        $set = [];
        $params = ['t' => $tenantId, 'id' => $id, 'uid' => $userId ?: null, 'at' => gmdate('Y-m-d H:i:s')];
        foreach (['label' => A::LABEL_MAX, 'reason' => A::REASON_MAX, 'note' => A::NOTE_MAX, 'nature' => 32, 'nature_label' => 80] as $col => $max) {
            if (array_key_exists($col, $fields)) {
                $set[] = $col . ' = :' . $col;
                $params[$col] = mb_substr((string) $fields[$col], 0, $max);
            }
        }
        if (array_key_exists('tags', $fields)) {
            $set[] = 'tags = :tags';
            $params['tags'] = self::packTags((array) $fields['tags']);
        }
        if ($set === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_link_attachments SET ' . implode(', ', $set) . ", updated_by_user_id = :uid, updated_at = :at WHERE tenant_id = :t AND id = :id AND status = 'active'",
            $params
        ) > 0;
    }

    /** Le rattachement devient un historique : statut retiré, lien oublié, motif et auteur du retrait gardés. */
    public function markRemoved(int $tenantId, int $id, int $userId, string $reason): bool
    {
        return $this->db->execute(
            "UPDATE sse_link_attachments SET status = 'removed', link_id = NULL, removed_by_user_id = :uid, removed_at = :at, removed_reason = :r WHERE tenant_id = :t AND id = :id AND status = 'active'",
            ['t' => $tenantId, 'id' => $id, 'uid' => $userId ?: null, 'at' => gmdate('Y-m-d H:i:s'), 'r' => mb_substr($reason, 0, A::REASON_MAX)]
        ) > 0;
    }

    /**
     * Rattachements actifs qui avaient demandé l'alignement vers les cibles d'un type (tâches Cerbère-Classification).
     *
     * @return list<array<string, mixed>>
     */
    public function alignRequested(int $tenantId, ?string $targetType = null, ?string $targetId = null, int $limit = 400): array
    {
        $sql = "SELECT * FROM sse_link_attachments WHERE tenant_id = :t AND status = 'active' AND align_requested = 1";
        $params = ['t' => $tenantId];
        if ($targetType !== null && $targetId !== null) {
            $sql .= ' AND target_type = :ty AND target_id = :id';
            $params += ['ty' => $targetType, 'id' => $targetId];
        }

        return $this->db->fetchAll($sql . ' ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)), $params);
    }

    public function setAligned(int $tenantId, int $id, ?string $from, string $to): void
    {
        $this->db->execute('UPDATE sse_link_attachments SET align_from = :f, align_to = :to WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id, 'f' => $from, 'to' => $to]);
    }

    // ------------------------------------------------------------------ Étiquettes

    /** @return list<array{id: int, name: string, slug: string, color: string}> */
    public function tags(int $tenantId): array
    {
        $out = [];
        // Catalogue partagé avec les fiches de renseignement (étape sse_tags) : les réglages du catalogue livré des fiches
        // (portée « field_note ») ne sont pas des étiquettes de rattachement.
        $shared = SseTagRepository::ready() ? " AND scope <> 'field_note'" : '';
        foreach ($this->db->fetchAll('SELECT id, name, slug, color FROM sse_attach_tags WHERE tenant_id = :t' . $shared . ' ORDER BY name ASC LIMIT 400', ['t' => $tenantId]) as $row) {
            $out[] = ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'slug' => (string) $row['slug'], 'color' => isset(A::TAG_COLORS[(string) $row['color']]) ? (string) $row['color'] : 'slate'];
        }

        return $out;
    }

    /** Étiquette du catalogue par son nom (créée si elle manque et si $create) : identifiant, ou 0. */
    public function tagId(int $tenantId, string $name, bool $create, int $userId, string $color = 'slate'): int
    {
        $name = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($name))), 0, A::TAG_MAX);
        $slug = A::tagSlug($name);
        if ($slug === '') {
            return 0;
        }
        $row = $this->db->fetchOne('SELECT id FROM sse_attach_tags WHERE tenant_id = :t AND slug = :s', ['t' => $tenantId, 's' => $slug]);
        if ($row !== null) {
            // Une étiquette des fiches reprise pour un rattachement devient commune aux deux.
            if ($create && SseTagRepository::ready()) {
                $this->db->execute("UPDATE sse_attach_tags SET scope = 'all' WHERE id = :id AND tenant_id = :t AND scope = 'field_note'", ['id' => (int) $row['id'], 't' => $tenantId]);
            }

            return (int) $row['id'];
        }
        if (!$create) {
            return 0;
        }
        try {
            return $this->db->insert(
                'INSERT INTO sse_attach_tags (tenant_id, name, slug, color, created_by_user_id, created_at) VALUES (:t, :n, :s, :c, :u, :at)',
                ['t' => $tenantId, 'n' => $name, 's' => $slug, 'c' => isset(A::TAG_COLORS[$color]) ? $color : 'slate', 'u' => $userId ?: null, 'at' => gmdate('Y-m-d H:i:s')]
            );
        } catch (\Throwable) {
            $row = $this->db->fetchOne('SELECT id FROM sse_attach_tags WHERE tenant_id = :t AND slug = :s', ['t' => $tenantId, 's' => $slug]);

            return $row !== null ? (int) $row['id'] : 0;
        }
    }

    /** @param list<int|string> $ids */
    public static function packTags(array $ids): string
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0))), 0, A::TAGS_PER_LINK);

        return $ids === [] ? '' : ',' . implode(',', $ids) . ',';
    }

    /** @return list<int> */
    public static function unpackTags(string $packed): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $packed)), static fn (int $i): bool => $i > 0));
    }

    // ------------------------------------------------------------------ Pistes

    /** @param array<string, mixed> $data title, description, classification, status, owner_user_id, user_id */
    public function createLead(int $tenantId, array $data): int
    {
        $year = gmdate('Y');
        for ($try = 0; $try < 5; ++$try) {
            $row = $this->db->fetchOne('SELECT COUNT(*) AS n FROM sse_leads WHERE tenant_id = :t AND reference_code LIKE :p', ['t' => $tenantId, 'p' => 'PST-' . $year . '-%']);
            $ref = 'PST-' . $year . '-' . str_pad((string) ((int) ($row['n'] ?? 0) + 1 + $try), 4, '0', STR_PAD_LEFT);
            try {
                return $this->db->insert(
                    'INSERT INTO sse_leads (tenant_id, reference_code, title, description, status, classification, owner_user_id, created_by_user_id, created_at)
                     VALUES (:t, :ref, :title, :d, :s, :c, :o, :u, :at)',
                    [
                        't' => $tenantId, 'ref' => $ref, 'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 200), 'd' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 4000),
                        's' => isset(A::LEAD_STATUSES[(string) ($data['status'] ?? '')]) ? (string) $data['status'] : 'ouverte',
                        'c' => A::isLevel((string) ($data['classification'] ?? '')) ? (string) $data['classification'] : 'encadrement',
                        'o' => (int) ($data['owner_user_id'] ?? 0) ?: null, 'u' => (int) ($data['user_id'] ?? 0) ?: null, 'at' => gmdate('Y-m-d H:i:s'),
                    ]
                );
            } catch (\Throwable) {
                // Référence prise entre-temps (clé unique) : on essaie la suivante.
            }
        }

        return 0;
    }

    /** @return array<string, mixed>|null */
    public function findLead(int $tenantId, int $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM sse_leads WHERE tenant_id = :t AND id = :id', ['t' => $tenantId, 'id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function leads(int $tenantId, int $limit = 300): array
    {
        return $this->db->fetchAll('SELECT * FROM sse_leads WHERE tenant_id = :t ORDER BY id DESC LIMIT ' . max(1, min(1000, $limit)), ['t' => $tenantId]);
    }

    /** @param array<string, mixed> $data title, description, status, classification (déjà contrôlée par l'appelant) */
    public function updateLead(int $tenantId, int $id, array $data): bool
    {
        $set = [];
        $params = ['t' => $tenantId, 'id' => $id, 'at' => gmdate('Y-m-d H:i:s')];
        foreach (['title' => 200, 'description' => 4000, 'status' => 12, 'classification' => 16] as $col => $max) {
            if (array_key_exists($col, $data)) {
                $set[] = $col . ' = :' . $col;
                $params[$col] = mb_substr(trim((string) $data[$col]), 0, $max);
            }
        }
        if ($set === []) {
            return false;
        }

        return $this->db->execute('UPDATE sse_leads SET ' . implode(', ', $set) . ', updated_at = :at WHERE tenant_id = :t AND id = :id', $params) > 0;
    }
}
