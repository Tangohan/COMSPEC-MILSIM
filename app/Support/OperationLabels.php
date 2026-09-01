<?php

declare(strict_types=1);

namespace App\Support;

final class OperationLabels
{
    public static function status(string $code): string
    {
        return match ($code) {
            'draft' => 'En préparation',
            'planned' => 'Planifiée',
            'active' => 'En cours',
            'paused' => 'Suspendue',
            'closed' => 'Close',
            default => 'En préparation',
        };
    }

    public static function classification(string $code): string
    {
        return match ($code) {
            'unclassified' => 'Non classifié',
            'restricted' => 'Diffusion restreinte',
            'confidential' => 'Confidentiel',
            'secret' => 'Secret',
            default => 'Diffusion restreinte',
        };
    }

    public static function workflow(string $code): string
    {
        return match ($code) {
            'draft' => 'Brouillon',
            'review' => 'En revue',
            'approved' => 'Approuvé',
            'published' => 'Publié sur la vue terrain',
            default => 'Brouillon',
        };
    }

    public static function visibility(string $code): string
    {
        return match ($code) {
            'private' => 'Personnel',
            'staff' => 'État-major',
            'element' => 'Élément',
            'operation' => 'Opération',
            'tenant' => 'Communauté',
            'published' => 'Vue terrain',
            default => 'État-major',
        };
    }

    public static function affiliation(string $code): string
    {
        return match ($code) {
            'friendly' => 'Ami',
            'hostile' => 'Ennemi',
            'neutral' => 'Neutre',
            'unknown' => 'Inconnu',
            default => 'Ami',
        };
    }

    public static function objectStatus(string $code): string
    {
        return match ($code) {
            'planned' => 'Prévu',
            'ready' => 'Prêt',
            'active' => 'En cours',
            'complete' => 'Achevé',
            'cancelled' => 'Annulé',
            default => 'Prévu',
        };
    }

    public static function taskStatus(string $code): string
    {
        return match ($code) {
            'upcoming' => 'À venir',
            'ready' => 'Prêt',
            'active' => 'En cours',
            'complete' => 'Achevé',
            'cancelled' => 'Annulé',
            default => 'À venir',
        };
    }

    public static function overlayKind(string $code): string
    {
        return match ($code) {
            'maneuver' => 'Manœuvre',
            'fire_support' => 'Appuis-feux',
            'intelligence' => 'Renseignement',
            'friendly' => 'Unités amies',
            'enemy' => 'Situation ennemie',
            'airspace' => 'Espace aérien',
            'logistics' => 'Logistique',
            default => 'Calque',
        };
    }

    public static function orderKind(string $code): string
    {
        return match ($code) {
            'opord' => 'Ordre d’opération',
            'warnord' => 'Ordre d’alerte',
            'frago' => 'Ordre fragmentaire',
            default => 'Ordre',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function statusOptions(): array
    {
        return self::options(['draft', 'planned', 'active', 'paused', 'closed'], [self::class, 'status']);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function classificationOptions(): array
    {
        return self::options(['restricted', 'unclassified', 'confidential', 'secret'], [self::class, 'classification']);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function workflowOptions(): array
    {
        return self::options(['draft', 'review', 'approved', 'published'], [self::class, 'workflow']);
    }

    /**
     * @param list<string> $codes
     * @param callable(string): string $label
     * @return list<array{value: string, label: string}>
     */
    private static function options(array $codes, callable $label): array
    {
        $out = [];
        foreach ($codes as $code) {
            $out[] = ['value' => $code, 'label' => $label($code)];
        }

        return $out;
    }
}
