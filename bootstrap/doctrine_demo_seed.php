<?php

declare(strict_types=1);

/**
 * Catalogue historique des doctrines de démonstration.
 *
 * Ne crée plus aucun document. Sert uniquement au nettoyage ciblé
 * ({@see bootstrap/doctrine_demo_cleanup.php}) : paires référence + titre
 * (et slug dérivé) issues de l’ancien seed, hors SIC/ATAK/2026-001.
 *
 * @return array{
 *     remove: list<array{reference: string, title: string, slug: string}>,
 *     keep: list<array{reference: string, title: string, slug: string}>
 * }
 */
return [
    'remove' => [
        ['reference' => 'EM/DOCTR/2026-001', 'title' => 'Doctrine générale d’emploi de l’unité', 'slug' => 'em-doctr-2026-001'],
        ['reference' => 'OPS/SEC/2026-014', 'title' => 'Mesures de sûreté applicables aux opérations extérieures', 'slug' => 'ops-sec-2026-014'],
        ['reference' => 'OPS/SIC/2026-018', 'title' => 'Emploi des moyens de transmission et procédures radio', 'slug' => 'ops-sic-2026-018'],
        ['reference' => 'DRH/PERS/2026-004', 'title' => 'Disponibilité, permissions et obligations du personnel', 'slug' => 'drh-pers-2026-004'],
        ['reference' => 'FORM/INST/2026-021', 'title' => 'Instruction relative au maintien des compétences individuelles', 'slug' => 'form-inst-2026-021'],
        ['reference' => 'LOG/MAT/2026-009', 'title' => 'Perception, emploi et restitution des matériels sensibles', 'slug' => 'log-mat-2026-009'],
        ['reference' => 'MED/SAN/2026-006', 'title' => 'Conduite à tenir en cas de blessé au combat', 'slug' => 'med-san-2026-006'],
        ['reference' => 'REN/PROC/2026-011', 'title' => 'Recueil, qualification et transmission du renseignement terrain', 'slug' => 'ren-proc-2026-011'],
    ],
    'keep' => [
        ['reference' => 'SIC/ATAK/2026-001', 'title' => 'Doctrine d’emploi d’ATAK / Overwatch Athena', 'slug' => 'sic-atak-2026-001'],
    ],
];
