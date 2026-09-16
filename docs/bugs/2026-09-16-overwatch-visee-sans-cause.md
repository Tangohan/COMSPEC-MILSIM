# Visée Overwatch : cause unique « relief »

## Contexte

Outil Visée / masque sur Overwatch Beta et sur la carte `/atak`.

## Symptôme

Dès qu’un masque était détecté, le tiroir disait toujours « Masqué par le relief ». Le trait restait continu jusqu’à la cible, même après l’obstacle.

## Cause

Le calcul ne lisait que le sol relevé. Les bâtiments et couverts déjà relevés n’étaient pas croisés. La carte traçait un seul trait, sans tenir compte du point de coupe.

## Correctif

Si un volume bâtiment ou couvert coupe la visée, le tiroir le dit clairement, avec la distance et de combien l’obstacle dépasse. Si ces volumes n’ont pas encore été relevés, le poste reste sur le relief et l’indique. Sur la carte : trait continu jusqu’à l’obstacle, puis pointillés jusqu’à la cible.

## Fichiers touchés

- `app/Services/Tactical/AtakTerrainSight.php`
- `app/Controllers/Api/AtakTerrainApiController.php`
- `public/assets/js/atak-overwatch-ops.js`
- `public/assets/js/atak-terrain-tools.js`

## Vérification

Un bâtiment relevé sur le rayon donne « Masqué par un bâtiment » et un trait coupé. Un couvert donne « Masqué par un couvert ». Sans relevé de scène, le texte mentionne que les couverts ne sont pas encore relevés.

## Statut

corrigé
