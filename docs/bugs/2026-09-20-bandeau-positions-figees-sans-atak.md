# Bandeau « plus temps réel » sans opérateur en liaison

## Contexte

Poste Overwatch Beta, carte ouverte sans téléphone ATAK ni pastille d’infanterie visible. Météo affichée en haut à gauche.

## Symptôme

Un bandeau rouge annonce des dizaines ou des centaines de « positions connues » vieilles de plusieurs jours, et « ce n’est plus du temps réel », alors qu’aucun opérateur n’est en liaison.

## Cause

Le bandeau comptait toute unité dont la dernière mise à jour dépassait 20 secondes, y compris des traces anciennes encore marquées en liaison et les unités IA. L’absence de réception récente était aussi traitée comme une liaison figée, même théâtre vide.

## Correctif

Le bandeau ne s’affiche que s’il existe au moins un opérateur (hors IA) vu dans les quinze dernières minutes, et dont la position stagne ou dont la liaison jeu est réellement figée. Les traces de plus de deux heures ne sont plus traitées comme du temps réel.

## Fichiers touchés

- `public/assets/js/atak-overwatch-c2.js`
- `public/assets/js/atak-overwatch-beta.js`
- `views/atak-overwatch-beta.php`
- `public/assets/css/atak-overwatch-beta.css`

## Vérification

Recharger Overwatch Beta (Ctrl+F5). Sans téléphone en liaison : pas de bandeau rouge de positions figées. Avec un opérateur qui vient de couper : le bandeau peut s’afficher, puis disparaître si plus personne n’est récent.

## Statut

Corrigé
