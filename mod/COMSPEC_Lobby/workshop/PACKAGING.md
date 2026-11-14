# Publication Workshop : COMSPEC Lobby

L'objet Steam n'existe pas encore. `PublisherCmd` ne sait que mettre à jour un objet existant : la **première** publication se fait une fois à la main, avec le Publisher d'Arma 3 Tools (fenêtre), sur décision de TTRD.

## Première publication (une fois)

1. Construire : `cmd /c "<chemin>\mod\COMSPEC_Lobby\build_mod.bat"`.
2. Pack propre : dossier `mod\COMSPEC_Lobby\publisher\@COMSPEC_Lobby` (ignoré par Git) avec seulement `addons\comspec_lobby_main.pbo` + `.bisign`, `keys\comspec_lobby.bikey`, `mod.cpp`, `logo.paa`.
3. Arma 3 Tools > Publisher > nouveau mod : ce dossier, titre « COMSPEC Lobby », description `STEAM_DESCRIPTION.txt`, aperçu `preview.jpg`, note = premier bloc `[h1]` de `STEAM_CHANGELOG.txt`.
4. Écrire l'identifiant de l'objet dans `workshop\STEAM_ITEM_ID`.

## Mises à jour

    & "F:\SteamLibrary\steamapps\common\Arma 3 Tools\Publisher\PublisherCmd.exe" update /id:<STEAM_ITEM_ID> "/changeNoteFile:<note.txt>" "/path:<chemin>\mod\COMSPEC_Lobby\publisher\@COMSPEC_Lobby" /nologo

Essai du 14/11/2026 : la création par script (bibliothèques du Publisher chargées dans PowerShell) n'aboutit pas, Steam ne s'initialise pas hors de l'outil. Ne pas réessayer cette voie.
