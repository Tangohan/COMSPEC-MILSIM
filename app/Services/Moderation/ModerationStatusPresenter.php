<?php

declare(strict_types=1);

namespace App\Services\Moderation;

/**
 * Libellés « humains » pour l’état effectif des restrictions (fiches personnel, etc.).
 */
final class ModerationStatusPresenter
{
    /** @return list<string> */
    public static function linesForStaff(ModerationRestrictionSet $set): array
    {
        $lines = [];
        if ($set->accountLocked) {
            $lines[] = 'Compte verrouillé (déconnexion forcée).';
        }
        if ($set->messagesBlocked) {
            $lines[] = 'Messagerie interne : envoi bloqué.';
        }
        if ($set->joinBlocked) {
            $lines[] = 'Nouvelle inscription avec la même adresse e-mail : bloquée pour cette communauté.';
        }
        $labels = ModerationRestrictionsCatalog::moduleLabels();
        foreach ($set->modulesBlocked as $key) {
            $label = $labels[$key] ?? $key;
            $lines[] = 'Accès limité : ' . $label . '.';
        }

        return $lines;
    }

    public static function briefForMember(ModerationRestrictionSet $set): ?string
    {
        if ($set->accountLocked) {
            return 'Votre accès au portail est restreint. En cas de question, contactez l’administration de votre communauté ou du site.';
        }
        if ($set->messagesBlocked) {
            return 'Votre accès à la messagerie est actuellement limité.';
        }
        if ($set->modulesBlocked !== []) {
            return 'L’accès à certaines parties du portail (formations, documents, etc.) est limité par votre organisation.';
        }
        if ($set->joinBlocked) {
            return 'Des limitations s’appliquent aux parcours d’inscription pour votre adresse e-mail sur cette communauté.';
        }

        return null;
    }

    public static function hasActiveRestrictions(ModerationRestrictionSet $set): bool
    {
        return self::linesForStaff($set) !== [];
    }
}
