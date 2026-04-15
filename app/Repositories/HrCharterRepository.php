<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class HrCharterRepository
{
    private function pdo(): PDO
    {
        return Database::getPdo();
    }

    public function schemaReady(): bool
    {
        try {
            $st = $this->pdo()->query(
                "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lms_hr_charter_documents' LIMIT 1"
            );

            return (bool) $st->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Document actif le plus récent pour la communauté. */
    public function getActiveDocumentForTenant(int $tenantId): ?array
    {
        if (!$this->schemaReady()) {
            return null;
        }
        $st = $this->pdo()->prepare(
            'SELECT * FROM lms_hr_charter_documents
             WHERE tenant_id = ? AND is_active = 1
             ORDER BY published_at DESC, id DESC
             LIMIT 1'
        );
        $st->execute([$tenantId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    public function userHasAcceptedDocument(int $userId, int $documentId): bool
    {
        if (!$this->schemaReady() || $documentId < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'SELECT 1 FROM lms_hr_charter_acceptances WHERE user_id = ? AND document_id = ? LIMIT 1'
        );
        $st->execute([$userId, $documentId]);

        return (bool) $st->fetchColumn();
    }

    /**
     * Vrai si un document actif existe et que le membre ne l’a pas encore accepté.
     */
    public function userMustAcknowledgeBeforeTraining(int $tenantId, int $userId): bool
    {
        $doc = $this->getActiveDocumentForTenant($tenantId);
        if ($doc === null) {
            return false;
        }
        $id = (int) ($doc['id'] ?? 0);

        return !$this->userHasAcceptedDocument($userId, $id);
    }

    public function recordAcceptance(int $tenantId, int $userId, int $documentId, ?string $ipAddress): void
    {
        $st = $this->pdo()->prepare(
            'INSERT INTO lms_hr_charter_acceptances (tenant_id, user_id, document_id, ip_address)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE accepted_at = VALUES(accepted_at), ip_address = VALUES(ip_address)'
        );
        $st->execute([$tenantId, $userId, $documentId, $ipAddress !== null && $ipAddress !== '' ? $ipAddress : null]);
    }

    /**
     * Insère un document par défaut si la table est vide pour ce tenant (première installation).
     */
    public function ensureSeedDocumentForTenant(int $tenantId): void
    {
        if (!$this->schemaReady() || $tenantId < 1) {
            return;
        }
        $st = $this->pdo()->prepare('SELECT 1 FROM lms_hr_charter_documents WHERE tenant_id = ? LIMIT 1');
        $st->execute([$tenantId]);
        if ($st->fetchColumn()) {
            return;
        }
        $title = 'Charte de participation aux formations';
        $body = '<p>Cette communauté propose des parcours pédagogiques. En poursuivant, vous reconnaissez avoir pris connaissance des règles de respect, de progression et d’usage raisonnable des ressources mises à disposition.</p>'
            . '<p>Les équipes peuvent mettre à jour cette charte ; une nouvelle acceptation pourra vous être demandée après publication.</p>';
        $ins = $this->pdo()->prepare(
            'INSERT INTO lms_hr_charter_documents (tenant_id, title, body_html, is_active, published_at)
             VALUES (?, ?, ?, 1, NOW())'
        );
        $ins->execute([$tenantId, $title, $body]);
    }

    /**
     * Met à jour le document actif de la communauté (titre et corps).
     */
    public function updateActiveDocumentContent(int $tenantId, string $title, string $bodyHtml): bool
    {
        if (!$this->schemaReady() || $tenantId < 1 || trim($title) === '') {
            return false;
        }
        $this->ensureSeedDocumentForTenant($tenantId);
        $doc = $this->getActiveDocumentForTenant($tenantId);
        if ($doc === null) {
            return false;
        }
        $id = (int) ($doc['id'] ?? 0);
        if ($id < 1) {
            return false;
        }
        $st = $this->pdo()->prepare(
            'UPDATE lms_hr_charter_documents SET title = ?, body_html = ?, published_at = NOW() WHERE id = ? AND tenant_id = ? AND is_active = 1'
        );

        return $st->execute([$title, $bodyHtml, $id, $tenantId]);
    }
}
