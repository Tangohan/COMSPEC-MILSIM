<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingModuleRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByCourseId(int $courseId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_modules WHERE course_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$courseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_modules WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getMaxPosition(int $courseId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM training_modules WHERE course_id = ?');
        $stmt->execute([$courseId]);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $courseId, array $data): int
    {
        $position = $data['position'] ?? $this->getMaxPosition($courseId) + 1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_modules (course_id, title, description, subtitle, learning_objectives, estimated_minutes, position, is_required)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $courseId,
            $data['title'],
            $data['description'] ?? null,
            $data['subtitle'] ?? null,
            $data['learning_objectives'] ?? null,
            (int) ($data['estimated_minutes'] ?? 0),
            $position,
            (int) ($data['is_required'] ?? 1),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['title', 'description', 'subtitle', 'learning_objectives', 'estimated_minutes', 'position', 'is_required'];
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
        $stmt = $this->pdo->prepare('UPDATE training_modules SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_modules WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function reorder(int $courseId, array $idOrder): void
    {
        foreach ($idOrder as $pos => $id) {
            $stmt = $this->pdo->prepare('UPDATE training_modules SET position = ? WHERE id = ? AND course_id = ?');
            $stmt->execute([$pos + 1, $id, $courseId]);
        }
    }
}
