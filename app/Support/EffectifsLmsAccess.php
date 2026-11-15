<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Gate;

/**
 * Accès au bureau LMS de pilotage des effectifs (outil RH organisationnel).
 */
final class EffectifsLmsAccess
{
    public static function allows(Gate $gate): bool
    {
        // Volontairement sans personnel.profile.view : cette permission est accordée par défaut à de
        // nombreux rôles opérationnels non-RH (instructeur, médecin, officier, recruteur...) pour la
        // consultation d'une fiche membre isolée — elle ne doit pas ouvrir le tableur RH complet
        // (e-mails, scores, historique) de toute la communauté.
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.roles.manage')
            || $gate->allows('site.support')
            || $gate->allows('organization.effectifs.hub.view')
            || $gate->allows('personnel.profile.update')
            || $gate->allows('personnel.assignments.manage')
            || $gate->allows('personnel.grades.manage')
            || $gate->allows('personnel.status.manage');
    }

    public static function canEditProfiles(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.profile.update');
    }

    /**
     * Lire ou télécharger les pièces du coffre RH (contrats, certificats, justificatifs) : les droits de gestion du
     * dossier, pas simplement l'accès au tableur des effectifs. Un responsable d'affectation ou de grades n'ouvre pas
     * le coffre.
     */
    public static function canViewHrDocuments(Gate $gate): bool
    {
        return self::canEditProfiles($gate) || $gate->allows('personnel.status.manage');
    }

    /**
     * Voir le suivi du dossier (étapes, échéances, historique). Mêmes personnes que les pièces du coffre RH, donc ceux
     * qui gèrent déjà le dossier : aucun accès nouveau à l'ouverture ; effectifs.suivi.view l'ouvre à d'autres, au choix
     * de la communauté (page Niveaux d'accès), et n'est accordé à personne automatiquement.
     */
    public static function canViewSuivi(Gate $gate): bool
    {
        return self::canViewHrDocuments($gate) || $gate->allows('effectifs.suivi.view') || $gate->allows('effectifs.suivi.manage');
    }

    /**
     * Modifier le suivi du dossier : administrateurs de la communauté (déjà maîtres de toute la fiche) ou droit
     * effectifs.suivi.manage. Un responsable RH sans ce droit consulte mais ne modifie pas.
     */
    public static function canManageSuivi(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('effectifs.suivi.manage');
    }

    /**
     * Décerner une décoration depuis la fiche. Exactement les droits qui ouvrent l'écran des décorations
     * (OrganizationAdminMiddleware, préfixe /back-office/referentiels/decorations) : aucun droit nouveau.
     */
    public static function canAwardDecorations(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('site.support')
            || $gate->allows('personnel.awards.manage')
            || $gate->allows('personnel.badges.manage');
    }

    public static function canManageStatus(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.status.manage');
    }

    /**
     * Ajouter un membre (fiche + compte) : gestion des comptes ou des dossiers. Les volets grade, affectation et
     * niveau d'accès de la fiche restent soumis à leur propre droit (canManageGrades, canManageAssignments, canManageRoles).
     */
    public static function canCreateMembers(Gate $gate): bool
    {
        return self::canManageStatus($gate) || self::canEditProfiles($gate);
    }

    /** Créer, envoyer, prolonger ou révoquer un lien « compléter son profil » : mêmes personnes. */
    public static function canInviteMembers(Gate $gate): bool
    {
        return self::canCreateMembers($gate);
    }

    public static function canManageAssignments(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.assignments.manage');
    }

    public static function canManageRoles(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('admin.roles.manage');
    }

    public static function canManageGrades(Gate $gate): bool
    {
        return $gate->allows('admin.organization')
            || $gate->allows('admin.access')
            || $gate->allows('personnel.grades.manage');
    }

    /**
     * Unité, emploi, grade : application immédiate réservée RH / Gestionnaire.
     * Les autres proposent une demande de correction.
     */
    public static function canApplyOrbatImmediately(Gate $gate): bool
    {
        return self::canManageAssignments($gate)
            || self::canManageGrades($gate)
            || self::canEditProfiles($gate);
    }

    /**
     * Mesures disciplinaires de la communauté : saisie et levée réservées aux personnes habilitées
     * aux restrictions de membres (même droit que l’écran « Restrictions membres »).
     */
    public static function canManageSanctions(Gate $gate): bool
    {
        return $gate->allows('admin.members.moderate');
    }

    /** Consultation des mesures disciplinaires sur la fiche : RH (statut du compte) ou habilités aux restrictions. */
    public static function canViewSanctions(Gate $gate): bool
    {
        return self::canManageSanctions($gate) || self::canManageStatus($gate);
    }

    /** Réglage des critères d’éligibilité à une élévation : réservé à qui gère les grades. */
    public static function canSetElevationCriteria(Gate $gate): bool
    {
        return self::canManageGrades($gate);
    }

    /** Peut solliciter une élévation RH auprès des personnes habilitées. */
    public static function canRequestElevation(Gate $gate): bool
    {
        return self::allows($gate);
    }
}
