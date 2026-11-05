<?php

declare(strict_types=1);

namespace App\Services\Integrations;

/** REST Discord uniquement : aucun jeton n’est renvoyé au navigateur. */
class DiscordEventClient
{
    /** @var array<string, string> */
    public const EMOJIS = ['yes' => '✅', 'no' => '❌', 'maybe' => '❓'];

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function webhook(string $url, array $payload): array
    {
        if (preg_match('#^https://(?:discord(?:app)?\.com|ptb\.discord\.com|canary\.discord\.com)/api(?:/v\d+)?/webhooks/(\d+)/([A-Za-z0-9_-]+)(?:\?.*)?$#D', $url, $m) !== 1) {
            throw new \RuntimeException('events.discord_invalid_webhook');
        }

        return $this->request('POST', '/webhooks/' . $m[1] . '/' . $m[2] . '?wait=true', '', $payload);
    }

    /** @return array<string, mixed> */
    public function bot(string $method, string $path, string $token): array
    {
        return $this->request($method, $path, $token);
    }

    /** @return list<string> */
    public function reactionUsers(string $channel, string $message, string $emoji, string $token): array
    {
        $ids = [];
        $after = '0';
        for ($page = 0; $page < 100; $page++) {
            $users = $this->bot('GET', '/channels/' . $channel . '/messages/' . $message . '/reactions/' . rawurlencode($emoji) . '?limit=100&after=' . $after, $token);
            foreach ($users as $user) {
                if (is_array($user) && empty($user['bot']) && isset($user['id'])) {
                    $ids[] = (string) $user['id'];
                }
            }
            if (count($users) < 100) {
                return $ids;
            }
            $last = end($users);
            $after = is_array($last) ? (string) ($last['id'] ?? '') : '';
            if (!preg_match('/^\d+$/D', $after)) {
                break;
            }
        }
        // Jamais importer une liste tronquée comme si des réactions avaient été retirées.
        throw new \RuntimeException('events.discord_incomplete');
    }

    /** @param array<string, mixed>|null $payload @return array<string, mixed> */
    protected function request(string $method, string $path, string $token, ?array $payload = null): array
    {
        $headers = ['Content-Type: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bot ' . $token;
        }
        $ch = curl_init('https://discord.com/api/v10' . $path);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        }
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException($status === 429 ? 'events.discord_rate_limited' : 'events.discord_failed');
        }
        if ($status === 204) {
            return [];
        }
        $data = json_decode((string) $body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('events.discord_failed');
        }

        return $data;
    }
}
