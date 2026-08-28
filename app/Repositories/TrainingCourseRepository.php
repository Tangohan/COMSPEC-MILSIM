<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingCourseRepository
{
    public const LMS_SCOPE_TENANT = 'tenant';

    public const LMS_SCOPE_PLATFORM = 'platform';

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** Cache : colonne du sélecteur de lecteur présente (déploiement migré ou non). */
    private ?bool $lessonPlayerModeColumn = null;

    /**
     * La bascule de lecteur de leçon est-elle disponible ?
     * Sur un déploiement non migré, l'UI masque le réglage et l'écriture est ignorée.
     */
    public function hasLessonPlayerModeColumn(): bool
    {
        if ($this->lessonPlayerModeColumn !== null) {
            return $this->lessonPlayerModeColumn;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_courses'
                   AND COLUMN_NAME = 'lesson_player_mode' LIMIT 1"
            );
            $st->execute();
            $this->lessonPlayerModeColumn = (bool) $st->fetchColumn();
        } catch (\Throwable) {
            $this->lessonPlayerModeColumn = false;
        }

        return $this->lessonPlayerModeColumn;
    }

    /** @return list<string> valeurs distinctes de category pour autocomplétion, sans catalogue dédié. */
    public function listDistinctCategoriesForTenant(int $tenantId): array
    {
        $st = $this->pdo->prepare(
            "SELECT DISTINCT category FROM training_courses WHERE tenant_id = ? AND category IS NOT NULL AND category != '' ORDER BY category ASC"
        );
        $st->execute([$tenantId]);

        return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @param bool $tenantCatalogOnly si vrai avec visibility published : exclut les parcours lms_scope=platform (réservés au catalogue fusionné)
     */
    public function listForTenant(
        int $tenantId,
        ?string $visibility = 'published',
        ?string $category = null,
        ?string $search = null,
        bool $tenantCatalogOnly = false
    ): array {
        $sql = 'SELECT * FROM training_courses WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($visibility !== null) {
            $sql .= ' AND visibility = ?';
            $params[] = $visibility;
        }
        if ($tenantCatalogOnly) {
            $sql .= " AND COALESCE(lms_scope, 'tenant') = 'tenant'";
        }
        if ($category !== null && $category !== '') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        if ($search !== null && $search !== '') {
            $sql .= ' AND (title LIKE ? OR short_description LIKE ?)';
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
        }
        $sql .= ' ORDER BY title ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Formations publiées « plateforme Athena » (tous tenants).
     *
     * @return list<array<string, mixed>>
     */
    public function listPublishedPlatform(?string $category = null, ?string $search = null): array
    {
        $sql = "SELECT * FROM training_courses WHERE lms_scope = 'platform' AND visibility = 'published'";
        $params = [];
        if ($category !== null && $category !== '') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        if ($search !== null && $search !== '') {
            $sql .= ' AND (title LIKE ? OR short_description LIKE ?)';
            $term = '%' . $search . '%';
            $params[] = $term;
            $params[] = $term;
        }
        $sql .= ' ORDER BY title ASC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Un même slug ne doit apparaître qu’une fois : la copie de la communauté
     * masque le parcours « toute la plateforme ».
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public static function collapseDuplicateCatalogRows(array $rows, int $viewerTenantId): array
    {
        $bySlug = [];
        $order = [];
        foreach ($rows as $row) {
            $slug = strtolower(trim((string) ($row['slug'] ?? '')));
            if ($slug === '') {
                $slug = '__id:' . (int) ($row['id'] ?? 0);
            }
            if (!isset($bySlug[$slug])) {
                $bySlug[$slug] = $row;
                $order[] = $slug;
                continue;
            }
            if (self::catalogRowPreferredOver($row, $bySlug[$slug], $viewerTenantId)) {
                $bySlug[$slug] = $row;
            }
        }

        $out = [];
        foreach ($order as $slug) {
            $out[] = $bySlug[$slug];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $candidate
     * @param array<string, mixed> $current
     */
    private static function catalogRowPreferredOver(array $candidate, array $current, int $viewerTenantId): bool
    {
        $candMine = (int) ($candidate['tenant_id'] ?? 0) === $viewerTenantId;
        $currMine = (int) ($current['tenant_id'] ?? 0) === $viewerTenantId;
        if ($candMine !== $currMine) {
            return $candMine;
        }
        $candCommunity = (($candidate['lms_scope'] ?? self::LMS_SCOPE_TENANT) !== self::LMS_SCOPE_PLATFORM);
        $currCommunity = (($current['lms_scope'] ?? self::LMS_SCOPE_TENANT) !== self::LMS_SCOPE_PLATFORM);
        if ($candCommunity !== $currCommunity) {
            return $candCommunity;
        }

        return false;
    }

    /**
     * Formations publiées pour le carrousel dashboard (ordre vitrine, puis date de cycle, puis titre).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublishedForDashboard(int $tenantId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM training_courses c WHERE c.visibility = 'published' AND ("
            . '(c.tenant_id = ? AND COALESCE(c.lms_scope, \'tenant\') = \'tenant\')'
            . ' OR (c.lms_scope = \'platform\' AND NOT EXISTS ('
            . 'SELECT 1 FROM training_courses t WHERE t.visibility = \'published\''
            . ' AND t.tenant_id = ? AND COALESCE(t.lms_scope, \'tenant\') = \'tenant\' AND t.slug = c.slug'
            . '))'
            . ') ORDER BY (c.showcase_sort_order IS NULL) ASC, c.showcase_sort_order ASC, (c.showcase_cycle_date IS NULL) ASC, c.showcase_cycle_date ASC, c.title ASC '
            . 'LIMIT ' . ($limit * 2)
        );
        $stmt->execute([$tenantId, $tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_slice(self::collapseDuplicateCatalogRows($rows, $tenantId), 0, $limit);
    }

    public function findById(int $id, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM training_courses WHERE id = ?';
        $params = [$id];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findBySlug(string $slug, int $viewerTenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM training_courses WHERE tenant_id = ? AND slug = ? AND COALESCE(lms_scope, 'tenant') = 'tenant' AND visibility = 'published' LIMIT 1"
        );
        $stmt->execute([$viewerTenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        $stmt = $this->pdo->prepare(
            "SELECT * FROM training_courses WHERE slug = ? AND lms_scope = 'platform' AND visibility = 'published' LIMIT 1"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Accès lecteur : parcours de la communauté (tenant + publié ou brouillon pour l’éditeur) ou parcours plateforme publié.
     */
    public function findByIdForViewer(int $courseId, int $viewerTenantId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_courses WHERE id = ? AND (tenant_id = ? OR (lms_scope = \'platform\' AND visibility = \'published\')) LIMIT 1'
        );
        $stmt->execute([$courseId, $viewerTenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Code de partage (unique sur la plateforme, une formation par code). */
    public function findByShareCode(string $raw): ?array
    {
        $code = function_exists('training_lms_normalize_share_code') ? training_lms_normalize_share_code($raw) : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $raw) ?? '');
        if ($code === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM training_courses WHERE enrollment_share_code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function isEnrollmentShareCodeTaken(string $code, ?int $excludeCourseId = null): bool
    {
        $c = function_exists('training_lms_normalize_share_code') ? training_lms_normalize_share_code($code) : '';
        if ($c === '') {
            return true;
        }
        $sql = 'SELECT 1 FROM training_courses WHERE enrollment_share_code = ?';
        $params = [$c];
        if ($excludeCourseId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeCourseId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function findByUuid(string $uuid, ?int $tenantId = null): ?array
    {
        $sql = 'SELECT * FROM training_courses WHERE uuid = ?';
        $params = [$uuid];
        if ($tenantId !== null) {
            $sql .= ' AND tenant_id = ?';
            $params[] = $tenantId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function slugExists(int $tenantId, string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM training_courses WHERE tenant_id = ? AND slug = ? AND COALESCE(lms_scope, 'tenant') = 'tenant'";
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function platformSlugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1 FROM training_courses WHERE slug = ? AND lms_scope = 'platform'";
        $params = [$slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    /**
     * Parcours « catalogue communauté » : lignes du tenant hors catalogue plateforme (brouillons et publiés comptent).
     */
    public function countTenantCatalogCourses(int $tenantId): int
    {
        if ($tenantId < 1) {
            return 0;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM training_courses WHERE tenant_id = ? AND COALESCE(lms_scope, 'tenant') = 'tenant'"
        );
        $stmt->execute([$tenantId]);
        $n = $stmt->fetchColumn();

        return $n !== false ? (int) $n : 0;
    }

    public function create(int $tenantId, array $data): int
    {
        $uuid = $data['uuid'] ?? $this->generateUuid();
        $scope = $data['lms_scope'] ?? self::LMS_SCOPE_TENANT;
        if ($scope !== self::LMS_SCOPE_PLATFORM) {
            $scope = self::LMS_SCOPE_TENANT;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_courses (tenant_id, lms_scope, uuid, title, slug, course_code, short_description, description, learning_objectives, theme_json, thumbnail_path, banner_path, category, level, language_code, estimated_minutes, passing_score, is_mandatory, is_certifying, validity_days, visibility, created_by, updated_by, lms_created_with_version, lms_last_saved_with_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $scope,
            $uuid,
            $data['title'],
            $data['slug'],
            $data['course_code'] ?? null,
            $data['short_description'] ?? null,
            $data['description'] ?? null,
            $data['learning_objectives'] ?? null,
            $data['theme_json'] ?? null,
            $data['thumbnail_path'] ?? null,
            $data['banner_path'] ?? null,
            $data['category'] ?? null,
            $data['level'] ?? 'initiation',
            $data['language_code'] ?? 'fr',
            (int) ($data['estimated_minutes'] ?? 0),
            (float) ($data['passing_score'] ?? 80),
            (int) ($data['is_mandatory'] ?? 0),
            (int) ($data['is_certifying'] ?? 0),
            isset($data['validity_days']) ? (int) $data['validity_days'] : null,
            $data['visibility'] ?? 'draft',
            $data['created_by'],
            $data['updated_by'] ?? null,
            isset($data['lms_created_with_version']) ? (string) $data['lms_created_with_version'] : null,
            isset($data['lms_last_saved_with_version']) ? (string) $data['lms_last_saved_with_version'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];
        $allowed = ['title', 'slug', 'course_code', 'short_description', 'description', 'learning_objectives', 'theme_json', 'enrollment_policy_json', 'enrollment_share_code', 'instruction_audio_url', 'instruction_audio_instructor_optional', 'instruction_audio_notes', 'thumbnail_path', 'banner_path', 'showcase_cycle_date', 'showcase_location', 'showcase_badge', 'showcase_card_style', 'showcase_sort_order', 'category', 'level', 'language_code', 'estimated_minutes', 'passing_score', 'is_mandatory', 'is_certifying', 'validity_days', 'visibility', 'updated_by', 'lms_created_with_version', 'lms_last_saved_with_version', 'lms_scope', 'pedagogical_owner_user_id', 'final_validator_user_id', 'lesson_player_mode'];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $params[] = $data[$k];
            }
        }
        if ($fields === []) {
            return;
        }
        $params[] = $id;
        $stmt = $this->pdo->prepare('UPDATE training_courses SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    /** Suppression définitive (cascade modules, inscriptions, etc. selon schéma BDD). */
    public function deleteByIdForTenant(int $id, int $tenantId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_courses WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
