<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use PDO;

/**
 * Synchronise le catalogue militaire vers `roles` (intra) et `personnel_job_roles` (+ catégories).
 * Idempotent : met à jour les slugs catalogue ; n’efface pas les rôles personnalisés hors catalogue.
 */
final class MilitaryRoleCatalogSyncService
{
    public static function syncAllTenants(PDO $pdo): void
    {
        $st = $pdo->query('SELECT id FROM tenants');
        if (!$st) {
            return;
        }
        while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
            self::syncForTenant($pdo, (int) ($row['id'] ?? 0));
        }
    }

    public static function syncForTenant(PDO $pdo, int $tenantId): void
    {
        if ($tenantId <= 0 || !self::hasTable($pdo, 'roles')) {
            return;
        }

        $visualsPath = dirname(__DIR__, 3) . '/config/role_catalog_visuals.php';
        /** @var array{category_icons?: array<string, string>, tier_badge_classes?: array<string, string>} $visuals */
        $visuals = is_file($visualsPath) ? (require $visualsPath) : [];
        $categoryIcons = $visuals['category_icons'] ?? [];
        $tierClasses = $visuals['tier_badge_classes'] ?? [];

        $hasPjr = self::hasTable($pdo, 'personnel_job_roles') && self::hasTable($pdo, 'personnel_job_role_categories');
        $hasLabelEnPjr = $hasPjr && self::hasColumn($pdo, 'personnel_job_roles', 'label_en');
        $hasMosCodePjr = $hasPjr && self::hasColumn($pdo, 'personnel_job_roles', 'mos_code');
        $hasMosTitlePjr = $hasPjr && self::hasColumn($pdo, 'personnel_job_roles', 'mos_specialty_title');
        $hasCategory = self::hasColumn($pdo, 'roles', 'category');
        $hasSubcategory = self::hasColumn($pdo, 'roles', 'subcategory');
        $hasLabelEnRole = self::hasColumn($pdo, 'roles', 'label_en');
        $hasSemantic = self::hasColumn($pdo, 'roles', 'semantic_tier');
        $hasVisualOnly = self::hasColumn($pdo, 'roles', 'is_visual_only');
        $hasDispP = self::hasColumn($pdo, 'roles', 'display_priority');
        $hasDispW = self::hasColumn($pdo, 'roles', 'display_weight');
        $hasDispG = self::hasColumn($pdo, 'roles', 'display_group');
        $hasBadge = self::hasColumn($pdo, 'roles', 'badge_style');

        /** @var array<string, int> $categoryLeafIds */
        $categoryLeafIds = [];
        if ($hasPjr) {
            foreach (MilitaryOperationalRoleCatalog::entries() as $e) {
                $ck = self::categoryKeyFromLabel($e['category']) . '|' . self::categoryKeyFromLabel($e['subcategory']);
                if (!isset($categoryLeafIds[$ck])) {
                    $categoryLeafIds[$ck] = self::ensurePersonnelCategoryPair(
                        $pdo,
                        $tenantId,
                        $e['category'],
                        $e['subcategory']
                    );
                }
            }
        }

        $catalogSlugs = MilitaryOperationalRoleCatalog::catalogSlugSet();

        foreach (MilitaryOperationalRoleCatalog::entries() as $entry) {
            $slug = $entry['slug'];
            $ck = self::categoryKeyFromLabel($entry['category']);
            $icon = $categoryIcons[$ck] ?? 'heroicon-o-identification';
            $tier = $entry['semantic_tier'];
            $tierClass = $tierClasses[$tier] ?? 'bg-slate-100 text-slate-800 ring-slate-200';
            $badgeStyle = null;
            if ($hasBadge) {
                try {
                    $badgeStyle = json_encode(['icon' => $icon, 'tierClass' => $tierClass], JSON_THROW_ON_ERROR);
                } catch (\Throwable $_) {
                    $badgeStyle = null;
                }
            }

            $sel = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
            $sel->execute([$tenantId, $slug]);
            $existingId = $sel->fetchColumn();
            $isInsert = !$existingId;
            $roleId = $isInsert ? null : (int) $existingId;

            if ($isInsert) {
                $fields = ['tenant_id', 'name', 'slug', 'description', 'is_system', 'is_locked', 'role_layer', 'created_at'];
                $holders = ['?', '?', '?', '?', '1', '0', "'intra'", 'NOW()'];
                $params = [
                    $tenantId,
                    $entry['name'],
                    $slug,
                    $entry['description'],
                ];
                if ($hasCategory) {
                    $fields[] = 'category';
                    $holders[] = '?';
                    $params[] = $entry['category'];
                }
                if ($hasSubcategory) {
                    $fields[] = 'subcategory';
                    $holders[] = '?';
                    $params[] = $entry['subcategory'];
                }
                if ($hasLabelEnRole) {
                    $fields[] = 'label_en';
                    $holders[] = '?';
                    $params[] = $entry['label_en'];
                }
                if ($hasSemantic) {
                    $fields[] = 'semantic_tier';
                    $holders[] = '?';
                    $params[] = $tier;
                }
                if ($hasVisualOnly) {
                    $fields[] = 'is_visual_only';
                    $holders[] = '?';
                    $params[] = (int) $entry['is_visual_only'];
                }
                if ($hasDispG) {
                    $fields[] = 'display_group';
                    $holders[] = '?';
                    $params[] = (int) $entry['display_group'];
                }
                if ($hasDispW) {
                    $fields[] = 'display_weight';
                    $holders[] = '?';
                    $params[] = (int) $entry['display_weight'];
                }
                if ($hasDispP) {
                    $fields[] = 'display_priority';
                    $holders[] = '?';
                    $params[] = (int) $entry['display_priority'];
                }
                if ($hasBadge && $badgeStyle !== null) {
                    $fields[] = 'badge_style';
                    $holders[] = '?';
                    $params[] = $badgeStyle;
                }
                $sql = 'INSERT INTO roles (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $holders) . ')';
                $pdo->prepare($sql)->execute($params);
                $roleId = (int) $pdo->lastInsertId();
            } else {
                $sets = ['name = ?', 'description = ?', 'is_system = 1'];
                $upParams = [$entry['name'], $entry['description']];
                if ($hasCategory) {
                    $sets[] = 'category = ?';
                    $upParams[] = $entry['category'];
                }
                if ($hasSubcategory) {
                    $sets[] = 'subcategory = ?';
                    $upParams[] = $entry['subcategory'];
                }
                if ($hasLabelEnRole) {
                    $sets[] = 'label_en = ?';
                    $upParams[] = $entry['label_en'];
                }
                if ($hasSemantic) {
                    $sets[] = 'semantic_tier = ?';
                    $upParams[] = $tier;
                }
                if ($hasVisualOnly) {
                    $sets[] = 'is_visual_only = ?';
                    $upParams[] = (int) $entry['is_visual_only'];
                }
                if ($hasDispG) {
                    $sets[] = 'display_group = ?';
                    $upParams[] = (int) $entry['display_group'];
                }
                if ($hasDispW) {
                    $sets[] = 'display_weight = ?';
                    $upParams[] = (int) $entry['display_weight'];
                }
                if ($hasDispP) {
                    $sets[] = 'display_priority = ?';
                    $upParams[] = (int) $entry['display_priority'];
                }
                if ($hasBadge && $badgeStyle !== null) {
                    $sets[] = 'badge_style = ?';
                    $upParams[] = $badgeStyle;
                }
                $upParams[] = $tenantId;
                $upParams[] = $slug;
                $sql = 'UPDATE roles SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND slug = ?';
                $pdo->prepare($sql)->execute($upParams);
            }

            if ($roleId > 0 && (int) ($entry['is_visual_only'] ?? 0) === 0) {
                // INSERT IGNORE : complète les rôles catalogue encore vides (ex. créés par migration organique sans droits).
                if ($isInsert || self::rolePermissionCount($pdo, $roleId) === 0) {
                    self::copyPermissionsFromBaseline($pdo, $tenantId, $roleId, $entry['permission_baseline']);
                }
            }

            if ($hasPjr && $roleId) {
                $pairKey = self::categoryKeyFromLabel($entry['category']) . '|' . self::categoryKeyFromLabel($entry['subcategory']);
                $leafId = $categoryLeafIds[$pairKey] ?? 0;
                if ($leafId > 0) {
                    $mosCode = isset($entry['mos_code']) ? trim((string) $entry['mos_code']) : '';
                    $mosCode = $mosCode !== '' ? $mosCode : null;
                    $mosTitle = isset($entry['mos_specialty_title']) ? trim((string) $entry['mos_specialty_title']) : '';
                    $mosTitle = $mosTitle !== '' ? $mosTitle : null;
                    self::upsertPersonnelJobRole(
                        $pdo,
                        $tenantId,
                        $leafId,
                        $slug,
                        $entry['name'],
                        $entry['description'],
                        $hasLabelEnPjr ? $entry['label_en'] : null,
                        (int) $entry['display_weight'],
                        $hasLabelEnPjr,
                        $mosCode,
                        $mosTitle,
                        $hasMosCodePjr,
                        $hasMosTitlePjr
                    );
                }
            }
        }

        // Harmonise role_layer intra pour les slugs catalogue (si un rôle existait en autre couche — rare)
        $ph = implode(',', array_fill(0, count($catalogSlugs), '?'));
        $params = array_merge([$tenantId], array_keys($catalogSlugs));
        try {
            $pdo->prepare("UPDATE roles SET role_layer = 'intra' WHERE tenant_id = ? AND slug IN ({$ph})")->execute($params);
        } catch (\Throwable $_) {
        }
    }

    public static function categoryKeyFromLabel(string $label): string
    {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if ($t === false) {
            $t = $label;
        }
        $t = strtolower((string) $t);
        $t = preg_replace('/[^a-z0-9]+/', '-', $t);

        return trim((string) $t, '-') ?: 'cat';
    }

    private static function ensurePersonnelCategoryPair(PDO $pdo, int $tenantId, string $categoryName, string $subName): int
    {
        $rootSlug = self::categoryKeyFromLabel($categoryName);
        $childSlug = $rootSlug . '-' . self::categoryKeyFromLabel($subName);

        $q = $pdo->prepare('SELECT id FROM personnel_job_role_categories WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $q->execute([$tenantId, $rootSlug]);
        $rootId = (int) ($q->fetchColumn() ?: 0);
        if ($rootId <= 0) {
            $ord = self::nextCategorySortOrder($pdo, $tenantId, null);
            $pdo->prepare(
                'INSERT INTO personnel_job_role_categories (tenant_id, parent_id, name, slug, sort_order) VALUES (?, NULL, ?, ?, ?)'
            )->execute([$tenantId, $categoryName, $rootSlug, $ord]);
            $rootId = (int) $pdo->lastInsertId();
        }

        $q->execute([$tenantId, $childSlug]);
        $childId = (int) ($q->fetchColumn() ?: 0);
        if ($childId <= 0) {
            $ord = self::nextCategorySortOrder($pdo, $tenantId, $rootId);
            $pdo->prepare(
                'INSERT INTO personnel_job_role_categories (tenant_id, parent_id, name, slug, sort_order) VALUES (?, ?, ?, ?, ?)'
            )->execute([$tenantId, $rootId, $subName, $childSlug, $ord]);
            $childId = (int) $pdo->lastInsertId();
        }

        return $childId;
    }

    private static function nextCategorySortOrder(PDO $pdo, int $tenantId, ?int $parentId): int
    {
        if ($parentId === null) {
            $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM personnel_job_role_categories WHERE tenant_id = ? AND parent_id IS NULL');
            $st->execute([$tenantId]);
        } else {
            $st = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM personnel_job_role_categories WHERE tenant_id = ? AND parent_id = ?');
            $st->execute([$tenantId, $parentId]);
        }

        return (int) $st->fetchColumn();
    }

    /**
     * @param 'member'|'officer'|'instructor'|'medic'|'logistics'|'hr'|'rto'|'probation' $baselineSlug
     */
    private static function copyPermissionsFromBaseline(PDO $pdo, int $tenantId, int $newRoleId, string $baselineSlug): void
    {
        $src = $pdo->prepare('SELECT id FROM roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $src->execute([$tenantId, $baselineSlug]);
        $srcId = (int) ($src->fetchColumn() ?: 0);
        if ($srcId <= 0 || $srcId === $newRoleId) {
            return;
        }
        $permSt = $pdo->prepare('SELECT permission_id FROM role_permissions WHERE role_id = ?');
        $permSt->execute([$srcId]);
        $link = $pdo->prepare('INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)');
        while ($row = $permSt->fetch(PDO::FETCH_ASSOC)) {
            $pid = (int) ($row['permission_id'] ?? 0);
            if ($pid > 0) {
                $link->execute([$newRoleId, $pid]);
            }
        }
    }

    private static function rolePermissionCount(PDO $pdo, int $roleId): int
    {
        $st = $pdo->prepare('SELECT COUNT(*) FROM role_permissions WHERE role_id = ?');
        $st->execute([$roleId]);

        return (int) $st->fetchColumn();
    }

    private static function upsertPersonnelJobRole(
        PDO $pdo,
        int $tenantId,
        int $categoryLeafId,
        string $slug,
        string $name,
        string $description,
        ?string $labelEn,
        int $sortOrder,
        bool $hasLabelEnColumn,
        ?string $mosCode,
        ?string $mosSpecialtyTitle,
        bool $hasMosCodeColumn,
        bool $hasMosTitleColumn
    ): void {
        $chk = $pdo->prepare('SELECT id FROM personnel_job_roles WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $chk->execute([$tenantId, $slug]);
        $existing = $chk->fetchColumn();
        if ($existing) {
            $sets = ['category_id = ?', 'name = ?', 'description = ?', 'sort_order = ?', 'is_system = 1'];
            $params = [$categoryLeafId, $name, $description, $sortOrder];
            if ($hasLabelEnColumn) {
                $sets[] = 'label_en = ?';
                $params[] = $labelEn;
            }
            if ($hasMosCodeColumn) {
                $sets[] = 'mos_code = ?';
                $params[] = $mosCode;
            }
            if ($hasMosTitleColumn) {
                $sets[] = 'mos_specialty_title = ?';
                $params[] = $mosSpecialtyTitle;
            }
            $params[] = $tenantId;
            $params[] = $slug;
            $sql = 'UPDATE personnel_job_roles SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND slug = ?';
            $pdo->prepare($sql)->execute($params);

            return;
        }
        $cols = ['tenant_id', 'category_id', 'name', 'slug', 'description'];
        $holders = ['?', '?', '?', '?', '?'];
        $insParams = [$tenantId, $categoryLeafId, $name, $slug, $description];
        if ($hasLabelEnColumn) {
            $cols[] = 'label_en';
            $holders[] = '?';
            $insParams[] = $labelEn;
        }
        if ($hasMosCodeColumn) {
            $cols[] = 'mos_code';
            $holders[] = '?';
            $insParams[] = $mosCode;
        }
        if ($hasMosTitleColumn) {
            $cols[] = 'mos_specialty_title';
            $holders[] = '?';
            $insParams[] = $mosSpecialtyTitle;
        }
        $cols[] = 'sort_order';
        $holders[] = '?';
        $insParams[] = $sortOrder;
        $cols[] = 'is_system';
        $holders[] = '1';
        $sql = 'INSERT INTO personnel_job_roles (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $holders) . ')';
        $pdo->prepare($sql)->execute($insParams);
    }

    private static function hasTable(PDO $pdo, string $table): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $st->execute([$table]);

        return (bool) $st->fetchColumn();
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        $st = $pdo->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $st->execute([$table, $column]);

        return (bool) $st->fetchColumn();
    }
}
