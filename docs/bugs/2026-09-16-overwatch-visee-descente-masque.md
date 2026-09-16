# Visée masquée à tort en descente

## Contexte

Outil Visée / masque sur Overwatch Beta. Trait tiré depuis un aéronef (ou une crête) vers un point plus bas.

## Symptôme

Le tiroir affichait « Masqué par le relief » avec une coupe à quelques dizaines de mètres (ex. 47 m), alors que l’observateur est visiblement plus haut. Altitudes affichées : observateur plus bas que la cible, ce qui contredit la pente vue sur la carte.

## Cause

1. La visée prenait le **sol** sous le clic, pas l’altitude de l’appareil tout près.
2. La première case de relief (environ 50 m) sous l’observateur était comptée comme un obstacle : le rebord de la pente où il se trouve.

## Correctif

- Si un contact est à moins de 80 m du point, son altitude réelle sert d’observateur ou de cible.
- La case de sol sous l’observateur (et sous la cible) n’est plus un masque.
- Le tiroir indique la pente du sol et précise « appareil » quand l’altitude vient d’un contact.

## Fichiers touchés

- `app/Services/Tactical/AtakTerrainSight.php`
- `app/Controllers/Api/AtakTerrainApiController.php`
- `public/assets/js/atak-overwatch-beta.js`
- `tests/Unit/AtakTerrainSightTest.php`

## Vérification

Tests : visée en descente avec une lèvre de 13 m sur la première case → dégagée. Aéronef à 350 m au-dessus d’une crête à 200 m → dégagée.

## Statut

corrigé
