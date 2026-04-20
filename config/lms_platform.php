<?php

declare(strict_types=1);

/**
 * Version fonctionnelle du Studio LMS / moteur de parcours (hors déploiement serveur).
 * Incrémenter la version lors d’un changement visible pour les auteurs ou le rendu apprenant.
 *
 * @return array{
 *   version: string,
 *   label: string,
 *   changelog: list<array{version: string, date: string, title: string, items: list<string>}>
 * }
 */
return [
    'version' => '1.4.0',
    'label' => 'Studio & parcours formation',
    'changelog' => [
        [
            'version' => '1.4.0',
            'date' => '2026-04-20',
            'title' => 'Décision d’inscription, pilotage admin et publication guidée',
            'items' => [
                'Catalogue apprenant : ajout d’une barre de filtres utilitaires (niveau, durée, modalité, disponibilité) avec réinitialisation rapide.',
                'Cartes du catalogue : métadonnées décisionnelles visibles (niveau, charge hebdomadaire estimée, format) et badge de progression « non commencé / en cours / terminé ».',
                'Pilotage admin : nouvelle section « Actions du jour » en tête, KPIs transformés en raccourcis actionnables vers les vues filtrées.',
                'Pilotage admin : ajout d’un bloc « santé opérationnelle » pour suivre complétion, parcours inactifs et backlog de validation.',
                'Studio : ajout d’un assistant de mise en ligne (4 étapes), checklist de publication avec score prêt-à-publier et aides contextuelles en accordéons.',
                'UI : harmonisation de libellés FR (ex. « Tous les modules », « Détails »), amélioration de la lisibilité (tailles minimales de texte sur informations clés).',
            ],
        ],
        [
            'version' => '1.3.0',
            'date' => '2026-04-19',
            'title' => 'Loader visuel + timeline enrichie + personnalisation Studio',
            'items' => [
                'Présentation Studio : nouveau bloc « Loader d’ouverture (slide) » pour définir une image, un titre et un texte affichés pendant la préparation du parcours.',
                'Côté apprenant, l’écran de chargement du parcours peut afficher une carte visuelle de briefing (image + contenu) avant l’introduction plein écran.',
                'Frise du parcours (structure) enrichie avec une lecture temporelle réaliste : repère de démarrage cumulé (T+XX min) et durée estimée par module.',
                'Mise à jour de la version fonctionnelle LMS pour tracer cette évolution majeure dans le journal Studio.',
            ],
        ],
        [
            'version' => '1.2.0',
            'date' => '2026-04-06',
            'title' => 'Catalogue : parcours communauté et parcours plateforme',
            'items' => [
                'Le catalogue apprenant peut afficher à la fois les parcours publiés par votre communauté et des parcours proposés sur l’ensemble du site, selon la configuration.',
                'Filtres par origine : tous les parcours, ceux de la communauté uniquement, ou ceux proposés sur toute la plateforme ; les filtres par thème et la recherche restent combinables.',
                'Les parcours « toute la plateforme » sont repérés par une pastille dans le catalogue.',
                'Dans le Studio, les administrateurs de la plateforme choisissent la portée du parcours (communauté ou toute la plateforme) et les adresses courtes des parcours globaux sont uniques à l’échelle du site.',
                'L’export d’une formation inclut désormais l’information de portée dans le fichier structuré (réimport : parcours créé en communauté par défaut).',
            ],
        ],
        [
            'version' => '1.1.1',
            'date' => '2026-04-06',
            'title' => 'Liens admin formation & segment d’adresse /public',
            'items' => [
                'Lorsque le site est servi depuis le dossier public, les adresses qui contiennent encore « /public/ » (ex. export depuis l’ancien chemin admin) ne renvoient plus une page introuvable : le chemin est reconnu comme pour le reste de l’application.',
                'La détection des pages back-office utilise le même calcul d’adresse, pour un menu latéral cohérent dans ce cas.',
            ],
        ],
        [
            'version' => '1.1.0',
            'date' => '2026-04-06',
            'title' => 'Quiz côté apprenant & parcours d’accueil portail',
            'items' => [
                'Correction de la soumission des questionnaires à choix unique et vrai / faux (notation cohérente avec la base).',
                'Pour l’apprenant, les propositions affichées sont mélangées à chaque chargement (l’ordre ne suit plus celui saisi dans le Studio).',
                'Écran questionnaire : temps restant, fin de session indicative, contrôles avant envoi et messages d’erreur affichés dans la page (plus de fenêtre système).',
                'Parcours d’accueil « portail » (graine par défaut) : bilan interrogé à mi-parcours, deux étapes par module de fond (parcours visuel puis fiche « À retenir »). Les parcours déjà présents avec l’ancienne structure (cinq modules) sont étendus lors du passage des migrations.',
                'Côté Studio : pas de changement d’écran obligatoire ; ouvrez une formation et enregistrez pour mettre à jour la trace de version affichée sur la fiche.',
            ],
        ],
        [
            'version' => '1.0.0',
            'date' => '2026-04-06',
            'title' => 'Suivi de version du Studio',
            'items' => [
                'Chaque formation mémorise la version du Studio sous laquelle elle a été créée, et la version lors du dernier enregistrement dans le Studio.',
                'Journal des évolutions accessible depuis le menu du Studio (parcours, éditeur visuel, types de leçons, rendu apprenant).',
                'Indicateur dans le tableau des formations lorsque le contenu a été initié avant la version actuelle de l’outil.',
            ],
        ],
    ],
];
