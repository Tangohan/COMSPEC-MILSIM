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
    /** Fenêtre d'alerte avant échéance d'une qualification (jours). */
    private const EXPIRING_WINDOW_DAYS = 30;

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

        // Chaînon Formation → Qualification : aligner le dossier personnel sur les échéances.
        // Traité toutes communautés confondues (la table porte tenant_id depuis la migration
        // personnel_qualifications_training_link) ; sans cette colonne, on ne touche à rien.
        $qualificationsExpired = 0;
        $qualificationsExpiring = 0;
        $chk3 = $this->pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualifications'
               AND COLUMN_NAME IN ('tenant_id', 'training_certificate_id')"
        );
        if ($chk3 && (int) $chk3->fetchColumn() === 2) {
            $stmt = $this->pdo->prepare(
                "UPDATE personnel_qualifications SET status = 'expired', updated_at = NOW()
                 WHERE expires_at IS NOT NULL AND expires_at < CURDATE() AND status <> 'expired'"
            );
            $stmt->execute();
            $qualificationsExpired = $stmt->rowCount();

            $stmt = $this->pdo->prepare(
                "UPDATE personnel_qualifications SET status = 'expiring', updated_at = NOW()
                 WHERE expires_at IS NOT NULL
                   AND expires_at >= CURDATE()
                   AND expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                   AND status NOT IN ('expiring', 'in_progress')"
            );
            $stmt->execute([self::EXPIRING_WINDOW_DAYS]);
            $qualificationsExpiring = $stmt->rowCount();

            // Un certificat révoqué ou expiré ne doit plus porter une qualification valide.
            $stmt = $this->pdo->prepare(
                "UPDATE personnel_qualifications pq
                 INNER JOIN training_certificates tc ON tc.id = pq.training_certificate_id
                 SET pq.status = 'expired', pq.updated_at = NOW()
                 WHERE tc.status <> 'valid' AND pq.status IN ('valid', 'expiring')"
            );
            $stmt->execute();
            $qualificationsExpired += $stmt->rowCount();
        }

        return [
            'ok' => true,
            'summary' => "Parcours expirés : {$enrollments} · Certificats expirés : {$certificates}"
                . " · Qualifications expirées : {$qualificationsExpired} · à renouveler : {$qualificationsExpiring}",
            'details' => [
                'enrollments' => $enrollments,
                'certificates' => $certificates,
                'qualifications_expired' => $qualificationsExpired,
                'qualifications_expiring' => $qualificationsExpiring,
            ],
        ];
    }
}
