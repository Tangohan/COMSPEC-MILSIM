# Alerte « Probablement parti » sur tous les marqueurs

## Contexte

Overwatch affiche une étiquette d’âge sur les marqueurs trop anciens.

## Symptôme

Tous les repères (amis, points, maisons) affichaient « Probablement parti », pas seulement les contacts hostiles.

## Cause

L’étiquette d’âge était posée dès qu’un marqueur dépassait le délai, sans regarder le camp.

## Correctif

L’étiquette n’apparaît que pour un marqueur hostile (symbole `o_`, couleur adverse, appartenance hostile). Les flèches de déplacement des autres marqueurs restent.

## Fichiers touchés

- `public/assets/js/atak-overwatch-ops.js`

## Vérification

Un marqueur ami ancien n’a plus l’étiquette. Un marqueur d’infanterie hostile trop ancien la conserve.

## Statut

corrigé
