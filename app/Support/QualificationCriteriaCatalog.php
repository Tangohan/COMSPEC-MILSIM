<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Modes d'obtention d'une qualification et critères automatiques que le site sait mesurer.
 *
 * Seuls figurent les critères alimentés par des données réellement reçues : temps de jeu du mod (user_arma_playtime),
 * heures de vol par appareil (atak_pilot_time + carnet de vol contresigné), relevés de mission du mod
 * (atak_distance_stats : missions, sauts, distances, temps en véhicule), pointages aux événements
 * (community_event_rsvps), sessions de jeu validées (roleplay_game_session_members), comptes rendus validés
 * (aar_reports) et équipement vu en jeu (game_item_usage).
 */
final class QualificationCriteriaCatalog
{
    public const MODE_MANUAL = 'manual';
    public const MODE_ACADEMY = 'academy';
    public const MODE_AUTO = 'auto';
    public const MODE_PILOT = 'pilot';

    public const AWARD_DIRECT = 'direct';
    public const AWARD_PROPOSE = 'propose';

    /** Fenêtres glissantes proposées (jours, 0 = depuis toujours). */
    public const WINDOWS = [0, 30, 90, 180, 365];

    public const MAX_GROUPS = 8;
    public const MAX_ITEMS = 12;

    /**
     * Critères mesurés. scope : liste de périmètres possibles (voir scopeKind) ; window : la donnée est datée, un seuil
     * peut porter sur les N derniers jours.
     *
     * @var array<string, array{label: string, unit: string, scope: string, window: bool, help: string, step: float}>
     */
    public const METRICS = [
        'playtime_hours' => [
            'label' => 'Heures de jeu',
            'unit' => 'h',
            'scope' => 'playtime',
            'window' => false,
            'step' => 0.5,
            'help' => 'Temps de jeu relevé par le mod ATAK depuis son installation : tout Arma, sur serveur ou en Zeus.',
        ],
        'session_hours' => [
            'label' => 'Heures de session validées',
            'unit' => 'h',
            'scope' => 'session_category',
            'window' => true,
            'step' => 0.5,
            'help' => 'Heures pointées et validées par les responsables sur les sessions de jeu, toutes ou d’une seule catégorie.',
        ],
        'sessions_count' => [
            'label' => 'Sessions de jeu validées',
            'unit' => 'sessions',
            'scope' => 'none',
            'window' => true,
            'step' => 1,
            'help' => 'Nombre de sessions de jeu où la présence du membre a été validée.',
        ],
        'flight_hours' => [
            'label' => 'Heures de vol',
            'unit' => 'h',
            'scope' => 'aircraft',
            'window' => false,
            'step' => 0.5,
            'help' => 'Heures aux commandes relevées par le mod, plus les vols du carnet de vol contresignés : au total, par filière, par famille d’appareils ou pour un appareil précis.',
        ],
        'flight_cond_hours' => [
            'label' => 'Heures de vol de nuit, aux instruments ou en patrouille',
            'unit' => 'h',
            'scope' => 'flight_condition',
            'window' => false,
            'step' => 0.5,
            'help' => 'Heures notées sur le carnet de vol et contresignées par un instructeur.',
        ],
        'missions_played' => [
            'label' => 'Missions jouées',
            'unit' => 'missions',
            'scope' => 'none',
            'window' => true,
            'step' => 1,
            'help' => 'Parties relevées par le mod ATAK (une par mission jouée).',
        ],
        'events_attended' => [
            'label' => 'Présences aux événements',
            'unit' => 'présences',
            'scope' => 'event_type',
            'window' => true,
            'step' => 1,
            'help' => 'Pointages à l’arrivée sur les événements du calendrier (opérations, entraînements…).',
        ],
        'halo_jumps' => [
            'label' => 'Sauts en chute libre',
            'unit' => 'sauts',
            'scope' => 'none',
            'window' => true,
            'step' => 1,
            'help' => 'Sauts à ouverture commandée (HALO / HAHO) relevés par le mod.',
        ],
        'foot_km' => [
            'label' => 'Distance parcourue à pied',
            'unit' => 'km',
            'scope' => 'none',
            'window' => true,
            'step' => 1,
            'help' => 'Kilomètres parcourus à pied en mission, relevés par le mod.',
        ],
        'ground_vehicle_hours' => [
            'label' => 'Heures en véhicule terrestre',
            'unit' => 'h',
            'scope' => 'none',
            'window' => true,
            'step' => 0.5,
            'help' => 'Temps passé à bord d’un véhicule terrestre, toutes places confondues, relevé par le mod.',
        ],
        'boat_hours' => [
            'label' => 'Heures sur l’eau',
            'unit' => 'h',
            'scope' => 'none',
            'window' => true,
            'step' => 0.5,
            'help' => 'Temps passé à bord d’une embarcation, toutes places confondues, relevé par le mod.',
        ],
        'air_hours' => [
            'label' => 'Heures en aéronef (toutes places)',
            'unit' => 'h',
            'scope' => 'none',
            'window' => true,
            'step' => 0.5,
            'help' => 'Temps passé à bord d’un aéronef, pilote ou passager, relevé par le mod.',
        ],
        'aar_written' => [
            'label' => 'Comptes rendus de fin d’opération validés',
            'unit' => 'comptes rendus',
            'scope' => 'none',
            'window' => true,
            'step' => 1,
            'help' => 'Comptes rendus (AAR) rédigés par le membre et validés par un responsable.',
        ],
        'item_days' => [
            'label' => 'Jours de jeu avec un équipement',
            'unit' => 'jours',
            'scope' => 'item',
            'window' => true,
            'step' => 1,
            'help' => 'Jours où le mod a vu l’objet (ou une famille d’objets) dans l’équipement du membre : arme, lanceur, radio, matériel médical…',
        ],
    ];

    /** @var array<string, string> */
    public const PLAYTIME_SCOPES = [
        '' => 'Tout le jeu',
        'server' => 'Sur serveur',
        'zeus' => 'En Zeus',
    ];

    /** @var array<string, string> */
    public const FLIGHT_CONDITIONS = [
        'cond:night:heli' => 'De nuit, en hélicoptère',
        'cond:night:plane' => 'De nuit, en avion',
        'cond:ifr:heli' => 'Aux instruments, en hélicoptère',
        'cond:ifr:plane' => 'Aux instruments, en avion',
        'cond:formation:heli' => 'En patrouille, en hélicoptère',
        'cond:formation:plane' => 'En patrouille, en avion',
    ];

    /** @var array<string, string> */
    public const EVENT_TYPES = [
        '' => 'Tous les événements',
        'operation' => 'Opérations',
        'formation' => 'Formations',
        'evenement' => 'Événements',
        'autre' => 'Autres',
    ];

    /** @var array<string, array{label: string, help: string}> */
    public const MODES = [
        self::MODE_MANUAL => [
            'label' => 'Délivrée par un responsable',
            'help' => 'Un instructeur ou un gestionnaire la délivre à la main, avec la date et un commentaire ou une preuve.',
        ],
        self::MODE_ACADEMY => [
            'label' => 'Formation Academy',
            'help' => 'Obtenue en réussissant une formation de l’Academy : l’attestation délivrée crée la qualification.',
        ],
        self::MODE_AUTO => [
            'label' => 'Automatique',
            'help' => 'Délivrée par le site dès que les seuils mesurés en jeu sont atteints (vérifié toutes les heures).',
        ],
        self::MODE_PILOT => [
            'label' => 'Cursus pilote',
            'help' => 'Palier du cursus pilote : délivré d’après les heures de vol, réglé sur la page Cursus pilote.',
        ],
    ];

    public static function isMetric(string $key): bool
    {
        return isset(self::METRICS[$key]);
    }

    public static function metricLabel(string $key): string
    {
        return self::METRICS[$key]['label'] ?? $key;
    }

    public static function unit(string $key): string
    {
        return self::METRICS[$key]['unit'] ?? '';
    }

    public static function windowAllowed(string $key): bool
    {
        return !empty(self::METRICS[$key]['window']);
    }

    /** Périmètre valide pour ce critère (vide = sans périmètre). */
    public static function normalizeScope(string $metric, string $scope): string
    {
        $scope = trim($scope);
        $kind = self::METRICS[$metric]['scope'] ?? 'none';

        return match ($kind) {
            'playtime' => array_key_exists($scope, self::PLAYTIME_SCOPES) ? $scope : '',
            'flight_condition' => array_key_exists($scope, self::FLIGHT_CONDITIONS) ? $scope : 'cond:night:heli',
            'event_type' => array_key_exists($scope, self::EVENT_TYPES) ? $scope : '',
            'aircraft' => preg_match('/^(kind:(heli|plane)|family:[a-z0-9_:]{1,90}|vclass:[a-z0-9_]{1,80})$/', strtolower($scope)) === 1 ? strtolower($scope) : '',
            'item' => preg_match('/^(kind:[a-z_]{1,24}|class:[A-Za-z0-9_]{1,128})$/', $scope) === 1 ? $scope : '',
            'session_category' => mb_substr($scope, 0, 60),
            default => '',
        };
    }

    /**
     * Phrase lisible d'un critère : « 10 h de vol — UH-60 Black Hawk, sur les 90 derniers jours ».
     *
     * @param array<string, mixed> $c
     * @param array{courses?: array<int, string>, definitions?: array<int, string>, scopes?: array<string, string>} $names
     */
    public static function describe(array $c, array $names = []): string
    {
        $type = (string) ($c['type'] ?? 'metric');
        if ($type === 'academy') {
            $id = (int) ($c['course_id'] ?? 0);

            return 'Réussir la formation Academy « ' . ($names['courses'][$id] ?? ('#' . $id)) . ' »';
        }
        if ($type === 'qualification') {
            $id = (int) ($c['definition_id'] ?? 0);

            return 'Détenir la qualification « ' . ($names['definitions'][$id] ?? ('#' . $id)) . ' »';
        }
        $metric = (string) ($c['metric'] ?? '');
        $min = (float) ($c['min'] ?? 0);
        $text = self::formatNumber($min) . ' ' . self::unit($metric) . ' — ' . self::metricLabel($metric);
        $scope = (string) ($c['scope'] ?? '');
        if ($scope !== '') {
            $text .= ' (' . self::scopeLabel($metric, $scope, $names['scopes'] ?? []) . ')';
        }
        $window = (int) ($c['window_days'] ?? 0);
        if ($window > 0 && self::windowAllowed($metric)) {
            $text .= ', sur les ' . $window . ' derniers jours';
        }

        return $text;
    }

    /**
     * Morceaux d'un critère pour l'affichage (chacun traduit séparément) : valeur, unité, libellé, périmètre, fenêtre.
     *
     * @param array<string, mixed> $c
     * @param array{courses?: array<int, string>, definitions?: array<int, string>, scopes?: array<string, string>} $names
     * @return array{value: string, unit: string, label: string, scope: string, window: int}
     */
    public static function parts(array $c, array $names = []): array
    {
        $type = (string) ($c['type'] ?? 'metric');
        if ($type === 'academy') {
            $id = (int) ($c['course_id'] ?? 0);

            return ['value' => '', 'unit' => '', 'label' => 'Formation Academy réussie', 'scope' => $names['courses'][$id] ?? ('#' . $id), 'window' => 0];
        }
        if ($type === 'qualification') {
            $id = (int) ($c['definition_id'] ?? 0);

            return ['value' => '', 'unit' => '', 'label' => 'Qualification détenue', 'scope' => $names['definitions'][$id] ?? ('#' . $id), 'window' => 0];
        }
        $metric = (string) ($c['metric'] ?? '');
        $scope = (string) ($c['scope'] ?? '');

        return [
            'value' => self::formatNumber((float) ($c['min'] ?? 0)),
            'unit' => self::unit($metric),
            'label' => self::metricLabel($metric),
            'scope' => $scope !== '' ? self::scopeLabel($metric, $scope, $names['scopes'] ?? []) : '',
            'window' => self::windowAllowed($metric) ? max(0, (int) ($c['window_days'] ?? 0)) : 0,
        ];
    }

    /** @param array<string, string> $known libellés connus (appareils, objets vus en jeu) */
    public static function scopeLabel(string $metric, string $scope, array $known = []): string
    {
        if (isset($known[$scope])) {
            return $known[$scope];
        }
        $kind = self::METRICS[$metric]['scope'] ?? 'none';

        return match ($kind) {
            'playtime' => self::PLAYTIME_SCOPES[$scope] ?? $scope,
            'flight_condition' => self::FLIGHT_CONDITIONS[$scope] ?? $scope,
            'event_type' => self::EVENT_TYPES[$scope] ?? $scope,
            'aircraft' => match (true) {
                $scope === 'kind:heli' => 'Hélicoptères',
                $scope === 'kind:plane' => 'Avions',
                str_starts_with($scope, 'family:') => substr($scope, 7),
                str_starts_with($scope, 'vclass:') => substr($scope, 7),
                default => $scope,
            },
            'item' => str_starts_with($scope, 'kind:')
                ? (\App\Services\GameEquipment\GameItemKinds::KINDS[substr($scope, 5)]['plural'] ?? substr($scope, 5))
                : substr($scope, 6),
            default => $scope,
        };
    }

    public static function formatNumber(float $v): string
    {
        if (abs($v - round($v)) < 0.001) {
            return (string) (int) round($v);
        }

        return str_replace('.', ',', rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'));
    }

    /** @return array<string, string> */
    public static function modeLabels(): array
    {
        return array_map(static fn (array $m): string => $m['label'], self::MODES);
    }
}
