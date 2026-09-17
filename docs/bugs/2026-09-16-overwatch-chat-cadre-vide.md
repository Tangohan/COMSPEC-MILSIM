# Fil Comms : grand cadre vide au-dessus des messages

## Contexte

Onglet Comms, canal sans message (ou avec des messages).

## Symptôme

Un grand rectangle sombre à bordure rouge occupait le centre du fil. Le texte « Aucun message pour le moment » passait en dessous, hors du cadre.

## Cause

La boîte de confirmation « Vider le fil » a `display:grid`, ce qui écrase `hidden`. Elle restait donc dans la grille et prenait toute la hauteur restante.

## Correctif

`hidden` masque vraiment la confirmation. Le fil occupe l’espace restant, le texte vide s’affiche dedans.

## Fichiers touchés

- `public/assets/css/atak-overwatch-beta.css`

## Vérification

Comms → un canal vide : pas de cadre rouge. « Aucun message » est dans le fil. Vider le fil affiche encore la confirmation.

## Statut

corrigé
