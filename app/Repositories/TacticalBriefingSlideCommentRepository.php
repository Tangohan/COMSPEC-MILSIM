<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\LazyDatabaseConnection;
use PDO;

/**
 * Commentaires sur les diapositives de briefing (téléphone ATAK / back-office).
 */
class TacticalBriefingSlideCommentRepository
{
    use LazyDatabaseConnection;


    public function __construct()
    {
        // PDO + schéma à la première requête (pas au boot Container / mod-report).
    }

    protected function onDatabaseConnected(PDO $pdo): void
    {
        try {
            require_once dirname(__DIR__, 2) . '/bootstrap/tactical_briefing_slide_enrichment_migration.php';
            if (function_exists('ensure_tactical_briefing_slide_enrichment_schema')) {
                ensure_tactical_briefing_slide_enrichment_schema($pdo);
            }
        } catch (\Throwable) {
        }
    }

    public function isReady(): bool
    {
        try {
            $stmt = $this->pdo()->query("SHOW TABLES LIKE 'tactical_briefing_slide_comments'");

            return (bool) ($stmt && $stmt->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public function listForSlide(int $tenantId, int $slideId, int $limit = 80): array
    {
        if (!$this->isReady() || $tenantId < 1 || $slideId < 1) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $stmt = $this->pdo()->prepare(
            "SELECT id, slide_id, author_label, body, source, created_at
             FROM tactical_briefing_slide_comments
             WHERE tenant_id = ? AND slide_id = ?
             ORDER BY created_at ASC, id ASC
             LIMIT {$limit}"
        );
        $stmt->execute([$tenantId, $slideId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Compteurs de commentaires par diapositive pour un tenant.
     *
     * @return array<int, int> slide_id => count
     */
    public function countsBySlideForTenant(int $tenantId): array
    {
        if (!$this->isReady() || $tenantId < 1) {
            return [];
        }
        $stmt = $this->pdo()->prepare(
            'SELECT slide_id, COUNT(*) AS c
             FROM tactical_briefing_slide_comments
             WHERE tenant_id = ?
             GROUP BY slide_id'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[(int) ($row['slide_id'] ?? 0)] = (int) ($row['c'] ?? 0);
        }

        return $out;
    }

    public function insert(int $tenantId, int $slideId, string $authorLabel, string $body, string $source = 'phone'): ?int
    {
        if (!$this->isReady() || $tenantId < 1 || $slideId < 1) {
            return null;
        }
        $body = trim($body);
        if ($body === '') {
            return null;
        }
        $authorLabel = mb_substr(trim($authorLabel), 0, 120);
        if ($authorLabel === '') {
            $authorLabel = 'Opérateur';
        }
        $source = in_array($source, ['phone', 'admin', 'arma'], true) ? $source : 'phone';
        if (mb_strlen($body) > 2000) {
            $body = mb_substr($body, 0, 2000);
        }
        $stmt = $this->pdo()->prepare(
            'INSERT INTO tactical_briefing_slide_comments (tenant_id, slide_id, author_label, body, source, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$tenantId, $slideId, $authorLabel, $body, $source]);

        return (int) $this->pdo()->lastInsertId();
    }
}
