<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentPermissionRepository
{
    private const TYPES = ['role', 'unit', 'user', 'group'];
    private const ACCESS_LEVELS = ['read', 'comment', 'edit', 'approve', 'manage'];

    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /** @return list<array{id: int, document_id: int, permission_type: string, permission_value: string, access_level: string}> */
    public function getByDocument(int $documentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_id, permission_type, permission_value, access_level, created_at FROM document_permissions WHERE document_id = ? ORDER BY permission_type, permission_value'
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, array{permission_type: string, permission_value: string, access_level: string}> $permissions
     */
    public function setForDocument(int $documentId, array $permissions): void
    {
        $this->pdo->prepare('DELETE FROM document_permissions WHERE document_id = ?')->execute([$documentId]);
        foreach ($permissions as $item) {
            $type = $item['permission_type'] ?? '';
            $value = $item['permission_value'] ?? '';
            $level = $item['access_level'] ?? 'read';
            if (in_array($type, self::TYPES, true) && $value !== '' && in_array($level, self::ACCESS_LEVELS, true)) {
                $this->add($documentId, $type, $value, $level);
            }
        }
    }

    public function add(int $documentId, string $permissionType, string $permissionValue, string $accessLevel = 'read'): void
    {
        if (!in_array($permissionType, self::TYPES, true) || !in_array($accessLevel, self::ACCESS_LEVELS, true)) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_permissions (document_id, permission_type, permission_value, access_level, created_at) VALUES (?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$documentId, $permissionType, substr($permissionValue, 0, 190), $accessLevel]);
    }

    public function remove(int $documentId, int $permissionId): void
    {
        $this->pdo->prepare('DELETE FROM document_permissions WHERE document_id = ? AND id = ?')->execute([$documentId, $permissionId]);
    }

    public static function getTypes(): array
    {
        return self::TYPES;
    }

    public static function getAccessLevels(): array
    {
        return self::ACCESS_LEVELS;
    }
}
