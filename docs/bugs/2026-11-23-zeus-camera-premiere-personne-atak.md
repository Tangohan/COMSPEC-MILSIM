# Zeus : la caméra repasse en première personne à cause de l'ATAK

## Contexte
Interface Zeus ouverte pendant qu'un écran de l'ATAK rend une caméra (flux drone, caméra en direct, photo, vue 3D, carte JPC, test PiP).

## Symptôme
La vue repasse sur le joueur en première personne alors que l'interface Zeus reste ouverte. Il faut quitter puis rouvrir le Zeus.

## Cause
Un `cameraEffect` vers une texture, ouvert ou terminé pendant que le Zeus tient la vue, rend la vue principale à l'unité du joueur. Le correctif `fn_tocRtt` (mode `keep`) ne couvrait que les écrans TOC.

## Correctif
Après chacun des 33 `cameraEffect` des autres caméras de l'ATAK, `["keep"] call comspec_atak_native_fnc_tocRtt` rend la vue à `curatorCamera` si l'interface Zeus est ouverte.

## Fichiers touchés
14 fichiers dans `functions/camera`, `functions/drone`, `functions/pages` et `functions/shell`.

## Vérification
Lecture du code. Non reproduit en jeu.

## Statut
corrigé à vérifier en jeu
