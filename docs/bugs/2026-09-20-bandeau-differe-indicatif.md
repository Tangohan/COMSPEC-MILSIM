# Bandeau « Différé » sous l’indicatif

## Contexte

Poste Overwatch Beta, carte 2D immersif. Contact encore affiché (ex. TA1) dont la position n’est plus à l’instant.

## Symptôme

Un bandeau rouge « Différé · il y a 1 min » s’affichait sous l’indicatif, en plus du cadre du nom. La carte devenait illisible.

## Cause

Le pastille d’âge était collée dans le libellé de l’unité, comme un second cadre de texte.

## Correctif

Plus de texte d’âge sur la carte. Le cadre de l’indicatif (et le losange) passe en pointillés : ambre si la liaison est en retard, rouge si la position a plus d’une minute. L’ancienneté reste au survol et dans la fiche.

## Fichiers touchés

- `public/assets/js/atak-overwatch-beta.js`
- `public/assets/css/atak-overwatch-beta.css`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Un contact en retard : seul TA1 (ou l’indicatif) reste, cadre en pointillés colorés, sans bandeau sous le nom. Survol : le délai apparaît. Fiche : dernière position inchangée.

## Statut

Corrigé
