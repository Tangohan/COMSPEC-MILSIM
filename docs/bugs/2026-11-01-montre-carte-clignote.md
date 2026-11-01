# Carte de la montre Garmin qui clignote

## Contexte
ATAK natif 2.9.22, montre Garmin, page CARTE (vidéo joueur du 01/11).

## Symptôme
La mini-carte alterne à chaque instant entre la position du joueur et un autre endroit (sans le point du joueur).

## Cause
`fn_watch.sqf` (page MAP) applique deux corrections : un recentrage écran qui décale le point visé pour que le joueur
tombe au centre du contrôle, puis un « suivi lent » qui remettait ce point visé sur la position brute dès qu'il s'en
écartait de plus de 2 m. Quand le décalage de recentrage dépasse 2 m (zooms larges), les deux s'annulent à chaque tick.

## Correctif
Le suivi lent déplace le point visé du même déplacement que le joueur (le décalage de recentrage est conservé).

## Fichiers touchés
`mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/garmin/fn_watch.sqf`

## Vérification
En jeu : montre, page CARTE, chaque zoom, à pied puis en véhicule : la carte reste centrée sur le joueur, sans saut.

## Statut
corrigé à vérifier en jeu (2.9.23)
