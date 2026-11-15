<?php

declare(strict_types=1);

namespace App\Services\DataQuality;

/**
 * Ce que les tableaux de la qualité des données mesurent : champs de la fiche regroupés en blocs (ceux de la campagne de
 * fiabilisation) et vérifications d'anomalies. Codes stables, textes français (traduits à l'affichage).
 */
final class DataQualityCatalog
{
    public const BLOCK_IDENTITY = 'identity';
    public const BLOCK_CONTACT = 'contact';
    public const BLOCK_IDENTIFIERS = 'identifiers';
    public const BLOCK_ASSIGNMENT = 'assignment';

    /** @var array<string, string> */
    public const BLOCKS = [
        self::BLOCK_IDENTITY => 'Identité',
        self::BLOCK_CONTACT => 'Contact',
        self::BLOCK_IDENTIFIERS => 'Matricules et identifiants',
        self::BLOCK_ASSIGNMENT => 'Affectation',
    ];

    /**
     * Champ => bloc et libellé. Un champ est « renseigné » quand sa valeur n'est pas vide (DataQualityAnalyzer::isFilled).
     *
     * @var array<string, array{block: string, label: string}>
     */
    public const FIELDS = [
        'display_name' => ['block' => self::BLOCK_IDENTITY, 'label' => 'Nom affiché'],
        'callsign' => ['block' => self::BLOCK_IDENTITY, 'label' => 'Indicatif'],
        'first_name' => ['block' => self::BLOCK_IDENTITY, 'label' => 'Prénom'],
        'last_name' => ['block' => self::BLOCK_IDENTITY, 'label' => 'Nom'],
        'portrait' => ['block' => self::BLOCK_IDENTITY, 'label' => 'Photo ou portrait'],
        'email' => ['block' => self::BLOCK_CONTACT, 'label' => 'Adresse e-mail'],
        'phone' => ['block' => self::BLOCK_CONTACT, 'label' => 'Téléphone'],
        'athena_identifier' => ['block' => self::BLOCK_IDENTIFIERS, 'label' => 'Identifiant Athena'],
        'tenant_member_number' => ['block' => self::BLOCK_IDENTIFIERS, 'label' => 'Numéro de membre'],
        'service_number' => ['block' => self::BLOCK_IDENTIFIERS, 'label' => 'Matricule de service'],
        'matricule_internal' => ['block' => self::BLOCK_IDENTIFIERS, 'label' => 'Matricule du dossier'],
        'steam_id' => ['block' => self::BLOCK_IDENTIFIERS, 'label' => 'Compte Steam'],
        'grade' => ['block' => self::BLOCK_ASSIGNMENT, 'label' => 'Grade'],
        'unit' => ['block' => self::BLOCK_ASSIGNMENT, 'label' => 'Unité'],
        'enlistment_date' => ['block' => self::BLOCK_ASSIGNMENT, 'label' => 'Date d’engagement'],
    ];

    public const SEVERITY_HIGH = 'high';
    public const SEVERITY_MEDIUM = 'medium';
    public const SEVERITY_LOW = 'low';

    /** Rang pour le tri : la plus grave d'abord. */
    public const SEVERITY_RANK = [self::SEVERITY_HIGH => 0, self::SEVERITY_MEDIUM => 1, self::SEVERITY_LOW => 2];

    /** @var array<string, string> */
    public const SEVERITY_LABELS = [
        self::SEVERITY_HIGH => 'Haute',
        self::SEVERITY_MEDIUM => 'Modérée',
        self::SEVERITY_LOW => 'Basse',
    ];

    /** @var array<string, string> */
    public const GROUPS = [
        'duplicates' => 'Doublons',
        'identity' => 'Identité',
        'assignment' => 'Grades et affectations',
        'dates' => 'Dates impossibles',
        'accounts' => 'Comptes',
    ];

    /**
     * Vérifications d'anomalies.
     *
     * @var array<string, array{group: string, severity: string, label: string, help: string}>
     */
    public const CHECKS = [
        'duplicate_steam' => ['group' => 'duplicates', 'severity' => self::SEVERITY_HIGH, 'label' => 'Compte Steam en double', 'help' => 'Le même compte Steam est inscrit sur plusieurs membres.'],
        'duplicate_member_number' => ['group' => 'duplicates', 'severity' => self::SEVERITY_HIGH, 'label' => 'Numéro de membre en double', 'help' => 'Le même numéro de membre est porté par plusieurs membres.'],
        'duplicate_callsign' => ['group' => 'duplicates', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Indicatif en double', 'help' => 'Le même indicatif est porté par plusieurs membres.'],
        'duplicate_identity' => ['group' => 'duplicates', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Même personne inscrite deux fois', 'help' => 'Prénom, nom et date de naissance identiques sur plusieurs comptes.'],
        'duplicate_display_name' => ['group' => 'duplicates', 'severity' => self::SEVERITY_LOW, 'label' => 'Nom affiché en double', 'help' => 'Le même nom affiché est porté par plusieurs membres.'],
        'callsign_is_real_name' => ['group' => 'identity', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Indicatif identique au prénom ou au nom', 'help' => 'L’indicatif reprend l’état civil : il ne protège plus l’identité.'],
        'display_name_is_email' => ['group' => 'identity', 'severity' => self::SEVERITY_LOW, 'label' => 'Nom affiché qui est une adresse e-mail', 'help' => 'Le nom affiché ressemble à une adresse e-mail.'],
        'email_invalid' => ['group' => 'identity', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Adresse e-mail invalide', 'help' => 'L’adresse e-mail du compte n’a pas un format valide.'],
        'grade_unknown' => ['group' => 'assignment', 'severity' => self::SEVERITY_HIGH, 'label' => 'Grade inconnu', 'help' => 'Le grade du membre n’existe plus ou appartient à une autre communauté.'],
        'grade_display_mismatch' => ['group' => 'assignment', 'severity' => self::SEVERITY_LOW, 'label' => 'Grade affiché différent du grade', 'help' => 'Le grade affiché sur la fiche ne correspond pas au grade enregistré.'],
        'unit_foreign' => ['group' => 'assignment', 'severity' => self::SEVERITY_HIGH, 'label' => 'Unité principale inconnue', 'help' => 'L’unité principale n’existe plus ou appartient à une autre communauté.'],
        'unit_missing' => ['group' => 'assignment', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Membre sans unité', 'help' => 'Aucune unité n’est renseignée sur la fiche du membre.'],
        'qualification_dates' => ['group' => 'assignment', 'severity' => self::SEVERITY_HIGH, 'label' => 'Qualification aux dates incohérentes', 'help' => 'Une qualification expire avant d’avoir été obtenue.'],
        'date_enlistment_future' => ['group' => 'dates', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Engagement dans le futur', 'help' => 'La date d’engagement n’est pas encore arrivée.'],
        'date_enlistment_old' => ['group' => 'dates', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Date d’engagement invraisemblable', 'help' => 'La date d’engagement est antérieure à 1980.'],
        'date_enlistment_before_birth' => ['group' => 'dates', 'severity' => self::SEVERITY_HIGH, 'label' => 'Engagement avant la naissance', 'help' => 'La date d’engagement précède la date de naissance.'],
        'date_birth_implausible' => ['group' => 'dates', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Date de naissance invraisemblable', 'help' => 'La date de naissance est dans le futur ou donne un âge hors de 10 à 100 ans.'],
        'qualification_future' => ['group' => 'dates', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Qualification obtenue dans le futur', 'help' => 'La date d’obtention d’une qualification n’est pas encore arrivée.'],
        'account_without_profile' => ['group' => 'accounts', 'severity' => self::SEVERITY_MEDIUM, 'label' => 'Compte sans fiche', 'help' => 'Le compte est actif mais aucune fiche de personnel n’existe.'],
        'never_connected' => ['group' => 'accounts', 'severity' => self::SEVERITY_LOW, 'label' => 'Compte jamais utilisé', 'help' => 'Le compte actif ne s’est jamais connecté depuis plus de 30 jours.'],
    ];
}
