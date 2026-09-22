# Overwatch Beta — double superposition sur les aéronefs

**Statut :** corrigé (sources)

## Contexte

Poste Overwatch Beta (`/ATAK-OVERWATCH-Beta`). Clic sur un aéronef (ex. Dustoff / HH-60M) ouvre la fiche.

## Symptôme

La fiche « Dustoff » affiche deux couches de texte l’une sur l’autre : libellés Situation (Véhicule, Chef, ETA, Autonomie…) mélangés à l’onglet Personnel (liste À bord). Illisible.

## Cause

`openAirAssetSheet` (et le clic sur un contact aérien via `selectUnit`) ouvrait **en même temps** :

1. le **dossier flottant** (`ATAKUnitDossier`, onglets Situation / Personnel…) ;
2. le **tiroir droit** avec la même fiche (`airAssetDetailHtml`).

Le dossier était semi-transparent (`rgba(…, 0.94)`), donc le contenu du tiroir transparaissait. En plus, l’onglet Situation du dossier répétait déjà la liste d’équipage (réservée à Personnel).

## Correctif

- Aéronef : ouvrir uniquement le dossier ; masquer le tiroir.
- Fond du dossier opaque (`#0c1016`).
- Liste d’équipage uniquement dans l’onglet Personnel ; ETA / autonomie / carburant restent dans Situation.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/js/atak-unit-dossier.js`
- `public/assets/css/atak-overwatch-beta.css`
- `public/assets/css/atak-cop.css`

## Vérification

1. Recharger Overwatch Beta (Ctrl+F5).
2. Cliquer Dustoff (ou autre aéronef) : une seule fiche, onglets lisibles.
3. Onglet Personnel : liste À bord sans fantômes de Situation.
4. Onglet Situation : position / ETA / autonomie, sans liste d’équipage dupliquée.

## Statut

corrigé (sources) — déploiement portail requis
