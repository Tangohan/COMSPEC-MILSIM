<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés, icônes SVG et couleurs par défaut pour les annonces communauté.
 */
final class TenantAlertVisuals
{
    /** Types retirés du choix communauté (conservés pour libellés d’anciennes annonces). */
    private const TENANT_EXCLUDED_KINDS = ['discount', 'maintenance'];

    /** @return array<string, array{label: string, hint: string, color: string}> */
    public static function kinds(): array
    {
        return [
            'info' => [
                'label' => 'Information',
                'hint' => 'Message général pour les membres.',
                'color' => '#0284c7',
            ],
            'novelty' => [
                'label' => 'Nouveauté',
                'hint' => 'Annonce d’une nouveauté ou d’un changement.',
                'color' => '#059669',
            ],
            'discount' => [
                'label' => 'Promo / remise',
                'hint' => 'Offre commerciale ou code avantage.',
                'color' => '#d97706',
            ],
            'urgent' => [
                'label' => 'Urgent',
                'hint' => 'Message prioritaire, mis en avant.',
                'color' => '#e11d48',
            ],
            'notice' => [
                'label' => 'Consigne',
                'hint' => 'Rappel ou instruction à l’attention de l’unité.',
                'color' => '#059669',
            ],
            'event' => [
                'label' => 'Événement',
                'hint' => 'Manœuvre, réunion, session planifiée.',
                'color' => '#4f46e5',
            ],
            'maintenance' => [
                'label' => 'Maintenance',
                'hint' => 'Interruption ou travaux techniques prévus.',
                'color' => '#64748b',
            ],
            'training' => [
                'label' => 'Formation',
                'hint' => 'Parcours, module ou session de formation à venir.',
                'color' => '#7c3aed',
            ],
            'recruitment' => [
                'label' => 'Recrutement',
                'hint' => 'Ouverture de postes, campagne ou appel à candidatures.',
                'color' => '#0369a1',
            ],
            'security' => [
                'label' => 'Sécurité',
                'hint' => 'Consigne de sécurité, rappel OPSEC ou mesure de protection.',
                'color' => '#b45309',
            ],
        ];
    }

    /**
     * Types proposés à la création / édition d’annonces communauté (milsim).
     *
     * @return array<string, array{label: string, hint: string, color: string}>
     */
    public static function kindsForTenant(): array
    {
        $kinds = self::kinds();
        foreach (self::TENANT_EXCLUDED_KINDS as $key) {
            unset($kinds[$key]);
        }

        return $kinds;
    }

    /**
     * Options du formulaire communauté : types autorisés, plus le type actuel s’il est hérité.
     *
     * @return array<string, array{label: string, hint: string, color: string}>
     */
    public static function kindsForTenantForm(?string $currentKind = null): array
    {
        $options = self::kindsForTenant();
        $currentKind = trim((string) $currentKind);
        if ($currentKind === '' || isset($options[$currentKind])) {
            return $options;
        }
        $all = self::kinds();
        if (!isset($all[$currentKind])) {
            return $options;
        }

        return [$currentKind => $all[$currentKind]] + $options;
    }

    /** @return list<string> */
    public static function kindKeys(): array
    {
        return array_keys(self::kinds());
    }

    /** @return list<string> */
    public static function kindKeysForTenant(): array
    {
        return array_keys(self::kindsForTenant());
    }

    /**
     * Autorise les types communauté, ou le type déjà enregistré (annonces héritées).
     */
    public static function isAllowedTenantKind(string $kind, ?string $existingKind = null): bool
    {
        if (in_array($kind, self::kindKeysForTenant(), true)) {
            return true;
        }
        $existingKind = $existingKind !== null ? trim($existingKind) : null;

        return $existingKind !== null
            && $existingKind !== ''
            && $kind === $existingKind
            && isset(self::kinds()[$kind]);
    }

    public static function kindLabel(string $kind): string
    {
        $kinds = self::kinds();

        return $kinds[$kind]['label'] ?? 'Annonce';
    }

    public static function defaultColorForKind(string $kind): string
    {
        $kinds = self::kinds();

        return $kinds[$kind]['color'] ?? '#0284c7';
    }

    /**
     * Clés d’icônes disponibles (SVG embarqués côté bandeau).
     *
     * @return array<string, string> key => libellé FR
     */
    public static function iconLabels(): array
    {
        return [
            'auto' => 'Selon le type',
            'info' => 'Information',
            'star' => 'Étoile',
            'tag' => 'Étiquette',
            'alert' => 'Attention',
            'megaphone' => 'Annonce',
            'calendar' => 'Calendrier',
            'wrench' => 'Maintenance',
            'shield' => 'Sécurité',
            'flag' => 'Drapeau',
            'graduation' => 'Formation',
            'users' => 'Recrutement',
        ];
    }

    /** @return list<string> */
    public static function iconKeys(): array
    {
        return array_keys(self::iconLabels());
    }

    public static function sanitizeHexColor(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('/^#([0-9A-Fa-f]{6})$/', $raw)) {
            return null;
        }

        return strtoupper($raw);
    }

    public static function publicUrl(?string $relativePath): ?string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $relativePath)) {
            return $relativePath;
        }
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        return url($relativePath);
    }
}
