<?php

declare(strict_types=1);

/**
 * Icônes (classes Tailwind / heroicons) et teintes badge par catégorie et par couche sémantique.
 * Clés catégorie = slug ASCII dérivé du libellé FR (voir MilitaryRoleCatalogSyncService::categoryKeyFromLabel).
 */
return [
    'category_icons' => [
        'administrations-et-services' => 'heroicon-o-building-library',
        'aerocombat' => 'heroicon-o-paper-airplane',
        'artillerie' => 'heroicon-o-fire',
        'combat-blind' => 'heroicon-o-shield-exclamation',
        'combat-blinde' => 'heroicon-o-shield-exclamation',
        'enseignement-recherche-et-musique' => 'heroicon-o-academic-cap',
        'forces-speciales' => 'heroicon-o-bolt',
        'genie-de-combat-btp-et-nrbc' => 'heroicon-o-wrench-screwdriver',
        'infanterie' => 'heroicon-o-shield-check',
        'cyber-informatique-et-telecoms' => 'heroicon-o-cpu-chip',
        'logistique-et-transports' => 'heroicon-o-truck',
        'maintenance' => 'heroicon-o-cog-6-tooth',
        'renseignement' => 'heroicon-o-eye',
        'restauration' => 'heroicon-o-shopping-bag',
        'sante' => 'heroicon-o-heart',
        'securite-et-prevention' => 'heroicon-o-lock-closed',
        'sport' => 'heroicon-o-trophy',
        'statut' => 'heroicon-o-tag',
        // Anciennes racines (rétrocompatibilité si catégories orphelines encore référencées)
        'etat-major' => 'heroicon-o-building-library',
        'appuis-feux' => 'heroicon-o-fire',
        'genie' => 'heroicon-o-wrench-screwdriver',
        'logistique' => 'heroicon-o-truck',
        'instruction' => 'heroicon-o-academic-cap',
        'administration' => 'heroicon-o-clipboard-document-list',
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
