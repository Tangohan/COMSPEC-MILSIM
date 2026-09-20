# Overwatch Beta : pictos du jeu invisibles

## Contexte

Poste Overwatch Beta, photo aérienne. Des marqueurs sont posés en jeu (points, cases, alertes, infanterie).

## Symptôme

Sur la carte du poste, seuls quelques noms restaient lisibles (lieu, navire). Les pictos du jeu — points, cases, alertes — n’apparaissaient plus. Un fichier d’icône se chargeait bien, mais l’image était transparente.

## Cause

Les icônes militaires du jeu sont des silhouettes blanches sur fond transparent. Le poste les affichait telles quelles. Comme le fichier répondait correctement, le dessin de repli (point ou case coloré) ne s’affichait jamais. Un marqueur sans nom devenait complètement invisible.

## Correctif

Pour ces pictos courants, le poste dessine un glyphe coloré (comme sur la carte en jeu). Les drapeaux et les lieux gardent leur image. Un nom sans picto reste dans un cadre.

## Fichiers touchés

- `public/assets/js/arma-map-markers.js`
- `public/assets/js/atak-overwatch-beta.js`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Les marqueurs posés en jeu doivent montrer un picto coloré. Un nom sans picto (navire, zone en mer) reste dans un cadre.

## Statut

corrigé
