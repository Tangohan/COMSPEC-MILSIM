# Arsenal Athena — freeze à l’ouverture / sync trop tôt

**Date :** 2026-09-28  
**Statut :** corrigé

## Contexte

Panneau tenues communauté dans ACE Arsenal. Sur grosses organisations, l’ouverture de l’arsenal gelait.

## Symptôme

Freeze net à l’ouverture de l’arsenal (ou dès le clic Athena), parfois plusieurs secondes.

## Cause

1. Au rafraîchissement : `ListWardrobes` paginé + **préchargement GetWardrobe** (jusqu’à 30 tenues) pour les icônes.
2. Calcul d’icônes sur toutes les tenues locales.
3. Sync déclenchée dès l’ouverture du panneau sans loader.

## Correctif

- Bouton renommé « ATHENA : Collection de votre organisation ».
- Aucune sync à l’ouverture de l’arsenal : sync seulement au clic, avec loader.
- Suppression du préchargement GetWardrobe pour icônes.
- Cache liste wardrobes 90 s ; invalidé après push/pull/delete.
- Listes locales sans icônes massives au premier paint.

## Fichiers touchés

- `fn_arsenalOverlayShow.sqf`, `fn_arsenalOverlayBeginLoad.sqf`, `fn_arsenalOverlayRefresh.sqf`, `config.cpp`

## Vérification

Ouvrir ACE Arsenal → fluide. Cliquer le bouton → loader puis listes. Importer une tenue → OK.

## Statut

Corrigé.
