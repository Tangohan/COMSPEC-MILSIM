<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Session;
use App\Services\Identity\UserIdentityMergeRules;
use PDO;

/**
 * Données legacy (service_number, admin_notes, etc.). La vérité métier pour clearance / readiness
 * doit être portée par {@see PersonnelProfileRepository} ; les champs ici servent encore à la
 * compatibilité et à la double-écriture matricule (voir {@see MatriculeService}).
 */
class PersonnelExtrasRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function getByUserId(int $userId, ?int $tenantId = null): ?array
    {
        if ($userId < 1) {
            return null;
        }
        $preferredTenantId = ($tenantId !== null && $tenantId > 0)
            ? $tenantId
            : $this->preferredTenantId($userId);
        if ($this->hasTenantIdColumn()) {
            $stmt = $this->pdo->prepare('SELECT * FROM personnel_extras WHERE user_id = ?');
            $stmt->execute([$userId]);
            $row = UserIdentityMergeRules::pickPreferredDossierRow(
                $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
                $preferredTenantId
            );

            return $row ?: null;
        }
        $stmt = $this->pdo->prepare('SELECT * FROM personnel_extras WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function preferredTenantId(int $userId): int
    {
        try {
            $sessionTid = (int) Session::get('tenant_id', 0);
            if ($sessionTid > 0) {
                return $sessionTid;
            }
        } catch (\Throwable) {
        }
        $st = $this->pdo->prepare('SELECT tenant_id FROM users WHERE id = ? LIMIT 1');
        $st->execute([$userId]);

        return (int) $st->fetchColumn();
    }

    private function hasTenantIdColumn(): bool
    {
        $st = $this->pdo->query(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_extras' AND COLUMN_NAME = 'tenant_id' LIMIT 1"
        );

        return $st !== false && (bool) $st->fetchColumn();
    }

    public function getProfileByUserId(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Crée une ligne personnel_extras si elle n'existe pas. */
    public function ensureRecord(int $userId): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO personnel_extras (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())');
        $stmt->execute([$userId]);
    }

    /** RGPD / soft-delete : efface matricule legacy et notes admin. */
    public function deleteByUserId(int $userId): void
    {
        if ($userId < 1) {
            return;
        }
        $stmt = $this->pdo->prepare('DELETE FROM personnel_extras WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    public function updateServiceNumber(int $userId, string $serviceNumber): bool
    {
        $this->ensureRecord($userId);
        $stmt = $this->pdo->prepare('UPDATE personnel_extras SET service_number = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$serviceNumber, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function updateAdminNotes(int $userId, string $adminNotes): bool
    {
        $this->ensureRecord($userId);
        $stmt = $this->pdo->prepare('UPDATE personnel_extras SET admin_notes = ?, updated_at = NOW() WHERE user_id = ?');
        $stmt->execute([$adminNotes, $userId]);
        return $stmt->rowCount() > 0;
    }
}
