<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Identité de jeu qui permet d'attribuer une écriture du jeu à un membre.
 *
 * Toujours : l'identité vérifiée par le jeton d'accès jeu personnel (game_sessions, ComspecApiKeyAuth::matchedUserId())
 * prime sur le Steam déclaré par le client (en-tête X-COMSPEC-STEAM, corps, paramètre d'URL). Un Steam déclaré qui
 * appartient à un autre membre est refusé (403 steam_mismatch, voir AtakArmaWriteGuard::preferVerifiedMember()).
 *
 * Mode strict (désactivé par défaut) : sans jeton personnel, la seule clé partagée (clé de communauté ou clé plateforme)
 * ne suffit plus pour attribuer une écriture à un Steam déclaré ni pour ouvrir une session de jeu, et la connexion par
 * Steam exige le jeton de poste (preuve d'une connexion antérieure sur ce poste). Il ne s'active que lorsque deux
 * conditions sont réunies :
 *  - ATAK_GAME_IDENTITY_STRICT_MIN_LINK nomme la première version de DLL qui présente toujours un jeton personnel ;
 *  - le plancher plateforme game_min_link_version (GameClientVersionGate) atteint cette version, donc les DLL plus
 *    anciennes sont déjà refusées. Abaisser le plancher sous cette version désactive le mode strict de lui-même.
 * Variable absente ou illisible : rien n'est lu (ni plancher, ni base) et aucun comportement ne change.
 */
final class AtakGameIdentityPolicy
{
    public const ENV_STRICT_MIN_LINK = 'ATAK_GAME_IDENTITY_STRICT_MIN_LINK';

    /** Refus du mode strict : écriture ou session de jeu sans jeton personnel. */
    public const ERROR_IDENTITY_REQUIRED = 'identity_required';

    /** Message sans « tenant », « unauthorized » ni code de refus définitif : la DLL 2.0.98 cherche ces sous-chaînes. */
    public const MESSAGE_IDENTITY_REQUIRED = 'Identité de jeu non vérifiée. Connectez votre compte Athena en jeu (e-mail, code ou Steam déjà relié à ce poste).';

    /** @var (\Closure(): string)|null plancher imposé par les tests à la place du réglage plateforme */
    private static ?\Closure $floorReader = null;

    /**
     * Mode strict actif : version configurée ET plancher des DLL au moins égal à cette version.
     */
    public static function strict(): bool
    {
        $min = self::configuredMinLink();
        if ($min === '') {
            return false;
        }
        try {
            $raw = self::$floorReader !== null
                ? (self::$floorReader)()
                : (string) (GameClientVersionGate::floor()['link'] ?? '');
        } catch (\Throwable) {
            return false;
        }
        $floor = GameClientVersionGate::normalizeFloor($raw);
        if ($floor === null || $floor === '') {
            return false;
        }

        return GameClientVersionGate::compareVersions($floor, $min) >= 0;
    }

    /** Version configurée (normalisée), ou chaîne vide si la variable est absente ou illisible. */
    public static function configuredMinLink(): string
    {
        $raw = (string) (($_ENV[self::ENV_STRICT_MIN_LINK] ?? null) ?: (getenv(self::ENV_STRICT_MIN_LINK) ?: ''));
        $min = GameClientVersionGate::normalizeFloor($raw);

        return $min ?? '';
    }

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

    /** @param (\Closure(): string)|null $reader */
    public static function useFloorReaderForTests(?\Closure $reader): void
    {
        self::$floorReader = $reader;
    }
}
