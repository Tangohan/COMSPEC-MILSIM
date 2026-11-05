<?php

declare(strict_types=1);

namespace App\Support;

final class DiscordEventVotes
{
    /** @param array<string, mixed>|null $rsvp */
    public static function fingerprint(?array $rsvp): ?string
    {
        if ($rsvp === null) {
            return null;
        }
        ksort($rsvp);

        return hash('sha256', json_encode($rsvp, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed>|null $rsvp @param array<string, mixed> $previous */
    public static function canClear(?array $rsvp, array $previous): bool
    {
        return $rsvp !== null && empty($rsvp['checked_in_at'])
            && $rsvp['status'] === $previous['reaction_status']
            && self::fingerprint($rsvp) === $previous['rsvp_fingerprint'];
    }

    /** Plusieurs réactions d’un membre = conflit, ne pas inventer de priorité. @param array<string, list<string>> $reactions @return array<string, string> */
    public static function resolve(array $reactions): array
    {
        $votes = [];
        foreach (['yes', 'no', 'maybe'] as $status) {
            foreach (array_unique($reactions[$status] ?? []) as $id) {
                $key = 'discord:' . $id;
                $votes[$key] = isset($votes[$key]) ? 'conflict' : $status;
            }
        }

        return $votes;
    }
}
