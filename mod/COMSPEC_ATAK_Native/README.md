# @COMSPEC_ATAK_Native

Mod Arma 3 autonome et distinct de `@COMSPECOverwatch`. Licence APL-SA, voir `LICENSE` et `CREDITS.md`.

Identités livrées :

- dossier publié : `@COMSPEC_ATAK_Native` ;
- patch Arma : `comspec_atak_native_main` ;
- PBO : `main.pbo` avec préfixe `z\comspec_atak_native\addons\main` ;
- DLL : `COMSPECATAKNativeExtension_x64.dll` ;
- produit transmis à Athena : `comspec_atak_native` ;
- génération UI transmise à Athena : `native-rsc-v1`.

## En jeu

- `Ctrl+U` ouvre / ferme le terminal, `Ctrl+Maj+U` bascule mini / plein écran (touches CBA modifiables).
- Mode mini : téléphone dans le coin bas droit, dock d'apps en bas. Mode plein écran : rail d'apps à gauche, inspecteur sur la carte.
- Le lanceur (bouton `APPS`) liste les applications déclarées dans `COMSPEC_ATAK_Apps` (`config.cpp`).

## Build

- Windows avec Arma 3 Tools et le SDK .NET 8 : `build_mod.bat` (DLL + PBO).
- Sans Arma 3 Tools (Linux ou Windows) : `hemtt build` depuis `Sources/`, puis copier
  `Sources/.hemttout/build/addons/comspec_atak_native_main.pbo` vers `@COMSPEC_ATAK_Native/addons/main.pbo`.
  `hemtt check` vérifie la config et le SQF.
