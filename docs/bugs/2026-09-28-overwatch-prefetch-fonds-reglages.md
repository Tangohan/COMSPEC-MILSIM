# Overwatch Beta — télécharger les fonds depuis les réglages

## Contexte

28 septembre 2026. Les zooms martèlent le poste pour chaque extrait de carte. Un cache passif existe déjà ; l’opérateur veut forcer le téléchargement complet.

## Symptôme

Sous charge VPS, le fond disparaît au zoom. Pas d’action claire pour préparer les fonds à l’avance.

## Correctif

Dans **Réglages → Fond de carte** : bouton **Télécharger les fonds du théâtre** (plan + calques Atlas), progression, arrêt possible. Stockage via `OverwatchTileCache` (navigateur).

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/js/overwatch-gl/OverwatchTileCache.js`
- `public/assets/js/atak-overwatch-beta.js`
- `tests/Unit/OverwatchGlAssetTest.php`

## Vérification

Réglages → Télécharger les fonds → barre de progression → zooms sans rafale réseau (onglet Réseau). Arrêt possible en cours.

## Statut

corrigé (à déployer)
