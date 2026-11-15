<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DiscordBotRepository;
use App\Repositories\DiscordCompanionRepository;
use App\Repositories\EventAutomationRepository;
use App\Services\Integrations\DiscordBotBureau;
use App\Services\Integrations\DiscordCompanionSettings;
use App\Services\Integrations\DiscordEventAttendanceService;
use App\Services\Integrations\DiscordWebhookDirectory;

/** API privée du processus Companion, distincte des clés API des communautés. */
final class DiscordCompanionController
{
    public function __construct(private ?DiscordCompanionSettings $settings = null, private ?DiscordBotBureau $bureau = null)
    {
    }

    public function handle(Request $request, array $params = []): Response
    {
        $header = (string) ($request->header('Authorization') ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? ''));
        $this->settings ??= new DiscordCompanionSettings();
        if (!$this->settings->accepts($header)) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $raw = $request->inputString('payload', '{}');
        if (strlen($raw) > 1000000) return Response::json(['error' => 'payload_too_large'], 413);
        try {
            $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new \RuntimeException('invalid_payload');
            $op = $request->inputString('op');
            if (in_array($op, ['cmd', 'reminders', 'reminder_ack', 'roles_plan', 'welcome', 'guild_removed'], true)) {
                return $this->bureau($op, $this->snowflake($data['guild_id'] ?? ''), $data);
            }
            $repo = new DiscordCompanionRepository();
            $events = new EventAutomationRepository();
            if ($op === 'jobs') return Response::json(['jobs' => $repo->jobs()]);
            $guild = $this->snowflake($data['guild_id'] ?? '');
            if ($op === 'pair' || $op === 'channels') {
                $channels = [];
                foreach (array_slice((array) ($data['channels'] ?? []), 0, 500, true) as $id => $name) {
                    $channels[$this->snowflake($id)] = DiscordWebhookDirectory::cleanChannel((string) $name);
                }
                if ($op === 'pair') {
                    $channel = $this->snowflake($data['channel_id'] ?? '');
                    if (!isset($channels[$channel])) throw new \RuntimeException('invalid_channel');
                    $repo->pair((string) ($data['code'] ?? ''), $guild, (string) ($data['guild_name'] ?? ''), $channel, $channels);
                } else {
                    $repo->updateChannels($guild, (string) ($data['guild_name'] ?? ''), $channels);
                }
                $this->markSeen($guild, $data);
                return Response::json(['ok' => true]);
            }
            $eventId = (int) ($data['event_id'] ?? 0);
            $event = $repo->eventForGuild($eventId, $guild);
            if (!$event) return Response::json(['error' => 'not_found'], 404);
            if ($op === 'failed') {
                $events->recordSync((int) $event['tenant_id'], $eventId, 'events.discord_failed');
                return Response::json(['ok' => true]);
            }
            $message = $this->snowflake($data['message_id'] ?? '');
            $channel = $this->snowflake($data['channel_id'] ?? '');
            if ($op === 'published') {
                if ($channel !== (string) ($event['channel_id'] ?: $event['default_channel_id']) || (!empty($event['message_id']) && $event['message_id'] !== $message)) {
                    throw new \RuntimeException('message_mismatch');
                }
                $events->published((int) $event['tenant_id'], $eventId, $channel, $message);
                $events->recordSync((int) $event['tenant_id'], $eventId);
                return Response::json(['ok' => true]);
            }
            if ($op !== 'reactions' || $event['message_id'] !== $message || $event['channel_id'] !== $channel) throw new \RuntimeException('message_mismatch');
            if (empty($event['enabled']) || empty($event['rsvp_enabled']) || !empty($event['cancelled_at']) || (string) ($event['ends_at'] ?: $event['starts_at']) < date('Y-m-d H:i:s')) return Response::json(['ok' => true, 'skipped' => true]);
            $reactions = [];
            foreach (['yes', 'no', 'maybe'] as $status) {
                $ids = (array) ($data['reactions'][$status] ?? []);
                if (count($ids) > 10000) throw new \RuntimeException('too_many_reactions');
                $reactions[$status] = array_map($this->snowflake(...), $ids);
            }
            (new DiscordEventAttendanceService())->importReactions((int) $event['tenant_id'], $eventId, $reactions);
            $events->recordSync((int) $event['tenant_id'], $eventId);
            return Response::json(['ok' => true]);
        } catch (\Throwable $e) {
            $allowed = ['companion.invalid_code', 'companion.already_bound', 'invalid_payload', 'invalid_channel', 'invalid_id', 'message_mismatch', 'too_many_reactions'];
            return Response::json(['error' => in_array($e->getMessage(), $allowed, true) ? $e->getMessage() : 'companion_failed'], 422);
        }
    }

    /** Le bot signale sa présence (et les rôles que la communauté peut désigner) : état « connecté » côté site. @param array<string, mixed> $data */
    private function markSeen(string $guild, array $data): void
    {
        try {
            $roles = null;
            if (isset($data['roles']) && is_array($data['roles'])) {
                $roles = [];
                foreach (array_slice($data['roles'], 0, 250, true) as $id => $name) {
                    $roles[$this->snowflake($id)] = DiscordWebhookDirectory::cleanChannel((string) $name);
                }
            }
            (new DiscordBotRepository())->markSeen($guild, $roles);
        } catch (\Throwable) {
            // Migration du bureau pas encore passée : le bot garde son fonctionnement « événements ».
        }
    }

    /**
     * Bureau des effectifs : commandes lancées sur Discord, rappels, rôles. La communauté vient du serveur lié, jamais de la saisie.
     *
     * @param array<string, mixed> $data
     */
    private function bureau(string $op, string $guild, array $data): Response
    {
        $bureau = $this->bureau ??= new DiscordBotBureau();
        try {
            return match ($op) {
                'cmd' => Response::json($bureau->command(
                    (string) ($data['command'] ?? ''),
                    $guild,
                    $this->snowflake($data['user_id'] ?? ''),
                    ['query' => (string) ($data['query'] ?? '')]
                )),
                'reminders' => Response::json(['reminders' => $bureau->reminders($guild)]),
                'reminder_ack' => Response::json(['ok' => $bureau->acknowledge($guild, (string) ($data['kind'] ?? ''), (string) ($data['ref'] ?? ''))]),
                'roles_plan' => Response::json($bureau->rolesPlan($guild)),
                'welcome' => Response::json($bureau->welcome($guild)),
                default => $this->guildRemoved($bureau, $guild),
            };
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'invalid_id') {
                throw $e;
            }

            return Response::json(['ok' => false, 'error' => 'bureau_unavailable']);
        }
    }

    private function guildRemoved(DiscordBotBureau $bureau, string $guild): Response
    {
        $bureau->guildRemoved($guild);

        return Response::json(['ok' => true]);
    }

    private function snowflake(mixed $id): string
    {
        if (!is_scalar($id) || preg_match('/^\d{1,24}$/D', (string) $id) !== 1) throw new \RuntimeException('invalid_id');
        return (string) $id;
    }
}
