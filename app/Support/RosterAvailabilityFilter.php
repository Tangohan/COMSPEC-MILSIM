<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Filtre « À vérifier » du tableur des effectifs (paramètre sessions=verifierN).
 *
 * Garde un membre quand le suivi des sessions est actif pour lui (ligne server_sessions présente), qu'il n'a aucune
 * session qui compte depuis N jours (ou pas de Steam relié) ET qu'aucune vérification de disponibilité n'a été
 * enregistrée pour lui depuis N jours. Sans le suivi des sessions sur le site, le filtre ne garde personne.
 */
final class RosterAvailabilityFilter
{
    /** @var list<int> délais proposés, en jours */
    public const DAYS = [7, 14, 30, 60, 90];

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (int $d): string => 'verifier' . $d, self::DAYS);
    }

    /** Nombre de jours d'une valeur du filtre, 0 si ce n'est pas le filtre « À vérifier ». */
    public static function days(string $value): int
    {
        if (preg_match('/^verifier(\d+)$/', $value, $m) !== 1) {
            return 0;
        }

        return in_array((int) $m[1], self::DAYS, true) ? (int) $m[1] : 0;
    }

    /**
     * @param list<array<string, mixed>> $rows lignes du tableur avec server_sessions (PlayerSessionService::rosterCell)
     * @param array<int, array{created_at: string}> $latestChecks dernière vérification par membre
     * @return list<array<string, mixed>>
     */
    public static function apply(array $rows, array $latestChecks, int $days, int $now): array
    {
        $since = $now - max(1, $days) * 86400;

        return array_values(array_filter($rows, static function (array $r) use ($latestChecks, $since): bool {
            $srv = $r['server_sessions'] ?? null;
            if (!is_array($srv)) {
                return false;
            }
            $last = isset($srv['last_at']) ? (int) $srv['last_at'] : 0;
            if (!empty($srv['linked']) && $last >= $since) {
                return false;
            }
            $checked = strtotime((string) ($latestChecks[(int) ($r['id'] ?? 0)]['created_at'] ?? ''));

            return $checked === false || $checked < $since;
        }));
    }
}
