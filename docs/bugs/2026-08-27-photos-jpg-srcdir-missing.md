# Photos terrain introuvables après la bascule VPS

## Contexte

Depuis le passage d’Athena sur le VPS, les photos (casque, drone, cliché ATAK) ne remontent plus sur la carte. Journal Overwatch : `PhotoUpload file_not_found` / `srcdir_missing` sur un JPEG daté.

## Symptôme

Galerie vide pour les nouvelles prises. Le handshake Athena peut pourtant réussir.

## Cause

Le pont Photo Library / BCE annonçait un JPEG dont le dossier n’existe pas, et demandait à l’extension de l’envoyer tel quel. L’extension mettait le travail en file (succès apparent) puis échouait : le fichier n’était jamais sur le disque. Ce n’est pas un refus du VPS : l’image ne partait pas du PC de jeu.

## Correctif

Pour un JPEG annoncé, relancer une capture PNG Arma hors de la frame du clic, au lieu d’envoyer le chemin mort.

## Fichiers touchés

- `mod/.../atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`
- `mod/.../atak_athena/config.cpp` (1.0.52)

## Vérification

Rebuild pack, relancer Arma. Prendre une photo : un PNG COMSPEC doit apparaître dans Captures / Screenshots, puis la galerie du poste.

## Statut

corrigé (rebuild pack requis)
