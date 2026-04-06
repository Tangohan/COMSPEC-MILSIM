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
    'version' => '1.1.0',
    'label' => 'Studio & parcours formation',
    'changelog' => [
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
