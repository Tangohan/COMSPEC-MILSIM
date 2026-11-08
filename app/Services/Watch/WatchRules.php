<?php

declare(strict_types=1);

namespace App\Services\Watch;

/**
 * Libellés et explications des règles de détection, pour l'administration. Chaque règle dit ce qu'elle constate, jamais
 * ce qu'il faut en conclure : la décision revient à un humain.
 */
final class WatchRules
{
    /** @var array<string, array{label: string, explain: string}> */
    private const RULES = [
        'position_impossible' => [
            'label' => 'Déplacements impossibles',
            'explain' => 'Plusieurs positions successives d’un même joueur sont séparées par une distance sans rapport avec la vitesse possible dans le temps écoulé. Une téléportation de mission, une réapparition ou un transport par Zeus peuvent le provoquer : à vérifier avec la communauté.',
        ],
        'position_hors_carte' => [
            'label' => 'Positions hors carte',
            'explain' => 'Plusieurs positions envoyées au-delà de l’étendue d’une carte. Une carte personnalisée très grande peut en être la cause.',
        ],
        'volume_anormal' => [
            'label' => 'Volume d’envois anormal',
            'explain' => 'Un même joueur envoie, sur plusieurs minutes de suite, bien plus de données que l’usage normal du mod.',
        ],
        'identifiant_multi_steam' => [
            'label' => 'Même jeton, plusieurs Steam',
            'explain' => 'Un même jeton de compte ou de session de jeu a été présenté avec plusieurs identifiants Steam dans un temps court. Un compte partagé ou un jeton copié peuvent l’expliquer.',
        ],
        'identifiant_steam_decale' => [
            'label' => 'Steam différent de celui du jeton',
            'explain' => 'L’identifiant Steam déclaré dans les envois n’est pas celui auquel le jeton est lié, à plusieurs reprises.',
        ],
        'ecritures_refusees' => [
            'label' => 'Écritures refusées en rafale',
            'explain' => 'Beaucoup d’envois du mod ont été refusés par les contrôles du serveur en une minute (identité, limite de débit, coordonnées…). Un mod mal configuré ou ancien le provoque aussi.',
        ],
        'version_incoherente' => [
            'label' => 'Version du mod incohérente',
            'explain' => 'La version annoncée par l’en-tête diffère de celle déclarée dans la requête, ou la version change plusieurs fois pour le même joueur dans un temps court.',
        ],
        'charge_elevee' => [
            'label' => 'Charge élevée du site',
            'explain' => 'Une ou plusieurs mesures de l’application dépassent leur seuil d’alerte de façon soutenue. Voir la page Charge et surcharge.',
        ],
    ];

    public static function label(string $rule): string
    {
        return self::RULES[$rule]['label'] ?? $rule;
    }

    public static function explain(string $rule): string
    {
        return self::RULES[$rule]['explain'] ?? '';
    }

    /** @return list<string> */
    public static function modRules(): array
    {
        return array_values(array_filter(array_keys(self::RULES), static fn (string $r): bool => $r !== 'charge_elevee'));
    }

    /**
     * Valeurs mesurées d'une alerte en lignes lisibles « libellé : valeur ».
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function measuredLines(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $names = [
            'sauts' => 'Sauts constatés', 'vitesse_max_ms' => 'Vitesse maximale (m/s)', 'distance_max_m' => 'Plus grand écart (m)',
            'seuil_vitesse_ms' => 'Seuil de vitesse (m/s)', 'fenetre_min' => 'Fenêtre (min)', 'positions' => 'Positions',
            'coordonnee_max_m' => 'Coordonnée la plus éloignée (m)', 'limite_m' => 'Limite (m)',
            'minutes_au_dessus' => 'Minutes au-dessus du seuil', 'maximum_par_minute' => 'Maximum par minute',
            'seuil_par_minute' => 'Seuil par minute', 'steams_distincts' => 'Steam distincts', 'dernier_steam' => 'Dernier Steam',
            'occurrences' => 'Occurrences', 'refus_dans_la_minute' => 'Refus dans la minute', 'motifs' => 'Motifs',
            'constat' => 'Constat', 'version_en_tete' => 'Version de l’en-tête', 'version_declaree' => 'Version déclarée',
            'versions_distinctes' => 'Versions distinctes', 'versions' => 'Versions', 'palier' => 'Palier de charge',
            'rapport_au_seuil' => 'Rapport au seuil', 'p95_ms' => 'p95 (ms)', 'erreurs_pct' => 'Erreurs 5xx (%)',
            'charge_par_coeur' => 'Charge par cœur', 'requetes_fenetre' => 'Requêtes de la fenêtre',
            'ralentissement_actif' => 'Ralentissement actif',
        ];
        $lines = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $parts = [];
                foreach ($value as $k => $v) {
                    $parts[] = is_string($k) ? $k . ' ×' . (is_scalar($v) ? (string) $v : '') : (is_scalar($v) ? (string) $v : '');
                }
                $value = implode(', ', $parts);
            } elseif (is_bool($value)) {
                $value = $value ? 'oui' : 'non';
            } elseif ($value === null) {
                $value = '—';
            }
            $lines[] = [$names[$key] ?? (string) $key, (string) $value];
        }

        return $lines;
    }
}
