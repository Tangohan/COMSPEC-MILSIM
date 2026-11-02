# Photos ATAK : la DLL cherchait les captures « partout »

## Contexte

Photo du téléphone, du drone, des fiches (FRS, SEEK, renseignement), Galerie, capture de la carte : le jeu écrit un PNG
avec la commande `screenshot`, puis COMSPEC Link (ou la DLL native pour la capture de la carte) le retrouve sur le disque
pour le recopier dans `Documents\Arma 3 - COMSPEC\Captures` et l'envoyer à Athena. Question du joueur : « Notre mod
cherche encore le screen partout ? ».

## Symptôme

- Oui : jusqu'à Link 2.0.115, chaque recherche passait par une vingtaine de dossiers : `Documents\Arma 3\Screenshots`,
  chaque sous-dossier de `Documents\Arma 3` et de `Arma 3 - Other Profiles` (un niveau), les mêmes sous OneDrive,
  `%LOCALAPPDATA%\Arma 3` et ses sous-dossiers, le dossier du jeu, **chaque mod de `!Workshop`** (Screenshot,
  Screenshots, `addons\ATAK_PhotoLibrary`), les bibliothèques Steam des lecteurs C: à H: (`libraryfolders.vdf`) pour
  retrouver un dossier BCE, plus le dossier parent de chaque profil. La recherche par nom regardait aussi un niveau de
  sous-dossiers.
- Liste refaite à chaque appel (aucun cache) : `MapCap.Collect` la refaisait toutes les 400 ms pendant 30 s par tuile,
  `TryReadableImageFile` à chaque candidat.
- `NotifyNewPhoto` (commande rapide, sur le fil du jeu) lançait `EnsureScreenshotWatchers`, donc ce parcours des
  dossiers Workshop, en direct à la première photo ; `ResolveLocalImagePath` gardait une attente `Thread.Sleep(50)` × 6.
- Profil lancé avec `-profiles=` / `-name=` : seuls `<profiles>\<nom>\Screenshots` et `<profiles>\Screenshots`
  étaient essayés (pas `<profiles>\Users\<nom>\Screenshots`). Échec : « Photo introuvable sur le disque — capturez à
  nouveau. », sans dire où la DLL avait cherché.

## Cause

Empilement de correctifs successifs (IceMan / BCE / pack SOAR, Photo Library, profils déplacés) : chaque cas ajoutait
des dossiers sans jamais en retirer. Le nom du profil Arma utilisé n'était jamais transmis à la DLL.

## Correctif (ATAK 2.9.30, connect 1.6.28, COMSPEC Link 2.0.116)

- `ScreenshotDirs()` (Extension.cs) : liste bornée, dans cet ordre, sans sous-dossier, recalculée toutes les 5 s au
  plus : (a) dossier du réglage CBA « Dossier des captures d'écran » ; (b) Screenshots du profil Arma utilisé (nom
  transmis par le SQF, `profileName`, sinon `-name` ; `-profiles=` : `Users\<nom>`, `<nom>`, racine) — profil par
  défaut si `<nom>.Arma3Profile` est dans `Documents\Arma 3`, sinon `Arma 3 - Other Profiles\<nom>` (nom brut ou
  encodé `%20`, sinon la liste des profils lue sur un niveau) ; (c) `Documents\Arma 3\Screenshots`, puis
  `OneDrive\Documents`. Le dossier `Captures` COMSPEC reste en fin de liste pour les recherches (copies du mod).
- Supprimés : parcours `!Workshop`, bibliothèques Steam, `%LOCALAPPDATA%\Arma 3`, dossier du jeu, sous-dossiers,
  `FindBceScreenshotDir` / `RemapMissingWorkshopScreenshotPath` (`GetBceScreenshotDir` répond `ERR|not_found`).
- Plus d'attente bloquante : watchers démarrés en tâche de fond, `Thread.Sleep` retiré de `ResolveLocalImagePath`
  (la file des photos relance déjà avec `Task.Delay`).
- `Shots.Config [dossier, profil]` (mémoire, immédiat) envoyé aux deux DLL au démarrage et à chaque changement du
  réglage ; `Shots.Check` (par `Async`) : dossier en service, existe, lisible, nombre d'images, dernière capture.
  Chemin validé dans la DLL : local absolu `X:\…`, pas de `\\serveur`, pas de lecteur réseau, pas de `.` / `..`, pas
  de racine de disque. Lecture seule : aucun ménage automatique ni modification de profil dans ce dossier ; seules les
  images du mod (`COMSPEC_…`, `cmap_…`) peuvent y être effacées à la demande du joueur.
- Message au joueur : « Capture introuvable dans <dossier> : vérifiez le réglage Dossier des captures d'écran » (la
  DLL joint `in=<dossier>` à `file_not_found`), notification du téléphone ; capture de la carte : même renvoi.
- Réglage visible dans Réglages du téléphone › À propos › Photos (dossier utilisé, bouton VÉRIFIER LE DOSSIER).

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`, `COMSPECExtension.csproj`
- `mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/camera/fn_shotDir.sqf` (nouveau), `config.cpp`,
  `XEH_preInit.sqf`, `XEH_postInitClient.sqf`, `functions/pages/fn_pageSettings.sqf`,
  `functions/camera/fn_mapCapture.sqf`, `script_component.hpp`, `i18n/en.txt`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`, `connect/config.cpp`
- `tests/Unit/AtakShotDirSettingAssetTest.php`, `tests/Unit/AtakPhotoLibraryAthenaSendAssetTest.php`

## Vérification

- Hors jeu : DLL compilée, `Shots.Config` / `Shots.Check` appelés par réflexion : `\\srv\share` → `network`,
  `relative\x` → `not_absolute`, `C:\` → `drive_root`, `C:\foo\..\bar` → `parent`, `C:\a:b` → `invalid` ; profil
  `NewPI` → `Documents\Arma 3 - Other Profiles\NewPI\Screenshots` ; dossier saisi absent → repli profil + `missing`.
- En jeu (à faire) : photo du téléphone avec le réglage vide (profil par défaut, puis autre profil) ; réglage rempli
  avec un dossier absent puis réseau (refus affiché) ; Réglages › À propos › VÉRIFIER LE DOSSIER ; Options des addons
  › COMSPEC › ATAK · Photo › Vérifier le dossier des captures ; photo drone, Galerie (envoi, suppression), capture de
  la carte ; jeu lancé avec `-profiles=D:\ArmaProfils -name=Test`.

## Statut

Corrigé (à tester en jeu).
