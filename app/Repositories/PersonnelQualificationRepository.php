<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use Throwable;

class PersonnelQualificationRepository
{
    /** Qualification saisie à la main par l'encadrement. */
    public const SOURCE_MANUAL = 'manual';

    /** Qualification émise automatiquement par une formation certifiante. */
    public const SOURCE_TRAINING = 'training';

    private PDO $pdo;

    /** Cache de présence des colonnes du chaînon formation (déploiement pas encore migré). */
    private ?bool $trainingLinkReady = null;
    private ?bool $reasonLabelColumnReady = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * Les colonnes du lien formation sont-elles disponibles ?
     * Sur un déploiement non migré, l'émission automatique est neutralisée sans erreur.
     */
    public function trainingLinkReady(): bool
    {
        if ($this->trainingLinkReady !== null) {
            return $this->trainingLinkReady;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualifications'
                   AND COLUMN_NAME IN ('tenant_id', 'training_course_id', 'training_certificate_id', 'source')"
            );
            $st->execute();
            $this->trainingLinkReady = (int) $st->fetchColumn() === 4;
        } catch (Throwable) {
            $this->trainingLinkReady = false;
        }

        return $this->trainingLinkReady;
    }

    /** @return list<array<string, mixed>> */
    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_qualifications WHERE user_id = ? ORDER BY expires_at IS NULL DESC, expires_at DESC, obtained_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add(int $userId, string $qualificationName, array $options = []): int
    {
        $columns = ['user_id', 'qualification_name', 'level', 'status', 'obtained_at', 'expires_at', 'issued_by'];
        $values = [
            $userId,
            $qualificationName,
            $options['level'] ?? null,
            $options['status'] ?? 'valid',
            $options['obtained_at'] ?? null,
            $options['expires_at'] ?? null,
            $options['issued_by'] ?? null,
        ];

        if ($this->reasonLabelColumnReady()) {
            $columns[] = 'reason_label';
            $values[] = $this->normalizeReasonLabel($options['reason_label'] ?? null);
        }

        if ($this->trainingLinkReady()) {
            $columns[] = 'tenant_id';
            $values[] = isset($options['tenant_id']) ? (int) $options['tenant_id'] : null;
            $columns[] = 'training_course_id';
            $values[] = isset($options['training_course_id']) ? (int) $options['training_course_id'] : null;
            $columns[] = 'training_certificate_id';
            $values[] = isset($options['training_certificate_id']) ? (int) $options['training_certificate_id'] : null;
            $columns[] = 'source';
            $values[] = (string) ($options['source'] ?? self::SOURCE_MANUAL);
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $stmt = $this->pdo->prepare(
            'INSERT INTO personnel_qualifications (' . implode(', ', $columns) . ', created_at, updated_at)
             VALUES (' . $placeholders . ', NOW(), NOW())'
        );
        $stmt->execute($values);

        return (int) $this->pdo->lastInsertId();
    }

    public function reasonLabelColumnReady(): bool
    {
        if ($this->reasonLabelColumnReady !== null) {
            return $this->reasonLabelColumnReady;
        }
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_qualifications'
                   AND COLUMN_NAME = 'reason_label' LIMIT 1"
            );
            $st->execute();
            $this->reasonLabelColumnReady = (bool) $st->fetchColumn();
        } catch (Throwable) {
            $this->reasonLabelColumnReady = false;
        }

        return $this->reasonLabelColumnReady;
    }

    private function normalizeReasonLabel(?string $reasonLabel): ?string
    {
        $reasonLabel = trim((string) $reasonLabel);
        if ($reasonLabel === '') {
            return null;
        }
        if (function_exists('mb_strlen') && mb_strlen($reasonLabel) > 255) {
            return mb_substr($reasonLabel, 0, 255);
        }
        if (strlen($reasonLabel) > 255) {
            return substr($reasonLabel, 0, 255);
        }

        return $reasonLabel;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare('UPDATE personnel_qualifications SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    /** Prochaine date d'expiration parmi les qualifications avec expires_at. */
    public function getNextExpiration(int $userId): ?string
    {
        $stmt = $this->pdo->prepare(
            'SELECT MIN(expires_at) FROM personnel_qualifications WHERE user_id = ? AND expires_at IS NOT NULL AND expires_at > CURDATE()'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_COLUMN);
        return $row ? (string) $row : null;
    }

    /**
     * Émet (ou met à jour) la qualification issue d'un certificat de formation.
     *
     * Idempotent : l'index unique sur `training_certificate_id` garantit qu'un certificat
     * rejoué ne crée pas de doublon. Retourne l'identifiant de la qualification, ou null
     * si le déploiement n'est pas migré.
     *
     * @param array{level?: ?string, obtained_at?: ?string, expires_at?: ?string, issued_by?: ?int} $options
     */
    public function upsertFromCertificate(
        int $tenantId,
        int $userId,
        int $certificateId,
        int $courseId,
        string $qualificationName,
        array $options = []
    ): ?int {
        if (!$this->trainingLinkReady() || $certificateId < 1 || $userId < 1) {
            return null;
        }

        $existing = $this->findByCertificateId($certificateId);
        $expiresAt = $options['expires_at'] ?? null;
        $status = self::statusForExpiry($expiresAt);

        if ($existing !== null) {
            $stmt = $this->pdo->prepare(
                'UPDATE personnel_qualifications
                 SET qualification_name = ?, level = ?, status = ?, obtained_at = ?, expires_at = ?,
                     training_course_id = ?, tenant_id = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $stmt->execute([
                $qualificationName,
                $options['level'] ?? null,
                $status,
                $options['obtained_at'] ?? null,
                $expiresAt,
                $courseId > 0 ? $courseId : null,
                $tenantId,
                (int) $existing['id'],
            ]);

            return (int) $existing['id'];
        }

        return $this->add($userId, $qualificationName, [
            'level' => $options['level'] ?? null,
            'status' => $status,
            'obtained_at' => $options['obtained_at'] ?? null,
            'expires_at' => $expiresAt,
            'issued_by' => $options['issued_by'] ?? null,
            'tenant_id' => $tenantId,
            'training_course_id' => $courseId > 0 ? $courseId : null,
            'training_certificate_id' => $certificateId,
            'source' => self::SOURCE_TRAINING,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findByCertificateId(int $certificateId): ?array
    {
        if (!$this->trainingLinkReady() || $certificateId < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM personnel_qualifications WHERE training_certificate_id = ? LIMIT 1'
        );
        $stmt->execute([$certificateId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * Révoque la qualification adossée à un certificat révoqué.
     * La ligne est conservée (traçabilité) et passée en `expired`.
     */
    public function revokeForCertificate(int $certificateId): bool
    {
        if (!$this->trainingLinkReady() || $certificateId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "UPDATE personnel_qualifications SET status = 'expired', updated_at = NOW()
             WHERE training_certificate_id = ?"
        );
        $stmt->execute([$certificateId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Statut dérivé de la date d'expiration.
     * `expiring` s'applique dans la fenêtre précédant l'échéance.
     *
     * Fonction pure (statique) : testable sans base de données.
     */
    public static function statusForExpiry(?string $expiresAt, int $expiringWindowDays = 30): string
    {
        if ($expiresAt === null || trim($expiresAt) === '') {
            return 'valid';
        }
        $ts = strtotime($expiresAt);
        if ($ts === false) {
            return 'valid';
        }
        $now = time();
        if ($ts < $now) {
            return 'expired';
        }
        if ($ts <= $now + ($expiringWindowDays * 86400)) {
            return 'expiring';
        }

        return 'valid';
    }

    /**
     * Réaligne les statuts sur les dates d'échéance pour une communauté (tâche planifiée).
     *
     * @return array{expiring: int, expired: int}
     */
    public function syncStatusesForTenant(int $tenantId, int $expiringWindowDays = 30): array
    {
        if (!$this->trainingLinkReady() || $tenantId < 1) {
            return ['expiring' => 0, 'expired' => 0];
        }

        $expired = $this->pdo->prepare(
            "UPDATE personnel_qualifications
             SET status = 'expired', updated_at = NOW()
             WHERE tenant_id = ? AND expires_at IS NOT NULL AND expires_at < CURDATE()
               AND status <> 'expired'"
        );
        $expired->execute([$tenantId]);

        $expiring = $this->pdo->prepare(
            "UPDATE personnel_qualifications
             SET status = 'expiring', updated_at = NOW()
             WHERE tenant_id = ? AND expires_at IS NOT NULL
               AND expires_at >= CURDATE()
               AND expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND status NOT IN ('expiring', 'in_progress')"
        );
        $expiring->execute([$tenantId, $expiringWindowDays]);

        return [
            'expiring' => $expiring->rowCount(),
            'expired' => $expired->rowCount(),
        ];
    }

    /**
     * Qualifications d'une communauté arrivant à échéance — support du tableau de recyclage.
     *
     * @return list<array<string, mixed>>
     */
    public function listExpiringForTenant(int $tenantId, int $withinDays = 60, int $limit = 200): array
    {
        if (!$this->trainingLinkReady() || $tenantId < 1) {
            return [];
        }
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT pq.*, u.display_name, u.callsign
             FROM personnel_qualifications pq
             INNER JOIN users u ON u.id = pq.user_id
             WHERE pq.tenant_id = ?
               AND pq.expires_at IS NOT NULL
               AND pq.expires_at <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
               AND pq.status <> 'in_progress'
             ORDER BY pq.expires_at ASC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, max(0, $withinDays)]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Détenteurs valides d'une qualification issue d'une formation donnée.
     * Base de la question métier « qui est formé à quoi ? ».
     *
     * @return list<int> identifiants utilisateurs
     */
    public function userIdsQualifiedForCourse(int $tenantId, int $courseId): array
    {
        if (!$this->trainingLinkReady() || $tenantId < 1 || $courseId < 1) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT user_id FROM personnel_qualifications
             WHERE tenant_id = ? AND training_course_id = ? AND status IN ('valid', 'expiring')"
        );
        $stmt->execute([$tenantId, $courseId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /** Un membre détient-il une qualification valide issue de cette formation ? */
    public function userHasValidQualificationForCourse(int $tenantId, int $userId, int $courseId): bool
    {
        if (!$this->trainingLinkReady() || $tenantId < 1 || $userId < 1 || $courseId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM personnel_qualifications
             WHERE tenant_id = ? AND user_id = ? AND training_course_id = ?
               AND status IN ('valid', 'expiring')
             LIMIT 1"
        );
        $stmt->execute([$tenantId, $userId, $courseId]);

        return (bool) $stmt->fetchColumn();
    }
}
