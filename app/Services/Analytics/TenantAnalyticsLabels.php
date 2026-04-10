<?php

declare(strict_types=1);

namespace App\Services\Analytics;

/**
 * Libellés « métier » pour l’écran analytics communauté (pas d’identifiants techniques visibles).
 */
final class TenantAnalyticsLabels
{
    public static function categoryLabel(string $category): string
    {
        return match ($category) {
            AnalyticsEventCategory::TRAINING => 'Formations',
            AnalyticsEventCategory::TENANT_PUBLIC => 'Fiche publique',
            AnalyticsEventCategory::RECRUITMENT => 'Recrutement',
            AnalyticsEventCategory::PORTAL => 'Portail — espace membre',
            default => 'Autre activité',
        };
    }

    public static function eventNameLabel(string $name): string
    {
        return match ($name) {
            AnalyticsEventName::TRAINING_CATALOG_VIEW => 'Ouverture du catalogue des formations',
            AnalyticsEventName::COURSE_VIEW => 'Consultation d’un parcours',
            AnalyticsEventName::COURSE_SHARE_CODE_USED => 'Accès à un parcours par code',
            AnalyticsEventName::TENANT_PUBLIC_VIEW => 'Consultation de la fiche publique',
            AnalyticsEventName::RECRUITMENT_OPENING_VIEW => 'Consultation d’un avis de poste',
            AnalyticsEventName::ENLISTMENT_FORM_OPEN => 'Ouverture du formulaire de candidature',
            AnalyticsEventName::ENLISTMENT_SUBMITTED => 'Candidature envoyée',
            AnalyticsEventName::COURSE_PAGE_DURATION => 'Temps passé sur une fiche parcours (mesure)',
            AnalyticsEventName::TENANT_PUBLIC_PAGE_DURATION => 'Temps passé sur la fiche publique (mesure)',
            AnalyticsEventName::RECRUITMENT_OPENING_PAGE_DURATION => 'Temps passé sur un avis de poste (mesure)',
            AnalyticsEventName::TENANT_RECRUITMENT_CTA_CLICK => 'Clic vers le recrutement',
            default => 'Action enregistrée',
        };
    }
}
