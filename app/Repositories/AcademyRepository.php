<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Services\Academy\AcademyCurriculum;
use App\Services\Academy\AcademyBuiltInCourses;
use PDO;

final class AcademyRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getPdo();
    }

    public function courses(int $tenantId, bool $drafts = false): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT course_key, content_json, published FROM academy_courses WHERE tenant_id = ?' . ($drafts ? '' : ' AND published = 1') . ' ORDER BY updated_at DESC');
        $stmt->execute([$tenantId]);
        // Published Academy modules are versioned with the application. No migration or
        // request-time seed is needed; learners still keep a private tenant-scoped snapshot.
        $courses = array_values(AcademyBuiltInCourses::all());
        $reserved = array_fill_keys(array_column($courses, 'key'), true);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (isset($reserved[$row['course_key']])) {
                continue;
            }
            $content = json_decode($row['content_json'], true, 64, JSON_THROW_ON_ERROR);
            $content['key'] = $row['course_key'];
            $content['published'] = (bool) $row['published'];
            $courses[] = $content;
        }
        return $courses;
    }

    public function course(int $tenantId, string $key): ?array
    {
        foreach ($this->courses($tenantId) as $course) {
            if ($course['key'] === $key) {
                return $course;
            }
        }
        return null;
    }

    public function saveCourse(int $tenantId, int $userId, array $content, bool $published): void
    {
        AcademyCurriculum::validate($content);
        // Always create a new revision. Existing enrollments retain their content snapshot.
        $key = 'course-' . bin2hex(random_bytes(8));
        $content['key'] = $key;
        unset($content['demo'], $content['built_in']);
        $stmt = $this->pdo->prepare('INSERT INTO academy_courses (tenant_id, course_key, content_json, published, updated_by) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$tenantId, $key, json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), (int) $published, $userId]);
    }

    public function publish(int $tenantId, string $key): void
    {
        $stmt = $this->pdo->prepare('UPDATE academy_courses SET published = 1 WHERE tenant_id = ? AND course_key = ?');
        $stmt->execute([$tenantId, $key]);
    }

    public function enroll(int $tenantId, int $userId, array $course): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?)');
        $stmt->execute([$tenantId, $userId, $course['key'], json_encode(['chapter' => 0, 'stage' => 0, 'tenant_id' => $tenantId, 'learner_id' => $userId, 'snapshot' => $course, 'enrolled' => time(), 'updated' => time()], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    public function states(int $tenantId, int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT course_key, state_json FROM academy_learners WHERE tenant_id = ? AND user_id = ? ORDER BY updated_at DESC');
        $stmt->execute([$tenantId, $userId]);
        $states = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $states[$row['course_key']] = json_decode($row['state_json'], true, 64, JSON_THROW_ON_ERROR);
        }
        return $states;
    }

    /** Serialize state mutations: concurrent submits cannot replay an exam or skip a stage. */
    public function mutate(int $tenantId, int $userId, string $key, callable $change): void
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT state_json FROM academy_learners WHERE tenant_id = ? AND user_id = ? AND course_key = ? FOR UPDATE');
            $stmt->execute([$tenantId, $userId, $key]);
            $json = $stmt->fetchColumn();
            if ($json === false) {
                throw new \DomainException('not_enrolled');
            }
            $state = $change(json_decode($json, true, 64, JSON_THROW_ON_ERROR));
            $stmt = $this->pdo->prepare('UPDATE academy_learners SET state_json = ?, updated_at = CURRENT_TIMESTAMP WHERE tenant_id = ? AND user_id = ? AND course_key = ?');
            $stmt->execute([json_encode($state, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $tenantId, $userId, $key]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function saveNotes(int $tenantId, int $userId, string $text): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO academy_learners (tenant_id, user_id, course_key, state_json) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP');
        $stmt->execute([$tenantId, $userId, '__notes', json_encode(['text' => $text], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
    }

    /** Staff see educational submissions in their own community, never personal notebooks. */
    public function learners(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT a.user_id, a.course_key, a.state_json, a.updated_at FROM academy_learners a WHERE a.tenant_id = ? AND a.course_key <> '__notes' ORDER BY a.updated_at DESC LIMIT 200");
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$row) {
            $row['state'] = json_decode($row['state_json'], true, 64, JSON_THROW_ON_ERROR);
            unset($row['state_json']);
        }
        return $rows;
    }
}
