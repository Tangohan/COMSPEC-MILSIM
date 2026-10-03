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

- `Ctrl+U` sort ou range le téléphone **porté** : il reste affiché dans le coin et l'on continue à jouer, sans souris. La carte suit le joueur.
- `Ctrl+Maj+U` le prend **en main** (souris, clavier) ou le repose. En main : glisser la coque pour déplacer le téléphone, boutons du haut pour vertical / horizontal et mini / plein écran.
- **Athena** (app) : connexion Steam, e-mail + mot de passe, code e-mail ou code d'appairage du portail, puis Entrer. Avec COMSPEC Overwatch chargé, le terminal réutilise sa session et sa synchronisation (position, marqueurs, chat, ordres, alertes, photos) au lieu d'en ouvrir une seconde.
- **Carte** : boussole, trait jaune joueur → curseur, panneau curseur (grille 6/8/10 chiffres, altitude, distance, azimut), carte « moi ». Outils carte : trait, dessin libre, distance, mesure A-B, bâtiments numérotés, hauteur, boussole, précision de grille, terrain plat, ligne de vue.
- **Marqueurs** : clic avec l'outil marqueur pour un marqueur rapide, double clic pour l'éditeur complet (titre, description partagée, type, couleur, taille, orientation, opacité, canal), clic pour sélectionner, double clic pour modifier, `Suppr` sur le marqueur pointé pour l'effacer. Traits et dessins en polylignes. Fonctionne en mini comme en plein écran.
- **Messagerie** : canaux Général / Commandement / Groupe / Alertes TOC et messages directs, en bulles. Le téléphone vibre à l'arrivée d'un message.
- **Réseau** : stabilité, latence, perte, relais, zone radio. **Réglages** : réglages du téléphone et réglages roleplay d'Overwatch. **Photos** : photo rapide envoyée sur ATAK web.

## Ressources graphiques

`tools/gen_assets.py` convertit la coque fournie (`tools/src/android_s7_ca.png`, portrait obtenu par rotation) et dessine les icônes (SVG → PNG → PAA). Il demande Pillow, CairoSVG et HEMTT :
`python3 tools/gen_assets.py /chemin/vers/hemtt` régénère `Sources/addons/main/data/*.paa`.

## Build

- Windows avec Arma 3 Tools et le SDK .NET 8 : `build_mod.bat` (DLL + PBO).
- Sans Arma 3 Tools (Linux ou Windows) : `hemtt build` depuis `Sources/`, puis copier
  `Sources/.hemttout/build/addons/comspec_atak_native_main.pbo` vers `@COMSPEC_ATAK_Native/addons/main.pbo`.
  `hemtt check` vérifie la config et le SQF.
