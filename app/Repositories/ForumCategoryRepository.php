<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class ForumCategoryRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(int $tenantId): array
    {
        return $this->dedupeRootsBySlug($this->fetchRootCategoriesForTenant($tenantId), $tenantId);
    }

    /**
     * Racines visibles pour un tenant (locales + globales), sans déduplication.
     * Après migration « canaux publics globaux », plusieurs tenants ont pu laisser
     * des racines scope=global / tenant_id NULL avec le même slug (MySQL autorise
     * plusieurs NULL dans UNIQUE(tenant_id, slug)) — d’où des doublons en UI.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchRootCategoriesForTenant(int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fc.*,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS topic_count,
                    (SELECT COUNT(*) FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS post_count,
                    (SELECT MAX(fp.created_at) FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id) AS last_post_at,
                    (SELECT fp.user_id FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id ORDER BY fp.created_at DESC LIMIT 1) AS last_post_user_id
             FROM forum_categories fc
             WHERE (fc.tenant_id = ? OR (COALESCE(fc.scope, \'tenant\') = \'global\' AND fc.tenant_id IS NULL))
               AND fc.parent_id IS NULL
             ORDER BY fc.display_order ASC, fc.id ASC'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Une entrée par slug : préfère la racine du tenant courant, sinon la plus active, sinon le plus petit id.
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function dedupeRootsBySlug(array $rows, int $tenantId): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            $key = $slug !== '' ? 's:' . $slug : 'i:' . (int) ($row['id'] ?? 0);
            $groups[$key][] = $row;
        }
        $out = [];
        foreach ($groups as $group) {
            $out[] = $this->preferRootCandidate($group, $tenantId);
        }
        usort(
            $out,
            static function (array $a, array $b): int {
                $ord = ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));

                return $ord !== 0 ? $ord : (((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
            }
        );

        return $out;
    }

    /**
     * @param non-empty-list<array<string, mixed>> $group
     * @return array<string, mixed>
     */
    private function preferRootCandidate(array $group, int $tenantId): array
    {
        usort(
            $group,
            static function (array $a, array $b) use ($tenantId): int {
                $aLocal = isset($a['tenant_id']) && (int) $a['tenant_id'] === $tenantId ? 0 : 1;
                $bLocal = isset($b['tenant_id']) && (int) $b['tenant_id'] === $tenantId ? 0 : 1;
                if ($aLocal !== $bLocal) {
                    return $aLocal <=> $bLocal;
                }
                $byTopics = ((int) ($b['topic_count'] ?? 0)) <=> ((int) ($a['topic_count'] ?? 0));
                if ($byTopics !== 0) {
                    return $byTopics;
                }

                return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
            }
        );

        return $group[0];
    }

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        return $this->findBySlugAny($slug, $tenantId);
    }

    /**
     * Trouve une catégorie par slug (racine ou sous-catégorie).
     */
    public function findBySlugAny(string $slug, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT fc.*,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS topic_count,
                    (SELECT COUNT(*) FROM forum_posts fp INNER JOIN forum_topics ft ON ft.id = fp.topic_id WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS post_count
             FROM forum_categories fc
             WHERE (fc.tenant_id = ? OR (COALESCE(fc.scope, \'tenant\') = \'global\' AND fc.tenant_id IS NULL))
               AND fc.slug = ?
             ORDER BY (fc.tenant_id = ?) DESC, fc.id ASC
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM forum_categories
             WHERE id = ?
               AND (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))
             LIMIT 1"
        );
        $stmt->execute([$id, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Racine forum (`parent_id` NULL) par slug exact. */
    public function findRootBySlug(int $tenantId, string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM forum_categories
             WHERE (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))
               AND parent_id IS NULL AND slug = ?
             ORDER BY (tenant_id = ?) DESC, id ASC
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $slug, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Première racine « grand public » : de préférence slug general, sinon première racine hors organisation / modération / plateforme.
     */
    public function findPreferredGeneralRoot(int $tenantId): ?array
    {
        $roots = $this->listForTenant($tenantId);
        $fallback = null;
        foreach ($roots as $r) {
            $scope = (string) ($r['scope'] ?? 'global');
            if (in_array($scope, ['organization', 'tenant', 'moderation', 'platform'], true)) {
                continue;
            }
            if (($r['slug'] ?? '') === 'general') {
                return $r;
            }
            if ($fallback === null) {
                $fallback = $r;
            }
        }

        return $fallback;
    }

    /** Racine section organisation (forum v2), sans parent. */
    public function findOrganizationRoot(int $tenantId): ?array
    {
        if (!$this->hasScopeColumn()) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM forum_categories WHERE tenant_id = ? AND parent_id IS NULL AND scope IN ('organization','tenant') ORDER BY (slug LIKE 'org-%') DESC, display_order ASC, id ASC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findChildByParentAndSlug(int $tenantId, int $parentId, string $slug): ?array
    {
        if ($slug === '' || $parentId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM forum_categories
             WHERE (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))
               AND parent_id = ? AND slug = ? LIMIT 1"
        );
        $stmt->execute([$tenantId, $parentId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function getSubcategories(int $parentId, int $tenantId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT fc.*,
                    (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS topic_count
             FROM forum_categories fc
             WHERE (fc.tenant_id = ? OR (COALESCE(fc.scope, 'tenant') = 'global' AND fc.tenant_id IS NULL))
               AND fc.parent_id = ?
             ORDER BY fc.display_order ASC"
        );
        $stmt->execute([$tenantId, $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Liste des catégories racine avec sous-catégories (pour dropdown new-topic / rail).
     * Fusionne les sous-salons des racines globales dupliquées (même slug).
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenantWithChildren(int $tenantId): array
    {
        $roots = $this->fetchRootCategoriesForTenant($tenantId);
        $groups = [];
        foreach ($roots as $root) {
            $slug = trim((string) ($root['slug'] ?? ''));
            $key = $slug !== '' ? 's:' . $slug : 'i:' . (int) ($root['id'] ?? 0);
            $groups[$key][] = $root;
        }

        $out = [];
        foreach ($groups as $group) {
            $chosen = $this->preferRootCandidate($group, $tenantId);
            $childrenByKey = [];
            foreach ($group as $r) {
                foreach ($this->getSubcategories((int) ($r['id'] ?? 0), $tenantId) as $ch) {
                    $cs = trim((string) ($ch['slug'] ?? ''));
                    $ck = $cs !== '' ? 's:' . $cs : 'i:' . (int) ($ch['id'] ?? 0);
                    if (!isset($childrenByKey[$ck])) {
                        $childrenByKey[$ck] = $ch;
                        continue;
                    }
                    if ((int) ($ch['topic_count'] ?? 0) > (int) ($childrenByKey[$ck]['topic_count'] ?? 0)) {
                        $childrenByKey[$ck] = $ch;
                    }
                }
            }
            $kids = array_values($childrenByKey);
            usort(
                $kids,
                static function (array $a, array $b): int {
                    $ord = ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));

                    return $ord !== 0 ? $ord : (((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
                }
            );
            $chosen['children'] = $kids;
            $out[] = $chosen;
        }

        usort(
            $out,
            static function (array $a, array $b): int {
                $ord = ((int) ($a['display_order'] ?? 0)) <=> ((int) ($b['display_order'] ?? 0));

                return $ord !== 0 ? $ord : (((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));
            }
        );

        return $out;
    }

    public function isSubscribedCategory(int $userId, int $categoryId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM forum_category_subscriptions WHERE user_id = ? AND category_id = ? LIMIT 1');
        $stmt->execute([$userId, $categoryId]);
        return (bool) $stmt->fetchColumn();
    }

    public function subscribeCategory(int $userId, int $categoryId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO forum_category_subscriptions (user_id, category_id, created_at) VALUES (?, ?, NOW())');
        $stmt->execute([$userId, $categoryId]);
    }

    public function unsubscribeCategory(int $userId, int $categoryId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM forum_category_subscriptions WHERE user_id = ? AND category_id = ?');
        $stmt->execute([$userId, $categoryId]);
    }

    /**
     * Salons (catégories) suivis par l’utilisateur pour ce tenant — dernier sujet + compteur non-lu,
     * pour le widget « Mes salons suivis » du tableau de bord.
     *
     * @return list<array<string, mixed>>
     */
    public function listSubscribedForUser(int $userId, int $tenantId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT fc.id, fc.name, fc.slug, fc.icon, fc.color_theme,
                        (SELECT ft.id FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0
                            ORDER BY COALESCE(ft.updated_at, ft.created_at) DESC LIMIT 1) AS last_topic_id,
                        (SELECT ft.title FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0
                            ORDER BY COALESCE(ft.updated_at, ft.created_at) DESC LIMIT 1) AS last_topic_title,
                        (SELECT MAX(COALESCE(ft.updated_at, ft.created_at)) FROM forum_topics ft
                            WHERE ft.category_id = fc.id AND ft.is_hidden = 0) AS last_activity_at,
                        (SELECT COUNT(*) FROM forum_topics ft WHERE ft.category_id = fc.id AND ft.is_hidden = 0
                            AND NOT EXISTS (SELECT 1 FROM forum_read fr WHERE fr.user_id = ? AND fr.topic_id = ft.id)) AS unread_count
                 FROM forum_category_subscriptions fcs
                 INNER JOIN forum_categories fc ON fc.id = fcs.category_id
                 WHERE fcs.user_id = ?
                   AND (fc.tenant_id = ? OR (COALESCE(fc.scope, 'tenant') = 'global' AND fc.tenant_id IS NULL))
                 ORDER BY last_activity_at DESC, fc.display_order ASC"
            );
            $stmt->execute([$userId, $userId, $tenantId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            if ($e->getCode() === '42S02' || str_contains($e->getMessage(), "doesn't exist")) {
                return [];
            }
            throw $e;
        }
    }

    public function countChildren(int $categoryId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM forum_categories
             WHERE (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))
               AND parent_id = ?"
        );
        $stmt->execute([$tenantId, $categoryId]);

        return (int) $stmt->fetchColumn();
    }

    public function countTopicsInCategory(int $categoryId, int $tenantId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM forum_topics
             WHERE category_id = ?
               AND (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))"
        );
        $stmt->execute([$categoryId, $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    private function hasScopeColumn(): bool
    {
        static $v = null;
        if ($v === null) {
            $s = $this->pdo->query("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'forum_categories' AND COLUMN_NAME = 'scope' LIMIT 1");
            $v = $s && (bool) $s->fetchColumn();
        }

        return $v;
    }

    public function create(int $tenantId, array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        }
        $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : 0;
        $parentId = $parentId > 0 ? $parentId : null;

        $scope = isset($data['scope']) ? trim((string) $data['scope']) : 'global';
        $scope = match ($scope) {
            'general', 'platform' => 'global',
            'organization' => 'tenant',
            default => $scope,
        };
        if (!in_array($scope, ['tenant', 'global', 'mission', 'moderation'], true)) {
            $scope = 'global';
        }

        $ownerTenantId = null;
        if ($parentId !== null) {
            $parent = $this->findById($parentId, $tenantId);
            if (!$parent || !empty($parent['parent_id'])) {
                throw new \InvalidArgumentException('parent_id doit référencer une catégorie racine.');
            }
            $scope = (string) ($parent['scope'] ?? $scope);
            if ($this->hasScopeColumn() === false) {
                $scope = 'general';
            }
        } else {
            if ($scope === 'tenant') {
                $ownerTenantId = isset($data['owner_tenant_id']) ? (int) $data['owner_tenant_id'] : $tenantId;
            }
        }
        $tenantFk = $scope === 'global' ? null : $tenantId;

        if ($this->hasScopeColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forum_categories (tenant_id, parent_id, scope, owner_tenant_id, name, slug, description, icon, color_theme, display_order, is_locked, min_role_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $tenantFk,
                $parentId,
                $scope,
                $ownerTenantId,
                $name,
                $slug,
                $data['description'] ?? null,
                $data['icon'] ?? null,
                $data['color_theme'] ?? 'slate',
                (int) ($data['display_order'] ?? 0),
                isset($data['min_role_id']) ? (int) $data['min_role_id'] : null,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO forum_categories (tenant_id, parent_id, name, slug, description, icon, color_theme, display_order, is_locked, min_role_id, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())'
            );
            $stmt->execute([
                $tenantFk ?? $tenantId,
                $parentId,
                $name,
                $slug,
                $data['description'] ?? null,
                $data['icon'] ?? null,
                $data['color_theme'] ?? 'slate',
                (int) ($data['display_order'] ?? 0),
                isset($data['min_role_id']) ? (int) $data['min_role_id'] : null,
            ]);
        }

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $tenantId, array $data): bool
    {
        $cat = $this->findById($id, $tenantId);
        if (!$cat) {
            return false;
        }
        $name = $data['name'] ?? $cat['name'];
        $slug = $data['slug'] ?? $cat['slug'] ?? $this->slugify((string) $name);

        if ($this->hasScopeColumn()) {
            $scope = isset($data['scope']) ? trim((string) $data['scope']) : (string) ($cat['scope'] ?? 'global');
            $scope = match ($scope) {
                'general', 'platform' => 'global',
                'organization' => 'tenant',
                default => $scope,
            };
            if (!in_array($scope, ['tenant', 'global', 'mission', 'moderation'], true)) {
                $scope = 'global';
            }
            $parentId = array_key_exists('parent_id', $data)
                ? ((int) $data['parent_id'] > 0 ? (int) $data['parent_id'] : null)
                : (isset($cat['parent_id']) && $cat['parent_id'] !== null && $cat['parent_id'] !== '' ? (int) $cat['parent_id'] : null);
            if ($parentId !== null) {
                if ($parentId === $id) {
                    return false;
                }
                $parent = $this->findById($parentId, $tenantId);
                if (!$parent || !empty($parent['parent_id'])) {
                    return false;
                }
                $scope = (string) ($parent['scope'] ?? $scope);
            }
            $ownerTenantId = $cat['owner_tenant_id'] ?? null;
            if ($parentId === null && $scope === 'tenant') {
                $ownerTenantId = isset($data['owner_tenant_id']) ? (int) $data['owner_tenant_id'] : $ownerTenantId;
            }
            if ($parentId !== null) {
                $ownerTenantId = null;
            }
            $tenantFk = $scope === 'global' ? null : $tenantId;
            $stmt = $this->pdo->prepare(
                'UPDATE forum_categories SET tenant_id = ?, parent_id = ?, scope = ?, owner_tenant_id = ?, name = ?, slug = ?, description = ?, icon = ?, color_theme = ?, display_order = ?, updated_at = NOW()
                 WHERE id = ? AND (tenant_id = ? OR (COALESCE(scope, \'tenant\') = \'global\' AND tenant_id IS NULL))'
            );
            $stmt->execute([
                $tenantFk,
                $parentId,
                $scope,
                $ownerTenantId,
                $name,
                $slug,
                $data['description'] ?? $cat['description'],
                $data['icon'] ?? $cat['icon'],
                $data['color_theme'] ?? $cat['color_theme'] ?? 'slate',
                isset($data['display_order']) ? (int) $data['display_order'] : (int) $cat['display_order'],
                $id,
                $tenantId,
            ]);

            return true;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE forum_categories SET name = ?, slug = ?, description = ?, icon = ?, color_theme = ?, display_order = ?, updated_at = NOW()
             WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            $name,
            $slug,
            $data['description'] ?? $cat['description'],
            $data['icon'] ?? $cat['icon'],
            $data['color_theme'] ?? $cat['color_theme'] ?? 'slate',
            isset($data['display_order']) ? (int) $data['display_order'] : (int) $cat['display_order'],
            $id,
            $tenantId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare(
            "DELETE FROM forum_categories
             WHERE id = ?
               AND (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))"
        );
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    public function setLocked(int $id, int $tenantId, bool $locked): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE forum_categories
             SET is_locked = ?, updated_at = NOW()
             WHERE id = ?
               AND (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))"
        );
        $stmt->execute([$locked ? 1 : 0, $id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Réordonner les catégories racine par liste d'ids (ordre = index).
     */
    public function reorder(int $tenantId, array $idOrder): void
    {
        foreach ($idOrder as $order => $id) {
            $stmt = $this->pdo->prepare(
                "UPDATE forum_categories SET display_order = ?, updated_at = NOW()
                 WHERE id = ?
                   AND (tenant_id = ? OR (COALESCE(scope, 'tenant') = 'global' AND tenant_id IS NULL))
                   AND parent_id IS NULL"
            );
            $stmt->execute([$order, (int) $id, $tenantId]);
        }
    }

    private function slugify(string $s): string
    {
        $s = preg_replace('/[^a-z0-9]+/i', '-', trim($s));
        return strtolower(trim($s, '-')) ?: 'categorie';
    }
}
