<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentAuditRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    /**
     * @param array<string, mixed>|null $oldValue
     * @param array<string, mixed>|null $newValue
     */
    public function log(
        int $documentId,
        ?int $userId,
        string $action,
        ?array $oldValue = null,
        ?array $newValue = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        $ip = $ipAddress ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null);
        $ua = $userAgent ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if (is_string($ua) && strlen($ua) > 500) {
            $ua = substr($ua, 0, 500);
        }
        $oldJson = $oldValue !== null ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : null;
        $newJson = $newValue !== null ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : null;
        $stmt = $this->pdo->prepare(
            'INSERT INTO document_audit_log (document_id, user_id, action, old_value, new_value, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$documentId, $userId, $action, $oldJson, $newJson, $ip, $ua]);
    }

    /** @return list<array{id: int, document_id: int, user_id: ?int, action: string, old_value: ?string, new_value: ?string, ip_address: ?string, user_agent: ?string, created_at: string}> */
    public function getByDocument(int $documentId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, document_id, user_id, action, old_value, new_value, ip_address, user_agent, created_at FROM document_audit_log WHERE document_id = ? ORDER BY created_at DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
