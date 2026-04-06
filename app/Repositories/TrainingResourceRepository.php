<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingResourceRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByLessonId(int $lessonId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tr.*, d.slug AS document_slug, d.status AS document_status
             FROM training_resources tr
             LEFT JOIN documents d ON d.id = tr.document_id
             WHERE tr.lesson_id = ?
             ORDER BY tr.id ASC'
        );
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tr.*, d.slug AS document_slug, d.status AS document_status
             FROM training_resources tr
             LEFT JOIN documents d ON d.id = tr.document_id
             WHERE tr.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $lessonId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_resources (lesson_id, resource_type, title, file_path, external_url, mime_type, file_size, document_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $lessonId,
            $data['resource_type'],
            $data['title'],
            $data['file_path'] ?? null,
            $data['external_url'] ?? null,
            $data['mime_type'] ?? null,
            isset($data['file_size']) ? (int) $data['file_size'] : null,
            isset($data['document_id']) && (int) $data['document_id'] > 0
                ? (int) $data['document_id'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['resource_type', 'title', 'file_path', 'external_url', 'mime_type', 'file_size', 'document_id'];
        $fields = [];
        $params = [];
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
        $stmt = $this->pdo->prepare('UPDATE training_resources SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_resources WHERE id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Parcours LMS publiés qui référencent un document de la bibliothèque (ressource library_document).
     *
     * @param list<int> $documentIds
     * @return list<array{document_id: int, course_title: string, course_slug: string}>
     */
    public function listPublishedLmsCourseRefsForDocumentIds(int $tenantId, array $documentIds): array
    {
        $documentIds = array_values(array_unique(array_filter(array_map('intval', $documentIds), static fn (int $i): bool => $i > 0)));
        if ($documentIds === []) {
            return [];
        }
        try {
            $ph = implode(',', array_fill(0, count($documentIds), '?'));
            $sql = "SELECT DISTINCT tr.document_id, c.title AS course_title, c.slug AS course_slug
                    FROM training_resources tr
                    INNER JOIN training_lessons tl ON tl.id = tr.lesson_id
                    INNER JOIN training_modules tm ON tm.id = tl.module_id
                    INNER JOIN training_courses c ON c.id = tm.course_id
                    WHERE tr.resource_type = 'library_document'
                      AND tr.document_id IS NOT NULL
                      AND tr.document_id IN ($ph)
                      AND c.visibility = 'published'
                      AND (
                          (c.tenant_id = ? AND COALESCE(c.lms_scope, 'tenant') = 'tenant')
                          OR c.lms_scope = 'platform'
                      )";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([...$documentIds, $tenantId]);
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'document_id' => (int) ($row['document_id'] ?? 0),
                    'course_title' => (string) ($row['course_title'] ?? ''),
                    'course_slug' => (string) ($row['course_slug'] ?? ''),
                ];
            }

            return $out;
        } catch (\Throwable) {
            return [];
        }
    }
}
