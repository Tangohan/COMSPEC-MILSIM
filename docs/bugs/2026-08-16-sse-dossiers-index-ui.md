# UI index dossiers d’affaire SSE

## Contexte

Page `/atak/sse/dossiers` — registre dense (table large, boutons doublonnés, métriques peu liées).

## Symptôme

Lecture fatigante : CTA répétés, tableau serré, espace mort avant les raccourcis du bas.

## Cause

Composition historique « panneau + table » sans hiérarchie dédiée.

## Correctif

- Hero avec actions principales (ouvrir / importer) une seule fois
- Métriques lisibles (dont état du verrou)
- Liste en cartes (réf., titre, diffusion, qui peut ouvrir, contenu, date FR)
- Filtres latéraux allégés ; barre de filtres principale
- Raccourcis d’exploitation en grille soignée

## Fichiers touchés

- `views/atak/sse/cases.php`
- `public/assets/css/sse_portal.css`

## Vérification

Ouvrir `/atak/sse/dossiers` en desktop et mobile ; filtrer / ouvrir une affaire.

## Statut

corrigé
