# Overwatch Beta 3D — clic contact ouvrait le mauvais panneau

## Contexte

Vue Relief 3D Overwatch Beta. Les pastilles de contact sont dessinées au-dessus du sol.

## Symptôme

Un clic sur un contact en 3D n’ouvrait pas la fiche du contact. Le tiroir latéral recevait l’objet unité à la place d’un titre et d’un contenu.

## Cause

Le clic 3D appelait `openDrawer(unité)` alors que `openDrawer` attend un libellé, un titre et du HTML. La sélection 2D passe par `selectUnit`.

## Correctif

Le clic 3D sur un contact appelle `selectUnit`. Un clic sur un bâtiment est converti en point théâtre puis passé au même menu contextuel que la carte à plat (`inspectWorld` / `openContextAt` / `hitDeletable`).

## Fichiers touchés

- `public/assets/js/overwatch-gl/OverwatchGlLayers.js`
- `public/assets/js/atak-overwatch-beta.js`

## Vérification

Tests d’actifs : présence de `selectUnit`, `inspectWorld`, `handleWorldClick`. Vérification visuelle : Relief 3D, clic contact → fiche ; clic bâtiment déjà marqué → menu du point.

## Statut

corrigé
