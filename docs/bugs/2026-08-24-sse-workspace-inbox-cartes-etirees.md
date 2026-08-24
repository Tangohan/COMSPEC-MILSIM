# Inbox workspace SSE — cartes trop hautes

## Contexte

Écran Intelligence Workspace (`/atak/sse/workspace`), colonne Inbox.

## Symptôme

Chaque carte Inbox occupait une grande hauteur : le libellé et le titre s’empilaient et se coupaient à ~2 rem de large, avec un vide important à droite.

## Cause

`.iw-feed-link { display: contents }` était moins spécifique que `.iw-intel-list li > a { display: grid }`. Le lien restait une grille d’une colonne, placée dans la piste icône (2,1 rem) : tout le texte s’enroulait verticalement.

## Correctif

Grille `icône | texte` posée directement sur le lien, cartes en hauteur auto, titre limité à deux lignes.

## Fichiers touchés

- `public/assets/css/sse_workspace.css`
- `views/atak/sse/_layout.php` (cache CSS)
- `tests/Unit/SseWorkspaceUiTest.php`

## Vérification

`php -l` sur le test. Contrôle visuel : cartes compactes, icône à gauche, titre à droite.

## Statut

corrigé
