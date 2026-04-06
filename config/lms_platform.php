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
    'version' => '1.0.0',
    'label' => 'Studio & parcours formation',
    'changelog' => [
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
