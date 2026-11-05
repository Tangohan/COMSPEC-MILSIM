<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Core\Database;
use App\Repositories\CommunityEventRepository;
use App\Repositories\EventAutomationRepository;
use App\Repositories\TenantRepository;
use App\Repositories\UserRepository;
use App\Support\DiscordEventVotes;
use App\Support\SecretCipher;
use PDO;

final class DiscordEventAttendanceService
{
    public function __construct(private ?DiscordEventClient $client = null)
    {
        $this->client ??= new DiscordEventClient();
    }

    public function botToken(int $tenantId): string
    {
        $settings = (new TenantRepository())->getSettings($tenantId);

        return SecretCipher::decrypt((string) ($settings['integrations']['event_discord_bot_token'] ?? '')) ?? '';
    }

    /** Publie les événements des trente prochains jours et importe les réactions toutes les cinq minutes. */
    public function run(): int
    {
        $repo = new EventAutomationRepository();
        $processed = 0;
        foreach ($repo->discordQueue() as $row) {
            $this->sync($row);
            $processed++;
        }

        return $processed;
    }

    /** @param array<string, mixed> $row */
    public function sync(array $row): void
    {
        $repo = new EventAutomationRepository();
        $tenant = (int) $row['tenant_id'];
        $eventId = (int) $row['event_id'];
        // Les verrouillages nommés évitent deux publications simultanées (cron / bouton).
        $pdo = Database::getPdo();
        $lockName = 'event-discord-' . $eventId;
        $lock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$lockName]);
        if (!(bool) $lock->fetchColumn()) {
            return;
        }
        try {
            $state = $repo->discord($tenant, $eventId);
            $event = (new CommunityEventRepository())->findByIdForTenant($eventId, $tenant);
            if (!$state || empty($state['enabled']) || !$event || !empty($event['cancelled_at'])) {
                return;
            }
            $row = array_merge($event, $state);
            $token = $this->botToken($tenant);
            if (empty($row['message_id'])) {
                $url = (new DiscordWebhookDirectory())->urlFor($tenant, (string) $row['destination']);
                if (!$url) {
                    throw new \RuntimeException('events.discord_missing_webhook');
                }
                $locale = (string) $row['locale'];
                $legend = '✅ ' . __('events.yes', [], $locale) . ' · ❌ ' . __('events.no', [], $locale) . ' · ❓ ' . __('events.maybe', [], $locale);
                $text = '**' . mb_substr((string) $row['title'], 0, 190) . "**\n" . (string) $row['starts_at'] . "\n";
                $text .= mb_substr((string) ($row['location'] ?? ''), 0, 190) . "\n";
                if (!empty($row['rsvp_enabled'])) {
                    $text .= $legend . "\n" . __('events.discord_vote_hint', [], $locale) . "\n" . __('events.discord_link', [], $locale) . ': ' . url('discord/roles-lies') . "\n";
                }
                $text .= url('evenements') . '#event-' . $eventId;
                $message = $this->client->webhook($url, ['content' => mb_substr($text, 0, 2000), 'allowed_mentions' => ['parse' => []]]);
                if (!preg_match('/^\d+$/D', (string) ($message['id'] ?? '')) || !preg_match('/^\d+$/D', (string) ($message['channel_id'] ?? ''))) {
                    throw new \RuntimeException('events.discord_failed');
                }
                $repo->published($tenant, $eventId, (string) $message['channel_id'], (string) $message['id']);
                $row['message_id'] = $message['id'];
                $row['channel_id'] = $message['channel_id'];
            }
            if (empty($row['rsvp_enabled'])) {
                $repo->recordSync($tenant, $eventId);
                return;
            }
            if ($token === '') {
                throw new \RuntimeException('events.discord_missing_bot');
            }
            if (empty($row['reactions_seeded'])) {
                foreach (DiscordEventClient::EMOJIS as $emoji) {
                    $this->client->bot('PUT', '/channels/' . $row['channel_id'] . '/messages/' . $row['message_id'] . '/reactions/' . rawurlencode($emoji) . '/@me', $token);
                }
                $repo->recordSync($tenant, $eventId, '', true);
            }
            $reactions = [];
            foreach (DiscordEventClient::EMOJIS as $status => $emoji) {
                $reactions[$status] = $this->client->reactionUsers((string) $row['channel_id'], (string) $row['message_id'], $emoji, $token);
            }
            $this->applyVotes($tenant, $eventId, DiscordEventVotes::resolve($reactions));
            $repo->recordSync($tenant, $eventId, '', true);
        } catch (\Throwable $e) {
            $key = str_starts_with($e->getMessage(), 'events.discord_') ? $e->getMessage() : 'events.discord_failed';
            $repo->recordSync($tenant, $eventId, $key);
        } finally {
            $pdo->prepare('SELECT RELEASE_LOCK(?)')->execute([$lockName]);
        }
    }

    /** @param array<string, list<string>> $reactions */
    public function importReactions(int $tenant, int $eventId, array $reactions): void
    {
        $this->applyVotes($tenant, $eventId, DiscordEventVotes::resolve($reactions));
    }

    /** @param array<string, string> $votes */
    private function applyVotes(int $tenant, int $eventId, array $votes): void
    {
        $pdo = Database::getPdo();
        $users = new UserRepository($pdo);
        // Seuls les liens OAuth vérifiés et les membres actifs de la communauté sont importés.
        $memberPredicate = $users->sqlMemberOfTenantPredicate('u', $tenant);
        $links = $pdo->query('SELECT l.user_id, l.discord_user_id FROM user_discord_links l JOIN users u ON u.id = l.user_id WHERE ' . $memberPredicate)->fetchAll(PDO::FETCH_ASSOC);
        $events = new CommunityEventRepository();
        foreach ($links as $link) {
            $userId = (int) $link['user_id'];
            $status = $votes['discord:' . (string) $link['discord_user_id']] ?? '';
            if ($status === 'conflict') {
                continue;
            }
            $pdo->beginTransaction();
            try {
                $eventLock = $pdo->prepare('SELECT id FROM community_events WHERE id = ? AND tenant_id = ? AND cancelled_at IS NULL FOR UPDATE');
                $eventLock->execute([$eventId, $tenant]);
                if (!$eventLock->fetchColumn()) {
                    $pdo->commit();
                    break;
                }
                $rsvpLock = $pdo->prepare('SELECT * FROM community_event_rsvps WHERE event_id = ? AND user_id = ? FOR UPDATE');
                $rsvpLock->execute([$eventId, $userId]);
                $before = $rsvpLock->fetch(PDO::FETCH_ASSOC);
                $st = $pdo->prepare('SELECT * FROM community_event_discord_votes WHERE event_id = ? AND user_id = ? FOR UPDATE');
                $st->execute([$eventId, $userId]);
                $previous = $st->fetch(PDO::FETCH_ASSOC);
                // Une réaction inchangée ne doit jamais écraser une correction faite sur le site.
                if (($previous['reaction_status'] ?? '') === $status) {
                    $pdo->commit();
                    continue;
                }
                if ($status !== '') {
                    $events->setRsvp($eventId, $userId, $status, null, null, $userId);
                    // Une réponse Discord ne modifie jamais le pointage effectif.
                    if (!empty($before['checked_in_at'])) {
                        $pdo->prepare('UPDATE community_event_rsvps SET checked_in_at = ? WHERE event_id = ? AND user_id = ?')->execute([$before['checked_in_at'], $eventId, $userId]);
                    }
                } elseif ($previous) {
                    $current = $events->getRsvp($eventId, $userId);
                    if (DiscordEventVotes::canClear($current, $previous)) {
                        $events->deleteRsvp($eventId, $userId, $userId);
                    }
                }
                $current = $events->getRsvp($eventId, $userId);
                $pdo->prepare('INSERT INTO community_event_discord_votes (event_id, user_id, reaction_status, rsvp_fingerprint) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE reaction_status = VALUES(reaction_status), rsvp_fingerprint = VALUES(rsvp_fingerprint)')
                    ->execute([$eventId, $userId, $status, DiscordEventVotes::fingerprint($current)]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    }
}
