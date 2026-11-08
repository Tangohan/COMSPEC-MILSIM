<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;
use PDO;

final class EventAutomationRepository
{
    public function __construct(private ?PDO $pdo = null)
    {
        $this->pdo ??= Database::getPdo();
        SilentSchemaMigration::run(base_path('bootstrap/event_automation_migration.php'), $this->pdo);
    }

    /** @param list<int> $days @param array<string, mixed> $template */
    public function createSeries(string $id, int $tenantId, int $author, string $frequency, array $days, array $template, string $anchor, string $cursor): void
    {
        $this->pdo->prepare('INSERT INTO community_event_series (id, tenant_id, created_by_user_id, frequency, weekdays_json, template_json, anchor_at, cursor_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$id, $tenantId, $author, $frequency, json_encode($days, JSON_THROW_ON_ERROR), json_encode($template, JSON_THROW_ON_ERROR), $anchor, $cursor]);
    }

    /** @return list<array<string, mixed>> */
    public function activeSeries(): array
    {
        return $this->pdo->query('SELECT * FROM community_event_series WHERE active = 1 ORDER BY checked_at IS NOT NULL, checked_at ASC, id ASC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function stopSeries(int $tenantId, string $id): void
    {
        $this->pdo->prepare('UPDATE community_event_series SET active = 0 WHERE tenant_id = ? AND id = ?')->execute([$tenantId, $id]);
    }

    public function isEndless(int $tenantId, string $id): bool
    {
        $st = $this->pdo->prepare('SELECT 1 FROM community_event_series WHERE tenant_id = ? AND id = ? AND active = 1');
        $st->execute([$tenantId, $id]);

        return (bool) $st->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function discord(int $tenantId, int $eventId): ?array
    {
        $st = $this->pdo->prepare('SELECT * FROM community_event_discord WHERE tenant_id = ? AND event_id = ?');
        $st->execute([$tenantId, $eventId]);

        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function configureDiscord(int $tenantId, int $eventId, string $destination, bool $rsvp, string $locale): void
    {
        $this->pdo->prepare('INSERT INTO community_event_discord (tenant_id, event_id, destination, rsvp_enabled, locale) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE destination = VALUES(destination), rsvp_enabled = VALUES(rsvp_enabled), locale = VALUES(locale), enabled = 1')
            ->execute([$tenantId, $eventId, $destination, $rsvp ? 1 : 0, $locale === 'en' ? 'en' : 'fr']);
    }

    public function disableDiscord(int $tenantId, int $eventId): void
    {
        $this->pdo->prepare('UPDATE community_event_discord SET enabled = 0 WHERE tenant_id = ? AND event_id = ?')->execute([$tenantId, $eventId]);
    }

    /** @return list<array<string, mixed>> */
    public function discordQueue(): array
    {
        return $this->pdo->query("SELECT d.*, e.title, e.description, e.location, e.starts_at, e.ends_at, e.cancelled_at
            FROM community_event_discord d JOIN community_events e ON e.id = d.event_id AND e.tenant_id = d.tenant_id
            WHERE d.destination <> 'companion' AND d.enabled = 1 AND e.cancelled_at IS NULL AND COALESCE(e.ends_at, e.starts_at) >= NOW()
              AND e.starts_at <= DATE_ADD(NOW(), INTERVAL 30 DAY)
            ORDER BY d.synced_at IS NOT NULL, d.synced_at ASC, e.starts_at ASC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function published(int $tenantId, int $eventId, string $channel, string $message): void
    {
        $this->pdo->prepare('UPDATE community_event_discord SET channel_id = ?, message_id = ?, last_error = ? WHERE tenant_id = ? AND event_id = ?')
            ->execute([$channel, $message, '', $tenantId, $eventId]);
    }

    public function recordSync(int $tenantId, int $eventId, string $error = '', bool $seeded = false): void
    {
        $this->pdo->prepare('UPDATE community_event_discord SET synced_at = NOW(), last_error = ?, reactions_seeded = GREATEST(reactions_seeded, ?) WHERE tenant_id = ? AND event_id = ?')
            ->execute([$error, $seeded ? 1 : 0, $tenantId, $eventId]);
    }
}
