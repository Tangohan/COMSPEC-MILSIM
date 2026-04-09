<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

/**
 * Politiques de consentement par typologie (clés techniques alignées sur le formulaire).
 */
final class CooperationConsentDefaults
{
    /** @return list<string> */
    public static function suggestedKeysForTypology(?string $typologySlug): array
    {
        return match ($typologySlug) {
            'exercice' => ['brief', 'liaison', 'competency'],
            'formation' => ['brief', 'competency'],
            'coordination_renseignement' => ['brief'],
            'appui_operationnel' => ['brief', 'liaison'],
            'liaison_interservices' => ['liaison', 'brief'],
            'soutien_logistique' => ['brief', 'liaison'],
            'preparation_mission' => ['brief', 'liaison', 'competency'],
            'retour_experience' => ['brief'],
            default => ['brief'],
        };
    }

    public static function consentTtlHours(): int
    {
        return 72;
    }
}
