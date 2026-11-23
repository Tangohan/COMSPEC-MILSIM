<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\SseCaseRepository;
use App\Services\Sse\SseRedactionService;

/**
 * Choix proposés par les assistants de création du portail SSE (toile, dossier, objet) : clés autorisées,
 * libellés et phrase d'explication de chaque carte. Rien de sensible ici, et aucune colonne nouvelle : les choix
 * « type de toile », « thème » et « urgence » s'écrivent en tête de la synthèse, en clair, à partir de clés connues.
 */
final class SseCreationBrief
{
    /** @var array<string, array{label:string, desc:string, tpl:string}> */
    public const MESH_TYPES = [
        'reseau' => [
            'label' => 'Réseau de relations',
            'desc' => 'Qui connaît qui, qui possède quoi : personnes, organisations, objets et leurs liens.',
            'tpl' => 'Réseau à cartographier : qui est lié à qui, et par quel moyen ? Questions à trancher :',
        ],
        'chrono' => [
            'label' => 'Chronologie d’événements',
            'desc' => 'Des faits placés dans l’ordre du temps pour comprendre une séquence.',
            'tpl' => 'Séquence à reconstituer : que s’est-il passé, dans quel ordre ? Période concernée :',
        ],
        'lieux' => [
            'label' => 'Lieux et déplacements',
            'desc' => 'Sites, itinéraires et points de rencontre, pour voir où les éléments se recoupent.',
            'tpl' => 'Lieux à relier : quels sites, quels trajets, quels points de rencontre ? Secteur concerné :',
        ],
        'hypothese' => [
            'label' => 'Hypothèse à tester',
            'desc' => 'Une idée à confirmer ou à écarter, avec les éléments pour et contre.',
            'tpl' => 'Hypothèse : … Ce qui la confirmerait : … Ce qui l’écarterait :',
        ],
        'libre' => [
            'label' => 'Toile libre',
            'desc' => 'Aucun cadre imposé : vous construisez la toile au fil de l’eau.',
            'tpl' => '',
        ],
    ];

    /** @var array<string, array{label:string, desc:string}> */
    public const MESH_STARTS = [
        'vide' => [
            'label' => 'Une toile vide',
            'desc' => 'Vous ajouterez vous-même les premières entités sur le canevas.',
        ],
        'dossier' => [
            'label' => 'Un dossier existant',
            'desc' => 'Reprend les entités et les liens déjà connus du dossier, sous forme de propositions de rapprochement.',
        ],
    ];

    /** @var array<string, array{label:string, desc:string}> */
    public const CASE_TYPES = [
        'affaire' => [
            'label' => 'Dossier d’affaire',
            'desc' => 'Une affaire à instruire : pièces, personnes, notes, carte et compte rendu.',
        ],
        'classeur' => [
            'label' => 'Classeur',
            'desc' => 'Un dossier qui sert à ranger d’autres dossiers, par secteur ou par période.',
        ],
    ];

    /** @var array<string, array{label:string, desc:string}> */
    public const CASE_THEMES = [
        'site' => [
            'label' => 'Site ou installation',
            'desc' => 'Un lieu à documenter ou à surveiller.',
        ],
        'personnes' => [
            'label' => 'Personnes ou réseau',
            'desc' => 'Des individus, des groupes et leurs relations.',
        ],
        'incident' => [
            'label' => 'Incident ou événement',
            'desc' => 'Un fait précis à reconstituer.',
        ],
        'materiel' => [
            'label' => 'Matériel et saisies',
            'desc' => 'Des équipements, des pièces ou des documents saisis.',
        ],
        'autre' => [
            'label' => 'Autre sujet',
            'desc' => 'Rien de ce qui précède ne convient.',
        ],
    ];

    /** @var array<string, array{label:string, desc:string}> */
    public const CASE_URGENCY = [
        'routine' => [
            'label' => 'Routine',
            'desc' => 'Aucun délai particulier : traitement dans le cours normal du travail.',
        ],
        'prioritaire' => [
            'label' => 'Prioritaire',
            'desc' => 'À traiter avant les dossiers courants, dans les prochains jours.',
        ],
        'immediate' => [
            'label' => 'Immédiate',
            'desc' => 'À traiter tout de suite : la situation évolue ou un délai est proche.',
        ],
    ];

    /** @var array<string, array{label:string, desc:string}> */
    public const OBJECT_KINDS = [
        'person' => ['label' => 'Identité', 'desc' => 'Une personne, décrite sans inventer d’état civil.'],
        'alias' => ['label' => 'Alias', 'desc' => 'Un pseudonyme ou un nom d’emprunt.'],
        'organization' => ['label' => 'Organisation', 'desc' => 'Un groupe, une société ou un service.'],
        'site' => ['label' => 'Site', 'desc' => 'Un lieu, un bâtiment ou une zone.'],
        'vehicle' => ['label' => 'Véhicule', 'desc' => 'Un véhicule, avec son immatriculation si elle est connue.'],
        'weapon' => ['label' => 'Arme / matériel', 'desc' => 'Une arme, un équipement ou une optique.'],
        'phone' => ['label' => 'Téléphone', 'desc' => 'Un numéro ou un appareil de téléphonie.'],
        'terminal' => ['label' => 'Terminal', 'desc' => 'Un ordinateur, une tablette ou tout autre terminal.'],
        'document' => ['label' => 'Document', 'desc' => 'Une pièce écrite : note, plan, liste, ordre.'],
        'event' => ['label' => 'Événement', 'desc' => 'Un fait daté : observation, mouvement, contact.'],
        'report' => ['label' => 'Compte rendu', 'desc' => 'Un rapport produit par un tiers ou par le bureau.'],
        'photo' => ['label' => 'Photographie', 'desc' => 'Une image ou une capture à conserver.'],
        'biometric' => ['label' => 'Relevé biométrique', 'desc' => 'Un relevé qui sert à reconnaître une personne.'],
        'seizure' => ['label' => 'Saisie', 'desc' => 'Un objet ou un lot saisi lors d’une opération.'],
        'custom' => ['label' => 'Élément libre', 'desc' => 'Tout ce qui ne rentre dans aucune autre catégorie.'],
    ];

    /** Catégories d'objets pour lesquelles un état de conservation a un sens (objets physiques ou supports). */
    public const PHYSICAL_KINDS = ['vehicle', 'weapon', 'phone', 'terminal', 'document', 'photo', 'seizure'];

    /** @var array<string, array{label:string, desc:string}> */
    public const OBJECT_STATES = [
        'non_verifie' => ['label' => 'Non vérifié', 'desc' => 'Personne n’a encore examiné l’objet.'],
        'intact' => ['label' => 'Intact', 'desc' => 'Complet et en bon état.'],
        'abime' => ['label' => 'Endommagé', 'desc' => 'Usé, cassé ou altéré, mais encore exploitable.'],
        'incomplet' => ['label' => 'Incomplet', 'desc' => 'Il manque des éléments ou des pages.'],
    ];

    /** @var array<string, string> */
    public const CLASSIFICATION_HELP = [
        SseCaseRepository::CLASS_INTERNAL => 'Information interne : diffusion large dans le portail.',
        SseCaseRepository::CLASS_COMMAND => 'Usage courant du bureau : à réserver aux membres qui travaillent sur le sujet.',
        SseCaseRepository::CLASS_CONFIDENTIAL => 'Accès restreint : seuls les membres habilités à ce niveau pourront l’ouvrir.',
        SseCaseRepository::CLASS_RESTRICTED => 'Accès très limité : à utiliser seulement si le contenu l’exige.',
    ];

    /** Retourne la clé si elle figure dans la liste, sinon la valeur par défaut. */
    public static function pick(string $raw, array $allowed, string $default): string
    {
        $key = strtolower(trim($raw));

        return isset($allowed[$key]) ? $key : $default;
    }

    /** Niveau de classification demandé, ramené à la liste connue puis au plafond de la session. */
    public static function capClassification(string $requested, string $fallback, string $maxLevel): string
    {
        $level = strtolower(trim($requested));
        if (!isset(SseRedactionService::LEVELS[$level])) {
            $level = $fallback;
        }
        if (SseRedactionService::levelRank($level) > SseRedactionService::levelRank($maxLevel)) {
            return $maxLevel;
        }

        return $level;
    }

    /**
     * Synthèse enregistrée : une première ligne de repères (« Type : … · Thème : … »), puis le texte libre.
     *
     * @param array<string, string> $marks libellé => valeur (les valeurs vides sont ignorées)
     */
    public static function composeSummary(array $marks, string $free): string
    {
        $parts = [];
        foreach ($marks as $label => $value) {
            $value = trim($value);
            if ($value !== '') {
                $parts[] = $label . ' : ' . $value;
            }
        }
        $free = trim($free);
        if ($parts === []) {
            return $free;
        }
        $head = implode(' · ', $parts);

        return $free === '' ? $head : $head . "\n\n" . $free;
    }
}
