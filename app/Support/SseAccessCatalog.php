<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue des droits d'accès du portail SSE : sections, niveaux de droit, types de bénéficiaires, profils de droit
 * « d'origine » (ce que les gardes faisaient avant les règles) et modèles prêts à l'emploi.
 *
 * Aucune règle n'est ici : une règle (table sse_access_rules) ne fait que restreindre ou étendre ces profils, par
 * communauté. Les profils d'origine sont la seule description des anciennes gardes (SseAccessPolicy les lit ; les
 * contrôleurs ne les répètent plus) : tant qu'aucune règle n'existe, la décision est exactement celle d'avant.
 *
 * Niveaux (ordre croissant d'étendue) : voir l'existence, lire, écrire, verser, supprimer, exporter, gérer. Un refus
 * sur « existence » ou « lire » emporte les niveaux qui en dépendent (voir DEPENDENTS) : on n'écrit pas ce qu'on ne lit pas.
 */
final class SseAccessCatalog
{
    public const LEVEL_EXISTS = 'exists';
    public const LEVEL_READ = 'read';
    public const LEVEL_WRITE = 'write';
    public const LEVEL_ADD = 'add';
    public const LEVEL_DELETE = 'delete';
    public const LEVEL_EXPORT = 'export';
    public const LEVEL_MANAGE = 'manage';

    /** @var array<string, array{label: string, hint: string}> */
    public const LEVELS = [
        self::LEVEL_EXISTS => ['label' => 'Voir l’existence', 'hint' => 'La section apparaît dans le bureau et dans les compteurs, sans qu’on puisse l’ouvrir.'],
        self::LEVEL_READ => ['label' => 'Lire', 'hint' => 'Ouvrir et consulter les pages de la section.'],
        self::LEVEL_WRITE => ['label' => 'Écrire', 'hint' => 'Modifier ce qui existe : fiches, statuts, annotations.'],
        self::LEVEL_ADD => ['label' => 'Verser', 'hint' => 'Ajouter de nouveaux éléments : photos, pièces, fiches, extractions.'],
        self::LEVEL_DELETE => ['label' => 'Supprimer', 'hint' => 'Retirer, archiver ou détacher des éléments.'],
        self::LEVEL_EXPORT => ['label' => 'Exporter', 'hint' => 'Sortir du bureau : PDF, impression, téléchargement, emport.'],
        self::LEVEL_MANAGE => ['label' => 'Gérer', 'hint' => 'Régler la section : réglages, tri, rattachements, dérogations propres au bureau.'],
    ];

    /**
     * Niveaux dont un refus emporte d'autres niveaux de la même section.
     *
     * @var array<string, list<string>>
     */
    public const DEPENDENTS = [
        self::LEVEL_EXISTS => [self::LEVEL_READ, self::LEVEL_WRITE, self::LEVEL_ADD, self::LEVEL_DELETE, self::LEVEL_EXPORT],
        self::LEVEL_READ => [self::LEVEL_WRITE, self::LEVEL_ADD, self::LEVEL_DELETE, self::LEVEL_EXPORT],
    ];

    public const SUBJECT_ROLE = 'role';
    public const SUBJECT_UNIT = 'unit';
    public const SUBJECT_UNIT_TREE = 'unit_tree';
    public const SUBJECT_USER = 'user';
    /** Position administrative (« En service actif », positions créées par la communauté) : roles.id du rôle d'affichage. */
    public const SUBJECT_POSITION = 'position';
    /** Fonction (emploi) du référentiel de la communauté : personnel_job_roles.id. */
    public const SUBJECT_JOB = 'job';
    /** Qualification valide (non expirée, non suspendue) : personnel_qualification_definitions.id. Court : subject_type = VARCHAR(12). */
    public const SUBJECT_QUALIF = 'qualif';

    /** @var array<string, string> */
    public const SUBJECT_TYPES = [
        self::SUBJECT_ROLE => 'Niveau d’accès',
        self::SUBJECT_UNIT => 'Unité',
        self::SUBJECT_UNIT_TREE => 'Unité et sous-unités',
        self::SUBJECT_USER => 'Identité',
        self::SUBJECT_POSITION => 'Position de service',
        self::SUBJECT_JOB => 'Fonction',
        self::SUBJECT_QUALIF => 'Qualification',
    ];

    /**
     * Les trois colonnes de « Qui peut quoi » : le niveau qui range la puce, et les niveaux qu'un accord simple pose.
     * Un accord « écrire » donne aussi voir ; « gérer » donne tout.
     *
     * @var array<string, array{label: string, shown: string, grant: list<string>}>
     */
    public const SIMPLE_ACCESS = [
        'voir' => ['label' => 'Voir', 'shown' => self::LEVEL_READ, 'grant' => [self::LEVEL_EXISTS, self::LEVEL_READ]],
        'ecrire' => ['label' => 'Écrire', 'shown' => self::LEVEL_WRITE, 'grant' => [self::LEVEL_EXISTS, self::LEVEL_READ, self::LEVEL_WRITE, self::LEVEL_ADD]],
        'gerer' => ['label' => 'Gérer', 'shown' => self::LEVEL_MANAGE, 'grant' => [self::LEVEL_EXISTS, self::LEVEL_READ, self::LEVEL_WRITE, self::LEVEL_ADD, self::LEVEL_DELETE, self::LEVEL_EXPORT, self::LEVEL_MANAGE]],
    ];

    /**
     * Modèles « en situation » : chaque étape désigne un bénéficiaire que la personne choisit (position, qualification…)
     * et ce qu'on lui accorde sur quelles sections. Sections null : toutes.
     *
     * @var array<string, array{label: string, hint: string, steps: list<array{label: string, kinds: list<string>, access: string, sections: list<string>|null}>}>
     */
    public const RECIPES = [
        'renseignement' => [
            'label' => 'Renseignement',
            'hint' => 'Les positions de commandement voient tout ; les titulaires de la qualification Analyste écrivent.',
            'steps' => [
                ['label' => 'Positions qui voient tout', 'kinds' => [self::SUBJECT_POSITION, self::SUBJECT_JOB], 'access' => 'voir', 'sections' => null],
                ['label' => 'Qualification de ceux qui écrivent', 'kinds' => [self::SUBJECT_QUALIF], 'access' => 'ecrire', 'sections' => ['fichiers', 'photos', 'papiers', 'extractions', 'dossiers', 'analyse', 'terrain']],
            ],
        ],
        'terrain' => [
            'label' => 'Fiches de renseignement ouvertes',
            'hint' => 'Les titulaires d’une qualification rédigent les fiches de renseignement ; une position les consulte.',
            'steps' => [
                ['label' => 'Qualification de ceux qui rédigent', 'kinds' => [self::SUBJECT_QUALIF], 'access' => 'ecrire', 'sections' => ['terrain']],
                ['label' => 'Position qui consulte', 'kinds' => [self::SUBJECT_POSITION, self::SUBJECT_JOB], 'access' => 'voir', 'sections' => ['terrain']],
            ],
        ],
        'responsable' => [
            'label' => 'Une position gère les dossiers',
            'hint' => 'Une position (chef, adjoint…) gère les dossiers d’enquête et l’analyse, quelle que soit son unité.',
            'steps' => [
                ['label' => 'Position qui gère', 'kinds' => [self::SUBJECT_POSITION, self::SUBJECT_JOB], 'access' => 'gerer', 'sections' => ['dossiers', 'analyse']],
            ],
        ],
    ];

    public const EFFECT_ALLOW = 'allow';
    public const EFFECT_DENY = 'deny';

    /** @var array<string, string> */
    public const EFFECTS = [
        self::EFFECT_ALLOW => 'Accorder',
        self::EFFECT_DENY => 'Refuser',
    ];

    /** Longueur minimale d'un motif (dérogation individuelle, refus, droit de gérer). */
    public const REASON_MIN = 5;
    public const REASON_MAX = 500;

    /** Plafond de règles actives par communauté et de changements par heure et par personne. */
    public const MAX_ACTIVE_RULES = 2000;
    public const CHANGES_PER_HOUR = 60;

    /**
     * Profils d'origine : droits qui ouvraient autrefois chaque garde. « account » exige un compte identifié (pas une
     * session invitée par code) ; « any » liste les droits dont un seul suffit. « entrant » : la porte du portail.
     * « none » : la garde n'existe pas (niveau sans objet pour la section).
     *
     * @var array<string, array{label: string, account: bool, any: list<string>}>
     */
    public const PROFILES = [
        'entrant' => ['label' => 'Toute personne entrée au bureau', 'account' => false, 'any' => []],
        'account' => ['label' => 'Tout compte identifié entré au bureau', 'account' => true, 'any' => []],
        'manage' => ['label' => 'Gestion des dossiers SSE', 'account' => true, 'any' => ['atak.sse.case.manage', 'atak.sse.grant', 'admin.access']],
        'files_manage' => ['label' => 'Gestion des fichiers ou des dossiers SSE', 'account' => true, 'any' => ['atak.sse.case.manage', 'atak.sse.grant', 'atak.sse.files.admin', 'admin.access']],
        'files_admin' => ['label' => 'Gestion des fichiers SSE', 'account' => true, 'any' => ['atak.sse.files.admin', 'admin.access']],
        'grant' => ['label' => 'Attribution des accès SSE', 'account' => true, 'any' => ['atak.sse.grant', 'admin.access']],
        'export' => ['label' => 'Export des dossiers SSE', 'account' => true, 'any' => ['atak.sse.export', 'admin.access']],
        'lore' => ['label' => 'Préparation du lore des sessions', 'account' => true, 'any' => ['atak.sse.lore.manage', 'admin.access']],
        'field_write' => ['label' => 'Rédaction des fiches de renseignement', 'account' => true, 'any' => ['atak.sse.access', 'atak.sse.case.manage', 'admin.access']],
        'none' => ['label' => 'Sans objet', 'account' => true, 'any' => []],
        // Refusé à tous tant qu'une règle d'accord ne l'ouvre pas : ni gestionnaire SSE ni administrateur n'y suffisent (SseAccessPolicy::legacyAllows).
        'sealed' => ['label' => 'Fermé par défaut : seule une règle d’accord explicite l’ouvre', 'account' => true, 'any' => ['sse.sealed.never']],
    ];

    /**
     * Sections du portail : libellé, explication, chemins (après « atak/sse/ ») et profil d'origine de chaque niveau.
     * « exists » suit toujours le profil de « read ».
     *
     * « sealed » : section fermée par défaut, jamais comprise dans « toutes les sections » (modèles, accords simples).
     *
     * @var array<string, array{label: string, hint: string, paths: list<string>, legacy: array<string, string>, sealed?: bool}>
     */
    public const SECTIONS = [
        'fichiers' => [
            'label' => 'Fichiers et sujets',
            'hint' => 'Passage aux fichiers, fiches des sujets, alias, liens, notes, mentions, fiches SEEK II et personnes du référentiel.',
            'paths' => ['fichiers', 'identites', 'personnes', 'avis'],
            'legacy' => ['read' => 'entrant', 'write' => 'files_manage', 'add' => 'account', 'delete' => 'files_manage', 'export' => 'entrant', 'manage' => 'files_admin'],
        ],
        'photos' => [
            'label' => 'Photothèque',
            'hint' => 'Photos signalétiques des fichiers et versements depuis la photothèque du site.',
            'paths' => ['fichiers/photos'],
            'legacy' => ['read' => 'entrant', 'write' => 'account', 'add' => 'account', 'delete' => 'files_manage', 'export' => 'entrant', 'manage' => 'files_admin'],
        ],
        'papiers' => [
            'label' => 'Papiers et couvertures',
            'hint' => 'Registre des papiers d’identité, identités fictives, couvertures, papiers des membres, atelier, kits et contrôles reçus.',
            'paths' => ['papiers'],
            'legacy' => ['read' => 'entrant', 'write' => 'manage', 'add' => 'manage', 'delete' => 'manage', 'export' => 'manage', 'manage' => 'manage'],
        ],
        'extractions' => [
            'label' => 'Extractions de téléphones',
            'hint' => 'Exploitation numérique : supports, acquisitions, artefacts, analyses ; extractions des téléphones ATAK du jeu.',
            'paths' => ['fichiers/telephones', 'exploitation-numerique'],
            'legacy' => ['read' => 'entrant', 'write' => 'manage', 'add' => 'manage', 'delete' => 'manage', 'export' => 'account', 'manage' => 'files_admin'],
        ],
        'dossiers' => [
            'label' => 'Dossiers d’enquête',
            'hint' => 'Dossiers validés et d’intérêt, investigations, sites, objets, rédaction, rapports, transmissions, collecte et validation.',
            'paths' => ['dossiers', 'interet', 'toiles', 'sites', 'objets', 'documents', 'bibliotheque', 'presentation', 'rapports', 'transmissions', 'collecte', 'validation'],
            'legacy' => ['read' => 'entrant', 'write' => 'manage', 'add' => 'manage', 'delete' => 'manage', 'export' => 'export', 'manage' => 'grant'],
        ],
        'analyse' => [
            'label' => 'Analyse',
            'hint' => 'Espace d’exploitation, cycle du renseignement, croisements, rapprochements, anomalies, chronologie.',
            'paths' => ['workspace', 'croisements', 'rapprochements', 'anomalies', 'chronologie', 'moteur'],
            'legacy' => ['read' => 'entrant', 'write' => 'manage', 'add' => 'manage', 'delete' => 'manage', 'export' => 'export', 'manage' => 'grant'],
        ],
        'lore' => [
            'label' => 'Lore et téléphones du jeu',
            'hint' => 'Lore des sessions (personnes, connu ou caché, phases) et maître du jeu.',
            'paths' => ['fichiers/lore', 'maitre-jeu'],
            'legacy' => ['read' => 'lore', 'write' => 'lore', 'add' => 'lore', 'delete' => 'lore', 'export' => 'lore', 'manage' => 'lore'],
        ],
        'journal' => [
            'label' => 'Journal',
            'hint' => 'Journal des consultations des fichiers, sur le site et en jeu.',
            'paths' => ['fichiers/journal'],
            'legacy' => ['read' => 'entrant', 'write' => 'none', 'add' => 'none', 'delete' => 'none', 'export' => 'none', 'manage' => 'none'],
        ],
        'terrain' => [
            'label' => 'Fiches de renseignement',
            'hint' => 'Notes libres remontées du terrain, avec pièces jointes, tri et rattachement aux dossiers.',
            'paths' => ['fiches'],
            'legacy' => ['read' => 'entrant', 'write' => 'field_write', 'add' => 'field_write', 'delete' => 'manage', 'export' => 'export', 'manage' => 'manage'],
        ],
        'identites_service' => [
            'label' => 'Identités de service des membres',
            'hint' => 'Images des membres (portrait, identité, couverture, papiers, clichés SEEK) classées Confidentiel élevé. Fermé à tous par défaut : en plus de l’habilitation Très restreint, le titulaire, sa hiérarchie ou une règle d’accord explicite ici donnent la lecture.',
            'paths' => ['identites-service'],
            'sealed' => true,
            'legacy' => ['read' => 'sealed', 'write' => 'none', 'add' => 'none', 'delete' => 'none', 'export' => 'none', 'manage' => 'none'],
        ],
        'outils' => [
            'label' => 'Modèles, campagnes et éditeur',
            'hint' => 'Modèles de mission, campagnes partagées et bibliothèque de l’éditeur du jeu.',
            'paths' => ['campagnes', 'dev', 'editeur'],
            'legacy' => ['read' => 'entrant', 'write' => 'manage', 'add' => 'manage', 'delete' => 'manage', 'export' => 'entrant', 'manage' => 'manage'],
        ],
    ];

    /**
     * Modèles prêts à l'emploi : se posent d'un clic sur un bénéficiaire, puis se modifient règle par règle.
     * « sections » : null = toutes ; « levels » : niveaux accordés (les cellules sans objet sont sautées).
     *
     * @var array<string, array{label: string, hint: string, sections: list<string>|null, levels: list<string>, deny: list<string>}>
     */
    public const TEMPLATES = [
        'enqueteurs' => [
            'label' => 'Enquêteurs : lire et écrire',
            'hint' => 'Voir, lire, écrire et verser dans les fichiers, la photothèque, les papiers, les extractions, les dossiers, l’analyse et les fiches.',
            'sections' => ['fichiers', 'photos', 'papiers', 'extractions', 'dossiers', 'analyse', 'terrain'],
            'levels' => ['exists', 'read', 'write', 'add'],
            'deny' => [],
        ],
        'analystes' => [
            'label' => 'Analystes : lecture seulement',
            'hint' => 'Voir et lire, sans écrire ni verser ; l’option « refuser le reste » interdit aussi les autres niveaux.',
            'sections' => ['fichiers', 'photos', 'papiers', 'extractions', 'dossiers', 'analyse', 'terrain', 'journal'],
            'levels' => ['exists', 'read'],
            'deny' => ['write', 'add', 'delete', 'export', 'manage'],
        ],
        'encadrement' => [
            'label' => 'Encadrement : tout',
            'hint' => 'Tous les niveaux de toutes les sections.',
            'sections' => null,
            'levels' => ['exists', 'read', 'write', 'add', 'delete', 'export', 'manage'],
            'deny' => [],
        ],
    ];

    /** @return list<string> */
    public static function sectionKeys(): array
    {
        return array_keys(array_filter(self::SECTIONS, static fn (array $def): bool => empty($def['sealed'])));
    }

    /** @return list<string> */
    public static function levelKeys(): array
    {
        return array_keys(self::LEVELS);
    }

    public static function hasSection(string $section): bool
    {
        return isset(self::SECTIONS[$section]);
    }

    public static function hasLevel(string $level): bool
    {
        return isset(self::LEVELS[$level]);
    }

    public static function sectionLabel(string $section): string
    {
        return self::SECTIONS[$section]['label'] ?? $section;
    }

    public static function levelLabel(string $level): string
    {
        return self::LEVELS[$level]['label'] ?? $level;
    }

    /** Profil d'origine d'une case (section, niveau) ; « none » si la case n'existe pas. */
    public static function legacyProfile(string $section, string $level): string
    {
        if (!isset(self::SECTIONS[$section]) || !isset(self::LEVELS[$level])) {
            return 'none';
        }
        $map = self::SECTIONS[$section]['legacy'];
        if ($level === self::LEVEL_EXISTS) {
            $level = self::LEVEL_READ;
        }

        return $map[$level] ?? 'none';
    }

    /** Une case a-t-elle un sens pour la section (une garde existe) ? */
    public static function applies(string $section, string $level): bool
    {
        return self::legacyProfile($section, $level) !== 'none';
    }

    /**
     * Niveaux qu'un refus sur $level emporte (lui-même compris).
     *
     * @return list<string>
     */
    public static function deniedBy(string $level): array
    {
        return array_merge([$level], self::DEPENDENTS[$level] ?? []);
    }

    /**
     * Section d'une adresse du portail (« atak/sse/fichiers/photos/12 » → « photos ») ; null si la page n'appartient
     * à aucune section (accueil, recherche, guide, codes d'accès, droits d'accès…). Préfixe le plus long d'abord.
     */
    public static function sectionForPath(string $path): ?string
    {
        $path = trim((string) (parse_url($path, PHP_URL_PATH) ?? ''), '/');
        $pos = strpos($path, 'atak/sse/');
        if ($pos === false) {
            return null;
        }
        $rest = trim(substr($path, $pos + strlen('atak/sse/')), '/');
        if ($rest === '') {
            return null;
        }
        $best = null;
        $bestLen = -1;
        foreach (self::SECTIONS as $key => $def) {
            foreach ($def['paths'] as $prefix) {
                if (($rest === $prefix || str_starts_with($rest, $prefix . '/')) && strlen($prefix) > $bestLen) {
                    $best = $key;
                    $bestLen = strlen($prefix);
                }
            }
        }

        return $best;
    }

    /**
     * Niveaux qu'un refus « simple » pose : voir ferme la lecture (et ce qui en dépend), écrire ferme écrire et verser,
     * gérer ferme la gestion.
     *
     * @return list<string>
     */
    public static function simpleDenyLevels(string $access): array
    {
        return match ($access) {
            'voir' => [self::LEVEL_READ],
            'ecrire' => [self::LEVEL_WRITE, self::LEVEL_ADD],
            'gerer' => [self::LEVEL_MANAGE],
            default => [],
        };
    }

    /** Libellé du modèle qui a posé une règle (modèle classique « enqueteurs » ou en situation « recipe:… »), vide si saisie à la main. */
    public static function sourceLabel(string $source): string
    {
        if (str_starts_with($source, 'recipe:')) {
            return self::RECIPES[substr($source, 7)]['label'] ?? '';
        }

        return self::TEMPLATES[$source]['label'] ?? '';
    }

    /** Colonne (voir, écrire, gérer) d'un niveau de règle, ou null si le niveau n'a pas de colonne simple. */
    public static function simpleColumnOf(string $level): ?string
    {
        foreach (self::SIMPLE_ACCESS as $key => $def) {
            if ($def['shown'] === $level) {
                return $key;
            }
        }

        return null;
    }

    /** Identifiant « type:id » d'un bénéficiaire (liste déroulante, formulaire). */
    public static function subjectRef(string $type, int $id): string
    {
        return $type . ':' . $id;
    }

    /** @return array{0: string, 1: int}|null [type, id] ou null si la référence est mal formée */
    public static function parseSubjectRef(string $ref): ?array
    {
        if (preg_match('/^(role|unit|unit_tree|user|position|job|qualif):([1-9][0-9]{0,9})$/', trim($ref), $m) !== 1) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }
}
