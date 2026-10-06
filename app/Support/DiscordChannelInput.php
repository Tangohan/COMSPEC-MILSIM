<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Lecture de ce qu'un administrateur colle dans un champ « salon Discord ».
 *
 * Athena publie via un webhook Discord (lien https://discord.com/api/webhooks/<id>/<jeton>) :
 * il n'y a pas de bot, donc l'identifiant d'un salon ou le lien « Copier le lien du salon »
 * ne suffit pas pour écrire dedans. Cette classe reconnaît ces cas pour expliquer quoi coller.
 */
final class DiscordChannelInput
{
    public const KIND_EMPTY = 'empty';
    public const KIND_WEBHOOK = 'webhook';
    public const KIND_CHANNEL_ID = 'channel_id';
    public const KIND_CHANNEL_LINK = 'channel_link';
    public const KIND_INVITE = 'invite';
    public const KIND_WEBHOOK_INCOMPLETE = 'webhook_incomplete';
    public const KIND_OTHER = 'other';

    private const HOSTS = ['discord.com', 'discordapp.com', 'ptb.discord.com', 'canary.discord.com', 'ptb.discordapp.com', 'canary.discordapp.com'];

    /**
     * @return array{kind:string, url:string, channel_id:string, guild_id:string}
     */
    public static function analyze(string $raw): array
    {
        $out = ['kind' => self::KIND_OTHER, 'url' => '', 'channel_id' => '', 'guild_id' => ''];
        // Espaces, retours à la ligne, chevrons <…> (copie depuis un message Discord).
        $value = trim($raw);
        $value = trim($value, " \t\n\r\0\x0B<>\"'");
        if ($value === '') {
            $out['kind'] = self::KIND_EMPTY;

            return $out;
        }
        if (preg_match('/^\d{17,20}$/', $value) === 1) {
            $out['kind'] = self::KIND_CHANNEL_ID;
            $out['channel_id'] = $value;

            return $out;
        }
        // Mention de salon <#123…> déjà débarrassée des chevrons : « #123… ».
        if (preg_match('/^#(\d{17,20})$/', $value, $m) === 1) {
            $out['kind'] = self::KIND_CHANNEL_ID;
            $out['channel_id'] = $m[1];

            return $out;
        }
        if (!preg_match('#^https?://#i', $value)) {
            if (preg_match('#^(?:www\.)?(?:(?:ptb|canary)\.)?discord(?:app)?\.com/#i', $value) === 1
                || preg_match('#^discord\.gg/#i', $value) === 1) {
                $value = 'https://' . $value;
            } else {
                return $out;
            }
        }
        $parts = parse_url($value);
        if (!is_array($parts)) {
            return $out;
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        if ($host === 'discord.gg' || ($host !== '' && in_array($host, self::HOSTS, true) && str_starts_with($path, '/invite/'))) {
            $out['kind'] = self::KIND_INVITE;

            return $out;
        }
        if (!in_array($host, self::HOSTS, true)) {
            return $out;
        }
        if (preg_match('#^/channels/(\d{17,20}|@me)/(\d{17,20})(?:/\d+)?$#', $path, $m) === 1) {
            $out['kind'] = self::KIND_CHANNEL_LINK;
            $out['guild_id'] = $m[1] === '@me' ? '' : $m[1];
            $out['channel_id'] = $m[2];

            return $out;
        }
        if (preg_match('#^/api(?:/v\d+)?/webhooks/(\d{1,20})/([A-Za-z0-9_\-]{20,})$#', $path, $m) === 1) {
            if (($parts['scheme'] ?? '') !== 'https' && strtolower((string) ($parts['scheme'] ?? '')) !== 'http') {
                return $out;
            }
            $url = 'https://discord.com/api/webhooks/' . $m[1] . '/' . $m[2];
            // thread_id : publier dans un fil d'un salon forum (paramètre officiel Discord).
            parse_str((string) ($parts['query'] ?? ''), $query);
            $thread = is_array($query) ? (string) ($query['thread_id'] ?? '') : '';
            if (preg_match('/^\d{17,20}$/', $thread) === 1) {
                $url .= '?thread_id=' . $thread;
            }
            $out['kind'] = self::KIND_WEBHOOK;
            $out['url'] = $url;

            return $out;
        }
        if (str_contains($path, '/webhooks/')) {
            $out['kind'] = self::KIND_WEBHOOK_INCOMPLETE;
        }

        return $out;
    }

    /**
     * Lien webhook normalisé, ou '' si la saisie n'en est pas un.
     */
    public static function webhookUrl(string $raw): string
    {
        $a = self::analyze($raw);

        return $a['kind'] === self::KIND_WEBHOOK ? $a['url'] : '';
    }

    /**
     * Message d'erreur clair (ou null si la saisie est un lien webhook valide ou vide).
     */
    public static function problem(string $raw, string $fieldLabel): ?string
    {
        $a = self::analyze($raw);
        $how = 'Dans Discord : clic droit sur le salon → Modifier le salon → Intégrations → Webhooks → Nouveau webhook → « Copier l’URL du webhook », puis collez ce lien (il commence par https://discord.com/api/webhooks/).';

        return match ($a['kind']) {
            self::KIND_EMPTY, self::KIND_WEBHOOK => null,
            self::KIND_CHANNEL_ID => $fieldLabel . ' : vous avez collé l’identifiant du salon (' . $a['channel_id'] . '). Athena publie sans bot, il lui faut le lien webhook du salon. ' . $how,
            self::KIND_CHANNEL_LINK => $fieldLabel . ' : vous avez collé le lien du salon (salon ' . $a['channel_id'] . '). Ce lien sert à ouvrir le salon, pas à y publier. ' . $how,
            self::KIND_INVITE => $fieldLabel . ' : c’est un lien d’invitation au serveur, pas un lien de publication. ' . $how,
            self::KIND_WEBHOOK_INCOMPLETE => $fieldLabel . ' : le lien webhook semble incomplet (il manque la fin, le jeton). Recopiez-le en entier avec « Copier l’URL du webhook ».',
            default => $fieldLabel . ' : lien non reconnu. ' . $how,
        };
    }
}
