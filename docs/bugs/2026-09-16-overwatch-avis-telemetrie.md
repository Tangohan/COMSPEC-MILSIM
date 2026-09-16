# Avis « Aucune télémétrie » non masquable

## Contexte

Sur Overwatch Beta, un encart central s’affiche tant qu’aucun contact autorisé n’est en liaison. Le théâtre (marqueurs, calques) reste visible derrière.

## Symptôme

L’avis recouvre le centre de la carte. Aucun moyen de le retirer : le poste ne peut pas tracer ni lire le théâtre sans ce bandeau collé.

## Cause

L’encart était recalculé à chaque mise à jour de la liste des contacts. Sans bouton de fermeture, il restait affiché dès que la liste était vide.

## Correctif

Croix et bouton **Masquer**. L’avis reste fermé pour la session en cours, même si la liste se rafraîchit encore vide. Il disparaît aussi dès qu’un contact arrive.

## Fichiers touchés

- `views/atak-overwatch-beta.php`
- `public/assets/css/atak-overwatch-beta.css`
- `public/assets/js/atak-overwatch-beta.js`

## Vérification

Tests d’assets : bouton Masquer, mémorisation de session. Recette : Overwatch Beta sans contact, Masquer, l’encart ne revient pas au rafraîchissement de la carte.

## Statut

corrigé
