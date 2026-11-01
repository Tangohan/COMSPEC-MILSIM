<?php

declare(strict_types=1);

namespace App\Services\Effectifs;

/**
 * Rang d’ancienneté « membre N / M » dans une communauté.
 *
 * Critère : date d’engagement réelle = la plus ancienne entre l’arrivée dans la communauté
 * (date d’engagement de la fiche, ou ancienneté communauté enregistrée) et l’arrivée avant
 * l’ouverture du site. C’est la date qui fonde l’« ancienneté réelle » affichée sur la fiche.
 *
 * Classement des membres actifs de la communauté, du plus ancien (1) au plus récent (M).
 * À date égale, le plus petit numéro de compte passe devant (ordre stable). Les membres sans
 * date d’engagement sont comptés dans M mais classés après ; ils n’ont pas de rang affiché.
 */
final class MemberSeniorityRank
{
    /**
     * @param list<int> $activeMemberIds membres actifs de la communauté
     * @param array<int, string> $engagementByUser date d’engagement réelle (YYYY-MM-DD) par membre
     * @return ?array{rank:int,total:int}
     */
    public static function rank(int $userId, array $activeMemberIds, array $engagementByUser): ?array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $activeMemberIds), static fn (int $id): bool => $id > 0)));
        if (!in_array($userId, $ids, true)) {
            return null;
        }
        $mine = self::normalize($engagementByUser[$userId] ?? '');
        if ($mine === '') {
            return null;
        }
        $rank = 1;
        foreach ($ids as $other) {
            if ($other === $userId) {
                continue;
            }
            $date = self::normalize($engagementByUser[$other] ?? '');
            if ($date === '') {
                continue;
            }
            $cmp = strcmp($date, $mine);
            if ($cmp < 0 || ($cmp === 0 && $other < $userId)) {
                $rank++;
            }
        }

        return ['rank' => $rank, 'total' => count($ids)];
    }

    /**
     * Date d’engagement réelle : la plus ancienne des dates connues.
     */
    public static function engagementDate(?string $communityStart, ?string $prePlatformStart): string
    {
        $dates = array_filter([self::normalize((string) $communityStart), self::normalize((string) $prePlatformStart)], static fn (string $d): bool => $d !== '');
        if ($dates === []) {
            return '';
        }
        sort($dates);

        return $dates[0];
    }

    private static function normalize(string $raw): string
    {
        $raw = trim($raw);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $raw) || str_starts_with($raw, '0000-00-00')) {
            return '';
        }

        return substr($raw, 0, 10);
    }
}
