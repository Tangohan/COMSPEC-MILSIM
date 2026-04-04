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
        $stmt = $this->pdo->prepare('SELECT * FROM training_resources WHERE lesson_id = ? ORDER BY id ASC');
        $stmt->execute([$lessonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_resources WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(int $lessonId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_resources (lesson_id, resource_type, title, file_path, external_url, mime_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $lessonId,
            $data['resource_type'],
            $data['title'],
            $data['file_path'] ?? null,
            $data['external_url'] ?? null,
            $data['mime_type'] ?? null,
            isset($data['file_size']) ? (int) $data['file_size'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['resource_type', 'title', 'file_path', 'external_url', 'mime_type', 'file_size'];
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
}
