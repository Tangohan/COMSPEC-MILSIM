# Suivi roleplay — menu Étape coupé par le tableau

## Contexte

Bureau de suivi roleplay (`/back-office/roleplay-followup`). Chaque ligne a un menu **Étape** (et **Tuteur**) qui ouvre un formulaire avec une liste.

## Symptôme

Sur les dernières lignes, le menu s’affiche sous le tableau : seule la mention « Nouvelle étape » reste visible, le reste est masqué. Impossible de choisir l’étape.

## Cause

Le tableau défile dans un cadre (`overflow: auto`). Le menu était collé sous le bouton, donc coupé par le bas du cadre. La liste du formulaire était aussi limitée par ce cadre.

## Correctif

Le menu s’affiche au-dessus des deux dernières lignes. À l’ouverture, il est détaché du tableau et collé à l’écran, au-dessus ou en dessous selon la place, pour rester entièrement lisible.

## Fichiers touchés

- `views/admin/organization/roleplay_followup.php`
- `tests/Unit/RoleplayFollowupSheetsAssetTest.php`

## Vérification

Ouvrir le bureau de suivi, cliquer **Étape** sur la dernière ligne : tout le formulaire (liste + Enregistrer) est visible. Même contrôle sur **Tuteur**.

## Statut

Corrigé.
