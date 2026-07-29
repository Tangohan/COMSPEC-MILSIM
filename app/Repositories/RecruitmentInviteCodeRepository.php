<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class RecruitmentInviteCodeRepository
{
    /** Accélération de candidature (formulaire d’enrôlement) — distinct des invitations e-mail et du code communauté. */
    public const KIND_PRIORITY = 'priority';

    private PDO $pdo;

    private static ?bool $hasInviteCodesTables = null;

    private static ?bool $hasCodeKindColumn = null;

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

    public function hasCodeKindColumn(): bool
    {
        if (!$this->tablesExist()) {
            return false;
        }

        if (self::$hasCodeKindColumn === null) {
            $stmt = $this->pdo->query(
                "SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'recruitment_invite_codes'
                 AND COLUMN_NAME = 'code_kind'
                 LIMIT 1"
            );
            self::$hasCodeKindColumn = (bool) ($stmt && $stmt->fetchColumn());
        }

        return self::$hasCodeKindColumn;
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

        $kind = $this->normalizeKind((string) ($data['code_kind'] ?? self::KIND_PRIORITY));

        $expiresAt = null;
        if (!empty($data['expires_at'])) {
            $ts = is_string($data['expires_at']) ? strtotime($data['expires_at']) : null;
            $expiresAt = $ts !== false && $ts !== null ? date('Y-m-d H:i:s', $ts) : null;
        }

        $metadata = null;
        if (!empty($data['metadata']) && is_array($data['metadata'])) {
            $metadata = json_encode($data['metadata'], JSON_UNESCAPED_UNICODE);
        }

        $params = [
            $tenantId,
            $code,
            trim((string) ($data['label'] ?? '')) ?: null,
        ];

        if ($this->hasCodeKindColumn()) {
            $stmt = $this->pdo->prepare(
                'INSERT INTO recruitment_invite_codes 
                (tenant_id, code, label, code_kind, max_uses, expires_at, auto_accept, assign_to_opening_id, default_specialty, metadata_json, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $params[] = $kind;
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO recruitment_invite_codes 
                (tenant_id, code, label, max_uses, expires_at, auto_accept, assign_to_opening_id, default_specialty, metadata_json, created_by, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );
        }

        $params[] = isset($data['max_uses']) && $data['max_uses'] > 0 ? (int) $data['max_uses'] : null;
        $params[] = $expiresAt;
        $params[] = !empty($data['auto_accept']) ? 1 : 0;
        $params[] = isset($data['assign_to_opening_id']) && $data['assign_to_opening_id'] > 0 ? (int) $data['assign_to_opening_id'] : null;
        $params[] = trim((string) ($data['default_specialty'] ?? '')) ?: null;
        $params[] = $metadata;
        $params[] = isset($data['created_by']) && $data['created_by'] > 0 ? (int) $data['created_by'] : null;

        $stmt->execute($params);

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
     * Trouve un code d'invitation par sa valeur pour un tenant.
     *
     * @return array<string, mixed>|null
     */
    public function findByCode(int $tenantId, string $code, ?string $kind = null): ?array
    {
        if (!$this->tablesExist() || trim($code) === '') {
            return null;
        }

        $sql = 'SELECT * FROM recruitment_invite_codes WHERE tenant_id = ? AND code = ?';
        $params = [$tenantId, trim($code)];

        if ($kind !== null && $this->hasCodeKindColumn()) {
            $sql .= ' AND code_kind = ?';
            $params[] = $this->normalizeKind($kind);
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Code prioritaire utilisable dans le formulaire d’enrôlement.
     *
     * @return array<string, mixed>|null
     */
    public function findPriorityByCode(int $tenantId, string $code): ?array
    {
        return $this->findByCode($tenantId, $code, self::KIND_PRIORITY);
    }

    /**
     * Trouve un code d'invitation par son ID.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $tenantId, int $id, ?string $kind = null): ?array
    {
        if (!$this->tablesExist() || $id < 1) {
            return null;
        }

        $sql = 'SELECT * FROM recruitment_invite_codes WHERE tenant_id = ? AND id = ?';
        $params = [$tenantId, $id];

        if ($kind !== null && $this->hasCodeKindColumn()) {
            $sql .= ' AND code_kind = ?';
            $params[] = $this->normalizeKind($kind);
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Liste les codes d'invitation pour un tenant (par défaut : prioritaires uniquement).
     *
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, bool $activeOnly = false, ?string $kind = self::KIND_PRIORITY): array
    {
        if (!$this->tablesExist()) {
            return [];
        }

        $sql = 'SELECT * FROM recruitment_invite_codes WHERE tenant_id = ?';
        $params = [$tenantId];

        if ($kind !== null && $this->hasCodeKindColumn()) {
            $sql .= ' AND code_kind = ?';
            $params[] = $this->normalizeKind($kind);
        }

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
    public function isCodeValid(int $tenantId, string $code, ?string $kind = null): bool
    {
        $codeData = $this->findByCode($tenantId, $code, $kind);
        if ($codeData === null) {
            return false;
        }

        $expiresAt = $codeData['expires_at'] ?? null;
        if ($expiresAt !== null) {
            $expireTs = strtotime((string) $expiresAt);
            if ($expireTs !== false && $expireTs <= time()) {
                return false;
            }
        }

        $maxUses = $codeData['max_uses'] ?? null;
        if ($maxUses !== null) {
            $usesCount = (int) ($codeData['uses_count'] ?? 0);
            if ($usesCount >= (int) $maxUses) {
                return false;
            }
        }

        return true;
    }

    public function isPriorityCodeValid(int $tenantId, string $code): bool
    {
        return $this->isCodeValid($tenantId, $code, self::KIND_PRIORITY);
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

            $stmt = $this->pdo->prepare(
                'INSERT INTO recruitment_invite_code_uses 
                (tenant_id, invite_code_id, enlistment_id, code_used, used_at) 
                VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$tenantId, $inviteCodeId, $enlistmentId, $code]);

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
    public function countActiveForTenant(int $tenantId, ?string $kind = self::KIND_PRIORITY): int
    {
        if (!$this->tablesExist()) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM recruitment_invite_codes 
             WHERE tenant_id = ? 
             AND (expires_at IS NULL OR expires_at > NOW()) 
             AND (max_uses IS NULL OR uses_count < max_uses)';
        $params = [$tenantId];

        if ($kind !== null && $this->hasCodeKindColumn()) {
            $sql .= ' AND code_kind = ?';
            $params[] = $this->normalizeKind($kind);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

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
    public function listRecentlyUsedForTenant(int $tenantId, int $limit = 10, ?string $kind = self::KIND_PRIORITY): array
    {
        if (!$this->tablesExist()) {
            return [];
        }

        $limit = max(1, min(50, $limit));
        $kindSql = '';
        $params = [$tenantId];
        if ($kind !== null && $this->hasCodeKindColumn()) {
            $kindSql = ' AND c.code_kind = ?';
            $params[] = $this->normalizeKind($kind);
        }

        $stmt = $this->pdo->prepare(
            "SELECT c.*, 
                    (SELECT COUNT(*) FROM recruitment_invite_code_uses u WHERE u.invite_code_id = c.id) as total_uses,
                    (SELECT MAX(used_at) FROM recruitment_invite_code_uses u WHERE u.invite_code_id = c.id) as last_used_at
             FROM recruitment_invite_codes c
             WHERE c.tenant_id = ? AND c.uses_count > 0{$kindSql}
             ORDER BY last_used_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizeKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return $kind === self::KIND_PRIORITY ? self::KIND_PRIORITY : self::KIND_PRIORITY;
    }
}
