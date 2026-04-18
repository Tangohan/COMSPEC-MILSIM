<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

class DocumentSecurityRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function createSession(int $tenantId, int $documentId, int $userId, bool $signatureRequired): string
    {
        $token = hash('sha256', $tenantId . '|' . $documentId . '|' . $userId . '|' . bin2hex(random_bytes(20)) . '|' . microtime(true));
        $stmt = $this->pdo->prepare('INSERT INTO document_access_sessions (tenant_id, document_id, user_id, session_token, signature_required, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if (is_string($ip) && strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        if (is_string($ua) && strlen($ua) > 500) {
            $ua = substr($ua, 0, 500);
        }
        $stmt->execute([$tenantId, $documentId, $userId, $token, $signatureRequired ? 1 : 0, $ip, $ua]);

        return $token;
    }

    public function findSessionByToken(string $token): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM document_access_sessions WHERE session_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function addReadSeconds(string $token, int $seconds): void
    {
        $seconds = max(0, min($seconds, 60));
        $stmt = $this->pdo->prepare('UPDATE document_access_sessions SET read_seconds = read_seconds + ?, last_seen_at = NOW() WHERE session_token = ?');
        $stmt->execute([$seconds, $token]);
    }

    public function markDownloaded(string $token): void
    {
        $stmt = $this->pdo->prepare('UPDATE document_access_sessions SET download_count = download_count + 1, last_seen_at = NOW() WHERE session_token = ?');
        $stmt->execute([$token]);
    }

    public function completeSignature(string $token, string $signatureName, ?string $signatureImagePath): void
    {
        $stmt = $this->pdo->prepare('UPDATE document_access_sessions SET signature_completed_at = NOW(), signature_name = ?, signature_image_path = ?, last_seen_at = NOW() WHERE session_token = ?');
        $stmt->execute([$signatureName, $signatureImagePath, $token]);
    }

    public function closeSession(string $token): void
    {
        $stmt = $this->pdo->prepare('UPDATE document_access_sessions SET closed_at = NOW(), last_seen_at = NOW() WHERE session_token = ?');
        $stmt->execute([$token]);
    }

    /** @param array<string, mixed> $metadata */
    public function logEvent(string $token, int $documentId, ?int $userId, string $eventType, array $metadata = []): void
    {
        $stmt = $this->pdo->prepare('SELECT id FROM document_access_sessions WHERE session_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $sessionId = (int) $stmt->fetchColumn();
        if ($sessionId <= 0) {
            return;
        }
        $eventStmt = $this->pdo->prepare('INSERT INTO document_access_events (session_id, document_id, user_id, event_type, metadata_json) VALUES (?, ?, ?, ?, ?)');
        $eventStmt->execute([$sessionId, $documentId, $userId, substr($eventType, 0, 80), $metadata !== [] ? json_encode($metadata) : null]);
    }

    public function latestStatsForDocument(int $documentId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare('SELECT s.*, u.display_name, u.email FROM document_access_sessions s LEFT JOIN users u ON u.id = s.user_id WHERE s.document_id = ? ORDER BY s.opened_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$documentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestEventsForDocument(int $documentId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('SELECT e.*, s.session_token, u.display_name, u.email FROM document_access_events e LEFT JOIN document_access_sessions s ON s.id = e.session_id LEFT JOIN users u ON u.id = e.user_id WHERE e.document_id = ? ORDER BY e.created_at DESC LIMIT ' . (int) $limit);
        $stmt->execute([$documentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
