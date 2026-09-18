# Visée : masque trop loin alors que le bâtiment est devant

## Contexte

Outil Visée / masque sur Overwatch Beta. Observateur (TA1) devant un hangar, trait tiré au-delà.

## Symptôme

Le point de masque tombait plus loin sur la route, alors qu’un bâtiment coupe la visée juste devant. Tout le trait était rouge.

## Cause

Les bâtiments à moins de 50 m de l’observateur étaient ignorés. Le point de masque visait le centre du volume, pas le mur. Le trait visible n’était pas distingué du masqué.

## Correctif

La visée s’arrête au premier mur ou couvert vraiment croisé. Jusqu’à cet obstacle le trait reste dégagé ; au-delà, il est masqué.

## Fichiers touchés

- `app/Services/Tactical/AtakTerrainSight.php`
- `app/Controllers/Api/AtakTerrainApiController.php`
- `public/assets/js/atak-overwatch-ops.js`

## Vérification

Glisser une visée d’un opérateur vers un hangar tout près : le point de masque se pose sur le bâtiment. Le trait est dégagé jusqu’au mur, masqué ensuite.

## Statut

corrigé
