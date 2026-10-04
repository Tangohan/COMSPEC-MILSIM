<?php

declare(strict_types=1);

namespace App\Services\Atak;

use App\Services\Integrations\DiscordEventRelayService;
use App\Services\Integrations\DiscordWebhookService;
use App\Services\Security\FileRateLimiter;
use App\Support\DiscordWebhookCatalog;

/**
 * Pont téléphone ATAK → salon Discord de la communauté.
 *
 * Le lien du webhook est réglé sur Athena (Intégrations → relais Discord) et ne quitte jamais le serveur :
 * le jeu n'envoie qu'un texte court, nettoyé ici (mentions retirées, longueur bornée, débit limité).
 */
final class AtakDiscordService
{
    public const MAX_LENGTH = 500;
    public const PLAYER_COOLDOWN_SECONDS = 5;
    /** Plafond par communauté, en plus du délai par joueur. */
    public const TENANT_MAX_PER_MINUTE = 30;
    private const ALERT_DEDUP_SECONDS = 60;
    private const TIMEOUT_SECONDS = 4;

    /** Raccourcis proposés par le téléphone : préfixe ajouté au message. */
    private const KINDS = [
        'CONTACT' => 'CONTACT',
        'SITREP' => 'SITREP',
        'SUPPORT' => 'BESOIN DE SOUTIEN',
        'RTB' => 'RTB',
        'MSG' => '',
    ];

    public function __construct(
        private ?DiscordEventRelayService $relay = null,
        private ?DiscordWebhookService $discord = null,
        private ?FileRateLimiter $limiter = null,
    ) {
        $this->relay ??= new DiscordEventRelayService();
        $this->discord ??= new DiscordWebhookService();
        $this->limiter ??= new FileRateLimiter();
    }

    public function isEnabled(int $tenantId): bool
    {
        return $this->relay->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_ATAK_DISCORD_APP) !== null;
    }

    /**
     * Message de l'app Discord du téléphone.
     *
     * @return array{ok:bool, status:int, error?:string, message:string}
     */
    public function sendFromPhone(int $tenantId, string $playerKey, string $callsign, string $text, string $kind = 'MSG', string $grid = ''): array
    {
        $url = $this->relay->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_ATAK_DISCORD_APP);
        if ($url === null) {
            return [
                'ok' => false,
                'status' => 409,
                'error' => 'discord_not_configured',
                'message' => 'Aucun salon Discord n’est relié au téléphone. Un administrateur peut l’activer sur Athena (Intégrations, relais Discord).',
            ];
        }
        $clean = self::sanitizeText($text);
        $kind = strtoupper(trim($kind));
        if (!array_key_exists($kind, self::KINDS)) {
            $kind = 'MSG';
        }
        if ($clean === '' && $kind === 'MSG') {
            return ['ok' => false, 'status' => 422, 'error' => 'empty', 'message' => 'Message vide.'];
        }
        $playerKey = $playerKey !== '' ? $playerKey : 'anon';
        if ($this->limiter->tooManyAttempts('atak_discord|p|' . $tenantId . '|' . $playerKey, 1, self::PLAYER_COOLDOWN_SECONDS)) {
            return ['ok' => false, 'status' => 429, 'error' => 'rate_limited', 'message' => 'Un message toutes les 5 secondes au plus.'];
        }
        if ($this->limiter->tooManyAttempts('atak_discord|t|' . $tenantId, self::TENANT_MAX_PER_MINUTE, 60)) {
            return ['ok' => false, 'status' => 429, 'error' => 'rate_limited', 'message' => 'Salon saturé : réessayez dans une minute.'];
        }

        $cs = self::sanitizeCallsign($callsign);
        $prefix = self::KINDS[$kind];
        $lines = [];
        $head = trim($prefix . ($grid !== '' ? ' · grille ' . self::sanitizeGrid($grid) : ''));
        if ($head !== '') {
            $lines[] = '**' . $head . '**';
        }
        if ($clean !== '') {
            $lines[] = $clean;
        }
        $result = $this->post($url, implode("\n", $lines), 'COMSPEC ATAK · ' . $cs);
        if (!$result['ok']) {
            return ['ok' => false, 'status' => 502, 'error' => 'discord_failed', 'message' => $result['error'] ?? 'Discord a refusé le message.'];
        }

        return ['ok' => true, 'status' => 200, 'message' => 'Message publié sur Discord.'];
    }

    /**
     * Recopie d'une alerte tactique grave (opérateur à terre / panique) si la communauté l'a activée.
     * Ne lève jamais d'exception : appelée au fil de l'enregistrement des alertes.
     *
     * @param array<string, mixed> $tactical sortie de TacticalAlertParser::enrichChatRow
     */
    public static function mirrorTacticalAlert(int $tenantId, array $tactical): void
    {
        try {
            if ($tenantId < 2 || (string) ($tactical['kind'] ?? '') !== 'eagle_down') {
                return;
            }
            $self = new self();
            $url = $self->relay->resolveUrl($tenantId, DiscordWebhookCatalog::KEY_ATAK_TACTICAL_ALERTS);
            if ($url === null) {
                return;
            }
            $cs = self::sanitizeCallsign((string) ($tactical['call_sign'] ?? $tactical['author'] ?? ''));
            $summary = self::sanitizeText((string) ($tactical['summary'] ?? ''));
            $grid = self::sanitizeGrid((string) ($tactical['grid'] ?? ''));
            // Même alerte relayée plusieurs fois (renvois réseau) : une seule recopie par minute.
            if ($self->limiter->tooManyAttempts('atak_discord|alert|' . $tenantId . '|' . hash('sha256', $cs . '|' . $summary), 1, self::ALERT_DEDUP_SECONDS)) {
                return;
            }
            $lines = ['**OPÉRATEUR À TERRE**' . ($grid !== '' ? ' · grille ' . $grid : '')];
            if ($summary !== '') {
                $lines[] = $summary;
            }
            $self->post($url, implode("\n", $lines), 'COMSPEC ATAK · ' . $cs);
        } catch (\Throwable) {
            // Discord ne doit jamais bloquer la remontée d'une alerte.
        }
    }

    /**
     * Retire les mentions (@everyone, @here, utilisateurs, rôles, salons), les caractères de contrôle,
     * puis borne la longueur. Les @ restants sont neutralisés (espace insécable de largeur nulle).
     */
    public static function sanitizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/u', '', $text);
        $text = (string) preg_replace('/<(@[!&]?|#)\d+>/u', '', $text);
        $text = (string) preg_replace('/@(everyone|here)\b/iu', '', $text);
        $text = str_replace('@', "@\u{200B}", $text);
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);
        $text = trim($text);
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $text = rtrim(mb_substr($text, 0, self::MAX_LENGTH - 1)) . '…';
        }

        return $text;
    }

    public static function sanitizeCallsign(string $callsign): string
    {
        $cs = (string) preg_replace('/[^\p{L}\p{N} ._\-]/u', '', $callsign);
        $cs = trim((string) preg_replace('/\s+/u', ' ', $cs));
        if ($cs === '') {
            $cs = 'Opérateur';
        }

        return mb_substr($cs, 0, 32);
    }

    private static function sanitizeGrid(string $grid): string
    {
        return mb_substr((string) preg_replace('/[^0-9A-Za-z ]/', '', $grid), 0, 16);
    }

    /**
     * Envoi direct (et non DiscordWebhookService::send) pour imposer allowed_mentions vide :
     * même si un motif échappe au nettoyage, Discord ne notifie personne.
     *
     * @return array{ok:bool, error?:string}
     */
    private function post(string $url, string $content, string $username): array
    {
        if (!$this->discord->isValidWebhookUrl($url)) {
            return ['ok' => false, 'error' => 'Relais Discord mal configuré sur Athena.'];
        }
        $content = trim($content);
        if ($content === '') {
            return ['ok' => false, 'error' => 'Message vide.'];
        }
        $payload = [
            'content' => mb_substr($content, 0, 2000),
            'username' => mb_substr($username, 0, 80),
            'allowed_mentions' => ['parse' => []],
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        curl_exec($ch);
        $errno = curl_errno($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        // Pas de journal du lien : seul le code HTTP est utile au diagnostic.
        if ($errno !== 0) {
            return ['ok' => false, 'error' => 'Discord injoignable pour le moment.'];
        }
        if ($status === 429) {
            return ['ok' => false, 'error' => 'Discord limite le salon : réessayez dans un instant.'];
        }
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'Discord a refusé le message (code ' . $status . ').'];
        }

        return ['ok' => true];
    }
}
