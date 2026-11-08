<?php

declare(strict_types=1);

/**
 * Étapes de « Mise à niveau » (/back-office/mise-a-niveau) à faire après une version.
 *
 * Pour ajouter une étape : une ligne ici, rien d'autre. L'étape de migration configuration_release_steps l'enregistre,
 * le catalogue l'affiche, et ConfigurationReleaseStepsTest vérifie que la page existe. N'y mettre que ce qui est sur main.
 *
 * - code : unique et stable, jamais réutilisé
 * - version : version où la nouveauté est arrivée (16 caractères au plus)
 * - path : page où agir, sans / au début (une vraie page, pas une ancienne adresse redirigée)
 * - applies : all (toutes les communautés), atak (ATAK actif) ou sse (portail SSE actif)
 * - level : informative ou recommended
 * - minutes : temps estimé
 *
 * Le responsable termine l'étape avec le bouton « C'est fait », ou la remet à plus tard.
 *
 * @return list<array{code: string, version: string, title: string, description: string, path: string, applies: string, level: string, minutes: int}>
 */
return [
    [
        'code' => 'ATAK_COMMUNITY_BRIEF_V1',
        'version' => '4.0.0',
        'title' => 'Consignes et kit obligatoire',
        'description' => 'L’app Communauté du téléphone affiche vos consignes, ordres et conseils, et compare ce que porte le joueur à votre kit obligatoire. Rédigez-les pour vos joueurs.',
        'path' => 'back-office/ressources/atak-consignes',
        'applies' => 'atak',
        'level' => 'recommended',
        'minutes' => 10,
    ],
    [
        'code' => 'ATAK_REALISM_REVIEW_4_V1',
        'version' => '4.0.0',
        'title' => 'Réglages de réalisme',
        'description' => 'Relisez les réglages de réalisme de la communauté avant la prochaine opération avec l’ATAK 4.0.0.',
        'path' => 'back-office/atak/realisme?onglet=reglages',
        'applies' => 'atak',
        'level' => 'recommended',
        'minutes' => 5,
    ],
    [
        'code' => 'AAR_CONCLUSION_ANNEXES_V1',
        'version' => '4.0.0',
        'title' => 'Comptes rendus : conclusion, annexes et synthèses',
        'description' => 'La conclusion du commandement est réservée au droit dédié. Les comptes rendus acceptent des annexes, et plusieurs peuvent être réunis en une synthèse. Vérifiez qui peut conclure.',
        'path' => 'back-office/atak/comptes-rendus',
        'applies' => 'atak',
        'level' => 'recommended',
        'minutes' => 5,
    ],
    [
        'code' => 'NINE_LINE_AIR_CREW_V1',
        'version' => '4.0.0',
        'title' => '9-line : pilotes et JTAC',
        'description' => 'La page 9-line reconnaît les pilotes et les JTAC d’après leurs qualifications et leurs fonctions. Vérifiez qu’ils les ont bien sur leur dossier.',
        'path' => 'back-office/referentiels/qualifications',
        'applies' => 'atak',
        'level' => 'informative',
        'minutes' => 5,
    ],
    [
        'code' => 'SSE_EDITOR_LIBRARY_V1',
        'version' => '4.0.0',
        'title' => 'Bibliothèque de l’éditeur SSE',
        'description' => 'Les renseignements, identités, papiers, dossiers et kits préparés dans l’éditeur en jeu sont visibles sur Athena. Prenez connaissance de cette bibliothèque.',
        'path' => 'atak/sse/editeur',
        'applies' => 'sse',
        'level' => 'informative',
        'minutes' => 3,
    ],
    [
        'code' => 'SSE_IDENTITY_AUTO_SEND_V1',
        'version' => '4.0.0',
        'title' => 'COMSPEC Identity : envoi automatique des contrôles',
        'description' => 'Choisissez si les contrôles d’identité faits en jeu sont envoyés automatiquement à Athena pour toute la communauté.',
        'path' => 'atak/sse/papiers/controles',
        'applies' => 'sse',
        'level' => 'recommended',
        'minutes' => 2,
    ],
    [
        'code' => 'MEMBER_NUMBER_TEMPLATES_V1',
        'version' => '4.0.0',
        'title' => 'Matricules : modèles et modèle par unité',
        'description' => 'Choisissez un modèle de matricule prêt à l’emploi et, si besoin, un modèle différent par unité.',
        'path' => 'back-office/organisation/matricules',
        'applies' => 'all',
        'level' => 'recommended',
        'minutes' => 5,
    ],
    [
        'code' => 'DATA_QUALITY_STORAGE_V1',
        'version' => '4.0.0',
        'title' => 'Qualité des données et place occupée',
        'description' => 'La page montre la place occupée par la communauté, et permet de télécharger ou de supprimer les photos du jeu et les données de renseignement.',
        'path' => 'back-office/organisation/qualite-donnees',
        'applies' => 'all',
        'level' => 'informative',
        'minutes' => 5,
    ],
    [
        'code' => 'ORBAT_TYPES_HISTORY_V1',
        'version' => '4.0.0',
        'title' => 'ORBAT : types d’entité personnalisés',
        'description' => 'Créez vos propres types d’entité pour les unités de l’ORBAT. L’ORBAT garde aussi l’historique de ses modifications.',
        'path' => 'back-office/organisation/types-entite',
        'applies' => 'all',
        'level' => 'informative',
        'minutes' => 5,
    ],
    [
        'code' => 'COMMAND_CHAIN_REVIEW_V1',
        'version' => '4.0.0',
        'title' => 'Chaîne de commandement',
        'description' => 'La chaîne signale les postes à pourvoir et propose des affectations, directement depuis la page. Traitez les alertes de votre communauté.',
        'path' => 'back-office/ressources/effectifs/chaine',
        'applies' => 'all',
        'level' => 'recommended',
        'minutes' => 10,
    ],
    [
        'code' => 'ROLEPLAY_PHASE_RULES_V1',
        'version' => '4.0.0',
        'title' => 'Parcours RH',
        'description' => 'Le parcours RH a été refait. Relisez les règles de passage d’une phase à l’autre.',
        'path' => 'back-office/roleplay-followup?onglet=phases',
        'applies' => 'all',
        'level' => 'recommended',
        'minutes' => 5,
    ],
    [
        'code' => 'ROLEPLAY_FOLLOWUP_V1',
        'version' => '4.0.0',
        'title' => 'Suivi roleplay et bilans individuels',
        'description' => 'Le suivi roleplay a été refait. Vous pouvez désormais désactiver les bilans individuels pour la communauté.',
        'path' => 'back-office/roleplay-followup?onglet=immersion',
        'applies' => 'all',
        'level' => 'informative',
        'minutes' => 3,
    ],
];
