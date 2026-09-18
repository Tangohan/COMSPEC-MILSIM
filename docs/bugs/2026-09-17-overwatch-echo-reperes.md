# Arrêt du jeu avec Overwatch actif — écho des repères

## Contexte

Décochez Overwatch dans les paramètres d’addon : le jeu tient. Recochez :
le jeu se ferme, souvent quelques dizaines de secondes plus tard, téléphone
ouvert ou non. Dernière ligne moteur : taille de tableau négative, puis
faute `7C2D2B58`. Athena 1.0.134 (liaison seulement avec téléphone) ne
couvrait pas ce cas : le téléphone était déjà porté.

## Symptôme

- Overwatch coché : arrêt brutal en mission, parfois juste après avoir
  recouché la case pendant la pause.
- Overwatch décoché : plus d’arrêt, mais la position et le chat continuaient.
- Les ordres (FRM, etc.) suivaient bien la case.

## Cause

Chaque repère créé ou déplacé (formes du poste, anneaux d’objectif, points
web) relançait plusieurs envois forcés vers le poste, plus le pont des
repères du téléphone. Une liste de formes trop longue était coupée : les
formes absentes du morceau étaient effacées puis recréées en boucle. Une
découpe de nom trop courte pouvait demander une taille de tableau négative
au moteur.

La remontée de position ignorait la case Overwatch. Les menus ACE se
réinstallaient à chaque relance Eden dans la même session Arma.

## Correctif

Les repères déjà posés par la liaison sont ignorés. Un seul rattrapage, sans
forçage. Une liste coupée ne déclenche plus d’effacement. Décochez Overwatch :
plus de position ni de messages. Les menus ACE ne se réinstallent plus à
chaque relance de mission dans la même session.

## Fichiers touchés

- `connect/XEH_postInit.sqf`
- `connect/functions/fn_startSyncLoops.sqf`
- `connect/functions/fn_pollMapShapes.sqf`
- `connect/functions/fn_receiveMapShape.sqf`
- `connect/functions/fn_deleteMapShape.sqf`
- `connect/functions/fn_pollAthenaMarkers.sqf`
- `connect/functions/fn_pollPoMarkers.sqf`
- `connect/functions/fn_resyncAllMapMarkers.sqf`
- `connect/functions/fn_updatePosition.sqf`
- `connect/functions/fn_initACE.sqf`
- `connect/functions/fn_initACEAthena.sqf`
- `connect/functions/fn_pollChatMessages.sqf`
- `connect/functions/fn_initATAK.sqf`

## Vérification

Quitter Arma complètement, pack Athena 1.0.135 / Overwatch 1.5.79. Recocher
Overwatch. Mission avec téléphone, une à deux minutes, ouvrir la pause,
revenir. Le jeu reste ouvert. Décochez Overwatch : la position s’arrête.

## Statut

corrigé (Athena 1.0.135 / Overwatch 1.5.79)
