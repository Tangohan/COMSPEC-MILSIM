<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Référentiel des fiches de renseignement simplifiées.
 *
 * Une fiche porte un type (badge bleu), un ou plusieurs thèmes (badges colorés),
 * un degré d'urgence, une discipline de recueil et un état de suivi. Les
 * libellés servent l'affichage — côté terrain comme côté bureau, pour que
 * l'ATAK et le portail parlent pareil.
 */
final class SseFieldNoteCatalog
{
    public const BODY_MAX_LENGTH = 1000;
    public const TITLE_MAX_LENGTH = 180;
    public const ATTACHMENTS_MAX = 4;
    public const THEMES_MAX = 4;

    public const DEFAULT_KIND = 'FRM';
    public const DEFAULT_URGENCY = 'routine';
    public const DEFAULT_STATUS = 'transmise';
    public const DEFAULT_SOURCE = '';

    /** @var list<string> */
    public const TONES = ['critical', 'warning', 'caution', 'stable', 'info', 'neutral'];

    /** @var array<string, string> */
    public const TONE_COLORS = [
        'critical' => '#dc2626',
        'warning' => '#ea580c',
        'caution' => '#ca8a04',
        'stable' => '#16a34a',
        'info' => '#2563eb',
        'neutral' => '#4b5563',
    ];

    /**
     * Types de fiche. La clé est le sigle affiché en badge.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const KINDS = [
        'FRM' => [
            'label' => 'Fiche de renseignement de mission',
            'hint' => 'Ce que vous avez constaté pendant la mission en cours.',
        ],
        'FRO' => [
            'label' => 'Fiche d’observation',
            'hint' => 'Un fait observé, sans lien direct avec la mission du jour.',
        ],
        'FRC' => [
            'label' => 'Fiche de contact',
            'hint' => 'Un échange avec une personne, un groupe ou une autorité locale.',
        ],
        'FRA' => [
            'label' => 'Fiche d’ambiance',
            'hint' => 'Le climat général d’un secteur : attitude de la population, tensions.',
        ],
        'FRT' => [
            'label' => 'Fiche technique',
            'hint' => 'Matériel, véhicule, installation, marquage ou signe distinctif relevé.',
        ],
    ];

    /**
     * Thèmes opérationnels. « tone » colore le badge ; « hint » précise le périmètre.
     *
     * @var array<string, array{label: string, hint: string, tone: string}>
     */
    public const THEMES = [
        'TERROR' => [
            'label' => 'Terrorisme',
            'hint' => 'Attentats, cellules terroristes, réseaux, financement, recrutement.',
            'tone' => 'critical',
        ],
        'INSURG' => [
            'label' => 'Insurrection',
            'hint' => 'Mouvements insurgés, rébellion, groupes armés non étatiques.',
            'tone' => 'critical',
        ],
        'CBRNE' => [
            'label' => 'CBRNE',
            'hint' => 'Armes chimiques, biologiques, radiologiques, nucléaires, explosives.',
            'tone' => 'critical',
        ],
        'ARMEMENT' => [
            'label' => 'Armement / Matériel',
            'hint' => 'Armes, munitions, équipements, véhicules, systèmes d’armes.',
            'tone' => 'warning',
        ],
        'PERSON' => [
            'label' => 'Personnes / Cibles',
            'hint' => 'Personnalités, cibles de haute valeur, leaders, combattants, personnel clé.',
            'tone' => 'warning',
        ],
        'PLANIF' => [
            'label' => 'Planification',
            'hint' => 'Intentions, plans, objectifs, calendriers d’opérations.',
            'tone' => 'warning',
        ],
        'LOGIST' => [
            'label' => 'Logistique',
            'hint' => 'Ravitaillement, transport, stockage, chaînes d’approvisionnement.',
            'tone' => 'caution',
        ],
        'COMMS' => [
            'label' => 'Communications',
            'hint' => 'Réseaux, transmissions, signaux, cyber, guerre électronique.',
            'tone' => 'caution',
        ],
        'FINANCE' => [
            'label' => 'Financement',
            'hint' => 'Flux financiers, blanchiment, ressources, économie illicite.',
            'tone' => 'caution',
        ],
        'RECRUT' => [
            'label' => 'Recrutement',
            'hint' => 'Enrôlement, radicalisation, formation, entraînement.',
            'tone' => 'caution',
        ],
        'INFRA' => [
            'label' => 'Infrastructures',
            'hint' => 'Bâtiments, installations, sites sensibles, points nodaux.',
            'tone' => 'stable',
        ],
        'ORGAN' => [
            'label' => 'Organisation',
            'hint' => 'Structures de commandement, hiérarchie, unités, groupes.',
            'tone' => 'stable',
        ],
        'MOUV' => [
            'label' => 'Mouvements',
            'hint' => 'Déplacements, infiltrations, exfiltrations, migrations.',
            'tone' => 'stable',
        ],
        'SECUR' => [
            'label' => 'Sécurité / Protection',
            'hint' => 'Mesures de sécurité, protection de forces, contre-ingérence.',
            'tone' => 'stable',
        ],
        'CIVIL' => [
            'label' => 'Environnement civil',
            'hint' => 'Population, sentiments, leaders communautaires, humanitaire.',
            'tone' => 'info',
        ],
        'METEO' => [
            'label' => 'Météo / Terrain',
            'hint' => 'Conditions météo, terrain, environnement opérationnel.',
            'tone' => 'info',
        ],
        'GENERAL' => [
            'label' => 'Général / Divers',
            'hint' => 'Renseignement non catégorisable, informations contextuelles.',
            'tone' => 'info',
        ],
    ];

    /**
     * Anciens codes conservés pour relire les fiches déjà transmises.
     *
     * @var array<string, string>
     */
    public const LEGACY_THEMES = [
        'securite_publique' => 'SECUR',
        'menace_armee' => 'INSURG',
        'engins_explosifs' => 'CBRNE',
        'ordre_public' => 'INSURG',
        'trafics' => 'FINANCE',
        'mouvements' => 'MOUV',
        'population' => 'CIVIL',
        'infrastructures' => 'INFRA',
        'communications' => 'COMMS',
        'logistique' => 'LOGIST',
        'environnement' => 'METEO',
        'divers' => 'GENERAL',
    ];

    /**
     * @var array<string, array{label: string, hint: string, tone: string}>
     */
    public const URGENCIES = [
        'critique' => [
            'label' => 'Critique',
            'hint' => 'Menace imminente : remonter immédiatement au poste de commandement.',
            'tone' => 'critical',
        ],
        'urgent' => [
            'label' => 'Urgent',
            'hint' => 'À traiter sans délai, dans les prochaines heures.',
            'tone' => 'warning',
        ],
        'normal' => [
            'label' => 'Normal',
            'hint' => 'À regarder dans le cours de la journée.',
            'tone' => 'caution',
        ],
        'routine' => [
            'label' => 'Routine',
            'hint' => 'À exploiter dans le cours normal du travail.',
            'tone' => 'neutral',
        ],
    ];

    /**
     * Anciens degrés d'urgence encore présents en base.
     *
     * @var array<string, string>
     */
    public const URGENCY_ALIASES = [
        'immediate' => 'critique',
        'priorite' => 'urgent',
    ];

    /**
     * Discipline de recueil. La clé est le sigle métier affiché.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const SOURCES = [
        'HUMINT' => [
            'label' => 'Renseignement humain',
            'hint' => 'Témoignage, contact, interrogation, source humaine.',
        ],
        'IMINT' => [
            'label' => 'Imagerie',
            'hint' => 'Photographie, drone, satellite, observation optique.',
        ],
        'SIGINT' => [
            'label' => 'Signaux',
            'hint' => 'Écoutes, transmissions, guerre électronique.',
        ],
        'OSINT' => [
            'label' => 'Sources ouvertes',
            'hint' => 'Presse, réseaux, documents publics, rumeur vérifiable.',
        ],
        'TECHINT' => [
            'label' => 'Technique',
            'hint' => 'Matériel saisi, analyse d’arme ou d’engin.',
        ],
        'MASINT' => [
            'label' => 'Mesures et signatures',
            'hint' => 'Détections physiques, chimiques, acoustiques, radiologiques.',
        ],
        'GEOINT' => [
            'label' => 'Géospatial',
            'hint' => 'Cartographie, relief, occupation du sol, itinéraires.',
        ],
    ];

    /** @var array<string, string> */
    public const STATUSES = [
        'brouillon' => 'Brouillon',
        'transmise' => 'Transmise',
        'prise_en_compte' => 'Prise en compte',
        'exploitee' => 'Exploitée',
        'sans_suite' => 'Classée sans suite',
    ];

    /** @var array<string, string> */
    public const ORIGINS = [
        'web' => 'Bureau SSE',
        'atak' => 'ATAK',
        'arma' => 'Terrain (Arma)',
    ];

    /** @var array<string, string> */
    public const ATTACHMENT_KINDS = [
        'photo' => 'Photographie',
        'capture' => 'Capture d’écran',
        'document' => 'Document',
        'croquis' => 'Croquis',
    ];

    /** @return array<string, string> */
    public static function kindOptions(): array
    {
        $out = [];
        foreach (self::KINDS as $code => $def) {
            $out[$code] = $def['label'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function themeOptions(): array
    {
        $out = [];
        foreach (self::THEMES as $code => $def) {
            $out[$code] = $code . ' — ' . $def['label'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function urgencyOptions(): array
    {
        $out = [];
        foreach (self::URGENCIES as $code => $def) {
            $out[$code] = $def['label'];
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function sourceOptions(): array
    {
        $out = [];
        foreach (self::SOURCES as $code => $def) {
            $out[$code] = $code . ' — ' . $def['label'];
        }

        return $out;
    }

    public static function normalizeKind(mixed $value): string
    {
        $code = strtoupper(trim((string) $value));

        return isset(self::KINDS[$code]) ? $code : self::DEFAULT_KIND;
    }

    public static function kindLabel(string $code): string
    {
        return self::KINDS[strtoupper($code)]['label'] ?? self::KINDS[self::DEFAULT_KIND]['label'];
    }

    public static function normalizeUrgency(mixed $value): string
    {
        $code = strtolower(trim((string) $value));
        $code = self::URGENCY_ALIASES[$code] ?? $code;

        return isset(self::URGENCIES[$code]) ? $code : self::DEFAULT_URGENCY;
    }

    public static function urgencyLabel(string $code): string
    {
        $normalized = self::normalizeUrgency($code);

        return self::URGENCIES[$normalized]['label'] ?? self::URGENCIES[self::DEFAULT_URGENCY]['label'];
    }

    public static function normalizeStatus(mixed $value): string
    {
        $code = strtolower(trim((string) $value));

        return isset(self::STATUSES[$code]) ? $code : self::DEFAULT_STATUS;
    }

    public static function statusLabel(string $code): string
    {
        return self::STATUSES[strtolower($code)] ?? self::STATUSES[self::DEFAULT_STATUS];
    }

    public static function originLabel(string $code): string
    {
        return self::ORIGINS[strtolower($code)] ?? self::ORIGINS['web'];
    }

    public static function normalizeSource(mixed $value): string
    {
        $code = strtoupper(trim((string) $value));
        if ($code === '' || !isset(self::SOURCES[$code])) {
            return self::DEFAULT_SOURCE;
        }

        return $code;
    }

    public static function sourceLabel(string $code): string
    {
        $normalized = self::normalizeSource($code);
        if ($normalized === '' || !isset(self::SOURCES[$normalized])) {
            return '';
        }

        return self::SOURCES[$normalized]['label'];
    }

    public static function normalizeThemeCode(mixed $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }
        $upper = strtoupper($raw);
        if (isset(self::THEMES[$upper])) {
            return $upper;
        }
        $lower = strtolower($raw);

        return self::LEGACY_THEMES[$lower] ?? '';
    }

    public static function themeLabel(string $code): string
    {
        $normalized = self::normalizeThemeCode($code);
        if ($normalized === '' || !isset(self::THEMES[$normalized])) {
            return $code;
        }

        return self::THEMES[$normalized]['label'];
    }

    public static function themeTone(string $code): string
    {
        $normalized = self::normalizeThemeCode($code);

        return self::THEMES[$normalized]['tone'] ?? 'neutral';
    }

    public static function themeColor(string $code): string
    {
        $tone = self::themeTone($code);

        return self::TONE_COLORS[$tone] ?? self::TONE_COLORS['neutral'];
    }

    public static function attachmentKindLabel(string $code): string
    {
        return self::ATTACHMENT_KINDS[strtolower($code)] ?? self::ATTACHMENT_KINDS['photo'];
    }

    /**
     * Garde uniquement des thèmes connus, sans doublon, dans la limite fixée.
     * Les anciens codes sont traduits vers le référentiel courant.
     *
     * @param mixed $value liste, chaîne JSON ou liste séparée par des virgules
     * @return list<string>
     */
    public static function normalizeThemes(mixed $value): array
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            $value = is_array($decoded) ? $decoded : explode(',', $trimmed);
        }
        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $raw) {
            $code = self::normalizeThemeCode($raw);
            if ($code === '' || in_array($code, $out, true)) {
                continue;
            }
            $out[] = $code;
            if (count($out) >= self::THEMES_MAX) {
                break;
            }
        }

        return $out;
    }

    public static function normalizeTitle(mixed $value): string
    {
        $title = trim((string) $value);
        $title = preg_replace('/\s+/u', ' ', $title) ?? $title;
        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $title = mb_substr($title, 0, self::TITLE_MAX_LENGTH);
        }

        return $title;
    }

    /**
     * Coupe le texte à la limite affichée dans les rédacteurs (web et ATAK).
     */
    public static function normalizeBody(mixed $value): string
    {
        $body = trim((string) $value);
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        if (mb_strlen($body) > self::BODY_MAX_LENGTH) {
            $body = mb_substr($body, 0, self::BODY_MAX_LENGTH);
        }

        return $body;
    }

    /**
     * Référentiel complet, tel qu'exposé au client ATAK pour afficher les mêmes
     * libellés que le portail.
     *
     * @return array<string, mixed>
     */
    public static function clientCatalog(): array
    {
        $kinds = [];
        foreach (self::KINDS as $code => $def) {
            $kinds[] = ['code' => $code, 'label' => $def['label'], 'hint' => $def['hint']];
        }
        $themes = [];
        foreach (self::THEMES as $code => $def) {
            $themes[] = [
                'code' => $code,
                'label' => $def['label'],
                'hint' => $def['hint'],
                'tone' => $def['tone'],
                'color' => self::themeColor($code),
            ];
        }
        $urgencies = [];
        foreach (self::URGENCIES as $code => $def) {
            $urgencies[] = [
                'code' => $code,
                'label' => $def['label'],
                'hint' => $def['hint'],
                'tone' => $def['tone'],
            ];
        }
        $sources = [];
        foreach (self::SOURCES as $code => $def) {
            $sources[] = ['code' => $code, 'label' => $def['label'], 'hint' => $def['hint']];
        }

        return [
            'body_max_length' => self::BODY_MAX_LENGTH,
            'title_max_length' => self::TITLE_MAX_LENGTH,
            'attachments_max' => self::ATTACHMENTS_MAX,
            'themes_max' => self::THEMES_MAX,
            'default_kind' => self::DEFAULT_KIND,
            'default_urgency' => self::DEFAULT_URGENCY,
            'kinds' => $kinds,
            'themes' => $themes,
            'urgencies' => $urgencies,
            'sources' => $sources,
        ];
    }
}
