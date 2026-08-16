<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Libellés métier du cycle de renseignement (LOT 4).
 * Clés techniques uniquement côté code — l’UI affiche les intitulés.
 */
final class SseIntelCycleCatalog
{
    /** @var array<string,string> */
    public const REQUIREMENT_TYPES = [
        'PIR' => 'Priorité de renseignement',
        'SIR' => 'Besoin spécifique',
        'EEI' => 'Élément essentiel d’information',
    ];

    /** @var array<string,string> */
    public const REQUIREMENT_STATUSES = [
        'ouvert' => 'Ouvert',
        'en_cours' => 'En cours',
        'partiellement_couvert' => 'Partiellement couvert',
        'satisfait' => 'Satisfait',
        'abandonne' => 'Abandonné',
    ];

    /** @var array<string,string> */
    public const PRIORITIES = [
        'basse' => 'Basse',
        'normale' => 'Normale',
        'prioritaire' => 'Prioritaire',
        'critique' => 'Critique',
    ];

    /** @var array<string,string> */
    public const TASKING_STATUSES = [
        'brouillon' => 'Brouillon',
        'emis' => 'Émis',
        'accepte' => 'Accepté',
        'en_cours' => 'En cours',
        'remis' => 'Remis',
        'clos' => 'Clos',
        'annule' => 'Annulé',
    ];

    /** @var array<string,string> */
    public const PRODUCT_TYPES = [
        'FLASH' => 'Compte rendu flash',
        'INITIAL' => 'Compte rendu initial',
        'UPDATE' => 'Mise à jour',
        'SUMMARY' => 'Synthèse',
    ];

    /** @var array<string,string> */
    public const PRODUCT_STATUSES = [
        'brouillon' => 'Brouillon',
        'en_relecture' => 'En relecture',
        'valide' => 'Validé',
        'sanitise' => 'Sanitisé',
        'diffuse' => 'Diffusé',
        'archive' => 'Archivé',
    ];

    /** @var array<string,string> — aligné sur SseCaseRepository / SseRedactionService */
    public const RELEASE_LEVELS = [
        'interne' => 'Usage interne',
        'encadrement' => 'Encadrement',
        'confidentiel' => 'Confidentiel',
        'tres_restreint' => 'Diffusion très restreinte',
    ];

    /** @var array<string,string> */
    public const ACK_STATUSES = [
        'envoye' => 'Envoyé',
        'accuse' => 'Accusé réception',
        'lu' => 'Lu',
    ];

    public static function requirementTypeLabel(string $code): string
    {
        $c = strtoupper(trim($code));

        return self::REQUIREMENT_TYPES[$c] ?? $c;
    }

    public static function statusLabel(string $mapKey, string $status): string
    {
        $map = match ($mapKey) {
            'requirement' => self::REQUIREMENT_STATUSES,
            'tasking' => self::TASKING_STATUSES,
            'product' => self::PRODUCT_STATUSES,
            'ack' => self::ACK_STATUSES,
            default => [],
        };

        return $map[$status] ?? $status;
    }
}
