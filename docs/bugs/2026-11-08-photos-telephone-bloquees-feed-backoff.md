# Photos du téléphone bloquées 5 min après un file_not_found (feed_backoff)

## Contexte

Discord, 8 novembre 2026 (kroncc) : « photos no longer sending @ random intervals & for random users », Athena
resynchronisé plusieurs fois sans effet. ATAK 4.0.0, COMSPEC Link 2.0.120. Ticket automatique Athena #938 (joueur Z01,
connect 1.6.34) : `ÉCHEC · PhotoUpload — file_not_found · 2025_10_27_11_59_52.png`.

## Symptôme

Par moments, les photos prises avec l'app Photo du téléphone ne partent plus : le viseur affiche « Photo non envoyée :
voir Galerie > Envois », sans autre message, puis l'envoi revient seul quelques minutes plus tard. Une
resynchronisation d'Athena ne change rien.

## Cause (prouvée par le code)

- `fn_extensionCallback` (`PhotoUpload`) pose `COMSPEC_FeedSnapFailUntil = maintenant + 300 s` après **tout**
  `file_not_found`, pour couper le spam des aperçus automatiques de flux (casque, drone).
- `fn_captureReconImage` appliquait cette pause à **tout** appel sans chemin (`_path == ""`) : `ERR|feed_backoff`,
  retour `false`, aucune notification. Or `fn_photoMode` et `fn_photoCam` (photo du téléphone) appellent justement
  `["", légende, "CTAB", "", false, false, true, fiche]`.
- Un seul `file_not_found`, même sur une **ancienne photo Photo Library** relancée en fond par
  `fn_athena_pollIcemanPhotos` (cas du ticket #938), bloquait donc toutes les photos du téléphone pendant 5 minutes.
  Aléatoire et seulement pour certains joueurs : il faut d'anciennes photos IceMan effacées du disque, ou une capture
  non écrite (dossier plein, voir `2026-11-08-photos-screenshots-limite-250-mo.md`).
- Variable de mission : la resynchronisation d'Athena ne la remet pas à zéro.

Code présent depuis le 16 août 2026 (f37df93ce). Aucun commit depuis le 28 octobre ne l'introduit. Ce qui le rend plus
fréquent n'est pas prouvé (hypothèses : photos plus lourdes donc dossier plein plus vite ; recherche bornée de Link
2.0.116, 2 novembre, qui ne retrouve plus les anciennes photos IceMan rangées dans les dossiers Workshop, donc plus de
`file_not_found`).

## Correctif

- `fn_captureReconImage` : pause seulement pour un aperçu automatique (`_autoFeed` : flux donné par l'appelant et pas
  de fiche), calculé avant que la caméra active ne remplisse le flux.
- `fn_extensionCallback` : un `file_not_found` sur une ancienne photo qui n'est pas du mod ne pose plus la pause.

## Fichiers touchés

- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_captureReconImage.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `tests/Unit/AtakPhotoStaleLibraryAssetTest.php`

## Vérification

- Hors jeu : `AtakPhotoStaleLibraryAssetTest` (pause réservée aux flux, appels du téléphone vérifiés).
- En jeu (à faire) : provoquer un `file_not_found` (Photo Library avec une photo effacée du disque), puis prendre une
  photo avec le téléphone dans la minute : elle doit partir (`PhotoUpload — uploaded`).

## Statut

Corrigé à vérifier en jeu.
