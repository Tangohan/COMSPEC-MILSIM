<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Identité de jeu qui permet d'attribuer une écriture du jeu à un membre.
 *
 * L'identité vérifiée par le jeton d'accès jeu personnel (game_sessions, ComspecApiKeyAuth::matchedUserId()) prime sur
 * le Steam déclaré par le client (en-tête X-COMSPEC-STEAM, corps, paramètre d'URL). Un Steam déclaré qui appartient à
 * un autre membre est refusé (403 steam_mismatch, voir AtakArmaWriteGuard::preferVerifiedMember()). Avec la seule clé
 * partagée (clé de communauté ou clé plateforme), rien n'est comparé : le Steam déclaré reste retenu comme avant.
 */
final class AtakGameIdentityPolicy
{
    /**
     * Membre vérifié par le jeton d'accès jeu personnel présenté avec la requête ; null avec une clé partagée seule.
     */
    public static function verifiedUserId(): ?int
    {
        $uid = ComspecApiKeyAuth::matchedUserId();

        return $uid !== null && $uid > 0 ? $uid : null;
    }

    /** Steam enregistré sur la session de ce jeton personnel (null s'il n'en porte pas ou sans jeton personnel). */
    public static function verifiedSteam(): ?string
    {
        $steam = ComspecApiKeyAuth::matchedSteamId();

        return $steam !== null ? SteamId::normalize($steam) : null;
    }

    /** Requête qui présente une identité personnelle vérifiée (jeton d'accès jeu). */
    public static function hasVerifiedIdentity(): bool
    {
        return self::verifiedUserId() !== null || self::verifiedSteam() !== null;
    }
}
