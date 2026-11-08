<?php

declare(strict_types=1);

namespace App\Support;

/**
 * COMSPEC IDENTITY (Lot 6) — valeurs fermées de l'atelier de documents, des kits types, de la préparation de mission
 * et des briefs de couverture.
 *
 * Les clés de SIDES, POP_CATEGORIES et WANTED_LEVELS partent dans le pack du serveur de jeu (population[], wanted[]) :
 * ne pas les renommer sans changer le mod SSE. Tout gabarit est imaginaire : aucun ne reprend un vrai titre officiel.
 */
final class SseIdWorkshopCatalog
{
    /** Modèles d'impression (gabarits génériques, jamais la copie d'un vrai titre). */
    public const LAYOUTS = [
        'modern' => 'Moderne (bandeau aux couleurs du pays)',
        'classic' => 'Classique (cadre guilloché)',
        'compact' => 'Sobre (fond clair)',
        'custom' => 'Personnalisé (image de fond importée)',
    ];

    /** Camps du jeu (side Arma) : clés envoyées au serveur. */
    public const SIDES = [
        'civ' => 'Civils',
        'west' => 'BLUFOR',
        'east' => 'OPFOR',
        'guer' => 'Indépendants',
    ];

    /** Catégories de population générée pour une opération. */
    public const POP_CATEGORIES = [
        'civilian' => 'Civils',
        'military' => 'Militaires',
        'suspect' => 'Suspects',
        'hvt' => 'Cibles prioritaires (HVT)',
    ];

    /** Niveau d'un recherché de l'opération (pack wanted[].level). */
    public const WANTED_LEVELS = [
        'surveillance' => 'Sous surveillance',
        'recherche' => 'Recherché',
        'hvt' => 'Cible prioritaire',
    ];

    /** Catégories de kits types. */
    public const KIT_CATEGORIES = [
        'civil' => 'Civil',
        'military' => 'Militaire',
        'diplomatic' => 'Diplomatique',
        'journalist' => 'Journaliste',
        'contractor' => 'Contractor',
        'undercover' => 'Agent infiltré',
        'other' => 'Autre',
    ];

    /** Identité qui porte les papiers d'un kit type affecté à un membre. */
    public const IDENTITY_MODES = [
        'primary' => 'Identité principale du membre',
        'alias' => 'Nouvel alias généré',
        'cover' => 'Nouvelle identité de couverture (habilitation Confidentiel)',
    ];

    /** Champs imprimés en plus des données du papier : clé => [libellé, longueur max]. */
    public const PRINT_FIELDS = [
        'place_of_issue' => ['Lieu de délivrance', 60],
        'height' => ['Taille', 12],
        'eyes' => ['Yeux', 24],
        'address' => ['Adresse', 120],
        'personal_number' => ['Numéro personnel', 20],
        'categories' => ['Catégories (permis)', 24],
        'service' => ['Service ou employeur', 60],
        'rank' => ['Grade ou fonction', 40],
        'phone' => ['Téléphone (simulé)', 24],
        'signature' => ['Signature (texte)', 40],
        'stamp' => ['Texte du tampon', 60],
        'remarks' => ['Observations', 160],
    ];

    /** Kits types proposés à la création (contenu de départ, à adapter). */
    public const KIT_PRESETS = [
        'civil' => [
            'name' => 'Civil — résident', 'doc_types' => ['id_card', 'driving_license'], 'identity_mode' => 'primary',
            'profession' => 'Commerçant', 'legend' => 'Résident de la région, sans lien avec les forces en présence.',
            'instructions' => 'Présentez vos papiers sans discuter ; ne portez aucun équipement militaire.',
            'print' => ['categories' => 'B'],
        ],
        'military' => [
            'name' => 'Militaire — carte de service', 'doc_types' => ['military_id'], 'identity_mode' => 'primary',
            'profession' => 'Militaire', 'legend' => 'Membre des forces armées en mission.',
            'instructions' => 'Carte de service à présenter aux points de contrôle amis uniquement.',
            'print' => ['service' => 'Forces armées', 'rank' => 'Sergent'],
        ],
        'diplomatic' => [
            'name' => 'Diplomatique — personnel d’ambassade', 'doc_types' => ['passport', 'id_card'], 'identity_mode' => 'alias',
            'profession' => 'Attaché', 'legend' => 'Attaché auprès d’une représentation étrangère, protégé par son statut.',
            'instructions' => 'Invoquez votre statut diplomatique ; refusez toute fouille de vos documents.',
            'print' => ['service' => 'Corps diplomatique', 'remarks' => 'Titulaire bénéficiant d’un statut diplomatique (fictif)'],
        ],
        'journalist' => [
            'name' => 'Journaliste — correspondant', 'doc_types' => ['passport'], 'identity_mode' => 'alias',
            'profession' => 'Journaliste', 'legend' => 'Correspondant d’un média étranger couvrant les événements.',
            'instructions' => 'Gardez votre carnet de notes ; votre rédaction peut confirmer votre présence.',
            'print' => ['service' => 'Presse étrangère'],
        ],
        'contractor' => [
            'name' => 'Contractor — société privée', 'doc_types' => ['passport', 'driving_license'], 'identity_mode' => 'alias',
            'profession' => 'Consultant sécurité', 'legend' => 'Employé d’une société de services sous contrat.',
            'instructions' => 'Restez vague sur votre employeur ; votre contrat est à présenter sur demande.',
            'print' => ['service' => 'Société de services', 'categories' => 'B C'],
        ],
        'undercover' => [
            'name' => 'Agent infiltré — couverture complète', 'doc_types' => ['id_card', 'passport', 'driving_license'], 'identity_mode' => 'cover',
            'profession' => 'Chauffeur', 'legend' => 'Habitant ordinaire ; rien ne doit le relier à son unité.',
            'instructions' => 'Ne révélez jamais votre vraie identité ; en cas de doute, appelez votre contact.',
            'print' => ['categories' => 'B'],
        ],
    ];

    public static function layout(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';

        return isset(self::LAYOUTS[$v]) ? $v : 'modern';
    }

    /**
     * Champs imprimés lus depuis la base (JSON) ou un formulaire (tableau) : seulement les clés connues, texte court.
     *
     * @return array<string, string>
     */
    public static function printFields(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach (self::PRINT_FIELDS as $key => [, $max]) {
            $v = $value[$key] ?? null;
            $v = is_scalar($v) ? trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '') : '';
            if ($v !== '') {
                $out[$key] = mb_substr($v, 0, $max);
            }
        }

        return $out;
    }

    /** Champs imprimés à ranger en base (null si aucun). */
    public static function printJson(mixed $value): ?string
    {
        $fields = self::printFields($value);

        return $fields === [] ? null : (string) json_encode($fields, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Liste de camps (« west,east » ou tableau) : seulement les clés connues, dans l'ordre du catalogue.
     *
     * @return list<string>
     */
    public static function sides(mixed $value): array
    {
        $list = is_array($value) ? $value : explode(',', (string) $value);
        $list = array_map(static fn (mixed $s): string => strtolower(trim(is_scalar($s) ? (string) $s : '')), $list);

        return array_values(array_filter(array_keys(self::SIDES), static fn (string $k): bool => in_array($k, $list, true)));
    }

    public static function label(array $map, ?string $key, string $fallback = '—'): string
    {
        return SseIdentityCatalog::label($map, $key, $fallback);
    }
}
