<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Super ping : signal bref (pulse de 7 s en jeu et sur le web), retiré seul du tableau des pings.
 * La marge couvre les sondages lents (4 s sur le poste) pour que chaque poste ait le temps de le voir passer.
 */
final class AtakSuperPing
{
    public const TTL_SECONDS = 15;

    /** Étiquette en tête du message, comme « [Super ping] » envoyé par le poste et par le jeu. */
    public static function isSuper(?string $message): bool
    {
        return preg_match('/^\s*\[[^\]]*super[^\]]*\]/iu', (string) $message) === 1;
    }

    /**
     * Sépare les pings encore valables et les identifiants des super pings arrivés au terme.
     * Chaque ligne porte `age_s`, calculé par la base (même horloge que `created_at`) ; il est retiré de la réponse.
     *
     * @param list<array<string, mixed>> $rows
     * @return array{rows: list<array<string, mixed>>, expired: list<int>}
     */
    public static function partition(array $rows): array
    {
        $kept = [];
        $expired = [];
        foreach ($rows as $row) {
            $age = isset($row['age_s']) ? (int) $row['age_s'] : 0;
            unset($row['age_s']);
            if ($age >= self::TTL_SECONDS && self::isSuper(isset($row['message']) ? (string) $row['message'] : null)) {
                $expired[] = (int) ($row['id'] ?? 0);
                continue;
            }
            $kept[] = $row;
        }

        return ['rows' => $kept, 'expired' => $expired];
    }
}
