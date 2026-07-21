<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TrainingFormationCustomPageRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return array{sql:string, params:list<mixed>} */
    private function whereClauseForTenant(int $tenantId, array $filters): array
    {
        $sql = ' WHERE p.tenant_id = ?';
        $params = [$tenantId];

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $sql .= ' AND (p.title LIKE ? OR p.slug LIKE ? OR p.summary LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND p.status = ?';
            $params[] = $status;
        }

        $structure = trim((string) ($filters['doc_structure'] ?? ''));
        if ($structure !== '') {
            $sql .= ' AND p.doc_structure = ?';
            $params[] = $structure;
        }

        $idIn = $filters['id_in'] ?? null;
        if (is_array($idIn)) {
            $idIn = array_values(array_unique(array_filter(array_map('intval', $idIn), static fn (int $v): bool => $v > 0)));
            if ($idIn === []) {
                // Filtre actif (ex. tag) sans résultat : ne rien retourner plutôt qu'ignorer le filtre.
                $sql .= ' AND 1=0';
            } else {
                $sql .= ' AND p.id IN (' . implode(',', array_fill(0, count($idIn), '?')) . ')';
                foreach ($idIn as $pid) {
                    $params[] = $pid;
                }
            }
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /** @return list<array<string, mixed>> */
    public function listByTenant(int $tenantId, int $limit = 200, array $filters = [], int $offset = 0): array
    {
        $limit = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $where = $this->whereClauseForTenant($tenantId, $filters);
        $sql = 'SELECT p.*, t.name AS theme_name
            FROM training_formation_custom_pages p
            LEFT JOIN training_formation_custom_page_themes t ON t.id = p.theme_id AND t.tenant_id = p.tenant_id'
            . $where['sql']
            . ' ORDER BY p.updated_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($where['params']);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string,mixed> $filters */
    public function countByTenant(int $tenantId, array $filters = []): int
    {
        $where = $this->whereClauseForTenant($tenantId, $filters);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM training_formation_custom_pages p' . $where['sql']);
        $stmt->execute($where['params']);

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public function listThemesByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_page_themes WHERE tenant_id = ? ORDER BY is_system DESC, name ASC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string,mixed>> */
    public function listTemplatesByTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_page_templates WHERE tenant_id = ? ORDER BY is_system DESC, name ASC');
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_pages WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findPublishedBySlug(int $tenantId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_pages WHERE tenant_id = ? AND slug = ? AND is_published = 1 AND archived_at IS NULL LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function slugExistsForTenant(int $tenantId, string $slug, ?int $exceptId = null): bool
    {
        if ($exceptId !== null) {
            $stmt = $this->pdo->prepare('SELECT 1 FROM training_formation_custom_pages WHERE tenant_id = ? AND slug = ? AND id <> ? LIMIT 1');
            $stmt->execute([$tenantId, $slug, $exceptId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT 1 FROM training_formation_custom_pages WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $stmt->execute([$tenantId, $slug]);
        }

        return (bool) $stmt->fetchColumn();
    }

    public function create(int $tenantId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_formation_custom_pages
            (tenant_id, slug, title, subtitle, summary, doc_structure, intro_html, html_body, sections_json, theme_id, icon, accent_color, layout_mode, show_toc, show_reading_progress, visibility_level, allowed_roles_json, status, is_published, published_at, scheduled_publish_at, estimated_read_time, created_by, updated_by, last_edited_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
        );
        $stmt->execute([
            $tenantId,
            $data['slug'],
            $data['title'],
            $data['subtitle'] ?? null,
            $data['summary'] ?? null,
            $data['doc_structure'] ?? 'single',
            $data['intro_html'] ?? null,
            $data['html_body'],
            $data['sections_json'] ?? null,
            $data['theme_id'] ?? null,
            $data['icon'] ?? null,
            $data['accent_color'] ?? null,
            $data['layout_mode'] ?? 'standard',
            (int) ($data['show_toc'] ?? 1),
            (int) ($data['show_reading_progress'] ?? 1),
            $data['visibility_level'] ?? 'tenant',
            $data['allowed_roles_json'] ?? null,
            $data['status'] ?? 'draft',
            (int) ($data['is_published'] ?? 0),
            $data['published_at'] ?? null,
            $data['scheduled_publish_at'] ?? null,
            (int) ($data['estimated_read_time'] ?? 1),
            $data['created_by'] ?? null,
            $data['updated_by'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = [];
        $cols = ['slug', 'title', 'subtitle', 'summary', 'doc_structure', 'intro_html', 'html_body', 'sections_json', 'theme_id', 'icon', 'accent_color', 'layout_mode', 'show_toc', 'show_reading_progress', 'visibility_level', 'allowed_roles_json', 'status', 'is_published', 'published_at', 'scheduled_publish_at', 'archived_at', 'estimated_read_time', 'updated_by', 'last_published_by', 'canonical_url', 'meta_title', 'meta_description', 'og_image'];
        foreach ($cols as $col) {
            if (!array_key_exists($col, $data)) {
                continue;
            }
            $fields[] = $col . ' = ?';
            if (in_array($col, ['is_published', 'show_toc', 'show_reading_progress'], true)) {
                $params[] = (int) (bool) $data[$col];
            } else {
                $params[] = $data[$col];
            }
        }
        if ($fields === []) {
            return true;
        }
        $fields[] = 'updated_at = NOW()';
        $fields[] = 'last_edited_at = NOW()';
        $params[] = $id;
        $params[] = $tenantId;
        $sql = 'UPDATE training_formation_custom_pages SET ' . implode(', ', $fields) . ' WHERE id = ? AND tenant_id = ?';

        return $this->pdo->prepare($sql)->execute($params);
    }

    public function delete(int $id, int $tenantId): bool
    {
        return $this->pdo->prepare('DELETE FROM training_formation_custom_pages WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
    }

    public function incrementView(int $id, int $tenantId, ?int $userId): void
    {
        $this->pdo->prepare('UPDATE training_formation_custom_pages SET view_count = view_count + 1, last_viewed_at = NOW() WHERE id = ? AND tenant_id = ?')->execute([$id, $tenantId]);
        $this->pdo->prepare('INSERT INTO training_formation_custom_page_views (page_id, tenant_id, viewer_user_id, viewed_at) VALUES (?, ?, ?, NOW())')->execute([$id, $tenantId, $userId]);
    }

    public function createRevision(int $pageId, int $tenantId, int $userId, string $type, array $snapshot, ?string $diff): void
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(version_no), 0) + 1 FROM training_formation_custom_page_revisions WHERE page_id = ? AND tenant_id = ?');
        $stmt->execute([$pageId, $tenantId]);
        $versionNo = (int) $stmt->fetchColumn();
        $title = (string) ($snapshot['title'] ?? ('Page #' . $pageId));
        $status = (string) ($snapshot['status'] ?? 'draft');

        $ins = $this->pdo->prepare('INSERT INTO training_formation_custom_page_revisions (page_id, tenant_id, version_no, status_snapshot, revision_type, title, content_snapshot_json, summary_diff, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $ins->execute([
            $pageId,
            $tenantId,
            $versionNo,
            $status,
            $type,
            $title,
            json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $diff,
            $userId > 0 ? $userId : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listRevisions(int $pageId, int $tenantId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_page_revisions WHERE page_id = ? AND tenant_id = ? ORDER BY version_no DESC LIMIT ' . max(1, min(100, $limit)));
        $stmt->execute([$pageId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findRevision(int $pageId, int $revisionId, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_page_revisions WHERE id = ? AND page_id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$revisionId, $pageId, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function addActivity(int $pageId, int $tenantId, ?int $userId, string $action, array $details = []): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO training_formation_custom_page_activity (page_id, tenant_id, actor_user_id, action, details_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->execute([
            $pageId,
            $tenantId,
            $userId,
            $action,
            $details !== [] ? json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listActivity(int $pageId, int $tenantId, int $limit = 40): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_formation_custom_page_activity WHERE page_id = ? AND tenant_id = ? ORDER BY created_at DESC LIMIT ' . max(1, min(200, $limit)));
        $stmt->execute([$pageId, $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string,mixed> */
    public function dashboardMetrics(int $tenantId): array
    {
        $metrics = [
            'recently_modified' => 0,
            'forgotten_drafts' => 0,
            'scheduled' => 0,
            'published_without_theme' => 0,
            'never_viewed' => 0,
        ];

        $queries = [
            'recently_modified' => "SELECT COUNT(*) FROM training_formation_custom_pages WHERE tenant_id = ? AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'forgotten_drafts' => "SELECT COUNT(*) FROM training_formation_custom_pages WHERE tenant_id = ? AND status IN ('draft','review') AND updated_at < DATE_SUB(NOW(), INTERVAL 30 DAY)",
            'scheduled' => "SELECT COUNT(*) FROM training_formation_custom_pages WHERE tenant_id = ? AND status = 'scheduled' AND scheduled_publish_at IS NOT NULL",
            'published_without_theme' => "SELECT COUNT(*) FROM training_formation_custom_pages WHERE tenant_id = ? AND is_published = 1 AND theme_id IS NULL",
            'never_viewed' => "SELECT COUNT(*) FROM training_formation_custom_pages WHERE tenant_id = ? AND is_published = 1 AND view_count = 0",
        ];
        foreach ($queries as $k => $sql) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tenantId]);
            $metrics[$k] = (int) $stmt->fetchColumn();
        }

        return $metrics;
    }

    /** Brouillons/révisions non modifiés depuis {$days} jours. @return list<array<string,mixed>> */
    public function listForgottenDrafts(int $tenantId, int $days = 30, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, title, status, updated_at FROM training_formation_custom_pages
             WHERE tenant_id = ? AND status IN ('draft','review') AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
             ORDER BY updated_at ASC LIMIT " . max(1, min(50, $limit))
        );
        $stmt->execute([$tenantId, $days]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Documents publiés jamais consultés. @return list<array<string,mixed>> */
    public function listNeverViewed(int $tenantId, int $limit = 10): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, title, status, updated_at FROM training_formation_custom_pages
             WHERE tenant_id = ? AND is_published = 1 AND view_count = 0
             ORDER BY updated_at ASC LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
