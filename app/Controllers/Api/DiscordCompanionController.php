<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Repositories\DiscordCompanionRepository;
use App\Repositories\EventAutomationRepository;
use App\Services\Integrations\DiscordCompanionSettings;
use App\Services\Integrations\DiscordEventAttendanceService;
use App\Services\Integrations\DiscordWebhookDirectory;

/** API privée du processus Companion, distincte des clés API des communautés. */
final class DiscordCompanionController
{
    public function __construct(private ?DiscordCompanionSettings $settings = null)
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

    private function snowflake(mixed $id): string
    {
        if (!is_scalar($id) || preg_match('/^\d{1,24}$/D', (string) $id) !== 1) throw new \RuntimeException('invalid_id');
        return (string) $id;
    }
}
