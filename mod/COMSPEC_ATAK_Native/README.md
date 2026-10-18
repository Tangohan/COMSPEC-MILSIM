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
- **Plan** (carte de briefing) : carte fixe nord en haut, graphismes APP-6A (zones ENY/INFIL/OBJ…, lignes, CLEARED/UNCLEARED), stylo, plan partagé par camp (réglage CBA `comspec_atak_native_plan_edit`), plans nommés, export/import `COMSPECPLAN1|…`.
- **Réseau** : stabilité, latence, perte, relais, zone radio. **Réglages** : réglages du téléphone et réglages roleplay d'Overwatch. **Photos** : photo rapide envoyée sur ATAK web.
- **Extraction** (câble DL-7) : regarder un support de données (ordinateur, téléphone, clé USB, caisse, corps…) et « Brancher l'ATAK » (molette ou ACE). Le câble et le module DL-7 apparaissent autour du téléphone (écran LCD, voyants PWR / AUTH / DATA) ; l'app montre l'écran du support (Windows, Linux, militaire, SCADA, téléphone, coffre), demande le mot de passe ou force l'accès, puis télécharge les fichiers choisis. S'éloigner arrache le câble (les fichiers déjà reçus restent). Données récupérées : lecture, coordonnées sur la carte, audio, partage au groupe.

## Personnalisation, antennes et menu ACE

- Réglages > Personnalisation : couleur de la coque (coyote, vert OD, noir), du module DL-7 (olive, noir, tan), antenne montée, bouton « Voir l'arrière ».
- Antennes (objets `COMSPEC_ATAK_Antenna_Stub`, `_Whip`, `_Patch`) : à porter dans l'inventaire puis visser (ACE « Téléphone ATAK > Antenne » ou Réglages). Effet sur le débit simulé (`fn_linkQuality`) : courte x1,25, fouet x1,5, patch x1,7 à l'arrêt seulement.
- ACE : une seule entrée « Téléphone ATAK » en auto-interaction (Voir l'arrière, Antenne, Personnaliser, Accessoires, Entretien, Passerelle du véhicule, Effacement d'urgence, Débrancher le câble DL-7).
- Textures générées par `tools/gen_assets.py` (`MODULE_TINTS`, `SHELL_TINTS`, `phone_back`, `antenna_svg`).
- Module DL-7 et panneau arrière rendus en 3D par `tools/render_3d.py` (Blender en module Python : `pip install bpy`), images dans `tools/render/`, puis repris par `gen_assets.py`.

## Gilet, modèles, accessoires et véhicules

- Téléphone sur le gilet : modèle `data/vest_phone.p3d` (classe `COMSPEC_ATAK_VestPhone`, sélections `camo` et `patch`), exporté par `tools/model_vest_phone.py` (Blender + extension « Arma 3 Object Builder », GPL-3, non livrée) ; textures `vest_phone*_co.paa` par `gen_assets.vest_phone_atlas`. Orientation à régler en jeu si besoin : `COMSPEC_ATAK_VestDirUp = [[dir], [up]]`.
- Modèles de téléphone (`COMSPEC_ATAK_PhoneModel` : `s7`, `rugged`, `iphone`) et tablette de bord (`tablet`) : rendus par `tools/render_phones.py`, même ouverture d'écran que le S7.
- Accessoires : `COMSPEC_ATAK_PowerBank` (`fn_powerBank`), `COMSPEC_ATAK_NvgFilter` et lueur des écrans (`fn_screenGlow`, réglage serveur « Lueur des écrans la nuit »). Effacement d'urgence et téléphone extractible en cas de capture : `fn_phoneSecurity`.
- Véhicules : `fn_vehicleTerminal` (tablette de bord, réglage serveur « Tablette des véhicules », objet `COMSPEC_ATAK_VehicleKit`), `fn_vehicleGateway` (passerelle, PC mobile ; portées par mission `COMSPEC_ATAK_GwRange = [4000, 10000]`).

## Socle de recharge CS-10

- Objet `COMSPEC_ATAK_ChargingDock` (Eden, Zeus) ou kit `COMSPEC_ATAK_DockKit` à poser (ACE Équipement). Dix emplacements : 1 à 5 en bas de gauche à droite vu de face, 6 à 10 sur le gradin.
- Logique : `functions/dock` (`fn_dockServer` état, recharge et mémoire serveur ; `fn_dockVisual` textures ; `fn_dockClient` et `fn_dockMenu` actions). Mémoire : `profileNamespace` du serveur, `COMSPEC_ATAK_DockDb_v1`.
- Modèle `data/charging_dock.p3d` : `tools/model_charging_dock.py` (Blender, Arma 3 Object Builder ; reprend le maillage du téléphone du gilet), images 2D par `tools/gen_dock_assets.py`. Sélections `phone1..10`, `screen1..10`, `led1..10`.

## Données à extraire (mission makers)

- **Eden** : module **COMSPEC — ATAK · Données > Données à extraire (câble ATAK)** synchronisé à un ou plusieurs objets (sans objet : le support choisi est créé à l'emplacement du module). **Ajouter un fichier** pour compléter.
- **Zeus** : mêmes modules, lâchés sur l'objet ; un formulaire s'ouvre. **Remettre / retirer les données** remet un support à zéro (essais, verrou, effacement).
- **Script** (champ init d'un objet) :
  `[this, [["name", "PC du QG"], ["password", "4471"], ["files", "ordre.txt | texte" + endl + "Repli à 04h00" + endl + "---" + endl + "cache | coord" + endl + "045123"]]] call comspec_atak_native_fnc_dataSetup;`
- Fichiers : un bloc par fichier, séparés par `---`, première ligne `nom | type`. Types : `texte`, `document`, `identifiants` (le mot de passe d'un autre poste, pour enchaîner), `coord` (carroyage ou `x, y`), `image` (chemin dans la mission), `audio` (classe CfgSounds ou chemin).
- Réglages : écran, organisation, couleur, image, mot de passe (diffusé en empreinte seulement), indice, essais, sanction (verrou, effacement, alarme), forçage et sa durée, durée du transfert, port (USB-C, RJ45, série), alarme (branchement, mauvais mot de passe, fin), usage unique, code serveur en fin.
- Événement CBA global en fin de téléchargement : `comspec_atak_native_dataDownloaded` `[objet, joueur, noms des fichiers]`.
- Réglages serveur **COMSPEC > ATAK · Extraction de données** : câble DL-7 obligatoire (objet « Câble de données DL-7 »), longueur du câble, vitesse des transferts.

## Capture de la carte (fond photo à niveaux de détail)

Comme Plans ou Google Maps : une vue satellite de toute la carte, bâtiments et arbres compris, de plus en plus fine quand on zoome.

1. **En jeu** (solo ou éditeur, Zeus avec ZEN) : module **COMSPEC ATAK > Capture de la carte**, rendu « Satellite », plusieurs hauteurs (ex. `1200` pour toute la carte puis `400` autour d'une ville). Ou console : `["start", ["sat", [1200], [], 0]] call comspec_atak_native_fnc_mapCapture;` (carte entière) puis `["start", ["sat", [400], getPosATL player, 1500]] call comspec_atak_native_fnc_mapCapture;`. Ciel dégagé à midi et unités cachées par défaut, Échap arrête. Avant une grande carte, relever `maxScreenShotFolderSizeMB` dans `Documents\Arma 3\Arma3.cfg` (une image pèse 3 à 5 Mo ; Altis à 1200 m : environ 800 images, 3 Go).
2. **Assemblage** (Python, `pip install pillow numpy`) : `python tools/map_stitch.py "%USERPROFILE%\Documents\Arma 3\Screenshots" sortie --pyramid --no-mosaic`. Le journal Arma (`%LOCALAPPDATA%\Arma 3\*.rpt`, lignes `CMAPL` / `CMAPS`) sert à redresser chaque image sur le relief ; garder le RPT de la capture. Sortie : `sortie/<carte>_sat/meta.json` et `z/x/y.webp`.
3. **Site** : ajouter `--upload https://athena.ttrd.fr/public --key <X_COMSPEC_KEY du .env>` ; le calque **Photo COMSPEC** apparaît dans les fonds de la carte ATAK web (`storage/map-photo/<carte>/sat`). Une zone capturée moins finement est découpée dans le niveau au-dessus, le zoom reste photo partout.
4. **Téléphone** : ajouter `--addon mods` (HEMTT 1.15 ou plus dans le PATH ou la variable `HEMTT` pour des PAA, sinon JPG) : `mods/@COMSPEC_ATAK_Photo/addons/comspec_atak_photo_<carte>.pbo`, mod optionnel à charger avec `@COMSPEC_ATAK_Native`. Le fond **PHOTO** apparaît alors dans Réglages > Carte et CALQUES (`fn_photoMap`). Taille limitée par `--addon-max-tiles` (12000 tuiles, environ 250 Mo). Si les tuiles se chevauchent ou laissent des joints : `profileNamespace setVariable ["COMSPEC_ATAK_PhotoPx", 1.0]` (facteur de taille) puis le signaler.

Rendus « 3D oblique » et « Plan » : mosaïque par hauteur (sans `--no-mosaic`) ; le plan entre aussi dans la pyramide (`<carte>_plan`, calque **Plan COMSPEC** du site).

## Ressources graphiques

`tools/gen_assets.py` convertit la coque fournie (`tools/src/android_s7_ca.png`, portrait obtenu par rotation) et dessine les icônes (SVG → PNG → PAA). Il demande Pillow, CairoSVG et HEMTT :
`python3 tools/gen_assets.py /chemin/vers/hemtt` régénère `Sources/addons/main/data/*.paa`.

## Build

- Windows avec Arma 3 Tools et le SDK .NET 8 : `build_mod.bat` (DLL + PBO).
- Sans Arma 3 Tools (Linux ou Windows) : `hemtt build` depuis `Sources/`, puis copier
  `Sources/.hemttout/build/addons/comspec_atak_native_main.pbo` vers `@COMSPEC_ATAK_Native/addons/main.pbo`.
  Les pylônes relais 3D sont dans un PBO à part : `comspec_atak_native_towers.pbo` vers `@COMSPEC_ATAK_Native/addons/towers.pbo`
  (modèles et textures : `tools/gen_tower_textures.py`, `tools/model_relay_towers.py`).
  Les objets d'inventaire 3D aussi : `comspec_atak_native_items.pbo` vers `@COMSPEC_ATAK_Native/addons/items.pbo`
  (`tools/gen_item_textures.py`, `tools/model_items.py`).
  `hemtt check` vérifie la config et le SQF.

## Langue (français / anglais)

- Réglage CBA **COMSPEC > Langue / Language**, tout en haut de la liste COMSPEC (onglet Client, par joueur) : English (défaut depuis 2.1.0), Français, les langues des mods de traduction, ou Auto (langue du jeu). Un joueur qui avait déjà choisi une langue la garde. Aussi dans le téléphone (Réglages > Langue / Language). Il vaut pour tout le mod : téléphone, menus CBA COMSPEC (ATAK, Overwatch, SSE) et écrans du SSE. Changer en cours de partie redessine la page ouverte et retraduit les menus CBA dès leur prochaine ouverture, sans relancer la mission.
- Le code reste écrit en français. Les textes affichés (`ctrlSetText`, `ctrlSetStructuredText`, `ctrlSetTooltip`, `lbAdd`, `hint`…) passent par `comspec_atak_native_fnc_tr`, qui les traduit avec le dictionnaire `Sources/addons/main/i18n/en.txt` (`[["français", "anglais"], ...]`). Les modèles à `%1`, `%2` traduisent aussi les textes formatés. Texte inconnu : reste en français. Les champs de saisie ne sont jamais traduits (`// i18n:brut`).
- Après un ajout de texte ou un rebase : `python3 tools/i18n.py wrap` (fait passer les nouveaux affichages par `fnc_tr`, sans effet sur ce qui l'est déjà), puis `python3 tools/i18n.py extract` liste dans `tools/i18n/missing.tsv` les textes sans traduction ; une fois traduits (`français<TAB>anglais`), `python3 tools/i18n.py merge fichier.tsv`.
- Autres langues : des mods de traduction communautaires (Workshop) déclarent leur langue dans `CfgComspecLanguages` ; elle apparaît dans le réglage, et toute phrase non traduite s'affiche en anglais. Guide et modèle : [`localisation/`](localisation/README.md). Après un `merge` dans en.txt : `python3 tools/i18n.py community` régénère le modèle.
- Menu CBA traduit au chargement (`fn_addSetting`). Noms d'objets et modules Eden/Zeus : `stringtable.xml` (langue du jeu), régénéré par `python3 tools/i18n.py stringtable` après un ajout dans `config.cpp` ou `modules.hpp` (écrire le texte en français, l'outil le remplace par une clé `$STR_`).
- Français : le code est en français, mais quelques écrans d'Overwatch connect sont écrits en anglais et les noms lus dans la config (objets, guide SSE) suivent la langue d'Arma. `i18n/fr.txt` (anglais -> français) les traduit : `python3 tools/i18n.py fr` l'assemble depuis `tools/i18n_fr_source.tsv` et les stringtables.
- Changement de langue en cours de partie : téléphone, écrans, menus CBA COMSPEC (à leur prochaine ouverture) et actions ACE suivent tout de suite. Les modules et menus Zeus (ZEN) suivent au prochain lancement de mission. Les noms affichés par le moteur lui-même (éditeur Eden, arsenal, inventaire) suivent toujours la langue d'Arma (stringtable), pas ce réglage.
- Overwatch connect embarqué : ses modules Eden/Zeus et objets (`config.cpp`, `modules/`) passent aussi par une `stringtable.xml` (`python3 tools/i18n.py --connect stringtable`, puis `tools/pbo_patch.py` sur comspec_overwatch_connect.pbo). Les journaux techniques restent en français et ne sont jamais extraits.
- Addon Overwatch connect embarqué et mod SSE : mêmes commandes avec `--connect` ou `--sse` (réglages CBA via `fn_addSetting` -> `fn_trMenu` de l'ATAK natif) (`python3 tools/i18n.py --connect wrap`, puis `extract` -> `tools/i18n/missing_connect.tsv`) ; même dictionnaire. Ses fenêtres traduisent leurs textes fixes au chargement (`fn_trDisplay`). Actions ACE, menu ZEN et modules Zeus ZEN passent par `fn_aceAction`, `fn_zenAction`, `fn_zenModule` (posés par `wrap`).
- Commandes du tchat en anglais acceptées aussi : `/help`, `/priority`, `/order`, `@grid`, `@time`, `@hdg`.

## Envoyer ses logs

Dans le dossier du mod, double-cliquez sur `Envoyer_mes_logs.bat`. Il prépare `COMSPEC_logs_<date>.zip` sur le Bureau avec les deux derniers `.rpt` d'Arma (`%LOCALAPPDATA%\Arma 3`) et les deux derniers journaux COMSPEC Link (`%LOCALAPPDATA%\Arma 3\COMSPEC\logs`). Les mots de passe, jetons et liens de webhook sont masqués dans la copie. Il reste à glisser le .zip dans un sujet bugs ou help du Discord.
