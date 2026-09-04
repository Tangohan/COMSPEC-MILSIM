# Réglages : section Terminal coupée en bas

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.20)

## Contexte

Écran Paramètres : précision de grille, Mini ATAK, puis Terminal.

## Symptôme

La rubrique Terminal est presque invisible, coincée sous la barre du bas. Impossible de lire Affichage / À propos sans forcer le défilement.

## Cause

La page des réglages calculait une hauteur trop courte et manquait de marge basse sous la barre matérielle.

## Correctif

La page défile jusqu’en bas, avec une marge sous le dernier bouton. Les curseurs Mini ATAK continuent d’écrire le profil.

## Fichiers touchés

- `web/phone.html`

## Vérification

`.settings-page` a une marge basse et un défilement vertical.

## Statut

Corrigé dans les sources 1.8.20 — reconstruire le pack et relancer Arma complètement.
