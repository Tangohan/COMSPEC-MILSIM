<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SseRichText;

/**
 * Modèles de documents personnels du bureau SSE : enregistrés à partir d'un document, privés à leur auteur ou partagés dans
 * la communauté. Cloisonnés par communauté (tenant_id) : un modèle d'une communauté n'apparaît jamais dans une autre.
 * Sans la table (étape sse_document_templates non appliquée), tout échoue en silence et seul le catalogue est proposé.
 */
final class SseDocumentTemplateRepository
{
    public const MAX_PER_USER = 40;

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * Modèles visibles du rédacteur : les siens et ceux que sa communauté partage.
     *
     * @return list<array<string,mixed>>
     */
    public function listVisible(int $tenantId, int $userId, string $guestKey): array
    {
        try {
            $rows = $this->db->fetchAll(
                'SELECT id, user_id, guest_key, title, description, document_type, classification, body, shared, updated_at
                 FROM sse_document_templates
                 WHERE tenant_id = :t AND (shared = 1 OR (user_id = :u AND guest_key = :g))
                 ORDER BY updated_at DESC, id DESC LIMIT 100',
                ['t' => $tenantId, 'u' => $userId, 'g' => $guestKey]
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $r) {
            $r['id'] = (int) $r['id'];
            $r['shared'] = (int) $r['shared'] === 1;
            $r['mine'] = (int) $r['user_id'] === $userId && (string) $r['guest_key'] === $guestKey;
            unset($r['user_id'], $r['guest_key']);
            $out[] = $r;
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public function findVisible(int $id, int $tenantId, int $userId, string $guestKey): ?array
    {
        foreach ($this->listVisible($tenantId, $userId, $guestKey) as $row) {
            if ($row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param array{title?:string,description?:string,body?:string,document_type?:string,classification?:string,shared?:bool} $data
     * @return int|null identifiant du modèle, null si refusé (titre ou corps vide, trop de modèles, table absente)
     */
    public function create(int $tenantId, int $userId, string $guestKey, array $data): ?int
    {
        $title = mb_substr(trim((string) ($data['title'] ?? '')), 0, 120);
        $raw = (string) ($data['body'] ?? '');
        $body = SseRichText::isRich($raw) ? SseRichText::wrap(substr(ltrim($raw), strlen(SseRichText::MARK))) : SseRichText::wrap($raw);
        if ($title === '' || SseRichText::plainText($body) === '' || strlen($body) > SseRichText::MAX_BYTES) {
            return null;
        }
        try {
            $count = $this->db->fetchOne(
                'SELECT COUNT(*) AS n FROM sse_document_templates WHERE tenant_id = :t AND user_id = :u AND guest_key = :g',
                ['t' => $tenantId, 'u' => $userId, 'g' => $guestKey]
            );
            if ((int) ($count['n'] ?? 0) >= self::MAX_PER_USER) {
                return null;
            }
            $now = date('Y-m-d H:i:s');

            return (int) $this->db->insert(
                'INSERT INTO sse_document_templates (tenant_id, user_id, guest_key, title, description, document_type, classification, body, shared, created_at, updated_at)
                 VALUES (:t, :u, :g, :title, :descr, :type, :class, :body, :shared, :now, :now2)',
                [
                    't' => $tenantId, 'u' => $userId, 'g' => $guestKey, 'title' => $title,
                    'descr' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 300),
                    'type' => SseDocumentRepository::normalizeType((string) ($data['document_type'] ?? 'note_analyse')),
                    'class' => SseCaseRepository::normalizeClassification((string) ($data['classification'] ?? 'confidentiel')),
                    'body' => $body, 'shared' => !empty($data['shared']) ? 1 : 0, 'now' => $now, 'now2' => $now,
                ]
            ) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Suppression réservée à l'auteur du modèle, dans sa communauté. */
    public function delete(int $id, int $tenantId, int $userId, string $guestKey): bool
    {
        try {
            return $this->db->execute(
                'DELETE FROM sse_document_templates WHERE id = :id AND tenant_id = :t AND user_id = :u AND guest_key = :g',
                ['id' => $id, 't' => $tenantId, 'u' => $userId, 'g' => $guestKey]
            ) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
