# Croisements — formulaire en modale

**Date :** 2026-08-16  
**Statut :** corrigé / livré

## Contexte

La page `/atak/sse/croisements` affichait le formulaire d’ajout d’entrée
surveillée en plein écran au-dessus des tableaux, forçant un défilement
avant de voir les correspondances.

## Symptôme

Vue encombrée : formulaire long, tableaux repoussés sous le fold.

## Correctif

- Formulaire d’ajout déplacé dans une modale (`sse-modal`, même pattern dossiers).
- Retrait confirmé via modale (plus de `confirm()` navigateur).
- Tableaux en premier (correspondances, puis liste active) + CTA « Ajouter ».

## Fichiers touchés

- `views/atak/sse/cross.php`
- `public/assets/js/sse-cross-modals.js`
- `public/assets/css/sse_portal.css`

## Vérification

1. Ouvrir Croisements → tableaux visibles immédiatement.
2. « Ajouter une entrée » → modale → enregistrer.
3. « Retirer » → modale de confirmation → retrait effectif.

## Statut

Livré — à déployer.
