# UI création objet SSE

## Contexte

Page `/atak/sse/objets/nouveau` — formulaire dense, peu hiérarchisé (liste déroulante de types, champs empilés).

## Symptôme

Lecture difficile : type peu visible, caractéristiques collées au reste, pas de parcours en étapes, actions perdues en bas.

## Cause

Maquette initiale centrée sur le panneau générique, sans composition dédiée ni sélecteur de type scannable.

## Correctif

- Hero + rappel « ce n’est pas une preuve »
- Étapes 01 / 02 / 03 (type & diffusion, caractéristiques, illustration)
- Grille de types en pastilles (radios) au lieu d’un seul select
- Zone image type « joindre », barre d’actions sticky
- CSS dans `sse_portal.css` (charte Bureau SSE)

## Fichiers touchés

- `views/atak/sse/object_create.php`
- `public/assets/css/sse_portal.css`

## Vérification

Ouvrir `/atak/sse/objets/nouveau`, changer de type (caractéristiques se mettent à jour), créer une identité.

## Statut

corrigé
