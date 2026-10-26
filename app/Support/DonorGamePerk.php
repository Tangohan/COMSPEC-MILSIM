<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Billing\DonorService;

/**
 * Palier « Donateur Athena » transmis au jeu et à la Tacmap. Jamais de montant ni d'e-mail : seulement le palier.
 *
 * Côté mod, la clé passe par GET /api/atak/experience sous le nom net_donor_tier, car la DLL COMSPEC Link actuelle
 * relaie toutes les clés net_* sans filtrage (pas de nouvelle version de l'extension à publier). donor_tier est
 * envoyé aussi, pour une future DLL qui la lirait directement.
 */
final class DonorGamePerk
{
    public const TIERS = ['soutien', 'allie', 'pilier'];

    /** Palier normalisé ('' si aucun ou inconnu). */
    public static function normalize(mixed $tier): string
    {
        $t = is_string($tier) ? strtolower(trim($tier)) : '';

        return in_array($t, self::TIERS, true) ? $t : '';
    }

    /**
     * Clés à fusionner dans la charge utile jeu.
     *
     * @param callable(int): ?string|null $tierOf résolveur de palier (DonorService::userTier par défaut)
     * @return array{net_donor_tier: string, donor_tier: string}
     */
    public static function payload(?int $userId, ?callable $tierOf = null): array
    {
        $tier = '';
        if ($userId !== null && $userId > 0) {
            try {
                $tier = self::normalize(($tierOf ?? [DonorService::class, 'userTier'])($userId));
            } catch (\Throwable) {
                $tier = '';
            }
        }

        return ['net_donor_tier' => $tier, 'donor_tier' => $tier];
    }

    /**
     * Paliers pour une liste d'utilisateurs (Tacmap : marqueurs et liste des joueurs). Seuls les donateurs sont renvoyés.
     *
     * @param list<int> $userIds
     * @param callable(int): ?string|null $tierOf
     * @return array<int, string>
     */
    public static function tiersFor(array $userIds, ?callable $tierOf = null): array
    {
        $out = [];
        foreach (array_unique(array_map('intval', $userIds)) as $id) {
            if ($id < 1) {
                continue;
            }
            $t = self::payload($id, $tierOf)['donor_tier'];
            if ($t !== '') {
                $out[$id] = $t;
            }
        }

        return $out;
    }
}
