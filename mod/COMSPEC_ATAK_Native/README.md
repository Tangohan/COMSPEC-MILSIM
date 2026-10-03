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

- `Ctrl+U` sort ou range le téléphone **porté** : il reste affiché dans le coin (vertical ou horizontal) et l'on continue à jouer, sans souris. La carte suit le joueur.
- `Ctrl+Maj+U` prend le téléphone **en main** (souris, clavier) ou le repose. En main, les boutons du haut basculent vertical / horizontal et mini / plein écran (plein écran toujours horizontal).
- Barre d'état : réseau Athena, batterie simulée (recharge en véhicule), météo (ACE si présent), heure de la mission.
- Carte : indicatifs (pseudo seulement sans indicatif), carte « moi » (grille, cap, altitude), panneau curseur (grille, altitude, distance, azimut), outils centrer / suivre / zoom / marqueur (ENI, AMI, OBJ, DANGER, PT sur le canal courant) / mesure / libellés.
- Messagerie : bulles par message, préfixes Athena (`[GROUPE]`, `[ROUTINE]`…) affichés en puces, lignes techniques masquées, envoi visible tout de suite.
- Données web : les ordres et alertes publiés par Overwatch connect sont fusionnés avec ceux d'Athena au lieu de les écraser.
- Le lanceur (bouton `APPS`) liste les applications déclarées dans `COMSPEC_ATAK_Apps` (`config.cpp`).

## Ressources graphiques

`tools/gen_assets.py` dessine la coque du téléphone et les icônes (SVG → PNG → PAA). Il demande Pillow, CairoSVG et HEMTT :
`python3 tools/gen_assets.py /chemin/vers/hemtt` régénère `Sources/addons/main/data/*.paa`.

## Build

- Windows avec Arma 3 Tools et le SDK .NET 8 : `build_mod.bat` (DLL + PBO).
- Sans Arma 3 Tools (Linux ou Windows) : `hemtt build` depuis `Sources/`, puis copier
  `Sources/.hemttout/build/addons/comspec_atak_native_main.pbo` vers `@COMSPEC_ATAK_Native/addons/main.pbo`.
  `hemtt check` vérifie la config et le SQF.
