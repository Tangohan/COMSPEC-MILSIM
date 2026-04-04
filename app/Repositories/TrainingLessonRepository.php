<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingLessonRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByModuleId(int $moduleId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_lessons WHERE module_id = ? ORDER BY position ASC, id ASC');
        $stmt->execute([$moduleId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_lessons WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getMaxPosition(int $moduleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(position), 0) FROM training_lessons WHERE module_id = ?');
        $stmt->execute([$moduleId]);
        return (int) $stmt->fetchColumn();
    }

    public function create(int $moduleId, array $data): int
    {
        $position = $data['position'] ?? $this->getMaxPosition($moduleId) + 1;
        $stmt = $this->pdo->prepare(
            'INSERT INTO training_lessons (module_id, title, lesson_type, content, external_url, duration_minutes, position, is_required)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $moduleId,
            $data['title'],
            $data['lesson_type'] ?? 'richtext',
            $data['content'] ?? null,
            $data['external_url'] ?? null,
            (int) ($data['duration_minutes'] ?? 0),
            $position,
            (int) ($data['is_required'] ?? 1),
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['title', 'lesson_type', 'content', 'external_url', 'duration_minutes', 'position', 'is_required'];
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
        $stmt = $this->pdo->prepare('UPDATE training_lessons SET ' . implode(', ', $fields) . ' WHERE id = ?');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM training_lessons WHERE id = ?');
        $stmt->execute([$id]);
    }

    /** Retourne la leçon précédente dans le même module (position < current). */
    public function getPreviousInModule(int $moduleId, int $currentPosition): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_lessons WHERE module_id = ? AND position < ? ORDER BY position DESC LIMIT 1');
        $stmt->execute([$moduleId, $currentPosition]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Retourne la leçon suivante dans le même module (position > current). */
    public function getNextInModule(int $moduleId, int $currentPosition): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_lessons WHERE module_id = ? AND position > ? ORDER BY position ASC LIMIT 1');
        $stmt->execute([$moduleId, $currentPosition]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
