# Ancienne photo Photo Library absente : ticket « ÉCHEC », alerte au joueur et file des photos occupée

## Contexte

Ticket automatique Athena #938, 8 novembre 2026 22:54 : `ERR|file_not_found|2025_10_27_11_59_52.png|srcdir_ok|dirs=2|
newest_<1h|…\Arma 3 - Other Profiles\Z01\Screenshots ; …\Users\Admin\Documents\Arma 3 - COMSPEC\Captures|in=…\Z01\
Screenshots`, connect 1.6.34, Link 2.0.120.

## Lecture du ticket

- `srcdir_ok` : le dossier du chemin demandé existe ; `newest_<1h` : il contient des captures récentes. Seul ce
  fichier manque.
- `AAAA_MM_JJ_HH_MM_SS.png` est le nommage de la Photo Library / Quick Picture d'IceMan (heure du PC à la prise ; vu
  dans les notes du 16 août et du 1er septembre). Le mod COMSPEC ne produit pas ce nom. Une photo du 27 octobre
  **2025** a un an : explication la plus simple, un enregistrement Photo Library ancien dont le fichier a été effacé
  depuis. Une horloge du PC fausse n'est pas exclue (invérifiable sans le RPT du joueur).
- Ce nom vient d'un enregistrement Photo Library (`Iceman_fnc_photo_getRecords`) relancé par
  `fn_athena_pollIcemanPhotos` (toutes les 30 s, une photo jamais vue par cycle). Le veilleur de dossier de Link ne
  peut pas l'avoir envoyé : il exige un fichier présent, écrit il y a moins de 2 min.
- `Users\Admin\…\Captures` avec le profil `Z01` est normal : `Admin` est le compte Windows, `Z01` le profil Arma ; le
  dossier Captures est par compte Windows, sur le disque local (Link 3.1.0).

## Cause (prouvée par le code)

- Nom précis : Link attendait 50 × 400 ms (20 s) puis relançait jusqu'à 4 fois sur 150 s, sur le **seul** fil des
  photos : les photos du téléphone attendaient derrière.
- `file_not_found` → phase « fail » (ERROR, rapport automatique), notification « Capture introuvable » sur le
  téléphone pour une photo que le joueur n'a pas prise, et pause de 5 min des photos
  (`2026-11-08-photos-telephone-bloquees-feed-backoff.md`).
- Liste des photos mortes (`COMSPEC_Athena_PhotoDead`) plafonnée à 200 entrées pour 2 à 3 entrées par photo : au-delà
  d'environ 70 photos disparues, les plus anciennes sortaient de la liste et revenaient toutes les 30 s.

## Correctif

- Link : `IsStaleTimestampedShot` (nom horodaté de plus d'une heure) → un seul essai, sans attente ni relance.
- `fn_extensionCallback` : `file_not_found` sur un nom autre que `COMSPEC_…` (et pas `empty_path`) → avertissement
  (`WARN`, pas de rapport automatique), message « Ancienne photo … absente du disque : ignorée, elle ne sera plus
  renvoyée », pas de notification, dernier retour de la Galerie inchangé, pas de pause ; liste des photos mortes
  portée à 600 entrées.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `tests/Unit/AtakPhotoStaleLibraryAssetTest.php`

## Vérification

- Hors jeu : `IsStaleTimestampedShot` par réflexion : nom du ticket → ancien ; `2026_11_08_22_40_00` à 22:54 →
  récent ; `COMSPEC_644_63259.png`, date invalide, date future → non.
- En jeu (à faire) : Photo Library avec une photo effacée du disque : une seule ligne `WARN · PhotoUpload —
  file_not_found`, aucune notification, et les photos du téléphone partent pendant ce temps.

## Statut

Corrigé à vérifier en jeu.
