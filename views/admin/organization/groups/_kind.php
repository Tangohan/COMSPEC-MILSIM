<?php
declare(strict_types=1);

/**
 * Libellés et adresses des écrans « Groupes » réutilisés par « Équipes » (même liste, fiche et formulaire).
 * Utilisation : `$unitUi = require __DIR__ . '/_kind.php';` avec `$unitKind` = 'group' (défaut) ou 'team'.
 *
 * @var string|null $unitKind
 * @return array<string, mixed>
 */

$kind = ($unitKind ?? 'group') === 'team' ? 'team' : 'group';

return $kind === 'team'
    ? [
        'kind' => 'team',
        'base' => 'back-office/teams',
        'one' => 'équipe',
        'One' => 'Équipe',
        'many' => 'équipes',
        'Many' => 'Équipes',
        'the' => 'l’équipe',
        'this' => 'cette équipe',
        'all' => 'Toutes les équipes',
        'none' => 'Aucune équipe ne correspond.',
        'create' => 'Créer une équipe',
        'createFirst' => 'Créer la première équipe',
        'emptyTitle' => 'Aucune équipe pour l’instant',
        'emptyText' => 'Une équipe réunit quelques membres pour une mission, un créneau ou une spécialité : binôme de tireurs, équipe médicale, équipage.',
        'lead' => 'Chef d’équipe',
        'namePh' => 'Équipe Alpha',
        'codePh' => 'ALPHA',
        'blurbPh' => 'Équipe de reconnaissance légère, engagée en tête des opérations de nuit.',
        'noBlurb' => 'Pas encore de présentation : écrivez deux phrases sur le rôle de l’équipe.',
        'publicToggle' => 'Afficher cette équipe sur la page publique',
        'hiddenHint' => 'Décochez pour une équipe interne.',
        'deleteConfirm' => 'Supprimer cette équipe ? Les membres restent dans la communauté, seule leur affectation à cette équipe disparaît.',
        'kpiTotal' => 'ÉQUIPES',
        'typeSelect' => false,
        'other' => ['label' => 'Groupes', 'href' => 'back-office/groups'],
    ]
    : [
        'kind' => 'group',
        'base' => 'back-office/groups',
        'one' => 'groupe',
        'One' => 'Groupe',
        'many' => 'groupes',
        'Many' => 'Groupes',
        'the' => 'le groupe',
        'this' => 'ce groupe',
        'all' => 'Tous les groupes',
        'none' => 'Aucun groupe ne correspond.',
        'create' => 'Créer un groupe',
        'createFirst' => 'Créer le premier groupe',
        'emptyTitle' => 'Aucun groupe pour l’instant',
        'emptyText' => 'Un groupe rassemble quelques membres : un groupe de combat, une cellule, une équipe d’appui.',
        'lead' => 'Chef de groupe',
        'namePh' => '1er Groupe de Combat',
        'codePh' => 'GC1',
        'blurbPh' => 'Groupe d’assaut de la compagnie, spécialisé dans le combat en zone urbaine.',
        'noBlurb' => 'Pas encore de présentation : écrivez deux phrases sur le rôle du groupe.',
        'publicToggle' => 'Afficher ce groupe sur la page publique',
        'hiddenHint' => 'Décochez pour une cellule interne.',
        'deleteConfirm' => 'Supprimer ce groupe ? Les membres restent dans la communauté, seule leur affectation à ce groupe disparaît.',
        'kpiTotal' => 'GROUPES',
        'typeSelect' => true,
        'other' => ['label' => 'Équipes', 'href' => 'back-office/teams'],
    ];
