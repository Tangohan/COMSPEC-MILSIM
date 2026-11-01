<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Message Discord d'un lien « compléter son profil » : texte tiré du modèle de la communauté (variables entre
 * accolades), mention de la personne si son identifiant Discord est connu, carte (embed) avec communauté, grade,
 * unité, lien et expiration. Logique pure : l'envoi se fait côté serveur (MemberProfileInviteSender).
 */
final class ProfileInviteDiscordMessage
{
    public const DEFAULT_TEMPLATE = "Bienvenue {indicatif} ! Ton profil {communaute} t’attend : indicatif, Discord, Steam, portrait et sécurité du compte, en quelques minutes.\nTon lien personnel, valable jusqu’au {expire} : {lien}";

    public const MAX_TEMPLATE = 1200;

    private const COLOR = 0x34D399;

    /** @return array<string, string> variable => explication */
    public static function variables(): array
    {
        return [
            'indicatif' => 'Indicatif du membre (ou son nom s’il n’en a pas encore)',
            'lien' => 'Son lien personnel',
            'expire' => 'Date et heure d’expiration du lien',
            'communaute' => 'Nom de la communauté',
            'unite' => 'Unité (avec son code)',
            'grade' => 'Grade',
        ];
    }

    public static function normalizeTemplate(?string $raw): string
    {
        $raw = trim(str_replace("\r\n", "\n", (string) $raw));
        if ($raw === '') {
            return self::DEFAULT_TEMPLATE;
        }

        return mb_substr($raw, 0, self::MAX_TEMPLATE);
    }

    /**
     * Remplace les variables. Les mentions de masse et les mentions écrites à la main sont neutralisées :
     * seule la mention du destinataire, ajoutée par payload(), peut notifier quelqu'un.
     *
     * @param array<string, string> $vars
     */
    public static function render(string $template, array $vars): string
    {
        $map = [];
        foreach (array_keys(self::variables()) as $key) {
            $map['{' . $key . '}'] = self::neutralize((string) ($vars[$key] ?? ''));
        }
        $text = strtr(self::neutralize(self::normalizeTemplate($template)), $map);

        return mb_substr($text, 0, 1800);
    }

    private static function neutralize(string $text): string
    {
        // @everyone / @here, <@123>, <@&role>, <#salon> : rendus inoffensifs (espace insécable invisible).
        $text = preg_replace('/@(everyone|here)/iu', "@\u{200B}$1", $text) ?? $text;

        return preg_replace('/<([@#][!&]?\d+)>/', "<\u{200B}$1>", $text) ?? $text;
    }

    /** Identifiant Discord numérique (liaison du compte, ou saisi par le gestionnaire), sinon null. */
    public static function discordUserId(?string ...$candidates): ?string
    {
        foreach ($candidates as $c) {
            $c = trim((string) $c, " \t\n\r\0\x0B<@!>");
            if (preg_match('/^\d{17,20}$/', $c) === 1) {
                return $c;
            }
        }

        return null;
    }

    /**
     * @param array{template?:string, callsign?:string, name?:string, link:string, expires_unix:int, expires_label:string,
     *   community?:string, unit?:string, grade?:string, discord_user_id?:?string, discord_handle?:string} $ctx
     * @return array<string, mixed> corps du message webhook
     */
    public static function payload(array $ctx): array
    {
        $who = trim((string) ($ctx['callsign'] ?? '')) !== '' ? trim((string) $ctx['callsign']) : trim((string) ($ctx['name'] ?? ''));
        $community = trim((string) ($ctx['community'] ?? '')) !== '' ? trim((string) $ctx['community']) : 'la communauté';
        $unit = trim((string) ($ctx['unit'] ?? ''));
        $grade = trim((string) ($ctx['grade'] ?? ''));
        $link = (string) $ctx['link'];
        $text = self::render((string) ($ctx['template'] ?? ''), [
            'indicatif' => $who,
            'lien' => $link,
            'expire' => (string) $ctx['expires_label'],
            'communaute' => $community,
            'unite' => $unit,
            'grade' => $grade,
        ]);
        $userId = self::discordUserId($ctx['discord_user_id'] ?? null);
        $handle = trim(ltrim((string) ($ctx['discord_handle'] ?? ''), '@'));
        if ($userId !== null) {
            $lead = '<@' . $userId . '>';
        } elseif ($handle !== '') {
            $lead = '**@' . self::neutralize(mb_substr($handle, 0, 40)) . '**';
        } else {
            $lead = '';
        }
        $fields = [];
        if ($grade !== '') {
            $fields[] = ['name' => 'Grade', 'value' => mb_substr(self::neutralize($grade), 0, 200), 'inline' => true];
        }
        if ($unit !== '') {
            $fields[] = ['name' => 'Unité', 'value' => mb_substr(self::neutralize($unit), 0, 200), 'inline' => true];
        }
        $unix = (int) $ctx['expires_unix'];
        $fields[] = ['name' => 'Expire', 'value' => '<t:' . $unix . ':f> (<t:' . $unix . ':R>)', 'inline' => true];
        $fields[] = ['name' => 'Votre lien', 'value' => '[Compléter mon profil](' . $link . ')', 'inline' => false];

        return [
            'content' => mb_substr(trim($lead . ($lead !== '' ? ' ' : '') . $text), 0, 2000),
            'username' => 'Athena',
            'embeds' => [[
                'title' => mb_substr('Compléter mon profil — ' . $community, 0, 250),
                'url' => $link,
                'description' => 'Créez ou activez votre compte, puis reliez Discord et Steam, ajoutez votre portrait et protégez le compte.',
                'color' => self::COLOR,
                'fields' => $fields,
                'footer' => ['text' => 'Lien personnel : ne le transférez pas.'],
            ]],
            'allowed_mentions' => $userId !== null ? ['parse' => [], 'users' => [$userId]] : ['parse' => []],
        ];
    }
}
