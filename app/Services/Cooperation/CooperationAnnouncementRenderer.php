<?php

declare(strict_types=1);

namespace App\Services\Cooperation;

final class CooperationAnnouncementRenderer
{
    /** @param array<string, string|int|float> $vars */
    public function render(?string $template, array $vars): string
    {
        if ($template === null || $template === '') {
            return '';
        }
        $out = $template;
        foreach ($vars as $key => $value) {
            $out = str_replace('{' . $key . '}', (string) $value, $out);
        }

        return $out;
    }

    /** @return list<string> */
    public static function placeholderHints(): array
    {
        return [
            'titre_cooperation',
            'unite_support',
            'unite_destinataire',
            'date_limite',
            'lien_synthese',
            'lien_proposition',
            'lien_espace_commun',
            'lien_negociation',
        ];
    }

    /** @return array<string, string> clé technique => libellé pour l’aide dans les formulaires */
    public static function placeholderLabelsFr(): array
    {
        return [
            'titre_cooperation' => 'Titre du dossier de coopération',
            'unite_support' => 'Communauté à l’initiative (unité support)',
            'unite_destinataire' => 'Communauté invitée ou concernée par l’événement',
            'date_limite' => 'Date limite de réponse affichée sur la proposition',
            'lien_synthese' => 'Lien vers la synthèse du dossier',
            'lien_proposition' => 'Lien vers la page « Proposition »',
            'lien_espace_commun' => 'Lien vers l’espace commun',
            'lien_negociation' => 'Lien vers la négociation',
        ];
    }
}
