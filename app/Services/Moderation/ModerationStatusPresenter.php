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
        if ($set->forumAccess === 'none') {
            $lines[] = 'Forum : aucun accès.';
        } elseif ($set->forumAccess === 'read_only') {
            $lines[] = 'Forum : lecture seule.';
        }
        if ($set->messagesBlocked) {
            $lines[] = 'Messagerie interne : envoi bloqué.';
        }
        if ($set->joinBlocked) {
            $lines[] = 'Nouvelle inscription avec la même adresse e-mail : bloquée pour cette communauté.';
        }
        $labels = ModerationRestrictionsCatalog::moduleLabels();
        $labels[ModerationRestrictionsCatalog::KEY_FORUM] = 'Forum';
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
        if ($set->forumAccess === 'none' || $set->messagesBlocked) {
            return 'Votre participation au forum ou à la messagerie est actuellement limitée.';
        }
        if ($set->forumAccess === 'read_only') {
            return 'Vous pouvez consulter le forum en lecture seule ; la publication de messages est limitée.';
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
