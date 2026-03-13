<?php

declare(strict_types=1);

/**
 * Types d’unités / équipes / groupes pour l’ORBAT MILSIM.
 * type => [ label, order ] — order pour l’affichage et la hiérarchie.
 */
return [
    'types' => [
        'organization' => ['label' => 'Organisation', 'order' => 0],
        'branch'       => ['label' => 'Branche', 'order' => 10],
        'group'        => ['label' => 'Groupe', 'order' => 20],
        'team'         => ['label' => 'Équipe', 'order' => 30],
        'section'      => ['label' => 'Section', 'order' => 40],
        'squad'        => ['label' => 'Escouade', 'order' => 50],
        'squadron'     => ['label' => 'Escadron', 'order' => 45],
        'unit'         => ['label' => 'Unité', 'order' => 60],
        'role'         => ['label' => 'Poste / Rôle', 'order' => 70],
    ],

    'default_type' => 'unit',
];
