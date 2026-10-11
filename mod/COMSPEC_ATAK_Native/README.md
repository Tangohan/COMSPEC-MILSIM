# @COMSPEC_ATAK_Native

Mod Arma 3 autonome : il embarque COMSPEC Overwatch connect (`comspec_overwatch_main.pbo`, `comspec_overwatch_connect.pbo` et `COMSPECExtension_x64.dll`) pour la connexion Athena, la synchro, les FRS et la Reco. Ne pas charger `@COMSPECOverwatch` en plus. Licence APL-SA, voir `LICENSE` et `CREDITS.md`.

Build : `build_mod.bat` recompile les deux DLL et empaquète le mod et Overwatch. Sous Linux : `hemtt build` dans `Sources`, puis `tools/pack_pbo.py` pour les deux PBO d'Overwatch.

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
- **Athena** (app) : connexion Steam, e-mail + mot de passe, code e-mail ou code d'appairage du portail, puis Entrer. Le terminal réutilise sa session et sa synchronisation (position, marqueurs, chat, ordres, alertes, photos) au lieu d'en ouvrir une seconde.
- **Carte** : boussole, trait jaune joueur → curseur, panneau curseur (grille 6/8/10 chiffres, altitude, distance, azimut), carte « moi ». Outils carte : trait, dessin libre, distance, mesure A-B, bâtiments numérotés, hauteur, boussole, précision de grille, terrain plat, ligne de vue.
- **Marqueurs** : clic avec l'outil marqueur pour un marqueur rapide, double clic pour l'éditeur complet (titre, description partagée, type, couleur, taille, orientation, opacité, canal), clic pour sélectionner, double clic pour modifier, `Suppr` sur le marqueur pointé pour l'effacer. Traits et dessins en polylignes. Fonctionne en mini comme en plein écran.
- **Messagerie** : canaux Général / Commandement / Groupe / Alertes TOC et messages directs, en bulles. Le téléphone vibre à l'arrivée d'un message.
- **Musique** : fichiers du dossier `Documents\Arma 3\COMSPEC_Music`, pistes du serveur (musiques d'Arma, des mods et de la mission, radio de la mission en réglage CBA) et liens audio (MP3, WAV, WMA, M4A, AAC, webradio). Volume, file d'attente, répétition, aléatoire, et **haut-parleur** : les joueurs proches entendent la même musique au même moment, plus fort en s'approchant. Fichiers et liens passent par la DLL (lecteur Windows), les pistes serveur par le moteur d'Arma.
- **Réseau** : stabilité, latence, perte, relais, zone radio. **Réglages** : réglages du téléphone et réglages roleplay d'Overwatch. **Photos** : photo rapide envoyée sur ATAK web.
- **Extraction** (câble DL-7) : regarder un support de données (ordinateur, téléphone, clé USB, caisse, corps…) et « Brancher l'ATAK » (molette ou ACE). Le câble et le module DL-7 apparaissent autour du téléphone (écran LCD, voyants PWR / AUTH / DATA) ; l'app montre l'écran du support (Windows, Linux, militaire, SCADA, téléphone, coffre), demande le mot de passe ou force l'accès, puis télécharge les fichiers choisis. S'éloigner arrache le câble (les fichiers déjà reçus restent). Données récupérées : lecture, coordonnées sur la carte, audio, partage au groupe.

## Personnalisation, antennes et menu ACE

- Réglages > Personnalisation : couleur de la coque (coyote, vert OD, noir), du module DL-7 (olive, noir, tan), antenne montée, bouton « Voir l'arrière ».
- Antennes (objets `COMSPEC_ATAK_Antenna_Stub`, `_Whip`, `_Patch`) : à porter dans l'inventaire puis visser (ACE « Téléphone ATAK > Antenne » ou Réglages). Effet sur le débit simulé (`fn_linkQuality`) : courte x1,25, fouet x1,5, patch x1,7 à l'arrêt seulement.
- ACE : une seule entrée « Téléphone ATAK » en auto-interaction (Voir l'arrière, Antenne, Personnaliser, Entretien, Débrancher le câble DL-7).
- Textures générées par `tools/gen_assets.py` (`MODULE_TINTS`, `SHELL_TINTS`, `phone_back`, `antenna_svg`).
- Module DL-7 et panneau arrière rendus en 3D par `tools/render_3d.py` (Blender en module Python : `pip install bpy`), images dans `tools/render/`, puis repris par `gen_assets.py`.

## Données à extraire (mission makers)

- **Eden** : module **COMSPEC — ATAK · Données > Données à extraire (câble ATAK)** synchronisé à un ou plusieurs objets (sans objet : le support choisi est créé à l'emplacement du module). **Ajouter un fichier** pour compléter.
- **Zeus** : mêmes modules, lâchés sur l'objet ; un formulaire s'ouvre. **Remettre / retirer les données** remet un support à zéro (essais, verrou, effacement).
- **Script** (champ init d'un objet) :
  `[this, [["name", "PC du QG"], ["password", "4471"], ["files", "ordre.txt | texte" + endl + "Repli à 04h00" + endl + "---" + endl + "cache | coord" + endl + "045123"]]] call comspec_atak_native_fnc_dataSetup;`
- Fichiers : un bloc par fichier, séparés par `---`, première ligne `nom | type`. Types : `texte`, `document`, `identifiants` (le mot de passe d'un autre poste, pour enchaîner), `coord` (carroyage ou `x, y`), `image` (chemin dans la mission), `audio` (classe CfgSounds ou chemin).
- Réglages : écran, organisation, couleur, image, mot de passe (diffusé en empreinte seulement), indice, essais, sanction (verrou, effacement, alarme), forçage et sa durée, durée du transfert, port (USB-C, RJ45, série), alarme (branchement, mauvais mot de passe, fin), usage unique, code serveur en fin.
- Événement CBA global en fin de téléchargement : `comspec_atak_native_dataDownloaded` `[objet, joueur, noms des fichiers]`.
- Réglages serveur **COMSPEC > ATAK · Extraction de données** : câble DL-7 obligatoire (objet « Câble de données DL-7 »), longueur du câble, vitesse des transferts.

## Ressources graphiques

`tools/gen_assets.py` convertit la coque fournie (`tools/src/android_s7_ca.png`, portrait obtenu par rotation) et dessine les icônes (SVG → PNG → PAA). Il demande Pillow, CairoSVG et HEMTT :
`python3 tools/gen_assets.py /chemin/vers/hemtt` régénère `Sources/addons/main/data/*.paa`.

## Build

- Windows avec Arma 3 Tools et le SDK .NET 8 : `build_mod.bat` (DLL + PBO).
- Sans Arma 3 Tools (Linux ou Windows) : `hemtt build` depuis `Sources/`, puis copier
  `Sources/.hemttout/build/addons/comspec_atak_native_main.pbo` vers `@COMSPEC_ATAK_Native/addons/main.pbo`.
  `hemtt check` vérifie la config et le SQF.
