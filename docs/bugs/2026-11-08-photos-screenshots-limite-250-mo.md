# Photos non écrites : dossier Screenshots du profil au-delà de 250 Mo

## Contexte

Même signalement (kroncc, photos qui ne partent plus par intermittence). Arma 3 refuse d'écrire une capture quand le
dossier Screenshots du profil dépasse `maxScreenShotFolderSizeMB` (250 Mo par défaut) : RPT
`Cannot save file. Directory …\Screenshots\ exceeded size limit (250MB).` (vu dans l'autotest du 8 novembre).

## Symptôme

Après une dizaine de photos dans la partie, la capture n'existe plus sur le disque : `PhotoUpload file_not_found`, puis
(avant le correctif de `2026-11-08-photos-telephone-bloquees-feed-backoff.md`) 5 minutes sans aucun envoi.

## Cause (prouvée)

- Une photo du téléphone pèse environ 20 Mo (PNG d'Arma ; 20 à 24 Mo sur le poste de test).
- COMSPEC Link recopie la capture dans `Documents\Arma 3 - COMSPEC\Captures` (`MirrorCapture`) et envoie la copie.
  Après l'envoi, `TryDeleteUploadedPhoto` effaçait **la copie** ; l'original restait dans Screenshots. Poste de test :
  17 photos `COMSPEC_*` (214 Mo) dans `NewPI\Screenshots`, dont des photos `uploaded` le 7 novembre, et `Captures`
  vide. Depuis le 3 octobre (e22416deb).
- Le ménage (`EnsureScreenshotQuota` : photos `COMSPEC_*` les plus anciennes au-delà de 180 Mo) ne passait **qu'une
  fois par partie**, à la première photo.
- Le relèvement de `maxScreenShotFolderSizeMB` dans le `.Arma3Profile` n'agit qu'au lancement suivant, et Arma réécrit
  ce fichier à la fermeture (profil `COMSPECTest` réécrit sans la clé) : non fiable. Qu'il soit perdu à chaque fois
  n'est pas prouvé.

## Correctif

- `Documents\Arma 3 - COMSPEC\Captures` devient le dossier des photos du mod : la copie envoyée y reste (200 fichiers
  et 1 Go au plus), et une fois la photo reçue par Athena l'**original** `COMSPEC_*` est retiré de Screenshots
  (`FinishUploadedShot`), seulement si la copie est complète. Jamais une image du joueur (autre nom), jamais le dossier
  choisi dans le réglage « Dossier des captures d'écran ». Sans copie possible (Documents en lecture seule) :
  comportement d'avant.
- Ménage de Screenshots toutes les 5 min au plus (seuil 150 Mo) et tout de suite après un `file_not_found` ; le profil
  `.Arma3Profile` n'est relevé qu'une fois par partie.
- `file_not_found` porte `size=<Mo>` : à 240 Mo ou plus, le téléphone dit « dossier des captures plein » et nomme le
  dossier à vider, au lieu de « capture introuvable ».
- Vérification du dossier (`Shots.Check`) : taille et dossier Captures ; avertissement une fois par partie dès 200 Mo
  (vérification 20 s après le chargement) ; Galerie › ENVOIS et Réglages › À propos › Photos : ligne du dossier,
  boutons OUVRIR LE DOSSIER CAPTURES (`Shots.OpenCaptures`) et VÉRIFIER LE DOSSIER ; Captures impossible à créer : dit
  en clair (« envoi depuis Screenshots »).

Le nom prévu `COMSPEC_<n>_<n>.png` reste la clé d'une photo du téléphone : `screenshot` écrit exactement ce nom, et
prendre « la plus récente par date » renvoyait la photo précédente (doublons Quick Picture, note du 13 septembre). Les
noms périmés d'autres mods sont traités par `2026-11-08-photo-library-ancienne-photo-bloque-file.md`.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs` (COMSPEC Link et DLL native, même source)
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/camera/fn_shotDir.sqf`,
  `functions/pages/fn_pageGallery.sqf`, `functions/pages/fn_pageSettings.sqf`, `XEH_postInitClient.sqf`, `i18n/en.txt`
- `tests/Unit/AtakPhotoStaleLibraryAssetTest.php`

## Vérification

- Hors jeu : DLL compilées (Link et native) ; `FinishUploadedShot` appelé par réflexion sur des fichiers temporaires :
  original `COMSPEC_` retiré si la copie est complète, gardé si la copie est tronquée, image du joueur gardée ;
  `DirectorySizeMb` = 250 sur le dossier de l'autotest ; `IsScreenshotQuotaDue` (0, 1 min, 6 min).
- En jeu (à faire) : 15 photos d'affilée avec un profil à 250 Mo par défaut ; `Screenshots` ne doit plus grossir,
  `Captures` garde les photos, la Galerie les montre toujours ; bouton OUVRIR LE DOSSIER CAPTURES.

## Statut

Corrigé à vérifier en jeu.
