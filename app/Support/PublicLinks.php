<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Liens externes officiels affichés sur le site public (une seule source).
 */
final class PublicLinks
{
    public const DISCORD_INVITE_URL = 'https://discord.gg/KFB2TsJVXn';

    /**
     * Invitation Discord COMSPEC. Surchargeable par PUBLIC_DISCORD_INVITE_URL, seulement si
     * la valeur est une adresse https de discord.gg ou discord.com.
     */
    public static function discordInviteUrl(): string
    {
        $raw = trim((string) (($_ENV['PUBLIC_DISCORD_INVITE_URL'] ?? null) ?: (getenv('PUBLIC_DISCORD_INVITE_URL') ?: '')));
        if ($raw !== '' && preg_match('~^https://(discord\.gg|discord\.com)/[A-Za-z0-9/_-]+$~', $raw) === 1) {
            return $raw;
        }

        return self::DISCORD_INVITE_URL;
    }
}
