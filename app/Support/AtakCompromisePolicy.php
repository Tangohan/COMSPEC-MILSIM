<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Qui peut défaire une compromission d'ATAK. Un appareil compromis ne retrouve aucun accès par lui-même : seule
 * l'administration (site, poste de commandement, app Admin) lève une compromission décidée par un chef ou par elle.
 * Le jeu ne lève que celles qu'un effet Zeus ou une remontée du terrain a posées (effet retiré par le Zeus).
 * Un joueur retrouve le réseau par un nouvel appareil, qui a son propre identifiant : l'ancienne fiche ne change pas.
 */
final class AtakCompromisePolicy
{
    /** Motifs posés par fn_syncTerminalCompromise (effet Zeus) et par la remontée de position. */
    private const GAME_REASONS = ['Action terrain / Zeus', 'Remonté depuis le terrain'];

    public static function isCompromised(?array $terminal): bool
    {
        return in_array(strtolower(trim((string) ($terminal['compromise_state'] ?? ''))), ['captured', 'compromised'], true);
    }

    /** Le client de jeu peut-il changer l'état de cette fiche ? */
    public static function gameMayChange(?array $terminal): bool
    {
        return !self::isCompromised($terminal)
            || in_array(trim((string) ($terminal['compromise_reason'] ?? '')), self::GAME_REASONS, true);
    }

    /**
     * L'état remonté avec une position change-t-il la fiche ? Sans cette règle, chaque position (état « none » par défaut
     * du mod) effaçait à l'instant la compromission validée sur le terrain.
     */
    public static function positionMayChange(?array $terminal, string $incoming): bool
    {
        return $terminal !== null
            && strtolower((string) ($terminal['compromise_state'] ?? 'none')) !== $incoming
            && self::gameMayChange($terminal);
    }

    /** Refus d'enregistrer ce terminal depuis le jeu : code d'erreur renvoyé au mod, null si rien ne s'y oppose. */
    public static function registrationRefusal(?array $terminal, int $userId): ?string
    {
        if ($terminal === null) {
            return null;
        }
        if (!self::gameMayChange($terminal)) {
            return 'terminal_compromised';
        }
        $owner = (int) ($terminal['user_id'] ?? 0);

        return $owner > 0 && $userId > 0 && $owner !== $userId ? 'terminal_uid_taken' : null;
    }
}
