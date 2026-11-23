<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Statuts de fiche qui n'ouvrent aucun accès au jeu ni à l'API ATAK.
 *
 * Un bannissement du site passe les fiches en « inactive » : cette valeur doit refuser le jeu au même titre que
 * « banned » ou « suspended ».
 */
final class AccountAccessStatus
{
    public const GAME_DENIED = ['banned', 'disabled', 'suspended', 'deleted', 'inactive', 'merged'];

    public static function deniesGame(?string $status): bool
    {
        return in_array(strtolower(trim((string) $status)), self::GAME_DENIED, true);
    }
}
