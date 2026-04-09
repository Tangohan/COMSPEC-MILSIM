<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CooperationAnnouncementTemplateRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getPdo();
    }

    public function tableExists(): bool
    {
        try {
            $st = $this->pdo->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cooperation_announcement_templates' LIMIT 1");

            return (bool) ($st && $st->fetchColumn());
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed>|null */
    public function findResolved(int $tenantId, string $eventKey, string $channel): ?array
    {
        if (!$this->tableExists() || $eventKey === '' || $channel === '') {
            return null;
        }
        foreach ([$tenantId, 0] as $tid) {
            $stmt = $this->pdo->prepare(
                'SELECT * FROM cooperation_announcement_templates
                 WHERE tenant_id = ? AND event_key = ? AND channel = ? AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([$tid, $eventKey, $channel]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null Ligne exacte (actif ou non), pour l’édition. */
    public function findExact(int $tenantId, string $eventKey, string $channel): ?array
    {
        if (!$this->tableExists() || $eventKey === '' || $channel === '') {
            return null;
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cooperation_announcement_templates
             WHERE tenant_id = ? AND event_key = ? AND channel = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $eventKey, $channel]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Valeurs de formulaire : entrée locale si présente, sinon défaut plateforme (tenant 0).
     *
     * @return array<string, mixed>
     */
    public function findForForm(int $tenantId, string $eventKey, string $channel): array
    {
        $own = $this->findExact($tenantId, $eventKey, $channel);
        if ($own) {
            return $own;
        }
        $plat = $this->findExact(0, $eventKey, $channel);

        return $plat ?? [
            'tenant_id' => $tenantId,
            'event_key' => $eventKey,
            'channel' => $channel,
            'subject' => null,
            'body' => '',
            'forum_settings_json' => null,
            'min_interval_hours' => 24,
            'is_active' => 0,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listForTenantScope(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT * FROM cooperation_announcement_templates WHERE tenant_id = ? ORDER BY event_key, channel'
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsert(int $tenantId, string $eventKey, string $channel, array $data): void
    {
        $subject = isset($data['subject']) ? substr(trim((string) $data['subject']), 0, 255) : null;
        $body = trim((string) ($data['body'] ?? ''));
        $forumJson = null;
        if (isset($data['forum_settings_json'])) {
            $forumJson = is_string($data['forum_settings_json'])
                ? $data['forum_settings_json']
                : json_encode($data['forum_settings_json'], JSON_UNESCAPED_UNICODE);
        }
        $minH = max(0, min(168, (int) ($data['min_interval_hours'] ?? 24)));
        $active = !empty($data['is_active']) ? 1 : 0;

        $stmt = $this->pdo->prepare(
            'INSERT INTO cooperation_announcement_templates
            (tenant_id, event_key, channel, subject, body, forum_settings_json, min_interval_hours, is_active, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body), forum_settings_json = VALUES(forum_settings_json),
            min_interval_hours = VALUES(min_interval_hours), is_active = VALUES(is_active), updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $eventKey, $channel, $subject !== '' ? $subject : null, $body, $forumJson, $minH, $active]);
    }

    public function delete(int $tenantId, string $eventKey, string $channel): void
    {
        $this->pdo->prepare(
            'DELETE FROM cooperation_announcement_templates WHERE tenant_id = ? AND event_key = ? AND channel = ? LIMIT 1'
        )->execute([$tenantId, $eventKey, $channel]);
    }
}
