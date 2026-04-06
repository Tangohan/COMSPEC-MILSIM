<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingCourseRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function listForTenant(
        int $tenantId,
        ?string $visibility = 'published',
        ?string $category = null,
        ?string $search = null
    ): array {
        $sql = 'SELECT * FROM training_courses WHERE tenant_id = ?';
        $params = [$tenantId];
        if ($visibility !== null) {
            $sql .= ' AND visibility = ?';
            $params[] = $visibility;
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
     * Formations publiées pour le carrousel dashboard (ordre vitrine, puis date de cycle, puis titre).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublishedForDashboard(int $tenantId, int $limit = 20): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            'SELECT * FROM training_courses WHERE tenant_id = ? AND visibility = ? '
            . 'ORDER BY (showcase_sort_order IS NULL) ASC, showcase_sort_order ASC, (showcase_cycle_date IS NULL) ASC, showcase_cycle_date ASC, title ASC '
            . 'LIMIT ' . $limit
        );
        $stmt->execute([$tenantId, 'published']);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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

    public function findBySlug(string $slug, int $tenantId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_courses WHERE tenant_id = ? AND slug = ? LIMIT 1');
        $stmt->execute([$tenantId, $slug]);
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
        $sql = 'SELECT 1 FROM training_courses WHERE tenant_id = ? AND slug = ?';
        $params = [$tenantId, $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = $this->pdo->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        return (bool) $stmt->fetch();
    }

    public function create(int $tenantId, array $data): int
    {
        $uuid = $data['uuid'] ?? $this->generateUuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_courses (tenant_id, uuid, title, slug, course_code, short_description, description, learning_objectives, theme_json, thumbnail_path, banner_path, category, level, language_code, estimated_minutes, passing_score, is_mandatory, is_certifying, validity_days, visibility, created_by, updated_by, lms_created_with_version, lms_last_saved_with_version)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
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
        $allowed = ['title', 'slug', 'course_code', 'short_description', 'description', 'learning_objectives', 'theme_json', 'enrollment_policy_json', 'enrollment_share_code', 'instruction_audio_url', 'instruction_audio_instructor_optional', 'instruction_audio_notes', 'thumbnail_path', 'banner_path', 'showcase_cycle_date', 'showcase_location', 'showcase_badge', 'showcase_card_style', 'showcase_sort_order', 'category', 'level', 'language_code', 'estimated_minutes', 'passing_score', 'is_mandatory', 'is_certifying', 'validity_days', 'visibility', 'updated_by', 'lms_created_with_version', 'lms_last_saved_with_version'];
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
