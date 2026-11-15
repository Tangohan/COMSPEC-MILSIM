<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\SseInterestCaseRepository;

/**
 * Règles pures des dossiers d'intérêt SSE (testées sans base) : onglets de la file, transitions d'état valides, priorités
 * P1 à P4, catalogue des types, types d'éléments liés, limites de saisie, étiquettes.
 *
 * Aucune clé existante n'est renommée : la priorité P1 à P4 est l'affichage du niveau d'intérêt (critique, prioritaire,
 * à surveiller, courant) et les onglets regroupent les états déjà en base.
 */
final class SseInterestCaseCatalog
{
    public const TAB_OPEN = 'ouverts';
    public const TAB_VALIDATE = 'a_valider';
    public const TAB_HOLD = 'en_attente';
    public const TAB_CLOSED = 'clos';
    public const TAB_ARCHIVED = 'archives';
    public const TAB_TRASH = 'corbeille';

    /** @var array<string, array{label: string, hint: string, statuses: list<string>}> */
    public const TABS = [
        self::TAB_OPEN => [
            'label' => 'Ouverts',
            'hint' => 'Dossiers en cours d’instruction.',
            'statuses' => ['signalement_recu', 'a_qualifier', 'en_collecte', 'en_analyse'],
        ],
        self::TAB_VALIDATE => [
            'label' => 'À valider',
            'hint' => 'Une décision humaine est attendue.',
            'statuses' => ['rapprochements_detectes', 'en_validation', 'correspondance_probable'],
        ],
        self::TAB_HOLD => [
            'label' => 'En attente',
            'hint' => 'Brouillons et dossiers suspendus.',
            'statuses' => ['brouillon', 'en_attente'],
        ],
        self::TAB_CLOSED => [
            'label' => 'Clos',
            'hint' => 'Instruction terminée, avec ou sans suite.',
            'statuses' => ['identite_consolidee', 'identite_infirme', 'sans_suite'],
        ],
        self::TAB_ARCHIVED => [
            'label' => 'Archivés',
            'hint' => 'Rangés, restaurables à tout moment.',
            'statuses' => ['archive'],
        ],
    ];

    /**
     * Passages d'état permis. Un état absent de la liste d'un autre n'est pas atteignable : c'est la seule table, le contrôleur
     * et le service la lisent ici. L'archivage et sa restauration ont leurs propres actions (voir SseInterestCaseService).
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        'brouillon' => ['signalement_recu', 'a_qualifier', 'sans_suite', 'archive'],
        'signalement_recu' => ['a_qualifier', 'en_attente', 'sans_suite', 'archive'],
        'a_qualifier' => ['en_collecte', 'en_analyse', 'en_attente', 'sans_suite', 'archive'],
        'en_collecte' => ['en_analyse', 'rapprochements_detectes', 'en_attente', 'sans_suite', 'archive'],
        'en_analyse' => ['en_collecte', 'rapprochements_detectes', 'en_validation', 'en_attente', 'sans_suite', 'archive'],
        'rapprochements_detectes' => ['en_analyse', 'en_collecte', 'en_validation', 'en_attente', 'sans_suite'],
        'en_attente' => ['a_qualifier', 'en_collecte', 'en_analyse', 'sans_suite', 'archive'],
        'en_validation' => ['en_analyse', 'correspondance_probable', 'identite_consolidee', 'identite_infirme', 'sans_suite'],
        'correspondance_probable' => ['en_analyse', 'en_validation', 'identite_consolidee', 'identite_infirme'],
        'identite_consolidee' => ['en_analyse', 'archive'],
        'identite_infirme' => ['en_analyse', 'archive'],
        'sans_suite' => ['a_qualifier', 'archive'],
        'archive' => [],
    ];

    /** Décisions de validation : réservées aux gestionnaires du dossier. */
    public const MANAGER_TARGETS = ['correspondance_probable', 'identite_consolidee', 'identite_infirme'];

    /** Passages qui exigent un motif écrit (clôture, rejet, réouverture, archivage). */
    public const REASON_TARGETS = ['identite_consolidee', 'identite_infirme', 'sans_suite', 'archive', 'en_attente'];

    /** États d'origine dont toute sortie est une réouverture (motif obligatoire). */
    public const REOPEN_FROM = ['identite_consolidee', 'identite_infirme', 'sans_suite'];

    /** @var array<string, array{code: string, label: string, rank: int}> */
    public const PRIORITIES = [
        'critique' => ['code' => 'P1', 'label' => 'Critique', 'rank' => 1],
        'prioritaire' => ['code' => 'P2', 'label' => 'Prioritaire', 'rank' => 2],
        'a_surveiller' => ['code' => 'P3', 'label' => 'À surveiller', 'rank' => 3],
        'courant' => ['code' => 'P4', 'label' => 'Courant', 'rank' => 4],
    ];

    /**
     * Types par défaut ; une communauté peut poser le sien dans le réglage « interest_case_types » du portail
     * (JSON {clé: libellé}).
     *
     * @var array<string, string>
     */
    public const DEFAULT_TYPES = [
        'personne' => 'Personne d’intérêt',
        'reseau' => 'Réseau ou groupe',
        'vehicule' => 'Véhicule suspect',
        'site' => 'Site ou lieu d’intérêt',
        'trafic' => 'Trafic ou activité suspecte',
        'autre' => 'Autre',
    ];

    public const TYPES_SETTING = 'interest_case_types';

    /** @var array<string, string> */
    public const LINK_TYPES = [
        'person' => 'Personne',
        'identity' => 'Identité',
        'site' => 'Site',
        'vehicle' => 'Véhicule',
        'transmission' => 'Transmission',
        'fiche' => 'Fiche de renseignement',
        'document' => 'Document émis',
        'case' => 'Dossier',
    ];

    /** Types dont la cible est un enregistrement du portail (les autres, ici le véhicule, sont saisis en texte libre). */
    public const LINK_TYPES_WITH_TARGET = ['person', 'identity', 'site', 'transmission', 'fiche', 'document', 'case'];

    /** @var array<string, string> */
    public const SORTS = [
        'recent' => 'Récents',
        'priorite' => 'Priorité',
        'echeance' => 'Échéance',
        'reference' => 'Référence',
        'titre' => 'Titre',
    ];

    /** @var array<string, string> libellés des champs dans l'historique */
    public const FIELD_LABELS = [
        'temporary_designation' => 'Titre',
        'opening_reason' => 'Motif d’ouverture',
        'description' => 'Description',
        'case_type' => 'Type',
        'interest_level' => 'Priorité',
        'status' => 'État',
        'assignee_user_id' => 'Responsable',
        'due_at' => 'Échéance',
        'classification' => 'Classification',
        'tags' => 'Étiquettes',
        'suspected_alias' => 'Alias supposé',
        'suspected_affiliation' => 'Affiliation',
        'confidence_level' => 'Confiance',
    ];

    /** @var array<string, string> libellés des actions de l'historique */
    public const ACTION_LABELS = [
        'create' => 'Ouverture du dossier',
        'update' => 'Modification',
        'status' => 'Changement d’état',
        'classification' => 'Classification',
        'link_add' => 'Élément lié ajouté',
        'link_remove' => 'Élément lié retiré',
        'archive' => 'Archivage',
        'unarchive' => 'Sortie des archives',
        'trash' => 'Mise à la corbeille',
        'restore' => 'Restauration',
        'purge' => 'Suppression définitive',
        'watch' => 'Abonnement',
        'note' => 'Note',
        'export' => 'Export PDF',
        'acl' => 'Diffusion modifiée',
    ];

    public const PER_PAGE = 25;
    public const PER_PAGE_MAX = 100;
    public const TRASH_DAYS = 30;
    public const TAGS_MAX = 8;
    public const TAG_LENGTH_MAX = 24;
    public const TITLE_MAX = 120;
    public const ALIAS_MAX = 160;
    public const REASON_MAX = 4000;
    public const DESCRIPTION_MAX = 8000;
    public const NOTE_MAX = 4000;
    public const CHANGE_REASON_MAX = 300;
    public const LINKS_MAX = 60;
    public const EXPORTS_PER_HOUR = 20;

    /** Onglet dans lequel un état se range ; null pour un état inconnu. */
    public static function tabOf(string $status): ?string
    {
        foreach (self::TABS as $tab => $meta) {
            if (in_array($status, $meta['statuses'], true)) {
                return $tab;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function statusesOf(string $tab): array
    {
        return self::TABS[$tab]['statuses'] ?? [];
    }

    /** @return list<string> états atteignables depuis $from, sans l'archive hors action d'archivage */
    public static function nextStatuses(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function needsReason(string $from, string $to): bool
    {
        return in_array($to, self::REASON_TARGETS, true) || in_array($from, self::REOPEN_FROM, true);
    }

    public static function priorityCode(string $level): string
    {
        return self::PRIORITIES[$level]['code'] ?? 'P4';
    }

    public static function priorityLabel(string $level): string
    {
        return self::PRIORITIES[$level]['label'] ?? 'Courant';
    }

    /** @return array<string, string> */
    public static function types(?string $tenantJson = null): array
    {
        $raw = trim((string) $tenantJson);
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $out = [];
                foreach ($decoded as $key => $label) {
                    $key = strtolower(trim((string) $key));
                    $label = trim(strip_tags((string) $label));
                    if (preg_match('/^[a-z0-9_]{2,40}$/', $key) === 1 && $label !== '') {
                        $out[$key] = mb_substr($label, 0, 60);
                    }
                }
                if ($out !== []) {
                    return $out;
                }
            }
        }

        return self::DEFAULT_TYPES;
    }

    /**
     * Étiquettes : au plus 8, de 24 signes, lettres, chiffres, espace, tiret, apostrophe ; sans doublon (casse ignorée).
     *
     * @param string|list<mixed>|null $raw
     * @return list<string>
     */
    public static function normalizeTags(string|array|null $raw): array
    {
        $parts = is_array($raw) ? $raw : preg_split('/[,;\n]+/', (string) $raw);
        $out = [];
        foreach ($parts ?: [] as $part) {
            $tag = trim((string) preg_replace('/\s+/u', ' ', (string) $part));
            $tag = trim((string) preg_replace('/[^\p{L}\p{N} \-’\']/u', '', $tag));
            if ($tag === '') {
                continue;
            }
            $tag = mb_substr($tag, 0, self::TAG_LENGTH_MAX);
            $out[mb_strtolower($tag)] ??= $tag;
            if (count($out) >= self::TAGS_MAX) {
                break;
            }
        }

        return array_values($out);
    }

    /** @param list<string> $tags */
    public static function joinTags(array $tags): ?string
    {
        return $tags === [] ? null : implode(',', $tags);
    }

    /** @return list<string> */
    public static function splitTags(?string $stored): array
    {
        return $stored === null || trim($stored) === '' ? [] : self::normalizeTags($stored);
    }

    /** Statuts connus du dépôt, pour valider une saisie. */
    public static function knownStatus(string $status): bool
    {
        return isset(SseInterestCaseRepository::STATUSES[$status]);
    }
}
