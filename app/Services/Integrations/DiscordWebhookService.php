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
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        $path = (string) ($parts['path'] ?? '');
        if (!str_contains($path, '/api/webhooks/')) {
            return false;
        }

        return $host === 'discord.com' || $host === 'discordapp.com';
    }

    /** @return array{ok:bool, error?:string} */
    public function send(string $webhookUrl, string $content, ?string $username = null, int $timeoutSeconds = self::TIMEOUT_SECONDS): array
    {
        $payload = [];
        $content = trim($content);
        if ($content !== '') {
            $payload['content'] = mb_substr($content, 0, 2000);
        }
        $username = trim((string) $username);
        if ($username !== '') {
            $payload['username'] = mb_substr($username, 0, 80);
        }

        return $this->post($webhookUrl, $payload, $timeoutSeconds);
    }

    /**
     * Message riche Discord (embed) — idéal pour changelog / mise à jour de pack.
     *
     * @param array{
     *   title?:string,
     *   description?:string,
     *   url?:string,
     *   color?:int,
     *   fields?:list<array{name:string, value:string, inline?:bool}>,
     *   footer?:array{text:string},
     *   author?:array{name:string, url?:string},
     *   timestamp?:string
     * } $embed
     * @return array{ok:bool, error?:string}
     */
    public function sendEmbed(
        string $webhookUrl,
        array $embed,
        ?string $content = null,
        ?string $username = null,
        int $timeoutSeconds = self::TIMEOUT_SECONDS
    ): array {
        $payload = [];
        $content = trim((string) $content);
        if ($content !== '') {
            $payload['content'] = mb_substr($content, 0, 2000);
        }
        $username = trim((string) $username);
        if ($username !== '') {
            $payload['username'] = mb_substr($username, 0, 80);
        }

        $clean = [];
        $title = trim((string) ($embed['title'] ?? ''));
        if ($title !== '') {
            $clean['title'] = mb_substr($title, 0, 256);
        }
        $description = trim((string) ($embed['description'] ?? ''));
        if ($description !== '') {
            $clean['description'] = mb_substr($description, 0, 4096);
        }
        $url = trim((string) ($embed['url'] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $clean['url'] = $url;
        }
        if (isset($embed['color']) && is_int($embed['color'])) {
            $clean['color'] = max(0, min(0xFFFFFF, $embed['color']));
        }
        if (!empty($embed['fields']) && is_array($embed['fields'])) {
            $fields = [];
            foreach (array_slice($embed['fields'], 0, 25) as $field) {
                if (!is_array($field)) {
                    continue;
                }
                $name = trim((string) ($field['name'] ?? ''));
                $value = trim((string) ($field['value'] ?? ''));
                if ($name === '' || $value === '') {
                    continue;
                }
                $fields[] = [
                    'name' => mb_substr($name, 0, 256),
                    'value' => mb_substr($value, 0, 1024),
                    'inline' => !empty($field['inline']),
                ];
            }
            if ($fields !== []) {
                $clean['fields'] = $fields;
            }
        }
        if (!empty($embed['footer']) && is_array($embed['footer'])) {
            $footerText = trim((string) ($embed['footer']['text'] ?? ''));
            if ($footerText !== '') {
                $clean['footer'] = ['text' => mb_substr($footerText, 0, 2048)];
            }
        }
        if (!empty($embed['author']) && is_array($embed['author'])) {
            $authorName = trim((string) ($embed['author']['name'] ?? ''));
            if ($authorName !== '') {
                $author = ['name' => mb_substr($authorName, 0, 256)];
                $authorUrl = trim((string) ($embed['author']['url'] ?? ''));
                if ($authorUrl !== '' && filter_var($authorUrl, FILTER_VALIDATE_URL)) {
                    $author['url'] = $authorUrl;
                }
                $clean['author'] = $author;
            }
        }
        $timestamp = trim((string) ($embed['timestamp'] ?? ''));
        if ($timestamp !== '') {
            $clean['timestamp'] = $timestamp;
        }

        if ($clean === [] && ($payload['content'] ?? '') === '') {
            return ['ok' => false, 'error' => 'Message vide.'];
        }
        if ($clean !== []) {
            $payload['embeds'] = [$clean];
        }

        return $this->post($webhookUrl, $payload, $timeoutSeconds);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool, error?:string}
     */
    private function post(string $webhookUrl, array $payload, int $timeoutSeconds = self::TIMEOUT_SECONDS): array
    {
        if (!$this->isValidWebhookUrl($webhookUrl)) {
            return ['ok' => false, 'error' => 'Lien Discord invalide. Vérifiez le relais configuré dans les réglages de la communauté.'];
        }
        if ($payload === []) {
            return ['ok' => false, 'error' => 'Message vide.'];
        }

        $timeout = max(2, min(12, $timeoutSeconds));
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(4, $timeout),
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Discord injoignable pour le moment.'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'Discord a refusé le message (code ' . $status . '). Vérifiez que le relais existe toujours dans les paramètres du salon.'];
        }

        return ['ok' => true];
    }
}
