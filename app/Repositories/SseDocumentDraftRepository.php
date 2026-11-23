<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseRichText;

/**
 * Brouillon automatique de l'éditeur de documents SSE : un par rédacteur et par document (document_id 0 : document pas encore
 * créé). Le corps est assaini avant d'être gardé. Sans la table (étape sse_document_drafts non appliquée), tout échoue en silence
 * et l'éditeur garde seulement le brouillon du navigateur.
 */
final class SseDocumentDraftRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * @param array{title?:string,body?:string,document_type?:string,classification?:string,case_id?:int} $data
     * @return string|null date d'enregistrement, null si la table manque ou si le corps est trop gros
     */
    public function save(int $tenantId, int $userId, string $guestKey, int $documentId, array $data): ?string
    {
        $body = (string) ($data['body'] ?? '');
        if (strlen($body) > SseRichText::MAX_BYTES * 2) {
            return null;
        }
        $body = SseRichText::isRich($body) ? SseRichText::wrap(substr(ltrim($body), strlen(SseRichText::MARK))) : SseRichText::wrap($body);
        $now = date('Y-m-d H:i:s');
        $params = [
            't' => $tenantId, 'u' => $userId, 'g' => $guestKey, 'd' => $documentId,
            'title' => mb_substr(trim((string) ($data['title'] ?? '')), 0, 240),
            'body' => $body,
            'type' => SseDocumentRepository::normalizeType((string) ($data['document_type'] ?? 'note_analyse')),
            'class' => SseCaseRepository::normalizeClassification((string) ($data['classification'] ?? 'confidentiel')),
            'case' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
            'now' => $now,
        ];
        try {
            $existing = $this->db->fetchOne(
                'SELECT id FROM sse_document_drafts WHERE tenant_id = :t AND user_id = :u AND guest_key = :g AND document_id = :d LIMIT 1',
                ['t' => $tenantId, 'u' => $userId, 'g' => $guestKey, 'd' => $documentId]
            );
            if ($existing) {
                $this->db->execute(
                    'UPDATE sse_document_drafts SET title = :title, body = :body, document_type = :type, classification = :class,
                        case_id = :case, updated_at = :now WHERE id = :id',
                    ['title' => $params['title'], 'body' => $body, 'type' => $params['type'], 'class' => $params['class'],
                        'case' => $params['case'], 'now' => $now, 'id' => (int) $existing['id']]
                );
            } else {
                $this->db->insert(
                    'INSERT INTO sse_document_drafts (tenant_id, user_id, guest_key, document_id, title, body, document_type, classification, case_id, updated_at)
                     VALUES (:t, :u, :g, :d, :title, :body, :type, :class, :case, :now)',
                    $params
                );
            }
        } catch (\Throwable) {
            return null;
        }

        return $now;
    }

    /** @return array<string,mixed>|null */
    public function find(int $tenantId, int $userId, string $guestKey, int $documentId): ?array
    {
        try {
            $row = $this->db->fetchOne(
                'SELECT title, body, document_type, classification, case_id, updated_at FROM sse_document_drafts
                 WHERE tenant_id = :t AND user_id = :u AND guest_key = :g AND document_id = :d LIMIT 1',
                ['t' => $tenantId, 'u' => $userId, 'g' => $guestKey, 'd' => $documentId]
            );
        } catch (\Throwable) {
            return null;
        }

        return $row ?: null;
    }

    public function discard(int $tenantId, int $userId, string $guestKey, int $documentId): void
    {
        try {
            $this->db->execute(
                'DELETE FROM sse_document_drafts WHERE tenant_id = :t AND user_id = :u AND guest_key = :g AND document_id = :d',
                ['t' => $tenantId, 'u' => $userId, 'g' => $guestKey, 'd' => $documentId]
            );
        } catch (\Throwable) {
        }
    }
}
