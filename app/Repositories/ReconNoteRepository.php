<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\ReconNoteCatalog;
use PDO;
use Throwable;

class ReconNoteRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        try {
            $st = $this->pdo->prepare(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'recon_notes' LIMIT 1"
            );
            $st->execute();
            if ($st->fetchColumn()) {
                return;
            }
            $migrate = require base_path('bootstrap/atak_recon_notes_migration.php');
            if (is_callable($migrate)) {
                $migrate($this->pdo);
            }
        } catch (Throwable) {
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function upsert(array $data): array
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $sourceId = trim((string) ($data['source_id'] ?? ''));
        if ($tenantId < 1 || $sourceId === '') {
            return [];
        }
        $mapId = max(1, (int) ($data['map_id'] ?? 1));
        $missionId = substr(trim((string) ($data['mission_id'] ?? '')), 0, 80);
        $text = substr(trim((string) ($data['text'] ?? '')), 0, ReconNoteCatalog::TEXT_MAX);
        $tag = ReconNoteCatalog::normalizeTag((string) ($data['tag'] ?? ''));
        $author = substr(trim((string) ($data['author'] ?? '')), 0, 80);
        $authorUid = substr(trim((string) ($data['author_uid'] ?? '')), 0, 32);
        $confidence = ReconNoteCatalog::normalizeConfidence((string) ($data['confidence'] ?? ''));
        $x = (float) ($data['pos_x'] ?? 0);
        $y = (float) ($data['pos_y'] ?? 0);
        $z = (float) ($data['pos_z'] ?? 0);

        $st = $this->pdo->prepare(
            'INSERT INTO recon_notes
             (tenant_id, map_id, mission_id, source_id, event_type, note_type,
              pos_x, pos_y, pos_z, text, tag, author, author_uid, confidence)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               text = VALUES(text),
               tag = VALUES(tag),
               confidence = VALUES(confidence),
               pos_x = VALUES(pos_x),
               pos_y = VALUES(pos_y),
               pos_z = VALUES(pos_z),
               author = VALUES(author)'
        );
        $st->execute([
            $tenantId,
            $mapId,
            $missionId,
            $sourceId,
            ReconNoteCatalog::EVENT_TYPE,
            ReconNoteCatalog::NOTE_TYPE,
            $x,
            $y,
            $z,
            $text,
            $tag,
            $author,
            $authorUid,
            $confidence,
        ]);

        return $this->findBySource($tenantId, $sourceId) ?? [];
    }

    public function secondsSinceLastByAuthor(int $tenantId, string $authorUid): ?int
    {
        $authorUid = trim($authorUid);
        if ($tenantId < 1 || $authorUid === '') {
            return null;
        }
        $st = $this->pdo->prepare(
            'SELECT created_at FROM recon_notes
             WHERE tenant_id = ? AND author_uid = ?
             ORDER BY created_at DESC LIMIT 1'
        );
        $st->execute([$tenantId, $authorUid]);
        $raw = $st->fetchColumn();
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $ts = strtotime($raw);

        return $ts === false ? null : max(0, time() - $ts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForMap(int $tenantId, int $mapId, int $limit = 80): array
    {
        $limit = max(1, min(200, $limit));
        $st = $this->pdo->prepare(
            'SELECT * FROM recon_notes
             WHERE tenant_id = ? AND map_id = ?
             ORDER BY created_at DESC
             LIMIT ' . $limit
        );
        $st->execute([$tenantId, max(1, $mapId)]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $out[] = $this->present($row);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findBySource(int $tenantId, string $sourceId): ?array
    {
        $st = $this->pdo->prepare(
            'SELECT * FROM recon_notes WHERE tenant_id = ? AND source_id = ? LIMIT 1'
        );
        $st->execute([$tenantId, $sourceId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->present($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        $created = (string) ($row['created_at'] ?? '');
        $ts = $created !== '' ? strtotime($created) : false;
        $age = ($ts === false) ? 0 : max(0, time() - $ts);
        $tag = ReconNoteCatalog::normalizeTag((string) ($row['tag'] ?? ''));
        $confidence = ReconNoteCatalog::normalizeConfidence((string) ($row['confidence'] ?? ''));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'source_id' => (string) ($row['source_id'] ?? ''),
            'event_type' => ReconNoteCatalog::EVENT_TYPE,
            'type' => ReconNoteCatalog::NOTE_TYPE,
            'pos_x' => (float) ($row['pos_x'] ?? 0),
            'pos_y' => (float) ($row['pos_y'] ?? 0),
            'pos_z' => (float) ($row['pos_z'] ?? 0),
            'text' => (string) ($row['text'] ?? ''),
            'tag' => $tag,
            'tag_label' => ReconNoteCatalog::tagLabel($tag),
            'color' => ReconNoteCatalog::tagColor($tag),
            'author' => (string) ($row['author'] ?? ''),
            'author_uid' => (string) ($row['author_uid'] ?? ''),
            'confidence' => $confidence,
            'confidence_label' => ReconNoteCatalog::confidenceLabel($confidence),
            'created_at' => $created,
            'age_sec' => $age,
            'freshness' => ReconNoteCatalog::freshness($age),
            'opacity' => ReconNoteCatalog::opacity($age),
        ];
    }
}
