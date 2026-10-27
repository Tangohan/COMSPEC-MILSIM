<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cursus pilote de référence : les paliers d'un vrai parcours de pilote (lâché solo, qualification de type, pilote
 * opérationnel, commandant de bord, instructeur, puis vol de nuit, aux instruments et en patrouille), avec la référence
 * réelle publique dont chaque palier s'inspire et un seuil d'heures ramené à l'échelle du jeu.
 *
 * Les références (EASA Part-FCL, FAA 14 CFR Part 61, US Army TC 3-04.11) servent de repère : les minimums réels se
 * comptent en dizaines ou centaines d'heures, une communauté Arma vole quelques heures par semaine. Les seuils du jeu
 * sont donc proposés par défaut et restent modifiables palier par palier.
 */
final class PilotSyllabusCatalog
{
    public const TRACK_HELI = 'heli';
    public const TRACK_PLANE = 'plane';

    /** Le palier dépend d'un appareil (un par type) ou de toute la filière (hélicoptère ou avion). */
    public const PER_FAMILY = 'family';
    public const PER_TRACK = 'track';

    public const MODE_DIRECT = 'direct';
    public const MODE_PROPOSE = 'propose';

    /** Types d'appareils regroupés par l'ATAK (AtakDistanceRepository::FAMILIES), rangés par filière. */
    private const FAMILY_TRACK = [
        'ch47' => self::TRACK_HELI, 'ch53' => self::TRACK_HELI, 'uh60' => self::TRACK_HELI, 'ah64' => self::TRACK_HELI,
        'ah1' => self::TRACK_HELI, 'uh1' => self::TRACK_HELI, 'mh6' => self::TRACK_HELI, 'v22' => self::TRACK_HELI,
        'mi8' => self::TRACK_HELI, 'mi24' => self::TRACK_HELI, 'mi28' => self::TRACK_HELI, 'ka52' => self::TRACK_HELI,
        'c130' => self::TRACK_PLANE, 'a10' => self::TRACK_PLANE, 'f16' => self::TRACK_PLANE, 'fa18' => self::TRACK_PLANE,
        'f35' => self::TRACK_PLANE, 'su25' => self::TRACK_PLANE,
    ];

    /**
     * @return array<string, array{label: string, short: string, noun: string}>
     */
    public static function tracks(): array
    {
        return [
            self::TRACK_HELI => ['label' => 'Hélicoptère', 'short' => 'H', 'noun' => 'hélicoptère'],
            self::TRACK_PLANE => ['label' => 'Avion', 'short' => 'A', 'noun' => 'avion'],
        ];
    }

    public static function trackLabel(string $track): string
    {
        return self::tracks()[$track]['label'] ?? 'Aéronef';
    }

    public static function normalizeTrack(string $track): string
    {
        return $track === self::TRACK_PLANE ? self::TRACK_PLANE : self::TRACK_HELI;
    }

    /**
     * Paliers du cursus, dans l'ordre du parcours. Chaque palier : périmètre (type d'appareil ou filière), heures
     * demandées sur ce périmètre et sur toute la filière, palier à détenir avant, mode d'attribution (direct, ou
     * proposé puis validé par un instructeur), palier qui valide, et la référence réelle par filière.
     *
     * @return array<string, array{
     *     label: string, short: string, per: string, min_scope_hours: float, min_track_hours: float, cond: ?string, min_cond_hours: float,
     *     prereq: ?string, mode: string, validator: ?string, currency_days: ?int, summary: string,
     *     real: array<string, string>, data_note: string
     * }>
     */
    public static function tiers(): array
    {
        return [
            'solo' => [
                'label' => 'Lâché solo',
                'short' => 'SOLO',
                'per' => self::PER_TRACK,
                'min_scope_hours' => 2.0,
                'min_track_hours' => 0.0,
                'cond' => null,
                'min_cond_hours' => 0.0,
                'prereq' => null,
                'mode' => self::MODE_PROPOSE,
                'validator' => 'ip',
                'currency_days' => null,
                'summary' => 'Premier vol seul aux commandes, autorisé par un instructeur après les heures en double commande.',
                'real' => [
                    self::TRACK_HELI => 'EASA PPL(H) (FCL.210.H) : 45 h de vol dont 25 h en double commande et 10 h solo supervisé. FAA (14 CFR 61.109(c)) : 40 h dont 20 h d’instruction et 10 h solo.',
                    self::TRACK_PLANE => 'EASA PPL(A) (FCL.210.A) : 45 h de vol dont 25 h en double commande et 10 h solo supervisé. FAA (14 CFR 61.109(a)) : 40 h dont 20 h d’instruction et 10 h solo.',
                ],
                'data_note' => '',
            ],
            'type' => [
                'label' => 'Qualification de type',
                'short' => 'QT',
                'per' => self::PER_FAMILY,
                'min_scope_hours' => 3.0,
                'min_track_hours' => 0.0,
                'cond' => null,
                'min_cond_hours' => 0.0,
                'prereq' => 'solo',
                'mode' => self::MODE_PROPOSE,
                'validator' => 'ip',
                'currency_days' => 90,
                'summary' => 'Apte à piloter ce type d’appareil, après un contrôle en vol (check ride) par un instructeur.',
                'real' => [
                    self::TRACK_HELI => 'EASA (AMC1 FCL.725(a)) : au moins 5 h d’instruction en vol sur un monomoteur, 8 h sur un bimoteur, puis un contrôle en vol. US Army : stage de qualification sur l’appareil (Aircraft Qualification Course).',
                    self::TRACK_PLANE => 'EASA (FCL.725) : formation sur type puis contrôle en vol par un examinateur. Militaire : transformation sur l’appareil en escadron de formation avant l’unité.',
                ],
                'data_note' => '',
            ],
            'rl1' => [
                'label' => 'Pilote opérationnel',
                'short' => 'OPS',
                'per' => self::PER_FAMILY,
                'min_scope_hours' => 10.0,
                'min_track_hours' => 0.0,
                'cond' => null,
                'min_cond_hours' => 0.0,
                'prereq' => 'type',
                'mode' => self::MODE_DIRECT,
                'validator' => null,
                'currency_days' => 90,
                'summary' => 'Prêt pour les missions sur ce type : les heures sur l’appareil suffisent, attribuée automatiquement.',
                'real' => [
                    self::TRACK_HELI => 'US Army (TC 3-04.11) : progression des niveaux de préparation RL3 (qualifié sur l’appareil), RL2 (tâches de base) puis RL1 (tâches de mission).',
                    self::TRACK_PLANE => 'Progression militaire : pilote qualifié sur l’appareil, puis apte aux missions de l’unité (équivalent RL1 de l’US Army, TC 3-04.11).',
                ],
                'data_note' => '',
            ],
            'pc' => [
                'label' => 'Commandant de bord',
                'short' => 'CDB',
                'per' => self::PER_FAMILY,
                'min_scope_hours' => 20.0,
                'min_track_hours' => 30.0,
                'cond' => null,
                'min_cond_hours' => 0.0,
                'prereq' => 'rl1',
                'mode' => self::MODE_PROPOSE,
                'validator' => null,
                'currency_days' => 90,
                'summary' => 'Responsable de l’appareil et de l’équipage, désigné par le commandement après évaluation.',
                'real' => [
                    self::TRACK_HELI => 'US Army (TC 3-04.11) : le pilote commandant de bord (PC) est désigné par écrit par le commandant d’unité après une évaluation en vol. FAA (14 CFR 61.129(c)) : licence commerciale hélicoptère à partir de 150 h.',
                    self::TRACK_PLANE => 'FAA (14 CFR 61.129(a)) : licence commerciale avion à partir de 250 h ; 1 500 h pour le pilote de ligne (61.159). Militaire : désignation par le commandant après évaluation.',
                ],
                'data_note' => '',
            ],
            'ip' => [
                'label' => 'Instructeur',
                'short' => 'IP',
                'per' => self::PER_FAMILY,
                'min_scope_hours' => 40.0,
                'min_track_hours' => 60.0,
                'cond' => null,
                'min_cond_hours' => 0.0,
                'prereq' => 'pc',
                'mode' => self::MODE_PROPOSE,
                'validator' => 'ip',
                'currency_days' => 90,
                'summary' => 'Forme et lâche les autres pilotes ; valide les paliers du cursus.',
                'real' => [
                    self::TRACK_HELI => 'FAA (14 CFR 61.183) : l’instructeur doit déjà détenir une licence commerciale (150 h en hélicoptère). EASA (FCL.915) : expérience minimale puis stage d’instructeur.',
                    self::TRACK_PLANE => 'FAA (14 CFR 61.183) : licence commerciale avec qualification aux instruments (250 h). EASA (FCL.915) : expérience minimale puis stage d’instructeur.',
                ],
                'data_note' => '',
            ],
            'night' => [
                'label' => 'Vol de nuit (JVN)',
                'short' => 'NUIT',
                'per' => self::PER_TRACK,
                'min_scope_hours' => 5.0,
                'min_track_hours' => 0.0,
                'cond' => 'night',
                'min_cond_hours' => 1.0,
                'prereq' => 'solo',
                'mode' => self::MODE_PROPOSE,
                'validator' => 'ip',
                'currency_days' => 90,
                'summary' => 'Vol de nuit et sous jumelles de vision nocturne, validé par un instructeur.',
                'real' => [
                    self::TRACK_HELI => 'FAA (14 CFR 61.109(c)) : 3 h de nuit dont 10 décollages et atterrissages ; jumelles de vision nocturne : formation au sol et en vol (61.31(k)). Maintien : 3 atterrissages de nuit en 90 jours (61.57(b)).',
                    self::TRACK_PLANE => 'FAA (14 CFR 61.109(a)) : 3 h de nuit dont 10 décollages et atterrissages. Maintien : 3 atterrissages complets de nuit en 90 jours (61.57(b)).',
                ],
                'data_note' => 'Les heures de nuit viennent du carnet de vol : le pilote les note sur ses vols, un instructeur les contresigne.',
            ],
            'ifr' => [
                'label' => 'Vol aux instruments (IFR)',
                'short' => 'IFR',
                'per' => self::PER_TRACK,
                'min_scope_hours' => 15.0,
                'min_track_hours' => 0.0,
                'cond' => 'ifr',
                'min_cond_hours' => 2.0,
                'prereq' => 'solo',
                'mode' => self::MODE_PROPOSE,
                'validator' => 'ip',
                'currency_days' => 180,
                'summary' => 'Vol sans visibilité extérieure, aux seuls instruments, validé par un instructeur.',
                'real' => [
                    self::TRACK_HELI => 'FAA (14 CFR 61.65(e)) : 40 h de vol aux instruments (réels ou simulés) et 50 h de navigation comme commandant de bord. Maintien : 6 approches en 6 mois (61.57(c)).',
                    self::TRACK_PLANE => 'FAA (14 CFR 61.65(d)) : 40 h de vol aux instruments (réels ou simulés) et 50 h de navigation comme commandant de bord. Maintien : 6 approches en 6 mois (61.57(c)).',
                ],
                'data_note' => 'Les heures aux instruments viennent du carnet de vol : le pilote les note sur ses vols, un instructeur les contresigne.',
            ],
            'form' => [
                'label' => 'Vol en patrouille',
                'short' => 'PATR',
                'per' => self::PER_TRACK,
                'min_scope_hours' => 8.0,
                'min_track_hours' => 0.0,
                'cond' => 'formation',
                'min_cond_hours' => 1.0,
                'prereq' => 'solo',
                'mode' => self::MODE_PROPOSE,
                'validator' => 'ip',
                'currency_days' => 90,
                'summary' => 'Vol en formation serrée avec d’autres appareils, validé par un instructeur.',
                'real' => [
                    self::TRACK_HELI => 'Tâche militaire évaluée en vol par un instructeur (programme d’entraînement de l’unité, TC 3-04.11) ; pas de minimum horaire civil.',
                    self::TRACK_PLANE => 'Tâche militaire évaluée en vol par un instructeur ; pas de minimum horaire civil.',
                ],
                'data_note' => 'Les heures en patrouille viennent du carnet de vol : le pilote les note sur ses vols, un instructeur les contresigne.',
            ],
        ];
    }

    /**
     * Conditions de vol notées au carnet (heures de nuit, aux instruments, en patrouille) : clé => libellé.
     *
     * @return array<string, string>
     */
    public static function conditions(): array
    {
        return ['night' => 'de nuit', 'ifr' => 'aux instruments', 'formation' => 'en patrouille'];
    }

    /**
     * Fonctions à bord notées au carnet : clé => libellé.
     *
     * @return array<string, string>
     */
    public static function crewRoles(): array
    {
        return [
            'pic' => 'Commandant de bord',
            'copilot' => 'Copilote',
            'student' => 'Élève (double commande)',
            'instructor' => 'Instructeur',
        ];
    }

    /** @return array<string, mixed>|null */
    public static function tier(string $key): ?array
    {
        return self::tiers()[$key] ?? null;
    }

    public static function tierLabel(string $key): string
    {
        return self::tiers()[$key]['label'] ?? 'Palier';
    }

    /**
     * Filière d'un type d'appareil : connu (FAMILIES) ou deviné par le genre relevé par le mod pour les autres.
     */
    public static function familyTrack(string $familyKey, string $kindFromData = ''): string
    {
        if (isset(self::FAMILY_TRACK[$familyKey])) {
            return self::FAMILY_TRACK[$familyKey];
        }

        return $kindFromData === 'plane' ? self::TRACK_PLANE : self::TRACK_HELI;
    }

    /**
     * Types d'appareils connus d'une filière : clé => libellé.
     *
     * @return array<string, string>
     */
    public static function knownFamilies(string $track): array
    {
        $labels = [
            'ch47' => 'CH-47 Chinook', 'ch53' => 'CH-53 Sea Stallion', 'uh60' => 'UH-60 Black Hawk', 'ah64' => 'AH-64 Apache',
            'ah1' => 'AH-1 Cobra', 'uh1' => 'UH-1 Huey', 'mh6' => 'MH-6 / AH-6 Little Bird', 'v22' => 'V-22 Osprey',
            'mi8' => 'Mi-8 / Mi-17 Hip', 'mi24' => 'Mi-24 Hind', 'mi28' => 'Mi-28 Havoc', 'ka52' => 'Ka-52 Alligator',
            'c130' => 'C-130 Hercules', 'a10' => 'A-10 Thunderbolt II', 'f16' => 'F-16 Fighting Falcon', 'fa18' => 'F/A-18 Hornet',
            'f35' => 'F-35 Lightning II', 'su25' => 'Su-25 Frogfoot',
        ];
        $out = [];
        foreach (self::FAMILY_TRACK as $key => $t) {
            if ($t === $track) {
                $out[$key] = $labels[$key];
            }
        }

        return $out;
    }

    /**
     * Code court d'une qualification créée par le cursus (unique dans la communauté, 64 caractères au plus).
     * PIL-H-SOLO, PIL-UH60-QT, PIL-CLASS-RHS_M_MELB-OPS…
     */
    public static function definitionCode(string $track, string $tierKey, ?string $familyKey): string
    {
        $tier = self::tier($tierKey);
        $short = $tier['short'] ?? strtoupper($tierKey);
        $mid = $familyKey !== null && $familyKey !== ''
            ? strtoupper((string) preg_replace('/[^A-Za-z0-9_]+/', '-', $familyKey))
            : (self::tracks()[$track]['short'] ?? 'H');

        return mb_substr('PIL-' . trim($mid, '-') . '-' . $short, 0, 64);
    }

    /** Nom affiché d'une qualification créée par le cursus : « Qualification de type UH-60 Black Hawk ». */
    public static function definitionName(string $track, string $tierKey, ?string $familyLabel): string
    {
        $label = self::tierLabel($tierKey);
        if ($familyLabel !== null && $familyLabel !== '') {
            return $label . ' ' . $familyLabel;
        }

        return $label . ' ' . (self::tracks()[$track]['noun'] ?? 'aéronef');
    }

    /** Périmètre d'heures d'un palier : « kind:heli » (toute la filière) ou « family:uh60 » (un type d'appareil). */
    public static function scopeFor(string $track, ?string $familyKey): string
    {
        return $familyKey !== null && $familyKey !== '' ? 'family:' . $familyKey : 'kind:' . $track;
    }
}
