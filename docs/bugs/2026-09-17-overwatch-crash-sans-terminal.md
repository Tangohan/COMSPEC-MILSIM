# Arrêt du jeu sans téléphone — liaison Overwatch en fond

## Contexte

Même faute moteur sur neuf sessions du 17/09 (`can't resize AutoArray` puis
`7C2D2B58`). Plusieurs sessions sans écran ATAK, avec `pas de terminal`.
Athena 1.0.133 ne changeait rien. L’opérateur a décoché Overwatch dans
Paramètres d’addon : le jeu tient.

## Symptôme

Environ 40 à 50 secondes après l’arrivée en mission, le jeu se ferme tout
seul. Pas besoin d’ouvrir le téléphone ni de l’avoir en poche.

## Cause

La session poste se restaure toute seule. Les boucles de liaison (messages,
repères, ordres, appui…) partent ensuite, même sans téléphone. Couper
Overwatch arrête ces échanges : plus d’arrêt. Le téléphone et une session
déjà ouverte restent affichés — la case ne ferme pas la session en cours.

## Correctif

Les boucles ne démarrent plus tant que l’opérateur n’a pas le téléphone.
Dès qu’il l’équipe, la liaison reprend. Décochez Overwatch : les échanges
s’arrêtent ; quittez Arma pour couper vraiment une session déjà ouverte.

## Fichiers touchés

- `connect/functions/fn_startSyncLoops.sqf`
- `connect/XEH_postInit.sqf`
- `connect/XEH_preInit.sqf`
- `connect/functions/fn_pollChatMessages.sqf`
- `connect/functions/fn_pollAthenaMarkers.sqf`

## Vérification

Overwatch décoché : plus d’arrêt, session éventuellement encore affichée.
Overwatch recouché, sans téléphone : le jeu tient. Équiper le téléphone :
la liaison reprend.

## Statut

corrigé (Athena 1.0.134)
