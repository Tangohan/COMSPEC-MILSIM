# Crash STATUS_STACK_OVERFLOW (0xC00000FD) — upload photos Overwatch

## Contexte

Session du 16/08/2026 : crashs Arma 3 en boucle après liaison Athena, avec remontée auto des captures ATAK / Photo Library. Journal `COMSPEC_2026-08-16_161629_587.log` + rapport `ArmaReport_Log_20260816T141912_tetar.zip`.

## Symptôme

- Launcher Arma : « Arrêt anormal », code `0xC00000FD` (`STATUS_STACK_OVERFLOW`)
- Après Handshake Athena : plusieurs `NotifyNewPhoto` OK puis `PhotoUpload` en échec `file_not_found` / `srcdir_missing`
- Chemins diagnostiques tronqués (ellipsis `…` → caractères bizarres dans le journal), ex. :
  - `…ments\Arma 3 - Other Profiles\NewPI\Screenshots`
  - **`F:\SteamLibrary\steamapps\common\Arma 3`** (racine jeu entière listée comme dossier de captures)
  - `…\!Workshop\@# S.O.A.R - FN\Screenshot`
- Plusieurs `PreInit` en quelques minutes (boucle crash → relance)

## Cause

1. **Racine Arma traitée comme dossier Screenshots** (`AddIfExists(cwd)` dans `EnumerateScreenshotDirs`).
2. Sur ce dossier, `FindScreenshotByFileName` faisait un `Directory.EnumerateFiles(..., SearchOption.AllDirectories)`. Avec les jonctions / arborescence Workshop, ce scan récursif natif peut provoquer un **débordement de pile** (`STATUS_STACK_OVERFLOW`) — exception non rattrapable en .NET.
3. Le `FileSystemWatcher` était aussi posé sur ces dossiers avec `IncludeSubdirectories = true`, donc potentiellement sur toute l’install.
4. Amplificateur de boucle : `ReleasePhotoDedup` après `file_not_found` + `PhotoSeen` uniquement en mémoire → après chaque crash / PreInit, les vieux clichés Photo Library (chemins morts type SOAR) étaient **re-notifiés** et re-scannaient le disque.

Les ellipsis `…` dans le diagnostic n’étaient **pas** une troncature de jointure de chemin cassée : c’est le format volontaire de `DescribeImageLookupFailure` (suffixe des chemins longs).

## Correctif

- Ne plus jamais ajouter la racine Arma comme source de captures.
- Watcher limité aux feuilles `Screenshots` / `Screenshot` / `Captures` COMSPEC, sans sous-arborescence large.
- Remplacement de `AllDirectories` par un scan peu profond (racine + 1 niveau).
- Abandon rapide si le dossier parent est mort ; pas de re-scan global en boucle.
- Ne plus libérer le dédup DLL après `file_not_found`.
- Persistance profil `COMSPEC_Athena_PhotoDead` pour ne pas rejouer les introuvables après un crash.
- Version connect **1.4.17** / extension **2.0.1**.

## Fichiers touchés

- `mod/UptoDate/COMSPECExtension/Extension.cs`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_extensionCallback.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/connect/config.cpp`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_pollIcemanPhotos.sqf`
- `mod/UptoDate/Sources/comspec-overwatch-addons/atak_athena/functions/fn_athena_bridgeIcemanPhoto.sqf`

## Vérification

- [ ] Rebuild `COMSPECExtension_x64.dll` + PBO `connect` / `atak_athena`
- [ ] Déployer vers `@COMSPECOverwatch` Workshop / local
- [ ] Relancer Arma : journal Boot `connect v1.4.17`, ping extension `2.0.1`
- [ ] Handshake Athena : plus de crash ; anciens clichés morts → au plus un `file_not_found` puis silence (liste morte profil)
- [ ] Nouvelle capture récente : remontée Athena OK (signal SQF ou watcher sur vrai dossier Screenshot)

## Statut

corrigé dans les sources — **rebuild / déploiement requis** avant validation in-game
