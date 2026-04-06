<?php

declare(strict_types=1);

/**
 * Icônes (classes Tailwind / heroicons) et teintes badge par catégorie et par couche sémantique.
 * Clés catégorie = slug ASCII dérivé du libellé FR (voir MilitaryRoleCatalogSyncService::categoryKeyFromLabel).
 */
return [
    'category_icons' => [
        'etat-major' => 'heroicon-o-building-library',
        'infanterie' => 'heroicon-o-shield-check',
        'appuis-feux' => 'heroicon-o-fire',
        'genie' => 'heroicon-o-wrench-screwdriver',
        'logistique' => 'heroicon-o-truck',
        'sante' => 'heroicon-o-heart',
        'instruction' => 'heroicon-o-academic-cap',
        'administration' => 'heroicon-o-clipboard-document-list',
        'statut' => 'heroicon-o-tag',
    ],
    /** Couleurs badge (couche sémantique) — compatibles mode clair. */
    'tier_badge_classes' => [
        'authority' => 'bg-rose-100 text-rose-900 ring-rose-200',
        'function' => 'bg-sky-100 text-sky-900 ring-sky-200',
        'liaison' => 'bg-amber-100 text-amber-950 ring-amber-200',
        'support' => 'bg-teal-100 text-teal-900 ring-teal-200',
        'specialty' => 'bg-violet-100 text-violet-900 ring-violet-200',
        'status' => 'bg-slate-200 text-slate-800 ring-slate-300',
    ],
];
