<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés, icônes SVG et couleurs par défaut pour les annonces communauté.
 */
final class TenantAlertVisuals
{
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
        ];
    }

    /** @return list<string> */
    public static function kindKeys(): array
    {
        return array_keys(self::kinds());
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
