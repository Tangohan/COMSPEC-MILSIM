<?php

declare(strict_types=1);

namespace App\Services\Integrations;

/**
 * Relais sortant vers un webhook Discord classique (pas de bot, pas d'OAuth) — permet à une
 * communauté de recevoir automatiquement ses annonces Athena dans un salon Discord.
 */
final class DiscordWebhookService
{
    private const TIMEOUT_SECONDS = 8;

    public function isValidWebhookUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https') {
            return false;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));

        return $host === 'discord.com' || $host === 'discordapp.com';
    }

    /** @return array{ok:bool, error?:string} */
    public function send(string $webhookUrl, string $content, ?string $username = null): array
    {
        if (!$this->isValidWebhookUrl($webhookUrl)) {
            return ['ok' => false, 'error' => 'URL de webhook Discord invalide (doit commencer par https://discord.com/api/webhooks/...).'];
        }
        $content = trim($content);
        if ($content === '') {
            return ['ok' => false, 'error' => 'Message vide.'];
        }
        $payload = ['content' => mb_substr($content, 0, 2000)];
        $username = trim((string) $username);
        if ($username !== '') {
            $payload['username'] = mb_substr($username, 0, 80);
        }

        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Discord injoignable pour le moment.'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'Discord a refusé le message (code ' . $status . '). Vérifiez que le webhook existe toujours dans les paramètres du salon.'];
        }

        return ['ok' => true];
    }
}
