<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class TrainingProgressRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array<string, mixed>> */
    public function listByEnrollmentId(int $enrollmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, l.title AS lesson_title, l.position AS lesson_position, l.module_id
             FROM training_progress p
             JOIN training_lessons l ON l.id = p.lesson_id
             WHERE p.enrollment_id = ?
             ORDER BY l.id ASC'
        );
        $stmt->execute([$enrollmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByEnrollmentAndLesson(int $enrollmentId, int $lessonId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM training_progress WHERE enrollment_id = ? AND lesson_id = ? LIMIT 1');
        $stmt->execute([$enrollmentId, $lessonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsert(int $enrollmentId, int $lessonId, array $data): void
    {
        $existing = $this->findByEnrollmentAndLesson($enrollmentId, $lessonId);
        $status = $data['status'] ?? ($existing['status'] ?? 'not_started');
        $progressPercent = (float) ($data['progress_percent'] ?? $existing['progress_percent'] ?? 0);
        $timeSpent = (int) ($data['time_spent_seconds'] ?? $existing['time_spent_seconds'] ?? 0);
        $lastPosition = (int) ($data['last_position_seconds'] ?? $existing['last_position_seconds'] ?? 0);
        $viewedAt = $data['viewed_at'] ?? $existing['viewed_at'] ?? null;
        $completedAt = $data['completed_at'] ?? $existing['completed_at'] ?? null;

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE training_progress SET status = ?, progress_percent = ?, time_spent_seconds = ?, last_position_seconds = ?, viewed_at = ?, completed_at = ? WHERE enrollment_id = ? AND lesson_id = ?'
            );
            $stmt->execute([$status, $progressPercent, $timeSpent, $lastPosition, $viewedAt, $completedAt, $enrollmentId, $lessonId]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO training_progress (enrollment_id, lesson_id, status, progress_percent, time_spent_seconds, last_position_seconds, viewed_at, completed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$enrollmentId, $lessonId, $status, $progressPercent, $timeSpent, $lastPosition, $viewedAt, $completedAt]);
        }
    }

    /** Insère une ligne de progression si elle n'existe pas. */
    public function ensureExists(int $enrollmentId, int $lessonId, string $status = 'not_started'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT IGNORE INTO training_progress (enrollment_id, lesson_id, status) VALUES (?, ?, ?)'
        );
        $stmt->execute([$enrollmentId, $lessonId, $status]);
    }

    /** Initialise les lignes de progression pour toutes les leçons d'un parcours (à l'entrée en formation). */
    public function initForEnrollment(int $enrollmentId, array $lessonIds): void
    {
        foreach ($lessonIds as $lessonId) {
            $this->ensureExists($enrollmentId, (int) $lessonId, 'not_started');
        }
    }

    public function countCompletedForEnrollment(int $enrollmentId): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM training_progress WHERE enrollment_id = ? AND status = 'completed'"
        );
        $stmt->execute([$enrollmentId]);
        return (int) $stmt->fetchColumn();
    }
}
