<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catalogue de la fenêtre « Rattacher » du bureau SSE : les périmètres (cibles possibles), la matrice « pièce → cibles qui ont
 * du sens », les natures de lien proposées, les modèles de motif, les couleurs des étiquettes et les types qui portent une
 * classification (donc alignables).
 *
 * Un nouveau périmètre se déclare ici (SCOPES + MATRIX) et dans SseLinkCatalog::TYPES / SseLinkAdapters (description et
 * confidentialité de l'élément) : la fenêtre, la recherche et la section « Éléments rattachés » le prennent sans autre code.
 */
final class SseAttachCatalog
{
    public const REASON_MIN = 5;

    public const REASON_MAX = 255;

    public const LABEL_MAX = 160;

    public const NOTE_MAX = 500;

    public const TAG_MAX = 40;

    public const TAGS_PER_LINK = 8;

    /** Une validation rattache au plus tant de pièces à tant de cibles. */
    public const BATCH_SOURCES = 50;

    public const BATCH_TARGETS = 12;

    public const PAGE_SIZE = 20;

    /** Lignes lues par périmètre pour la recherche (les plus récentes). */
    public const SCAN_LIMIT = 1500;

    /** Délai d'annulation rapide après un rattachement, en secondes. */
    public const UNDO_SECONDS = 15;

    /** Réglage du portail : toujours aligner à la hausse au rattachement (désactivé par défaut). */
    public const SETTING_ALWAYS_ALIGN = 'attach_always_align_up';

    /** Échelle de classification, du plus bas au plus haut (codes inchangés). */
    public const LEVELS = ['interne', 'encadrement', 'confidentiel', 'tres_restreint'];

    /**
     * Périmètres, dans l'ordre de la colonne de gauche : type de lien => libellé de l'onglet, phrase d'aide de l'état vide.
     *
     * @var array<string, array{label: string, one: string, empty: string}>
     */
    public const SCOPES = [
        'case' => ['label' => 'Dossiers', 'one' => 'Dossier', 'empty' => 'Aucun dossier d’enquête à votre portée ne correspond.'],
        'interest' => ['label' => 'Dossiers d’intérêt', 'one' => 'Dossier d’intérêt', 'empty' => 'Aucun dossier d’intérêt qui vous est ouvert ne correspond.'],
        'lead' => ['label' => 'Pistes', 'one' => 'Piste', 'empty' => 'Aucune piste ne correspond. Vous pouvez en créer une d’ici.'],
        'mesh' => ['label' => 'Investigations', 'one' => 'Investigation', 'empty' => 'Aucune investigation ne correspond.'],
        'person' => ['label' => 'Personnes', 'one' => 'Personne', 'empty' => 'Aucune fiche personne ne correspond.'],
        'identity' => ['label' => 'Identités', 'one' => 'Identité du registre', 'empty' => 'Aucune identité du registre ne correspond.'],
        'paper' => ['label' => 'Papiers', 'one' => 'Papier d’identité', 'empty' => 'Aucun papier d’identité ne correspond.'],
        'document' => ['label' => 'Documents', 'one' => 'Document de rédaction', 'empty' => 'Aucun document de rédaction ne correspond.'],
        'doc_issue' => ['label' => 'Documents émis', 'one' => 'Document émis', 'empty' => 'Aucun document émis ne correspond.'],
        'note' => ['label' => 'Fiches de renseignement', 'one' => 'Fiche de renseignement', 'empty' => 'Aucune fiche de renseignement ne correspond.'],
        'transmission' => ['label' => 'Transmissions', 'one' => 'Transmission', 'empty' => 'Aucune transmission ne correspond.'],
        'notice' => ['label' => 'Avis de recherche', 'one' => 'Avis de recherche', 'empty' => 'Aucun avis de recherche ne correspond.'],
        'site' => ['label' => 'Sites', 'one' => 'Site', 'empty' => 'Aucun site ne correspond.'],
        'organization' => ['label' => 'Organisations', 'one' => 'Organisation', 'empty' => 'Aucune organisation du registre ne correspond.'],
        'vehicle' => ['label' => 'Véhicules', 'one' => 'Véhicule', 'empty' => 'Aucun véhicule du registre ne correspond.'],
        'equipment' => ['label' => 'Matériels', 'one' => 'Matériel', 'empty' => 'Aucun matériel du registre ne correspond.'],
        'campaign' => ['label' => 'Campagnes', 'one' => 'Campagne', 'empty' => 'Aucune campagne ne correspond.'],
    ];

    private const FOLDERS = ['case', 'interest', 'lead', 'mesh', 'campaign'];

    /**
     * Matrice : type de la pièce => périmètres proposés, du plus probable au moins probable.
     *
     * @var array<string, list<string>>
     */
    public const MATRIX = [
        'note' => ['case', 'interest', 'lead', 'mesh', 'person', 'identity', 'site', 'organization', 'vehicle', 'equipment', 'transmission', 'document', 'notice', 'campaign'],
        'transmission' => ['case', 'interest', 'lead', 'mesh', 'person', 'identity', 'site', 'vehicle', 'organization', 'note', 'notice', 'campaign'],
        'document' => ['case', 'interest', 'lead', 'mesh', 'person', 'identity', 'site', 'organization', 'notice', 'campaign'],
        'doc_issue' => ['case', 'interest', 'lead', 'person', 'identity', 'notice'],
        'paper' => ['identity', 'person', 'case', 'interest', 'lead', 'notice', 'document'],
        'identity' => ['person', 'case', 'interest', 'lead', 'mesh', 'organization', 'site', 'vehicle', 'notice', 'campaign'],
        'check' => ['identity', 'person', 'paper', 'case', 'interest', 'lead', 'note', 'site', 'notice'],
        'seek' => ['identity', 'person', 'case', 'interest', 'lead'],
        'person' => ['case', 'interest', 'lead', 'mesh', 'organization', 'site', 'vehicle', 'notice', 'campaign'],
        'photo' => ['identity', 'person', 'case', 'interest', 'lead', 'site', 'vehicle', 'note', 'notice'],
        'attribute' => ['person', 'case', 'interest', 'lead', 'vehicle', 'organization'],
        'notice' => ['case', 'interest', 'lead', 'person', 'identity', 'campaign'],
        'site' => ['case', 'interest', 'lead', 'mesh', 'organization', 'campaign'],
        'lead' => ['case', 'interest', 'mesh', 'person', 'identity', 'site', 'organization', 'campaign'],
        'mesh' => ['case', 'interest', 'lead', 'campaign'],
        'organization' => ['case', 'interest', 'lead', 'mesh', 'person', 'site', 'campaign'],
        'vehicle' => ['case', 'interest', 'lead', 'mesh', 'person', 'site', 'campaign'],
        'equipment' => ['case', 'interest', 'lead', 'mesh', 'person', 'site', 'campaign'],
        'case' => ['interest', 'lead', 'campaign'],
        'interest' => ['case', 'lead', 'campaign'],
    ];

    /**
     * Natures de lien : clé de relation (SseLinkCatalog::RELATIONS) => libellé de la liste.
     *
     * @var array<string, string>
     */
    public const NATURES = [
        'filed_in' => 'Classé dans',
        'concerns' => 'Concerne',
        'evidence_of' => 'Preuve de',
        'mentions' => 'Mentionne',
        'created_from' => 'Issu de',
        'verify_with' => 'À vérifier avec',
        'related' => 'Lié à',
    ];

    /** @var list<string> */
    public const REASON_TEMPLATES = [
        'Recueilli dans le cadre de cette affaire',
        'Concerne la même personne',
        'Relevé au même endroit',
        'Pièce justificative',
        'Recoupement à vérifier',
        'Demande du responsable du dossier',
    ];

    /** Couleurs sobres des étiquettes (classe CSS ra-tag--<clé>). @var array<string, string> */
    public const TAG_COLORS = ['slate' => 'Ardoise', 'green' => 'Vert', 'blue' => 'Bleu', 'amber' => 'Ambre', 'red' => 'Rouge', 'violet' => 'Violet'];

    /** @var array<string, string> */
    public const LEAD_STATUSES = ['ouverte' => 'Ouverte', 'suivie' => 'Suivie', 'close' => 'Close'];

    /**
     * Où chaque type porte sa classification : [table, colonne]. Un type absent d'ici n'est pas alignable.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const CLASSIFIED = [
        'case' => ['sse_cases', 'classification'],
        'document' => ['sse_documents', 'classification'],
        'note' => ['sse_field_notes', 'classification'],
        'doc_issue' => ['sse_doc_issues', 'classification'],
        'person' => ['sse_persons', 'classification'],
        'mesh' => ['sse_meshes', 'classification'],
        'lead' => ['sse_leads', 'classification'],
    ];

    /** Cibles que la fenêtre sait créer à la volée. @var list<string> */
    public const CREATABLE = ['case', 'lead'];

    /**
     * Rattachements que la base historique sait porter sans la table des liens (mode dégradé).
     *
     * @var list<array{0: string, 1: string}>
     */
    public const LEGACY_PAIRS = [['note', 'case'], ['document', 'case'], ['transmission', 'case'], ['person', 'case'], ['case', 'interest'], ['interest', 'case']];

    public static function isSource(string $type): bool
    {
        return isset(self::MATRIX[$type]);
    }

    /** @return list<string> périmètres proposés pour une pièce de ce type */
    public static function targetsFor(string $sourceType): array
    {
        return self::MATRIX[$sourceType] ?? [];
    }

    /**
     * Périmètres communs à plusieurs pièces (rattachement en lot), dans l'ordre de la première.
     *
     * @param list<string> $sourceTypes
     * @return list<string>
     */
    public static function commonTargets(array $sourceTypes): array
    {
        $sourceTypes = array_values(array_unique($sourceTypes));
        if ($sourceTypes === []) {
            return [];
        }
        $common = self::targetsFor($sourceTypes[0]);
        foreach (array_slice($sourceTypes, 1) as $t) {
            $common = array_values(array_intersect($common, self::targetsFor($t)));
        }

        return $common;
    }

    public static function allows(string $sourceType, string $targetType): bool
    {
        return in_array($targetType, self::targetsFor($sourceType), true);
    }

    /**
     * Natures proposées vers un périmètre, la première étant celle par défaut.
     *
     * @return list<string>
     */
    public static function naturesFor(string $targetType): array
    {
        if (in_array($targetType, self::FOLDERS, true)) {
            return ['filed_in', 'concerns', 'evidence_of', 'created_from', 'verify_with', 'related'];
        }
        if (in_array($targetType, ['document', 'doc_issue', 'note', 'transmission'], true)) {
            return ['mentions', 'concerns', 'created_from', 'evidence_of', 'verify_with', 'related'];
        }

        return ['concerns', 'mentions', 'evidence_of', 'created_from', 'verify_with', 'related'];
    }

    public static function natureLabel(string $nature, string $custom = ''): string
    {
        $custom = trim($custom);

        return $custom !== '' ? $custom : (self::NATURES[$nature] ?? self::NATURES['related']);
    }

    public static function isLevel(?string $level): bool
    {
        return $level !== null && in_array($level, self::LEVELS, true);
    }

    /** Rang d'un niveau : -1 sans classification, 0 (interne) à 3 (très restreint). */
    public static function rank(?string $level): int
    {
        $i = $level === null ? false : array_search($level, self::LEVELS, true);

        return $i === false ? -1 : (int) $i;
    }

    /** Abréviation affichée (CUI, C, S, TS) ; « NC » sans classification. */
    public static function levelAbbr(?string $level): string
    {
        return self::isLevel($level) ? SseUsClassification::abbr((string) $level) : 'NC';
    }

    public static function levelLabel(?string $level): string
    {
        return self::isLevel($level) ? SseUsClassification::label((string) $level) : 'Non classifié';
    }

    /** Recherche sans accents ni casse : « Élodie » et « elodie » se trouvent. */
    public static function fold(string $text): string
    {
        $text = mb_strtolower($text);
        $text = strtr($text, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'å' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae', '’' => "'",
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** Identifiant d'étiquette : nom plié, lettres, chiffres et tirets. */
    public static function tagSlug(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/[^a-z0-9]+/', '-', self::fold($name)), '-'), 0, self::TAG_MAX);
    }
}
