<?php

declare(strict_types=1);

namespace App\Services\Platform;

/**
 * Phrases produites à partir de modèles rédactionnels et de données vérifiées.
 */
final class AthenaHeritageNarration
{
    /**
     * @param array<string, scalar|null> $vars
     */
    public static function render(string $templateKey, array $vars = []): string
    {
        $template = (string) __($templateKey);
        if ($template === '' || $template === $templateKey) {
            $template = self::fallback($templateKey);
        }
        foreach ($vars as $key => $value) {
            $template = str_replace(':' . $key, (string) $value, $template);
        }

        return $template;
    }

    private static function fallback(string $key): string
    {
        return match ($key) {
            'site.hist_narr_players' => 'Avec :value opérateurs enregistrés, ATHENA franchit une nouvelle étape de son déploiement international.',
            'site.hist_narr_km' => 'Les communautés COMSPEC ont cumulé :value km parcourus sur le terrain virtuel.',
            'site.hist_narr_playtime' => 'Les opérateurs ont cumulé :value heures de jeu sur ATHENA.',
            'site.hist_narr_intl' => 'Première organisation internationale enregistrée:country.',
            'site.hist_narr_first_arma' => 'Premier opérateur connecté depuis Arma 3.',
            'site.hist_narr_first_terminal' => 'Premier terminal authentifié sur le réseau ATHENA.',
            'site.hist_narr_first_relay' => 'Premier relais tactique déployé.',
            'site.hist_narr_first_atak_msg' => 'Premier message ATAK transmis.',
            'site.hist_narr_first_sse' => 'Première mission SSE ouverte.',
            'site.hist_narr_first_drone' => 'Premier vol de drone enregistré.',
            'site.hist_narr_first_translation' => 'Premier pack de traduction publié.',
            'site.hist_narr_ops_50' => 'Première opération réunissant :value joueurs.',
            'site.hist_narr_version' => 'Publication de la version majeure :version — jalon du patrimoine COMSPEC.',
            default => 'Événement ATHENA enregistré.',
        };
    }
}
