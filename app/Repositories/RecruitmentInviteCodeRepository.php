<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RecruitmentInviteCodeRepository
{
    private PDO $pdo;

    private static ?bool $hasInviteCodesTables = null;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tablesExist(): bool
    {
        if (self::$hasInviteCodesTables === null) {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.TABLES 
                 WHERE TABLE_SCHEMA = DATABASE() 
                 AND TABLE_NAME IN ('recruitment_invite_codes', 'recruitment_invite_code_uses') 
                 LIMIT 2"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            self::$hasInviteCodesTables = count($rows) === 2;
        }

        return self::$hasInviteCodesTables;
    }

    /**
     * Crée un nouveau code d'invitation pour un tenant.
     *
     * @param array<string, mixed> $data
     */
    public function create(int $tenantId, array $data): int
    {
        if (!$this->tablesExist()) {
            return 0;
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = $this->generateUniqueCode($tenantId);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO recruitment_invite_codes 
            (tenant_id, code, label, max_uses, expires_at, auto_accept, assign_to_opening_id, default_specialty, metadata_json, created_by, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $expiresAt = null;
        if (!empty($data['expires_at'])) {
            $ts = is_string($data['expires_at']) ? strtotime($data['expires_at']) : null;
            $expiresAt = $ts !== false && $ts !== null ? date('Y-m-d H:i:s', $ts) : null;
        }

        $metadata = null;
        if (!empty($data['metadata']) && is_array($data['metadata'])) {
            $metadata = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        $stmt->execute([
            $tenantId,
            $code,
            trim((string) ($data['label'] ?? '')) ?: null,
            isset($data['max_uses']) && $data['max_uses'] > 0 ? (int) $data['max_uses'] : null,
            $expiresAt,
            !empty($data['auto_accept']) ? 1 : 0,
            isset($data['assign_to_opening_id']) && $data['assign_to_opening_id'] > 0 ? (int) $data['assign_to_opening_id'] : null,
            trim((string) ($data['default_specialty'] ?? '')) ?: null,
            $metadata,
            isset($data['created_by']) && $data['created_by'] > 0 ? (int) $data['created_by'] : null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Génère un code unique pour le tenant.
     */
    private function generateUniqueCode(int $tenantId, int $length = 12): string
    {
        $attempts = 0;
        do {
            $code = strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
            $exists = $this->findByCode($tenantId, $code) !== null;
            $attempts++;
        } while ($exists && $attempts < 10);

        return $code;
    }

    /**
     * Trouve un code d'invitation par son code pour un tenant.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(int $tenantId, string $code): ?array
    {
        if (!$this->tablesExist() || trim($code) === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM recruitment_invite_codes 
             WHERE tenant_id = ? AND code = ? 
             LIMIT 1'
        );
        $stmt->execute([$tenantId, trim($code)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Trouve un code d'invitation par son ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $tenantId, int $id): ?array
    {
        if (!$this->tablesExist() || $id < 1) {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM recruitment_invite_codes 
             WHERE tenant_id = ? AND id = ? 
             LIMIT 1'
        );
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Liste tous les codes d'invitation pour un tenant.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $activeOnly = false): array
    {
        if (!$this->tablesExist()) {
            return [];
        }

        $sql = 'SELECT * FROM recruitment_invite_codes WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($activeOnly) {
            $sql .= ' AND (expires_at IS NULL OR expires_at > NOW()) AND (max_uses IS NULL OR uses_count < max_uses)';
        }

        $sql .= ' ORDER BY created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Vérifie si un code est valide et disponible.
     */
    public function isCodeValid(int $tenantId, string $code): bool
    {
        $codeData = $this->findByCode($tenantId, $code);
        if ($codeData === null) {
            return false;
        }

        // Vérifier l'expiration
        $expiresAt = $codeData['expires_at'] ?? null;
        if ($expiresAt !== null) {
            $expireTs = strtotime((string) $expiresAt);
            if ($expireTs !== false && $expireTs <= time()) {
                return false;
            }
        }

        // Vérifier le nombre d'utilisations
        $maxUses = $codeData['max_uses'] ?? null;
        if ($maxUses !== null) {
            $usesCount = (int) ($codeData['uses_count'] ?? 0);
            if ($usesCount >= (int) $maxUses) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enregistre l'utilisation d'un code d'invitation.
     */
    public function recordUse(int $tenantId, int $inviteCodeId, int $enlistmentId, string $code): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            // Enregistrer l'utilisation dans la table de logs
            $stmt = $this->pdo->prepare(
                'INSERT INTO recruitment_invite_code_uses 
                (tenant_id, invite_code_id, enlistment_id, code_used, used_at) 
                VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $inviteCodeId, $enlistmentId, $code]);

            // Incrémenter le compteur d'utilisations
            $stmt = $this->pdo->prepare(
                'UPDATE recruitment_invite_codes 
                SET uses_count = uses_count + 1, updated_at = NOW() 
                WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([$inviteCodeId, $tenantId]);

            $this->pdo->commit();

            return true;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            return false;
        }
    }

    /**
     * Met à jour un code d'invitation.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $tenantId, int $id, array $data): bool
    {
        if (!$this->tablesExist() || $id < 1) {
            return false;
        }

        $sets = [];
        $params = [];

        if (isset($data['label'])) {
            $sets[] = 'label = ?';
            $params[] = trim((string) $data['label']) ?: null;
        }

        if (isset($data['max_uses'])) {
            $sets[] = 'max_uses = ?';
            $params[] = $data['max_uses'] > 0 ? (int) $data['max_uses'] : null;
        }

        if (isset($data['expires_at'])) {
            $sets[] = 'expires_at = ?';
            $expiresAt = null;
            if ($data['expires_at'] !== null && $data['expires_at'] !== '') {
                $ts = is_string($data['expires_at']) ? strtotime($data['expires_at']) : null;
                $expiresAt = $ts !== false && $ts !== null ? date('Y-m-d H:i:s', $ts) : null;
            }
            $params[] = $expiresAt;
        }

        if (isset($data['auto_accept'])) {
            $sets[] = 'auto_accept = ?';
            $params[] = !empty($data['auto_accept']) ? 1 : 0;
        }

        if (isset($data['assign_to_opening_id'])) {
            $sets[] = 'assign_to_opening_id = ?';
            $params[] = $data['assign_to_opening_id'] > 0 ? (int) $data['assign_to_opening_id'] : null;
        }

        if (isset($data['default_specialty'])) {
            $sets[] = 'default_specialty = ?';
            $params[] = trim((string) $data['default_specialty']) ?: null;
        }

        if (empty($sets)) {
            return false;
        }

        $sets[] = 'updated_at = NOW()';
        $params[] = $tenantId;
        $params[] = $id;

        $sql = 'UPDATE recruitment_invite_codes SET ' . implode(', ', $sets) . ' WHERE tenant_id = ? AND id = ?';
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Supprime un code d'invitation (soft delete via désactivation).
     */
    public function delete(int $tenantId, int $id): bool
    {
        if (!$this->tablesExist() || $id < 1) {
            return false;
        }

        // On expire le code immédiatement plutôt que de le supprimer
        $stmt = $this->pdo->prepare(
            'UPDATE recruitment_invite_codes 
            SET expires_at = NOW(), updated_at = NOW() 
            WHERE tenant_id = ? AND id = ?'
        );

        return $stmt->execute([$tenantId, $id]);
    }

    /**
     * Compte le nombre de codes actifs pour un tenant.
     */
    public function countActiveForTenant(int $tenantId): int
    {
        if (!$this->tablesExist()) {
            return 0;
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM recruitment_invite_codes 
             WHERE tenant_id = ? 
             AND (expires_at IS NULL OR expires_at > NOW()) 
             AND (max_uses IS NULL OR uses_count < max_uses)'
        );
        $stmt->execute([$tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Récupère les statistiques d'utilisation pour un code.
     *
     * @return array{uses: int, last_used_at: ?string, enlistments: list<array<string, mixed>>}
     */
    public function getCodeStatistics(int $tenantId, int $inviteCodeId): array
    {
        if (!$this->tablesExist()) {
            return ['uses' => 0, 'last_used_at' => null, 'enlistments' => []];
        }

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) as uses, MAX(used_at) as last_used_at 
             FROM recruitment_invite_code_uses 
             WHERE tenant_id = ? AND invite_code_id = ?'
        );
        $stmt->execute([$tenantId, $inviteCodeId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->pdo->prepare(
            'SELECT e.id, e.first_name, e.last_name, e.email, e.status, u.used_at 
             FROM recruitment_invite_code_uses u
             INNER JOIN enlistments e ON e.id = u.enlistment_id AND e.tenant_id = u.tenant_id
             WHERE u.tenant_id = ? AND u.invite_code_id = ?
             ORDER BY u.used_at DESC
             LIMIT 50'
        );
        $stmt->execute([$tenantId, $inviteCodeId]);
        $enlistments = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'uses' => (int) ($stats['uses'] ?? 0),
            'last_used_at' => $stats['last_used_at'] ?? null,
            'enlistments' => $enlistments,
        ];
    }

    /**
     * Liste les codes récemment utilisés avec leurs statistiques.
     *
     * @return list<array<string, mixed>>
     */
    public function listRecentlyUsedForTenant(int $tenantId, int $limit = 10): array
    {
        if (!$this->tablesExist()) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $stmt = $this->pdo->prepare(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM recruitment_invite_code_uses u WHERE u.invite_code_id = c.id) as total_uses,
                    (SELECT MAX(used_at) FROM recruitment_invite_code_uses u WHERE u.invite_code_id = c.id) as last_used_at
             FROM recruitment_invite_codes c
             WHERE c.tenant_id = ? AND c.uses_count > 0
             ORDER BY last_used_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
