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
