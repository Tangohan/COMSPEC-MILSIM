<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Support\SilentSchemaMigration;

/**
 * Toiles de données SSE — graphes d’enquête persistés.
 */
final class SseMeshRepository
{
    /** @var array<string, string> */
    public const KIND_LABELS = [
        'person' => 'Identité',
        'alias' => 'Alias',
        'organization' => 'Organisation',
        'site' => 'Site',
        'vehicle' => 'Véhicule',
        'weapon' => 'Arme / matériel',
        'phone' => 'Téléphone',
        'terminal' => 'Terminal',
        'document' => 'Document',
        'event' => 'Événement',
        'report' => 'Compte rendu',
        'photo' => 'Photographie',
        'biometric' => 'Relevé biométrique',
        'seizure' => 'Saisie',
        'custom' => 'Élément libre',
    ];

    /** @var array<string, string> */
    public const RELATION_LABELS = [
        'possede' => 'POSSÈDE',
        'utilise' => 'UTILISE',
        'vu_avec' => 'A ÉTÉ VU AVEC',
        'observe_a' => 'A ÉTÉ OBSERVÉ À',
        'membre' => 'EST MEMBRE DE',
        'contact' => 'A CONTACTÉ',
        'apparente' => 'EST APPARENTÉ À',
        'biometrie' => 'CORRESPOND BIOMÉTRIQUEMENT À',
        'cite' => 'EST CITÉ DANS',
        'transporte' => 'A ÉTÉ TRANSPORTÉ PAR',
        'associe' => 'ASSOCIÉ À',
        'present' => 'PRÉSENT SUR',
        'observe' => 'OBSERVÉ PRÈS DE',
        'mentionne' => 'MENTIONNÉ PAR',
        'controle' => 'CONTRÔLÉ AVEC',
        'meme_individu' => 'MÊME INDIVIDU QUE',
    ];

    /** Statuts de lien (human-in-the-loop). */
    public const EDGE_STATUS_LABELS = [
        'unverified' => 'Supposé',
        'corroborated' => 'Corroboré',
        'confirmed' => 'Confirmé',
        'conflicting' => 'Réfuté',
    ];

    /** @var array<string, string> */
    public const STATUS_LABELS = [
        'ouvert' => 'Ouverte',
        'en_cours' => 'En analyse',
        'clos' => 'Close',
        'archive' => 'Archivée',
    ];

    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
        $this->ensureSchema();
    }

    private function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        SilentSchemaMigration::run(base_path('bootstrap/atak_sse_meshes_migration.php'));
        $done = true;
    }

    public static function kindLabel(string $kind): string
    {
        return self::KIND_LABELS[$kind] ?? 'Élément';
    }

    public static function relationLabel(string $key): string
    {
        return self::RELATION_LABELS[$key] ?? 'lié à';
    }

    public static function normalizeKind(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::KIND_LABELS[$s]) ? $s : 'custom';
    }

    /**
     * Champs métier par type d’objet (libellés humains + listes fermées).
     *
     * @return array<string, array{hint:string, fields:list<array<string,mixed>>}>
     */
    public static function metaSchema(): array
    {
        $confidence = SseInterestCaseRepository::CONFIDENCE;
        $personStatus = SsePersonRepository::STATUS_LABELS;
        $siteTypes = SseSiteRepository::TYPE_LABELS;
        $sex = [
            'inconnu' => 'Non déterminé',
            'homme' => 'Homme',
            'femme' => 'Femme',
            'autre' => 'Autre / non binaire',
        ];
        $reliability = [
            'non_evalue' => 'Non évaluée',
            'A' => 'A — fiable',
            'B' => 'B — généralement fiable',
            'C' => 'C — assez fiable',
            'D' => 'D — peu fiable',
            'E' => 'E — non fiable',
        ];
        $vehicleType = [
            'vl' => 'Véhicule léger',
            'utilitaire' => 'Utilitaire',
            'camion' => 'Camion',
            'moto' => 'Deux-roues',
            'blindé' => 'Blindé / militaire',
            'autre' => 'Autre',
        ];
        $weaponType = [
            'arme_poing' => 'Arme de poing',
            'arme_épaule' => 'Arme d’épaule',
            'explosif' => 'Explosif / munition',
            'optique' => 'Optique / viseur',
            'radio' => 'Transmission',
            'autre' => 'Autre matériel',
        ];
        $phoneOs = [
            'inconnu' => 'Inconnu',
            'android' => 'Android',
            'ios' => 'iOS',
            'autre' => 'Autre',
        ];
        $docType = [
            'piece_identite' => 'Pièce d’identité',
            'permis' => 'Permis / titre',
            'note' => 'Note manuscrite',
            'plan' => 'Plan / schéma',
            'liste' => 'Liste / inventaire',
            'ordre' => 'Ordre / consigne',
            'compte_rendu' => 'Compte rendu',
            'flash' => 'Flash',
            'autre' => 'Autre document',
        ];
        $eventType = [
            'observation' => 'Observation',
            'mouvement' => 'Mouvement',
            'contact' => 'Contact',
            'saisie' => 'Saisie',
            'reunion' => 'Réunion / rendez-vous',
            'autre' => 'Autre',
        ];
        $biometry = [
            'visage' => 'Visage',
            'empreinte' => 'Empreinte',
            'iris' => 'Iris',
            'voix' => 'Voix',
            'autre' => 'Autre',
        ];
        $seizure = SseSiteRepository::SEIZURE_LABELS;

        return [
            'person' => [
                'hint' => 'Fiche d’identité opérationnelle — pas une preuve d’identification établie.',
                'fields' => [
                    ['name' => 'status', 'label' => 'Statut', 'type' => 'select', 'options' => $personStatus],
                    ['name' => 'sex', 'label' => 'Sexe apparent', 'type' => 'select', 'options' => $sex],
                    ['name' => 'age_range', 'label' => 'Tranche d’âge', 'type' => 'text', 'placeholder' => 'Ex. 30–40 ans'],
                    ['name' => 'nationality', 'label' => 'Nationalité', 'type' => 'text'],
                    ['name' => 'affiliation', 'label' => 'Affiliation', 'type' => 'text'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'description', 'label' => 'Signalement / description', 'type' => 'textarea', 'placeholder' => 'Traits distinctifs, tenue, contexte d’observation…', 'rows' => 4],
                    ['name' => 'notes', 'label' => 'Notes d’exploitation', 'type' => 'textarea', 'placeholder' => 'Éléments utiles pour la suite de l’enquête', 'rows' => 3],
                ],
            ],
            'alias' => [
                'hint' => 'Nom d’emprunt ou désignation terrain, distinct de l’identité consolidée.',
                'fields' => [
                    ['name' => 'alias_kind', 'label' => 'Nature de l’alias', 'type' => 'select', 'options' => [
                        'indicatif' => 'Indicatif radio',
                        'surnom' => 'Surnom',
                        'pseudo' => 'Pseudonyme',
                        'code' => 'Nom de code',
                        'autre' => 'Autre',
                    ]],
                    ['name' => 'language', 'label' => 'Langue / alphabet', 'type' => 'text'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'context', 'label' => 'Contexte d’emploi', 'type' => 'textarea', 'placeholder' => 'Où / comment cet alias a été entendu ou lu', 'rows' => 3],
                ],
            ],
            'organization' => [
                'hint' => 'Groupe, cellule ou structure — rattachez-la ensuite aux identités.',
                'fields' => [
                    ['name' => 'org_kind', 'label' => 'Nature', 'type' => 'select', 'options' => [
                        'cellule' => 'Cellule',
                        'groupe' => 'Groupe armé',
                        'milice' => 'Milice',
                        'entreprise' => 'Entreprise / couverture',
                        'autre' => 'Autre',
                    ]],
                    ['name' => 'zone', 'label' => 'Zone d’influence', 'type' => 'text'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Rôle, effectifs estimés, modus operandi…', 'rows' => 4],
                ],
            ],
            'site' => [
                'hint' => 'Lieu d’intérêt ou site d’exploitation.',
                'fields' => [
                    ['name' => 'site_type', 'label' => 'Type de site', 'type' => 'select', 'options' => $siteTypes],
                    ['name' => 'status', 'label' => 'État d’exploitation', 'type' => 'select', 'options' => SseSiteRepository::STATUS_LABELS],
                    ['name' => 'grid', 'label' => 'Repère / grille', 'type' => 'text', 'placeholder' => 'Coordonnées ou toponyme'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'access', 'label' => 'Accès / approche', 'type' => 'textarea', 'placeholder' => 'Chemins, obstacles, points d’entrée', 'rows' => 3],
                    ['name' => 'description', 'label' => 'Description du site', 'type' => 'textarea', 'placeholder' => 'Disposition, pièces, activité observée…', 'rows' => 4],
                ],
            ],
            'vehicle' => [
                'hint' => 'Véhicule observé ou saisi.',
                'fields' => [
                    ['name' => 'vehicle_type', 'label' => 'Type', 'type' => 'select', 'options' => $vehicleType],
                    ['name' => 'plate', 'label' => 'Immatriculation', 'type' => 'text'],
                    ['name' => 'color', 'label' => 'Couleur', 'type' => 'text'],
                    ['name' => 'model', 'label' => 'Marque / modèle', 'type' => 'text'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'notes', 'label' => 'Observations', 'type' => 'textarea', 'placeholder' => 'Dommages, chargement, passagers…', 'rows' => 3],
                ],
            ],
            'weapon' => [
                'hint' => 'Arme ou matériel sensible.',
                'fields' => [
                    ['name' => 'weapon_type', 'label' => 'Catégorie', 'type' => 'select', 'options' => $weaponType],
                    ['name' => 'serial', 'label' => 'Numéro / marquage', 'type' => 'text'],
                    ['name' => 'condition', 'label' => 'État', 'type' => 'select', 'options' => [
                        'inconnu' => 'Inconnu',
                        'service' => 'En service',
                        'endommage' => 'Endommagé',
                        'neutralise' => 'Neutralisé',
                    ]],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'notes', 'label' => 'Notes', 'type' => 'textarea', 'placeholder' => 'Accessoires, munitions associées, contexte de découverte', 'rows' => 3],
                ],
            ],
            'phone' => [
                'hint' => 'Téléphone ou support mobile.',
                'fields' => [
                    ['name' => 'os', 'label' => 'Système', 'type' => 'select', 'options' => $phoneOs],
                    ['name' => 'imei', 'label' => 'IMEI / identifiant', 'type' => 'text'],
                    ['name' => 'number', 'label' => 'Numéro observé', 'type' => 'text'],
                    ['name' => 'condition', 'label' => 'État', 'type' => 'select', 'options' => [
                        'intact' => 'Intact',
                        'endommage' => 'Endommagé',
                        'verrouille' => 'Verrouillé',
                        'inconnu' => 'Inconnu',
                    ]],
                    ['name' => 'notes', 'label' => 'Notes d’exploitation', 'type' => 'textarea', 'placeholder' => 'Applications vues, contacts saillants, état de charge…', 'rows' => 3],
                ],
            ],
            'terminal' => [
                'hint' => 'Ordinateur, tablette ou terminal.',
                'fields' => [
                    ['name' => 'terminal_kind', 'label' => 'Type', 'type' => 'select', 'options' => [
                        'portable' => 'Portable',
                        'fixe' => 'Poste fixe',
                        'tablette' => 'Tablette',
                        'serveur' => 'Serveur',
                        'autre' => 'Autre',
                    ]],
                    ['name' => 'os', 'label' => 'Système', 'type' => 'text', 'placeholder' => 'Ex. Windows, Linux'],
                    ['name' => 'serial', 'label' => 'N° de série', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Contenu / observations', 'type' => 'textarea', 'placeholder' => 'Fichiers remarqués, comptes, chiffrement…', 'rows' => 4],
                ],
            ],
            'document' => [
                'hint' => 'Pièce documentaire versée au registre — joignez une image si disponible.',
                'fields' => [
                    ['name' => 'doc_type', 'label' => 'Type de document', 'type' => 'select', 'options' => $docType],
                    ['name' => 'language', 'label' => 'Langue', 'type' => 'text'],
                    ['name' => 'reliability', 'label' => 'Fiabilité source', 'type' => 'select', 'options' => $reliability],
                    ['name' => 'date_doc', 'label' => 'Date du document', 'type' => 'text', 'placeholder' => 'Si connue'],
                    ['name' => 'origin', 'label' => 'Provenance', 'type' => 'text', 'placeholder' => 'Où / sur qui a-t-il été trouvé'],
                    ['name' => 'summary', 'label' => 'Résumé du contenu', 'type' => 'textarea', 'placeholder' => 'Ce que dit le document, sans recopier tout le texte', 'rows' => 4],
                    ['name' => 'transcription', 'label' => 'Transcription / extrait', 'type' => 'textarea', 'placeholder' => 'Passages utiles, traduction, citations', 'rows' => 6],
                ],
            ],
            'event' => [
                'hint' => 'Fait ou observation datée.',
                'fields' => [
                    ['name' => 'event_type', 'label' => 'Nature', 'type' => 'select', 'options' => $eventType],
                    ['name' => 'when_label', 'label' => 'Quand', 'type' => 'text', 'placeholder' => 'Date / heure approximative'],
                    ['name' => 'where_label', 'label' => 'Où', 'type' => 'text'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'narrative', 'label' => 'Récit des faits', 'type' => 'textarea', 'placeholder' => 'Décrivez ce qui s’est passé, dans l’ordre', 'rows' => 5],
                    ['name' => 'actors', 'label' => 'Acteurs / témoins', 'type' => 'textarea', 'placeholder' => 'Qui était présent, rôles observés', 'rows' => 3],
                ],
            ],
            'report' => [
                'hint' => 'Compte rendu, flash ou note d’analyse — rédigez le corps du document ici.',
                'fields' => [
                    ['name' => 'report_kind', 'label' => 'Type', 'type' => 'select', 'options' => [
                        'flash' => 'Flash',
                        'compte_rendu' => 'Compte rendu',
                        'note' => 'Note d’analyse',
                        'synthese' => 'Synthèse',
                        'sitrep' => 'Situation (SITREP)',
                    ]],
                    ['name' => 'author', 'label' => 'Rédacteur', 'type' => 'text'],
                    ['name' => 'dtg', 'label' => 'Date / heure du produit', 'type' => 'text', 'placeholder' => 'Ex. 07 août 2026 — 19h20'],
                    ['name' => 'classification_note', 'label' => 'Niveau de diffusion', 'type' => 'select', 'options' => SseCaseRepository::CLASSIFICATION_LABELS],
                    ['name' => 'subject', 'label' => 'Objet', 'type' => 'text', 'placeholder' => 'Ce que le produit couvre'],
                    ['name' => 'situation', 'label' => 'Situation', 'type' => 'textarea', 'placeholder' => 'Contexte et état des lieux', 'rows' => 4],
                    ['name' => 'body', 'label' => 'Corps du compte rendu', 'type' => 'textarea', 'placeholder' => 'Faits, exploitation, appréciation…', 'rows' => 10],
                    ['name' => 'follow_on', 'label' => 'Suites à donner', 'type' => 'textarea', 'placeholder' => 'Actions recommandées, priorités', 'rows' => 4],
                ],
            ],
            'photo' => [
                'hint' => 'Photographie ou capture terrain — téléversez l’image ci-dessous.',
                'fields' => [
                    ['name' => 'angle', 'label' => 'Angle / sujet', 'type' => 'select', 'options' => [
                        'visage' => 'Visage',
                        'plan_large' => 'Plan large',
                        'detail' => 'Détail',
                        'document' => 'Document photographié',
                        'autre' => 'Autre',
                    ]],
                    ['name' => 'taken_at', 'label' => 'Prise de vue', 'type' => 'text', 'placeholder' => 'Date / heure si connue'],
                    ['name' => 'place', 'label' => 'Lieu', 'type' => 'text'],
                    ['name' => 'caption', 'label' => 'Légende', 'type' => 'textarea', 'placeholder' => 'Ce que montre la photo, éléments utiles', 'rows' => 3],
                ],
            ],
            'biometric' => [
                'hint' => 'Relevé biométrique — reste une proposition jusqu’à consolidation.',
                'fields' => [
                    ['name' => 'bio_kind', 'label' => 'Modalité', 'type' => 'select', 'options' => $biometry],
                    ['name' => 'quality', 'label' => 'Qualité', 'type' => 'select', 'options' => [
                        'faible' => 'Faible',
                        'moyenne' => 'Moyenne',
                        'bonne' => 'Bonne',
                        'excellente' => 'Excellente',
                    ]],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'notes', 'label' => 'Conditions de prélèvement', 'type' => 'textarea', 'placeholder' => 'Contexte, matériel utilisé, réserves', 'rows' => 3],
                ],
            ],
            'seizure' => [
                'hint' => 'Élément saisi sur site.',
                'fields' => [
                    ['name' => 'seizure_type', 'label' => 'Catégorie', 'type' => 'select', 'options' => $seizure],
                    ['name' => 'quantity', 'label' => 'Quantité', 'type' => 'text'],
                    ['name' => 'place', 'label' => 'Lieu de saisie', 'type' => 'text'],
                    ['name' => 'condition', 'label' => 'État', 'type' => 'text'],
                    ['name' => 'notes', 'label' => 'Notes de saisie', 'type' => 'textarea', 'placeholder' => 'Chaîne de possession, emballage, remarques', 'rows' => 3],
                ],
            ],
            'custom' => [
                'hint' => 'Élément libre — précisez la nature pour rester exploitable.',
                'fields' => [
                    ['name' => 'nature', 'label' => 'Nature', 'type' => 'text', 'placeholder' => 'Ex. cache, signalement anonyme…'],
                    ['name' => 'confidence', 'label' => 'Confiance', 'type' => 'select', 'options' => $confidence],
                    ['name' => 'note', 'label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Ce que représente cet élément', 'rows' => 4],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    public static function formatMetaLines(string $kind, array $meta): array
    {
        $schema = self::metaSchema()[self::normalizeKind($kind)] ?? null;
        if ($schema === null) {
            return [];
        }
        $lines = [];
        foreach ($schema['fields'] as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '' || !array_key_exists($name, $meta)) {
                continue;
            }
            if (in_array($name, ['image_path', 'image_url'], true)) {
                continue;
            }
            $raw = trim((string) $meta[$name]);
            if ($raw === '') {
                continue;
            }
            if (($field['type'] ?? '') === 'select' && is_array($field['options'] ?? null) && isset($field['options'][$raw])) {
                $raw = (string) $field['options'][$raw];
            }
            if (($field['type'] ?? '') === 'textarea' && mb_strlen($raw) > 140) {
                $raw = mb_substr($raw, 0, 137) . '…';
            }
            $lines[] = (string) ($field['label'] ?? $name) . ' : ' . $raw;
        }
        if (!empty($meta['image_path'])) {
            $lines[] = 'Image jointe';
        }

        return $lines;
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function encodeMetaJson(array $meta): ?string
    {
        $clean = [];
        foreach ($meta as $k => $v) {
            $key = preg_replace('/[^a-z0-9_]/i', '', (string) $k) ?? '';
            $val = trim((string) $v);
            if ($key === '' || $val === '') {
                continue;
            }
            $max = str_ends_with($key, '_path') || $key === 'image_url' ? 512 : 12000;
            $clean[$key] = mb_substr($val, 0, $max);
        }
        if ($clean === []) {
            return null;
        }

        return json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null;
    }

    /**
     * @return array<string, string>
     */
    public static function decodeMetaJson(mixed $raw): array
    {
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $k => $v) {
            if (!is_string($k) && !is_int($k)) {
                continue;
            }
            $out[(string) $k] = trim((string) $v);
        }

        return $out;
    }

    public static function normalizeRelation(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::RELATION_LABELS[$s]) ? $s : 'associe';
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $tenantId = (int) ($data['tenant_id'] ?? 0);
        $ref = trim((string) ($data['reference_code'] ?? ''));
        if ($ref === '') {
            $ref = $this->nextReference($tenantId);
        }

        return (int) $this->db->insert(
            'INSERT INTO sse_meshes (
                tenant_id, reference_code, title, summary, case_id, classification, status, created_by
            ) VALUES (
                :tenant_id, :reference_code, :title, :summary, :case_id, :classification, :status, :created_by
            )',
            [
                'tenant_id' => $tenantId,
                'reference_code' => $ref,
                'title' => trim((string) ($data['title'] ?? 'Toile sans titre')),
                'summary' => $this->nullIfEmpty($data['summary'] ?? null),
                'case_id' => !empty($data['case_id']) ? (int) $data['case_id'] : null,
                'classification' => SseCaseRepository::normalizeClassification(
                    (string) ($data['classification'] ?? SseCaseRepository::CLASS_COMMAND)
                ),
                'status' => $this->normalizeStatus((string) ($data['status'] ?? 'ouvert')),
                'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
            ]
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForTenant(int $tenantId, array $filters = []): array
    {
        $where = ['tenant_id = :t'];
        $params = ['t' => $tenantId];
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $this->normalizeStatus((string) $filters['status']);
        }
        if (!empty($filters['case_id'])) {
            $where[] = 'case_id = :case_id';
            $params['case_id'] = (int) $filters['case_id'];
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
            $where[] = '(reference_code LIKE :q_ref OR title LIKE :q_title OR summary LIKE :q_summary)';
            $params['q_ref'] = $like;
            $params['q_title'] = $like;
            $params['q_summary'] = $like;
        }
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_meshes WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 200',
            $params
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrateMesh($row);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, int $tenantId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM sse_meshes WHERE id = :id AND tenant_id = :t LIMIT 1',
            ['id' => $id, 't' => $tenantId]
        );

        return $row ? $this->hydrateMesh($row) : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id, 't' => $tenantId];
        foreach (['title', 'summary', 'classification', 'status', 'case_id'] as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            if ($k === 'classification') {
                $fields[] = 'classification = :classification';
                $params['classification'] = SseCaseRepository::normalizeClassification((string) $data['classification']);
            } elseif ($k === 'status') {
                $fields[] = 'status = :status';
                $params['status'] = $this->normalizeStatus((string) $data['status']);
            } elseif ($k === 'summary') {
                $fields[] = 'summary = :summary';
                $params['summary'] = $this->nullIfEmpty($data['summary']);
            } elseif ($k === 'case_id') {
                $fields[] = 'case_id = :case_id';
                $params['case_id'] = !empty($data['case_id']) ? (int) $data['case_id'] : null;
            } else {
                $fields[] = 'title = :title';
                $params['title'] = trim((string) $data['title']);
            }
        }
        if ($fields === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_meshes SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :t',
            $params
        ) > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addNode(int $meshId, int $tenantId, array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_mesh_nodes (
                mesh_id, tenant_id, kind, label, detail, meta_json, ref_type, ref_id, pos_x, pos_y
            ) VALUES (
                :mesh_id, :tenant_id, :kind, :label, :detail, :meta_json, :ref_type, :ref_id, :pos_x, :pos_y
            )',
            [
                'mesh_id' => $meshId,
                'tenant_id' => $tenantId,
                'kind' => self::normalizeKind((string) ($data['kind'] ?? 'custom')),
                'label' => trim((string) ($data['label'] ?? 'Sans libellé')),
                'detail' => $this->nullIfEmpty($data['detail'] ?? null),
                'meta_json' => $this->nullIfEmpty($data['meta_json'] ?? null),
                'ref_type' => $this->nullIfEmpty($data['ref_type'] ?? null),
                'ref_id' => !empty($data['ref_id']) ? (int) $data['ref_id'] : null,
                'pos_x' => (float) ($data['pos_x'] ?? (random_int(80, 720))),
                'pos_y' => (float) ($data['pos_y'] ?? (random_int(80, 420))),
            ]
        );
    }

    public function updateNodePosition(int $nodeId, int $tenantId, float $x, float $y, ?int $meshId = null): bool
    {
        $params = [
            'x' => $x,
            'y' => $y,
            'id' => $nodeId,
            't' => $tenantId,
        ];
        $sql = 'UPDATE sse_mesh_nodes SET pos_x = :x, pos_y = :y WHERE id = :id AND tenant_id = :t';
        if ($meshId !== null && $meshId > 0) {
            $sql .= ' AND mesh_id = :m';
            $params['m'] = $meshId;
        }
        try {
            $this->db->execute($sql, $params);
            // rowCount peut être 0 si les coords sont déjà identiques : vérifier l’existence.
            $checkSql = 'SELECT id FROM sse_mesh_nodes WHERE id = :id AND tenant_id = :t';
            $checkParams = ['id' => $nodeId, 't' => $tenantId];
            if ($meshId !== null && $meshId > 0) {
                $checkSql .= ' AND mesh_id = :m';
                $checkParams['m'] = $meshId;
            }
            $checkSql .= ' LIMIT 1';

            return $this->db->fetchOne($checkSql, $checkParams) !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    public function updateNode(int $nodeId, int $tenantId, array $data): bool
    {
        $fields = [];
        $params = ['id' => $nodeId, 't' => $tenantId];
        if (array_key_exists('label', $data)) {
            $fields[] = 'label = :label';
            $params['label'] = trim((string) $data['label']);
        }
        if (array_key_exists('detail', $data)) {
            $fields[] = 'detail = :detail';
            $params['detail'] = $this->nullIfEmpty($data['detail']);
        }
        if (array_key_exists('kind', $data)) {
            $fields[] = 'kind = :kind';
            $params['kind'] = self::normalizeKind((string) $data['kind']);
        }
        if ($fields === []) {
            return false;
        }

        return $this->db->execute(
            'UPDATE sse_mesh_nodes SET ' . implode(', ', $fields) . ' WHERE id = :id AND tenant_id = :t',
            $params
        ) > 0;
    }

    public function deleteNode(int $nodeId, int $tenantId): bool
    {
        return $this->db->execute(
            'DELETE FROM sse_mesh_nodes WHERE id = :id AND tenant_id = :t',
            ['id' => $nodeId, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listNodes(int $meshId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_mesh_nodes WHERE mesh_id = :m AND tenant_id = :t ORDER BY id ASC',
            ['m' => $meshId, 't' => $tenantId]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrateNode($row);
        }

        return $this->attachLinkedPersonPhotos($out, $tenantId);
    }

    /**
     * Objets d’un type donné, toutes investigations confondues (registre transversal).
     *
     * @param list<string>|string $kinds
     * @return list<array<string, mixed>>
     */
    public function listNodesByKindForTenant(int $tenantId, array|string $kinds, int $limit = 120): array
    {
        if ($tenantId < 1) {
            return [];
        }
        $kinds = array_values(array_unique(array_map(
            static fn (string $k): string => self::normalizeKind($k),
            is_array($kinds) ? $kinds : [$kinds]
        )));
        if ($kinds === []) {
            return [];
        }
        $limit = max(1, min(200, $limit));
        $placeholders = [];
        $params = ['t' => $tenantId];
        foreach ($kinds as $i => $kind) {
            $key = 'k' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $kind;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT n.*, m.reference_code AS mesh_reference, m.title AS mesh_title, m.id AS mesh_id
                 FROM sse_mesh_nodes n
                 INNER JOIN sse_meshes m ON m.id = n.mesh_id AND m.tenant_id = n.tenant_id
                 WHERE n.tenant_id = :t AND n.kind IN (' . implode(',', $placeholders) . ')
                 ORDER BY n.id DESC
                 LIMIT ' . $limit,
                $params
            );
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $node = $this->hydrateNode($row);
            $node['mesh_id'] = (int) ($row['mesh_id'] ?? 0);
            $node['mesh_reference'] = (string) ($row['mesh_reference'] ?? '');
            $node['mesh_title'] = (string) ($row['mesh_title'] ?? '');
            $node['href'] = url('atak/sse/toiles/' . $node['mesh_id']);
            $out[] = $node;
        }

        return $this->attachLinkedPersonPhotos($out, $tenantId);
    }

    /**
     * Si un nœud identité pointe vers une fiche personne sans image locale,
     * reprend la photo primaire (captures terrain / SEEK).
     *
     * @param list<array<string, mixed>> $nodes
     * @return list<array<string, mixed>>
     */
    private function attachLinkedPersonPhotos(array $nodes, int $tenantId): array
    {
        $personIds = [];
        foreach ($nodes as $n) {
            if (!empty($n['image_url']) || !empty($n['image_path'])) {
                continue;
            }
            $refType = strtolower((string) ($n['ref_type'] ?? ''));
            $kind = (string) ($n['kind'] ?? '');
            $refId = (int) ($n['ref_id'] ?? 0);
            if ($refId > 0 && ($refType === 'person' || $kind === 'person')) {
                $personIds[$refId] = true;
            }
        }
        if ($personIds === []) {
            return $nodes;
        }

        $ids = array_keys($personIds);
        $placeholders = [];
        $params = ['t' => $tenantId];
        foreach ($ids as $i => $pid) {
            $key = 'p' . $i;
            $placeholders[] = ':' . $key;
            $params[$key] = $pid;
        }

        try {
            $rows = $this->db->fetchAll(
                'SELECT p.id AS person_id,
                        COALESCE(ph.image_path, ph2.image_path) AS image_path
                 FROM sse_persons p
                 LEFT JOIN sse_person_photos ph ON ph.id = p.primary_photo_id AND ph.tenant_id = p.tenant_id
                 LEFT JOIN sse_person_photos ph2 ON ph2.person_id = p.id AND ph2.tenant_id = p.tenant_id
                    AND ph2.id = (
                        SELECT MIN(ph3.id) FROM sse_person_photos ph3
                        WHERE ph3.person_id = p.id AND ph3.tenant_id = p.tenant_id
                    )
                 WHERE p.tenant_id = :t AND p.id IN (' . implode(',', $placeholders) . ')',
                $params
            );
        } catch (\Throwable) {
            return $nodes;
        }

        $byPerson = [];
        foreach ($rows as $row) {
            $path = trim((string) ($row['image_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $byPerson[(int) ($row['person_id'] ?? 0)] = $path;
        }
        if ($byPerson === []) {
            return $nodes;
        }

        foreach ($nodes as $i => $n) {
            if (!empty($n['image_url']) || !empty($n['image_path'])) {
                continue;
            }
            $refId = (int) ($n['ref_id'] ?? 0);
            $path = $byPerson[$refId] ?? '';
            if ($path === '') {
                continue;
            }
            $nodes[$i]['image_path'] = $path;
            $nodes[$i]['image_url'] = function_exists('user_media_public_url')
                ? user_media_public_url($path)
                : null;
            if (!empty($nodes[$i]['image_url']) && function_exists('normalize_public_uploads_url')) {
                $nodes[$i]['image_url'] = normalize_public_uploads_url((string) $nodes[$i]['image_url']);
            }
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateNode(array $row): array
    {
        $kind = self::normalizeKind((string) ($row['kind'] ?? 'custom'));
        $meta = self::decodeMetaJson($row['meta_json'] ?? null);
        $resolved = self::resolveNodeImage($meta);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'mesh_id' => (int) ($row['mesh_id'] ?? 0),
            'kind' => $kind,
            'kind_label' => self::kindLabel($kind),
            'label' => (string) ($row['label'] ?? ''),
            'detail' => $row['detail'] ?? null,
            'meta' => $meta,
            'meta_lines' => self::formatMetaLines($kind, $meta),
            'image_path' => $resolved['path'],
            'image_url' => $resolved['url'],
            'ref_type' => $row['ref_type'] ?? null,
            'ref_id' => isset($row['ref_id']) ? (int) $row['ref_id'] : null,
            'pos_x' => (float) ($row['pos_x'] ?? 0),
            'pos_y' => (float) ($row['pos_y'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{path:?string, url:?string}
     */
    public static function resolveNodeImage(array $meta): array
    {
        $path = trim((string) ($meta['image_path'] ?? ''));
        $rawUrl = trim((string) ($meta['image_url'] ?? ''));
        if ($path === '' && $rawUrl !== '') {
            if (preg_match('#^https?://#i', $rawUrl) === 1 || str_starts_with($rawUrl, '/')) {
                $url = function_exists('normalize_public_uploads_url')
                    ? normalize_public_uploads_url($rawUrl)
                    : $rawUrl;

                return ['path' => null, 'url' => $url];
            }
            $path = $rawUrl;
        }
        if ($path === '') {
            return ['path' => null, 'url' => null];
        }
        $path = str_replace('\\', '/', $path);
        if (preg_match('#(?:^|/)(uploads/.+)$#i', $path, $m)) {
            $path = $m[1];
        }
        $url = function_exists('user_media_public_url') ? user_media_public_url($path) : null;
        if ($url !== null && function_exists('normalize_public_uploads_url')) {
            $url = normalize_public_uploads_url($url);
        }

        return [
            'path' => $path !== '' ? $path : null,
            'url' => $url,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function addEdge(int $meshId, int $tenantId, array $data): int
    {
        return (int) $this->db->insert(
            'INSERT INTO sse_mesh_edges (
                mesh_id, tenant_id, from_node_id, to_node_id, relation, note, reliability, created_by, author_label
            ) VALUES (
                :mesh_id, :tenant_id, :from_node_id, :to_node_id, :relation, :note, :reliability, :created_by, :author_label
            )',
            [
                'mesh_id' => $meshId,
                'tenant_id' => $tenantId,
                'from_node_id' => (int) ($data['from_node_id'] ?? 0),
                'to_node_id' => (int) ($data['to_node_id'] ?? 0),
                'relation' => self::normalizeRelation((string) ($data['relation'] ?? 'associe')),
                'note' => $this->nullIfEmpty($data['note'] ?? null),
                'reliability' => in_array((string) ($data['reliability'] ?? ''), ['unverified', 'corroborated', 'confirmed', 'conflicting'], true)
                    ? (string) $data['reliability']
                    : 'unverified',
                'created_by' => isset($data['created_by']) ? (int) $data['created_by'] : null,
                'author_label' => $this->nullIfEmpty($data['author_label'] ?? null),
            ]
        );
    }

    public function deleteEdge(int $edgeId, int $tenantId): bool
    {
        return $this->db->execute(
            'DELETE FROM sse_mesh_edges WHERE id = :id AND tenant_id = :t',
            ['id' => $edgeId, 't' => $tenantId]
        ) > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listEdges(int $meshId, int $tenantId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM sse_mesh_edges WHERE mesh_id = :m AND tenant_id = :t ORDER BY id ASC',
            ['m' => $meshId, 't' => $tenantId]
        );
        $out = [];
        foreach ($rows as $row) {
            $rel = self::normalizeRelation((string) ($row['relation'] ?? 'associe'));
            $reliability = (string) ($row['reliability'] ?? 'unverified');
            if (!isset(self::EDGE_STATUS_LABELS[$reliability])) {
                $reliability = 'unverified';
            }
            $out[] = [
                'id' => (int) $row['id'],
                'from_node_id' => (int) $row['from_node_id'],
                'to_node_id' => (int) $row['to_node_id'],
                'relation' => $rel,
                'relation_label' => self::relationLabel($rel),
                'note' => $row['note'] ?? null,
                'reliability' => $reliability,
                'reliability_label' => self::EDGE_STATUS_LABELS[$reliability] ?? $reliability,
                'author_label' => $row['author_label'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array{nodes:int, edges:int}>
     */
    public function countsForMeshes(array $meshIds, int $tenantId): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $meshIds), static fn (int $i): bool => $i > 0)));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['nodes' => 0, 'edges' => 0];
        }
        if ($ids === []) {
            return $out;
        }
        $in = implode(',', $ids);
        foreach ($this->db->fetchAll(
            "SELECT mesh_id, COUNT(*) AS n FROM sse_mesh_nodes WHERE tenant_id = :t AND mesh_id IN ($in) GROUP BY mesh_id",
            ['t' => $tenantId]
        ) as $row) {
            $out[(int) $row['mesh_id']]['nodes'] = (int) $row['n'];
        }
        foreach ($this->db->fetchAll(
            "SELECT mesh_id, COUNT(*) AS n FROM sse_mesh_edges WHERE tenant_id = :t AND mesh_id IN ($in) GROUP BY mesh_id",
            ['t' => $tenantId]
        ) as $row) {
            $out[(int) $row['mesh_id']]['edges'] = (int) $row['n'];
        }

        return $out;
    }

    private function nextReference(int $tenantId): string
    {
        $year = date('Y');
        $prefix = 'MESH-' . $year . '-';
        $row = $this->db->fetchOne(
            'SELECT reference_code FROM sse_meshes WHERE tenant_id = :t AND reference_code LIKE :p ORDER BY id DESC LIMIT 1',
            ['t' => $tenantId, 'p' => $prefix . '%']
        );
        $n = 1;
        if ($row && preg_match('/(\d+)$/', (string) $row['reference_code'], $m)) {
            $n = (int) $m[1] + 1;
        }

        return $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function hydrateMesh(array $row): array
    {
        $class = SseCaseRepository::normalizeClassification((string) ($row['classification'] ?? 'encadrement'));
        $status = $this->normalizeStatus((string) ($row['status'] ?? 'ouvert'));

        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => (int) ($row['tenant_id'] ?? 0),
            'reference_code' => (string) ($row['reference_code'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'summary' => $row['summary'] ?? null,
            'case_id' => isset($row['case_id']) && $row['case_id'] !== null ? (int) $row['case_id'] : null,
            'classification' => $class,
            'classification_label' => SseCaseRepository::classificationLabel($class),
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? $status,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizeStatus(string $raw): string
    {
        $s = strtolower(trim($raw));

        return isset(self::STATUS_LABELS[$s]) ? $s : 'ouvert';
    }

    private function nullIfEmpty(mixed $v): ?string
    {
        $s = trim((string) ($v ?? ''));

        return $s === '' ? null : $s;
    }
}
