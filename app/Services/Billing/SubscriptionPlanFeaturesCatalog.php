<?php

declare(strict_types=1);

namespace App\Services\Billing;

/**
 * Catalogue unique et typé des paramètres de formule (subscription_plans.features_json) : libellés français et
 * anglais, description, type, unité, groupe, bornes et valeur par défaut par palier.
 * Source de vérité pour le seed (bootstrap/community_platform_migration.php), l'administration des formules, la page
 * publique des formules et FEATURE-TIERS. Toute clé lue par le code doit y figurer (PlanFeaturesCatalogCoverageTest).
 *
 * Types : « bool » = module inclus ou non ; « int » = quota entier où 0 signifie illimité (l'administration affiche
 * une case « Illimité » au lieu du 0). Aucune clé de type liste n'existe aujourd'hui.
 * « read » = false : clé enregistrée dans les formules mais qu'aucun écran ne contrôle encore (modifier sa valeur ne
 * change rien pour les communautés).
 */
final class SubscriptionPlanFeaturesCatalog
{
    public const BOOL_FEATURES = [
        'documents',
        'messages',
        'personnel',
        'training',
        'equipment',
        'events',
        'courrier',
        'operational_board',
        'recruitment',
        'cooperation',
        'alerts',
        'atak',
        'atak_link_basic',
        'patch_studio',
        'analytics',
        'advanced_integrations',
        'community_create',
        'registry_featured',
        'pdf_exports',
        'year_review_collective',
        'pilot_qualifications',
        'qualification_catalog_bulk_import',
        'invite_link_custom_slug',
    ];

    public const INT_FEATURES = [
        'max_members',
        'max_training_courses',
        'max_catalog_qualification_imports',
        'max_real_issuer_imports',
        'max_custom_qualifications',
        'max_active_invite_links',
    ];

    /** Plafond le plus haut accepté pour un quota (au-delà : « Illimité »). */
    public const QUOTA_MAX = 100000;

    /**
     * Groupes d'affichage, dans l'ordre.
     *
     * @var array<string, array{label: string, label_en: string}>
     */
    public const GROUPS = [
        'members' => ['label' => 'Membres et organisation', 'label_en' => 'Members and organisation'],
        'academy' => ['label' => 'Academy et qualifications', 'label_en' => 'Academy and qualifications'],
        'recruitment' => ['label' => 'Recrutement', 'label_en' => 'Recruitment'],
        'game' => ['label' => 'ATAK et jeu', 'label_en' => 'ATAK and game'],
        'documents' => ['label' => 'Documents et doctrine', 'label_en' => 'Documents and doctrine'],
        'brand' => ['label' => 'Marque, visibilité et intégrations', 'label_en' => 'Brand, visibility and integrations'],
    ];

    /**
     * @return array<string, array{
     *   label: string, label_en: string, help: string, help_en: string, type: 'bool'|'int', group: string,
     *   read: bool, unit?: string, unit_en?: string, min?: int, max?: int
     * }>
     */
    public static function definitions(): array
    {
        $bool = static fn (string $group, string $label, string $labelEn, string $help, string $helpEn, bool $read = true): array => [
            'label' => $label, 'label_en' => $labelEn, 'help' => $help, 'help_en' => $helpEn,
            'type' => 'bool', 'group' => $group, 'read' => $read,
        ];
        $quota = static fn (string $group, string $label, string $labelEn, string $help, string $helpEn, string $unit, string $unitEn): array => [
            'label' => $label, 'label_en' => $labelEn, 'help' => $help, 'help_en' => $helpEn,
            'type' => 'int', 'group' => $group, 'read' => true,
            'unit' => $unit, 'unit_en' => $unitEn, 'min' => 1, 'max' => self::QUOTA_MAX,
        ];

        return [
            // Membres et organisation
            'max_members' => $quota('members', 'Nombre max. de membres', 'Max. number of members',
                'Comptes actifs dans la communauté. Les membres déjà inscrits ne sont jamais retirés.',
                'Active accounts in the community. Members already registered are never removed.',
                'membres', 'members'),
            'personnel' => $bool('members', 'Effectifs et ORBAT', 'Roster and ORBAT',
                'Fiches personnel, grades, unités et organigramme.',
                'Personnel files, ranks, units and organisation chart.', false),
            'messages' => $bool('members', 'Messagerie interne', 'Internal messaging',
                'Échanges privés entre membres de la communauté.',
                'Private exchanges between community members.'),
            'events' => $bool('members', 'Événements et calendrier', 'Events and calendar',
                'Création d’événements, inscriptions et planning (un quota mensuel peut s’appliquer sans ce module).',
                'Event creation, sign-ups and schedule (a monthly quota can apply without this module).'),
            'operational_board' => $bool('members', 'Mur opérationnel', 'Operations wall',
                'Permanences, consignes et tableau opérationnel.',
                'Duty rosters, standing orders and operations board.'),
            'alerts' => $bool('members', 'Alertes communauté', 'Community alerts',
                'Alertes et bandeaux d’information pour les membres.',
                'Alerts and information banners for members.'),
            'analytics' => $bool('members', 'Analytics organisation', 'Organization analytics',
                'Tableaux de bord et métriques de pilotage.',
                'Dashboards and management metrics.'),
            'pdf_exports' => $bool('members', 'Exports PDF des tableaux', 'PDF exports of tables',
                'Télécharger les tableaux du back-office (effectifs, dossier médical, heures de vol…) en PDF prêt à imprimer ou à envoyer.',
                'Download back-office tables (roster, medical file, flight hours…) as a PDF ready to print or send.'),
            'year_review_collective' => $bool('members', 'Rétrospective annuelle de l’unité', 'Unit year in review',
                'Bilan de l’année de toute la communauté (heures, kilomètres, SSE, tableau d’honneur) dans la rétrospective des joueurs. La rétrospective individuelle reste gratuite.',
                'Year review of the whole community (hours, kilometres, SSE, honour roll) in the players\' year in review. The individual year in review stays free.'),
            'community_create' => $bool('members', 'Création de communauté', 'Community creation',
                'Autorise la création d’une nouvelle communauté via ce palier.',
                'Allows creating a new community on this tier.', false),

            // Academy et qualifications
            'training' => $bool('academy', 'Formations', 'Training',
                'Parcours de l’Academy, Studio et suivi des apprenants.',
                'Academy courses, Studio and learner tracking.'),
            'max_training_courses' => $quota('academy', 'Nombre max. de parcours', 'Max. number of courses',
                'Parcours du catalogue de la communauté. Ceux déjà créés restent utilisables.',
                'Courses in the community catalogue. Courses already created stay usable.',
                'parcours', 'courses'),
            'pilot_qualifications' => $bool('academy', 'Cursus pilote automatique', 'Automatic pilot syllabus',
                'Qualifications de pilote attribuées ou proposées aux instructeurs d’après les heures de vol relevées en jeu.',
                'Pilot qualifications awarded or proposed to instructors based on the flight hours recorded in game.'),
            'qualification_catalog_bulk_import' => $bool('academy', 'Import d’un pays entier du catalogue de référence', 'Import a whole country from the reference catalogue',
                'Bouton « Tout importer » : toutes les qualifications d’un pays ou de l’OTAN en un clic.',
                '“Import all” button: all qualifications of a country or of NATO in one click.'),
            'max_catalog_qualification_imports' => $quota('academy', 'Qualifications importées du catalogue de référence', 'Qualifications imported from the reference catalogue',
                'Qualifications réelles importées dans le référentiel. Celles déjà importées restent utilisables.',
                'Real qualifications imported into the repository. Those already imported stay usable.',
                'qualifications', 'qualifications'),
            'max_real_issuer_imports' => $quota('academy', 'Émetteurs réels importés', 'Real issuers imported',
                'Écoles et centres réels importés depuis la page Émetteurs. L’émetteur d’une qualification importée suit toujours.',
                'Real schools and centres imported from the Issuers page. The issuer of an imported qualification always follows.',
                'émetteurs', 'issuers'),
            'max_custom_qualifications' => $quota('academy', 'Qualifications et brevets créés par la communauté', 'Qualifications and badges created by the community',
                'Qualifications et brevets créés dans le Studio ou le référentiel. Ceux déjà créés restent utilisables.',
                'Qualifications and badges created in the Studio or the repository. Those already created stay usable.',
                'créations', 'creations'),

            // Recrutement
            'recruitment' => $bool('recruitment', 'Recrutement et enrôlement', 'Recruitment and enlistment',
                'Candidatures, offres et parcours d’enrôlement.',
                'Applications, offers and enlistment pathways.', false),
            'invite_link_custom_slug' => $bool('recruitment', 'Liens d’invitation à adresse personnalisée', 'Invite links with a custom address',
                'Adresse choisie pour un lien d’invitation (ex. /i/tfj) au lieu d’un code généré.',
                'Chosen address for an invite link (e.g. /i/tfj) instead of a generated code.'),
            'max_active_invite_links' => $quota('recruitment', 'Liens d’invitation actifs', 'Active invite links',
                'Liens d’invitation utilisables en même temps. Les liens déjà actifs ne sont jamais coupés.',
                'Invite links usable at the same time. Links already active are never cut.',
                'liens actifs', 'active links'),

            // ATAK et jeu
            'atak' => $bool('game', 'Carte tactique ATAK', 'ATAK tactical map',
                'Carte, unités terrain et outils ATAK.',
                'Map, field units and ATAK tools.'),
            'atak_link_basic' => $bool('game', 'Liaison jeu de base', 'Basic game link',
                'Positions, comptes rendus et briefing envoyés depuis le jeu, même sans la carte tactique complète.',
                'Positions, reports and briefing sent from the game, even without the full tactical map.'),

            'patch_studio' => $bool('game', 'ATHENA Patch Studio (Pro+ minimum)', 'ATHENA Patch Studio (Pro+ minimum)',
                'Atelier graphique de patchs, ressources privées, exports et catalogue ATAK. Une formule effective Pro+ minimum reste requise.',
                'Patch editor, private assets, exports and ATAK catalogue. An effective Pro+ plan or higher is still required.'),

            // Documents et doctrine
            'documents' => $bool('documents', 'Documents', 'Documents',
                'Bibliothèque documentaire et pièces jointes.',
                'Document library and attachments.', false),
            'courrier' => $bool('documents', 'Courrier officiel', 'Official mail',
                'Bureau courrier, modèles, validation et archivage.',
                'Mail office, templates, approval and archiving.'),
            'equipment' => $bool('documents', 'Équipement', 'Equipment',
                'Manuels et fiches matériel.',
                'Manuals and equipment sheets.'),

            // Marque, visibilité et intégrations
            'registry_featured' => $bool('brand', 'Mise en avant dans le registre', 'Featured in the register',
                'Badge « mise en avant » sur la carte de la communauté et place parmi les premières du registre des unités.',
                '"Featured" badge on the community card and a place among the first in the unit register.'),
            'cooperation' => $bool('brand', 'Coopération inter-unités', 'Inter-unit cooperation',
                'Missions et annonces entre communautés.',
                'Missions and announcements between communities.'),
            'advanced_integrations' => $bool('brand', 'Intégrations avancées', 'Advanced integrations',
                'Clés d’accès et connecteurs externes.',
                'Access keys and external connectors.'),
        ];
    }

    /**
     * Paramètres rangés par groupe, dans l'ordre des groupes.
     *
     * @return array<string, list<string>>
     */
    public static function keysByGroup(): array
    {
        $out = array_fill_keys(array_keys(self::GROUPS), []);
        foreach (self::definitions() as $key => $def) {
            $out[$def['group']][] = $key;
        }

        return $out;
    }

    /** Clés enregistrées dans les formules mais qu'aucun écran ne contrôle encore. @return list<string> */
    public static function unreadKeys(): array
    {
        return array_keys(array_filter(self::definitions(), static fn (array $d): bool => !$d['read']));
    }

    /**
     * Défauts features_json par slug de plan (seed et clés absentes d’une formule).
     *
     * @return array<string, array<string, bool|int>>
     */
    public static function defaultsByPlanSlug(): array
    {
        return [
            'free' => [
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => false,
                'courrier' => false,
                'operational_board' => false,
                'recruitment' => true,
                'cooperation' => false,
                'alerts' => false,
                'atak' => false,
                'atak_link_basic' => true,
                'patch_studio' => false,
                'analytics' => false,
                'advanced_integrations' => false,
                'community_create' => true,
                'registry_featured' => false,
                'pdf_exports' => false,
                'year_review_collective' => false,
                'pilot_qualifications' => false,
                'qualification_catalog_bulk_import' => false,
                'invite_link_custom_slug' => false,
                'max_members' => 10,
                'max_training_courses' => 5,
                'max_catalog_qualification_imports' => 10,
                'max_real_issuer_imports' => 5,
                'max_custom_qualifications' => 5,
                'max_active_invite_links' => 2,
            ],
            'standard' => [
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => true,
                'courrier' => true,
                'operational_board' => true,
                'recruitment' => true,
                'cooperation' => false,
                'alerts' => true,
                'atak' => true,
                'atak_link_basic' => true,
                'patch_studio' => false,
                'analytics' => false,
                'advanced_integrations' => false,
                'community_create' => true,
                'registry_featured' => false,
                'pdf_exports' => true,
                'year_review_collective' => true,
                'pilot_qualifications' => true,
                'qualification_catalog_bulk_import' => false,
                'invite_link_custom_slug' => true,
                'max_members' => 25,
                'max_training_courses' => 25,
                'max_catalog_qualification_imports' => 50,
                'max_real_issuer_imports' => 25,
                'max_custom_qualifications' => 30,
                'max_active_invite_links' => 0,
            ],
            'pro' => [
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => true,
                'courrier' => true,
                'operational_board' => true,
                'recruitment' => true,
                'cooperation' => true,
                'alerts' => true,
                'atak' => true,
                'atak_link_basic' => true,
                'patch_studio' => false,
                'analytics' => true,
                'advanced_integrations' => false,
                'community_create' => true,
                'registry_featured' => true,
                'pdf_exports' => true,
                'year_review_collective' => true,
                'pilot_qualifications' => true,
                'qualification_catalog_bulk_import' => true,
                'invite_link_custom_slug' => true,
                'max_members' => 50,
                'max_training_courses' => 100,
                'max_catalog_qualification_imports' => 0,
                'max_real_issuer_imports' => 0,
                'max_custom_qualifications' => 0,
                'max_active_invite_links' => 0,
            ],
            'pro_plus' => [
                'documents' => true,
                'messages' => true,
                'personnel' => true,
                'training' => true,
                'equipment' => true,
                'events' => true,
                'courrier' => true,
                'operational_board' => true,
                'recruitment' => true,
                'cooperation' => true,
                'alerts' => true,
                'atak' => true,
                'atak_link_basic' => true,
                'patch_studio' => true,
                'analytics' => true,
                'advanced_integrations' => true,
                'community_create' => true,
                'registry_featured' => true,
                'pdf_exports' => true,
                'year_review_collective' => true,
                'pilot_qualifications' => true,
                'qualification_catalog_bulk_import' => true,
                'invite_link_custom_slug' => true,
                'max_members' => 80,
                'max_training_courses' => 0,
                'max_catalog_qualification_imports' => 0,
                'max_real_issuer_imports' => 0,
                'max_custom_qualifications' => 0,
                'max_active_invite_links' => 0,
            ],
        ];
    }

    /**
     * @return array<string, bool|int>
     */
    public static function defaultsForSlug(string $slug): array
    {
        $all = self::defaultsByPlanSlug();

        return $all[$slug] ?? $all['free'];
    }

    /** Libellé court pour la page d’upgrade. */
    public static function featureLabel(string $featureKey): string
    {
        $defs = self::definitions();

        return (string) ($defs[$featureKey]['label'] ?? $featureKey);
    }

    /**
     * features_json d'une ligne subscription_plans (tableau vide si absent ou illisible).
     *
     * @param array<string, mixed> $planRow
     *
     * @return array<string, mixed>
     */
    public static function decodeFeatures(array $planRow): array
    {
        $raw = $planRow['features_json'] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Valeur appliquée de chaque paramètre du catalogue : celle de la formule, sinon le défaut du palier (comme
     * FeatureGateService::allowsWithCatalogDefault / intWithCatalogDefault).
     *
     * @param array<string, mixed> $planRow
     *
     * @return array{values: array<string, bool|int>, defaulted: list<string>}
     */
    public static function planValues(array $planRow): array
    {
        $stored = self::decodeFeatures($planRow);
        $defaults = self::defaultsForSlug((string) ($planRow['slug'] ?? 'free'));
        $values = [];
        $defaulted = [];
        foreach (self::definitions() as $key => $def) {
            $has = array_key_exists($key, $stored);
            $raw = $has ? $stored[$key] : ($defaults[$key] ?? ($def['type'] === 'bool' ? false : 0));
            if (!$has) {
                $defaulted[] = $key;
            }
            $values[$key] = $def['type'] === 'bool' ? !empty($raw) : max(0, (int) $raw);
        }

        return ['values' => $values, 'defaulted' => $defaulted];
    }

    /**
     * Clés présentes dans features_json mais absentes du catalogue (conservées telles quelles à l'enregistrement).
     *
     * @param array<string, mixed> $features
     *
     * @return list<string>
     */
    public static function unknownKeys(array $features): array
    {
        return array_values(array_diff(array_map('strval', array_keys($features)), array_keys(self::definitions())));
    }

    /** Quota coché « Illimité » dans le formulaire ? */
    public static function unlimitedChecked(array $input, string $key): bool
    {
        return !empty($input['feature_' . $key . '_unlimited']);
    }

    /**
     * Contrôle serveur du formulaire : interrupteurs (0/1) et quotas (entier entre min et max, ou « Illimité »).
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, string> clé => message d'erreur
     */
    public static function validateForm(array $input): array
    {
        $errors = [];
        foreach (self::definitions() as $key => $def) {
            $raw = $input['feature_' . $key] ?? null;
            if ($def['type'] === 'bool') {
                if ($raw !== null && !in_array((string) $raw, ['0', '1', 'on', 'true', 'false', ''], true)) {
                    $errors[$key] = $def['label'] . ' : valeur non reconnue.';
                }
                continue;
            }
            if (self::unlimitedChecked($input, $key) || $raw === null) {
                continue;
            }
            $raw = trim((string) $raw);
            $min = (int) ($def['min'] ?? 1);
            $max = (int) ($def['max'] ?? self::QUOTA_MAX);
            if (preg_match('/^\d{1,9}$/', $raw) !== 1) {
                $errors[$key] = $def['label'] . ' : indiquez un nombre entier, ou cochez « Illimité ».';
            } elseif ((int) $raw < $min || (int) $raw > $max) {
                $errors[$key] = $def['label'] . ' : entre ' . $min . ' et ' . number_format($max, 0, ',', ' ') . ', ou cochez « Illimité ».';
            }
        }

        return $errors;
    }

    /**
     * Construit features_json à partir des interrupteurs / champs numériques du formulaire admin. Les clés inconnues
     * du catalogue déjà présentes dans $existing sont toujours conservées ; « Illimité » coché enregistre 0.
     *
     * @param array<string, mixed> $input
     * @param array<string, mixed> $existing
     * @param string $slug palier, pour le plafond par défaut d'un champ vidé
     *
     * @return array<string, mixed>
     */
    public static function buildFeaturesFromForm(array $input, array $existing = [], string $slug = ''): array
    {
        $out = $existing;
        foreach (self::BOOL_FEATURES as $key) {
            $raw = $input['feature_' . $key] ?? null;
            $out[$key] = $raw !== null && !in_array((string) $raw, ['0', 'false', ''], true);
        }
        $catalog = $slug !== '' ? self::defaultsForSlug($slug) : [];
        foreach (self::INT_FEATURES as $key) {
            if (self::unlimitedChecked($input, $key)) {
                $out[$key] = 0;
                continue;
            }
            $raw = $input['feature_' . $key] ?? null;
            if (!is_numeric(is_string($raw) ? trim($raw) : $raw)) {
                // Champ vidé ou absent : on garde le plafond actuel (sinon celui du catalogue), jamais « illimité » par accident.
                $raw = $existing[$key] ?? ($catalog[$key] ?? 0);
            }
            $out[$key] = max(0, min(self::QUOTA_MAX, (int) $raw));
        }

        return $out;
    }

    /** Valeur lisible : Oui / Non, nombre, ou « Illimité » (quota à 0). */
    public static function formatValue(string $key, mixed $value): string
    {
        $def = self::definitions()[$key] ?? null;
        if ($def === null) {
            return is_scalar($value) ? (is_bool($value) ? ($value ? 'Oui' : 'Non') : (string) $value) : (string) json_encode($value);
        }
        if ($def['type'] === 'bool') {
            return !empty($value) ? 'Oui' : 'Non';
        }
        $n = max(0, (int) $value);

        return $n === 0 ? 'Illimité' : number_format($n, 0, ',', ' ');
    }

    /**
     * Paramètres modifiés entre deux features_json (historique des modifications).
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return list<array{key: string, label: string, from: string, to: string}>
     */
    public static function diff(array $before, array $after): array
    {
        $defs = self::definitions();
        $out = [];
        $keys = array_unique(array_merge(array_keys($defs), array_map('strval', array_keys($before)), array_map('strval', array_keys($after))));
        foreach ($keys as $key) {
            $hasB = array_key_exists($key, $before);
            $hasA = array_key_exists($key, $after);
            if (!$hasB && !$hasA) {
                continue;
            }
            $b = $hasB ? $before[$key] : null;
            $a = $hasA ? $after[$key] : null;
            $type = $defs[$key]['type'] ?? null;
            $same = $type === 'bool' ? (!empty($b) === !empty($a)) : ($type === 'int' ? ((int) $b === (int) $a && $hasA === $hasB) : $b === $a);
            if ($same) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => (string) ($defs[$key]['label'] ?? $key),
                'from' => $hasB ? self::formatValue($key, $b) : '—',
                'to' => $hasA ? self::formatValue($key, $a) : '—',
            ];
        }

        return $out;
    }
}
