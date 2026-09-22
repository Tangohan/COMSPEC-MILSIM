# Overwatch Beta — double fiche (dossier + tiroir)

**Statut :** corrigé (sources)

## Contexte

Poste Overwatch Beta. Clic sur une unité (infanterie YA1, aéronef, etc.).

## Symptôme

Deux fiches en même temps : le dossier flottant (`#atak-unit-dossier`) par-dessus le tiroir (`#ow-drawer`). Illisible ; données Source Arma / Analyse Athena uniquement dans le dossier.

## Cause

`selectUnit` et `openAirAssetSheet` appelaient `ATAKUnitDossier.open` **en plus** du remplissage du tiroir (ou à la place pour l’aérien, ce qui masquait le tiroir voulu).

## Correctif

- Plus d’ouverture du dossier flottant sur Overwatch Beta : uniquement le tiroir.
- Fermeture forcée du dossier à chaque sélection ; CSS `display:none` de secours.
- Tiroir enrichi : État, blocs Source Arma et Analyse Athena.
- Aéronef : tiroir fiche aérienne uniquement.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`

## Vérification

1. Ctrl+F5 Overwatch Beta.
2. Clic YA1 (ou autre) : un seul panneau (tiroir), avec État / Source Arma / Analyse Athena.
3. Clic aéronef : une seule fiche dans le tiroir, pas de dossier flottant.

## Statut

corrigé (sources) — déploiement portail requis
