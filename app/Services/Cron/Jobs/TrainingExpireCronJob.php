<?php

declare(strict_types=1);

namespace App\Services\Cron\Jobs;

use App\Services\Cron\CronJobInterface;
use PDO;

/**
 * Expire les formations et certificats dépassés.
 */
final class TrainingExpireCronJob implements CronJobInterface
{
    public function __construct(private PDO $pdo) {}

    public function key(): string
    {
        return 'training_expire';
    }

    public function label(): string
    {
        return 'Formations expirées';
    }

    public function description(): string
    {
        return 'Clôture les parcours et certificats dont la date de validité est dépassée.';
    }

    public function run(): array
    {
        $enrollments = 0;
        $certificates = 0;

        $chk = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_enrollments' LIMIT 1");
        if ($chk && $chk->fetchColumn()) {
            $stmt = $this->pdo->prepare(
                "UPDATE training_enrollments SET status = 'expired'
                 WHERE status IN ('assigned','in_progress') AND expires_at IS NOT NULL AND expires_at < NOW()"
            );
            $stmt->execute();
            $enrollments = $stmt->rowCount();
        }

        $chk2 = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'training_certificates' LIMIT 1");
        if ($chk2 && $chk2->fetchColumn()) {
            $stmt = $this->pdo->prepare(
                "UPDATE training_certificates SET status = 'expired'
                 WHERE status = 'valid' AND expires_at IS NOT NULL AND expires_at < NOW()"
            );
            $stmt->execute();
            $certificates = $stmt->rowCount();
        }

        return [
            'ok' => true,
            'summary' => "Parcours expirés : {$enrollments} · Certificats expirés : {$certificates}",
            'details' => [
                'enrollments' => $enrollments,
                'certificates' => $certificates,
            ],
        ];
    }
}
