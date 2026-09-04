# Accueil : « OPÉRATEUR CONNECTÉ » chevauche le nom

- Date : 2026-09-04
- Statut : corrigé (pack 1.8.20)

## Contexte

Écran d’accueil du téléphone, bandeau OPÉRATEUR CONNECTÉ, nom NewPI, métadonnée NewPI - admin, onglets GRILLE / LIAISON / TÂCHES.

## Symptôme

Le nom commence sous la fin du titre. La métadonnée flotte en exposant à droite du nom.

## Cause

Dès que l’accueil est actif, le bloc passait en rangée (`display:flex` sans colonne). Titre, nom et groupe se mettaient côte à côte sur un écran étroit.

## Correctif

Empilement vertical : titre, puis nom, puis groupe. Les onglets restent en dessous, sans superposition.

## Fichiers touchés

- `web/phone.html` (bloc accueil)

## Vérification

CSS : `flex-direction:column` sur le bloc accueil actif.

## Statut

Corrigé dans les sources 1.8.20 — reconstruire le pack et relancer Arma complètement.
