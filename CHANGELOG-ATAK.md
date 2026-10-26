# CHANGELOG - Features ATAK

Toutes les modifications notables des features ATAK sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Règle : chaque version a un « Résumé joueur » (annonce Discord)

À chaque fusion sur main, l'action « Discord changelog » poste dans le salon releases une carte écrite pour les **joueurs**. Elle ne publie **que** le bloc « Résumé joueur » de l'entrée dont le titre contient la version du titre de la PR (sinon une section « Résumé joueur » dans la description de la PR). Le détail technique n'est jamais posté. **Sans résumé, rien n'est annoncé.**

Toute PR qui livre une version (mod ATAK natif, COMSPEC Link, ou nouveauté visible du site) ajoute ce bloc juste sous le titre de son entrée :

```
### Résumé joueur
Type : correctif
FR : L'adresse du portail Athena ne se règle plus
- Le téléphone se connecte toujours au portail officiel : il n'y a plus d'adresse à saisir.
- Si tu avais enregistré une autre adresse, elle est effacée automatiquement.
EN : The Athena portal address is no longer a setting
- The phone always connects to the official portal: there is no address to type any more.
- If you had saved another address, it is cleared automatically.

### Détail technique
- (le détail pour les développeurs, comme avant)
```

- **Type** : `nouveauté`, `correctif`, `performance` ou `patch` (choisit l'emoji de la carte).
- **FR / EN** : un titre court en clair, puis 2 à 4 puces. Mêmes informations dans les deux langues.
- Écrire pour un joueur : des phrases complètes qui disent ce qui change pour lui, avec le nom des apps et des menus tels qu'il les voit. Jamais de nom de fonction, de variable, de fichier, de clé d'API ou de secret, pas de numéro de PR, pas de lien, pas de « recompiler la DLL ».
- Le titre de l'entrée garde la version (« (ATAK natif 1.72.0) », « (COMSPEC Link 2.0.80) », « site ») : la carte en tire le champ Version.
- Aperçu avant de fusionner : `python3 .github/scripts/discord_changelog.py --preview 1.72.0` (les termes techniques repérés sont signalés).
- Pour ne rien annoncer : label `discord:skip` ou `[skip discord]` dans le titre de la PR.

Journal développeur (style Bohemia) : [SPOTREP #00003](docs/dev/SPOTREP-00003.md) · [TECHREP #00003](docs/dev/TECHREP-00003.md).

Changelog Steam (copier-coller) : [Overwatch 1.6.12](docs/dev/STEAM-CHANGELOG-2026-09-22-overwatch-1.6.12.md) · [Overwatch 1.6.9](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.9.md) · [Overwatch 1.6.8](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.8.md) · [Overwatch 1.6.7](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.7.md) · [Overwatch 1.6.3](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.3.md) · [Overwatch 1.5.78](docs/dev/STEAM-CHANGELOG-2026-09-15-overwatch-1.5.78.md) · [Overwatch 1.5.77](docs/dev/STEAM-CHANGELOG-2026-09-14-overwatch-1.5.77.md) · [Overwatch 1.5.76](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.76.md) · [Overwatch 1.5.75](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.75.md) · [Overwatch 1.5.74](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.74.md) · [Overwatch 1.5.73](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.73.md) · [Overwatch 1.5.72](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.72.md) · [Overwatch 1.5.71](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.71.md) · [Overwatch 1.5.70](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.70.md) · [Overwatch 1.5.69](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.69.md) · [Overwatch 1.5.68](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.68.md) · [Overwatch 1.5.36](docs/dev/STEAM-CHANGELOG-2026-09-11-overwatch-1.5.36.md).

---

## GPS en bâtiment moins imprécis (ATAK natif, prochaine version)

### Résumé joueur
Type : correctif
FR : Le GPS reste précis à l'intérieur des bâtiments
- Dans un bâtiment, ton point sur la carte reste à quelques mètres de ta vraie position (avant : jusqu'à 40 m et plus).
- Ta vraie position est toujours dans le cercle de précision dessiné autour de ton point.
- Sans satellites (sous-sol), la position par le réseau est plus proche : 15 à 60 m selon le signal.
- Les sauts de position du GPS sont plus courts.
EN : GPS stays accurate inside buildings
- Inside a building, your dot on the map stays a few metres from your real position (before: 40 m or more).
- Your real position is always inside the accuracy circle drawn around your dot.
- Without satellites (basement), the network position is closer: 15 to 60 m depending on signal.
- GPS position jumps are shorter.

### Détail technique
- `fn_gpsFix` : bâtiment = 5 à 8 satellites (fix gardé) au lieu de 2 à 6, reflets 3 à 10 au lieu de 12 à 35 ; erreurs de base resserrées (7+ satellites 4-6 m, 5-6 : 6-9 m, 4 : 9-13 m). Le test « rue étroite » (8 rayons à 60 m) et la forêt ne s'appliquent plus à l'intérieur : mes propres murs le déclenchaient (−5 satellites, +32 m), d'où la perte du fix et le point à ~40 m. Dérive du point bornée à 0,5 × rayon annoncé (0,8 avant). Position réseau : 60 → 15 m (1 à 4 barres), décalage 0,35 × rayon (0,6). Sauts multitrajets 6-20 m (15-60).
- `fn_ewDraw` : le cercle de précision est centré sur la position de mon icône de la même image (`COMSPEC_ATAK_SelfGpsPos`).
## Nouveau téléphone : iPhone 17 Pro en coque durcie olive (ATAK natif 2.4.2.AB - Denver)

### Résumé joueur
Type : nouveauté
FR : Un iPhone 17 Pro rejoint les téléphones ATAK
- Nouveau modèle dans Réglages > Personnaliser : iPhone 17 Pro en coque durcie olive, sur support de gilet.
- L'ATAK ne passe jamais sous la Dynamic Island.
- Le dos et le téléphone porté sur le gilet suivent le modèle.
- Le Durci S7 reste le défaut.
EN : An iPhone 17 Pro joins the ATAK phones
- New model in Settings > Customize: iPhone 17 Pro in an olive rugged case, on a vest mount.
- The ATAK never sits under the Dynamic Island.
- The back and the phone worn on the vest follow the model.
- The Rugged S7 stays the default.

### Détail technique
Version provisoire : le lot natif suivant la renumérote (STOP MERGE, regroupée avec les autres PR natives).

- Modèle `ip17` (profil `COMSPEC_ATAK_PhoneModel`), image fournie par TTRD le 2026-10-26 (`tools/src/ip17_photo.png`). `tools/cut_ip17.py` la détoure (fond noir relié aux bords, seuil bas pour garder le support JPC noir), ouvre l'écran à droite de la Dynamic Island (zone sûre d'iOS en paysage : l'île et le verre qui l'entoure restent sur la coque) et dessine le dos (miroir de la face, dos moulé olive, fenêtre sur le plateau photo bleu intense : trois objectifs en triangle, flash, LiDAR ; aucun logo).
- `frame_photos.py` (FRAMES, BACKS) : écran paysage `[0.2221, 0.3801, 0.7553, 0.6201]`, boîte visible `[0.1167, 0.3154, 0.8755, 0.7866]` dans `fn_layoutGet`. Coques jour/nuit, portrait/paysage, dos (`gen_assets.py <hemtt> ip17`), poussière de coque (`gen_dirt.py <hemtt> case ip17`).
- Téléphone porté ouvert sur le JPC et selfie : `COMSPEC_ATAK_JpcPhone_ip17` reprend `jpc_iphone.p3d` (même coque et même support que le V2 Enhanced) avec l'atlas `jpc_ip17_ca`, recalé pour que l'ouverture de l'écran tombe sur l'écran du modèle prêté (`gen_assets.JPC_SHARED`, étiré de 8 % en hauteur) ; texture d'écran 1024 x 496 comme le V2.
- Listes des modèles : Réglages > Personnaliser (`fn_pageSettings`), réglage CBA « Modèle du téléphone » et « Item requis : iPhone 17 Pro » (`XEH_preInit`), dos et Accessoires (`fn_backView`, `fn_pageAccessories` ; le dos du S23 affiche aussi son nom, il était vide), antenne SMA décalée sur le bord de la coque (`fn_antenna`), JPC (`fn_jpcScreen`), selfie (`fn_gestureSelfie`). Libellé anglais « Rugged iPhone 17 Pro ».
- Site : la carte Discord des photos rapides nomme aussi les modèles `s23` et `ip17`.
- `tools/paa_dxt.py` : mipmaps compressés en LZO1X comme HEMTT quand le module python-lzo est présent (2048 x 2048 : 5,6 Mo → 0,2 à 1 Mo). Les PAA de ce modèle en viennent (HEMTT non lancé dans ce fil).
- main.pbo **pas reconstruit** dans cette PR : il le sera au lot natif (HEMTT ou `build_mod.bat`). Test PHP : les PAA de l'iPhone 17 Pro (coques, dos) existent.

## Correctif de sécurité : liaison Athena, relais ATAK, Windows en français (ATAK natif 2.4.1.AB - Denver, COMSPEC Link 2.0.96)

### Résumé joueur
Type : correctif
FR : Mise à jour de sécurité obligatoire
- Deux failles corrigées (liaison Athena et relais ATAK).
- Météo radio et relèvements SIGINT réparés sous Windows en français.
- Un refus d'Athena sur une seule app ne coupe plus toute la liaison.
- Athena refusera bientôt les versions précédentes du mod.
EN : Mandatory security update
- Two security holes fixed (Athena link and ATAK relays).
- Radio weather and SIGINT bearings fixed on Windows set to French.
- If Athena refuses one app, the rest of the link keeps working.
- Athena will soon refuse older versions of the mod.

### Détail technique
Correctif seul de la 2.4.0 publiée (audit d'ingénierie, SEC-001, DLL-003, DLL-007 ; revue du mod, tour 4, pour les relais). Versions : ATAK natif 2.4.1, COMSPEC Link 2.0.96, Overwatch connect 1.6.19 (la 1.6.18, écusson Donateur, déjà sur main, sort avec cette version).

- SEC-001 (DLL Link, critique) : `AttachApiKeyHeader` attachait la clé de la communauté, le jeton de session et le SteamID à toute adresse fournie par le SQF (`Connect`, `PairStart`, `RedeemGameLink`, `LinkBySteam`…, URL absolue de `DownloadBriefingSlideImage`, variable publique `COMSPEC_ATAK_ScreenCast`), redirections comprises. Désormais aucun secret ne part hors des deux hôtes Athena de confiance (`athena.ttrd.fr`, `athena-comspec.eu`, sans sous-domaine ni identifiants), en HTTPS seulement : un seul point de contrôle pose l'adresse du portail (`ERR|untrusted_host` sinon), les deux sites de GameAuth qui posent le Bearer du compte refusent aussi un hôte non sûr, les clients porteurs de secrets ne suivent plus les redirections (les images les suivent à la main, secrets refiltrés à chaque saut), et un envoi de la file disque vers un autre hôte est abandonné, compté et signalé au journal de liaison du téléphone (sans déclencher l'arrêt du relevé de terrain, le renvoi de la météo ni la pause des flux vidéo que le journal attache à ces chemins). `LatestVersions`, `StartupCheck` et `Server.PublicIp` ne contactent plus un hôte arbitraire. `fn_connect` affiche le refus dans le téléphone. Un Athena local de développement demande `COMSPEC_DEV_TRUST_LOOPBACK=1` dans l'environnement d'Arma (voir `docs/migration-domaine-athena-comspec.md`). Banc isolé `tools/sec001-bench` (aucun réseau ; adresses piégées comparées à une table d'acceptations attendues, indépendante de la DLL) : 42 vérifications passent sur la DLL corrigée, 19 échouent sur la 2.4.0 (fuites et détournements).
- Relais (exécution de code à distance) : `fn_relayCond` compilait sur chaque poste la variable publique `COMSPEC_AtakRelayCond`, que tout client peut écrire. La condition en code n'est plus évaluée que par le serveur, depuis sa copie locale `COMSPEC_AtakRelayCondSrv`, et le résultat est publié dans `COMSPEC_AtakRelayCondOk`. **Rupture pour les missions** : une condition en code posée par script s'écrit maintenant côté serveur dans `COMSPEC_AtakRelayCondSrv` ; les préréglages (`mast`, `stopped`, `engineoff`) et le module « ATAK · Relais » posé dans Eden ne changent pas.
- DLL-003 : nombres lus et écrits en culture invariante (`NumberStyles.Float`, `CultureInfo.InvariantCulture`) : sur un Windows en français, la météo radio échouait ou rendait « 0,89 » et le gisement SIGINT était perdu.
- DLL-007 (régression 2.4.0) : un 403 « règle » sur une seule route coupait toute la liaison. Il ne verrouille plus que cette route (30 min), après vérification de `client-init` ; le verrou global ne tombe que si `client-init` refuse aussi.
- Côté site (PR séparée) : Athena peut refuser les DLL Link antérieures à 2.0.96 (`MOD_OUTDATED`, « mettez le mod à jour »). Le plancher reste inactif jusqu'à la publication Steam de cette version.
- DLL native (`COMSPECATAKNativeExtension.csproj`) : version passée de `1.0.0-native` à `2.0.96-native`. Elle est compilée depuis le même `Extension.cs` que COMSPEC Link et c'est ce numéro qu'elle annonce dans son `User-Agent` : laissée à 1.0.0, la DLL native **corrigée** aurait été refusée par le plancher de version ci-dessus. Aucun effet en jeu : le contrôle d'installation ne compare la version de la DLL au `main.pbo` que lorsque COMSPEC Link est présent, et la version de mod envoyée à Athena (`1.0.0-native`, hors du contrôle de version minimale de communauté) n'est pas touchée.
- Sortie : vérifier d'abord qu'aucune route `/public/api/…` (adresse appelée par les mods), `/api/…` ni `/map-photo/…` des deux domaines ne redirige (en GET : Athena répond 404 à HEAD, donc pas `curl -I` ; attendu 200, ni 301, 302, 307 ni 308, la DLL ne suivant plus ces redirections ; commande dans `docs/migration-domaine-athena-comspec.md`) ; recompiler les deux DLL sous Windows (`build_mod.bat`) et les committer (les copies du dépôt sont périmées), reconstruire main.pbo et le PBO connect, mettre à jour le pack S.O.A.R.
- Connu, hors de ce correctif (lots séparés) : la clé de la communauté reste rangée dans le profil Arma, lisible par une mission (SEC-009) ; une mission peut toujours faire télécharger une image depuis l'hôte de son choix (adresse IP du joueur visible par cet hôte, aucun secret envoyé).

---
## App Médical : dossiers médicaux de l'unité dans le téléphone (à regrouper, COMSPEC Link 2.0.96)

### Résumé joueur
Type : nouveauté
FR : Les dossiers médicaux de l'unité dans l'app Médical
- Nouvel onglet DOSSIERS : groupe sanguin, poids, taille, allergies et intolérances de chaque membre, tels que saisis sur Athena.
- Les joueurs de la partie d'abord (EN JEU), ou toute l'unité, avec une recherche par indicatif ou fonction ; MONITEUR ouvre le suivi du blessé.
- La liste est gardée sur ton PC : elle s'affiche tout de suite, même hors ligne, et ACTUALISER la relit sur Athena.
- Réservé aux médecins (fonction médicale sur Athena), à la gestion et au pilotage des effectifs. Le groupe sanguin et le poids apparaissent aussi sur les alertes et le moniteur.
EN : The unit's medical records in the Medical app
- New RECORDS tab: blood type, weight, height, allergies and drug intolerances of every member, as entered on Athena.
- Players in the mission first (IN GAME), or the whole unit, with a search by callsign or job; MONITOR opens the casualty's tracking.
- The list is kept on your PC: it shows instantly, even offline, and REFRESH reads it again from Athena.
- Restricted to medics (medical job on Athena), community management and personnel staff. Blood type and weight also show on alerts and on the monitor.

### Détail technique
- Site : `POST /api/atak/medical-roster` (PR site séparée) : membres actifs de la communauté de la session, session de jeu du joueur exigée ; droit = admin/gestion, `EffectifsLmsAccess`, ou emploi médical (`AtakMedicalRosterService::isMedicalJob`).
- COMSPEC Link 2.0.96 : `MedicalRoster` [ligne, cache|refresh] (pages comme `ListWardrobes`) et `MedicalRosterInfo` ; copie dans `Documents\Arma 3 - COMSPEC\Sync\medical_roster.json`, propre au site + communauté + Steam ; un 403 `forbidden` efface la copie. Ajouté au projet de la DLL native.
- ATAK natif : `fn_medRoster` (lecture paginée, cache, recherche, portée EN JEU / TOUTE L'UNITÉ), onglet DOSSIERS dans `fn_pageMedical`, `_medIdent` complète groupe sanguin et poids depuis le dossier. Debug > DLL vérifie `MedicalRoster`.
- Numéro ATAK natif attribué par le regroupement (docs/VERSIONING.md).
## CR de fin d'opération imposé dans le téléphone, à remplir ou à lire (ATAK natif : numéro donné au regroupement)

### Résumé joueur
Type : nouveauté
FR : Le compte rendu de fin d'opération s'ouvre dans ton téléphone
- Nouvelle app CR fin d'op : quand le commandement le demande, la fiche s'ouvre d'elle-même dans le téléphone, à remplir ou simplement à lire.
- Tant qu'elle n'est pas remise, elle revient toutes les deux minutes ; rien ne s'affiche jamais sur l'écran de jeu.
- Zeus, admins et gestionnaires l'imposent depuis l'app (onglet IMPOSER) ou le menu Zeus, et voient qui l'a ouverte et remise.
- Sur le site, le back-office suit chaque joueur : CR remis, dernière synchro de son ATAK, remontées de la partie.
EN : The end-of-operation report opens on your phone
- New End-of-op report app: when command asks for it, the form opens by itself on your phone, to fill in or simply to read.
- Until you submit it, it comes back every two minutes; nothing ever shows on the game screen.
- Zeus, admins and managers require it from the app (REQUIRE tab) or the Zeus menu, and see who opened and submitted it.
- On the website, the back-office tracks every player: report submitted, last sync of their ATAK, reports sent during the game.

### Détail technique
- Nouvelle app `DEBRIEF` (« CR fin d'op », section Mission) : `functions/debrief/fn_debrief.sqf` (client), `fn_debriefServer.sqf` (droits, envoi, suivi), `fn_pageDebrief.sqf` (fiche et panneau IMPOSER). Pastille au lanceur tant qu'un CR n'est pas remis.
- Demande du site : `fn_adminNotices` annonce `caps:"debrief"` à `AdminControl.Poll` ; un avis de genre `debrief` porte la fiche `["DBF1", mode, titre, intro, texte, questions]` lue par `parseSimpleArray` (méta `debrief=<id>;mode=…`), validé par `AdminControl.Ack` à la remise ou à la lecture. La ligne `debrief_cfg` donne le questionnaire par défaut, le droit d'imposer (gestionnaires désignés sur Athena, variable publique `COMSPEC_ATAK_DebriefRight`) et l'autorisation Zeus du site.
- Demande en jeu : événement serveur `comspec_atak_native_debrief` (`force`, `remind`, `lift`, `pull`, `state`, `hello`), joueurs visés tous / mon camp / mon groupe / sélection Zeus, arrivée en cours de partie servie (`hello`). Droits : `fn_adminAllowed` (Zeus, hôte, admin, `comspec_atak_native_admin_uids`), Zeus seul selon le nouveau réglage CBA `comspec_atak_native_debrief_zeus`.
- Retours à Athena par `SubmitTacticalReport` (type `DEBRIEF`, `structured_data.debrief_event` = forced / opened / read / submitted, réponses `a_<id>`), seulement si le téléphone est relié ; le mod marche sans Athena (suivi en jeu par le serveur).
- Menu Zeus « ATAK · Contrôle Zeus » : groupe « Fin d'opération » (imposer à remplir, imposer à lire avec texte, lever).
- Aucun changement de COMSPEC Link ni de la DLL. Traductions EN ajoutées (`i18n/en.txt`). `hemtt check` : aucune erreur. main.pbo à reconstruire au regroupement.
- Site : back-office « CR des joueurs (fin d'op) » (PR site séparée).

---
## Écrans TOC persistants, PC du poste de commandement et IA équipées d'un ATAK (ATAK natif, prochain regroupement)

### Résumé joueur
Type : nouveauté
FR : Des écrans TOC en jeu et des IA vraiment reliées au réseau ATAK
- Nouveau module Écran TOC (Eden et Zeus) : une TV ou un grand écran du jeu affiche en direct le réseau ATAK de ton camp, avec la carte, les positions des équipiers, les marqueurs et les derniers comptes rendus et messages.
- Le poste TOC ouvre maintenant l'écran du PC portable lui-même, sans passer par le téléphone : la vraie carte en grand avec les marqueurs, les groupes du camp, le flux des comptes rendus, le rédacteur d'ordre (clic droit sur la carte pour poser l'objectif) et les messages au camp.
- Devant l'écran, ACE ou la molette : carte et flux, carte seule, flux seul, ou « PIP du poste » où l'écran recopie ce que l'opérateur a sous les yeux sur le PC.
- Les écrans TOC sont gardés d'une partie à l'autre sur le même serveur.
- Un avis d'Athena validé avec COMPRIS (dont la levée de compromission) ne revient plus à chaque partie.
- Les missions peuvent obliger les IA à porter un ATAK : elles apparaissent sur le BFT comme un joueur, avec une position qui se met à jour au rythme du réseau, une erreur GPS réaliste, et se perdent près d'un brouilleur.
EN : In-game TOC screens and AI really linked to the ATAK network
- New TOC screen module (Eden and Zeus): a TV or large in-game screen shows your side's ATAK network live, with the map, teammates' positions, markers and the latest reports and messages.
- The TOC post now opens the laptop's own screen, with no phone involved: the real map in full size with markers, the side's groups, the report feed, an order composer (right click on the map to set the objective) and messages to the side.
- In front of the screen, with ACE or the scroll wheel: map and feed, map only, feed only, or "Post PIP", where the screen mirrors what the operator has in front of them on the laptop.
- TOC screens are kept from one session to the next on the same server.
- An Athena notice acknowledged with GOT IT (including a lifted compromise) no longer comes back every session.
- Missions can force AI to carry an ATAK: they show on the BFT like a player, with a position updated at the network rate, a realistic GPS error, and they drop out near a jammer.

### Détail technique
- `functions/toc/fn_tocScreen.sqf` : registre serveur `COMSPEC_ATAK_TocScreens`, état public `COMSPEC_ATAK_TocScr` par objet, texture UI `#(rgb,1600,900,1)ui("COMSPEC_RscTocScreenTexture", …)` posée en local et rafraîchie toutes les 2 s (écran proche et visible). Fond : tuiles du mod @COMSPEC_ATAK_Photo (`fn_photoMap` "info"), sinon `pictureMap` du terrain ; quadrillage, marqueurs ICON, C.R. géolocalisés (20 min), nœuds en symboles OTAN. Actions ACE / molette ; « Carte en grand » = `fn_tocPost` "open" puis page MAP (rien par-dessus l'écran du jeu).
- `fn_tocFeed.sqf` (serveur, 3 s) : `COMSPEC_ATAK_TocFeed_<camp>` = [serverTime, nœuds, flux, état], seulement pour les camps qui ont un écran. Nœuds = porteurs d'un ATAK (`fn_hasDevice`), point PLI et fraîcheur comme `fn_localDataRefresh`, équipage d'un véhicule regroupé. Flux = C.R., alertes, MEDEVAC, BDA, messages du poste TOC (30 par camp).
- `fn_tocPersist.sqf` : profil du serveur, clé `COMSPEC_ATAK_TocSave_<carte>_<mission>`, reprise 3 s après le départ, écriture regroupée (`fn_saveProfile`) ; réglage CBA `comspec_atak_native_toc_persist`.
- `fn_moduleTocScreen.sqf` + module `COMSPEC_ATAK_Module_TocScreen` (objets synchronisés, ou pose d'un écran du jeu). `fn_tocPost` : un écran TOC sert de PC de TOC à 4,5 m.
- `functions/core/fn_aiAtak.sqf` : réglages `comspec_atak_native_ai_atak` (off / leaders / all), `comspec_atak_native_ai_realism` (défaut vrai), `comspec_atak_native_ai_atak_item` ; variable `COMSPEC_ATAK_AiAtak` (vrai / faux) ; PLI des IA envoyé par le serveur (`COMSPEC_ATAK_Pli`, dérive GPS par IA, toit / forêt, brouilleurs comme `fn_ewEffects`). `fn_hasDevice` : variable IA prioritaire, IA réalistes = objet requis. `fn_localDataRefresh` : point serveur des IA utilisé comme celui d'un joueur. Module `COMSPEC_ATAK_Module_AiAtak`.
- `functions/toc/fn_tocLaptop.sqf` + display `COMSPEC_RscTocLaptop` (idd 88560) et contrôle `COMSPEC_RscMapToc` : écran du PC du poste, indépendant du téléphone (aucune vérification `fn_hasDevice`). Vraie carte Arma (`Draw` : nœuds, C.R., poste, objectif), onglets GROUPES / FLUX / ORDRES / CAMP, ordre par `fn_orderLocal` "send" (nouveaux paramètres : envoi direct par le serveur, position de l'objectif), message au camp ou à un chef par `comspec_atak_native_tocMsg` (nouveau 8e champ : destinataire). `fn_tocPost` "open" ouvre ce PC au lieu de l'ATAK du poste ; l'app Poste TOC du téléphone reste pour qui a un ATAK.
- PIP : le PC publie sur son opérateur `COMSPEC_ATAK_TocView` = [camp, centre, demi-largeur, onglet, sélection, indicatif, nom, ordre en cours, objectif] (au plus toutes les 2 s) ; l'affichage `TOC` de `fn_tocScreen` reprend ce cadrage, le groupe choisi et l'objectif, et revient à `SPLIT` sans opérateur. `fn_tocFeed` publie aussi pour les camps qui n'ont qu'un PC ouvert, et pousse les ordres du camp dans le flux.
- `fn_adminNotices` : avis vus et avis validés gardés dans le profil (`COMSPEC_ATAK_NoticeSeen`, `COMSPEC_ATAK_NoticeAck`, 200 derniers) ; un avis déjà validé n'est plus affiché ni listé et son COMPRIS repart vers Athena à chaque réception ; un avis déjà vu la partie précédente reprend son effet sans rejouer le bandeau. Corrige « je valide la levée de compromission, le message revient à chaque fois ».
- Traductions : 82 + 97 phrases dans `i18n/en.txt`, clés `STR_COMSPEC_ATAK_CFG_TOCSCR_*` / `AIATAK_*`, gabarits de traduction régénérés (`tools/i18n.py community`, `fr`).
- Non testé en jeu.

## Regroupement : app Admin et mode local dans le téléphone, écrans du briefing en jeu, montre Garmin, arsenal ACE sûr, nouvelle adresse athena-comspec.eu, jeu plus fluide (ATAK natif 2.4.0.AB - Denver, COMSPEC Link 2.0.95)

### Résumé joueur
Type : nouveauté
FR : Grosse mise à jour du téléphone ATAK
- Nouvelles apps : Admin (gestion et Zeus), Numéros et PC portable de TOC pour jouer sans Athena, montre Garmin au poignet (touche Montre, O), diapositives du Briefing sur les TV et projecteurs du jeu.
- Carte : indicatif perso, marqueurs nommés et modifiables avec leur heure de pose, recherche par grille, outils JTAC, réalisme GPS et brouillage d'écran.
- Arsenal ACE : « Tout basculer dans l'arsenal ACE » sans jamais effacer tes tenues, liste gardée sur ton PC.
- Moins de freezes, jeu plus fluide, centaines de corrections, téléphone traduit en anglais, et le site passe à l'adresse athena-comspec.eu.
EN : Big ATAK phone update
- New apps: Admin (management and Zeus), Numbers and a TOC laptop to play without Athena, a Garmin watch on your wrist (Watch key, O), Briefing slides on in-game TVs and projectors.
- Map: personal callsign, named and editable markers with the time they were placed, grid search, JTAC tools, GPS realism and screen jamming.
- ACE arsenal: "Send all to ACE arsenal" never wipes your loadouts any more, and the list is kept on your PC.
- Fewer freezes, smoother game, hundreds of fixes, phone translated into English, and the website moves to athena-comspec.eu.

### Détail technique
Un seul numéro pour tout le lot : ATAK natif 2.4.0 (après 2.3.10 sur main ; les numéros 2.3.11 réservés par certaines PR ne sont jamais sortis), COMSPEC Link 2.0.95 (après 2.0.93 sur main), Overwatch connect 1.6.17. Le détail de chaque partie est dans les entrées ci-dessous et dans les PR d'origine, fermées avec un lien vers ce regroupement.

- PR regroupées : #1029 (app Admin, parc Athena en jeu, debug IA), #1030 (logos ATHENA), #1032 (réalisme GPS, sang, dégâts et brouillage d'écran, montre Garmin, antennes du jeu en relais), #1033 (indicatif, marqueurs, recherche par grille, JTAC, jumelles), #1035 (app Athena : maintenance, /lier, QR code), #1036 (mode full local : PC de TOC, app Numéros, config.json), #1040 (écrans COMSPEC, diapositives sur les TV), #1044 (freezes, BIS_fnc_crewCount, fonctions connect), #1051 (marqueurs par le réseau ATAK, heure de pose, relais mât déployé), #1055 (images en PAA), #1057 (revue + optimisation, inclut #1034 et #1073 : refus d'Athena sans boucle, auto-test Dépannage liaison), #1064 (arsenal ACE), #1069 (revue multi-agents : 263 correctifs), #1072 (portail athena-comspec.eu), #1075 (retours des contributeurs : traductions natif, connect, SSE).
- Conflits résolus au regroupement : `fn_mapOnDraw` / `fn_mapLabels` (position GPS affichée), `config.cpp` (listes `units[]` et `CfgFunctions` réunies), `stringtable.xml` (clés des deux côtés), `XEH_postInitServer` (journal à deux plafonds de #1069 + nettoyage des accusés de #1057, `tocMsg` / `localOrder` de #1036), `fn_dataServer` / `fn_dataSetup` (code de fin gardé au serveur : version de #1057), sauvegardes du profil (`fn_profileSave` de #1057 pour les passages périodiques, `fn_saveProfile` de #1044 ailleurs, jamais `saveProfileNamespace` direct dans les fichiers fusionnés), arsenal connect (appel async de #1069 + cache local de #1064), `fn_zeusShowPlayerAtak` (journal du terminal, plus de hint par-dessus l'écran), `views/link/lier.php` (refonte déjà sur main gardée).
- Domaine : restes `athena.ttrd.fr` du natif passés à `athena-comspec.eu` (9-LINE du JTAC, commentaires), QR codes régénérés (`drone_remote_qr_ca`, `nineline_qr_ca`, `athena_link_qr_ca`). Overwatch connect et la DLL gardent `athena.ttrd.fr` (toujours servi).
- Build : `COMSPECATAKNativeExtension.csproj` liste maintenant `Extension_DataFlows.cs`, `Extension_RefusalLatch.cs`, `Extension_SelfTest.cs`, `Extension_WardrobeCache.cs` (la DLL de l'ATAK natif ne compilait plus avec #1057 + #1064). Les deux projets C# compilent (dotnet 8). main.pbo et towers.pbo reconstruits par HEMTT, comspec_overwatch_connect.pbo par `tools/pack_pbo.py`, `verify_build.py` OK. DLL Windows (NativeAOT) et PBO SSE à reconstruire sur le PC (`build_mod.bat`).
- Reporté au prochain regroupement : les 23 points restants de la revue (`/mnt/project-files/revue-mod/passe-2-corrections.md`, section « Reporté »).

---

## Moins de freezes, plus d'erreur BIS_fnc_crewCount en véhicule, fonctions connect réparées (ATAK natif : numéro donné au regroupement)

### Résumé joueur
Type : performance
FR : Moins de freezes et plus d'erreur en véhicule
- Le jeu ne se fige plus près d'une seconde à intervalles réguliers quand le mod enregistre tes réglages : l'enregistrement est regroupé (une fois par minute au plus, et à la fin de la mission).
- Le message d'erreur « BIS_fnc_crewCount » qui restait à l'écran tant qu'on était à bord d'un véhicule a disparu.
- Le bouton COMSPEC réapparaît dans l'arsenal ACE, et « Carte Athena » ouvre de nouveau le portail dans le navigateur du PC.
EN : Fewer freezes and no more error in vehicles
- The game no longer freezes for almost a second at regular intervals when the mod saves your settings: saving is grouped (at most once a minute, and at the end of the mission).
- The "BIS_fnc_crewCount" error that stayed on screen while you were inside a vehicle is gone.
- The COMSPEC button is back in the ACE arsenal, and "Athena map" opens the portal in your PC browser again.

### Détail technique
- **Freezes** (RPT d'un joueur Linux/Proton, ATAK 2.3.10) : lignes `[PERF] Image figée` de ~800 ms toutes les 2 à 20 s, dont une exactement à chaque étape du dépannage pas à pas (`fn_diagIsolateStart` fait `saveProfileNamespace` à chaque étape). `saveProfileNamespace` réécrit tout le fichier .vars : ~0,8 s sur ce PC. Nouvelles fonctions `comspec_atak_native_fnc_saveProfile` et `comspec_overwatch_connect_fnc_saveProfile` (même clé `COMSPEC_ProfileSavePending`) : une écriture par 60 s au plus via `CBA_fnc_waitAndExecute`, vidée sur `Ended`/`MPEnded` et à la fermeture de l'écran de jeu (display 46 `Unload`) ; `[true] call …` écrit tout de suite. Une écriture de plus de 100 ms est notée `[PERF] Écriture du profil : N ms`. Tous les `saveProfileNamespace;` du natif (sauf `XEH_preStart`) et du connect passent par elles (atak_athena gelé non touché).
- **BIS_fnc_crewCount** : `connect/functions/fn_updateVehicleTracking.sqf` lui passait un OBJET (la fonction attend un nom de classe) à chaque tick du suivi véhicule (2,5 s). Remplacé par `fullCrew [_vehicle, "", true]` : `crew_max` = toutes les places, `passenger_max` = places `cargo`.
- **Fonctions connect qui ne compilaient pas** (erreurs au lancement dans le RPT, fonctions vides en jeu) : `fn_intelNoteApplyGeometry` (`ctrlConfig` n'existe pas → `ctrlFontHeight`), `fn_collectBugReportLog` (`isNotEqualType` → `!(… isEqualType …)`), `fn_createChatChannel` (guillemets `\"` dans l'expression régulière → `""`), `fn_webBrowserOpenAthena` / `fn_webBrowserOpenSystem` (`openURL` n'existe pas → `OpenAthenaLink` de COMSPEC Link, portail seulement), `fn_zeusShowPlayerAtak` (`replaceString` → `splitString`/`joinString`).
- **Arsenal ACE** : `ace_arsenal_fnc_addRightPanelButton` recevait un texte en 4e paramètre (numéro de bouton attendu) : erreur `Params` et pas de bouton COMSPEC.
- **fn_tr** ligne 64 : `[_x, ""] select (isNil "_x")` plantait sur un argument absent (« Undefined variable _x »).
- Vérifié avec HEMTT 1.17.2 (`hemtt check` natif : 0 erreur ; connect : les 6 erreurs de syntaxe ont disparu, restent les faux positifs `ctrlWebBrowserAction` inconnus de HEMTT). main.pbo et `comspec_overwatch_connect.pbo` à reconstruire au regroupement.
## Marqueurs par le seul réseau ATAK, heure de pose sur chaque marqueur, relais actif seulement mât déployé (ATAK natif : numéro donné au regroupement)

### Résumé joueur
Type : nouveauté
FR : Marqueurs ATAK confidentiels et relais radio conditionnels
- Nouveau réglage serveur « Diffusion des marqueurs ATAK » : en « Réseau ATAK uniquement », un marqueur posé sur le téléphone n'apparaît plus sur la carte d'Arma des autres joueurs. Il n'arrive que sur les téléphones des joueurs du canal qui ont du réseau ; sans signal, il arrive quand le réseau revient.
- La fiche d'un marqueur (clic sur le marqueur dans l'app Carte) affiche l'heure de pose, depuis combien de temps il est posé et qui l'a posé, même pour les marqueurs posés avant ton arrivée sur le serveur.
- Module « ATAK · Relais » : nouveau choix « Actif seulement si » (mât ou antenne déployé, véhicule à l'arrêt, moteur coupé, ou condition écrite par le créateur de mission). Exemple : un camion radio ne relaie le réseau ATAK que mât dressé.
EN : Confidential ATAK markers and conditional radio relays
- New server setting "ATAK marker distribution": with "ATAK network only", a marker placed on the phone no longer shows on other players' Arma map. It only reaches the phones of players on that channel who have network; without signal, it arrives when the network returns.
- A marker's card (click the marker in the Map app) shows when it was placed, how long ago and who placed it, even for markers placed before you joined the server.
- "ATAK · Relay" module: new "Active only when" choice (mast or antenna deployed, vehicle stopped, engine off, or a condition written by the mission maker). Example: a radio truck only relays the ATAK network with its mast up.

### Détail technique
- Question d'un joueur (marqueurs ATAK visibles sur la carte vanilla par tout le canal, même sans ATAK ni réseau). Avant : les marqueurs du téléphone sont des marqueurs Arma `createMarker` sur le canal choisi (Global par défaut), donc visibles sur la carte d'Arma de tout le canal, sans condition de réseau.
- `fn_markerNet` (nouveau) : réglage serveur `comspec_atak_native_marker_net` (`arma` par défaut, `atak`). En `atak` : `createMarkerLocal` (même nom `_USER_DEFINED #…` : modifiable et effaçable comme avant), état envoyé par `fn_netSend` (file sans signal) à l'événement serveur `comspec_atak_native_tacMkSrv`, gardé par le serveur (500 au plus) et remis par `comspec_atak_native_tacMk` aux joueurs du canal (Global/Camp = camp, Commandement = chefs de groupe, Groupe, Véhicule = équipage, Direct = 50 m) ; réception retenue tant que `fn_p2pReachable` est faux, livrée au retour du signal (boucle de 2 s). Modifications via le crochet de `fn_markerWeb` (`touch`), suppressions via l'EH `MarkerDeleted` ; signature de l'état pour éviter les boucles. Les 4 poses (palette `fn_markerDrop`, trait `fn_markerStroke`, LZ `fn_mapToolRun`, éditeur `fn_markerEditSave`) passent par `["create", …]`. Synchro à l'arrivée (`sync`).
- Horodatage : la fiche du marqueur (`fn_pageMap`) affiche « Posé à HH:MM · il y a N min · INDICATIF » depuis `COMSPEC_ATAK_MarkerMeta` ; le serveur note heure et auteur des marqueurs Arma (EH `MarkerCreated`) et les renvoie aux joueurs qui rejoignent ; en mode réseau ATAK, heure et auteur de la pose voyagent avec le marqueur.
- `fn_relayCond` (nouveau) : `COMSPEC_AtakRelayCond` sur un relais ou une passerelle de véhicule (`mast` : sources d'animation dont le nom contient mast/antenna/antena/telescop/aerial/pole/rod au-delà de 90 % ; `stopped` ; `engineoff` ; ou code SQF, `_this` = l'objet). `fn_relayNet` liste le relais hors service avec la raison (« mât replié »…). Module « ATAK · Relais » : attributs Eden `Cond` + `CondCode`, liste « Actif seulement si » dans le formulaire Zeus.
- À tester en jeu : noms des sources d'animation du mât du GAZ-66 R-142 de RHS reconnus par `mast` (sinon condition en code) ; `createMarkerLocal` + `setMarkerText` global sur un marqueur local (doit rester local) ; marqueurs reçus hors réseau puis au retour du signal ; JIP.
- Réglage `comspec_atak_native_marker_channel` inchangé (Global par défaut) : en mode carte d'Arma, Global reste visible par tous les camps.
## Refus d'Athena : plus de demandes en boucle, Dépannage liaison qui donne la bonne correction (Overwatch connect 1.6.17)

### Résumé joueur
Type : correctif
FR : Quand Athena te refuse, le jeu arrête d'insister et te dit quoi faire
- Si Athena refuse la clé enregistrée dans ton profil (clé changée ou retirée), le Dépannage liaison le dit et propose Lier mon compte au lieu de Reconnecter, qui échouait à chaque fois.
- Si ton compte, ton Steam ou ton réseau est bloqué par ta communauté, il te dit de contacter un administrateur.
- Après un refus de ce genre, le téléphone n'envoie plus rien jusqu'à ce que tu refasses la liaison ou Reconnecter Athena : avant, il redemandait toutes les quelques secondes, pour rien.
- Une fonction absente de l'offre de ta communauté (ordres, tchat, zones…) n'est plus redemandée sans arrêt : un essai par demi-heure.
EN : When Athena refuses you, the game stops insisting and tells you what to do
- If Athena refuses the key saved in your profile (key changed or withdrawn), Link troubleshooting says so and offers Link my account instead of Reconnect, which kept failing.
- If your account, Steam or network is blocked by your community, it tells you to contact an administrator.
- After such a refusal, the phone sends nothing more until you link again or use Reconnect Athena: before, it asked again every few seconds for nothing.
- A feature missing from your community's plan (orders, chat, zones…) is no longer requested non-stop: one try every half hour.

### Détail technique
- Mesure en production (`request_telemetry`, 24 h au 2026-10-26) : 136 000 requêtes notées dont 128 000 refus 401/403, jusqu'à 21 000 refus par heure. Causes côté site : 401 `unauthorized` (clé refusée ou session expirée), 403 `feature_unavailable` (offre basique : ordres, minuteurs, tchat, zones, alertes médicales, marqueurs, véhicules), 403 `tenant_context_required`, et les refus du garde d'écriture (`steam_not_linked`, `account_disabled`, `steam_required`, `atak_blocked`, `mod_steam_blocked`, `mod_ip_blocked`). Côté mod : les sondages GET de la DLL Link (`ServePollGet`) ignoraient toutes les pauses et le cache masquait les refus au SQF ; le lot de télémétrie en 401 repartait chaque seconde ; `AuthInvalidated` rouvrait le canal toutes les 48 s.
- DLL Link, nouveau `Extension_RefusalLatch.cs` : `NoteRefusal` lit le code JSON `error` de chaque 401/403 (sondages, lot, file, envois). 403 « règle » → verrou global immédiat ; 401 → un client-init (`MaybeReauthAfter401`, désormais aussi pour les sondages), verrou s'il est refusé ; `feature_unavailable` → cette adresse seule se tait 30 min. Verrou global : `ServePollGet`, `EnqueueOrSend` et `DrainQueue` n'envoient plus rien. Levée : `SetGameAuth("READY")` sans erreur (nouvelle liaison, nouvelle clé, Reconnecter accepté) ou redémarrage. Les appels de liaison et client-init ne sont pas bloqués.
- `fn_extensionCallback.sqf`, `AuthInvalidated` : la cause est écrite dans `COMSPEC_LinkDetail` avec les libellés de `fn_connect`, et la réouverture automatique à 48 s n'a plus lieu pour un refus connu.
- DLL `COMSPECExtension_x64.dll` du mod natif recompilée (NativeAOT) ; numéro de version de la DLL inchangé pour ne pas déclencher l'alerte « DLL périmée » de main.pbo.
- `fn_diagChecks.sqf` (connect embarqué dans l'ATAK natif), étape 6 « Liaison » : avant, tout échec de `fn_connect` hors « non lié » tombait dans le cas par défaut (« site injoignable », correction Reconnecter). Désormais `COMSPEC_LinkDetail` est classé : clé refusée (`ERR unauthorized`) → Lier mon compte ; communauté manquante ou Steam invalide → Lier mon compte ; compte non autorisé ou accès mod restreint → contacter un administrateur, pas de correction ; Steam requis → mettre le mod à jour.
- Contexte : rotation de la clé plateforme du 2026-10-26. Depuis #1052 la liaison renvoie une clé de communauté ; un profil qui gardait l'ancienne clé plateforme reçoit maintenant `unauthorized`, et seule une nouvelle liaison enregistre une clé valide.
- `comspec_overwatch_connect.pbo` repaqueté seul (AddonBuilder, préfixe `z\comspec_overwatch\addons\connect`) ; main.pbo et la version de l'ATAK natif ne changent pas. Nouveaux textes en français seulement (repli de `fn_tr`), à ajouter à `en.txt` au prochain regroupement de main.pbo.
- Non testé en jeu. À tester : profil avec une clé invalide → Échap › Dépannage liaison affiche « clé d'accès refusée » et le bouton ouvre Lier mon compte ; après liaison, Revérifier → Liaison OK.
## Retours des contributeurs : téléphone en anglais, SEEK II, accueil qui garde sa place (ATAK natif : numéro donné au regroupement)

### Résumé joueur
Type : correctif
FR : Le téléphone parle mieux anglais et l'accueil ne remonte plus en haut
- Les noms des apps et des sections de l'accueil, la flèche Retour, l'écran de verrouillage (date, batterie) et de nombreux boutons des apps (Météo, Sniper, Explosifs, Anti-drone, Logistique, Profil, Briefing…) sont traduits en anglais.
- En revenant d'une app ou après un changement dans Réglages, l'accueil et les Réglages se rouvrent à l'endroit où tu étais.
- SEEK II : les intitulés des écrans et les infobulles sont traduits, la consigne des empreintes n'apparaît plus en double, et QUERY sans relevé l'affiche sur l'écran du SEEK.
- Menu Échap : la barre COMSPEC et le bouton Réalisme et réseau ne sont plus écrasés avec la taille d'interface Normale.
EN : The phone speaks better English and the home screen keeps its place
- App and section names on the home screen, the Back arrow, the lock screen (date, battery) and many app buttons (Weather, Sniper, Explosives, Anti-drone, Logistics, Profile, Briefing…) are now in English.
- Coming back from an app or after a change in Settings, the home screen and Settings reopen where you were.
- SEEK II: screen labels and tooltips are translated, the fingerprint instruction no longer shows twice, and QUERY with no capture says so on the SEEK screen.
- Escape menu: the COMSPEC bar and the Realism and network button are no longer squashed with the Normal interface size.

### Détail technique
- Retours du salon Discord Contributor (kroncc). App renommées en anglais selon ses propositions : Liaison allié → ATAK Link, Vidéo live → Live Feed, Recherchés → Target Deck ; Rejeu → Replay (AAR).
- `fn_tr` : un symbole devant le texte (« ■ SEEK PRÊT », « ‹ RETOUR ») ne bloque plus la recherche. `fn_langApply` : `%%` d'un modèle correspond au `%` affiché (73 modèles, dont « Batterie %1 %% », ne marchaient jamais).
- `fn_displayLoad` : infobulles fixes de l'écran relues dans la config et traduites. `fn_trDisplay` d'Overwatch connect et du SSE : textes enrichis (type 13) et infobulles des écrans relus dans la config (écran retrouvé par son idd, mis en cache) ; avant, « ŒIL DROIT (OD) », « NOM »… restaient en français.
- Mots français en capitales (CALCULER, TROUPES, RALLUMER…) : ignorés par `tools/i18n.py extract` (pris pour des identifiants), ajoutés à la main dans `en.txt` (~180 entrées).
- `fn_pageRender` / `fn_displayUnload` : défilement gardé pour LAUNCHER et SETTINGS, y compris quand l'écran est recréé.
- `fn_lockScreen` : date en anglais (« SATURDAY, FEBRUARY 24 ») hors langue française.
- `fn_pageSse` : tuiles d'indicateurs sur deux lignes quand l'écran est étroit (les libellés se coupaient au milieu des mots).
- Overwatch connect : `fn_sseIdentityQuery` (QUERY sans relevé : bandeau AUCUN RELEVÉ sur le SEEK), `fn_ssePersonRefreshPanels` (consigne bilingue en double retirée, légende NFIQ découpée pour la traduction), infobulle « dossier actif » raccourcie (elle débordait de l'écran), `fn_onInterruptLoad` et `fn_netConfig` (police tirée de la hauteur de la barre).
- SSE : `docs/ZEUS_QUICKSTART_EN.md`, ce qu'il faut poser en Zeus pour une exploitation de site prête à jouer.
- Non traité ici : titres des tuiles d'accueil du SEEK (gravés dans les images PAA), clavier du SEEK imposé (option CBA demandée).
- À reconstruire au regroupement : main.pbo, comspec_overwatch_connect.pbo, PBO du SSE (core).

---

## Traduction ouverte aux contributeurs : rôles, traduction du site, kit des mods complété (ATAK natif : numéro donné au regroupement, site)

### Résumé joueur
Type : nouveauté
FR : Athena se traduit avec la communauté
- Nouvelle page Contribuer : demande un rôle de traducteur, testeur, créateur de contenu, développeur ou support ; l'équipe le valide et te donne le rôle sur le Discord.
- Nouvel espace Traduire le site : chaque membre connecté propose des traductions, les traducteurs de la langue les valident, et une langue publiée apparaît dans le sélecteur de langue du site (le reste s'affiche en anglais). L'anglais du site peut aussi y être corrigé.
- Mods : les phrases encore en français dans la version anglaise du téléphone ATAK, du mod SSE et de la connexion Overwatch sont traduites.
- Les mods de langue faits sur Traduire l'ATAK traduisent aussi les noms d'objets et des modules Eden et Zeus de l'ATAK et du SSE quand Arma 3 est dans cette langue.
- Discord : relie ton compte Discord à ton compte Athena (Mon compte › Préférences, ou le menu Rôles liés du serveur) pour obtenir le rôle Athena verified ; ton profil Discord affiche tes communautés. Sur le serveur, tu choisis toi-même les rôles Translator, Tester ou Content creator.
EN : Athena is now translated with the community
- New Contribute page: apply for a translator, tester, content creator, developer or support role; the team approves it and gives you the role on Discord.
- New Translate the website space: any signed-in member suggests translations, the language's translators approve them, and a published language shows in the website's language picker (the rest shows in English). The website's English can also be fixed there.
- Mods: sentences still in French in the English version of the ATAK phone, the SSE mod and the Overwatch connection are now translated.
- Language mods made with Translate ATAK also translate the names of items and of Eden and Zeus modules of ATAK and SSE when Arma 3 runs in that language.
- Discord: link your Discord account to your Athena account (My account › Preferences, or the server's Linked Roles menu) to get the Athena verified role; your Discord profile shows your communities. On the server, you pick the Translator, Tester or Content creator roles yourself.

### Détail technique
- Mod (`tools/i18n.py`, `en.txt`) : 72 textes français affichés sans traduction ajoutés (capture de la carte, test vidéo PiP, tableau de bord SSE, diagnostics Overwatch connect). Le modèle communautaire `localisation/template/xx.txt` (14 675 phrases) reprend aussi les textes écrits en anglais dans le code (`tools/i18n_fr_source.tsv`, clé = texte anglais, cherché tel quel par `fn_tr`) et les textes des stringtables absents du dictionnaire. Nouveau `localisation/template/stringtable.json` (1 287 clés ATAK, SSE, Overwatch connect) ; `i18n.py community F --game Spanish` écrit la `stringtable.xml` du mod de langue ; catégorie « Objets et modules Eden/Zeus » dans `categories.json`. Aucun SQF modifié : seul `en.txt` change dans main.pbo (à reconstruire au regroupement).
- Site, atelier `/traduire-atak` : `modele.json` livre les clés de stringtable ; le zip (navigateur et téléchargement du Workshop des langues) ajoute `stringtable.xml` au PBO quand la langue du jeu est choisie (`AtakTranslationModBuilder::buildStringtable`). À vérifier en jeu : une stringtable chargée après COMSPEC (requiredAddons) remplace bien les noms pour cette langue du jeu.
- Site : `/contribuer` (ContributorController), `/traduire-site` (SiteTranslationController, SiteTranslationService), `/admin/system/contributeurs` (SystemContributorsController). Migration `20261025000001_site_contributors_translation.sql` (run-migrations sur le VPS). Les langues publiées et leurs dictionnaires sont compilés dans `storage/i18n/community/` (CommunityLanguages) : `LocaleService` accepte leurs codes, une langue de la communauté se comporte comme l'anglais (`locale()` = en, `site_lang()` = son code) avec son dictionnaire par-dessus (HtmlTranslator, Translator, dictionnaire JavaScript `/traduire-site/dico/{code}.js`).
- Discord : script `tools/discord/comspec_discord_contributors.py` (lancé sur le PC de TTRD, jeton du bot jamais sur le site) : rôles Translator / Content creator, salons translation / contributors, question de choix des rôles dans l'accueil du serveur (Onboarding), conditions des rôles liés déclarées à l'application et rôle Athena verified.
- Rôles liés Discord (Linked Roles) : `DiscordLinkedRoles` (OAuth `identify role_connections.write`, conditions compte Athena / membre d'une communauté / nombre de communautés / ancienneté, nom des communautés sur le profil), `/discord/roles-lies` (adresse de vérification) et retour, panneau Discord dans Mon compte › Préférences, réglages Application ID + secret client chiffré dans Intégrations › Plateforme, tâche automatique quotidienne `discord_linked_roles_sync`. Migration `20261025000002_user_discord_links.sql`. Dans le portail des développeurs Discord : OAuth2 › Redirects = `/discord/roles-lies/retour` ; puis la condition « Compte Athena » sur le rôle Athena verified (Paramètres du serveur › Rôles › Liens).
## App Admin dans le téléphone : parc ATAK de la communauté, ATAK de la partie et debug de l'IA, pour la gestion et les Zeus (regroupé dans ATAK natif 2.4.0.AB - Denver, COMSPEC Link 2.0.95)

### Résumé joueur
Type : nouveauté
FR : Nouvelle app Admin pour la gestion et les Zeus
- EN JEU : l'ATAK de chaque joueur (batterie, état, liaison Athena) et les effets Zeus en un clic.
- ATHENA : le parc ATAK de la communauté, avec blocage, message et compromission.
- DEBUG IA : les téléphones ramassés par l'ennemi et qui il traque.
EN : New Admin app for community managers and Zeus
- IN GAME: every player's ATAK (battery, state, Athena link) and Zeus effects in one click.
- ATHENA: the community ATAK fleet, with block, message and compromise.
- AI DEBUG: phones picked up by the enemy and who it hunts.

### Détail technique
- App `Admin` (Système, `functions/admin/`), dans le téléphone uniquement, montrée seulement à qui y a droit (`fn_appVisible` -> `fn_adminClient "visible"`). Icône `data/app_admin.paa` (`tools/gen_assets.py only app_admin`).
- Deux droits séparés :
  - En jeu : Zeus, hôte, admin connecté (#login) ou UID Steam du nouveau réglage serveur `comspec_atak_native_admin_uids` (« ATAK · Admin »). Vérifié par le serveur (`fn_adminAllowed`), aussi pour les effets Zeus (`fn_zeusServer`).
  - Athena : gestionnaire de la communauté (`admin.organization` / `admin.access`) ou administrateur de la plateforme, lu par le site sur la session de jeu du joueur (la clé commune du serveur ne suffit pas).
- Onglet EN JEU : à la demande (toutes les 10 s app ouverte, rien sinon), le serveur demande à chaque joueur sa fiche, envoyée directement à l'admin (`comspec_atak_native_adminReport` / `adminData`) : ATAK porté, batterie, état, liaison Athena, versions, n° / IMEI / terminal, SOS, balise, position, compromission, effets Zeus. Actions par joueur via `comspec_atak_native_zeus` : verrouiller, muet, brouiller, éteindre, réparer, tout rétablir. Fiche Athena liée par Steam.
- Onglet ATHENA : `POST /api/atak/game-admin/fleet` et `/action` (`AtakGameAdminService`, mêmes règles que /back-office/atak/parc-atak) : bloquer dans la communauté, lever un blocage (ceux de la plateforme : admin plateforme seulement), déconnecter, message imposé, déclarer capturé / compromis, lever la compromission (source `ingame` dans l'historique, journal « depuis le jeu »). Retrait et blocage toutes communautés restent sur le site. COMSPEC Link `GameAdmin.Fleet` / `GameAdmin.Action` (2.0.94), 40 fiches par page.
- Onglet DEBUG IA (`fn_adminServer "scan"`) : captures avec porteur, déchiffrage, exploitation du BFT, joueurs repérés et groupes en traque (`fn_captureServer` garde `targets` / `hunters`), IA en route pour ramasser ; valider / rejeter (aussi pour un autre camp), arrêter la traque, reprendre le téléphone, couper la traque pour la partie.
- Boutons à conséquence en deux temps (CONFIRMER). `i18n/en.txt` : 143 textes.
- Version 2.3.11, COMSPEC Link 2.0.94 (DLL à recompiler). main.pbo non reconstruit dans cette PR (regroupement).
- À tester en jeu : app visible en Zeus et invisible pour un joueur ; relevé EN JEU avec plusieurs joueurs ; effets Zeus depuis l'app ; parc ATHENA avec un compte gestionnaire relié (bloquer puis lever, message reçu, compromission puis levée) ; DEBUG IA après qu'une IA ennemie a ramassé un téléphone (traque visible, ARRÊTER LA TRAQUE, REPRENDRE LE TÉLÉPHONE).
## Nouveaux logos ATHENA sur le site et dans le téléphone (regroupé dans ATAK natif 2.4.0.AB - Denver)

### Résumé joueur
Type : nouveauté
FR : Nouvelle identité ATHENA : l'aigle
- Le site Athena adopte le nouveau logo : l'aigle remplace le « A » en haut de chaque page et dans le tableau de bord, le logo complet ATHENA est en pied de page, et l'icône de l'onglet, des favoris et de l'appli installée sur téléphone change aussi.
- Dans le téléphone ATAK, l'app Athena prend l'icône de l'aigle et sa carte d'état affiche le nouveau logo ATHENA.
- Les logos ont été redessinés en vectoriel : nets à toutes les tailles, sans le trait blanc qui bordait l'ancienne image.
EN : New ATHENA identity: the eagle
- The Athena website takes on the new logo: the eagle replaces the "A" at the top of every page and in the dashboard, the full ATHENA logo sits in the footer, and the icon for the browser tab, bookmarks and the app installed on a phone changes too.
- In the ATAK phone, the Athena app gets the eagle icon and its status card shows the new ATHENA logo.
- The logos were redrawn as vectors: sharp at every size, without the white line that edged the old picture.

### Détail technique
- Logos vectorisés (potrace, tracé à 6x avec lissage) à partir des PNG fournis ; colonne blanche des 3 premiers pixels du logo complet retirée. Sources : `public/assets/brand/*.svg` (blanc / noir), `mod/COMSPEC_ATAK_Native/tools/src/athena_logo.svg` et `athena_embleme.svg`.
- Site : `athena-header__brand-mark`, `dash-rail__brand-mark` et le pied de page portail utilisent l'emblème / le logo en masque CSS (couleur du thème) ; en-tête d'accès public et accueil (`hi-wordmark`, pied) en `<img>` ; `athena-192/512.png` carrés (étaient 520x240 et 1200x480), nouveaux `athena-180/32.png`, `athena-maskable-512.png`, `favicon.ico` ; manifeste PWA et cache `sw.js` (v11) à jour ; `atak-eagle-logo.png` (ATAK web, porte SSE) remplacé par l'emblème.
- Mod : `gen_assets.py <hemtt> athenalogo` régénère `app_athena.paa` (emblème) et le nouveau `logo_athena.paa` (logo complet) ; `fn_pageAthena` et le repli de la ligne « status » de `fn_formRender` l'utilisent. Version 2.3.11, main.pbo reconstruit (HEMTT), verify_build OK. Link inchangé (2.0.93).
## Indicatif perso, marqueurs nommés et modifiables, recherche par grille, outils JTAC (cap magnétique, 9-LINE sur un autre écran), jumelles sans marquage ENI par défaut (ATAK natif, version fixée au regroupement)

### Résumé joueur
Type : nouveauté
FR : Ton propre indicatif, des marqueurs à ton nom et les outils du JTAC
- C2 et Groupe : « Mon indicatif » te laisse choisir ton indicatif ; il est gardé hors ligne et repris sur Athena (et l'inverse).
- Marqueurs : écris leur texte dans la palette, modifie un marqueur posé (appui long, Modifier le marqueur), choisis le nom par défaut et la numérotation (suite ou reprise des numéros manquants) dans les réglages de l'addon.
- Carte : déplacer la carte au clic gauche ne ferme plus l'outil ouvert (bouton ✕ pour le quitter, menu OUTILS rangé par usage) ; RECHERCHE accepte une grille avec ou sans espaces ni tirets ; tes cercles suivent ta position GPS.
- JTAC : l'outil carte IP → cible donne le cap magnétique, la distance et l'altitude pour le 9-LINE, et le 9-LINE s'ouvre sur un autre écran ou un téléphone (QR code ou bouton). Les jumelles ne marquent plus d'ENI sur la carte, sauf si l'option est activée.
EN : Your own callsign, named markers and JTAC tools
- C2 and Group: "My callsign" lets you choose your callsign; it is kept offline and sent to Athena (and the other way round).
- Markers: type their text in the palette, edit a placed marker (long press, Edit marker), choose the default name and numbering (next number or reuse missing ones) in the addon settings.
- Map: dragging the map with the left button no longer closes the open tool (✕ button to leave it, TOOLS menu grouped by use); SEARCH accepts a grid with or without spaces or dashes; your rings follow your GPS position.
- JTAC: the IP → target map tool gives the magnetic heading, distance and elevation for the 9-LINE, and the 9-LINE opens on another screen or a phone (QR code or button). Binoculars no longer mark enemies on the map unless the option is enabled.

### Détail technique
- Indicatif : `fn_callsignAction` (profil `COMSPEC_ATAK_MyCallsign`, variable publique `COMSPEC_ATAK_CsCustom`, lue en premier par `fn_unitCallsign`) ; vers le site par `Squad.Sync` (`callsign_custom`, `AtakRoleService::recordFromSync` met à jour `users.callsign` et `personnel_profiles.callsign`, 2 à 24 caractères nettoyés) ; depuis le site par la ligne `CS` du catalogue des rôles (`GetFireTeams roles:`), appliquée si la valeur du site a changé depuis la dernière lecture.
- Jumelles : réglage serveur `comspec_atak_native_optic_mark` (désactivé par défaut) ; le désignateur laser marque toujours.
- Marqueurs : réglages client `comspec_atak_native_marker_name` (jetons {camp} {type} {n} {grille} {indicatif} {heure}) et `comspec_atak_native_marker_numbering` (next / fill) ; ligne TEXTE de la palette ; entrée MKEDIT du menu contextuel.
- Recherche : type GRILLE, `fn_gridParse` (préfixe MGRS ignoré, deux blocs ramenés à la même précision).
- GPS : soi-même (icône, anneaux, étiquettes, trait de distance) dessiné à la position GPS simulée (`COMSPEC_ATAK_SelfGpsPos`).
- Clic gauche de la carte : armé à l'appui (`mapLc`), exécuté au relâchement s'il n'y a pas eu de glisser (`fn_mapMouseButtonUp` rappelle `fn_mapMouseButtonDown` avec `_click`) ; TRAIT et DESSIN démarrent toujours à l'appui. Menu OUTILS en sections (NAVIGATION, MESURER, DESSINER, AFFICHAGE), bandeau d'outil actif avec icône et bouton ✕.
- JTAC : `fn_magBearing` (réglage serveur `comspec_atak_native_mag_decl`), outil carte IPTGT `fn_jtacTool` (VERS LE 9-LINE remplit l'app JTAC), ligne 2 du 9-LINE en cap magnétique ; page site `/9-line` (flux des 9-LINE de la communauté toutes les 5 s, calculateur IP → cible), QR `data\nineline_qr_ca.paa`.
## Mode full local : PC portable de TOC, app Numéros, configuration locale config.json (ATAK natif, version attribuée au regroupement ; COMSPEC Link : DLL native modifiée)

### Résumé joueur
Type : nouveauté
FR : Jouer sans Athena, avec un vrai PC de TOC en jeu
- Mode full local : un serveur peut jouer sans Athena ni site web (réglage serveur). Ordres, messages du poste, comptes rendus, numéros de téléphone et carnet passent en jeu.
- PC portable de TOC : un ordinateur posé dans la mission devient le poste de commandement. À côté, l'ATAK du poste s'ouvre sur une grande tablette avec l'app Poste TOC : message à tout le camp (ROUTINE, URGENT, FLASH), ordres aux groupes et leurs réponses, situation des groupes, derniers comptes rendus. Les réponses des joueurs arrivent à l'opérateur assis au poste.
- Nouvelle app Numéros : tes numéros et leurs types (mobile, satellite, fixe, radio IP, ligne chiffrée, bip, urgence), numéro principal, annuaire des joueurs de ton camp et carnet de contacts.
- Tout est gardé dans un fichier config.json modifiable à la main (Documents, Arma 3, COMSPEC_Local), aussi hors ligne.
EN : Play without Athena, with a real TOC laptop in game
- Full local mode: a server can play with no Athena and no website (server setting). Orders, post messages, reports, phone numbers and contacts run in game.
- TOC laptop: a computer placed in the mission becomes the command post. Next to it, the post's ATAK opens on a large tablet with the TOC post app: message to the whole side (ROUTINE, URGENT, FLASH), orders to groups and their replies, group situation, latest reports. Players' replies reach the operator sitting at the post.
- New Numbers app: your numbers and their types (mobile, satellite, landline, IP radio, secure line, pager, emergency), main number, directory of the players on your side and contact list.
- Everything is kept in a config.json file you can edit by hand (Documents, Arma 3, COMSPEC_Local), offline too.

### Détail technique
- DLL native (`COMSPECATAKNativeExtension/LocalConfig.cs`, aiguillage `Local*` dans `Extension.cs` sous `COMSPEC_ATAK_NATIVE`) : `LocalBegin / LocalAppend / LocalCommit [nom] / LocalGet [nom, décalage] / LocalFolder / LocalOpenFolder`. Fichiers `Documents\Arma 3\COMSPEC_Local\<nom>.json` (nom : lettres, chiffres, `-`, `_`), JSON vérifié et indenté, écriture atomique, ancien fichier en `.bak` ; lecture tolérante (commentaires, virgules finales), rendue en tableau SQF (`["#obj", [clé, valeur]...]`) par morceaux (réponse bornée à 8000 octets). Compilé et testé hors jeu (dotnet 8). Le DLL est à reconstruire (COMSPEC Link +1 au regroupement) ; sans lui, la configuration reste dans le profil Arma.
- `fn_localCfg` : chargement (fichier, sinon profil `COMSPEC_ATAK_LocalCfgSave`, sinon défauts), normalisation (8 numéros, 100 contacts, un seul principal), enregistrement fichier + profil, `apply` (variables publiques `COMSPEC_ATAK_LocalNum` en ATAK local, `COMSPEC_ATAK_Numbers`), types, générateur de numéros par plan (FR, US, UK, DE, BE, RU, ES). `fn_phoneIdent` prend `COMSPEC_ATAK_LocalNum` (avant la surcharge de mission `COMSPEC_ATAK_Ident`).
- App Numéros (`fn_pageNumbers`, `fn_numbersAction`) : onglets MES NUMÉROS / ANNUAIRE / CARNET / FICHIER.
- PC de TOC (`functions/toc` : `fn_tocPost`, `fn_pageToc`, `fn_tocAction`) : objet `COMSPEC_ATAK_TocLaptop` (modèle de l'ordinateur portable d'Arma, attribut Eden « Camp du poste TOC »), variable `COMSPEC_ATAK_Toc` sur n'importe quel objet, réglage « Tous les ordinateurs portables servent de PC de TOC » ; recherche du poste chaque seconde (2,5 m, à pied), `fn_vehicleTerminal` renvoie `toc` (tablette de bord), action ACE / molette, app Poste TOC visible seulement au poste. Messages du poste : événement `comspec_atak_native_tocMsg` relayé au camp par le serveur et rejoué aux arrivants ; `fn_p2pSend` remet les réponses à « TOC · indicatif » à l'opérateur au poste (`COMSPEC_ATAK_TocOn`).
- Ordres sans Athena (`fn_orderLocal`) : sans COMSPEC Link, en ATAK local ou au poste TOC, `fn_orderAction` envoie l'ordre par `comspec_atak_native_localOrder` (camp, rejoué aux arrivants), `fn_tasksAll` ajoute `localTasks`, réponses par `fn_orderAck` comme avant. NOUVEL ORDRE visible pour chef de groupe, joueur seul, rôle chef / commandant, opérateur du poste.
- Réglages serveur **ATAK · Mode local et poste TOC** : `comspec_atak_native_local_only` (mode full local : `fn_athenaMode` natif et Overwatch connect renvoient `off`, Réglages > Service Athéna affiche « imposé »), `comspec_atak_native_toc_enabled`, `comspec_atak_native_toc_anylaptop`.
- Nouvelles icônes `app_numbers`, `app_toc` (`tools/gen_assets.py only app_numbers,app_toc`). Traductions EN ajoutées. `hemtt check` et `hemtt build` OK.
- Au regroupement : bump de version, main.pbo reconstruit, `comspec_overwatch_connect.pbo` repacké (fn_athenaMode du connect modifié), DLL native reconstruite.
- À tester en jeu (rien ne l'a été) : réglage mode full local (plus de question Athéna, Réglages « imposé ») ; app Numéros (ajout, GÉNÉRER, principal, privé, annuaire, carnet, FICHIER : ouvrir le dossier, modifier config.json à la main puis RECHARGER) ; recherche par numéro sur la carte avec le numéro choisi ; PC de TOC posé dans Eden et par Zeus (action ACE et sans ACE, tablette, app Poste TOC qui disparaît en s'éloignant) ; message du poste URGENT et réponse d'un joueur ; NOUVEL ORDRE sans Athena, réponses REÇU / TERMINÉ, joueur arrivé en cours de partie.

---

## Jeu plus fluide, app Drone réparée, choix des données envoyées à Athena (regroupé dans ATAK natif 2.4.0.AB - Denver, COMSPEC Link 2.0.95)

### Résumé joueur
Type : performance
FR : Fini la petite saccade toutes les deux secondes, l'app Drone répond de nouveau
- Téléphone porté ou carte ouverte : la carte ne relit plus tous les marqueurs de la mission dès que l'un d'eux bouge, ce qui faisait une petite saccade toutes les deux secondes.
- App Drone : la croix, PLEIN ÉCRAN et les boutons de la barre fonctionnent, et après un appui les autres boutons restent cliquables sans passer le téléphone en petit format.
- Téléphone : en écrivant dans un champ, A et E ne font plus pencher ton personnage, et une photo prise pendant une pause ou un changement de fenêtre part quand même au lieu d'échouer. Sans COMSPEC Link, l'appareil photo garde simplement la photo sur ton PC.
- Ta communauté peut choisir sur Athena ce que le jeu envoie (tout, ou seulement les comptes rendus) : moins de données, une liaison plus légère.
EN : No more small stutter every two seconds, the Drone app responds again
- Phone worn or map open: the map no longer re-reads every mission marker as soon as one moves, which caused a small stutter every two seconds.
- Drone app: the cross, FULL SCREEN and the bar buttons work, and after a press the other buttons stay clickable without switching the phone to small size.
- Phone: when typing in a field, A and E no longer make your character lean, and a photo taken while paused or tabbed out is still sent instead of failing. Without COMSPEC Link, the camera simply keeps the photo on your PC.
- Your community can choose on Athena what the game sends (everything, or reports only): less data, a lighter link.

### Détail technique
- Saccade : `fn_mapGameMarkers`, `fn_localDataRefresh` et `fn_strokeDraw` ne relisent que les marqueurs changés (carnets `COMSPEC_ATAK_MkDirtyGm/Ld/Sk` tenus par les EH MarkerCreated / Deleted / Updated, clé "" = tout relire au-delà de 300), relecture complète toutes les 30 s ; doublons Athena / jeu cherchés dans une grille de 15 m (avant : chaque marqueur Athena comparé à chaque marqueur du jeu) ; `fn_storeSet` accepte une signature fournie (unités et marqueurs ne passent plus par `str` de toute la table) ; masquage des zones Overwatch seulement quand un marqueur a changé ; échéances `hasDevice` étalées ; compteur `[PERF]` « unités et marqueurs (carte) ».
- Clics : `fn_pageClear` masque les anciens contrôles et les supprime à l'image suivante (un bouton n'est plus supprimé pendant son propre ButtonClick, ce qui bloquait tous les clics) ; nouveau `fn_clickNext` (clic différé d'une image) pour l'app Drone, la vue drone de la carte et la fiche photo.
- Saisie : `fn_keyBlock` regarde aussi la fenêtre qui reçoit la touche et ne dépend plus de l'état « en main ».
- Photos (tickets file_not_found) : COMSPEC Link 2.0.94 relance jusqu'à 4 fois (15 s d'écart, 150 s au plus) un nom précis pas encore écrit au lieu de répondre `file_not_found` après 20 s, sans bloquer la file des autres photos ; connect `fn_captureReconImage` ne retient plus le nom d'une capture refusée par `screenshot` (les envois des 3 s suivantes cherchaient un fichier jamais écrit). ATAK seul (sans addon connect) : appareil photo, mode photo, Galerie, cliché drone et dossier SSE ne lancent plus de fonction absente (erreur de script) ; la photo reste dans Screenshots. Aucune dépendance à BCE ni au pack SOAR : les dossiers BCE ne sont balayés que s'ils existent.
- explosive-timers 400 `steam_required` (ticket Z01, Zeus / SSE sans Steam) : couvert côté site, le miroir des notifications sans Steam répond 200 `notifs_skipped` quelle que soit la version du mod.
- Données remontées : site `AtakDataFlowsService` (positions, vehicles, combat, phone_mirror, context ; préréglages « tout » / « comptes rendus seulement »), carte « Données remontées du jeu » en tête de Configuration ATAK (badge « À choisir » tant que la communauté n'a pas répondu), `GET /api/atak/data-flows` (liaison de base), lot de télémétrie qui accepte sans traiter un flux coupé et renvoie `flows`. COMSPEC Link 2.0.94 : `GetDataFlows`, flux coupés jamais mis en file (positions coupées : un envoi par minute pour la présence) ; connect `fn_pollDataFlows` (30 s) : véhicules, drones, aéronefs, miroir et pertes ne sont plus calculés quand ils sont coupés.
- Signalements : `vehicle_exception` du lot : colonnes bornées à leur type SQL (`sanitizeColumns` : texte coupé, entiers, décimaux, booléens), doublon d'équipage (1062) repris en mise à jour, historique non bloquant, cause courte `detail` dans la réponse. `explosive-timers` 400 `steam_required` : miroir des notifications sans Steam ignoré en 200 côté site, pas envoyé en solo côté connect.
- À rebâtir : main.pbo, comspec_overwatch_connect.pbo, DLL COMSPEC Link 2.0.94. Rien de testé en jeu.

---

## Regroupement : marquage depuis le flux d'un drone du camp, GPS sans position périmée, connexion Athena du natif, matériel SSE reconnu, pack photo de la carte (ATAK natif 2.3.6.AB - Denver)

### Résumé joueur
Type : nouveauté
FR : Marque ce que voit le drone, GPS plus juste, connexion Athena réparée
- Vidéo Live : sur un drone de DRONES DU CAMP, un réticule donne le cap, la grille et la distance du point visé ; MARQUER LA CIBLE accroche la personne ou le véhicule encadré (LOCK suivi sur la carte) et MARQUER LE POINT pose un marqueur à l'endroit visé. L'opérateur du terminal UAV garde le contrôle.
- GPS : sans fix, le téléphone ne te place plus là où tu étais il y a 100 m ; il passe sur une position par le réseau autour de toi (plus précise avec plus de barres), ou dit qu'il n'a pas de position fiable.
- Connexion Athena : avec l'ATAK natif seul, un code autorisé sur le site ouvre bien la session ; en cas de refus, le téléphone affiche la vraie cause au lieu de « serveur injoignable ».
- Aussi : l'appareil photo, le SEEK et le terminal de COMSPEC SSE fonctionnent avec le téléphone ATAK natif, et la capture de la carte propose des vues plus proches et plus lointaines pour le nouveau pack photo optionnel.
EN : Mark what the drone sees, more honest GPS, Athena sign-in fixed
- Live video: on a FRIENDLY DRONES drone, a reticle gives the heading, grid and distance of the aimed point; MARK TARGET locks the boxed person or vehicle (LOCK followed on the map) and MARK POINT drops a marker at the aimed spot. The UAV terminal operator keeps control.
- GPS: without a fix, the phone no longer places you where you were 100 m ago; it switches to a network position around you (more accurate with more bars), or says it has no reliable position.
- Athena sign-in: with the native ATAK alone, a code authorised on the website now opens the session; when it is refused, the phone shows the real reason instead of "server unreachable".
- Also: the COMSPEC SSE camera, SEEK and terminal work with the native ATAK phone, and map capture offers closer and wider views for the new optional photo pack.

### Détail technique
- #993 (déjà sur main) : `fn_gpsFix` ne garde la dernière position connue que 30 s et tant que le joueur n'a pas bougé de plus de 25 m ; ensuite position par le réseau autour de la vraie position (150 m d'incertitude à une barre, 30 m à quatre, décalage de direction gardé), sinon pas de position fiable (500 m).
- #1000 (déjà sur main) : SSE `hasEquipment` accepte le téléphone ATAK natif (`comspec_atak_native_fnc_hasDevice`, Overwatch connect en repli) ; `fn_deviceScan` ne prend plus les pièces saisies du SSE (`COMSPEC_SSE_Smartphone`…) pour le téléphone du joueur.
- #1001 (déjà sur main) : pack secondaire optionnel `@COMSPEC_ATAK_Photo` (`build_photo_pack.bat`, `tools/map_stitch.py` : collecte des captures, raccords fondus, WebP/DXT1) ; formulaire Zeus de capture : niveaux Standard / Plus proche (150 m) / Plus loin (4000 m) / Complet / Personnalisé.
- #1008 (déjà sur main, partie site déployée) : `GameAuthService` ne compare le pack Overwatch minimal qu'à une version numérique (le natif envoie `native`, d'où un 426 à chaque code autorisé) ; `fn_athenaLink` garde le code HTTP du refus et le journalise, `fn_athenaStatus` / `fn_pageAthena` affichent « Athena a refusé la connexion » avec la cause.
- #1010, flux drone en lecture seule :
  - `fn_livecamUav.sqf` : direction de la caméra gardée dans le repère du drone (`COMSPEC_ATAK_LivecamUavDir`) ; mode `["aim"]` = point du sol sous le réticule (3 km) ; ligne CAP / VISÉ rafraîchie chaque seconde.
  - `fn_lockAction.sqf` : nouvelle source `LIVECAM` (caméra du flux, champ du terminal de l'opérateur) pour `mark`, `aimed` et le suivi.
  - `fn_droneFlir.sqf` : style `LIVECAM` (cadres seulement, repère de la caméra du flux) : cadre jaune « VISÉ » et cadres LOCK.
  - `fn_pageLivecam.sqf` : réticule, ligne VISÉ, boutons MARQUER LA CIBLE et MARQUER LE POINT (`fn_markerDrop`, 2 s entre deux marques) pour un drone du camp seulement.
  - `fn_livecamStop.sqf` : nettoyage. `i18n/en.txt` : 4 textes.
- Tests SSE : la version attendue du mod SSE passe de 0.9.0 à 1.0.0 (#1006).
- Version 2.3.6, main.pbo reconstruit avec HEMTT, `verify_build.py` OK. COMSPEC Link inchangé (2.0.90).
- À tester en jeu : réticule, cadre jaune et LOCK sur un drone du camp (vue nacelle et pilote) ; GPS à couvert après un fix dehors ; OBTENIR MON CODE puis autorisation sur /lier avec l'ATAK natif seul ; appareil photo, SEEK et terminal SSE avec le téléphone natif ; capture Zeus Plus proche et Plus loin.

---

## Regroupement : bandeau de la carte, arsenal ACE réparé, briefing et CAS dans le téléphone, ancien menu ACE retiré, diapositives Athena, Connexion Athena dans Eden (SSE), chargement plus fluide (ATAK natif 2.3.5.AB - Denver)

### Résumé joueur
Type : nouveauté
FR : Carte plus lisible, arsenal réparé, plus rien par-dessus le jeu
- Carte : le curseur et ta position tiennent dans un bandeau compact en bas à gauche (grille, distance, azimut), avec ton cap en direct et ton indicatif à la couleur de ton équipe de feu ; les noms des repères ne passent plus dessous, et le cercle de l'unité sélectionnée garde la même taille au zoom.
- Arsenal ACE : on peut de nouveau taper un nom (Tenues Athena et Mes équipements), et Mes tenues gagne la sélection multiple, la suppression en une fois et Annuler.
- Plus rien par-dessus le jeu : le briefing s'ouvre dans l'app BRIEFING, une demande CAS arrive en notification du téléphone et l'ancien menu ACE « COMSPEC Athena » à fenêtres plein écran est retiré ; une diapositive Athena noire s'affiche, ou le téléphone dit pourquoi.
- Aussi : Connexion Athena et temps passé dans Eden comptés avec COMSPEC SSE, arrivée en mission plus fluide, plus de carrés noirs dans Vidéo Live, capture de la carte Zeus avec reprise, et la tablette sur le JPC passe en chantier (W.I.P).
EN : Clearer map, arsenal fixed, nothing over the game any more
- Map: the cursor and your position fit in one compact bar at the bottom left (grid, distance, bearing), with your live heading and your callsign in your fire team colour; marker names no longer slide under it, and the selected unit's ring keeps the same size at any zoom.
- ACE arsenal: you can type a name again (Athena outfits and My loadouts), and My outfits gets multiple selection, delete in one go and Undo.
- Nothing over the game any more: the briefing opens in the BRIEFING app, a CAS request arrives as a phone notification and the old "COMSPEC Athena" ACE menu with full-screen windows is removed; a black Athena slide now shows, or the phone says why.
- Also: Athena sign-in and Eden time counted with COMSPEC SSE, smoother mission start, no more black squares in Live video, Zeus map capture that resumes, and the tablet on the JPC becomes work in progress (W.I.P).

### Détail technique
- #982 (déjà sur main) : `fn_pageLivecam` ne pose le panoramique et RENOMMER que pour une caméra fixe ; flèches ◀ ▶ (absentes des polices) remplacées par `<` `>` en Barlow.
- #983 : dictionnaire anglais préparé au `XEH_preStart` et repris depuis uiNamespace ; `COMSPEC_ATAK_PfhSpread` décale chacune des 48 boucles périodiques des postInit (5 s au plus) ; icônes gardées chargées créées par lots de 8 par image.
- #985, tablette sur le JPC en W.I.P :
- `fn_jpcScreen.sqf` : nouvelle action `["wip"]` (vrai tant que `_allowed` est faux) ; le code de la tablette est conservé, `_allowed = true` le rouvre.
- `XEH_postInitClient.sqf` : la touche CBA « PhoneJpc » ne fait rien en W.I.P (la touche repasse au jeu). `fn_aceMenu.sqf` : action « Ouvrir l'ATAK sur le JPC » masquée.
- `fn_keybinds.sqf` / `fn_pageSettings.sqf` : 6e champ « en chantier » ; ligne grisée, étiquette W.I.P, boutons touche et DÉFAUT inactifs, capture refusée. Traductions EN ajoutées.
- Version 2.3.5, main.pbo reconstruit avec HEMTT, COMSPEC Link inchangé (2.0.90).
- #986, diapositives du briefing : site `BriefingSlideGameImage` (raison `no_gd`, `source_missing`, `unreadable`, `write_failed`, copie JPEG faite à l'envoi, avertissement dans le back-office) ; `fn_pageBriefing` : nom de fichier local = id + hash de l'URL, contrôle du format (JPEG/PAA), cause affichée dans le téléphone, ligne `BRIEFING` dans le RPT et le .log de session.
- #987, bandeau de la carte :
- `fn_pageMap.sqf` : panneau curseur et carte « moi » fusionnés en un bandeau à deux cases (même hauteur, liseré vert à gauche, filet séparateur), largeur calée sur le texte ; repli sur 3 lignes si la case est trop étroite ; flèche de navigation des points de passage posée au-dessus du bandeau.
- `fn_mapOverlayUpdate.sqf` : hiérarchie valeur principale (taille 1,2, grasse) / détails (0,85, gris) ; azimut sur 3 chiffres, distance en km au-delà de 1 000 m ; points cardinaux selon `COMSPEC_ATAK_Lang` ; case « moi » : cap, indicatif à la couleur de l'équipe de feu (`fn_ftInfo`) et grille en main.
- `fn_mapHudRects.sqf` (nouveau) : rectangles du bandeau et du cap relatifs à la carte ; `fn_mapMarkerText`, `fn_mapPlaceText` (via `COMSPEC_ATAK_LblRects`) et `fn_mapLabels` les évitent.
- `fn_mapOnDraw.sqf` : anneau de sélection en icône à taille fixe (2,1 × le symbole) au lieu d'une ellipse de 18 × échelle m.
- `fn_mapOverlayUpdate.sqf` : case « moi » mise à jour par la boussole à chaque degré (`_meUpdate`) ; cap sous la boussole sur 3 chiffres.
- `fn_mapMarkerText.sqf` : plaques des zones en main à 0,020 (porté : 0,024 inchangé), hauteur 1,22, fond 0,82.
- `i18n/en.txt` : « CURSEUR » -> « CURSOR ».
- #988, arsenal ACE :
- Cause (Retour arrière) : `ace_arsenal_fnc_onKeyDown` garde toutes les touches tant qu'aucune de ses barres de recherche n'a le focus ; Retour arrière y appelle `buttonHide`, Ctrl+C / Ctrl+V exportent ou importent. Nos champs (recherche, nom de la tenue) posent `ace_arsenal_leftSearchbarFocus` en SetFocus / KillFocus, comme les barres d'ACE.
- Cause probable (champ Nom de Mes équipements) : l'écran des tenues d'ACE est un display enfant non modal ; nos contrôles restaient actifs sur l'arsenal dessous et un champ masqué du panneau pouvait garder le focus clavier. `ace_arsenal_loadoutsDisplayOpened` masque et désactive le bouton et le panneau Tenues Athena, puis donne le focus au champ Nom d'ACE (IDC 303) ; `ace_arsenal_loadoutsDisplayClosed` les rend et relit Mes tenues. À la fermeture du panneau, le focus revient au bouton ATHENA · TENUES.
- Sélection multiple (`fn_arsenalOverlayAction` pick / markAll / deleteMarked / undoDelete, `fn_arsenalOverlayMark`) : cases dans la colonne d'état, une seule écriture de `ace_arsenal_saved_loadouts` pour toute la sélection, corbeille `COMSPEC_ArsenalTrash` (profil) pour Annuler (une tenue du même nom enregistrée depuis n'est pas écrasée). Les tenues déjà sur le site y restent.
- À tester en jeu : taper, effacer et Entrée dans les deux champs du panneau ; Ctrl+C / Ctrl+V dans un champ (ne doit plus exporter) ; Mes équipements d'ACE : taper un nom, Enregistrer, puis revenir ; panneau Tenues fermé puis Mes équipements ; Sélection multiple, Tout cocher avec une collection choisie, Supprimer la sélection, Annuler ; re-clic sur une tenue cochée (la décoche).
- #989, capture de la carte (module Zeus) : 3 hauteurs par défaut (300, 800, 2000 m), cases en mer sautées, suivi du relevé dans le profil (`COMSPEC_ATAK_MapCapProg`, enregistré toutes les 25 images), reprise sur la même grille, dossier réel du profil dans les messages, ligne `CMAPF|fichier` par image.
- #990, addon Overwatch connect embarqué (pas `atak_athena`, gelé) : `fn_openBriefingBoard` ouvre l'app BRIEFING (ou une notification sans téléphone) au lieu de `COMSPEC_Briefing_Dialog` ; `fn_receiveCASRequest` envoie une notification qui ouvre l'app CAS au lieu de la visionneuse 9 lignes ; boucle `CBA_fnc_addDisplayHandler` retirée de `fn_registerZeusAttributeButtons` (erreur « Unknown enum value: rscdisplayattributes »). Ancien menu ACE « COMSPEC Athena » retiré sous le natif (`fn_initACE`, `fn_initACEAthena`, `fn_initATAKMenu`, `fn_addRelayDashboardActions`) : ne restent que les actions sans fenêtre ; nouveau `fn_openNativePage` (Ctrl+Maj+S ouvre l'app FRS, MEDEVAC ouvre MEDICAL, « Service Athéna » ouvre les Réglages du téléphone).
- #992, COMSPEC SSE : `fn_dashCollect` (`[getText, getNumber] select isNumber` évaluait `getNumber "RANDOM"`) remplacé par un if/else ; nouveau `comspec_sse_fnc_athenaEden` (reprise de session `RestoreSession`, sinon code court `PairStart` / `PairStatus`, aucune clé dans le SQF), entrée « Connexion Athena » du menu COMSPEC SSE et bouton du tableau de bord ; temps Eden actif (pause après 5 min sans saisie, aperçu non compté) envoyé par `ReportPlaytime` contexte `editor` toutes les 5 min, à l'aperçu et à la fermeture. `comspec_sse_guide.pbo` et `comspec_sse_main.pbo` repackés ; DLL et site inchangés.
- Version 2.3.5, main.pbo reconstruit avec HEMTT et comspec_overwatch_connect.pbo repacké depuis ses sources (plus de PBO patchés), `verify_build.py` OK. COMSPEC Link inchangé (2.0.90). La partie site de #986 part avec le déploiement VPS ; le mod COMSPEC SSE (#992) est à republier sur son propre item Steam.
- À tester en jeu (rien ne l'a été) : bandeau de la carte ; saisie et sélection multiple dans l'arsenal ; Briefing / diaporama et CAS depuis Athena ; diapositives JPG et PNG ; Vidéo Live sur un drone du camp et une caméra fixe ; capture de la carte avec reprise ; touche et ligne Tablette sur le JPC ; absence d'erreur CBA au lancement ; menu ACE COMSPEC Athena (plus de fenêtre plein écran, Ctrl+Maj+S, MEDEVAC, Service Athéna) ; SSE dans Eden (tableau de bord sans erreur RANDOM, Connexion Athena, ligne Eden de la fiche personnel).

---

## Drone : le flux DRONES DU CAMP suit la vue du terminal UAV (ATAK natif 2.3.4.AB - Denver)

### Résumé joueur
Type : correctif
FR : Vidéo Live montre enfin ce que voit l'opérateur du drone
- L'image d'un drone du camp dans Vidéo Live suit maintenant exactement la vue du terminal UAV de l'opérateur : même direction, même siège (nacelle ou pilote), même zoom et même vision.
- Quand l'opérateur ferme son terminal, l'image garde la dernière orientation qu'il avait choisie.
EN : Live video now shows what the drone operator sees
- A friendly drone's picture in Live video now matches the operator's UAV terminal view: same direction, same seat (camera pod or pilot), same zoom and same vision mode.
- When the operator closes the terminal, the picture keeps the last orientation they had chosen.

### Détail technique
- `fn_livecamUav.sqf` : caméra attachée sans suivre l'os de la nacelle ; direction lue via `weaponDirection` (siège nacelle) ou `getPilotCameraDirection` (siège pilote).
- `XEH_postInitClient.sqf` : la machine de l'opérateur publie `COMSPEC_UavView` [siège, fov, vision] sur le drone (diffusé, rafraîchi < 1 s).
- `fn_livecamStop.sqf` : nettoyage de l'état de suivi.
- main.pbo reconstruit avec HEMTT, `verify_build.py` OK. Link inchangé (2.0.90).

## Regroupement : flux nacelle des drones du camp dans Vidéo Live, drone retiré de la flotte rendu au terminal UAV, contrôle des PBO au build (ATAK natif 2.3.3.AB - Denver)

### Résumé joueur
Type : nouveauté
FR : Vidéo Live montre la caméra des drones du camp
- Choisis un drone allié en vol dans DRONES DU CAMP de l'app Vidéo Live pour voir ce que filme sa nacelle, de jour, en vision nocturne ou en thermique, même s'il est piloté au terminal UAV par un équipier.
- Un drone libéré depuis l'onglet FLOTTE de l'app Drone suit de nouveau les ordres « aller ici » du terminal UAV.
- Cette version reprend aussi la 2.3.2 : terminal UAV de nouveau accessible avec l'ATAK en miniature, et Manette de pilotage dans l'onglet VOL de l'app Drone.
EN : Live video now shows friendly drones' cameras
- Pick a friendly drone in flight in FRIENDLY DRONES in the Live video app to watch what its camera pod sees, by day, night vision or thermal, even when a teammate flies it with a UAV terminal.
- A drone released from the FLEET tab of the Drone app follows the UAV terminal's "move here" orders again.
- This version also carries 2.3.2: the UAV terminal is reachable again with the ATAK minimized, and a flight Controller in the FLIGHT tab of the Drone app.

### Détail technique
- #969 : nouveau `fn_livecamUav.sqf` (caméra sur le point mémoire `uavCameraGunnerPos`, direction depuis `uavCameraGunnerDir`), rubrique DRONES DU CAMP dans `fn_pageLivecam.sqf`, station d'un drone dans `fn_feedLink.sqf` (terminal UAV, sinon téléphone appairé, sinon ATAK allié le plus proche à moins de 2 km), boucle drone dans `fn_livecamStart.sqf`. `fn_droneFleet.sqf` « libérer » envoie `fn_droneCmd` "release" au propriétaire local du drone (rendu à son groupe par `doFollow`, comme au désappairage).
- #963 : `build_mod.bat` lance `tools/verify_build.py` en fin de build (PBO inattendus déplacés dans %TEMP%\comspec_atak_retires, taille, SHA-256, préfixe, doublons, fichiers absents des sources) et s'arrête en erreur si un PBO n'est pas conforme.
- Version 2.3.3, main.pbo reconstruit avec HEMTT, COMSPEC Link inchangé (2.0.90).
- À tester en jeu : Vidéo Live > DRONES DU CAMP sur un AR-2 ou Greyhawk piloté au terminal par un équipier (nacelle, NV, thermique, « drone perdu », « Personne ne reçoit ce drone ») ; drone libéré depuis FLOTTE puis « aller ici » au terminal.

---

## Drone : terminal UAV vanilla réparé, manette de pilotage dans l'app Drone (ATAK natif 2.3.2.AB - Denver)

### Résumé joueur
Type : correctif
FR : Le terminal UAV marche de nouveau, et l'app Drone a une manette
- Avec l'ATAK en miniature, la molette d'Arma reste libre : l'action « UAV Terminal » est de nouveau accessible.
- Le pilote peut toujours connecter son terminal UAV à un drone appairé au téléphone, et un pilote parti ne bloque plus personne.
- Après le pilotage manuel ou un désappairage, le drone suit de nouveau les ordres « aller ici » du terminal.
- Nouvelle Manette dans l'onglet VOL de l'app Drone : avancer, reculer, de côté, pivoter, monter et descendre, avec des boutons ou les touches ZQSD, A/E, Espace et C, aussi en plein écran.
EN : The UAV terminal works again, and the Drone app has a controller
- With the ATAK minimized, Arma's scroll wheel stays free: the "UAV Terminal" action is reachable again.
- The pilot can always connect their UAV terminal to a drone paired with the phone, and a pilot who left no longer blocks anyone.
- After manual flight or unpairing, the drone follows the terminal's "move here" orders again.
- New Controller in the FLIGHT tab of the Drone app: forward, back, sideways, turn, climb and descend, with buttons or the WASD, Q/E, Space and C keys, also in full screen.

### Détail technique
- PR #967 : `XEH_postInitClient.sqf` (ActionBlock, boucle de verrou réécrite), `XEH_preInit.sqf` (« Menu d'actions » par défaut à « En main seulement »), `fn_droneCmd.sqf` (`manual`/`release` sans `doStop`, rendu par `doFollow`, nouveau cas `stick`), `fn_droneAction.sqf` (`stick`, `stickSet`, `stickStep`, `stickStop`, `stickKey`), `fn_pageDrone.sqf` (section Manette + KeyUp), `fn_droneOsd.sqf`, traductions EN, gabarit de traduction.
- Version 2.3.2, main.pbo reconstruit, COMSPEC Link inchangé (2.0.90).
- À tester en jeu : UAV Terminal par la molette avec l'ATAK en miniature, clic droit « aller ici » ; connexion du terminal par le pilote d'un drone appairé ; Manette (décollage, touches tenues, boutons, STOP, plein écran) ; un autre joueur bloqué tant que le pilote est là, libre ensuite.

---

## Crédits : logo ATAK à la place du logo SOAR (ATAK natif 2.3.1.AB - Denver)

### Résumé joueur
Type : correctif
FR : Le logo COMSPEC ATAK en tête de l'app Crédits
- L'app Crédits du téléphone affiche maintenant le logo COMSPEC ATAK au lieu du logo SOAR.
EN : The COMSPEC ATAK logo at the top of the Credits app
- The phone's Credits app now shows the COMSPEC ATAK logo instead of the SOAR logo.

### Détail technique
- `fn_pageCredits.sqf` : l'image du bandeau passe de `data\logo_soar.paa` à `data\logo_atak.paa` (déjà dans le mod). Le fond d'écran « SOAR » des Réglages ne change pas.
- Version 2.3.1, main.pbo reconstruit.

---

## Regroupement : profils de réalisme Milsim, positions BFT par points, GPS naturel, carte satellite, radio MPU5 et voix ACRE2, brouilleurs d'autres mods (ATAK natif 2.3.0.AB - Denver, COMSPEC Link 2.0.90)

### Résumé joueur
Type : nouveauté
FR : Réalisme Milsim par défaut, carte satellite, MPU5 et voix ACRE2, brouilleurs d'autres mods
- Le menu Échap « Réalisme et réseau » propose les profils Milsim (par défaut), Standard et Arcade ; chaque réglage reste modifiable. En Milsim, tes amis sur la carte sont à la position de leur dernier envoi (toutes les 10 s environ), et ton GPS perd en précision dans les bâtiments, en forêt ou entre les immeubles, et met quelques secondes à trouver les satellites.
- Nouveaux fonds de carte Satellite et Hybride dans Réglages et dans l'app Carte, avec la capture photo HD de la carte par-dessus quand elle existe.
- Une radio maillée MPU5 dans ton inventaire relie ton ATAK plus loin, et l'onglet VOIX de l'app Wave Relay montre qui émet sur tes fréquences ACRE2, ton PTT et les équipiers connectés.
- Les brouilleurs radio TFAR et ceux d'autres mods de guerre électronique brouillent aussi ton ATAK, et une mesure du Vector 21 ou une nouvelle touche pose le point visé sur la carte.
EN : Milsim realism by default, satellite map, MPU5 and ACRE2 voice, jammers from other mods
- The ESC menu "Realism and network" offers the Milsim (default), Standard and Arcade profiles; every setting stays adjustable. In Milsim, friends on the map sit where they last reported (about every 10 s), and your GPS loses accuracy inside buildings, in forests or between tall buildings, and takes a few seconds to find the satellites.
- New Satellite and Hybrid map backgrounds in Settings and in the Map app, with the HD photo capture of the map on top where one exists.
- An MPU5 mesh radio in your inventory links your ATAK further, and the VOICE tab of the Wave Relay app shows who is transmitting on your ACRE2 frequencies, your PTT and connected teammates.
- TFAR radio jammers and those of other electronic warfare mods now also jam your ATAK, and a Vector 21 measurement or a new key drops the aimed point on the map.

### Détail technique
Profils de réalisme, BFT par points, GPS naturel, fonds satellite (PR #959) :
- `fn_netCond` : condition « profile » (CBA `comspec_atak_native_realism_profile`, règle site `net_profile` ; 3 Milsim par défaut, 2 Standard, 1 Arcade, 0 Personnalisé). La valeur du profil ne s'applique que si le réglage CBA est resté à sa valeur par défaut ; les valeurs du menu Échap puis les règles du site l'emportent clé par clé. Nouvelles clés pli, pliRate, gps, gpsK, gpsFix, battery (site `net_pli`, `net_pli_rate`, `net_gps`, `net_gps_k`, `net_gps_fix`, `net_battery`), relayées par Link 2.0.89+ sans changement de DLL.
- `fn_pli` : variable publique `COMSPEC_ATAK_Pli` [position avec l'erreur GPS, cap, serverTime, précision], envoyée à l'intervalle (±15 %, moitié en mouvement) ; pas de point balise coupée, en réception seule, perdu selon les pertes de la liaison. `fn_localDataRefresh` affiche le dernier point et son âge, cercle de précision.
- `fn_gpsFix` (état dans missionNamespace) : ciel visible (toit, murs, sous-sol, horizon masqué par immeubles et relief, arbres, véhicule), satellites, attente du fix 3 à 40 s, position par le réseau (150 m), dérive lente. `fn_ewEffects` indices 4-6 = erreur, décalage et état totaux (0-3 restent ceux du brouillage pour les alertes).
- Fonds SAT et HYBRID : cartes 88532/88533 (`COMSPEC_RscMapAtakSat` / `Hyb`, `maxSatelliteAlpha=1`), toujours prises par `fn_mapCtrl` (un test interdit `displayCtrl 88530` ailleurs). Menu Échap renommé « Réalisme et réseau », n'envoie que le profil et les écarts.
- À tester en jeu : profils et réglages, points BFT entre deux joueurs, GPS dans un bâtiment / en forêt / au démarrage, fonds Satellite et Hybride avec et sans capture PHOTO.

Radio maillée MPU5 et voix ACRE2 (PR #960) :

- Objet `COMSPEC_ATAK_MPU5` (CfgWeapons, modèle et icône de la radio vanilla, pas de 3D propre). Détection `fn_mpu5` "has" : cet objet, toute classe contenant « MPU5 », ou le réglage CBA `comspec_atak_native_mpu5_items`.
- Réglages `comspec_atak_native_mpu5_range` (6 000 m), `_mpu5_gain` (8 dB), `_mpu5_mbps` (20 Mbit/s), `_mpu5_items`.
- Maillage (`fn_waveRelayAction` "scan") : portée du nœud MPU5 relevée. Liaison (`fn_relayNet` "best") : gain MPU5 ajouté au bilan radio. Relais d'équipe : `fn_relayNet` "list" ajoute les MPU5 des équipiers (type `mpu5`) avec le débit montant qu'elles publient (`COMSPEC_ATAK_Mpu5` [Mbit/s, bonds], au plus 3 bonds), brouillage compris ; exclues des passerelles du maillage.
- Voix ACRE2 : événements CBA `acre_startedSpeaking` / `acre_stoppedSpeaking` (joueur) et `acre_remoteStartedSpeaking` / `acre_remoteStoppedSpeaking` (autres, type 1 = radio) ; fréquence lue dans `acre_sys_data_fnc_dataEvent` "getCurrentChannelData", sinon préréglage (`acre_api_fnc_getPresetChannelField`) ; PTT par `acre_api_fnc_getMultiPushToTalkAssignment`. Chaque téléphone publie `COMSPEC_ATAK_VoiceNet` [[id radio, MHz, canal]...] au changement.
- Limites : ACRE2 n'a pas de radio maillée, la voix reste sur les radios ACRE2 ; « qui émet » = émissions sur les fréquences réglées sur mes radios, portée radio non vérifiée ; joueurs sans ATAK absents de la liste.
- Onglet TALKGROUPS renommé VOIX avec ACRE2 (inchangé sans ACRE2) ; MON NŒUD montre la MPU5. 38 traductions anglaises, 2 clés de stringtable.
- À tester en jeu : objet MPU5 dans l'inventaire (toast, portée sur RÉSEAU, gain sur la liaison), relais d'équipe avec un relais de mission en « réseau exclusivement par relais », onglet VOIX avec PRC-152 / PRC-148 / PRC-343 (fréquences, PTT 1-3, émission locale et distante). Aucun DLL ni migration ; main.pbo à reconstruire au regroupement.

Brouilleurs d'autres mods, API brouilleur, télémètre vers la carte (PR #957) :
- `fn_jammer` (network) : API publique `["add", [source, rayon, force 1-100, durée, camp épargné, id]]` / `["remove", id ou objet]` / `["list"]`, appelable de partout (événement CBA serveur `comspec_atak_native_jammer` hors serveur). Entrées dans `COMSPEC_ATAK_Jammers` (ids `api:`, `obj:`, `cls:`), liste serveur `COMSPEC_ATAK_ExtJammersSrv`, tick 5 s : objets détruits retirés, objets éteints par `COMSPEC_ATAK_JamActive` ou `a3f_jammer_active` à false sortis de la liste publique, entrées effacées par une écriture concurrente d'un client remises.
- Réglage CBA `comspec_atak_native_jam_classes` (« Objets brouilleurs d'autres mods », `Classe:rayon:force`, `entities` avec héritage).
- `fn_zeusJam` : scripts de brouillage TFAR lus par `tf_sendingDistanceMultiplicator` du joueur (brouillage = 1 - multiplicateur), réglage `comspec_atak_native_jam_tfar` (coché par défaut). Vérifié sur le script « TFAR Radio Jammer » (Matzenino).
- `fn_lasePoint` (tactical) : événement CBA local ACE `ace_vector_rangefinderData` (envoyé par ACE après une mesure azimut + distance) et touche CBA « Envoyer le point visé à l'ATAK » (sans touche par défaut : `laserTarget`, sinon `lineIntersectsSurfaces` 5 km en vue optique) -> `fn_markerDrop`. Réglage `comspec_atak_native_lase_link`.
- Guide réseau (section brouillage) et MODULES.md (section 5, FR + EN) mis à jour ; 12 traductions anglaises.
- À tester en jeu : script TFAR Radio Jammer avec TFAR, un objet listé dans le réglage (goniométrie GE, carte, débit), mesure Vector 21 avec ACE, touche dans des jumelles et au désignateur laser. Aucun DLL ni migration ; main.pbo à reconstruire au regroupement.
---

Aucune DLL ni migration : COMSPEC Link reste en 2.0.90.

## Regroupement : Samsung S23 durci, drone refait et plus léger, ATAK compromis visible, touches dans le téléphone (ATAK natif 2.2.1.AB - Denver, COMSPEC Link 2.0.90)

### Résumé joueur
Type : nouveauté
FR : COMSPEC ATAK 2.2.1 « Denver » : le Samsung S23 durci, un drone refait et plus léger, un téléphone compromis qui se voit
- Nouveau téléphone : le Samsung S23 en coque durcie tan, à choisir dans Réglages > Personnaliser > Modèle. Les coques Support JPC, Étui Multicam et Coque nervurée sont retirées : ton téléphone repasse tout seul en Durci S7.
- Drone : la croix et PLEIN ÉCRAN marchent enfin sur l'image, les onglets passent à gauche en grand format, la vue drone devient une vignette sur la carte en petit format, et VOL > FILMER suit un allié ou toi-même. Il pèse aussi moins sur la mémoire du PC (qualité HAUTE en 1024 px).
- Un téléphone déclaré capturé ou compromis le montre : logo COMSPEC Link rouge, carte LIAISON DE DONNÉES COUPÉE avec le motif et l'origine, alliés figés à leur dernière position connue.
- Réglages > Accès et touches refait, avec toutes les touches de l'ATAK modifiables dans le téléphone ; volet SITUATION de la carte lisible avec une vue CONFIGURATION ; arme rangée et visages floutés avec l'app Photo ; Tache de sang sous COMSPEC SSE sans texte cassé ; « Mesurer ma tension » sur soi retiré ; app OSINT en W.I.P.
EN : COMSPEC ATAK 2.2.1 "Denver": the rugged Samsung S23, a reworked and lighter drone, a compromised phone that shows it
- New phone: the Samsung S23 in a tan rugged case, picked in Settings > Customize > Model. The JPC mount, Multicam pouch and Ribbed case are removed: your phone switches back to the Rugged S7 on its own.
- Drone: the cross and FULL SCREEN finally work on the picture, tabs move to the left on the large layout, the drone view becomes a thumbnail on the map on the small layout, and FLIGHT > FILM follows a teammate or yourself. It is also lighter on PC memory (HIGH quality at 1024 px).
- A phone declared captured or compromised now shows it: red COMSPEC Link logo, DATA LINK SEVERED map with the reason and origin, friendly units frozen at their last known position.
- Settings > Access and keys redesigned, with every ATAK key changeable right in the phone; readable SITUATION panel on the map with a CONFIGURATION view; weapon lowered and faces blurred with the Photo app; Blood stain under COMSPEC SSE without broken text; self "Measure my blood pressure" removed; OSINT app now W.I.P.

### Détail technique
Regroupe #937, #941, #942, #945, #948, #949, #950, #951 et #952. `VERSION_STR` « 2.2.1 » (seul comparé), nom affiché « 2.2.1.AB - Denver ». COMSPEC Link 2.0.90 (#952 : 7e colonne méta dans `AdminControl.Poll`) : DLL à recompiler (build_mod.bat). main.pbo reconstruit avec HEMTT une fois toutes les PR fusionnées ; `comspec_sse_biometrics.pbo` repatché par #948. Aucune migration.

#### Samsung S23 durci, trois coques retirées (#937)

##### Résumé joueur de la PR
Type : nouveauté
FR : Nouveau téléphone : le Samsung S23 durci
- Le Samsung S23 en coque durcie tan rejoint les modèles du téléphone : à choisir dans Réglages > Personnaliser > Modèle ou dans les options du mod (Modèle du téléphone).
- Son dos est visible quand on retourne le téléphone, et c'est lui qu'on tient en main en mode photo et selfie.
- Les coques Support JPC, Étui Multicam et Coque nervurée sont retirées : si tu en utilisais une, ton téléphone repasse tout seul en Durci S7, qui reste le modèle par défaut.
EN : New phone: the rugged Samsung S23
- The Samsung S23 in a tan rugged case joins the phone models: pick it in Settings > Customize > Model or in the mod options (Phone model).
- Its back shows when you flip the phone over, and it is the phone you hold in photo and selfie mode.
- The JPC mount, Multicam pouch and Ribbed case are removed: if you used one, your phone switches back to the Rugged S7 on its own, which stays the default model.

##### Détail technique
- Modèle `s23` : coque photo découpée dans la planche de TTRD (`tools/cut_s23.py` → `tools/src/frame_s23.png`, `frame_s23_back.png`), passée par `tools/frame_photos.py` (vraie photo du dos via `BACKS`, plus de panneau dessiné pour ce modèle) ; PAA `phone_{landscape,portrait}_s23{,_night}`, `phone_back_s23`, `dirt_case_s23_{land,port}`, `jpc_s23_ca` (`gen_assets.py <hemtt> s23`, `gen_dirt.py <hemtt> case s23`).
- Écran et boîte visible dans `fn_layoutGet` (paysage : écran 0.2221 0.3449 0.7553 0.6553, visible 0.1392 0.2983 0.8330 0.7036) ; port SMA décalé de -17 px (`fn_antenna`) ; dos (`fn_backView`), Établi (`fn_pageAccessories`), selfie et JPC (`COMSPEC_ATAK_JpcPhone_s23` = `jpc_s7.p3d` + atlas S23, silhouettes presque identiques).
- Retirés : `plate`, `flip`, `ribbed` (rendus Blender de `render_phones.py`, jugés laids) dans les listes du téléphone et CBA, les réglages « Objet exigé », `config.cpp`, `model.cfg`, et 33 fichiers (PAA, P3D, rendus). Profil resté sur l'un d'eux : `fn_layoutGet` le repasse en `s7`.

#### App OSINT en W.I.P (#941)
- `wip=1` sur `class Osint` dans `COMSPEC_ATAK_Apps` (config.cpp) : tuile grisée avec bandeau W.I.P, retirée du dock, message dans le téléphone à l'ouverture ; le code de l'app est gardé.

#### Volet SITUATION de la carte lisible, vue CONFIGURATION (#942)
- Rendus « fold » et « switch » de `fn_formRender` revus (titres non coupés, compteur à part, nom du calque avec OUI/NON ; touche aussi Équipes, Musique et Réglages) ; `fn_layoutGet` : volet à 34 %, 74 % en CONFIGURATION (`COMSPEC_ATAK_InspFull`) ; `fn_pageMap` : bouton et explication de chaque calque, 18 chaînes EN.
- À tester en jeu : volet SITUATION en paysage et portrait, CONFIGURATION, écrans Équipes, Musique et Réglages.

#### Réglages > Accès et touches refait, touches réglables dans le téléphone (#945)
- Nouveau `fn_keybinds` : 19 raccourcis, enregistrés comme le bouton OK de CBA (`cba_keybinding_registry_v3` + saveProfileNamespace) ; capture de la touche dans les KeyDown de `fn_displayLoad` et `fn_open` ; bouton DEFAULT par ligne, alerte quand deux actions partagent une touche.
- À tester en jeu : changer une touche dans le téléphone puis la retrouver dans les options CBA, conflit de touches, DEFAULT.

#### Tache de sang sous COMSPEC SSE, texte « any … true », tension sur soi retirée (#948)
- `fn_bioBloodStain` : le `true` final écrasait le résultat du mode `near` (texte « any … true ») ; sortie en `exitWith`, action visible à moins de 2,5 m d'une vraie trace ; nouveau mode `say` vers `fn_notify` (résultats dans le téléphone) ; actions sous `COMSPEC_SSE_SELF` ; « Mesurer ma tension et SpO2 » sur soi retiré (la mesure d'un blessé reste).
- À tester en jeu : menu ACE près et loin d'une trace, examen, latentes, ADN.

#### App Photo : arme rangée, visages floutés sur l'appareil intégré (#949)
- `fn_photoCam` mode « pose » : SwitchWeapon 299 à l'ouverture, arme rendue à la fermeture ; au déclenchement, `fn_photoFaces` ajoute les cadres au cliché (ou les garde dans `COMSPEC_ATAK_PhotoFaces` hors réseau, pour un renvoi depuis la Galerie).
- À tester en jeu : objectif arrière et selfie, photo d'un joueur, renvoi depuis la Galerie.

#### Drone : croix et PLEIN ÉCRAN cliquables, onglets à gauche, vignette, FILMER (#950)
- `fn_pageDrone` : boutons créés après la surface de la nacelle (qui prenait les clics), mise en page `_leftMenus` ; `fn_droneAction` : cible `UNIT:<netId>` (FILMER un allié, ME FILMER), vue directe annoncée par notification dans le téléphone ; `fn_pageMap` : incrustation réduite à 36 % en petit format.
- À tester en jeu : croix, PLEIN ÉCRAN, AGRANDIR, FILMER un allié et soi-même.

#### Drone plus léger pour la mémoire du PC (#951)
- Distance de vue PiP plafonnée à 1500 m (`COMSPEC_ATAK_DronePipVD`) puis rendue au joueur ; HAUTE 2048 → 1024 px (un profil resté sur 2 passe en HAUTE) ; anneau de textures de rendu 8 → 3 noms par qualité ; aperçu du cliché en 512 px ; jauge RAM (`fn_ramUsage`, `fn_statusUpdate`) qui compte les textures du drone.
- À tester en jeu : mémoire et images par seconde avant/après, drone en BASSE et HAUTE.

#### Terminal compromis : Link rouge et carte dégradée (#952)

##### Résumé joueur de la PR
Type : nouveauté
FR : Un ATAK compromis le montre enfin
- Le logo COMSPEC Link passe au rouge dès que ton téléphone est déclaré capturé ou compromis.
- La carte affiche LIAISON DE DONNÉES COUPÉE, des codes d'erreur, l'IMEI, l'état, le motif et l'origine (manuelle ou automatique).
- Les amis restent figés à leur dernière position connue ; seule ta position GPS reste en direct.
EN : A compromised ATAK finally shows it
- The COMSPEC Link logo turns red as soon as your phone is declared captured or compromised.
- The map shows DATA LINK SEVERED, error codes, the IMEI, state, reason and origin (manual or automatic).
- Friendly units stay frozen at their last known position; only your own GPS position stays live.

##### Détail technique
- Nouveau `fn_compromiseView` (état gardé dans le profil par terminal), `fn_adminNotices`, `fn_compromise`, `fn_mapOnDraw`, `fn_statusUpdate` ; site : méta de l'avis `compromise` (`AtakCompromiseNoticeMeta` : source, état, certificats révoqués, référence) servie par `AtakApiController::adminControlMine` ; COMSPEC Link la passe en 7e colonne de `AdminControl.Poll`.
- À tester en jeu : compromission manuelle depuis /admin/parc-atak et automatique, logo rouge, carte dégradée, levée de la compromission.

## Regroupement : réseau qui suit la météo et la charge, français complet, Resynch qui renvoie tout, Réglages, fluidité, nettoyage et correctifs (ATAK natif 2.2.0.AB - Denver, COMSPEC Link 2.0.89)

### Résumé joueur
Type : nouveauté
FR : COMSPEC ATAK 2.2.0 « Denver » : un réseau qui suit la météo, le français partout, un Resynch complet et une série de correctifs
- Pluie, orage, vent et surcharge des relais comptent maintenant sur le réseau, avec des pertes par rafales ; nouveau bouton Conditions réseau dans le menu Échap, réglable par l'admin ou le Zeus et imposable depuis le site.
- Le réglage Français traduit enfin tout : écrans d'Overwatch, menus, actions ACE et noms automatiques des marqueurs suivent la langue du mod, sans relancer la mission ; les réglages COMSPEC sont réécrits en français clair.
- L'app Synchro renvoie tout ce qui manque à Athena et montre l'état de chaque catégorie ; l'app Réglages montre l'état de la liaison et cherche n'importe quel réglage ; le code de liaison Athena se renouvelle tout seul ; les avis de l'administration (compromission, réinitialisation) arrivent dans le téléphone.
- Téléphone plus fluide, surtout rangé ; photo plein écran sans main devant la vue (touche B pour le flou) ; l'image du drone ne reste plus noire ; pouls et nom du blessé dans l'app Médical ; guide réseau et capture de carte Zeus réparés ; plus de vieilles fenêtres par-dessus le jeu ; le check de démarrage ne part qu'une fois ; les messages du Link gardent leurs accents.
EN : COMSPEC ATAK 2.2.0 "Denver": a network that follows the weather, French everywhere, a complete Resynch and a batch of fixes
- Rain, storms, wind and relay congestion now count on the network, with losses in bursts; new Network conditions button in the Escape menu, set by the admin or Zeus and enforceable from the website.
- The French setting now translates everything: Overwatch screens, menus, ACE actions and automatic marker names follow the mod language, without restarting the mission; COMSPEC settings have clearer French wording.
- The Synchro app resends everything Athena is missing and shows the state of each category; the Settings app shows your link status and searches any setting; the Athena link code renews itself; admin notices (compromise, reset) now reach the phone.
- A smoother phone, especially when stowed; fullscreen camera with no hand in front of the view (B key for blur); the drone picture no longer stays black; pulse and casualty name in the Medical app; network guide and Zeus map capture fixed; no more old windows over the game; the startup check is sent only once; Link messages keep their accents.

### Détail technique
Regroupe #893, #894, #896, #897, #898, #899, #900, #905, #906, #908, #909, #910, #911, #912, #913, #919, #921 et #935. `VERSION_STR` « 2.2.0 » (seul comparé), nom affiché « 2.2.0.AB - Denver » (passage en 2.2.0 : nouveautés réseau et Réglages, nettoyage de #910). COMSPEC Link 2.0.89 (#909, #919, #921) : DLL à recompiler (build_mod.bat). Le bouton du menu principal de #909 est retiré (#893 garde le guide réseau dans Eden seulement) ; le bouton Conditions réseau du menu Échap reste. main.pbo reconstruit avec HEMTT 1.22 (build « 2026.10.19 4689fd6 », `i18n/fr.txt` inclus, 96,8 Mo, sous la limite GitHub de 100 Mo). `comspec_overwatch_connect.pbo` et `comspec_overwatch_main.pbo` repris de #910 puis repatchés avec les sources fusionnées (#898, #900) ; `comspec_sse_core.pbo` patché par #898. Site à déployer : #909 (Contrôle serveur > Réseau et simulation, `POST /api/atak/net-conditions`), #912 (`AtakStartupCheckService`), #921 (avis de l'administration, `run-migrations`).

#### Le réglage Français traduit tout, noms automatiques des marqueurs (#898)
- `i18n/fr.txt` (anglais vers français) chargé en français, assemblé par `tools/i18n.py fr` depuis `tools/i18n_fr_source.tsv` et les stringtables ; `fn_tr` et les écrans (ATAK, connect, SSE) traduisent dès qu'un dictionnaire est chargé ; langue lue dans le profil CBA dès le preInit ; menus CBA vidés au changement de langue ; actions ACE retraduites à chaque affichage. Noms automatiques des marqueurs (palette, LOCK, détections drone, END/FIN) posés dans la langue du mod et relus par `fn_markerAutoName`.
- À tester en jeu : passer en Français puis en English sans relancer (menus CBA, actions ACE, note bêta, écrans connect, SSE) ; marqueurs automatiques posés en anglais relus en français.

#### Photo plein écran : plus de main devant la vue, touche B (#894)
- Bras baissé par défaut en objectif arrière, réglage CBA personnel « Photo : téléphone levé en main » pour la remettre ; B « flou <type> » dans la barre des raccourcis ; B, H et F1 gardés par le mode photo (`fn_keyBlock`).
- À tester en jeu : objectif arrière sans main, réglage coché, selfie, B pendant la visée.

#### App Médical : pouls et nom du patient (#896)
- `fn_medVitals` publie de nouveau la fréquence cardiaque d'un patient vivant (régression 1.97.0) ; repli sur le pseudo du joueur sans indicatif Athena ; en-tête du moniteur agrandi.
- À tester en jeu : moniteur, Suivi des troupes et bilan d'un blessé non relié à Athena.

#### Capture de la carte Zeus (#897)
- « Ciel dégagé à midi » : `_noon` recevait la météo au lieu de la date (erreur setDate, capture arrêtée au départ) ; échelle du rendu plan fixée une fois ; Échap arrête depuis la carte ; pas arrondi à 5 m ; `map_stitch.py` réunit plusieurs captures d'une même hauteur.
- À tester en jeu : module Zeus « Capture de la carte » avec Ciel dégagé à midi, rendu plan, deux villes à la même hauteur.

#### Guide réseau : toutes les rubriques s'ouvrent, guide seulement dans Eden (#893)

##### Résumé joueur de la PR
Type : correctif
FR : Le guide « Réseau et simulation » affiche enfin chaque rubrique
- Cliquer sur une rubrique du guide (Relais, Brouillage, Envois, Le téléphone…) affiche bien son texte et son schéma ; avant, la fenêtre restait sur « Vue d'ensemble ».
- Le bouton du guide disparaît du menu principal du jeu : le guide s'ouvre dans l'éditeur Eden, menu COMSPEC ATAK > Guide : réseau et simulation.
EN : The "Network and simulation" guide now opens every section
- Clicking a section of the guide (Relays, Jamming, Transmissions, The phone…) now shows its text and diagram; before, the window stayed on "Overview".
- The guide button is gone from the game's main menu: the guide opens in the Eden editor, COMSPEC ATAK menu > Guide: network and simulation.

##### Détail technique
- `fn_netGuide` : le gestionnaire LBSelChanged appelait `fn_tr` à chaque clic ; il s'exécute hors du script qui a créé la fenêtre (namespace de l'interface), où la fonction peut manquer : erreur, rien n'était mis à jour (l'affichage initial, fait dans le script, marchait). Textes et schémas de chaque rubrique sont maintenant traduits et mis en forme à l'ouverture ; le clic n'utilise plus que des commandes du moteur et `lbCurSel`. Cause déduite du code et de la capture, non reproduite en jeu.
- Menu principal (TTRD) : `fn_mainMenuButton` et son Extended_DisplayLoad RscDisplayMain retirés, clés de stringtable du bouton supprimées ; seule entrée restante : Display3DEN > COMSPEC ATAK > Guide.

#### Drone : image de la caméra noire dans le téléphone (#899)

##### Résumé joueur de la PR
Type : correctif
FR : L'image du drone ne doit plus rester noire dans le téléphone
- Après la caméra plein écran, après être sorti de l'app Drone ou après un zoom, l'image du drone repart sur un flux neuf au lieu de rester noire.
- Si l'image reste noire malgré tout, le bouton TEST VIDÉO (Drone > RÉGLAGES) montre une nouvelle vue J : une caméra simple accrochée au drone. Dites-nous si J et I s'affichent.
EN : The drone picture should no longer stay black in the phone
- After the full-screen camera, after leaving the Drone app or after zooming, the drone picture restarts on a fresh feed instead of staying black.
- If it still stays black, the VIDEO TEST button (Drone > SETTINGS) shows a new view J: a plain camera attached to the drone. Tell us whether J and I show a picture.

##### Détail technique
- `fn_droneAction` : cible de rendu neuve à chaque création de la caméra du flux (anneau de 8 noms par qualité `comspecdronefeed<q><n>`) ; #830 était revenu à un nom fixe (`comspec_dronelive`) repris juste après un `Terminate` (retour de VUE DIRECTE, page quittée, caméra libérée à 5 s). Caméra créée comme la lettre F du TEST VIDÉO (posée, rendu lancé, `camCommit 0`, accrochée une fois) ; la boucle de la nacelle ne fait plus que l'orienter et ne la raccroche que si le point bouge. Zoom : caméra décrochée, `camCommit`, raccrochée dans la même image. Effet de vision reposé sur chaque cible neuve.
- `fn_pipCheck` : plus de rebranchement de la texture après coup (Drone et Photo, les deux vues noires, étaient les seules à reposer la texture ; Vidéo Live et le TEST VIDÉO la posent une fois). Nouvelle ligne RPT `[CAMERA] DRONE état` toutes les 15 s tant que l'image est à l'écran (cible, âge, rendu, hauteur, distance au drone, accroche, champ, vision, PiP, i/s, PROBLÈME repéré). TEST VIDÉO : la lettre H (thermique, déjà vue bonne) devient J, caméra de script accrochée au drone.
- Cause déduite du code (aucune reproduction en jeu possible ici) : à tester en jeu, vision nuit et jour, avant et après CAMÉRA PLEIN ÉCRAN, avant et après un zoom.


#### Resynch : tout ce qui est nouveau remonte, et compte rendu par catégorie (#900)

##### Résumé joueur de la PR
Type : nouveauté
FR : L'app Synchro renvoie enfin tout, et dit clairement ce qui est parti
- Le Resynch renvoie maintenant aussi la carte du camp, le plan de briefing, les escouades et équipes, la montre Garmin, les tenues modifiées depuis leur dernier envoi, le catalogue de tes objets avec leurs icônes et la liste des mods.
- Seul ce qui manque à Athena repart : les photos déjà transmises et les icônes déjà sur le site ne sont pas renvoyées, et rouvrir l'app ne relance plus tout si le dernier renvoi a moins de 5 minutes.
- Nouvelle page : un état global en haut (à jour, ou ce qui n'est pas parti), une carte par catégorie avec son icône et son état (renvoyé, à jour, en file, pas parti), l'heure du dernier renvoi et un bouton qui affiche le délai avant de pouvoir relancer.
EN : The Synchro app now resends everything, and clearly says what went out
- Resynch now also resends the side map, the briefing plan, squads and teams, the Garmin watch, loadouts changed since they were last sent, your item catalogue with its icons and the mod list.
- Only what Athena is missing goes out again: photos already sent and icons already on the website are not resent, and reopening the app no longer resends everything if the last resync is less than 5 minutes old.
- New page: an overall status at the top (up to date, or what did not go out), one card per category with its icon and state (resent, up to date, queued, not sent), the time of the last resync and a button that shows how long until you can run it again.

##### Détail technique
- `fn_resynchRun` (natif) : après `comspec_overwatch_connect_fnc_forceSyncData`, renvoie la carte du camp (instantané `COMSPEC_ATAK_MkSnap` vidé si ce joueur est le relais du camp, sinon le relais est nommé), `planWeb ["force"]`, `squadSync [true]`, `garminTick ["push"]` (nouveau mode : profil écrit et envoyé sans clore l'activité), tenues déjà sur le site dont le sha local diffère du registre (`arsenalLedger`) via `arsenalPushAll [noms]`, et lit la file d'envoi (`COMSPEC_ATAK_NetQueue` + `SendQueue.Status`). Les tenues jamais partagées ne sont pas envoyées (le partage reste un choix fait dans Tenues Athena).
- `forceSyncData` (connect) : vide `COMSPEC_OpCatalogSentKey`, `COMSPEC_OpModsSentKey` et `COMSPEC_ItemIconsSent` avant la fiche opérateur (catalogue, mods et icônes repartent une fois ; `Item.Icons` n'envoie que les icônes absentes du site), lit le résultat de la fiche (validée, en attente, refusée, écarts signalés) et écrit `COMSPEC_LastResynchItems` = `[[clé, état, titre, détail]...]`. `COMSPEC_LastResynchSummary` (ancien affichage) est gardé tel quel.
- `fn_pageResynch` refait : carte d'état globale, bouton « RELANCER LE RESYNCH » / « PATIENTEZ N S » (délai de 15 s de `forceSyncData`), nouvelle ligne `cells` de `fn_formRender` (grille de cartes, deux colonnes en paysage : icône de l'app, liseré et état en couleur). Lancement automatique à l'ouverture seulement si le dernier renvoi a plus de 5 min.
- `comspec_overwatch_connect.pbo` repatché (`tools/pbo_patch.py`, `fn_forceSyncData.sqf`). main.pbo à reconstruire au regroupement. COMSPEC Link inchangé (2.0.88). `i18n/en.txt` : 76 entrées.
- À tester en jeu : ouvrir Synchro (renvoi auto), relancer (bouton en attente 15 s), rouvrir l'app avant 5 min (pas de renvoi), vérifier sur Athena le plan de briefing, les équipes, la montre Garmin sur la fiche du membre, une tenue modifiée, les icônes du kit ; couper la liaison puis Resynch (ligne « Liaison Athena »).


#### App Réglages : état de la liaison, recherche, Athéna et synchronisation, À propos (#905)

##### Résumé joueur de la PR
Type : nouveauté
FR : L'app Réglages se met à jour
- En haut des Réglages, tu vois tout de suite si ton téléphone est connecté et synchronisé avec le poste.
- Un champ de recherche trouve n'importe quel réglage (par exemple « nuit » ou « boussole ») sans ouvrir chaque catégorie.
- Nouvelles catégories « Athéna et synchronisation » et « À propos du téléphone » : synchro, envois en attente, Resynch, versions et identité du téléphone.
- La touche retour te ramène à la liste des Réglages au lieu de fermer l'app.
EN : The Settings app gets an upgrade
- At the top of Settings you can see right away whether your phone is connected and synced with the command post.
- A search field finds any setting (for example "night" or "compass") without opening every category.
- New "Athena and sync" and "About phone" categories: sync, pending sends, Resynch, versions and phone identity.
- The back key takes you to the Settings list instead of closing the app.

##### Détail technique
- Tout est dans `fn_pageSettings.sqf` (réutilise `fn_athenaStatus`, `fn_offline`, `fn_syncStats`, `fn_phoneIdent`, `fn_extVersion` ; DLL interrogée seulement sur la page À propos), `backSub` pour la touche retour. Carte : « Rétablir la carte par défaut » en deux appuis. Traductions dans `i18n/en.txt`, modèle communautaire régénéré. DLL inchangée.
- À tester en jeu : carte d'état de l'accueil (connecté, non lié, Athéna sur Non), recherche en français et en anglais, touche retour dans une catégorie et dans une recherche, boutons RESYNCH / APP ATHENA / DÉTAIL, versions sur À propos (Link en vert si la DLL correspond).


#### Liaison Athena : code renouvelé tout seul, nouveau code si la session tombe (#906)

##### Résumé joueur de la PR
Type : correctif
FR : Le code de liaison Athena se renouvelle tout seul
- Un code qui expire pendant que l'app Athena est à l'écran est remplacé automatiquement, plus besoin d'appuyer sur NOUVEAU CODE.
- Si ta session Athena tombe en pleine partie (expirée, révoquée, Steam lié à un autre compte), le téléphone te propose aussitôt un nouveau code.
EN : The Athena link code now renews itself
- A code that expires while the Athena app is on screen is replaced automatically, no need to press NEW CODE.
- If your Athena session drops mid-game (expired, revoked, Steam linked to another account), the phone offers a new code right away.

##### Détail technique
- `fn_athenaLink` : troisième paramètre `auto` (3 renouvellements au plus, remis à zéro par une demande manuelle) et action `watch`, appelée toutes les 10 s par un PFH de `XEH_postInitClient.sqf` ; une session ouverte qui tombe sur une erreur de session (SESSION_EXPIRED, révocation, refresh, 401, STEAM_NOT_LINKED) propose un code une fois par perte (notification + indice). `fn_athenaStatus` renvoie le `code` d'erreur brut. Côté site : #904 (déjà sur main).
- À tester en jeu : app Athena ouverte sans session au-delà de 10 min (nouveau code tout seul) ; session ouverte puis appareil révoqué sur le site (notification de code en ~10 s).


#### Fluidité : moins de calculs téléphone rangé, carte et menu des apps allégés, derniers textes traduits (#908)

##### Résumé joueur de la PR
Type : amélioration
FR : Le téléphone coûte moins d'images par seconde, surtout rangé
- Téléphone rangé, la qualité du réseau et le meilleur relais sont recalculés moins souvent : beaucoup moins de calculs de relief et de bâtiments en fond, près des relais surtout.
- La carte ne relit plus tous les marqueurs de la mission chaque seconde : seulement quand un marqueur est posé, modifié ou supprimé.
- Le menu des apps se redessine plus vite (recherche, épinglage, pastilles), et la lueur d'écran et la musique ne travaillent plus pour rien quand elles ne servent pas.
- Derniers textes encore en français en mode anglais traduits (débits du réseau, fiches SEEK et SSE en Bluetooth, trois infobulles de modules Eden).
EN : The phone costs fewer frames per second, especially when stowed
- With the phone stowed, network quality and the best relay are recomputed less often: far fewer terrain and building checks in the background, especially near relays.
- The map no longer rereads every mission marker each second: only when a marker is placed, changed or deleted.
- The apps menu redraws faster (search, pinning, badges), and screen glow and music no longer do work when they are not in use.
- Last texts still in French in English mode are translated (network throughput, SEEK and SSE records over Bluetooth, three Eden module tooltips).

##### Détail technique
- `fn_linkQuality` : cache 6 s téléphone fermé (2 s ouvert) ; un cache vidé exprès (antenne, dégâts, mode avion, brouillage, test de débit) vide aussi le meilleur relais.
- `fn_relayNet "best"` : résultat gardé 1,5 s ouvert / 6 s fermé (`COMSPEC_ATAK_RelayBest`, vidé par `"pin"`) ; `"list"` ne compte plus les téléphones (hasDevice sur chaque joueur) quand aucun relais n'est en service.
- Marqueurs : compteur `COMSPEC_ATAK_MkRev` (EH MarkerCreated / Deleted / Updated). `fn_mapGameMarkers` relit sur changement (sinon 5 s au lieu de 1 s), `fn_localDataRefresh` sur changement (2 s avec marqueurs Athena, sinon 10 s), `fn_markerWebSweep` (relais du camp, 15 s) ne sérialise plus rien sans changement.
- `fn_localDataRefresh` : pistes Athena non parcourues quand l'indicatif a déjà trouvé la sienne. `fn_natoSymbol` : cache vidé d'un coup au-delà de 400 unités (plus de copie des clés à chaque image).
- `fn_feedLink "publish"` : variation de palier diffusée seulement si elle tient deux tours (relais changé ou coupure : aussitôt).
- `fn_screenGlow "tick"` : sortie immédiate réglage serveur coupé (défaut). `fn_musicTick` : `MusicStatus` (DLL, appel bloquant) au plus toutes les 1,5 s, position extrapolée entre deux. `fn_phoneSecurity` : catalogue cherché dans le HashMap. `fn_relayEden` : éditeur relu toutes les 1,5 s sans tour posée.
- Lanceur : pastilles calculées une fois par app, mesure des libellés de tuile gardée (`COMSPEC_ATAK_TileFit`, plus de contrôle créé et supprimé par tuile à chaque redessin).
- i18n : 14 entrées dans `en.txt` (+ modèle communautaire regénéré), 3 infobulles de `modules.hpp` en `$STR_COMSPEC_ATAK_CFG_*`. main.pbo à reconstruire au regroupement. COMSPEC Link inchangé.
- À tester en jeu : barres de signal et bascule de relais téléphone ouvert et rangé (près d'un relais, en marchant), toast « relais perdu » ; poser, déplacer et supprimer un marqueur (carte du téléphone, et sur Athena pour le relais du camp) ; marqueurs Athena reçus ; recherche, épinglage et pastille Messages dans le menu des apps ; musique DLL (barre de lecture, fin de piste) ; aperçu de tour relais dans Eden ; mode anglais : page Réseau > débits, Bluetooth > fiches SEEK.


#### Réseau : météo, surcharge, pertes en rafales et Conditions réseau du menu Échap (#909)

##### Résumé joueur de la PR
Type : nouveauté
FR : Le réseau du téléphone suit la météo et la charge, et se règle depuis le menu Échap
- Pluie, orage et vent fort comptent maintenant sur le réseau : antennes mouillées, bruit des éclairs, signal qui fluctue. Le brouillard, lui, ne change rien, comme en vrai.
- Quand beaucoup de téléphones utilisent le même relais, le canal se remplit : le débit baisse, la latence monte et des paquets se perdent. Les pertes arrivent par rafales, comme sur une vraie radio.
- Nouveau bouton Conditions réseau dans le menu Échap : chacun y voit ce qui coûte du signal en ce moment. Un administrateur du serveur ou un Zeus y règle la météo, la surcharge, les pertes et la latence pour tout le serveur, avec des modèles prêts à l'emploi.
- Les administrateurs de la communauté peuvent imposer ces conditions depuis la page Contrôle serveur du site, qui affiche aussi celles appliquées en jeu.
EN : The phone network follows weather and load, and is set from the Escape menu
- Rain, storms and strong wind now count on the network: wet antennas, lightning noise, a fluctuating signal. Fog changes nothing, as in real life.
- When many phones use the same relay, its channel fills up: throughput drops, latency rises and packets are lost. Losses come in bursts, as on a real radio.
- New Network conditions button in the Escape menu: everyone sees what is costing signal right now. A server admin or a Zeus sets weather, congestion, losses and latency there for the whole server, with ready-made presets.
- Community admins can enforce these conditions from the Server control page of the website, which also shows the ones applied in game.

##### Détail technique
- `fn_netCond` : conditions effectives, règle du site (`net_*` de `COMSPEC_TenantExperience`) > menu Échap (`COMSPEC_ATAK_NetCond`, diffusée, JIP) > CBA. Toutes les lectures de `net_sim`, `net_realism`, `relay_only`, `vehicle_gateways`, `offline_hold`, `video_relays` passent par lui. Nouveaux réglages CBA (tous actifs par défaut) : `net_weather`, `net_weather_k`, `net_congestion`, `net_bg_load`, `net_loss`, `net_loss_floor`, `net_latency_add`.
- `fn_netWeather` : pluie ITU-R P.838 à 4,9 GHz sur le trajet, radômes mouillés, bruit d'orage (`lightnings`, ciel couvert), vent > 8 m/s ; ajoutés au bilan de `fn_radioLink` (le débit suit la MCS) ; sans relais, équivalent sur la liaison directe. Brouillard : aucun effet, affiché.
- `fn_netLoad` : trafic émis publié par joueur (`COMSPEC_ATAK_NetLoad`, 10 s, seulement s'il change), charge du relais = trafic à portée + trafic de fond ; partage au-delà de 100 %, rendement CSMA, file M/M/1, collisions (`fn_linkQuality`).
- `fn_netSend` : pertes Gilbert-Elliott (rafales d'environ 3 envois), aucune perte si la condition est coupée ; perte minimale et latence ajoutée dans `fn_linkQuality`.
- `fn_netConfig` : bouton du menu Échap (`RscDisplayInterrupt`/`RscDisplayMPInterrupt`, sous la barre COMSPEC), fenêtre avec source de chaque condition, modèles, conditions du moment ; application contrôlée côté serveur (admin, hôte, Zeus), notification à tous, envoi au site `NetConditions.Report`.
- COMSPEC Link 2.0.89 : `GetExperience` transmet toutes les clés `net_*` ; `NetConditions.Report` -> `POST /api/atak/net-conditions`. DLL à recompiler (build_mod.bat).
- Site : groupe « Réseau et simulation radio » dans Contrôle serveur (13 règles, « Laisser au serveur » par défaut), bloc « Conditions appliquées en jeu » et bouton pour les garder comme règles (`/back-office/atak/controle-serveur/reseau-adopter`). Stockage dans `experience_config.net_live`, sans migration.
- Guide réseau : rubrique « Météo, surcharge et pertes de paquets ».
- À tester en jeu : bouton Conditions réseau en solo et en multijoueur (admin connecté, Zeus, simple joueur en lecture seule) ; appliquer un modèle et voir la notification chez tous ; arrivée d'un joueur après application (JIP) ; pluie forte et orage (Zeus météo) et lecture de la page Réseau ; plusieurs joueurs qui envoient des photos sur un même relais (surcharge) ; perte minimale 20 % (renvois, remise en file) ; règle imposée depuis le site (verrouillée dans la fenêtre) avec Link 2.0.89 ; bloc « Conditions appliquées en jeu » sur le site.


#### Nettoyage : plus de vieilles fenêtres hors du téléphone (#910)

##### Résumé joueur de la PR
Type : correctif
FR : Plus de vieilles fenêtres par-dessus le jeu
- Le briefing diffusé s'ouvre dans l'app Briefing du téléphone, plus dans l'ancien tableau, Zeus compris.
- La question « Votre unité utilise-t-elle Athéna ? » se répond dans le téléphone (app Athena ou Réglages).
- En Zeus ou sans téléphone, plus aucun écran Athena ne s'affiche au chargement.
EN : No more old windows over the game
- A shared briefing opens in the phone's Briefing app, no longer in the old board, Zeus included.
- The question "Does your unit use Athena?" is answered in the phone (Athena app or Settings).
- As Zeus or without a phone, no Athena screen shows up while loading any more.

##### Détail technique
- Overwatch connect embarqué : `fn_loadGoogleBriefing` et `fn_extensionCallback` (`google_deck_ready`) n'ouvrent plus `COMSPEC_Briefing_Dialog` quand `comspec_atak_native_main` est chargé (l'événement JIP l'ouvrait chez tous les clients, Zeus et sans téléphone compris) ; une notification « ouvrez l'app Briefing » par présentation, aux porteurs de téléphone.
- `XEH_postInitClient` : premier lancement en mission (connexion directe, aperçu Eden, Zeus) sans choix du service Athéna -> `["notify"] call comspec_atak_native_fnc_athenaChoice` au lieu de `COMSPEC_AthenaChoice_Dialog`. La question reste au menu principal et au menu Échap. App Athena : boutons OUI / NON à la place de CHOISIR ; Réglages : bouton « Revoir la question » retiré ; `["show"]` ouvre le téléphone sur Réglages.
- `fn_athenaLink` : l'indice `hintSilent` du code de connexion seulement si le joueur a un téléphone (`fn_hasDevice`).
- Zeus : Ctrl + double-clic sur un joueur n'ouvre plus l'ancien panneau ATAK d'Overwatch (`COMSPEC_ZeusAtakPlayerActionsRegistered`, remplacé par « ATAK · Contrôle Zeus »). Ancien module « tableau des relais » masqué dans Eden (`scope = 1`, les missions existantes se chargent). Logo de démarrage d'Overwatch main plus affiché en double avec celui du natif.
- Code mort retiré d'Overwatch connect (aucun appelant, atak_athena compris) : fenêtres `COMSPEC_Chat_Dialog` (`display.hpp`), `COMSPEC_Hub_Dialog`, `COMSPEC_Callsign_Dialog`, `CfgZeusRelayModules.hpp` (jamais inclus), et 27 fonctions (chat, hub, indicatif, solution de tir, IFF, perte de paquets, `handlePositionUpdateCallback`, `diagIsolateShow`, `tacticalAlertDialogShow`, `openCASDialog`, `showAthenaLinkHelp`, `sseModuleTargets`, `setBriefingScreens`, `checkCASLine`, `updateDangerZone`). ATAK natif : `fn_settingsSave` et `fn_photoTake` (sans appelant).
- `comspec_overwatch_connect.pbo` et `comspec_overwatch_main.pbo` repatchés (`tools/pbo_patch.py --changed`, `--rm`). 3 phrases ajoutées à `i18n/en.txt`.
- À tester en jeu : aperçu Eden sans téléphone et en Zeus avec un profil neuf (aucune fenêtre, pas d'indice Athena) ; avec téléphone et profil neuf (notification Service Athéna, OUI dans l'app Athena) ; briefing Google diffusé par un chef (pas de tableau, app Briefing, Zeus sans fenêtre, joueur JIP) ; Ctrl + double-clic en Zeus ; écran de démarrage (un seul logo) ; menu Échap > Service Athéna toujours là.


#### Modèle du téléphone dans les options des addons (#911)

##### Résumé joueur de la PR
Type : nouveauté
FR : Le modèle du téléphone se choisit aussi dans les options des addons
- Nouvelle liste « Modèle du téléphone » dans Options des addons > COMSPEC > ATAK · Objets par interface.
- Le même choix reste dans le téléphone : Réglages > Personnaliser.
- Le Durci S7 reste le modèle par défaut ; un modèle dont l'objet exigé n'est pas porté reste en S7, et le téléphone te le signale.
EN : The phone model can now be picked in the addon options
- New "Phone model" list in Addon Options > COMSPEC > ATAK · Items per interface.
- The same choice is still in the phone: Settings > Customize.
- The Rugged S7 stays the default; a model whose required item is not carried stays on the S7.

##### Détail technique
- `XEH_preInit.sqf` : réglage CBA LIST client `comspec_atak_native_phone_model` (défaut « Set in the phone ») qui écrit le profil `COMSPEC_ATAK_PhoneModel` ; objet exigé absent -> S7 gardé et notification dans le téléphone (pas de message dans le chat). `fn_pageSettings.sqf` (`COMSPEC_ATAK_ProfSet`) remet la liste CBA à jour quand le modèle change dans le téléphone. Aide des « Required item » : ils autorisent un modèle sans le choisir. 4 phrases dans `i18n/en.txt`.
- À tester en jeu : choisir V2 Enhanced dans les options (châssis changé), objet exigé non porté (S7 + message), changement dans le téléphone (liste CBA suit), relance du jeu (choix gardé).


#### Réglages en français clair, réglages morts retirés, réglages manquants ajoutés (#913)

##### Résumé joueur de la PR
Type : amélioration
FR : Les réglages COMSPEC se lisent en français clair
- Les options des addons COMSPEC (ATAK, Overwatch, SSE) et l'app Réglages du téléphone sont réécrites en français naturel ; chaque aide dit ce que fait le réglage, sa valeur par défaut et qui le règle.
- Des réglages qui ne servaient plus sont retirés, et trois nouveaux apparaissent : portée Wi-Fi des caméras fixes, portée du son Bluetooth, capteur dédié de l'Anti-drone.
EN : COMSPEC settings read in plain French
- COMSPEC addon options (ATAK, Overwatch, SSE) and the phone Settings app have clearer French wording; each help text says what the setting does, its default and who sets it.
- Unused settings were removed and three new ones added: fixed camera Wi-Fi range, Bluetooth sound range, Anti-drone dedicated sensor.

##### Détail technique
- ~600 phrases réécrites (`XEH_preInit.sqf` natif et Overwatch connect, réglages SSE, `fn_pageSettings`, `fn_netConfig`, `fn_netCond`, `fn_moduleRelay`, `fn_pageNetwork`, `fn_pageMap`, `fn_pageStatus`, palette de marqueurs, dossier ACE « Infos tactiques JVN ») ; l'anglais ne change pas (`i18n/en.txt` reprend l'ancienne traduction). `tools/i18n_fr_source.tsv` corrigé, `fr.txt` et modèle communautaire régénérés ; les packs communautaires indexés sur le français repartent vides pour les phrases réécrites.
- Retirés (sans effet) : Overwatch « Fréquence de synchronisation », « Rappel Windows : lier le compte Athena », « Aperçus caméra automatiques » et son intervalle ; SSE « Marqueurs SSE sur la carte ». Ajoutés (déjà lus par le code) : `comspec_atak_native_camera_range` (600 m), `comspec_atak_native_bt_sound_range` (20 m), `comspec_atak_native_drone_sensor`.
- Correctif : `fn_pollOrders` / `fn_pollChat` lisaient `comspec_atak_native_map_id` (inexistant, donc toujours carte 1) ; ils lisent le numéro de carte de l'opération comme `fn_frsSubmit`.
- PBO repatchés : Overwatch connect ; SSE analysis, biometrics, campaign, compat_ace, compat_bii, core, debug, intel, network.
- À tester en jeu : options des addons COMSPEC en Français puis English ; app Réglages en français ; menu Échap > Conditions réseau (mêmes noms) ; sans COMSPEC Link, carte n° 2 : les ordres du poste arrivent.


#### Check de démarrage : une seule fois au premier lancement, renvoi par Dépannage liaison (#912)

##### Résumé joueur de la PR
Type : correctif
FR : Le check de démarrage n'est envoyé qu'une fois
- Le rapport du salon startup-check part une seule fois, au premier lancement, et plus à chaque mission ni à chaque reconnexion.
- Pour le renvoyer : menu Échap > Dépannage liaison > Renvoyer le check de démarrage.
EN : The startup check is sent only once
- The startup-check report is sent once, at first launch, and no longer on every mission or reconnection.
- To send it again: Esc menu > Link troubleshooting > Resend startup check.

##### Détail technique
- `fn_startupCheck` : « déjà envoyé » (date + réponse d'Athena) et identifiant de session gardés dans `profileNamespace` ; boucle de renvoi à chaque reconnexion retirée ; `["reset"]` efface l'état et refait un envoi ; échec réseau retenté à la mission suivante ; champ `scope=once` passé tel quel par la DLL.
- Overwatch connect : `fn_diagAction "startup"` + bouton `BtnStartup` (idc 69) dans `display_diag_isolate.hpp`. Texte d'aide du réglage CBA réécrit (« une seule fois », comment renvoyer).
- Site : `AtakStartupCheckService` garde la déduplication joueur + session ; les anciens mods (sans `scope`) ne postent plus qu'un message par joueur sur 30 jours.
- À tester en jeu : une carte au premier lancement, aucune après changement de mission, reset de la liaison ou relance d'Arma ; bouton « Renvoyer le check de démarrage » (notification puis carte), en français et en anglais.


#### COMSPEC Link : accents UTF-8, Steam en solo, rapports automatiques plus justes (#919)

##### Résumé joueur de la PR
Type : correctif
FR : Les messages du Link gardent leurs accents
- Les textes envoyés par COMSPEC Link au jeu s'affichent avec leurs accents (plus de « momentan�ment »).
- En solo et en hôte local, plus d'erreurs d'envoi à cause d'un identifiant Steam factice.
EN : Link messages keep their accents
- Texts sent by COMSPEC Link to the game keep their accents.
- In solo and local host, no more send errors caused by a placeholder Steam ID.

##### Détail technique
- `Extension.cs` : rappel Arma en pointeurs UTF-8 (`Marshal.StringToCoTaskMemUTF8`, libérés après l'appel, données coupées à 20 000 octets sur une limite de caractère) au lieu de `LPStr` (page de code ANSI). `PairStart` et le code de liaison passent par `ApplySteamUid` (refuse `_SP_`, LOCAL, IA).
- `Extension_Telemetry.cs` : `NotePostError(code, url, response)` sur un 4xx du lot de télémétrie (raison du serveur gardée).
- Overwatch connect : `fn_reportDiag` envoie une empreinte vide (le site hache le message complet, deux pannes différentes donnent deux rapports) ; `fn_extensionCallback` : `google_deck_error` `private`/`not_found` en WARN, sans rapport automatique (le site vérifie la présentation à l'enregistrement, #918).
- À tester en jeu : notification à accents venant du Link ; aperçu Eden en solo sans 400 `telemetry/batch` ; deux pannes d'envoi différentes, deux rapports.


#### Avis de l'administration en jeu : compromission, levée, réinitialisation (#921)

##### Résumé joueur de la PR
Type : nouveauté
FR : Les avis de l'administration arrivent dans le téléphone
- Quand l'administration ou le poste de commandement déclare ou lève une compromission, réinitialise ton ATAK, change son certificat ou ton numéro, le téléphone affiche une alerte sur son écran et une notification, sans rien par-dessus le jeu.
- Une carte reste en haut de l'app Alertes jusqu'à ce que tu appuies sur COMPRIS ; les messages envoyés depuis le parc ATAK du site arrivent de la même façon.
EN : Admin notices now reach the phone
- When the administration or the command post declares or lifts a compromise, resets your ATAK, changes its certificate or your number, the phone shows an alert on its screen and a notification, with nothing over the game view.
- A card stays at the top of the Alerts app until you press GOT IT; messages sent from the website's ATAK fleet page arrive the same way.

##### Détail technique
- Site : `atak_admin_messages.kind` (migration paresseuse, `run-migrations`). `AtakAdminControlRepository::notifyPlayer` appelé par les actions du parc ATAK, la compromission/levée du poste, la régénération de certificat et de numéro. Endpoints joueur `POST /api/atak/admin-control/mine` et `/mine/ack` (session de jeu ou Steam ; seulement ses propres messages).
- COMSPEC Link : `AdminControl.Poll` (une ligne par message, séparée par tabulations) et `AdminControl.Ack` (en file).
- ATAK natif : `fn_adminNotices` interroge toutes les 30 s une fois lié à Athena et téléphone porté ; nouvelle section en tête de `fn_pageAlerts` (rouge compromission, vert levée, ambre réinitialisation/certificat) ; 25 phrases dans `i18n/en.txt`.
- Plus rien sur l'écran du jeu (règle de TTRD : tout reste dans le téléphone) : `fn_fullAlert` devient un bandeau dans l'écran du téléphone, compte à rebours de brèche (`fn_breachTop`) déplacé dans le téléphone, indice `hintSilent` du code Athena et 3 messages d'écran retirés (`fn_athenaLink`, `fn_p2pReceive`, `fn_zeusClient`).
- À tester en jeu : aucune alerte ni message hors du téléphone ; compromission déclarée sur sa fiche dans /admin/parc-atak (alerte, carte Alertes, COMPRIS), puis levée (alerte verte) ; ATAK retiré puis relié (alerte « données réinitialisées »).


#### Crédits du pack chinois : RickLeo et MOYA (#935)
- App Crédits du téléphone, `CREDITS.md` et page ATAK natif du site : RickLeo et MOYA crédités pour le pack de traduction chinois. 2 phrases dans `i18n/en.txt`.


## Regroupement : réseau radio réaliste, apps refaites, synchro allégée, icônes des objets (ATAK natif 2.1.0.AB - Denver, COMSPEC Link 2.0.88)

### Résumé joueur
Type : nouveauté
FR : COMSPEC ATAK 2.1.0 « Denver » : réseau réaliste, apps refaites et téléphone en anglais par défaut
- Le réseau radio suit le relief, les forêts et les bâtiments, avec un guide illustré depuis Eden et le menu principal. Le téléphone n'échange plus que ce qui change, et l'app Réseau montre ce qui part vers Athena.
- Apps refaites : menu des applications (épingles, récentes, filtres), panneau SITUATION de la carte, Distances en tableau de bord, app Athena qui dit clairement si tu es connecté et quoi faire sinon, panneau Tenues Athena qui montre ce qui est déjà sur le site.
- Corrections : clic droit de la Carte, palette des marqueurs (durée, récents, canal Chefs), tutoriel et salles de l'app Plan, textes coupés de Garmin (qui demande maintenant une Montre Garmin au poignet), marche avec l'appareil photo plein écran, module Zeus Relais.
- Le téléphone démarre en anglais (ton choix de langue est gardé). Les objets portés ont leur image sur Athena, les 17 objets SSE ont de vraies icônes, et Eden gagne un tableau de bord SSE.
EN : COMSPEC ATAK 2.1.0 "Denver": realistic network, redesigned apps and an English phone by default
- The radio network follows terrain, forests and buildings, with an illustrated guide from Eden and the main menu. The phone only exchanges what changed, and the Network app shows what goes to Athena.
- Redesigned apps: app launcher (pins, recents, filters), the map SITUATION panel, Distances as a dashboard, an Athena app that clearly says whether you are connected and what to do if not, an Athena outfits panel that shows what is already on the website.
- Fixes: Map right-click, the marker palette (duration, recents, Leaders channel), the Plan app tutorial and rooms, cut-off Garmin text (the app now needs a Garmin watch on your wrist), walking with the fullscreen camera, the Zeus Relay module.
- The phone starts in English (your language choice is kept). Carried items have their picture on Athena, the 17 SSE items have real icons, and Eden gets an SSE dashboard.

### Détail technique
Regroupe #869 (déjà sur main), #872, #873, #875, #876, #877, #878, #879, #880, #881, #882, #884, #886, #887, #889, #890 et #891. `VERSION_STR` « 2.1.0 » (seul comparé), nom affiché « 2.1.0.AB - Denver ». COMSPEC Link 2.0.88 (#884 `Item.Icons`, #886 `HashText` + colonnes `mine`/`sha256` de `ListWardrobes`, #887 gzip/304 et `SyncStats`) : DLL à recompiler (build_mod.bat). main.pbo reconstruit avec HEMTT 1.22 ; `comspec_overwatch_connect.pbo`, `comspec_sse_guide.pbo`, `comspec_sse_main.pbo` repatchés depuis les sources fusionnées (`tools/pbo_patch.py`), `comspec_sse_evidence.pbo` reconstruit par #889. `i18n/en.txt` fusionné puis normalisé, `localisation/template/xx.txt` régénéré.
- Site à déployer avec la version : affichage des icônes (#884) et compaction des réponses au jeu `GameApiResponseCompactor` (#887). VPS : `php run-migrations.php` (table `game_item_icons` de #883 si pas encore passée).
- Tests alignés : l'avertissement BattlEye vit dans `fn_athenaStatus` (test de câblage reco), `$_POST`/`$_GET` posés dans le test de compaction, le tableau de bord SSE hors Eden prévient par `hintSilent` (pas de chat vanilla).

#### Anglais par défaut et traductions restantes (#869)
- English est la valeur par défaut et le premier choix de la langue ; un joueur qui avait choisi une langue la garde. Modules Eden/Zeus d'Overwatch connect traduits (`connect/stringtable.xml`), ~280 textes ajoutés.
- À tester en jeu : profil neuf en anglais ; profil réglé sur Français qui le reste ; modules Eden Overwatch en anglais.

#### Appareil photo plein écran : on peut de nouveau marcher (#872)
- En x1 plus de caméra scriptée (`fn_photoMode`) : on marche et on tourne, le téléphone masqué pour soi seul ; un déplacement ramène le zoom à x1.
- À tester en jeu : marcher, courir, pas de côté en x1 ; zoomer à l'arrêt puis repartir ; photo en x1 ; selfie en marchant.

#### Menu des applications enrichi, app Brèche en W.I.P (#880, #873)
- Épingles (clic droit, 12 au plus), rangée Récentes, sections repliables avec non-lus, puces de filtre, badge NOUVEAU (`new=1`) jusqu'à la première ouverture. Champ `wip=1` de `COMSPEC_ATAK_Apps` : la Brèche est grisée et ne s'ouvre plus.
- À tester en jeu : épingler/désépingler, replier puis se reconnecter, badges, recherche « Breach », anglais.

#### App Carte : clic droit réparé, palette des marqueurs (#875)
- `fn_mapCtxMenu` (`relayAt` renvoyait `true` depuis #850) ; palette : canal « Chefs », « Plusieurs marqueurs », DURÉE (permanent, 10 min, 30 min, 1 h, `fn_markerDrop`), RÉCENTS (6 derniers, profil), altitude et lieu le plus proche.
- À tester en jeu : clic droit sur carte vide, marqueur, étape, équipier ; DURÉE 10 min ; RÉCENTS.

#### App Garmin : texte entier, objet Montre Garmin (#877)
- Polices ajustées dans `fn_pageGarmin` ; nouvel objet `COMSPEC_ATAK_GarminWatch` (emplacement montre, modèle 3D, `items_co` régénéré) ; réglage serveur `comspec_atak_native_garmin_watch` (coché) : sans montre l'app n'enregistre ni n'envoie rien.
- À tester en jeu : textes dans toutes les tailles ; arsenal ACE ; sans puis avec montre ; réglage décoché.

#### Tableau de bord SSE dans Eden (#878)
- Menu COMSPEC SSE > Tableau de bord SSE : 7 onglets en lecture seule (Synthèse et contrôles de cohérence, Entités, Liaisons, Organigramme, Items et membres, Campagne, Site Athena), sans nouvelle DLL ni endpoint.
- À tester en jeu : ouverture/fermeture, mission organisée, onglet Site (Link connecté et démarrage à froid), grosse carte, anglais.

#### App Distances en tableau de bord (#879)
- `fn_pageDistance` réécrit (bandeau mission, carte par mode, heures de pilote, dernières missions, carrière, records) ; carnet local `fn_distanceLog` (`COMSPEC_ATAK_DistBook`, 12 missions). Serveur et site inchangés.
- À tester en jeu : paysage et portrait, anglais, carnet après 2 missions, reconnexion sans doublon.

#### Panneau SITUATION de la carte (#881)
- Formulaire défilant à sections repliables (`fn_formRender`, en-tête fold) : Situation ou fiche de l'unité, Fond de carte, calques par thème avec interrupteurs OUI/NON ; repli et défilement mémorisés.
- À tester en jeu : chaque calque (défilement conservé), fonds de carte, clic sur une unité puis sur le vide, calque imposé par la communauté.

#### App Athena refaite, connexion explicite (#882)
- `fn_athenaStatus` traduit l'état de la DLL en phrase et action (RECONNECTER, OUVRIR LE CANAL, ACTIVER LE SERVICE, CHOISIR), étapes de la liaison visibles, onglets CODE / E-MAIL / STEAM / PORTAIL.
- À tester en jeu : BattlEye, service refusé, compte non lié, code en attente, connecté, canal fermé, session expirée ; anglais et TRÈS GRAND.

#### Tenues Athena : panneau refait et état de synchro (#886)
- `fn_arsenalOverlay*` réécrits, `fn_arsenalHash` / `fn_arsenalLedger` / `fn_arsenalStateInfo` / `fn_arsenalMarkSeen` ; chaque tenue dit si elle est sur le site, modifiée ici ou sur le site ; un bouton n'envoie que les tenues pas à jour.
- À tester en jeu : enregistrer une tenue (Pas sur le site, Sur le site, Modifiée ici), tenue partagée vue « Nouvelle ».

#### Icônes SSE (#889)
- `picture` des 17 objets de `addons/evidence` (rendus Blender, 256 px DXT5) ; PNG 512/256 pour le site.
- À tester en jeu : les 17 icônes dans l'arsenal ACE et dans une caisse.

#### Module Zeus « ATAK · Relais » (#890)
- `COMSPEC_ATAK_Module_Relay` dans `units[]` ; mât par défaut `COMSPEC_ATAK_RelayTower_M12` ; retour au Zeus.
- À tester en jeu : poser le module en terrain ouvert puis près d'un bâtiment ; pylône vu par un autre joueur ; relais dans l'app Réseau.

#### App Plan : texte du tutoriel entier, salle collaborative qui se crée (#876)

##### Résumé joueur de la PR
Type : correctif
FR : App Plan : tutoriel lisible et salles qui marchent
- La fiche du tutoriel s'agrandit selon le texte de l'étape : plus de paragraphe coupé, et les boutons PASSER, PRÉCÉDENT, SUIVANT s'affichent en entier.
- « Créer une salle » crée bien la salle et affiche son code. REJOINDRE et les autres lignes des menus SALLE, EXPORTER et du clic droit répondent au premier clic.
- Les libellés trop longs pour leur bouton (par exemple SALLE avec son code et le nombre de joueurs) sont réduits pour tenir au lieu d'être coupés.
EN : Plan app: readable tutorial and working rooms
- The tutorial card grows with the step's text: no more cut-off paragraph, and the SKIP, PREVIOUS, NEXT buttons show in full.
- "Create a room" now creates the room and shows its code. JOIN and the other entries of the ROOM, EXPORT and right-click menus respond on the first click.
- Labels too long for their button (for example ROOM with its code and player count) are shrunk to fit instead of being cut.

##### Détail technique
- Cause de « Créer une salle ne fait rien » : la carte reçoit aussi les clics faits sur les menus posés par-dessus. Son MouseButtonDown fermait le menu et le redessinait sans ses boutons avant leur ButtonClick. Chaque menu (et le panneau du code de salle) est maintenant une zone bloquée pour la carte, et chaque ligne une zone cliquable jouée par fn_planMap au clic, une seule fois avec son ButtonClick (fn_planUi `_once`). Le code de salle est relu dans le champ au clic sur REJOINDRE.
- Tutoriel (fn_planHelp) : fiche plus large (24 hauteurs de police, 70 % de la carte au plus), hauteur mesurée avec ctrlTextHeight au lieu d'une hauteur fixe ; posée à droite de la palette sans la couvrir.
- Nouveau fn_textFit : réduit la police d'un bouton dont le libellé dépasse (mesuré sur un texte provisoire, ctrlTextWidth ne mesurant que le texte simple), jamais sous 70 %. Utilisé par tous les boutons de l'app Plan et de son aide ; réutilisable par les autres apps.
- Pas de régression commune des polices : fn_fontSet, fn_fontApply, fn_fontText et ui/fonts.hpp n'ont pas changé depuis 1.85.0. Les sous-libellés de la palette n'étaient pas coupés : la fiche du tutoriel les recouvrait.
- À tester en jeu : tutoriel étapes 1 à 10 (en français, en anglais, en TRÈS GRAND) ; SALLE > Créer une salle (le code s'affiche, SALLE XXXXX · 1 en haut) ; taper un code puis REJOINDRE depuis un autre joueur ; lignes du menu EXPORTER et du clic droit ; un clic sur la carte hors menu le ferme toujours.

#### Icônes des objets sur Athena (#884)

##### Résumé joueur de la PR
Type : nouveauté
FR : Les objets portés ont maintenant leur image sur Athena
- Les pages Équipement des joueurs et Kits par rôle peuvent afficher l'icône de chaque arme, tenue, gilet ou sac, comme dans l'arsenal.
- L'image est prise une seule fois dans les fichiers du mod sur ton PC, en arrière-plan, sans rien changer à ta partie.
EN : Carried items now have their picture on Athena
- The Player equipment and Role kits pages can show the icon of each weapon, uniform, vest or backpack, as in the arsenal.
- The picture is read only once from the mod files on your PC, in the background, without affecting your game.

##### Détail technique
- COMSPEC Link 2.0.88 : commande `Item.Icons` ([classe, picture]…). Fil d'arrière-plan unique : demande au site les classes sans icône (`POST /api/atak/item-icons/missing`), retrouve le `.paa` dans le PBO dont le préfixe correspond (Arma 3, DLC, @mods locaux, !Workshop, Workshop Steam, `-mod=`/`-serverMod=`, seul l'en-tête des PBO est lu), garde le premier mipmap ≤ 512 px et l'envoie (`POST /api/atak/item-icons`), ou `not_found`. Une fois par classe et par session, 300 au plus, rien en cas de 429 / réseau en repli. `Item.Icons` `["status"]` donne les compteurs.
- connect.pbo : `fn_sendItemIcons` appelé après un envoi réussi de la fiche opérateur (register/sync) avec le catalogue des objets ; classes déjà proposées pendant la partie ignorées. Ancienne DLL : un INFO dans le journal, pas de nouvel essai.
- Site (PR #883) : décodage PAA (DXT1/3/5 + LZO, ARGB + LZSS) en PNG ≤ 256 px rangé par SHA-256, table `game_item_icons`.
- À tester en jeu : icônes d'un mod Workshop (ex. RHS/CUP) et d'Arma 3 de base visibles après une partie ; « Item.Icons » `status` dans la console de débogage.

#### Synchronisation : seulement ce qui change, et son état dans l'app Réseau (#887)

##### Résumé joueur de la PR
Type : performance
FR : Le téléphone n'envoie et ne retélécharge plus que ce qui change
- Ta fiche opérateur part à la connexion, puis seulement quand ton équipement, ton indicatif, ton grade ou tes versions changent (avec un rappel tous les quarts d'heure). Avant, elle pouvait repartir toutes les dix secondes.
- Le catalogue de tes objets et la liste de tes mods ne partent qu'une fois, puis seulement s'ils changent.
- Quand rien n'a bougé sur Athena (messages, marqueurs, formes, ordres…), le téléphone ne retélécharge plus la liste ; le reste voyage compressé.
- Nouvelle partie « Synchronisation avec Athena » dans l'app Réseau : état de la liaison, volume envoyé et reçu, dernier envoi de ta fiche et pourquoi, ce qui attend d'être envoyé, et le détail canal par canal.
EN : The phone only sends and downloads what changed
- Your operator profile is sent on connection, then only when your gear, callsign, rank or versions change (with a reminder every quarter hour). Before, it could go out every ten seconds.
- Your item catalogue and mod list are sent once, then only when they change.
- When nothing changed on Athena (messages, markers, shapes, orders…), the phone no longer downloads the list again; the rest travels compressed.
- New "Sync with Athena" part in the Network app: link state, volume sent and received, your profile's last send and why, what is waiting to be sent, and the detail channel by channel.

##### Détail technique
- Overwatch connect, `fn_syncOperatorProfile` : le délai d'attente après un refus ou une réponse « pending » n'était jamais appliqué (`exitWith` dans un bloc `then` ne quittait que ce bloc) : une fiche refusée, en 401 au démarrage ou en 404 repartait à chaque contrôle de 8 s. Sortie remise au niveau du script ; un refus durable (4xx, compte bloqué, migration) attend 1, 2, 4… 10 min. Le libellé du journal dit pourquoi la fiche part (« fiche opérateur (changé : gilet, 14.2 Ko) », « rappel 15 min », « première connexion ») : si « → fiche opérateur » revient encore souvent, le champ en cause est écrit. Variables lues par l'app Réseau : `COMSPEC_OperatorLastSentAt`, `COMSPEC_OperatorLastWhat`, `COMSPEC_OperatorLastBytes`, `COMSPEC_OperatorSentCount`.
- `fn_operatorProfileTick` : contrôle léger (empreinte seule, sans catalogue ni mods, nouveau 4e paramètre `_light` de `fn_buildOperatorProfile`), rappel « heartbeat » toutes les 15 min qui garde à jour les écarts et `update_required` calculés par le site. `item_catalog` comme `mods` : envoyés à l'enregistrement puis seulement quand leur clé change (`COMSPEC_OpCatalogSentKey`, enregistrée au succès) ; les clés ne partent plus au site. Côté site, un sync sans catalogue relève les objets depuis l'équipement et garde le nom et le mod déjà connus (`GameEquipmentRepository::recordItems`).
- COMSPEC Link 2.0.88, `Extension_SyncStats.cs` : `SendTimed` (GET des interrogations et la plupart des POST) annonce `Accept-Encoding: gzip`, envoie `If-None-Match` avec l'empreinte gardée de la dernière réponse ; sur 304 la copie gardée est resservie en 200 (96 réponses de 512 Ko au plus), l'appelant ne voit aucune différence. Compteurs par chemin d'API (octets envoyés et reçus sur le réseau, appels, réponses inchangées, octets évités, âge et dernier code), fenêtre glissante de 5 min, commande `SyncStats` (en mémoire, sans réseau). File d'envoi et lots de télémétrie comptés aussi. Fiche opérateur postée en gzip (`HttpJsonBody` la décompresse déjà).
- Site, `GameApiResponseCompactor` (appelé par `Application` juste avant l'envoi) : pour COMSPEC Link seulement (User-Agent `COMSPECExtension/` ou `COMSPECATAKNativeExtension/`), GET `/api/…` 200 JSON : `ETag` faible sur le corps, 304 sans corps si `If-None-Match` correspond, sinon gzip au-delà de 1 Ko. Navigateurs et site inchangés. Une réponse qui contient l'heure du serveur (ordres) ne vaut jamais 304, elle est seulement compressée.
- ATAK natif, app Réseau : partie « Synchronisation avec Athena » (`fn_syncStats` nouveau, `fn_pageNetwork`) ; bandeau de dépannage d'Overwatch : « Débit » compte maintenant les deux sens (avant, seulement les retours).
- À tester en jeu : la fiche part une fois à la connexion puis à un changement de tenue (journal « changé : … ») ; la page Réseau montre des réponses « inchangées » quand rien ne bouge ; messages, marqueurs, formes et ordres arrivent toujours ; la fiche et les écarts restent à jour sur le site (fiche opérateur du membre).

#### Réseau : bilan radio réaliste et guide du réseau (#891)

##### Résumé joueur de la PR
Type : nouveauté
FR : Un réseau radio plus réaliste, et un guide pour le comprendre
- Le signal tient compte du relief entre toi et le relais, de la forêt et des bâtiments traversés : monter sur une crête ou sortir d'un sous-bois redonne des barres.
- Au bord de la couverture, tu peux encore recevoir sans pouvoir émettre (« Réception seule ») : tes envois attendent et partent dès que tu te rapproches. Un message qui échoue trois fois repart en file au lieu d'arriver quand même, et une photo lourde retarde le message suivant.
- Près d'un relais, ton signal résiste mieux à un brouilleur ; au bord de la couverture, il tombe vite.
- Nouveau guide « Réseau et simulation » avec schémas : bouton COMSPEC ATAK en haut à gauche du menu principal, et menu COMSPEC ATAK dans l'éditeur Eden.
EN : A more realistic radio network, and a guide to understand it
- The signal accounts for the terrain between you and the relay, and for the forest and buildings in the way: climbing a ridge or leaving a wood brings your bars back.
- At the edge of coverage you can still receive without being able to transmit ("Receive only"): your messages wait and leave as soon as you get closer. A message that fails three times goes back to the queue instead of arriving anyway, and a heavy photo delays the next message.
- Near a relay your signal holds up better against a jammer; at the edge of coverage it drops fast.
- New "Network and simulation" guide with diagrams: COMSPEC ATAK button at the top left of the main menu, and COMSPEC ATAK menu in the Eden editor.

##### Détail technique
- Nouveau `fn_radioLink` : bilan radio en dB à 4,9 GHz. Marge en espace libre `20 log10(portée / distance)` (la portée de mission reste la portée d'un lien dégagé), profil du terrain avec courbure terrestre (k = 4/3), diffraction en lame de couteau ITU-R P.526 sur l'arête principale (+2 dB par crête secondaire, plafond 40 dB), feuillage par Weissberger là où le rayon passe à moins de 20 m du sol (arbres comptés par `nearestTerrainObjects`, +20 % sous la pluie), bâtiments traversés sur les 1 500 derniers mètres (12 dB chacun).
- `fn_relayNet` : `"link"` renvoie aussi marge, pertes détaillées et MCS ; débit par modulation adaptative (8 paliers) et perte `40·e^(-marge/5)`. `"best"` : passe rapide (relief) puis calcul complet sur les 3 meilleurs, voie descendante +4 dB (frange réception seule), terminal de bord +3 dB, évanouissements ±2 dB par relais, plus en mouvement. Bonds entre répéteurs sur le même bilan.
- `fn_linkQuality` : causes en dB dans la page Réseau, clés `tx`, `margin`, `jitter` ; relief non compté deux fois avec un relais ; pluie et brouillard sans effet à 4,9 GHz (seul le feuillage mouillé compte) ; brouilleurs selon le rapport J/S (burn-through) ; batterie < 10 % émission réduite ; pertes et gigue en mouvement rapide.
- `fn_netSend` : canal partagé (`COMSPEC_ATAK_NetBusyUntil`), gigue, urgents en tête de file, 3e échec = retour en file, réception seule = file. Maillage Wave Relay (`fn_waveRelayAction`) sur le même bilan.
- Réglage serveur « Réalisme de la simulation radio » (`comspec_atak_native_net_realism`) : Réaliste (défaut), Exigeant, Simplifié (ancien modèle, portée x 0,35 derrière le relief).
- Guide `fn_netGuide` (10 sections, 5 schémas FR/EN `data/netguide_*.paa`, `tools/gen_net_guide.py`) : menu Eden COMSPEC ATAK > Guide, bouton du menu principal (`fn_mainMenuButton`, Extended_DisplayLoad de RscDisplayMain). `fn_langApply` : un texte avec « % » sans numéro est maintenant traduit tel quel.
- À tester en jeu : barres et page Réseau derrière une crête, en forêt et en ville ; frange réception seule ; brouilleur près et loin d'un relais ; file d'envoi (photo puis message) ; guide depuis Eden et depuis le menu principal ; FPS avec 10 relais.

---

## Regroupement : mode hors connexion, points de passage, fond photo de la carte (ATAK natif 2.0.1.AB - Denver, COMSPEC Link 2.0.87)

### Résumé joueur
Type : nouveauté
FR : COMSPEC ATAK 2.0.1 « Denver » : hors connexion, points de passage, fond photo, et tout ce qu'apportait la 1.97
- Sans signal, le téléphone garde ses dernières données et le dit ; messages, comptes rendus, MEDEVAC et alertes attendent et arrivent au retour du réseau, et tes SMS et messages restent après une reconnexion. La batterie dépend de l'app ouverte et de la musique, et les notifications se trient par Messages, Missions et Réseau.
- Points de passage refaits : clic droit pour poser, nom et type de chaque étape, itinéraire partagé en direct avec ton groupe. La capture de la carte devient un vrai fond photo qui s'affine au zoom, sur le site et dans le téléphone.
- Déjà en 1.97 : marqueurs au clic droit, fiche de chaque relais, pylônes 3D refaits, partage Bluetooth (photos, musiques, itinéraires, fiches SEEK), notes de reco transmises au poste, app JTAC stable.
- Aussi en 1.97 : nouvelle app Garmin, sang réaliste et nettoyage sans kit, tension et oxygène mesurés, téléphone verrouillable avec touche retour, visages de tes alliés floutés sur tes photos, kits de ton rôle dans les tenues Athena.
EN : COMSPEC ATAK 2.0.1 "Denver": offline mode, waypoints, photo basemap, and everything 1.97 brought
- With no signal the phone keeps its latest data and says so; messages, reports, MEDEVAC and alerts wait and arrive when the network is back, and your texts and messages survive a reconnection. Battery drain depends on the open app and the music, and notifications can be sorted by Messages, Missions and Network.
- Waypoints redone: right-click to place, a name and type for each waypoint, the route shared live with your group. The map capture now becomes a real photo basemap that sharpens as you zoom, on the website and in the phone.
- Already in 1.97: right-click markers, a card for each relay, redone 3D towers, Bluetooth sharing (photos, music, routes, SEEK files), recon notes reaching the command post, a stable JTAC app.
- Also in 1.97: new Garmin app, realistic blood and cleaning without a kit, measured blood pressure and oxygen, a lockable phone with a back key, your allies' faces blurred in your photos, your role's kits in the Athena wardrobes.

### Détail technique
Regroupe #857, #858 et #859. Première version de MAJEUR 2 décidée par TTRD : `VERSION_STR` « 2.0.1 » (seul comparé) et nom affiché `VERSION_NAME` « 2.0.1.AB - Denver » (docs/VERSIONING.md). La 1.97.0 n'a pas été annoncée : ce Résumé joueur la couvre aussi. COMSPEC Link inchangée (2.0.87). main.pbo reconstruit avec HEMTT 1.17.2, empreinte « 2026.10.18 aa7ecf3 » posée par tools/stamp_build.py puis remise à vide.

#### Points de passage refaits : pose au clic droit, noms, types, itinéraire partagé en direct (#857)
##### Résumé joueur de la PR
Type : nouveauté
FR : Points de passage refaits, plus rapides et lisibles
- Clic droit sur la carte puis « Point de passage » : une fiche s'ouvre pour donner un nom, choisir le type (étape, départ, point de contrôle, ralliement, objectif, LZ, arrivée) et le partage, puis VALIDER. Avec « À la suite », on enchaîne les étapes ; un clic gauche pose tout de suite.
- L'itinéraire est partagé en direct avec ton groupe (ou ton camp, ou toi seul) : chaque étape posée, modifiée ou supprimée apparaît aussitôt chez les autres, sans couper leur navigation.
- Sur la carte : traits épais à la couleur de chaque étape, flèche de sens, distance et azimut des tronçons, étapes numérotées et nommées. Clique une étape pour la renommer, changer son type, la déplacer, la remonter ou la supprimer.
- L'app Itinéraire montre le type de chaque étape, permet d'inverser l'itinéraire et de « Faire naviguer le groupe ».
EN : Waypoints redone, faster and easier to read
- Right-click the map then "Waypoint": a card opens to give a name, pick the type (waypoint, start, checkpoint, rally point, objective, LZ, finish) and the sharing, then CONFIRM. With "In a row" you chain waypoints; a left-click places one right away.
- The route is shared live with your group (or your side, or only you): every waypoint placed, edited or deleted shows up at once for the others, without stopping their navigation.
- On the map: thick lines in each waypoint's colour, direction arrows, leg distance and bearing, numbered and named waypoints. Click a waypoint to rename it, change its type, move it, reorder it or delete it.
- The Route app shows each waypoint's type, can reverse the route and "Send group on route".

##### Détail technique
- `fn_wpAction` : étapes `[pos, nom, type]` (une étape `[pos, nom]` d'avant vaut le type WP), sept types (WP, SP, CP, RP, OBJ, LZ, FIN) avec couleur et symbole CfgMarkers, nom automatique par type (« CP 3 »), actions open / anchor / place / edit / new / rename / retype / move / up / down / reverse, partage profil `COMSPEC_ATAK_WpShare` (0 moi seul, 1 groupe par défaut, 2 camp) et pose à la suite `COMSPEC_ATAK_WpSeries` (oui par défaut). Chaque modification part en direct une seconde après la dernière (`sync`, événement `comspec_atak_native_waypoints` avec le drapeau « en direct » : pas de vibreur, navigation gardée) ; « Faire naviguer le groupe » (`push`) envoie et lance la navigation. 30 étapes au plus (20 avant).
- `fn_wpCard` (nouveau) : fiche du mode POINTS DE PASSAGE sur la carte (nom, type, partage, à la suite, VALIDER ; pour une étape existante TERMINÉ, DÉPLACER ICI, AVANT / APRÈS, NAVIGUER, SUPPRIMER). `fn_wpDraw` (nouveau) : rendu de l'itinéraire sorti de `fn_mapOnDraw` (traits épais, chevrons, distances au zoom, tronçons passés estompés, point choisi et trait vers le curseur). `fn_wpAt` (nouveau) : étape sous le curseur.
- Carte : clic droit en mode POINTS DE PASSAGE = point de pose (ne quitte plus l'outil), clic gauche sur une étape = sa fiche (aussi en sélection). Menu clic droit : POINT DE PASSAGE, et sur une étape MODIFIER L'ÉTAPE, NAVIGUER VERS L'ÉTAPE, SUPPRIMER L'ÉTAPE.
- Serveur : un itinéraire effacé n'est plus rejoué aux arrivants.
- Bâti sur le regroupement 1.97.0 (#853) pour reprendre son menu clic droit ; main.pbo non reconstruit (fait au regroupement).

**À tester en jeu** : clic droit > Point de passage, nom saisi puis VALIDER, Entrée dans le champ nom, À la suite coché et décoché, clic gauche en mode étapes ; clic sur une étape (sélection et mode étapes), renommer, changer le type, DÉPLACER ICI après un clic droit, AVANT / APRÈS, SUPPRIMER ; à deux dans le même groupe, une étape posée ou supprimée apparaît chez l'autre sans couper sa navigation ; partage Camp avec un joueur d'un autre groupe ; Moi seul n'envoie rien ; « Faire naviguer le groupe » ; arrivée en cours de partie (itinéraire du groupe rejoué) ; lisibilité des traits et noms sur carte claire et sombre, en mini et en plein écran ; envoi d'un itinéraire en Bluetooth.

#### Capture de la carte multi-hauteurs : orthophoto à niveaux de détail pour le site et le téléphone (#858)

- `fn_mapCapture` (satellite) : téléobjectif (fov 0,12) monté environ 6 fois plus haut pour la même emprise, presque sans perspective ; cadrage mesuré par `worldToScreen` et altitudes du terrain sous chaque image notés dans le RPT (`CMAPL`, `CMAPS`). Options Zeus « Ciel dégagé à midi » et « Cacher unités et véhicules », lignes en aller-retour, estimation en Go et minutes.
- `tools/map_stitch.py --pyramid` : chaque point du sol pris dans l'image la plus centrée puis redressé sur le relief ; toutes les hauteurs dans une pyramide z/x/y WebP en mètres Arma. `--upload` l'envoie au site, `--addon` fabrique la PBO du téléphone (`@COMSPEC_ATAK_Photo`, PAA via HEMTT ≥ 1.15, sinon JPG).
- Site : `AtakMapPhotoStore` + `AtakMapPhotoController` : `GET /map-photo/{carte}/{rendu}/{z}/{x}/{y}.webp` (tuile absente découpée dans son ancêtre, cache), `POST /api/atak/map-photo/upload` en paquets ZIP avec la clé plateforme `X_COMSPEC_KEY`. Calque « Photo COMSPEC » dans `atak_map_fond_layers`. VPS : `storage/map-photo/` inscriptible par PHP.
- ATAK natif : `fn_photoMap` dessine les tuiles visibles sous les symboles quand le style PHOTO est choisi (Réglages > Carte, CALQUES), proposé seulement si la carte a un fond.
- À tester en jeu : module Zeus « Capture de la carte » sur Stratis (Satellite, hauteurs 1200 et 400, carte entière puis une ville) sans interface ni unités ; `map_stitch.py --pyramid` avec le RPT ; `--upload` puis calque Photo COMSPEC sur la carte ATAK web ; `--addon` et `@COMSPEC_ATAK_Photo` : fond PHOTO dans le téléphone, tuiles jointives (sinon donner la valeur `COMSPEC_ATAK_PhotoPx`).

#### Fonctionnement hors connexion, consommation par app, centre de notifications trié (#859)

- `fn_offline` (nouveau) : `online`, `tick` (boucle de 2 s de la file), `hold`, `var`, `state`. L'état réseau reste celui de `fn_linkQuality` / `fn_relayNet`. Les variables de COMSPEC Link (messages, alertes, ordres, renseignement) sont figées hors réseau ; `remoteSync` ne tourne plus sans signal.
- Réceptions retenues hors réseau puis rejouées au retour (`CBA_fnc_localEvent`) : medevac, medevacStatus, bda, report, reportRcpt, orderAck, cas, logi, alert, panic, waypoints (y compris les mises à jour en direct de #857).
- Sauvegarde profil `COMSPEC_ATAK_OfflineSave` par `COMSPEC_ATAK_MissionKey` (toutes les 30 s, disque toutes les 2 min). `fn_netSend` : clé optionnelle (la dernière remplace la précédente en file), envois urgents gardés si la file est pleine ; `fn_markerWeb` passe par la file ; `fn_chatSend` : messages en attente affichés « En attente de réseau ».
- App Réseau : section « Hors connexion » ; Messagerie et Tâches : heure des données. Batterie selon l'app ouverte (carte et GPS, flux vidéo, données) et la musique. Notifications : filtres Messages, Missions, Réseau. CBA (ATAK · Simulation) : `comspec_atak_native_offline_hold`, `comspec_atak_native_offline_save`. Pas de changement de DLL.
- À tester en jeu : sous-sol ou mode avion (notification « Hors connexion », « fil du HH:MM ») ; message Athena hors réseau « En attente de réseau » puis envoyé ; compte rendu / MEDEVAC / alerte d'un autre joueur reçus seulement à la sortie avec « Synchro après … » ; marqueur modifié deux fois hors réseau = un seul envoi ; quitter et rejoindre : SMS et messages toujours là ; app Réseau (Hors connexion, détail batterie) ; filtres du centre de notifications.

---

## Regroupement : marqueurs, relais, Bluetooth, app Garmin, sang et santé, téléphone, flou des visages, kits par rôle, Reco, JTAC (ATAK natif 1.97.0, COMSPEC Link 2.0.87)

### Résumé joueur
Type : nouveauté
FR : Relais et Bluetooth refaits, app Garmin, sang et santé réalistes, visages floutés, marqueurs au clic droit
- Carte : clic droit à l'endroit voulu puis VALIDER pour poser un marqueur (canal Général par défaut), alertes effaçables, fiche de chaque relais au clic (fréquence, perte, santé) ; pylônes 3D refaits et réseau par relais seulement si la mission l'impose. La carte JTAC ne saute plus et le 9-LINE s'affiche sur Overwatch.
- Bluetooth : envoie photos, musiques, position, itinéraire, plan ou fiches SEEK à un téléphone appairé ; SEEK montre ce que l'équipe a déjà fait sur une personne ; le vibreur BFT transmet un message rapide. Les notes de l'app Reco arrivent enfin au poste, et l'app Athena prévient quand l'anti-triche du jeu bloque la liaison.
- Nouvelle app Garmin (Body Battery, cœur, stress, sommeil, activités, défis), envoyée sur ta fiche du site ; sang sur l'écran réaliste (Discret, Réaliste ou Gore), nettoyage sans kit, tension et oxygène affichés seulement après une mesure, ADN sur les taches de sang, SALUTE dans l'ordre.
- Téléphone plus propre : verrouillage, touche retour partout, mini déplaçable à la souris, alertes réglables, photo et selfie en mini ; les visages de tes alliés sont floutés sur tes photos (4 styles), les profils Tinder et SSE s'affichent sur fond noir, Zeus peut capturer toute la carte et les kits de ton rôle sont proposés dans les tenues Athena. MP3 refusés désormais lus et menu COMSPEC SSE d'Eden réparé.
EN : Relays and Bluetooth redone, Garmin app, realistic blood and health, blurred faces, right-click markers
- Map: right-click where you want, then CONFIRM to place a marker (Global channel by default), alerts can be cleared, click a relay to see its card (frequency, loss, health); 3D towers redone and relay-only networks when the mission requires it. The JTAC map no longer jumps and the 9-LINE shows on Overwatch.
- Bluetooth: send photos, music, position, route, plan or SEEK files to a paired phone; SEEK shows what the team already did on a person; the BFT buzzer sends a quick message. Reco app notes finally reach the command post, and the Athena app warns when the game's anti-cheat blocks the link.
- New Garmin app (Body Battery, heart rate, stress, sleep, activities, challenges), sent to your member file on the site; realistic blood on the screen (Subtle, Realistic or Gore), cleaning without a kit, blood pressure and oxygen shown only after a measurement, DNA on blood stains, SALUTE in order.
- Cleaner phone: lock screen, back key everywhere, mini screen movable with the mouse, adjustable alerts, photo and selfie in mini; your allies' faces are blurred in your photos (4 styles), Tinder and SSE profiles show on a black background, Zeus can capture the whole map and your role's kits are offered in the Athena wardrobes. Refused MP3 files now play and the Eden COMSPEC SSE menu is fixed.

### Détail technique
Regroupe #840, #841, #844, #845, #847, #848, #849, #850, #851 et #854 (une seule version : ATAK natif 1.97.0, COMSPEC Link 2.0.87 ; main.pbo reconstruit depuis les sources avec HEMTT 1.17.2, empreinte « 2026.10.18 59e32bf » posée par tools/stamp_build.py puis remise à vide ; towers.pbo de #850 repris tel quel, seule à changer ses sources ; comspec_overwatch_connect.pbo de #851 repatché avec les sources connect de #854 par tools/pbo_patch.py).

#### Marqueurs au clic droit (VALIDER, à la suite, canal Général), fiche d'étiquette, alertes effaçables (#840)

- Nouvelles fonctions `fn_markerPlace` (ouverture, point de pose, VALIDER, canal, repli) et `fn_markerDeletable` (joueur / alerte locale / mission). `fn_mapMouseButtonDown/Up` arment le clic droit en mode MARQUEUR comme en sélection ; le double clic ouvre la palette au point.
- Palette : point de pose au clic droit (anneau + aperçu), VALIDER, « À la suite », replier / déplier (profil), fermer, ligne CANAL (Général par défaut ; repli Camp puis canal courant si le serveur refuse, avec bandeau). Réglages d'addon ATAK · Carte : « Menu de pose des marqueurs » (palette / éditeur complet) et « Canal des marqueurs ».
- Fiche d'étiquette refaite (liseré couleur, nature MISSION / JOUEUR / ALERTE, grille · distance · azimut, Y ALLER, CENTRER, MODIFIER, SUPPRIMER / EFFACER). Alertes effaçables localement : fiche, Suppr, clic droit, « Effacer les alertes (N) ».
- À tester en jeu : clic droit > Mettre un marqueur puis VALIDER ; « À la suite » ; canal Général vu par un autre groupe ; réglage Éditeur complet ; EFFACER sur WIA / CONTACT ; fiche en mini sans recouvrir OUTILS ; anglais.

#### Reco transmise au poste, point pointé sur la carte, alerte BattlEye, points simples visibles (#841)

- Cause des recos perdues : la commande synchrone `RECON.Note` de COMSPEC Link postait le JSON tel que livré par `callExtension` (guillemets doublés). PHP lisait un corps vide et répondait 400 « Indiquez une observation » ; rien n'était enregistré (RECO 0 sur le site). 2.0.87 passe le corps par `EnrichAtakPayload` (normalisation + jeton de session, Steam, version), dans les chemins synchrone et asynchrone.
- `POST /api/recon/notes` : une entrée « Renseignement » au journal Activité par nouvelle note (type, texte, carroyage, auteur), et un `error_log [atak/recon-notes]` quand une note est refusée vide ou illisible.
- Relais Discord par communauté « Notes de reco » (Back-office > Intégrations, groupe Téléphone ATAK, désactivé par défaut, salon commun ou salon à part) : une carte par note (type, observation, fiabilité, carroyage, auteur), envoyée après la réponse, 30 par minute et par communauté au plus, mentions et liens neutralisés. `ReconNoteDiscordService`, clé `atak_recon_notes`.
- App Reco : bouton POINTÉ CARTE (clé `recoPick` de `COMSPEC_ATAK_State`, lue par `fn_mapMouseButtonDown`, retour sur l'app avec le carroyage 8 chiffres).
- App Athena : si la DLL de liaison ne répond pas (relu toutes les 20 s), pastille « BATTLEYE ACTIF » et encadré rouge avec la marche à suivre. Même indice dans le contrôle d'intégrité du mode natif seul.
- Points simples (`mil_dot`, `hd_dot`) : le point ne remplit qu'un quart de son image et la couleur par défaut est noire. Téléphone (`fn_mapGameMarkers`) : symbole doublé, éclairci s'il est trop sombre. Site (`arma-map-markers.js`) : rond plein cerclé de blanc au lieu du PNG, et le libellé « Repère · indicatif » n'est plus masqué (seul « Repère » nu l'est).
- Non testé en jeu. À tester : une reco envoyée apparaît sur le site (calque Notes reco, fil RECO), au journal Activité et dans Discord ; POINTÉ CARTE ; jeu lancé avec BattlEye puis sans ; point simple noir posé en jeu, visible au téléphone et sur le site. Il faut recompiler la DLL (build_mod.bat) et reconstruire main.pbo au regroupement.

#### Menu SSE d'Eden, MP3 refusés, check de liaison sur Discord (#844)

- @COMSPEC_SSE guide : les actions du menu d'Eden appelaient `BIS_fnc_openFieldManual`, qui y cherche un écran parent et tombe sur un booléen (erreur `createDisplay` ligne 40 de fn_openFieldManual.sqf). Elles passent par `comspec_sse_fnc_openGuide`, qui dans Eden attend la fermeture du menu (uiSleep 0.05) puis ouvre sa propre fenêtre sur l'écran 313 (liste des chapitres CfgHints + texte). comspec_sse_guide.pbo patché avec tools/pbo_patch.py.
- DLL native (MusicPlayer.cs) : si MCI refuse d'ouvrir un fichier local (ERR277 = MCIERR_INTERNAL), copie dans le cache sans balise ID3v2 ni octets avant la première trame MPEG (deux trames valides de suite), second essai ; sinon le vrai format est reconnu (ftyp, WebM, OggS, fLaC, page web). À recompiler avec build_mod.bat.
- Site : `App\Support\MusicGameCheck` (même recherche de trame), appelé par `MusicTrackStorage::storeFromUpload` (MP3 réparé sur place, faux MP3 refusé, note dans la réponse) et par la nouvelle action groupée `game_check` de /admin/musique et /back-office/ressources/musique (taille et sha1 mis à jour). Pas de migration.
- fn_startupCheck : envoi à la liaison (`COMSPEC_AthenaReady` vrai, identité du téléphone reçue) ou, sans liaison 2 min après le contrôle d'installation, avec l'erreur (`comspec_overwatch_auth_error`, `COMSPEC_LinkDetail`) ; nouveau rapport à chaque nouvelle liaison dans la mission. Champ `trigger` (link, link_failed) ; étapes `link`, `phone` (numéro, IMEI, attribué par Athena ou provisoire), `terminal` (objet porté, modèle) ; l'étape `account` est remplacée par `link`. Aucune nouvelle commande DLL.
- TEST VIDÉO : le flux réel du drone (I) est créé en premier.

#### App JTAC : carte et fiche stables, géométrie JTAC sur Overwatch, temps d'écran dépliable (#845)

- `fn_jtacDraw` n'enregistre la vue qu'une fois la carte calée (3 recalages au plus, comme `fn_planMap`) ; la fiche `formRender` est inscrite dans `COMSPEC_ATAK_ScrollGroups`. `fn_jtacAction` « brief » pose l'objet `geo`, `fn_firesAction` « send9 » l'ajoute au JSON de `SubmitNineLine` (DLL inchangée).
- Site : colonne `atak_nine_line.geo_json` (bootstrap idempotent, run-migrations.php, schema.sql), `CasNineLineRepository::sanitizeGeo`, `/api/cas` renvoie `geo`, calque « Appuis JTAC » dans Overwatch Beta. Back-office Temps d'écran : membres dépliables, filtres nom / rôle / app, durée des apps masquée par défaut. Test `JtacGeoOverwatchTest`.
- À tester en jeu : onglet CTRL, carte et fiche immobiles après plusieurs actualisations ; 9-LINE transmis visible sur Overwatch Beta ; Temps d'écran dépliable et filtrable.

#### Nouvelle app Garmin : montre connectée (#847)

- Nouvelle app `Garmin` (section Navigation, ordre 20, icône app_garmin) : `fn_pageGarmin` dessine trois onglets (Accueil, Défis, Activités) sur fond sombre, barre d'onglets fixe en bas ; onglet gardé dans `COMSPEC_ATAK_State` (`gmTab`).
- `fn_garminTick` (client, PFH 2 s dans XEH_postInitClient, sauvegarde aussi à `Ended`) : pas et distances avec les règles de `fn_distanceServer` (plus la nage), activités reconnues seules (close après 90 s d'arrêt ou au changement de genre, distance minimale par type, lieu par `nearestLocations`, 40 gardées), stress (suppression, ennemi < 300 m, blessures, COMBAT), fréquence cardiaque (`ace_medical_heartRate` sinon estimée), sommeil (inconscient ou immobile allongé / assis au calme > 2 min), Body Battery (+20/h sommeil, +8/h repos, -6 à -46/h effort et stress, +12/h hors du jeu au retour).
- Données par jour réel du PC dans `profileNamespace COMSPEC_ATAK_Garmin` (paires, 14 jours, courbe à 5 min gardée pour aujourd'hui et hier), écrites toutes les 5 min.
- Envoi au site à chaque écriture (5 min et fin de mission) : nouvelle commande COMSPEC Link 2.0.87 `Garmin.Report` -> POST /api/atak/garmin (`AtakApiController::garmin`, joueur reconnu comme pour ScreenTime.Report). Photo entière : état, 14 jours sans les courbes, 40 activités. `AtakGarminRepository` valide et plafonne ; jours en `GREATEST` (un profil effacé ne fait pas baisser un jour reçu), activités en `INSERT IGNORE`. Tables `atak_garmin_days`, `atak_garmin_activities`, `atak_garmin_status` (bootstrap/atak_garmin_migration.php, étape `atak_garmin` de run-migrations.php). Carte « Montre Garmin en jeu » sur la fiche du membre. L'app affiche « Envoyé au site il y a … min » ou la raison du non-envoi.
- Sur le VPS : lancer run-migrations.php. Dans le jeu : la DLL COMSPEC Link 2.0.87 est nécessaire pour l'envoi.
- 18 icônes (app_garmin, g_*) dans tools/gen_assets.py ; textes anglais ajoutés à en.txt.
- À tester en jeu : ouvrir l'app (Navigation > Garmin), courir 500 m puis s'arrêter 90 s (activité « Course à pied » dans Activités), monter en véhicule et rouler 1 km, s'allonger 2 min sans ennemi (Sommeil augmente, Body Battery remonte), se faire tirer dessus (Stress monte), changer l'objectif de pas dans Défis, quitter et revenir (Body Battery rechargée, message « rechargés pendant … hors du jeu »), passer le téléphone en anglais, vérifier après 5 min la carte « Montre Garmin en jeu » sur sa fiche du site.

#### Sang réaliste et gore, nettoyage libre, constantes médicales réalistes, socle Zeus, ADN sur les taches de sang (#848)

Retours de jeu de TTRD du 2026-10-16 (19:00 à 20:51).

- **Sang à l'écran** (`fn_screenDirt`, `fn_screenSurface`) : les taches sont une liste mémorisée `stains` ([type, variante, x, y, taille, angle, opacité], 40 au plus) au lieu d'un tirage pseudo-aléatoire partagé : ajouter une trace ne déplace plus les autres. Tirages séparés par catégorie (`_from`). Nouveaux modes : `tap` (empreinte de doigt sanglant au point touché, `fn_displayLoad`), `hit` (blessure du porteur : éclaboussures, coulures, flaque si l'écran est ouvert), `nearby` (homme tué à moins de 3,5 m, `EntityKilled`), `zeus`. Dix textures `blood_{splat,hand,drip,spatter,pool}_{0,1}.paa` générées par `tools/gen_dirt.py blood`. Réglage CBA `comspec_atak_native_blood_style` (Discret / Réaliste / Gore).
- **« Depop au clic »** : le contrôle cliqué passait au premier plan ; le retour du focus au relâchement (déjà en place) suffit pour les pages, la carte peut encore masquer les taches pendant un glisser.
- **Nettoyage** (`fn_screenClean`) : plus de condition d'objet ni de quantité ; mode `deep` (10 s, tout à zéro) : action ACE « Nettoyage complet (écran et coque) », boutons app Profil et Réglages. Zeus : « Couvrir l'écran de sang » et « Nettoyer l'écran » (`fn_zeusMenu`, `fn_zeusClient`), nettoyage forcé même si la saleté est coupée chez le joueur.
- **Alerte santé** (`fn_athenaSignal`) : plus de `fullAlert` (bandeau rouge sur l'écran de jeu, visible sans téléphone) ; notification ATAK seulement, et seulement si le joueur a un téléphone.
- **Constantes** : nouvelle `fn_medVitals` (ACE : pouls, tension, volume ; KAT : SpO2, respiration ; sinon classes ATLS I à IV). Arrêt / mort : FC 0, tension et SpO2 `---`. Systolique < 70 : SpO2 non mesurable. Tension et SpO2 n'existent qu'après une mesure (action ACE « Mesurer tension et SpO2 », 8 s, sur un autre ou sur soi ; `COMSPEC_ATAK_Nibp` public sur l'unité) : sinon « non mesurée ». Utilisée par le moniteur (`fn_vizEcg`), le suivi des troupes (`fn_pageMedical`) et le bilan NFC (`fn_linkAllyAction`, lignes Tension et SpO2 ajoutées) ; « Volume sanguin » devient « Perte sanguine estimée ».
- **Socle Zeus** (`fn_dockServer` `zeusKit`) : un `Item_COMSPEC_ATAK_DockKit` créé en cours de partie (time > 0 : Zeus, script) est remplacé côté serveur par `COMSPEC_ATAK_ChargingDock` à la même place, rangeable avec le kit, éditable par les Zeus, non recréé aux parties suivantes. Un kit placé dans l'éditeur reste une valise à ramasser.
- **SSE** (`comspec_sse_fnc_bioBloodStain`, `comspec_sse_biometrics.pbo`) : le serveur relève les gouttes des blessés qui saignent (tous les 1,5 m) et la flaque d'un corps, avec la personne la plus proche (soignant) ; liste publique `COMSPEC_SSE_BloodTraces` (150 au plus). Action ACE personnelle « Tache de sang » à 2,5 m : Examiner, Relever les empreintes sanglantes (latente versée à la base, auteur = blessé ou soignant), Prélever l'ADN (sang du blessé, laboratoire, qualité selon l'âge et la pluie).
- **SALUTE** (`fn_pageSalute`) : champs dans l'ordre doctrinal S-A-L-U-T-E (Localisation avant Unité), intitulés « Volume (Size) »… comme le C.R. SALUTE de l'app Comptes rendus.
- **Kit de nettoyage en complément** : réglage `comspec_atak_native_clean_items` (pansements ACE par défaut) : deux fois plus vite et nettoyage complet ; jamais obligatoire.
- Traductions anglaises ajoutées (`i18n/en.txt`).

#### Retours de jeu : téléphone verrouillé, retour, disposition à la souris, réglages et photo (#849)

- Écran de verrouillage : `fn_dockRender` sort tant que `COMSPEC_ATAK_LockUp` (le rafraîchissement d'une seconde recréait le dock et le rail par-dessus) ; `fn_navigate` refuse l'ouverture d'une app verrouillé ; `fn_lockScreen` redessine le dock au déverrouillage.
- Retour : `fn_back` gère un sous-écran déclaré par la page (`backSub` = [page, code], remis à zéro par `fn_pageRender`) avant l'historique ; déclaré par DataLink, Signalements, Wanted, Dating, Briefing, FRS, Galerie, drone de la carte. Bouton retour actif hors accueil.
- Sous-menus (`fn_formRender`) : textes secondaires plus clairs, titres de section blancs, sections repliables plus contrastées.
- Emplacement : ancres TC et BC (`fn_layoutGet`), mode disposition dans `fn_phoneDrag` (ARRANGE_ON/OFF/DRAW : voile, cadre, glisser partout, au moins un quart visible).
- Boussole : aiguille et cadran mis à jour à chaque image (`fn_mapOnDraw` -> `fn_mapOverlayUpdate` [false, true], getDirVisual).
- Localisation : `relayNet` « locateStart » (6 à 10 s, annulable), action « Effacer les localisations » du menu carte (trilatération, traces, recherche).
- Alertes > Historique > CARTE : suivi coupé et visée périmée ignorée (`fn_mapCenter`), ping local sur le point.
- Options des addons : `comspec_atak_native_phone_3d` (ATAK en 3D, défaut décoché : gilet et JPC), `comspec_atak_native_screen_glow` défaut décoché (aucune lumière vue par les autres), `comspec_atak_native_stowed_damage` (téléphone rangé destructible, défaut coché), catégorie « Objets par interface » (`comspec_atak_native_ui_items_<modèle>`, tablette : tablet,tablette,ctab) ; un modèle sans son objet retombe sur le S7.
- Notifications : profil `COMSPEC_ATAK_Toasts` (fn_toast), types muets `COMSPEC_ATAK_NotifMuted` réglables dans Réglages > Alertes.
- Personnaliser réécrit par sections (Téléphone, Thème et écran, Verrouillage et sécurité, Animations et sons, Accessoires) et volets repliables (Patch, Gilet si 3D, Dock) ; police Barlow (test) retirée ; style JPC bloqué (`fn_jpcScreen` _allowed = false, `fn_vestMount` ignore l'ancien choix).
- Photo : en MINI, l'app Photo n'ouvre plus de viseur (boutons PHOTO / SELFIE vers `fn_photoMode`) ; selfie : objectif sur le téléphone visé sur le visage, position lissée, recadrage borné (`fn_gestureSelfie` « lens »).
- Athena : volet AIDE À LA CONNEXION dans l'app ; bandeau WARNING qui ouvre l'app Athena quand le service est choisi mais la session absente (25 s puis toutes les 10 min, `fn_statusUpdate`).
- Dictionnaire anglais complété (`tools/i18n.py merge`).

#### Relais : clic sur la carte, fréquence, perte et santé, édition Zeus, réseau exclusivement par relais, pylônes 3D refaits (#850)

- `fn_relayNet` : l'entrée « list » passe à 21 champs (fréquence MHz, canal auto, perte %, structure %, santé nominal/dégradé/critique/hors service, sauts, nombre de relais sur le même canal, latence ms). Plan de canaux auto 4940–4975 MHz (pas de 5 MHz, premier canal libre parmi les relais qui se recouvrent), passerelles en 4985. Interférence co-canal : débit / (1 + 0,35 n). Perte = produit des causes (dégâts, brouillage, co-canal, sauts, saturation). « best » rend fréquence, santé, latence et suit le relais épinglé (`uiNamespace COMSPEC_ATAK_RelayPin`). Nouvelles actions `entry`, `only`, `required`, `pin`.
- `fn_linkQuality` : en mode exclusivement par relais et sans relais, multiplicateur 0 et facteur « Aucun relais reçu (réseau exclusivement par relais) », y compris si la simulation réseau est coupée ; latence du relais ajoutée, facteur si le relais est dégradé ou critique.
- Réglage CBA serveur `comspec_atak_native_relay_only` (défaut non), case « Réseau exclusivement par relais » du module Relais (variable `COMSPEC_ATAK_RelayOnly`), variable de mission `COMSPEC_LinkViaRelays` toujours prise en compte. Combo Fréquence du module : canaux C1–C8.
- `fn_moduleRelay` : posé en Zeus sur un relais existant, le formulaire reprend ses valeurs et modifie le relais (uid et batterie conservés).
- Nouveau `fn_relayZeus` : attributs ZEN « Relais · … » (nom, en service, fréquence, portée, débit, hauteur, places, réseau du camp, liaison, alimentation, batterie) appliqués à tous les relais sélectionnés, menu contextuel ZEN « ATAK · Relais » (fiche, allumer, couper, batterie neuve, réparer, réseau exclusivement par relais).
- Carte : `fn_mapCtxMenu` mode `relayAt` et fiche relais (mode carte au clic gauche en mode SÉLECTION, lignes ajoutées au clic droit) ; `fn_meshDraw` étiquette MHz/perte/canal partagé/santé, anneau du relais préféré ; `fn_pageNetwork` lignes de relais sur 3 lignes avec la santé.
- `tools/model_relay_towers.py` : tête circulaire (double anneau, bras, caillebotis annulaire, 3 panneaux par secteur, RRU, AAU, GPS), jarretières avec bagues jaune/rouge, colliers, feeders en chaînette, échelle à câbles, local technique (porte, clim, entrée de câbles), groupe électrogène, clôture barbelée ; 30/40/50 m, m12, m20 et ruines réexportés (LOD1 30 m ≈ 19k, 40 m ≈ 29k, 50 m ≈ 38k triangles). Pour garder towers.pbo sous les 100 Mo de GitHub (93,6 Mo) : ruines sans le niveau complet (jarretières et colliers arrachés), LOD de tir = pièces de la géométrie, géométrie des ruines en tronçons de 10 m.
- main.pbo et towers.pbo reconstruits avec HEMTT 1.17.2 sur main à jour (ATAK natif 1.96.0), empreinte posée puis remise à vide ; la version sera fixée au regroupement.

### À tester en jeu
- Carte du téléphone en mode SÉLECTION : clic gauche sur un relais → fiche ; clic droit → actions relais puis menu habituel ; « Relais préféré » puis « Choix automatique ».
- App Réseau et carte : fréquence, perte, latence et santé affichées ; abîmer un pylône → dégradé puis critique (orange).
- Deux relais proches réglés sur le même canal en Eden : « canal partagé » et débit plus bas.
- Réglage CBA « Réseau exclusivement par relais » puis la case du module : sans relais, plus de réseau et messages en attente ; retour du réseau en s'approchant d'un relais.
- Zeus : double clic sur un pylône (attributs « Relais · … »), menu clic droit « ATAK · Relais », module Relais posé sur un relais existant.
- Nouveaux pylônes 30/40/50 m et mâts 12/20 m : tête, câbles, local, groupe, clôture ; fluidité à distance (LOD) et ruines après destruction.

#### Bluetooth : photos, musiques, données et fiches ; SEEK en équipe ; vibreur BFT avec message (#851)

Version provisoire : le fil de regroupement attribue le numéro du lot (docs/VERSIONING.md). main.pbo reconstruit avec HEMTT 1.17.2, empreinte posée par tools/stamp_build.py puis remise à vide ; comspec_overwatch_connect.pbo repaqueté (tools/pack_pbo.py). DLL modifiée : `build_mod.bat` obligatoire.

##### Bluetooth : transfert de fichiers et de données
- COMSPEC Link 2.0.87 (`BtTransfer.cs`, branché dans `Extension.cs` sous `COMSPEC_ATAK_NATIVE`) : `BtFileOpen` / `BtFileRead` / `BtFileClose` côté émetteur (photo limitée aux dossiers de captures et de screenshots, 8 Mo ; musique limitée au dossier COMSPEC_Music, 30 Mo ; morceaux de 3000 octets en base64), `BtRecvOpen` / `BtRecvWrite` / `BtRecvEnd` / `BtRecvAbort` côté récepteur (fichier temporaire dans `%TEMP%\COMSPEC_BT`, rangé à la fin dans le dossier Captures ou `COMSPEC_Music\Bluetooth`, nom unique).
- `fn_btItems` (nouveau) : listes par onglet (photos, musiques, données, fiches), données envoyées et rangement à la réception (repère local 10 min, `wpAction receive`, bibliothèque Plan, journal SEEK marqué « reçu de », fiches SSE), boîte de réception `COMSPEC_ATAK_BtInbox` (30).
- `fn_btAction` : nouvelles sortes `pho`, `mp3`, `pos`, `wp`, `plan`, `seek`, `sse` ; offre → acceptation → envoi (`data`, ou `fbeg` / `fchk` / `fend` pompé par un PFH à 4 morceaux par 0,25 s, portée vérifiée à chaque envoi), accusé `done`, `fabort` / `cancel`, réceptions bloquées plus de 30 s abandonnées, Bluetooth coupé = transferts arrêtés. DLL ancienne : message « reconstruire avec build_mod.bat ».
- `fn_pageBluetooth` : section « Transferts en cours » (pourcentage, barre, ANNULER), envoi en deux rangées d'onglets (SONS / MUSIQUE / PHOTOS, DONNÉES / FICHES), « Reçus par Bluetooth » avec VOIR.

##### SEEK : actions des autres joueurs sur le même individu
- `fn_sseTeamLog` (connect, nouveau) : journal d'équipe public sur l'unité visée (`COMSPEC_SSE_TeamLog`, 20 lignes, même action du même joueur dans la minute remplacée).
- Alimenté par `fn_sseBiometricSample`, `fn_sseCaptureFacePhoto` (métadonnées de la photo désormais publiques), `fn_seekHistoryPush`, `fn_sseSignAtak`, `fn_ssePersonDialogSubmit` (qui garde aussi la fiche SSE envoyée dans `COMSPEC_SSE_Sheets`, 40, pour le Bluetooth).
- Affiché dans la synthèse du terminal SEEK (`fn_ssePersonRefreshPanels`) et dans le panneau sujet de l'app SSE (`fn_pageSse`).

##### BFT : vibreur avec message et alerte à l'écran
- `fn_pageBft` / `fn_bftAction` : VIBRER ouvre un composeur (6 messages prêts, texte libre de 80 caractères, `<>` retirés, 5 s entre deux envois vers la même personne) ; l'événement `comspec_atak_native_buzz` porte maintenant texte, position et émetteur (anciens appels compatibles).
- `fn_buzzAlert` (nouveau) : alerte orange sur l'écran du téléphone, gardée 2 min (affichée à l'ouverture si le téléphone était rangé, redessinée après chaque page par `fn_fx raise`), boutons VU (`comspec_atak_native_buzzAck` → « X a vu votre message » chez l'émetteur), CARTE, FERMER.

#### Flou des visages, fond noir des profils, capture de la carte, équipement des joueurs et kits par rôle (#854)

- Flou automatique des visages (BLUE FORCE ALLY) : à la prise de vue, `fn_photoFaces` repère les têtes visibles (alliés par défaut, ou tout le monde) et envoie leurs cadres dans `photo_extra` (DLL inchangée) ; le site les floute à la réception (`App\Support\PhotoFaceBlur`, GD). Quatre types (gaussien, mouvement, pixel, carré noir), réglages force, opacité, taille et angle (CBA « ATAK · Photo »), touche B en mode photo pour changer de type.
- Profils Tinder / SSE filmés sur un clone local dans un studio noir (`fn_blackStage`, désactivable).
- Module Zeus « COMSPEC ATAK › Capture de la carte » (`fn_mapCapture`) : satellite, 3D 45° et plan à plusieurs hauteurs ; `tools/map_stitch.py` assemble une mosaïque géoréférencée et des tuiles z/x/y.
- Overwatch connect : la fiche opérateur envoie le catalogue des objets portés et les mods chargés (`fn_collectItemCatalog`, `fn_collectLoadedMods`) ; ingestion par `GameItemUsageRecorder` (try/catch). Site : `/back-office/equipement-joueurs`, `/back-office/kits-roles` (kits publiés comme tenues d'arsenal « Kits par rôle »), bloc « Kits proposés pour mon rôle » sur `/equipment`. Migration `migrations/20261018000001_game_item_usage.sql` (`php run-migrations.php`).
- À tester en jeu : photo avec des alliés dans le champ (visages floutés sur le site, pour les 4 types) ; profil Tinder / SSE sur fond noir ; module Zeus « Capture de la carte » sur une petite zone puis `map_stitch.py` ; après connexion, `/back-office/equipement-joueurs` se remplit, créer un kit puis l'appliquer depuis « Tenues Athena ».

---

## Regroupement : app Plan refaite, partage des caméras, nouvelle app Distances (ATAK natif 1.96.0, COMSPEC Link 2.0.86)

### Résumé joueur
Type : nouveauté
FR : App Plan refaite, caméras partagées par NFC, Bluetooth ou relais, nouvelle app Distances
- App Plan : outils rangés dans une palette, menu au clic droit, ANNULER / RÉTABLIR (Ctrl+Z / Ctrl+Y), une description par élément, un menu EXPORTER, des salles où tous ceux qui ont le code dessinent sur le même plan, un tutoriel et l'aide sur la touche H.
- App Vidéo live : bouton PARTAGER vers un ATAK ou vers TOUS, par NFC (téléphones dos à dos, même sans réseau), par Bluetooth (courte portée, sans réseau) ou par les relais (sans limite de distance). Une caméra n'est plus visible par tout le camp sans partage.
- Nouvelle app Distances : tes pas, tes kilomètres à pied, en véhicule, en bateau, en vol et en saut HALO, et tes heures de pilote par appareil, comptés par le serveur même si ton téléphone est vide ou cassé.
- Ces distances arrivent sur ta fiche du site toutes les 10 minutes, avec le cumul de toutes tes missions.
EN : Plan app redone, cameras shared by NFC, Bluetooth or relay, new Distances app
- Plan app: tools in a palette, right-click menu, UNDO / REDO (Ctrl+Z / Ctrl+Y), a description per item, an EXPORT menu, rooms where everyone with the code draws on the same plan, a tutorial and help on the H key.
- Live video app: SHARE button to one ATAK or to ALL, by NFC (phones back to back, even with no network), by Bluetooth (short range, no network) or through relays (no distance limit). A camera is no longer visible to the whole side without sharing.
- New Distances app: your steps, kilometres on foot, by vehicle, by boat, in flight and in HALO jumps, and your pilot hours per aircraft, counted by the server even if your phone is flat or broken.
- These distances reach your member file on the site every 10 minutes, with your all-missions totals.

### Détail technique
Regroupe #833, #834 et #836 (une seule version : ATAK natif 1.96.0, COMSPEC Link 2.0.86 pour la nouvelle commande `Distance.Batch` de #836 ; main.pbo reconstruit depuis les sources avec HEMTT 1.17.2, empreinte « 2026.10.14 fc17ea7 » posée par tools/stamp_build.py puis remise à vide ; sans texHeaders.bin).

#### App Plan refaite : palette d'outils, menus, annuler / rétablir, salles collaboratives, aide et tutoriel (#833)

- `fn_pagePlan` ne crée plus que la carte ; barres, palette, fiche de sélection et menus sont dans `fn_planUi` (« build »), refait seul à chaque clic : la carte n'est plus recréée par SUPPR. / CONFIRMER / choix d'un outil. Une carte neuve n'enregistre sa vue (`COMSPEC_ATAK_PlanView`) qu'une fois calée sur le but (`fn_planMap` « draw », 3 recalages au plus) : avant, son premier dessin (coin haut gauche du monde) écrasait la vue gardée.
- Clic droit sans outil : menu (`ctxOpen` / `ctxAct`) sur l'élément pointé (modifier, dupliquer, copier vers brouillon / camp / salle, nettoyé ⇄, grille, centrer, supprimer) ou sur la carte vide (note, ennemi, CP, RP, grille, centrer, aide).
- Historique : le serveur renvoie à l'auteur ce que chaque opération a changé (`comspec_atak_native_planDiff` [repère, salle, avant, après]) ; ANNULER / RÉTABLIR envoient l'opération `patch` (ids gardés). Brouillon : même diff calculé localement. 40 entrées.
- Salles : `roomNew` (code de 5 caractères, `COMSPEC_ATAK_PlanRoom_<code>` publique), rejoindre par code, quitter ; droit d'édition = avoir le code. La salle est dessinée sur la carte principale et publiable sur le plan du camp.
- Élément : 11e champ description (300 caractères), envoyée au site (`desc`) et affichée dans l'infobulle de la carte du briefing web.
- EXPORTER : code COMSPECPLAN1 (inchangé), texte de briefing avec grilles, enregistrer, publier, importer ; source au choix (camp, brouillon, salle).
- Aide (H, `fn_planHelp`) selon l'outil en cours et raccourcis (Entrée, Échap, Ctrl+Z/Y/D, Suppr) ; tutoriel en 10 étapes au premier lancement (profil `COMSPEC_ATAK_PlanTuto`), rejouable. Textes FR traduits en anglais (en.txt).
- À tester en jeu : dessin des 3 géométries, clic droit (menu / fin de tracé), SUPPR. puis CONFIRMER sans saut de carte, Ctrl+Z / Ctrl+Y sur brouillon et camp, salle à 2 joueurs (création, code, dessin croisé, quitter), export texte collé dans Discord, tutoriel au premier lancement, touche H, langue anglaise.

#### Partage des caméras par NFC, Bluetooth ou relais, à un ATAK ou à TOUS (#834)

- Nouveau `fn_camShare` : liste d'accès en variable publique `COMSPEC_ATAK_ShareAcc` sur la source (objet caméra, ou l'unité pour la caméra de son téléphone). NFC à 2 m sans réseau ; Bluetooth à portée `comspec_atak_native_bt_range` (nouveau curseur CBA, 5 à 100 m, 15 par défaut), Bluetooth allumé des deux côtés ; relais par le réseau (`linkQuality`), TOUS = tout le camp, arrivants compris. Événement CBA `comspec_atak_native_camShare` pour prévenir les destinataires ; RETIRER LES ACCÈS coupe le flux chez l'autre.
- `fn_camAction` : `list` filtrée par accès (`listAll` garde tout le camp pour les noms), statut `NOACCESS`, UID du poseur enregistré à la pose. `fn_feedLink` : liaison Wi-Fi directe à une caméra fixe à portée (`comspec_atak_native_camera_range`, 600 m), même sans réseau.
- `fn_pageLivecam` : bouton PARTAGER et panneau (méthode avec ses conditions, puis destinataire) ; `fn_livecamStart` et la mini-caméra de la carte s'arrêtent sans accès. Réglage CBA `comspec_atak_native_camera_share` : « Partage obligatoire » (défaut) ou « Tout le camp voit tout » (comportement d'avant, PARTAGER masqué). Page « Partage des caméras » du Manuel de terrain (FR/EN). Test `AtakCameraShareTest`.
- À tester en jeu : caméra posée visible du seul poseur ; PARTAGER par NFC à 1 m en mode avion, l'équipier la regarde à moins de 600 m ; Bluetooth refusé hors portée ou éteint (raison affichée), TOUS à portée ; relais TOUS, un arrivant la voit ; RETIRER LES ACCÈS ; Ma caméra partagée visible dans VOIR CAMÉRA (carte / BFT) ; réglage « Tout le camp voit tout ».

#### Nouvelle app Distances : pas, kilomètres, heures de pilote par appareil, sur le téléphone et la fiche du site (#836)

- Serveur (`fn_distanceServer`, PFH 3 s dans `XEH_postInitServer`) : écart de position de chaque joueur rangé à pied / terrestre / bateau / vol / saut (ParachuteBase, ou chute libre : pas au sol, > 15 m ATL, vz < -8 m/s ou anim halofreefall ; distance 3D en saut, 2D sinon). Téléport ignoré au-delà d'une vitesse plausible par mode. Pas = distance / longueur de pas (0,7 à 1,6 m selon l'allure), rien à la nage ni à plat ventre. Temps à pied = temps en mouvement. Saut compté au départ d'au moins 150 m ou sous voile. Pilote = `currentPilot` d'un `Air` moteur allumé, par `typeOf`.
- Envoi `Distance.Batch` (nouvelle commande COMSPEC Link 2.0.86, file d'attente comme `ScreenTime.Batch`) toutes les 10 min, au départ d'un joueur et à `MPEnded`, par lots de 25 ; ce qui échoue reste en mémoire serveur. Compteurs de la mission poussés au joueur toutes les 15 s (`COMSPEC_ATAK_DistMe`, ciblé sur son client) pour l'app `fn_pageDistance` (lanceur Navigation, icône `app_distance`).
- Réglage CBA serveur « Distances parcourues » (`comspec_atak_native_distance`, actif par défaut).
- Site : `POST /api/atak/distance/batch` → `atak_distance_stats` (une ligne par membre et session `mission_key`) et `atak_pilot_time` (par classe d'appareil), migration `bootstrap/atak_distance_migration.php`. Fiche du membre : carte « Distances parcourues en jeu » (cumuls, sessions, temps à pied total et moyen par session) et « Heures de pilote par appareil », classes regroupées par type (`AtakDistanceRepository::aircraftFamily` : Chinook avec Huron, Black Hawk avec Ghost Hawk, Little Bird avec Hummingbird…).
- DLL à reconstruire (build_mod.bat) : sans COMSPEC Link 2.0.86 le serveur compte quand même mais n'envoie rien (journal WARN DISTANCE).
- Non testé en jeu. À vérifier : marche, course, sprint, rampé, nage (pas et km) ; véhicule terrestre, bateau ; hélicoptère et avion en pilote et en passager ; saut HALO depuis un avion et ouverture de voile ; respawn et téléport Zeus non comptés ; app Distances mise à jour toutes les 15 s ; fiche du site après 10 min ou à la fin de mission.

---

## Regroupement : drone comme Vidéo Live, téléphone en main pour les photos, carte plus lisible, antenne, numéro de build (ATAK natif 1.95.0)

### Résumé joueur
Type : nouveauté
FR : App Drone refaite, téléphone en main pour les photos, carte plus lisible
- L'image du drone s'affiche comme celle des caméras fixes de Vidéo Live ; l'app Drone montre la liste des drones à gauche (choisir pour piloter, ou appairer un drone tout proche), l'image et les commandes à droite, avec la rangée JOUR / VISION NOCTURNE / THERMIQUE.
- En mode photo, ton personnage tient son téléphone devant lui et la photo part de l'objectif au dos ; en selfie, l'objectif se place face à ton visage même tête baissée. Si la formule de ta communauté n'inclut pas la carte Athena, une fenêtre explique une fois pourquoi ta photo n'est pas partie.
- Sur la petite carte du téléphone, les noms des repères sont plus grands et les symboles de la mission sont à l'échelle de l'écran ; le calque « Cartouches » s'appelle maintenant « Coordonnées repères ».
- L'antenne d'extension part du port de la coque au lieu de flotter au-dessus, le texte des boutons verts n'a plus de contour noir, et Réglages, Crédits et Statut affichent la version avec la date du build.
EN : Drone app redone, phone in hand for photos, clearer map
- The drone picture is shown like the fixed cameras in Live Video; the Drone app lists drones on the left (pick one to fly it, or pair a drone close by), with the picture and controls on the right and the DAY / NIGHT VISION / THERMAL row.
- In photo mode, your character holds the phone in front of them and the photo is taken from the rear lens; in selfie mode, the lens moves in front of your face even with your head down. If your community's plan does not include the Athena map, a window explains once why your photo was not sent.
- On the phone's small map, marker names are larger and mission symbols scale with the screen; the "Labels" layer is now called "Marker coords".
- The extension antenna starts from the case port instead of floating above it, green button labels no longer have a black outline, and Settings, Credits and Status show the version with the build date.

### Détail technique
Regroupe #820, #822, #825, #826, #827, #829, #830 et #831 (une seule version : ATAK natif 1.95.0, COMSPEC Link inchangé 2.0.85 ; main.pbo reconstruit depuis les sources avec HEMTT 1.17.2, empreinte « 2026.10.14 fc17ea7 » posée par tools/stamp_build.py puis remise à vide ; sans texHeaders.bin, que HEMTT ne produit pas et dont l’ancienne copie ne décrit plus les textures d’antenne régénérées).

#### Numéro de build complet dans le téléphone (#822)

- Règle de numérotation commune : `docs/VERSIONING.md` (MAJEUR.MINEUR.CORRECTIF, MINEUR +1 par sortie ou regroupement, CORRECTIF +1 pour un correctif seul d'une version déjà sortie, empreinte de build jamais comparée).
- `build_info.hpp` (`BUILD_STAMP`, vide dans le dépôt) inclus par `script_component.hpp` ; posé par `build_mod.bat` (git + PowerShell) ou `tools/stamp_build.py`, puis remis à vide.
- `COMSPEC_ATAK_NativeBuild` = « VERSION_STR - empreinte » (« - dev » sans empreinte) : Réglages, Crédits, Statut, app Debug (Vue d'ensemble, DLL), page texte STATUT, dump debug, check de démarrage Discord, RPT (BOOT) et journal de session COMSPEC (module VERSION, au postInit client).
- `COMSPEC_ATAK_NativeVersion` et `mod_version` envoyés au site restent `MAJEUR.MINEUR.CORRECTIF` ; `fn_integrityCheck` ignore tout ce qui suit un espace dans les versions comparées.
- Test : `AtakReleaseInfoTest::testBuildStampIsNeverCommitted`.

#### Photo refusée faute de carte Athena : une fenêtre claire au joueur (#820)

- XEH_postInitClient (retour des photos) : quand le dernier refus porte `feature_unavailable` (connect, 1.93.0), `BIS_fnc_guiMessage` sur l'écran de jeu (attend la fermeture du menu Échap), une fois par session (`COMSPEC_ATAK_PlanModalShown`), à la place de la notification « Photo refusée par Athena ». Textes FR, traduits dans i18n/en.txt.
- À tester en jeu : communauté sans la carte, prendre deux photos. La fenêtre s'ouvre une seule fois, lisible en FR et en EN (Réglages > Langue), OK la ferme. Communauté avec la carte : aucune fenêtre, envoi normal.

#### Texte des boutons verts moins gras (#825)

- `COMSPEC_RscButton` (ui/controls.hpp) : `shadow=0`. La classe héritait du `shadow=2` du RscButton du jeu (contour noir du texte), qui doublait l'épaisseur du texte noir sur fond vert. Touche tous les boutons du téléphone (classes dérivées comprises) ; police inchangée (Roboto Condensed Bold, ou la police choisie dans Réglages > Police).

#### Antenne d'extension fixée sur la coque (#826)

- Cause : l'antenne était dessinée avec « garder les proportions » (style 2096) dans un cadre 384 x 1536 qui dépasse
  de la coque. Au moindre écart entre le rapport de pixels calculé et l'écran, Arma recentrait l'image dans ce cadre
  très haut et elle remontait d'environ 50 px de texture, alors que la coque (style 48) est étirée sur le sien.
  L'antenne utilise maintenant COMSPEC_RscPhone (style 48), comme la coque et le câble DL-7 ; idem pour le dos,
  la batterie externe et l'antenne de « Voir l'arrière ».
- Le port SMA était placé pour le S7 seul : décalage par modèle (rugged, iphone, sonim, tablet, keys, plate, flip,
  ribbed) vers le bord haut de chaque coque, mesuré sur les rendus.
- gen_assets.py : bossage moulé et écrou six pans sous l'embase (`_boss_svg`), absent de l'icône d'inventaire.
  antenna_*_land/port/back.paa régénérés.

#### Carte du téléphone : noms des repères plus lisibles, symboles de la mission à l'échelle de l'écran (#827)

- `COMSPEC_RscMapAtak` : `showMarkers=0` (la carte Arma dessinait les marqueurs à taille fixe en pixels, 32 px pour l'OTAN) ; `COMSPEC_RscMapJtac` garde `showMarkers=1`.
- Nouveau `fn_mapGameMarkers` : redessine allMapMarkers (ICON à `taille CfgMarkers x markerSize x 0,62 x écran (0,45..1,2) x réglage symboles`, RECTANGLE / ELLIPSE à la brosse CfgMarkerBrushes avec bordure, POLYLINE en trait fin). Propriétés relues toutes les secondes ou dès que le nombre de marqueurs change, position lue à chaque image.
- `fn_mapMarkerText` : texte 0,021 -> 0,024, ligne 2 0,86 -> 0,9, plaque 0,84 -> 0,93 d'opacité, texte blanc pur ; décalage du nom calculé sur la taille réellement dessinée (`COMSPEC_ATAK_MkScale`).
- `fn_mapOnDraw` : plancher du facteur texte écran 0,8 -> 0,95 (noms de lieux, indicatifs, repères).
- Non testé en jeu. À vérifier : téléphone porté et plein écran (S7 et un autre terminal), zoom loin / moyen / près ; symboles OTAN plus petits et une seule fois (si un grand symbole Arma reste dessiné sous le petit, `showMarkers` n'est pas pris en compte par le jeu) ; zones pleines, bordées et hachurées ; traits tracés à la main ; carte JTAC inchangée.

#### Drone : image rendue comme les caméras fixes de Vidéo Live, liste des drones à gauche (#830)

- Caméra du flux créée comme celle de `fn_camView` (cible au nom fixe, texture carrée rapport 1, contrôle COMSPEC_RscPhone, pas de camCommit à la création) et raccrochée au drone à chaque image ; zoom par camSetPos + camCommit puis raccrochage.
- App Drone en grand format couché : liste à gauche (flotte : choisir = piloter ; drones à moins de 15 m : choisir = appairer), image et commandes à droite, rangée JOUR / VISION NOCTURNE / THERMIQUE comme Vidéo Live.
- À tester en jeu : image du drone (plus d'écran noir), zoom, JOUR / NV / THERMIQUE, piloter un drone de la flotte, appairer un drone posé à moins de 15 m.

#### Appareil photo : le téléphone dans la main, selfie face au visage (#831)

- `fn_gestureSelfie` : mode `photo` = même pose que le selfie (bras tendu, téléphone du profil accroché à la main droite) ; mode `rear` = position de l'objectif arrière (dos du téléphone).
- `fn_photoMode` : objectif arrière placé sur le téléphone tenu en main (caméra scriptée même sans zoom, visée à la souris comme au zoom). Réglage CBA « Animation de l'appareil photo » : défaut `comspec_atak_native_fnc_gestureSelfie` ; l'ancienne valeur `gesturePoint` est reprise comme le nouveau défaut.
- Selfie (`lens`) : on ne peut pas tourner la tête d'un joueur par script, donc l'objectif suit la tête. Orientation lue sur l'os `head` (`selectionVectorDirAndUp`, repli `eyeDirection`), site borné à -35°/+50°. Tête à moins de 12° du téléphone : objectif sur le téléphone ; au-delà, glisse vers l'axe du visage (complet à 30°). Journal `[PHOTO] Selfie : visage à X° du téléphone…` toutes les 3 s.

À vérifier en jeu :
- Mode photo (objectif arrière) : le S7 est dans la main droite, bien orienté (écran vers soi), et l'image n'est pas masquée par la main ou le téléphone ; visée et zoom à la souris.
- Autre joueur : voit le téléphone dans la main pendant la photo, puis le téléphone disparaît à la sortie.
- Selfie en entrant tête baissée ou tournée : le visage est cadré de face. Relever la ligne `Selfie : visage à …` du journal si ce n'est pas le cas (elle dit si l'orientation vient de l'os de la tête ou du regard).
- Passage photo ↔ selfie (R) sans que le bras retombe ; sortie (Espace) : bras baissé, arme reprise.

#### Calque « Cartouches » renommé « Coordonnées repères » (#829)

- Le libellé faisait penser à des munitions. Même réglage (`COMSPEC_ATAK_MarkerTags` / `native_marker_tags`) : nom et coordonnées E / N sous les repères, en zoom rapproché. CALQUES de la carte : « Coordonnées repères » ; Réglages > Carte et page Expérience ATAK du site : « Coordonnées des repères ». en.txt et modèle communautaire mis à jour.

---

## Objets d'inventaire en vraie 3D, pylônes relais retravaillés jour et nuit (ATAK natif 1.94.0)

### Résumé joueur
Type : nouveauté
FR : Les objets ATAK ont enfin leur vrai modèle 3D, et les pylônes relais sont bien plus réalistes
- Batterie, batterie externe, caméra IP, filtre JVN, kit de réparation, kit véhicule, les trois antennes, câble DL-7 et valise du socle : posés au sol ou dans l'éditeur, ce sont maintenant de vrais objets détaillés, plus la sacoche générique. Leurs icônes d'inventaire sont refaites d'après ces modèles.
- La caméra IP posée est une caméra durcie sur rotule et base magnétique, avec projecteurs infrarouges visibles la nuit.
- Les pylônes relais gagnent des haubans, plates-formes, armoires vert olive avec climatisation, enclos grillagés, filet de camouflage, panneaux de danger et une dalle béton. Les ombres sont plus fidèles.
- La nuit, les feux rouges d'obstacle clignotent au sommet (et à mi-hauteur sur les pylônes de 40 et 50 m).
EN : ATAK items finally have real 3D models, and the relay towers look far more realistic
- Battery, power bank, IP camera, NVG filter, repair kit, vehicle kit, the three antennas, DL-7 cable and dock case: on the ground or in the editor, they are now real detailed objects instead of the generic pouch. Their inventory icons are redone from these models.
- The placed IP camera is a rugged camera on a ball mount and magnetic base, with infrared lamps visible at night.
- Relay towers gain guy wires, platforms, olive cabinets with air conditioning, fenced compounds, camouflage netting, danger signs and a concrete pad. Shadows are more accurate.
- At night, red obstruction lights blink at the top (and halfway up on the 40 and 50 m towers).

### Détail technique

#### Objets d'inventaire 3D (tools/model_items.py, tools/gen_item_textures.py)
- 11 modèles MLOD `item3d_*.p3d` (battery, powerbank, camera, nvgfilter, repairkit, vehiclekit, stub, whip, patch, datacable, dock) + `camera_ip.p3d` : LOD 1, ombre 0 (enveloppe convexe par pièce, rentrée 0,4 mm), géométrie (masse, autocenter=0) et géométrie de tir. Dos au sol, avant vers +Y.
- Une seule texture : atlas 1024 `items_co/_nohq/_smdi` (32 pastilles de matière + étiquettes et sérigraphies), `items.rvmat` (Super) et `items_glow.rvmat` (voyants et IR émissifs).
- Références visuelles fournies par TTRD : caméra durcie khaki / noir sur rotule magnétique, pochette ranger green à écusson COMSPEC, berceau véhicule khaki avec boîtier d'antenne et fouet, patch khaki.
- config.cpp : `model=` des 11 CfgWeapons et des `Item_COMSPEC_ATAK_*` (plus `editorPreview` rendus), `COMSPEC_ATAK_CameraObj` sur `camera_ip.p3d` (au lieu de HandyCam_F). fn_camAction : écart à la surface 9,2 cm au sol (base magnétique), 4,5 cm au mur et au plafond.
- Icônes d'inventaire `item_*.paa` (sauf le téléphone) : rendus Cycles des modèles (fond transparent, ombre portée). gen_assets.py ne les écrase plus quand `render/icon_*.png` existe.

#### Pylônes relais (tools/model_relay_towers.py, tools/gen_tower_textures.py)
- Détail : dalle béton du site, goussets aux nœuds du treillis, échelons des monopoles, pare-glace des passerelles de câbles, armoires vert olive (`tower_olive`) avec panneau danger RF, voyant, plinthe et ouïes, groupes de climatisation, plaque d'identification du site (`tower_sign`), enclos en grillage transparent (`tower_fence_ca`, collision gardée, balles traversantes), filet de camouflage (`tower_net_ca`) sur le monopole 20 m, haubans + ancrages (30 m peint olive, 12 m), plate-forme de tête du 40 m, couronne et parabole supplémentaires (30 m, 50 m).
- Nuit : feux d'obstacle (globe émissif) sur les cinq pylônes, mi-hauteur sur 40 / 50 m ; LOD mémoire `light_top`, `light_mid1`, `light_mid2` et `class MarkerLights` (rouge, clignotant, éteint le jour) dans towers/config.cpp.
- Ombres : ombre proche tirée de la LOD 4 au lieu de la LOD 12 (échelle, chemins de câbles, garde-corps, haubans) ; panneaux, filet et grillage exclus des ombres et géométries.
- Ruines : haubans rompus (ancrages restants), feux éteints.
- Aperçus : `tools/render/tower_family_night.png` (vue de nuit), aperçus Eden refaits.
- Nouveau PBO `items.pbo` (addons/items : modèles, atlas, rvmat ; 8 Mo) pour garder main.pbo sous la limite de 100 Mo de GitHub ; build_mod.bat, README et project.toml à jour. PBO main, towers et items reconstruits avec HEMTT. Non testé en jeu : vérifier les feux MarkerLights sur un bâtiment, l'orientation des objets dans les porte-armes au sol, et la pose de la caméra au mur.

---

## Regroupement : Zeus sur les ATAK, Comptes rendus, nouveaux terminaux, carte, drone, mise à jour (ATAK natif 1.93.0, COMSPEC Link 2.0.85)

### Résumé joueur
Type : nouveauté
FR : Le Zeus prend la main sur les ATAK, Comptes rendus refaits, nouveaux terminaux
- Le Zeus peut désactiver, verrouiller, rendre muet, brouiller ou abîmer ton téléphone, et les brouilleurs du mod Crows Electronic Warfare brouillent aussi l'ATAK.
- L'app Comptes rendus est refaite : une fiche par type (SALUTE, UXO, blessé, LOGREP…), des valeurs proposées par le jeu, des brouillons et des accusés de réception.
- Nouveaux terminaux V2 ENHANCED, XIAOMI X3D et SONIM XP8, nouvelle tablette ; la carte a des noms de repères et de lieux plus lisibles, masquables dans CALQUES.
- Drone : bouton PLEIN ÉCRAN, image plus légère, ordres de la carte qui disent s'ils sont reçus. Le téléphone te prévient enfin des mises à jour, l'app Athena remplace l'ancienne fenêtre « Lier le jeu à Athena », et une photo refusée par la formule de ta communauté est expliquée.
EN : Zeus takes control of the ATAKs, Reports redone, new terminals
- Zeus can disable, lock, mute, jam or damage your phone, and Crows Electronic Warfare jammers now jam the ATAK too.
- The Reports app is redone: one sheet per report type (SALUTE, UXO, casualty, LOGREP…), values suggested by the game, drafts and read receipts.
- New terminals V2 ENHANCED, XIAOMI X3D and SONIM XP8, new tablet; the map has clearer marker and place names, which can be hidden in LAYERS.
- Drone: FULL SCREEN button, lighter picture, map orders that tell you whether they were received. The phone now tells you about updates, the Athena app replaces the old "Link the game to Athena" window, and a photo refused by your community's plan is explained.

### Détail technique
Regroupe #805, #806, #807, #808, #810, #811, #812, #813, #814 et #818 (une seule version : ATAK natif 1.93.0, COMSPEC Link 2.0.85 ; main.pbo et comspec_overwatch_connect.pbo reconstruits depuis les sources).

#### Nouveaux terminaux : V2 Enhanced, XIAOMI X3D, SONIM XP8 et nouvelle tablette (#805)

- Coques photo fournies par TTRD (tools/src/frame_*.png) posées par `tools/frame_photos.py` dans la toile 2048 px : proportions gardées, ouverture de l'écran mise à 1092 px de large et centrée comme celle du S7. Franges presque transparentes du détourage (alpha < 12) supprimées. Dos : miroir de la face, intérieur remplacé par un panneau polymère avec module photo, marquages remis à l'endroit.
- Identifiants inchangés (`iphone` = V2 ENHANCED, `rugged` = XIAOMI X3D, `keys` et `tablet` = tablette) : aucun profil à migrer. Nouveau modèle `sonim` (SONIM XP8) : photo assemblée par frame_photos.py (téléphone, goTenna Pro posé derrière et dépassant en haut ; au dos, son côté clip).
- `fn_layoutGet` : écran propre à chaque modèle (`_scrLand`, paysage ; portrait = quart de tour), boîtes visibles remesurées. Le S7 et les coques plate / flip / ribbed gardent l'ouverture commune.
- PAA régénérés : `phone_{landscape,portrait}_{iphone,rugged,keys,tablet}[_night]`, `phone_back_{iphone,rugged,keys}`, `dirt_case_*` de ces modèles, `item_vehiclekit` (`gen_assets.py <hemtt> frames`, `gen_dirt.py <hemtt> case …`).
- JPC : `jpc_iphone` / `jpc_rugged` (p3d et atlas) reconstruits, `jpc_sonim` ajouté (classe COMSPEC_ATAK_JpcPhone_sonim, selfie compris) avec l'ouverture de chaque photo ; texture de l'écran 3D aux proportions du modèle (1024 x 496 V2 Enhanced, 1024 x 384 X3D, 1024 x 584 Sonim, `COMSPEC_ATAK_JpcTexH`). La tablette sur le JPC reste le modèle 3D à touches (rendu gardé sous `render/phone_keys_jpc_*`).
- Noms : Réglages > Modèle du téléphone, vue de dos et Accessoires, carte Discord des photos ; anglais dans i18n/en.txt.
- À tester en jeu : Réglages > Modèle du téléphone, choisir V2 ENHANCED, XIAOMI X3D, SONIM XP8 puis TABLETTE ; pour chacun, mini et plein écran, portrait et paysage, jour et nuit : l'interface remplit exactement l'ouverture (pas de bande noire, rien de coupé), la poussière de coque suit le cadre ; Voir l'arrière ; tablette de véhicule ; porté sur le JPC avec V2 ENHANCED, X3D et SONIM (écran 3D cadré, pas déformé). Un profil qui avait Premium ou Tactique doit ouvrir directement le nouveau modèle.
#### Ordres au drone depuis la carte : chaque ordre dit s'il part ou pourquoi il est refusé (#806)

- Retour de TTRD (14/10, capture : sous-menu affiché sur un DJI Mavic 3E, « les ordres fonctionnent pas ? »). Chemin relu de bout en bout : clés DGOTO/DHUNT/DHOVER/DLAND bien traitées après le regroupement 1.91.0, `mapOrder` → `_send` → événement visé `comspec_atak_native_droneCmd` → `goto`/`hunt` côté machine du drone. Pas de clé perdue. Le refus le plus probable est la liaison (`_needLink`), qui ne disait que « hors liaison » sans raison ni journal ; « perdu 12:58 » sur la capture est un marqueur LOCK (cible marquée perdue de vue), pas l'état de la liaison.
- `fn_mapCtxMenu` : les quatre ordres partent à l'image suivante (`CBA_fnc_execNextFrame`), hors du clic d'un bouton que `close` vient d'effacer.
- `fn_droneAction "mapOrder"` : journal à chaque étape (demande, liaison bonne et barres, veille, drone posé, terminal UAV repris, envoi, attente d'accusé) ; refus expliqués par bandeau (app DRONE au clic) + ligne rouge : point illisible, pas de drone, détruit, batterie (`fuel`) vide, frappe en cours, liaison perdue (pas de téléphone, batterie du téléphone, signal Mavic, relief avec portée ×0,3, distance/portée), zone interdite.
- `_watchMap` (chaque seconde, liaison ou non) : `COMSPEC_ATAK_DroneMapOrder` [ordre, grille, time, étape, COMSPEC_DroneAckN à l'envoi] → « ordre reçu » (bandeau SUCCESS) dès que le compteur bouge, ligne à 5 s sans accusé, bandeau ERROR à 30 s (FORCER L'EXÉCUTION ou réappairer).
- en.txt : 31 traductions.
#### Caméra noire : bouton PLEIN ÉCRAN du drone, TEST VIDÉO détaillé, drone plus léger (#807)

- Retour de TTRD (14/10, après 1.91.0) : viseur Photo et image du drone toujours noirs dans le téléphone ; bris d'écran validé. Le premier TEST VIDÉO (13/10 21:20) s'affichait aux 4 endroits, mais montrait le joueur de dos, c'est-à-dire pas depuis la position posée par le script : la caméra n'est pas là où le code la place. Cause des vues noires toujours non confirmée.
- `fn_pipCheck` "test" : grille de 9 images dans une fenêtre par-dessus le téléphone, 30 s : A référence (premier test), B texture 1024, C rapport 16/9, D caméra déplacée à chaque image comme l'app Photo (camSetPos + camCommit + setPosASL), E déplacée par setPosASL seul, F accrochée au joueur et orientée chaque image comme le drone, G posée 30 m au-dessus et tournée vers le sol, H thermique, I flux réel du drone (camBind). Relevé à 2 s : position réelle de chaque caméra (distance au joueur, hauteur, écart à la position posée). Bouton aussi dans l'app Photo.
- App Drone : bouton PLEIN ÉCRAN sur l'image (bord droit, mi-hauteur) → `fn_droneAction` "direct". Pendant la vue directe, le rendu dans la texture est coupé (la scène n'est plus dessinée deux fois).
- Performance (retour de TTRD : « le drone fait très vite une surcharge ») : netteté par défaut BASSE (texture 512 au lieu de 1024), caméra et boucle de la nacelle libérées 5 s après avoir quitté l'app Drone (20 s avant) ; viseur Photo en texture 512.
- Journal : lignes « Flux caméra ouvert / suspendu / arrêté » du drone ; les lignes CAMERA, PHOTO, DRONE et PERF du journal natif sont recopiées dans le journal de session COMSPEC (.log d'Overwatch), qui ne les contenait pas.
- Non testé en jeu.
#### Contrôle Zeus des ATAK : désactiver, couper le son, punir, brouiller, dégâts (#808)


- Nouveau dossier `functions/zeus`. Le serveur seul écrit l'état (`fn_zeusServer`, événement CBA `comspec_atak_native_zeus`, accepté d'un curateur assigné, de l'hôte ou d'un admin connecté) : variable d'unité publique `COMSPEC_ATAK_ZeusFx` = `[[clé, valeur, fin]...]` (clés `off`, `lock`, `mute`, `jam`, `heat`, `drain`), lue par `fn_zeusFx`. Effets durables jusqu'à l'action inverse ou « Tout rétablir » (ou une durée), reportés à la réapparition (`EntityRespawned`) et vus par les arrivants (variable publique). Effets ponctuels relayés au joueur (`comspec_atak_native_zeusFx` → `fn_zeusClient`).
- Côté joueur : `fn_canUse` renvoie `zeus` (désactivé) ou `heat` (surchauffe 3) et le téléphone se range ; `fn_deviceOverlay` masque l'app et appelle `fn_zeusOverlay` (écran rouge verrouillé, bandes de parasites, voile de surchauffe) ; muet : `fn_vibrate`, `fn_fx`, `fn_musicTick`/`fn_musicAction` (musique et haut-parleur coupés), `fn_btInit` (sons Bluetooth) ; `fn_battery` compte surchauffe et décharge ; dégâts par `fn_deviceDamage` / `fn_deviceRepair` (réglage serveur « Dégâts du téléphone » requis).
- Brouillage : 7e élément « force » dans `COMSPEC_ATAK_Jammers` (`fn_linkQuality`, `fn_ewEffects`), brouilleurs de zone Zeus remis par le serveur toutes les 5 s si une écriture d'un client les efface. `fn_zeusJam` : brouillage ciblé + brouilleurs Crows EW lus dans `crowsEW_jamming_jamMap` (format déduit du mod, non vérifié en jeu), réglage serveur « Brouilleurs Crows Electronic Warfare ».
- Zeus : menu clic droit ZEN « ATAK · Contrôle Zeus » (Accès, Son, Punir, Brouiller, Dégâts et effets, Tout rétablir) sur la sélection ou l'unité survolée (`fn_zeusMenu`) ; il remplace le menu ZEN / ACE Zeus d'Overwatch « ATAK — Infos / dégâts / brouillage », qui agissait sur l'état du téléphone d'Overwatch. Modules Zeus sans ZEN : « ATAK · Contrôle Zeus » (joueur sous le module, rayon, tous, camp ; intensité, durée, message) et « ATAK · Brouilleur de zone ». Aide : Manuel de terrain > COMSPEC — ATAK > Contrôle Zeus des ATAK (FR/EN).
- À tester en jeu (Zeus + un joueur) : chaque entrée du menu clic droit et les deux modules ; verrou (écran rouge, clics bloqués, message), désactivation (téléphone rangé, message), muet (musique coupée, plus de vibration sonore), alarme entendue par un joueur proche, brouillage ciblé et de zone (barres de signal, app Réseau), surchauffe 1-2-3, IEM, destruction puis réparation ; persistance après réapparition et pour un joueur qui rejoint ; un brouilleur Crows EW posé près d'un joueur.
#### Notification « mise à jour disponible » réparée et plus claire (#811)

- Cause : `fn_integrityCheck` « start » attendait le choix du service Athéna puis bouclait `while { mode == "on" }` toutes les 2 h ; un « Non » ou un choix fait après les 30 premières secondes coupait la vérification pour toute la mission, et rien n'était noté au journal dans ce cas. La notification passait par `_raise` (bandeau une fois par lancement du jeu, texte « Mettez le mod à jour sur Steam ou reconstruisez le main.pbo », journalisée en ERROR) ; en anglais, le libellé « Mise à jour disponible » étant traduit seul, le reste du texte restait en français.
- Boucle qui suit le réglage toute la partie : contrôle dès le passage sur « Oui », puis toutes les 30 min.
- Journal module `UPDATE` (français) : chaque décision (attente du choix, service sur « Non », demande envoyée, erreur de la DLL ou d'Athena, à jour, version de développement plus récente, mise à jour disponible avec ou sans résumé, bandeau déjà montré pour cette version, notifications coupées).
- Bandeau + entrée des Notifications une fois par version (profil `COMSPEC_ATAK_UpdateNotified`, 20 dernières) ; carte épinglée (`COMSPEC_ATAK_Integrity`, clés `update_atak` / `update_link`) retirée dès que la version est à jour. Une DLL Link en retard alors que l'ATAK l'est aussi ne donne qu'une notification.
- Site : `GET /api/atak/version` renvoie `summary_fr` / `summary_en`, le « Résumé joueur » (titre + puces, 300 caractères max) de l'entrée « ATAK natif <version> » de ce fichier (`AtakReleaseInfo::playerSummary`).
- COMSPEC Link 2.0.85 : `LatestVersions` renvoie `OK|atak|link|résumé FR|résumé EN` (anciens main.pbo : champs en plus ignorés). Le résumé suit la langue du téléphone.
- Test en jeu sans changer de main.pbo : console de debug, `["remote", "1.0.0"] spawn comspec_atak_native_fnc_integrityCheck` (simule un ATAK 1.0.0, profil non modifié).
- À tester en jeu (DLL rebuild via build_mod.bat pour le résumé) : service Athéna sur « Oui », la commande ci-dessus → bandeau orange « Mise à jour ATAK disponible : version 1.93.0 (vous avez la 1.0.0). … », carte épinglée en tête des Notifications, lignes `[UPDATE]` dans l'app Debug ; en anglais, texte entièrement en anglais ; avec le main.pbo à jour, journal « ATAK 1.93.0 à jour » et aucune carte.
#### Liaison : 502/504 en alerte sans rapport auto, ligne ZEN une fois, dump boot plus clair (#812)


- Connect `fn_extensionCallback` (PostError) : 502 et 504 rejoignent 0/-1/401/403/429/503 en WARN, donc plus de `reportDiag` automatique (502 vu le 2026-10-14 à 10:50:24 UTC sur `POST /api/atak/explosive-timers`, pendant le reload PHP-FPM du déploiement de #802).
- Connect `XEH_preInit` : « Stub zen_attributes_fnc_addAttribute installé » passe en INFO, une fois par lancement d'Arma (`uiNamespace`).
- Connect `fn_diagStatusSnapshot` : « Version des mods » affiche « ATAK natif x.y.z » quand l'ancienne surcouche Athena n'est pas chargée (avant : « Athena — »).
- Connect `fn_logDump` : `athenaReady=false` du dump « boot » est suivi de « (avant le handshake : normal au démarrage) » ; ce dump part à CBA_settingsInitialized, avant le handshake.
- comspec_overwatch_connect.pbo repatché (tools/pbo_patch.py, 4 fichiers). main.pbo inchangé.
#### Carte du téléphone : nouveaux noms de repères, de cibles et de lieux, lieux masquables (#813)

- La carte du téléphone (`COMSPEC_RscMapAtak`) n'écrit plus le texte des marqueurs ni les noms de lieux (`sizeExLabel` et `sizeExNames` minuscules, comme la grille) ; l'ATAK les dessine : `fn_mapMarkerText` (repères Arma et Athena, LOCK) et `fn_mapPlaceText` (lieux de `nearestLocations`, liste faite une fois par carte).
- Styles : réglages CBA client `comspec_atak_native_map_marker_style` (plate, pill, card, outline, classic) et `comspec_atak_native_map_place_style` (tactical, tag, quiet, classic). Catégories : `comspec_atak_native_map_show_marker_text`, `_cities`, `_villages`, `_locals`, `_sites` (aussi dans CALQUES, espace « C », et Réglages > Carte).
- Désencombrement : étiquette à droite du symbole, sinon à gauche, sinon rien ; en zoom très éloigné seuls les LOCK et le repère sélectionné gardent leur nom ; villages sous l'échelle 0,25, lieux-dits et sites sous 0,08 ; un lieu qui chevauche une étiquette de repère n'est pas écrit.
- Largeur des textes mesurée une fois (`fn_mapTextW`, `ctrlTextWidth` sur un texte caché, cache).
- La carte de l'app JTAC passe sur `COMSPEC_RscMapJtac` et garde les textes Arma.
- Aperçus : /mnt/project-files/atak/etiquettes-carte/.

#### Liaison Athena : l'ancienne fenêtre « Lier le jeu à Athena » ne s'ouvre plus avec l'ATAK natif (#810)
- Connect `fn_accountLinkShow` et `auth/fn_openLogin` passent la main à l'action `["show", user]` de `comspec_atak_native_fnc_athenaLink` quand l'ATAK natif est chargé : ACE « Connexion Athena », hub « Compte », Dépannage liaison « Lier », tablette web, Réglages > Lier mon compte et bouton de l'écran d'authentification ouvrent le téléphone sur l'app Athena, onglet COMPTE (code à valider sur athena.ttrd.fr/lier, ou e-mail + mot de passe). Un appel automatique dans les 90 premières secondes de mission n'ouvre rien : il demande le code et affiche la notification. Sans l'ATAK natif, l'ancien comportement reste. La surcouche Iceman (atak_athena) n'est pas modifiée.
- À tester en jeu : mission sans session Athena, aucune ancienne fenêtre ; ACE > COMSPEC Athena > Connexion Athena ouvre le téléphone sur l'app Athena avec un code ; le valider sur athena.ttrd.fr/lier connecte.

#### App Comptes rendus refaite : une fiche par type de compte rendu (#814)
- `fn_reportTypes` : catalogue étendu (repère de ligne, requis, proposition, présentation par type : code, couleur, bandeau, outils, JNET, photo).
- `fn_reportForm` (nouveau) : fiche propre à chaque type ; `fn_reportAuto` (nouveau) : propositions tirées du jeu (knowsAbout du groupe, position estimée par targetKnowledge, effectifs, chargeurs, carburant, laser).
- `fn_formRender` : lignes `lineseg` et `linecombo`, libellé rouge et pastille élargie pour `line`.
- `fn_reportAction` : brouillons (`COMSPEC_ATAK_RepDrafts`), contrôle avant envoi, accusés `comspec_atak_native_reportRcpt` (relayés au camp, rejoués aux arrivants), photo via `captureReconImage`, `jnet_escalate` dans structured_data, accusé d'ordre via `fn_taskAction`.
- Site : `JnetFieldReportBridge` remonte de 1, 2 échelons ou jusqu'au sommet selon `jnet_escalate` ; libellés des nouveaux champs dans `AtakIcemanReportCatalog`.
- Non testé en jeu.

#### Photos : refus de la formule expliqué au joueur, sans rapport automatique (#818)
- Cause du rapport #661 (ÉCHEC · PhotoUpload — http_403 · feature_unavailable) : `AtakPlanAccess` (garde de formule depuis le 13/09) refuse toute l'API tactique quand la formule appliquée de la communauté n'a pas `atak` (essai fondateur de 30 jours terminé, formule `free`). Ce n'est pas une régression de #793/#794.
- Connect (`fn_extensionCallback`, PhotoUpload) : `ERR|http_403|fichier|feature_unavailable` → journalisé en WARN (pas de rapport auto), message clair FR (traduit EN dans i18n/en.txt), retour Galerie en alerte. comspec_overwatch_connect.pbo et main.pbo repatchés (tools/pbo_patch.py).
- Site (#817) : un tel rapport reçu des anciens mods est classé Faible (ni ticket ni e-mail) et son détail nomme la communauté et sa formule.
- À tester en jeu : avec une communauté sans la carte, prendre une photo ; Galerie > dernière photo refusée affiche le nouveau message (FR et EN), aucun rapport auto dans /admin/atak-mod-reports. Avec une communauté qui a la carte, l'envoi marche toujours.

## Erreur 502 au déploiement, journal de l'appareil à la bonne heure (site)

### Résumé joueur
Type : correctif
FR : Athena ne coupe plus tes envois pendant une mise à jour
- Une mise à jour d'Athena laisse finir les envois en cours : fini l'erreur 502 isolée pendant un déploiement.
- Le journal de l'appareil sur Athena affiche l'heure de ton PC, au lieu d'avoir deux heures d'avance.
EN : Athena no longer cuts your sends during an update
- An Athena update lets sends in progress finish: no more lone 502 error during a deployment.
- The device log on Athena shows your PC's time, instead of running two hours ahead.

### Détail technique

- Cause du 502 sur `POST /api/atak/explosive-timers` (2026-10-14 12:50:24 heure du joueur = 10:50:24 UTC) : le déploiement de #802 (étape « Pull on VPS » 10:50:19 → 10:50:24) finit par `systemctl reload php8.4-fpm`. Avec `process_control_timeout = 0` (défaut), le reload tue les workers occupés et nginx renvoie 502. Le code de l'endpoint n'est pas en cause.
- `deploy-vps.yml` : règle une fois `process_control_timeout = 10s` dans `/etc/php/8.4/fpm/php-fpm.conf` (sauvegarde `.bak-deploy`, `php-fpm -t` avant, restauration si refusé) puis reload.
- `AtakDeviceLogRepository` : COMSPEC Link envoie `logged_at` en heure locale sans fuseau ; l'écart entre la ligne la plus récente du lot et l'heure serveur, arrondi au quart d'heure (15 min à 14 h), est retiré des heures sans fuseau.
- Le volet mod (502/504 en alerte, ligne ZEN, dump) est dans une PR à part, livrée avec le regroupement ATAK.
## Eden : menu COMSPEC ATAK réparé, TBR et aide relais (ATAK natif 1.92.0)

### Résumé joueur
Type : correctif
FR : Le menu COMSPEC ATAK de l'éditeur Eden refonctionne
- « Aide : poser un relais » ouvre l'aide des relais au lieu d'afficher une erreur de script.
- « TBR · Tableau des relais » s'affiche de nouveau, au lieu de bloquer la souris derrière une fenêtre invisible.
- Les deux fenêtres se ferment avec le bouton Fermer ou la touche Échap.
- Poser un relais sans liaison Athena n'envoie plus de rapport d'erreur automatique « Relais ATAK ».
EN : The COMSPEC ATAK menu in the Eden editor works again
- "Help: placing a relay" opens the relay help instead of showing a script error.
- "TBR · Relay table" shows again, instead of locking the mouse behind an invisible window.
- Both windows close with the Close button or the Escape key.
- Placing a relay without an Athena connection no longer sends an automatic "Relais ATAK" error report.

### Détail technique

- Cause : les deux entrées du menu Eden s'exécutaient pendant que la liste déroulante du menu était encore ouverte. `BIS_fnc_openFieldManual` n'y trouvait pas d'écran parent (booléen passé à `createDisplay`, fn_openFieldManual.sqf ligne 40) ; la fenêtre TBR était créée mais restait invisible et gardait la souris.
- `fn_relayEden` : les modes `table` et `help` relancent leur travail dans un script à part (`spawn` + `uiSleep 0.05`), une fois le menu refermé ; fenêtre commune `_window` (fond assombri, panneau et titre en safeZone, couleurs explicites, Échap ferme).
- Nouveau mode `help` : le texte du manuel de terrain (`STR_COMSPEC_ATAK_CFG_BF0FFB1714`, CfgHints) dans une fenêtre défilante de l'éditeur ; le menu n'appelle plus `BIS_fnc_openFieldManual`. La page reste dans Aide > Manuel de terrain.
- Connect (`fn_syncAtakRelay`) : sans session Athena (`COMSPEC_AthenaReady` faux), la fiche du relais n'est plus envoyée ; la boucle serveur des relais (12 s) la remonte dès que la liaison est prête. `UpdateRelay` n'est plus appelé par `callExtLogged` : la DLL l'envoie en tâche de fond et rend un résultat vide, compté à tort comme « ÉCHEC · Relais ATAK | ["",0,0] » toutes les 12 s (journal de TTRD du 14/10). Seul un refus ERR|… est journalisé, en WARN (pas de rapport auto), suivi d'une pause d'une minute. Évite les rapports auto « ÉCHEC · Relais ATAK » (rapport #659). comspec_overwatch_connect.pbo repatché (tools/pbo_patch.py).
- À tester en jeu (Eden) : menu COMSPEC ATAK > Aide : poser un relais (fenêtre lisible, défilement, Fermer, Échap) ; TBR avec et sans relais posés (liste, Aller, Sélectionner, double clic, Fermer, Échap) ; la souris et la caméra d'Eden répondent après fermeture.

## Regroupement : images dans le téléphone, ordres au drone, plan de briefing, app Groupe, mode PETIT (ATAK natif 1.91.0, COMSPEC Link 2.0.84)

### Résumé joueur
Type : nouveauté
FR : Images du drone enfin visibles, ordres au drone sur la carte, plan de briefing partagé
- La caméra du drone, le viseur de l'app Photo et les diapositives du Briefing s'affichent dans le téléphone au lieu de rester noirs. Sur la carte, un clic droit donne des ordres au drone : aller, rechercher, stationnaire, atterrir.
- Le plan de briefing de l'app Plan apparaît sur la carte du site Athena et, par défaut, sur la carte du téléphone.
- En véhicule, la molette et le menu d'actions marchent de nouveau avec le téléphone en miniature. Le HUD des autres mods (boussole, heure…) ne passe plus par-dessus le téléphone.
- Nouvelle interface de l'app Groupe, et mode PETIT plus lisible : carte sur toute la largeur, plus d'apps sur l'accueil, messagerie en une vue, barre du haut allégée, fini le bandeau « indicatif · grille » en boucle. Le selfie peut aussi inverser les axes de la souris.
EN : Drone pictures finally visible, drone orders from the map, shared briefing plan
- The drone camera, the Photo app viewfinder and Briefing slides show in the phone instead of staying black. On the map, a right-click gives orders to the drone: go to, search, hover, land.
- The briefing plan from the Plan app shows on the Athena website map and, by default, on the phone map.
- In a vehicle, the scroll wheel and action menu work again with the phone as a miniature. Other mods' HUD (compass, time…) no longer draws over the phone.
- A new Group app interface, and a clearer SMALL mode: full-width map, more apps on the home screen, messages in one view, lighter top bar, no more repeating "callsign · grid" banner. The selfie can also invert the mouse axes.

### Détail technique

Une seule version pour 8 PR regroupées (#782, #783, #785, #786, #787, #788, #789, #791) : ATAK natif 1.91.0, COMSPEC Link 2.0.84 (la DLL change avec l'app Plan #787 : recompiler avec build_mod.bat ; migration du plan de briefing à lancer côté site). main.pbo reconstruit avec HEMTT ; i18n/en.txt fusionné à trois voies et localisation/template/xx.txt régénéré (tools/i18n.py community) ; dictionnaire JS du site régénéré. Non testé en jeu.

#### Drone, Photo et Briefing : les images s'affichent enfin dans le téléphone (#783)

- Résultat du TEST VIDÉO (TTRD, 21:20 en jeu, nuit) : les 4 cadres, dont les 2 dans le téléphone, montrent une image nette, alors que la vue drone (même en THERMIQUE) et le viseur Photo restent noirs. Différences supprimées : textures r2t désormais carrées (512/1024/2048, le 16/9 vient du rapport r2t), noms de cibles sans tiret bas ni chiffre (comspecdronecam<a-c>n<a-h>, comspecphotocam<a-d>), plus de correction de couleurs setPiPEffect type 3 (« BASSE LUMIÈRE » / « MODE NUIT » retirés de l'incrustation) ; incrustation Live cam de la carte et cliché drone en texture carrée.
- fn_pageDrone : bouton « X » rouge en haut à droite de l'image (droneAction "cam") ; VUE DIRECTE renommée CAMÉRA PLEIN ÉCRAN et placée dans la rangée principale.
- Briefing : Arma n'affiche pas les PNG (même cause que les portraits opérateur, #724) et la plupart des diapositives envoyées sur Athena sont des PNG. /api/atak/briefing-slides sert désormais une copie JPEG 2048 x 1024 (App\Support\BriefingSlideGameImage, image entière centrée sur fond noir), écrite une fois dans uploads/briefing-slides/<tenant>/atak/. Côté serveur seulement : le site doit être mis à jour sur le VPS. Les présentations Google Slides chargées par l'engrenage restent en PNG (DLL) : non corrigé ici.
- Textes coupés (captures TTRD) : fn_pageMap, rangée de la grande vue drone, largeur de chaque bouton mesurée sur son texte traduit (ctrlTextWidth), police réduite pour toute la rangée si elle déborde ; boutons de la petite fenêtre et barre de l'app Drone (fn_pageDrone) : police réduite tant que le libellé dépasse.
- Suite de la PR #780 (fusionnée avec seulement le TEST VIDÉO). Non testé en jeu.

#### Carte : ordres au drone depuis le clic droit (#785)

- fn_mapCtxMenu : ligne DRONE (drone `COMSPEC_ATAK_Drone` en vie) qui ouvre un sous-menu (clé `sub` de l'état mapCtx, redessiné à l'image suivante) : DGOTO, DHUNT, DHOVER, DLAND, RETOUR.
- fn_droneAction `["mapOrder", [genre, position]]` : liaison exigée, `goto` / `hunt [rayon choisi, pilote, centre]` (recherche en bandes de #777) / `goto [p, "HOVER"]` + nacelle à -90° / `goto [p, "LAND"]` ; journal et notification. `_send` renvoie maintenant `true` (false si zone interdite).
- fn_droneCmd `goto` : 2e argument facultatif `HOVER` | `LAND` : arrivée à 6 m (au lieu de 20 m), ou IA arrêtée à moins de 25 m ; `LAND` se pose au lieu du stationnaire (aussi en CQB).
- Non testé en jeu : vérifier le sous-menu, la précision du stationnaire et de l'atterrissage sur le point, et la recherche centrée sur le clic.

#### App Plan : le plan de briefing visible sur le site et sur la carte du téléphone (#787)

- Le serveur note dans `COMSPEC_ATAK_Plan_<camp>` l'UID de l'auteur de chaque modification (4e champ). `fn_planWeb` (toutes les 5 s) : l'auteur envoie tout le plan à Athena par COMSPEC Link `BriefingPlan.Push` (POST `/api/atak/briefing-plan`) ; sans confirmation après 30 à 60 s, un autre téléphone du camp relié l'envoie. Révision confirmée partagée (`COMSPEC_ATAK_PlanWeb_<camp>`) : un seul envoi par révision. Tracés à main levée allégés à 6000 sommets pour tout le plan.
- Site : table `atak_briefing_plans` (une ligne par carte et par camp, créée aussi à la première écriture), GET `/api/atak/briefing-plan` (plans des 12 dernières heures), calque Leaflet `atak-briefing-plan.js` (zones remplies, lignes de phase étiquetées, flèches, points), préférence `showBriefingPlan`.
- Calque Plan de la carte du téléphone : nouvelle préférence `COMSPEC_ATAK_PlanOnMap2`, active par défaut, avec le brouillon local plus pâle.
- Brouillons et plans enregistrés restent personnels : ils arrivent sur le site une fois publiés dans le plan partagé. Exception : serveur sans le mod natif (`COMSPEC_ATAK_PlanSrvOn` absent), chaque joueur envoie son brouillon (`owner` = UID, une ligne par joueur).
- Détection du serveur avec le mod : drapeau `COMSPEC_ATAK_PlanSrvOn` posé dès le preInit serveur ; côté client, `isServer` ou plan déjà écrit suffisent ; sans drapeau, le serveur est quand même essayé (5 s) avant le brouillon, puis `COMSPEC_ATAK_PlanNoSrv` évite d'attendre aux ajouts suivants. Le message « Serveur sans le mod ATAK natif » n'apparaît plus à tort en solo ou en partie hébergée.
- TOUT EFF. ne vidait que la couche visée (souvent le partagé, vide quand les éléments étaient partis au brouillon) : il vide maintenant tracé, brouillon et partagé autorisé, avec un bouton CONFIRMER ? visible.
- Clic droit de la carte du Plan : le relâchement n'arrivait pas toujours à la carte ; il est aussi détecté par `inputMouse 1` (premier des deux, traité une fois, événement `rclick`).

---

#### Véhicules : la molette et le menu d'actions ne sont plus bloqués par le téléphone (#789)

- Cause : le réglage « Masquer le menu d'actions » vaut « En main et porté » par défaut. En véhicule l'écran JPC n'est pas utilisable (fn_jpcScreen exige d'être à pied), le téléphone porté passe en miniature (cutRsc, texture = false) et COMSPEC_ATAK_ActionBlock bloquait PrevAction / NextAction : plus de menu d'actions, donc plus de « Démarrer le moteur ».
- Correctif : dans COMSPEC_ATAK_ActionBlock, joueur dans un véhicule (objectParent player non nul) et réglage CBA client `comspec_atak_native_action_menu_vehicle` coché (défaut) → seul le téléphone en main garde la molette, comme le mode « En main seulement ».
- À tester en jeu : miniature affichée, monter conducteur / passager / tireur, molette → menu d'actions et démarrage moteur ; téléphone en main en véhicule → la molette ne fait pas défiler le menu ; décocher le réglage → ancien comportement.

#### Téléphone : le HUD des autres mods ne passe plus par-dessus (#788)

- Arma dessine les calques cutRsc (RscTitles) de tous les mods au-dessus des fenêtres createDisplay : le téléphone en main passait sous le HUD F-PANO ECOTI (Workshop), et le téléphone porté sous les calques créés après le sien.
- Nouveau `fn_foreignHud` : tant que le téléphone est affiché, un per-frame CBA scanne chaque seconde les displays rangés dans `uiNamespace` et absents de `allDisplays` (les calques), hors téléphone (`COMSPEC_ATAK_*`) et HUD du jeu (`IGUI_displays`), et masque (`ctrlShow false`) leurs contrôles de premier niveau qui chevauchent la coque (`layoutGet` « visible »). Remasqués à chaque image si le mod les réaffiche, réaffichés à la fermeture ou quand ils ne chevauchent plus la coque. Aucun mod tiers modifié.
- Réglage CBA client `comspec_atak_native_hide_foreign_hud` (ATAK · Affichage, activé par défaut). Écran 3D du JPC non concerné.
- Limite : un texte posé par `cutText` / `titleText` ou dessiné par `drawIcon3D` n'a pas de display accessible et ne peut pas être masqué.

#### App Groupe : nouvelle interface (#791)

- fn_pageGroup réécrit : hero (symbole OTAN, nom, type, effectif, équipes, chef, accès), strip de bilan (respecte medShow allies), membres triés chef > moi > distance, fold « perso » (clés COMSPEC_ATAK_State grpFold_<clé>, fn_groupAction "fold"), QUITTER désactivé si seul, onglet GROUPES avec état vide.
- fn_ftRows : strip « VOUS », folds « role » et « place », grille de rôles 4 par rangée en mini portrait et 6 sinon (infobulle = libellé complet), boutons d'édition d'équipe sur deux rangées, APPLIQUER MON RÔLE remplacé par le bouton PRENDRE de la liste complète.
- fn_formRender : nouvelle ligne `field` (champ + bouton sur la même ligne) et 6e paramètre facultatif de `combo` ([libellé, code] : bouton intégré, code appelé avec la donnée choisie). Pas d'application au simple choix : la molette sur une liste fermée en change la ligne.
- 46 textes ajoutés à i18n/en.txt. Aucune nouvelle fonction de jeu. Non testé en jeu.

#### Mode PETIT : carte, accueil et messagerie plus lisibles, fin de la notification en boucle (#786)

- Retour de TTRD (13/10) : « INFO · TA1 · 1519 1729 » en boucle en mode PETIT. fn_inspectorUpdate envoyait la notification à chaque rendu en mode mini avec une unité sélectionnée ; fn_statusUpdate le rappelle chaque seconde et la grille change quand l'unité bouge, donc l'anti-doublon de fn_notify ne jouait pas. La notification part désormais de fn_mapSelect, une fois par clic.
- fn_layoutGet : page MAP en mini (hors texture JPC) sans dock ni rail ; fn_dockRender ne dessine rien quand les deux sont nuls. Paysage mini : 5 colonnes au lanceur (au lieu de 4).
- fn_launcherRender : pas de recherche en mini, tuiles à 0,8 de hauteur relative (0,92 en plein écran).
- fn_pageChat : vue partagée liste + conversation réservée au plein écran paysage.
- fn_layoutApply : en mini, météo (88514, 88523, 88524) et RAM masquées.
- Calculs de place (1080p, paysage mini) : écran 502 × 286 px, barre d'état + barre d'app 58 px, rail 49 px. Non testé en jeu.

#### Selfie : axes de la souris inversables (#782)

- Demande de TTRD (13/10) : inverser gauche / droite à la souris en mode selfie.
- Réglages CBA client (non forcés par le serveur) comspec_atak_native_selfie_invert_x / _y, lus dans fn_photoMode "MOUSE" (branche selfie uniquement ; le zoom de l'objectif arrière et le glisser de l'app Photo ne changent pas). Traductions ajoutées à en.txt.
- Non testé en jeu.

## Drone : bouton TEST VIDÉO pour l'image noire du téléphone (ATAK natif 1.90.0)

### Résumé joueur
Type : patch
FR : Un test pour réparer l'image noire du drone dans le téléphone
- Nouveau bouton TEST VIDÉO dans l'app Drone (RÉGLAGES) : pendant 20 s, la même vidéo s'affiche à 4 endroits numérotés. Dites-nous lesquels montrent une image : on saura où le téléphone bloque la vidéo.
- En anglais, l'image vidéo ne passe plus dans la traduction du téléphone.
EN : A test to fix the black drone picture in the phone
- New VIDEO TEST button in the Drone app (SETTINGS): for 20 s, the same video shows in 4 numbered places. Tell us which ones show a picture: we will know where the phone blocks the video.
- In English, the video picture no longer goes through the phone's translation.

### Détail technique
- Retour de TTRD (13/10) : la PiP s'affiche partout ailleurs dans le jeu, sauf dans le téléphone. fn_pipCheck "test" / "testEnd" : caméra devant le joueur, texture carrée `#(argb,512,512,1)r2t(comspecpiptest,1.0)` à 4 endroits (1 groupe de contenu, 2 display du téléphone, 3 RscDisplayEmpty par-dessus, 4 calque cutRsc RscTitleDisplayEmpty), journal [CAMERA] ; bouton dans la rangée RÉGLAGES de la vue caméra.
- fn_tr : les textures commençant par « #( » ne sont jamais traduites.
- Seul commit de la PR #771 resté hors du regroupement #777. Non testé en jeu.

## Langues ajoutées par la communauté (ATAK natif 1.89.0)

### Résumé joueur
Type : nouveauté
FR : Le téléphone peut être traduit dans d'autres langues par la communauté
- Une traduction du téléphone dans une autre langue peut maintenant être publiée sur le Workshop comme un petit mod séparé, par n'importe qui.
- Une fois ce mod chargé, sa langue apparaît dans Réglages > Langue du téléphone et dans les options COMSPEC, à côté de Français et English. En Auto, elle est choisie toute seule si ton jeu est dans cette langue.
- Une phrase pas encore traduite s'affiche en anglais.
- Le guide pour les traducteurs est fourni avec le mod.
EN : The phone can be translated into other languages by the community
- Anyone can now publish a translation of the phone into another language as a small separate mod on the Workshop.
- Once that mod is loaded, its language appears in Settings > Language on the phone and in the COMSPEC options, next to Français and English. On Auto, it is picked automatically when your game runs in that language.
- A sentence that is not translated yet is shown in English.
- A guide for translators ships with the mod.

### Détail technique

- Registre `CfgComspecLanguages` (config.cpp) : une classe par code de langue avec `name`, `file` (dictionnaire `[["français", "traduction"], ...]`, 3e colonne ignorée) et `gameLanguage[]`. L'ATAK natif y déclare `en` (i18n/en.txt) ; un mod de traduction ajoute la sienne sans toucher au code.
- Nouveaux `fn_langList` (langues déclarées, gardées dans uiNamespace) et `fn_langResolve` (valeur du réglage -> code ; Auto : français, sinon la langue dont `gameLanguage` correspond au jeu, sinon anglais ; langue d'un mod déchargé : comme Auto).
- `fn_langApply` charge l'anglais puis la langue choisie par-dessus (phrase manquante = anglais), garde un dictionnaire préparé par langue dans uiNamespace (`COMSPEC_ATAK_I18nUi_<code>`) et recharge quand la langue change (`COMSPEC_ATAK_I18nLoaded`). `fn_trMenu` (menus CBA Overwatch connect et SSE) suit le même chemin.
- Réglage CBA `comspec_atak_native_language` et Réglages > Langue du téléphone : Auto, Français, puis chaque langue de `fn_langList` (quatre boutons par ligne au plus).
- Kit traducteurs : `mod/COMSPEC_ATAK_Native/localisation/` (README EN/FR, `template/xx.txt` à trois colonnes, exemple d'addon `comspec_atak_lang_es`). `python3 tools/i18n.py community [fichier]` met à jour un fichier de traduction (ou régénère le modèle) et signale les `%1` perdus.
- Hors champ : noms d'objets et modules Eden/Zeus (stringtable, langue du jeu), fenêtres d'accord et de choix Athéna de la connexion Overwatch (textes FR/EN propres).
- main.pbo reconstruit avec HEMTT. COMSPEC Link reste en 2.0.83. Non testé en jeu : à vérifier, le choix de langue (Français, English, Auto) marche comme avant, et un mod de test construit depuis l'exemple ajoute bien sa langue.

## Regroupement : IA ennemie et ATAK perdus, drone, pylônes relais, SEEK, Zeus (ATAK natif 1.88.0)

### Résumé joueur
Type : nouveauté
FR : Un ATAK perdu peut trahir ton équipe, drone armé plus fiable, nouveaux pylônes relais
- L'IA ennemie ramasse les ATAK sur les corps et au sol et s'en sert pour te traquer. Le camp reçoit l'alerte « Compromission suspectée » ; un chef de groupe, le propriétaire ou le Zeus la valide dans Alertes pour couper le téléphone. Batterie vide, la SIM reste localisable par les relais.
- Drone : toutes les grenades, roquettes et charges se fixent dessous, la charge explose au contact de la cible, et la recherche balaie la zone en bandes au lieu de tourner en rond.
- Pylônes relais : vraies collisions, ruine au sol quand ils sont détruits, deux nouveaux monopoles de 12 et 20 m, et un calque « Relais » sur la carte de l'ATAK.
- Terminal SEEK : le clavier ne fait plus bouger ton personnage et une même personne n'est plus enregistrée en double. Zeus : le relevé des routes et villes dit s'il a démarré, et les noms de villes sont lisibles sur la carte.
EN : A lost ATAK can betray your team, more reliable armed drone, new relay towers
- Enemy AI picks up ATAKs from bodies and the ground and uses them to hunt you. The side gets a "Suspected compromise" alert; a group leader, the owner or the Zeus confirms it in Alerts to cut the phone off. With a dead battery, the SIM can still be located by the relays.
- Drone: every grenade, rocket and charge can be attached underneath, the charge explodes on contact with the target, and search sweeps the area in lanes instead of circling.
- Relay towers: real collisions, a ruin on the ground when destroyed, two new 12 m and 20 m monopoles, and a "Relays" layer on the ATAK map.
- SEEK terminal: the keyboard no longer moves your character and the same person is no longer recorded twice. Zeus: the roads and towns survey says whether it started, and town names are readable on the map.

### Détail technique

Une seule version pour 5 PR regroupées (#771, #773, #774, #775, #778) : ATAK natif 1.88.0. COMSPEC Link reste en 2.0.83 (aucune DLL modifiée). main.pbo et towers.pbo reconstruits avec HEMTT ; comspec_overwatch_connect.pbo repatché depuis les sources fusionnées (SEEK #773, Zeus #775, relais #778, tools/pbo_patch.py --changed) ; comspec_sse_biometrics.pbo (#773) et comspec_sse_guide.pbo (#775) repris tels quels. i18n/en.txt fusionné à trois voies. Non testé en jeu.

#### Pylônes relais : collisions, rendu, ruine, monopoles, calque Relais et tableau TBR (#778)

- Modèles (`tools/model_relay_towers.py`, régénérés) : boîtes et cornières à facettes, tubes lissés (les normales moyennées des massifs les éclairaient à contre-jour, d'où les bandes claires). Ombres : une enveloppe convexe fermée par pièce, rentrée de 6 mm (avant : 32 arêtes ouvertes par LOD d'ombre, paraboles et révolutions à pointe, et volumes collés aux faces visibles). Géométrie : enveloppe de chaque pièce du pylône simplifié (fini la boîte invisible de 4 m en tête et les croisillons traversables), géométrie de tir sur le treillis presque complet, nouvelles LOD vue (le plein seulement) et chemin praticable (massifs, dalles, paliers). Ruines : tronçons fermés séparés pliés avec le pylône.
- Matières (`tower_*.rvmat`) : reflets 0,9 → 0,2 environ, puissance 60 → 28, Fresnel métal poli (4,7) → acier patiné (1,4), diffus 0,82 pour l'acier galvanisé (trop blanc).
- Ruine : `fn_relayTowerRuin` (événement Killed posé par `fn_relayTower`, aussi sur les tours créées par le module) pose `COMSPEC_ATAK_RelayTower_XX_ruins` si le moteur n'a laissé aucune ruine au bout de 8 s.
- Monopoles `COMSPEC_ATAK_RelayTower_M12` (3 km, 10 Mbit/s, 32 places) et `_M20` (4,5 km, 15 Mbit/s, 48 places), ruines comprises, aperçus Eden.
- Fréquences (base) : attribut « Bande de fréquence » sur les pylônes et le module relais (formulaire Zeus aussi), variable `COMSPEC_AtakRelayFreq` (MHz, 0 = automatique), affichée sur la carte et dans le TBR ; pas encore utilisée dans les calculs de liaison.
- Eden : menu COMSPEC ATAK (TBR · tableau des relais : nom, type, camp, portée, bande, liaison, état, position, répéteurs isolés signalés ; Aller, Sélectionner ; aide relais). Module relais de type Tour fixe sans objet synchronisé : la tour de 40 m apparaît à sa place dans l'éditeur (objet local, non sauvé). `fn_relayEden`, `Cfg3DEN` (ouverture / nouvelle mission), `3DEN` ajouté aux dépendances de main.
- Carte ATAK : calque « Relais » (`COMSPEC_ATAK_RelaysLayer`, affiché par défaut) séparé du calque Wave Relay (couverture, liens) ; interrupteur aussi dans l'app Wave Relay. COMSPEC Link : avec l'ATAK natif chargé, le carré « Relais … » et son cercle (`fn_updateNearestRelayMap`) ne sont plus posés, l'alerte de sortie de portée reste.
- À tester en jeu : collisions au pied (marcher contre les massifs, les croisillons, l'armoire), tirs dans le treillis, sélection dans Eden ; ombres et reflets en plein soleil ; destruction (ruine qui reste, pas de doublon) ; monopoles 12 et 20 m ; module Tour fixe dans Eden (aperçu qui suit, disparaît à la suppression) ; menu COMSPEC ATAK > TBR ; calque Relais masqué / affiché sur la carte du téléphone.

#### Drone : charge qui explose, grenades acceptées, recherche en balayage (#771)

- fn_droneAction : charges reconnues par la simulation de la munition (shotGrenade, shotSmoke(X), shotIlluminating, shotRocket, shotMissile, shotMine, shotDirectionalBomb, shotShell à un coup…), variante `_Scripted` prise si elle existe ; liste de l'onglet TÂCHES par lignes de deux.
- fn_droneCmd « strike » : piqué final dans un CBA PFH (chaque image) au lieu d'une boucle planifiée à 0,05 s ; fusée de proximité 3,5 m, point le plus proche dépassé, cible tuée à moins de 25 m ; gestionnaires Killed et Dammaged qui déclenchent la charge en mode STRIKE ; charge retirée avant setDamage (pas de double explosion).
- fn_droneCmd « hunt » : vol piloté par notre boucle (IA MOVE coupée), montée 6 m/s en avançant, 50 km/h au moins, hauteur tenue sur le relief 60 m devant, montée devant un obstacle ; balayage en bandes (écart selon la hauteur), puis bandes croisées ; détection deux fois par seconde.
- XEH_postInitClient (ace_medical_woundReceived), fn_pageLinkAlly, fn_pageNetwork : `params` avec `["", défaut]` remplacé par `""` (erreur « Variable locale dans espace global »).
- Caméra du drone noire : pas de changement, cause toujours non confirmée.

#### Terminal SEEK : clavier gardé, plus de doublons, dossiers automatiques, photo et liaison (#773)

- Overwatch connect (embarqué) : `fn_sseKeyBlock` + `fn_sseTerminalGuard` (KeyDown sur le SEEK idd 9991 et sur le display 46, arme rangée à l'ouverture, rafraîchissement de la barre d'état quand la liaison change).
- `fn_sseLinkState` : même verdict que `fn_statusUpdate` du téléphone (COMSPEC_LinkState « offline » ne masque plus la liaison du téléphone). Repris par `fn_sseIdentityQuery`, `fn_ssePersonRefreshPanels` et le pack SSE (`fn_bioQuery`, `fn_bioCaptureLatentAction`).
- `fn_sseIdentityQuery` : même sujet + mêmes relevés = verdict repris, pas de nouvelle requête. `fn_seekHistoryPush` : une ligne par sujet (clé netId [9], compteur [10]) ; `fn_pageSse` additionne les compteurs.
- `fn_sseUploadFacePhoto` (extrait de la transmission) : appelé aussi par `fn_sseCaptureFacePhoto` quand le sujet a déjà un id Athena ; un cliché déjà joint ne repart plus à chaque réouverture.
- Site : `POST /api/sse/persons` en doublon complète la fiche existante (`mergeRetransmission`), classe au dossier sans nouvelle ligne de journal si rien ne change ; code de dossier inconnu → dossier créé (`findOrCreateByReference`) ; A1 sans dossier ouvert unique → dossier du jour `AUTO-AAMMJJ`.
- PBO reconstruits : main.pbo, comspec_overwatch_connect.pbo, comspec_sse_biometrics.pbo.

#### L'IA ennemie ramasse les ATAK et traque les joueurs ; secours SIM à batterie vide (#774)

- `fn_captureServer` (serveur, PFH 3 s et 5 s) : sources = corps (`allDeadMen`) et joueurs à terre portant l'étiquette `COMSPEC_ATAK_PhoneTag`, porte-objets au sol signalés par l'événement Put (`fn_linkAllyInit`), corps d'une IA porteuse (`COMSPEC_ATAK_CapOf`). L'IA ennemie la plus proche dans `capture_radius` reçoit `doMove`, prend le téléphone à 4 m (`unlinkItem`/`removeItem` là où le corps est local, cargo reconstruit pour un porte-objet) et garde l'identité du téléphone (`COMSPEC_ATAK_Ident`), donc GÉOLOC le retrouve.
- Exploitation : après `capture_crack` s si le propriétaire avait un code, rien si le téléphone était effacé. Toutes les `capture_refresh` s, le groupe porteur et jusqu'à 3 groupes du même camp à moins de `capture_share` m reçoivent `reveal` des joueurs du camp à balise BFT active à moins de `capture_hunt_radius` m, puis un point SAD sur le plus proche (`capture_hunt`). Variables de mission `COMSPEC_ATAK_NoPickup` / `COMSPEC_ATAK_NoHunt` sur une unité ou un groupe.
- `fn_compromise` (client) : étiquette publiée toutes les 5 s (UID, camp, indicatif, numéro, IMEI, terminal Athena, code, effacé), alertes (notification, alerte plein écran, journal), section « Compromissions » de l'app Alertes (VALIDER / REJETER / CARTE). État partagé `COMSPEC_ATAK_CaptureList`. Validée : l'exploitation s'arrête, le valideur appelle COMSPEC Link `CompromiseTerminal` (état `compromised`) ; sans liaison Athena, le serveur passe au propriétaire puis à un chef du camp. Le propriétaire reçoit `COMSPEC_ATAK_PhoneGen + 1` (nouvel IMEI).
- Site : `/api/atak/terminals/compromise` avec l'état `compromised` révoque les certificats encore valides du terminal (motif `keyCompromise`, `AtakRealismRepository::revokeCertificatesForTerminal`) et le note dans le journal d'activité.
- `fn_simSos` : `COMSPEC_ATAK_SimSos` publié quand la batterie simulée est à 0 ; GÉOLOC (`fn_ewAction`) et RECHERCHER (`fn_mapSearch`) répondent « secours SIM » avec une position tirée des relais qui entendent le téléphone (1 relais : cellule large, 2 : moyenne, 3+ : triangulation ; mission sans relais : antennes fictives, rayon doublé). Allié traçable en secours SIM même si « Tracer son propre camp » est décoché.
- Réglages CBA serveur : « ATAK · Capture par l'ennemi » (activé, distance, forçage, traque, portée, partage, rafraîchissement, qui valide) et dans « ATAK · Géolocalisation (GEOLOC) » : mode secours SIM, retrouver un allié.
- Pas de changement de DLL (COMSPEC Link 2.0.82 sait déjà envoyer `CompromiseTerminal`). Pas de migration.
- À tester en jeu (serveur dédié de préférence) : mourir près d'une IA ennemie avec son ATAK, la voir ramasser le téléphone ; alerte sur les ATAK du camp ; IA qui vient vers les joueurs à balise active ; mode avion ; VALIDER par un chef de groupe puis fin de la traque et certificat révoqué sur Athena ; GÉOLOC de l'IMEI volé ; batterie à 0 puis GÉOLOC du numéro par un allié, avec et sans relais dans la mission.

#### Zeus : relevé routes et villes qui répond, tutoriel SSE visible, villes lisibles sur la carte (#775)

Non testé en jeu. Retours de TTRD : « le tuto zeus et le module relever route et ville ne marchent pas », « mauvais placements » (app SSE), « les villes c'est trop laid, juste le titre de la ville avec un fond ».
- Relevé routes et villes (Overwatch connect embarqué) : le relevé partait bien mais n'écrivait que dans le journal du téléphone, invisible dans Zeus. Nouveau `fn_zeusFeedback` (bandeau ZEN, sinon message Zeus vanilla, sinon notification) appelé au lancement, en cas de refus et à la fin (`COMSPEC_GeoZeusNotify`). Module Eden : le Zeus / l'admin n'étaient testés qu'à l'initialisation (souvent pas encore connus, jamais vrais sur serveur dédié sans admin) ; ils sont maintenant testés une fois lié, avec repli sur n'importe quel joueur lié après 60 s et un seul envoi par serveur (`COMSPEC_GeoNetworkRunner`).
- SSE : `comspec_sse_fnc_openGuide` affiche le chapitre du manuel dans une fenêtre posée sur l'écran Zeus (display 312) ; hors Zeus, manuel de terrain comme avant. « Poser un exercice ici » confirme dans Zeus.
- Carte web (`atak-geo-network.js`, Overwatch beta, carte opérationnelle, tacmap) : localités en étiquettes `divIcon` (ville, bourg, village, lieu-dit), rang affiché selon le zoom et chevauchements retirés ; styles injectés par le script.
- App SSE du téléphone : bandeau, tuiles d'indicateurs, en-têtes de panneau et cases d'attributs prennent la hauteur mesurée de leur texte.
- À tester en jeu : poser « Relever routes et villes » dans Zeus (message attendu), « Tutoriel : manuel du Zeus » (fenêtre au-dessus de Zeus), l'app SSE en portrait et paysage.

## Regroupement : JTAC, Messages, Musique, Signaler un problème (ATAK natif 1.87.0, COMSPEC Link 2.0.83)

### Résumé joueur
Type : nouveauté
FR : JTAC plus réaliste, Messages, Musique et Signaler un problème plus pratiques
- JTAC : par défaut, c'est toi qui remplis le 9-LINE (IP, description de la cible, dégagement, remarques) et qui choisis ton appareil et ta cible ; les lignes calculées le restent. L'administrateur de ta communauté peut réactiver la complétion automatique dans Contrôle de mission.
- Messages : la molette fait défiler la liste et la conversation, un bouton « Nouveau SMS » montre les joueurs de ton camp en ligne, et l'app reste fluide avec beaucoup de canaux.
- Musique : en paysage, la pochette est à gauche et les commandes à droite ; Son, File d'attente, Favoris et Haut-parleurs s'ouvrent d'un clic, un seul à la fois, avec l'essentiel visible même fermés.
- Signaler un problème : nouvelle fenêtre avec six types de problème, une case pour joindre ton journal (clés et mots de passe retirés), et un message clair « transmis » ou la raison du refus. L'envoi ne fige plus le jeu.
EN : More realistic JTAC, easier Messages, Music and Report a problem
- JTAC: by default you fill in the 9-LINE yourself (IP, target description, egress, remarks) and pick your aircraft and target; computed lines stay computed. Your community administrator can turn automatic completion back on in Mission control.
- Messages: the mouse wheel scrolls the list and the conversation, a "New SMS" button shows the players on your side who are online, and the app stays smooth with many channels.
- Music: in landscape, the cover sits on the left and the controls on the right; Sound, Queue, Favourites and Speakers open with a click, one at a time, with the key information visible even when closed.
- Report a problem: a new window with six kinds of problem, a box to attach your log (keys and passwords removed), and a clear "sent" message or the reason it was refused. Sending no longer freezes the game.

### Détail technique

Une seule version pour 4 PR regroupées (#760, #761, #766, #768) : ATAK natif 1.87.0, COMSPEC Link 2.0.83 (la DLL change : nouveau réglage JTAC autorisé, recompiler avec build_mod.bat). main.pbo reconstruit avec HEMTT, comspec_overwatch_connect.pbo repatché (fenêtre Signaler un problème). i18n/en.txt fusionné à trois voies. Non testé en jeu.

#### JTAC : saisie manuelle par défaut, complétion automatique en réglage admin (#768)

- Nouveau réglage communauté `native_jtac_autofill` (catalogue AtakExperienceService, groupe réalisme, surface Contrôle de mission), défaut `off`. Ajouté à la liste blanche de COMSPEC Link (2.0.83) ; sans DLL à jour, la clé n'arrive pas et le mod reste en saisie manuelle.
- fn_jtacLines : toute la complétion de 1.85.0 (appareil le plus proche, cible laser, IP à 5 NM, ligne 5, menaces, FAH, dégagement, armement) n'agit que si la règle vaut `on`. Un IP proposé avant la coupure est effacé. Expose `autoOn`.
- fn_pageJtac : bouton IP AUTO masqué et textes d'aide adaptés quand la complétion est coupée.

#### Fenêtre « Signaler un problème » refaite, envoi et journal fiables (#766)

- `display_bug_report.hpp` (idd 9992) refait aux couleurs de l'ATAK natif : catégories en 6 boutons (`fn_bugReportPick`, code envoyé dans `channel`), champ multi-lignes 1500 caractères, `RscCheckBox` 9805 (cochée par défaut) avec explication, statut 9820, boutons « Envoyer le signalement » (primaire) / « Annuler ».
- `fn_bugReportSubmit` : lancé en `spawn` (extAwait asynchrone, plus de gel), message ≥ 10 caractères, résultat dans la fenêtre (succès : bouton « Fermer » ; échec : raison selon `rate_limited`, `invalid_url`, `http_503`, `timeout`, `invalid`, extension absente, bouton « Réessayer »).
- Journal : avant, le détail était coupé aux 1800 premiers caractères (les lignes les plus anciennes) et, au-dessus de 800, la DLL n'ajoutait pas la fin du journal. Désormais instantané court (< 800 : liaison, mode Athena, DLL, verdict du dépannage, fin du tampon session) + source `player` : la DLL joint les 3500 derniers caractères du journal fichier, lignes de secrets retirées. Case décochée : source `player-nolog`, rien de joint.
- `fn_reportDiag` : toute source commençant par `player` échappe à l'anti-spam automatique.
- Site : rien à changer, `/admin/atak-mod-reports` affiche déjà le détail (journal compris).

#### Messages : molette, bouton Nouveau SMS, chargement plus léger (#760)

Non testé en jeu. Retours de TTRD en jeu sur 1.83.0 : molette sans effet, bouton SMS manquant en bas de page, chargement lourd de tous les canaux.
- Molette : les groupes défilants imbriqués dans le contenu de la page ne recevaient pas la molette. `fn_pageChat` enregistre [groupe, hauteur du contenu] (`COMSPEC_ATAK_WheelGroups`) et pose une fois un `MouseZChanged` sur l'écran du téléphone qui fait défiler le groupe sous la souris.
- Bouton « + NOUVEAU SMS » en pied de liste (en main) : état `chatPick`, liste réduite aux contacts en ligne, ANNULER pour revenir.
- `fn_messagesAll` garde son résultat par canal tant que ses sources n'ont pas changé (au plus 5 s) : la liste, les pastilles (`fn_chatUnread`) et la barre d'état ne relisent plus chaque canal plusieurs fois par seconde.
- « Chargement… » tant que COMSPEC Link n'a pas fait son premier relevé (`COMSPEC_ChatPollBootstrapped`).
- Textes anglais des ajouts de 1.78.0 et 1.84.0 dans i18n/en.txt.

#### App Musique : sections repliables et lecteur côte à côte (#761)

Non testé en jeu. Retour de TTRD : défilement pénible en bas de l'onglet LECTURE. Pas de DLL à recompiler, pas de migration.
- fn_vizPlayer : conversion largeur / hauteur corrigée (COMSPEC_ATAK_Ratio = pixelH / pixelW : largeur d'un carré = hauteur / ratio). En paysage le test « écran étroit » se trompait et empilait la pochette au-dessus ; pochette carrée, icônes, boutons et sauts à la bonne largeur.
- fn_formRender : nouvelle ligne « fold » (en-tête repliable pleine largeur, chevron, résumé à droite) ; icône des curseurs à la bonne largeur.
- fn_pageMusic : sections en accordéon (clé « fold » de l'état d'interface, « Son » ouverte par défaut), action « fold » dans fn_musicAction.
- Icônes ui_chevron_down / ui_chevron_up (gen_assets.py) ; icônes du lecteur gardées chargées (fn_textureKeep).
- À vérifier en jeu : onglet LECTURE en plein écran paysage (pochette carrée à gauche), ouverture / fermeture des 4 sections, curseurs de volume dans « Son », portrait mini.

## Barlow Condensed revient en test dans Réglages > Police (ATAK natif 1.86.0)

### Résumé joueur
Type : patch
FR : Barlow Condensed à l'essai
- Réglages > Police propose « Barlow (test) », une version corrigée de la police de la 1.83.0. Roboto reste la police par défaut.
- Si le texte s'affiche mal avec Barlow, il suffit de repasser sur Roboto.
EN : Barlow Condensed on trial
- Settings > Font offers "Barlow (test)", a fixed version of the 1.83.0 font. Roboto stays the default.
- If text looks wrong with Barlow, just switch back to Roboto.

### Détail technique
- Cause probable des lettres décalées de la 1.83.0 (« RÉGLAGES » lu « 2ÉGLAGES ») : la table `.fxy` générée avait des trous (code 0 puis 32…, puis des codes isolés). Les `.fxy` d'ACE3 n'en ont aucun dans un bloc de 32 codes alignés (0-351, 992-1087, 8176-8191). `tools/gen_fonts.py` écrit maintenant des blocs complets de 32, les codes absents en entrée vide. Polices régénérées.
- Familles embarquées renommées `COMSPEC_BarlowC`, `COMSPEC_BarlowCBold`, `COMSPEC_BarlowCLight` ; `COMSPEC_Barlow*` (classes et balises) restent sur Roboto Condensed du jeu, écrites par le script. Valeur de réglage `barlow2` : un ancien choix `barlow` ne réactive pas la police.
- `fn_fontText` remplace les noms suivis de leur guillemet (sinon `COMSPEC_Barlow` remplacé dans `COMSPEC_BarlowCBold`).
- Non vérifiable hors du jeu : la troncature en hauteur vue en 1.83.0 peut venir d'une autre cause (hauteur de ligne). Si Barlow (test) est encore cassée, il faudra passer par FontToTGA (Arma 3 Tools) sur un PC Windows.
- À tester en jeu : Réglages > Police > BARLOW (TEST) ; accueil, notifications, Réglages, petits textes sous les valeurs ; retour ROBOTO.

## JTAC : la fiche 9-LINE se complète toute seule (ATAK natif 1.85.0)

### Résumé joueur
Type : nouveauté
FR : JTAC : la fiche 9-LINE se remplit toute seule
- Dès qu'il y a une cible, toute la fiche se complète : point initial (nommé d'après le lieu-dit le plus proche), cap, distance, altitude, description des ennemis vus, marquage, amis, dégagement, axe d'attaque et menaces sol-air.
- Les valeurs proposées apparaissent en vert et restent modifiables ; ce que tu tapes l'emporte toujours. IP AUTO remet le point initial proposé.
- Ton laser allumé sert de cible automatiquement, et l'appareil le plus proche est choisi s'il n'y en a pas.
- Quand il manque quelque chose, l'app dit exactement quoi et comment le faire.
EN : JTAC: the 9-LINE card fills itself in
- As soon as there is a target, the whole card fills in: initial point (named after the nearest place), heading, distance, elevation, description of the enemies seen, mark, friendlies, egress, attack heading and surface-to-air threats.
- Proposed values show in green and stay editable; what you type always wins. AUTO IP brings back the proposed initial point.
- Your laser, when on, becomes the target automatically, and the nearest aircraft is picked if none is.
- When something is missing, the app says exactly what and how to provide it.

### Détail technique

Non testé en jeu. Demande de TTRD : « On peut pas tout compléter ? » (capture de la fiche CAS BRIEF vide).
- fn_jtacLines : appareil le plus proche si aucun choisi, cible = point laser du joueur si rien n'est verrouillé, IP proposé à 5 NM côté appareil (sinon à l'opposé des amis) nommé d'après nearestLocations, ligne 5 (ennemis connus à 150 m : nombre × type, à l'arrêt / en mouvement), ligne 9 (direction vers l'IP, altitude de l'appareil), FAH (IP → cible ± 20), menaces (SAM / MANPADS / AAA par airLock des munitions, blindés, armes légères), armement par défaut. Propositions rangées sous auto_<clé> ; la saisie l'emporte.
- fn_jtacAction : « save » n'enregistre pas une proposition laissée telle quelle ; IP posé à la main = ipAuto faux, action ipAuto pour revenir à l'IP proposé ; NOUVELLE MISSION remet l'IP en automatique.
- fn_pageJtac : champs proposés en vert (nouveau genre de ligne autoedit dans fn_formRender), consigne explicite sans cible, « Il manque : … » détaillé, bouton IP AUTO.
- fn_formRender : le bandeau strip ne fait plus chevaucher ses deux textes (colonnes, passage à la ligne).
- À tester en jeu : fiche avec cible verrouillée, laser allumé sans verrouiller, IP posé à la main puis IP AUTO, description et menaces avec ennemis connus ou non, saisie d'une valeur puis changement de cible (la saisie reste, les propositions suivent).

## Retour à la police du jeu sur le téléphone (ATAK natif 1.84.0)

### Résumé joueur
Type : correctif
FR : Le téléphone retrouve sa police habituelle
- La nouvelle police de la 1.83.0 s'affichait mal : textes coupés et lettres décalées. Le téléphone repasse en Roboto Condensed, la police du jeu.
- Dans Réglages > Police, il reste Roboto (par défaut) et Purista. Les autres polices reviendront une fois corrigées.
EN : The phone gets its usual font back
- The new font from 1.83.0 rendered badly, with cut-off text and misplaced letters. The phone is back to Roboto Condensed, the game's font.
- Settings > Font now offers Roboto (default) and Purista. The other fonts will come back once fixed.

### Détail technique
- Test de TTRD en jeu sur la 1.83.0 : libellés coupés en hauteur, petits textes illisibles, caractères mal placés. Cause probable : les polices converties par `tools/gen_fonts.py` (métriques `.fxy` ou planches `.paa`) ne sont pas lues comme prévu par Arma.
- `ui/fonts.hpp` : `COMSPEC_Barlow`, `COMSPEC_BarlowBold` et `COMSPEC_BarlowLight` pointent sur les fichiers Roboto Condensed du jeu (mêmes chemins que BCE), donc toutes les classes et balises `<t font=…>` du téléphone sont de nouveau en Roboto. Les familles B612, Rajdhani et Share Tech restent dans le pbo mais ne sont plus proposées.
- Réglage `comspec_atak_native_font` réduit à `roboto` (défaut) et `purista` ; une ancienne valeur `barlow` retombe sur Roboto.
- À vérifier en jeu : textes du téléphone nets et entiers (écran d'accueil, notifications, tuiles), choix Purista dans Réglages > Police.

## Nouvelle police du téléphone, au choix (ATAK natif 1.83.0)

### Résumé joueur
Type : nouveauté
FR : Nouvelle police du téléphone, au choix
- Le téléphone passe en Barlow Condensed, une police condensée plus nette sur le petit écran.
- Réglages > Police permet d'en choisir une autre : B612, Rajdhani, Share Tech, ou celles du jeu (Roboto, Purista).
- Le même choix est dans Options des addons > COMSPEC > Police du téléphone.
EN : New phone font, your choice
- The phone now uses Barlow Condensed, a condensed font that stays sharp on the small screen.
- Settings > Font lets you pick another one: B612, Rajdhani, Share Tech, or the game's own (Roboto, Purista).
- The same choice is in Addon Options > COMSPEC > Phone font.

### Détail technique

Non testé en jeu. Demandes de TTRD : « T'as pas du tout changé les font », « Va pour Barlow Condensed, et mets un paramètre dans les settings pour le font et permet de choisir les autres ».
- Polices OFL converties au format Arma par `tools/gen_fonts.py` (sans FontToTga) : `.fxy` = « BIFo » + 01 01 00 00 puis 7 entiers 16 bits par caractère (code, page, x, y, largeur, hauteur, avance), format relevé sur les polices d'ACE3 ; planche `-01.paa` (DXT5, comme ACE3). 12 tailles (8 à 46) pour Barlow Condensed Medium/Bold/Regular, B612 Regular/Bold, Rajdhani Medium/Bold, Share Tech : 5,4 Mo. Familles `COMSPEC_Barlow`, `COMSPEC_BarlowBold`, `COMSPEC_BarlowLight`, `COMSPEC_B612(Bold)`, `COMSPEC_Rajdhani(Bold)`, `COMSPEC_ShareTech` dans `ui/fonts.hpp`, chaque taille appariée à Roboto Condensed du jeu pour les caractères absents (symboles). Licences dans `fonts/*/OFL.txt`, crédits dans CREDITS.md.
- Classes `COMSPEC_Rsc…` et balises `<t font=…>` des apps en `COMSPEC_Barlow…` ; police par défaut des balises `<t>` posée dans `COMSPEC_RscStructuredText >> Attributes`.
- Réglage CBA client `comspec_atak_native_font` (en tête de la liste COMSPEC, dans la police du jeu), repris dans Réglages > Affichage > Police : `fn_fontSet` pose `COMSPEC_ATAK_FontSet` [courant, gras, léger] ; `fn_fontApply` l'applique aux contrôles créés par `fn_pageCtrl`, `fn_formRender`, `fn_tileCreate` et aux textes fixes du display (`fn_layoutApply`) ; `fn_fontText` (appelée par `fn_tr`) remplace les familles dans le texte structuré et l'enveloppe dans la police choisie ; les `ctrlSetFont` et `drawIcon` des apps et de la carte lisent `COMSPEC_ATAK_FontSet`. Les rares contrôles créés par `ctrlCreate` hors de ces fonctions restent en Barlow.
- Règle pour les prochaines PR du téléphone : écrire `COMSPEC_Barlow`/`COMSPEC_BarlowBold` dans les balises `<t font=…>` et `(COMSPEC_ATAK_FontSet select 0|1|2)` pour `ctrlSetFont` et `drawIcon`, jamais `RobotoCondensed…`.

À tester en jeu
- Premier point : le texte du téléphone s'affiche bien en Barlow Condensed (format de police généré sans l'outil de Bohemia). Si le texte est illisible ou absent : Échap > Options > Options des addons > COMSPEC > Police du téléphone > Roboto Condensed remet la police du jeu.
- Les 6 polices, en mode mini (portrait, paysage) et plein écran ; aucun libellé qui déborde (BFT, Alertes, Réglages, Drone, CFF, tuiles du lanceur) ; textes de la carte.

## Gouttes de pluie sur l'écran désactivées (ATAK natif 1.82.0)

### Résumé joueur
Type : patch
FR : Plus de gouttes de pluie sur l'écran du téléphone pour le moment
- Sous la pluie, l'écran du téléphone ne se couvre plus de petites bulles.
- L'effet revient plus tard, refait avec des gouttes qui glissent.
EN : No more raindrops on the phone screen for now
- In the rain, the phone screen no longer gets covered in small bubbles.
- The effect will come back later, redone with sliding drops.

### Détail technique
- Demande de TTRD : effet de pluie mis de côté (W.I.P). fn_screenSurface ne dessine plus aucune goutte ; la saleté, le sang, les traces de doigts et le nettoyage sont inchangés. La refonte animée reste en brouillon (PR #728).
- À vérifier en jeu : sous la pluie, téléphone sorti, plus aucune goutte ; poussière et traces toujours visibles.

## Grande mise à jour regroupée : téléphone, vidéo par relais, Athena (ATAK natif 1.81.0, COMSPEC Link 2.0.82, site)

### Résumé joueur
Type : nouveauté
FR : Grosse mise à jour : messagerie réparée, vidéo par relais, applis refaites
- Applis du téléphone refaites : Musique (grande pochette, barre de progression), centre de notifications (cartes par urgence, filtres), appareil photo plein écran (raccourcis lisibles, ISO et vitesse qui suivent la lumière) et Briefing (un engrenage pour coller le lien Google Slides). Les textes sont plus lisibles, avec une taille « Très grand ».
- Messages fonctionne à nouveau (canaux, défilement, non-lus) et les SMS sont plus fiables. Ton portrait opérateur Athena s'affiche dans le téléphone et dans Tinder.
- Le drone, la caméra casque, le Live cam et les caméras fixes passent par les relais de la mission : la qualité de l'image suit la liaison. Une caméra noire est expliquée, le drone a un bouton VUE DIRECTE, et le drone comme le marquage de cibles font moins ramer le jeu.
- Côté Athena : véhicules et drones de nouveau sur la carte web, check de démarrage posté dans le Discord (désactivable), et sur le site une page Configuration refaite et un lecteur d'images de reconnaissance avec zoom et plein écran.
EN : Big update: messaging fixed, video through relays, apps redone
- Phone apps redone: Music (big cover, progress bar), notification centre (cards by urgency, filters), full-screen camera (readable shortcuts, ISO and shutter speed that follow the light) and Briefing (a gear to paste the Google Slides link). Text is easier to read, with a "Very large" size.
- Messages works again (channels, scrolling, unread counts) and SMS are more reliable. Your Athena operator portrait shows on the phone and in Tinder.
- The drone, helmet camera, Live cam and fixed cameras go through the mission's relays: image quality follows the link. A black camera is explained, the drone has a DIRECT VIEW button, and both the drone and target marking slow the game down less.
- On Athena: vehicles and drones are back on the web map, the startup check is posted to the Discord (can be turned off), and on the site a redone Configuration page and a recon image viewer with zoom and full screen.

### Détail technique

Une seule version pour 17 PR regroupées (#720, #721, #724, #727, #731 à #736, #738 à #743, #745) : ATAK natif 1.81.0, COMSPEC Link 2.0.82 (la DLL change : cache des portraits, JSON des véhicules, check de démarrage). main.pbo reconstruit avec HEMTT, comspec_overwatch_connect.pbo repatché (fn_extensionCallback, fn_hashMapToJson). Les photos Discord (#744) sont arrivées sur main avant, en 1.80.0. Gouttes de pluie (#728) non incluse : rendu en attente de validation. Non testé en jeu.
- Conflits résolus : liste des fonctions de config.cpp (union), nacelle « link » de fn_droneAction (cache 0,2 s de #732 + chemin par relais de #734), fn_droneStatic (palier adaptatif de #734 + diagnostic PiP de #736), incrustation Live cam de la carte (hauteur #734 + police mini #740).
- Live cam : #734 (fn_videoLink, utilisé par le drone, la caméra casque et les caméras fixes) et #743 (fn_feedLink, définition et décrochages de la page Live cam) coexistent ; le changement de palier n'est plus notifié qu'une fois (boucle fn_livecamStart). À unifier plus tard.

#### Fiche personnel : marges des encarts Avancement et Visibilité (#735)

- Site seul : encarts Avancement et Visibilité de la fiche personnel espacés et alignés (bureau, mobile, mode nuit) ; feuille personnel-file-refresh.css.

#### Back-office : refonte complète de la page Configuration (#738)

- Site seul : page Configuration du back-office refaite (sections, recherche, repérage des réglages modifiés, mode nuit, mobile) ; nouveaux back-office-configuration.css/js, test ConfigurationPageAssetTest.

#### Lecteur d'images de reconnaissance (zoom, plein écran, fiche, album) (#739)

- Site seul : lecteur d'images de reconnaissance (zoom, plein écran, fiche de la prise de vue, album) sur la carte ATAK, la tacmap et les remontées ; vue recon-viewer, route et API associées, test ReconImageViewerTest. Le lien « Ouvrir la photo sur Athena » des photos Discord (#744) ouvre maintenant ce lecteur (ReconImageStorage::viewerUrl).

#### Véhicules refusés par Athena (HTTP 400) : JSON réparé, motif gardé dans le journal (#731)

Non testé en jeu. Signalé par TTRD : `HTTP POST code 400 /public/api/atak/vehicles` en boucle juste après la connexion, alors que fiche opérateur, position et chat passaient. Il faut recompiler la DLL (build_mod.bat) ; pas de migration. La partie site n'est active qu'après déploiement sur le VPS.
- Cause : Athena répond 400 quand le corps du véhicule est illisible ou sans indicatif. Le sérialiseur JSON d'Overwatch connect laissait passer des sauts de ligne bruts, des nombres non finis (« nan », « 1.#INF ») et un guillemet seul pour un tableau imbriqué : tout le corps devenait illisible, donc sans indicatif. Le motif exact n'était pas journalisé, la cause précise de ces trois 400 reste déduite.
- Overwatch connect : chaînes échappées (sauts de ligne, tabulations, autres caractères de contrôle), nombres non finis envoyés à 0, tableaux imbriqués et camps/objets/groupes sérialisés proprement.
- COMSPEC Link 2.0.81 : un corps encore illisible est réparé avant l'envoi (caractères de contrôle, nan/inf, virgule décimale). Pour un refus 4xx (hors 401/403/429), le journal du jeu garde désormais le motif renvoyé par Athena, et pour un 400/422 le début du corps envoyé : `ÉCHEC · HTTP POST — code 400 · /public/api/atak/vehicles (il y a 0 s) — réponse : … · envoyé : {…}`.
- Site : même réparation du JSON côté Athena, indicatif repris aussi de `vehicle_id` ou `vehicle_type`, et le 400 dit pourquoi (JSON illisible ou clés reçues), avec une ligne `[atak/vehicles]` dans error_log.
- À vérifier en jeu : après connexion, plus de 400 sur /api/atak/vehicles en montant dans un véhicule ou en pilotant un drone ; si un 400 revient, copier la ligne ÉCHEC complète du RPT (elle contient maintenant le motif).

#### Portrait opérateur dans le téléphone (#724)

Non testé en jeu. Signalé par TTRD : le volet rapide n'affichait que les initiales au lieu du portrait opérateur. DLL à recompiler (build_mod.bat), site à déployer sur le VPS, pas de migration.
- Site : la vignette JPEG du portrait (uploads/portraits/atak) est faite aussi quand le portrait n'existe que dans storage/uploads (repli déjà servi par le site), et écrite dans storage/uploads si public/uploads n'est pas inscriptible. Un portrait JPEG reste envoyé tel quel si la conversion échoue.
- Site : /api/atak/player-profile renvoie `avatar_status` (no_portrait, source_missing, unsupported_format, write_failed) et l'écrit dans le journal PHP (`[atak/player-profile]`) quand une image existe mais ne peut pas partir.
- COMSPEC Link 2.0.81 : GetPlayerAvatarInfo transmet cette raison (colonne « S: »).
- Téléphone : la réponse est découpée sans fusionner les colonnes vides (un nom ou un indicatif vide décalait l'adresse de l'image). Chaque échec (profil, téléchargement, format) est écrit en français dans le journal (RPT et app Debug, module PORTRAIT) avec sa raison, et le joueur reçoit une notification pour son propre portrait, une fois par raison.
- Cache des portraits (demande de TTRD) : l'image téléchargée reste sur le disque et le profil Arma retient laquelle va avec quel joueur. D'une partie à l'autre, le portrait (le sien, équipiers, groupe, chat) s'affiche tout de suite, même hors ligne ; une seule vérification par partie demande l'adresse au site, et l'image n'est retéléchargée que si le portrait a changé ou si le fichier manque (COMSPEC Link 2.0.81 rend le fichier déjà présent sans requête). Pendant un échec, l'image en cache reste affichée.
- Traduction : un chemin d'image téléchargée (C:/…/x.jpg) n'est plus passé au dictionnaire anglais.

#### Check de démarrage posté dans le Discord Athena (#742)

Non testé en jeu. Il faut recompiler la DLL (build_mod.bat) et déployer le site ; pas de migration. Le salon et son webhook sont à créer : sans `DISCORD_DEBUG_WEBHOOK` dans le `.env` du VPS, Athena accepte le rapport et ne poste rien.
- Nouveau `fn_startupCheck` (appelé par le postInit client, une fois par mission) : 45 s après l'arrivée en jeu, après le contrôle de l'installation (`fn_integrityCheck`), il évalue 11 étapes (main.pbo unique et cohérent, téléphone initialisé, DLL chargée et de la version attendue, extension qui répond et appels Async, portail Athena joignable, compte lié, mise à jour disponible, CBA/ACE/ACRE/TFAR, Overwatch connect/SSE/pylônes, FPS et ping, erreurs du journal) et les envoie par la commande DLL `StartupCheck`. Rien n'est envoyé avec le service Athéna sur « Non » ou le nouveau réglage CBA client `comspec_atak_native_startup_report` décoché. Trois essais en cas d'échec réseau. Résultat dans le journal (`[STARTUP]`) et dans l'app Debug, onglet DLL.
- Données envoyées : pseudo, indicatif, versions (mod, config, DLL, Arma), carte et nom de mission, étapes. L'UID Steam n'est envoyé que haché (`hashValue`, pour le dédoublonnage), aucune adresse IP ni jeton.
- COMSPEC Link 2.0.81 : commande `StartupCheck` (champs `clé=valeur` mis en JSON par la DLL), `POST /api/atak/startup-check` sans clé API.
- Site : `POST /api/atak/startup-check` (public, 20 requêtes / 10 min par adresse, rien en base) poste un embed par joueur et par session dans le webhook `DISCORD_DEBUG_WEBHOOK` : ✅/⚠️/❌ par étape, libellés FR / EN, joueur, versions, carte, date. Anti-spam : un message par session, 6 par joueur et par heure, 20 par minute au total. Textes du jeu nettoyés avant Discord (liens retirés, adresses IP tronquées, dossier utilisateur Windows masqué, mentions neutralisées). Aucun lien dans le message.

#### Version de la DLL affichée dans « À propos » (#720)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Capture de TTRD : « Extension (DLL) : inconnue », « COMSPEC Link 1.6.16 », « COMSPEC ATAK 1.11.0 ».
- « Extension (DLL) » restait « inconnue » : la valeur lue n'était jamais remplie. La version est maintenant demandée à la DLL chargée (GetExtensionVersion, nouvelle fonction fn_extVersion) ; DLL muette : « absente ou trop ancienne : relancez build_mod.bat » ; autre version que celle attendue : affichée en orange avec la version attendue.
- La ligne « COMSPEC Link 1.6.16 » montrait la version de l'addon Overwatch connect, pas celle de la DLL : renommée « Addon Overwatch connect ».
- « COMSPEC ATAK » passe en rouge si le code et la config du main.pbo n'ont pas la même version (deux main.pbo chargés).
- Le contrôle d'installation et le journal Debug lisent la même version de DLL.
- À vérifier en jeu : « 1.11.0 » sur la capture veut dire que le main.pbo chargé date d'avant la 1.69.0 (vieux main.pbo encore chargé, ou mod pas mis à jour).

#### Centre de notifications refondu (#721)

Non testé en jeu. Demande de TTRD : améliorer l'UI des notifications. Pas de DLL à recompiler, pas de migration.
- Une carte par notification : liseré et pastille d'icône à la couleur de gravité (info, message, attention, erreur), source en titre (« Drone », « Synchronisation »…), texte dessous, heure relative (« à l'instant », « il y a 4 min ») et pastille verte sur les non lues.
- Un texte répété ne s'empile plus : il remonte en tête avec un compteur « ×3 ».
- Croix sur chaque carte pour l'effacer seule ; clic sur la carte : ouvre l'app concernée quand elle est connue (Drone, Messagerie, Photos, Synchronisation…).
- Filtres Toutes / Alertes / Infos / Messages avec leur nombre ; « Tout effacer » efface ce que le filtre affiche.
- État du mod épinglé en tête (PBO en double, DLL périmée, mise à jour disponible, 1.69.0) en rouge ou orange, clic vers Debug > DLL ; ces cartes restent tant que l'anomalie existe.
- Réglages rapides en une barre compacte : mode avion, Bluetooth, silencieux, et accès au volet rapide complet.
- État vide soigné (« Tout est calme ») ; textes traduits en anglais. L'écran de verrouillage partage les mêmes icônes et titres.

#### App Musique : lecteur refait (#727)

Non testé en jeu. Demande de TTRD : améliorer l'UI de l'onglet LECTURE. Pas de DLL à recompiler, pas de migration ; la lecture elle-même (fichiers, liens, serveur, Athena, haut-parleur de proximité) ne change pas.
- Lecteur : grande pochette avec l'égaliseur animé dedans, titre plus gros, ligne « artiste · source · Piste 1 / 4 » avec les états EN PAUSE, CHARGEMENT, ERREUR et HAUT-PARLEUR. Les pistes Athena affichaient « · piste 1/4 » sans source : corrigé (source « Athena », artiste quand il n'est pas déjà dans le titre).
- Barre de progression plus épaisse, avec un curseur rond et les temps aux deux bouts ; un clic sur la barre va à ce passage de la piste (haut-parleur : les joueurs proches suivent).
- Commandes en icônes centrées : aléatoire, précédent, lecture / pause (grosse pastille), suivant, répéter (non, la file, le morceau, au clic). Sauts -30 / -10 / +10 / +30 s et STOP en petits boutons dessous.
- Volumes : curseurs 0-100 au cran de 1 au lieu de - / valeur / +, son appliqué pendant le glissé ; l'icône (casque, haut-parleur) coupe et rétablit. Le volume en service (écouteurs ou haut-parleur) est surligné.
- Les lignes « Répéter » et « Lecture aléatoire » sont remplacées par les icônes du lecteur ; file d'attente, favoris et haut-parleurs proches restent dessous.
- À vérifier en jeu : téléphone en main plein écran (et taille réduite), portrait mini, clic sur la barre (fichier local, lien, piste serveur), glissé des deux curseurs avec et sans haut-parleur, icônes aléatoire / répéter, traduction anglaise.

#### Drone et marquage de cibles plus légers (#732)

Non testé en jeu. Retour de TTRD : « le drone fait lag, ou le marquage ? » (capture : alerte « LOCK 093 perdu »).
- `fn_droneAction` ["link"] répond avant le reste du fichier (tables d'ordres refaites à chaque appel) et garde son résultat 0,2 s : il était appelé une fois par cible marquée et par passage du suivi (2 fois/s), plus incrustations FLIR (4/s), neige (2/s) et flotte.
- `fn_lockAction` tick : source (œil, direction, liaison) calculée une fois par passage et par source, pas par cible. Visée (["aimed"], 4/s caméra ouverte) : tri grossier par position avant `isKindOf`, `aimPos`, boîte englobante et `checkVisibility` (caméra zoomée, `nearEntities` couvre plus d'un kilomètre).
- Bandeaux : cibles perdues dans le même passage regroupées (« 3 cibles perdues : LOCK 093, 094, 095 »), 30 s au plus souvent par cible ; « raccroché » seulement si la perte a été annoncée. Le journal du drone note toujours tout. Traduction « LOCK %1 raccroché » corrigée (reacquired, pas released).
- Marqueur suivi : `setMarkerPosLocal` à chaque déplacement, `setMarkerPos` (réseau et file JIP) toutes les 3 s au plus par cible, au lieu de chaque seconde.
- Cadres LOCK de la caméra (`fn_droneFlir`, chaque image) : liste des cibles relue 4 fois par seconde au lieu de chaque image (elle était triée à chaque lecture).
- Carte : la flotte reprend la liaison relevée par le suivi (`COMSPEC_DroneBgLinked`) au lieu d'un calcul de relief par drone et par image.
- Journal du drone : redessin des onglets CIBLES/JOURNAL sur un compteur de lignes (`COMSPEC_ATAK_DroneLogN`) ; plafonné à 100 lignes, il ne se redessinait plus.
- Mesures : nouvelle `fn_perfAcc`. Toutes les 30 s, ligne RPT `[PERF] Boucles 30 s` : img/s, images lentes (plus de 50 ms) et la pire, drone, cibles marquées, page, puis pour chaque boucle (suivi drone, flotte, nacelle, cadres caméra, neige, marquage suivi et visée, carte, carte drone, dessin de chaque page) appels, moyenne et pire en ms, les plus coûteuses d'abord. « Image figée » indique aussi drone, cibles marquées et page.

À mesurer en jeu : envoyer le RPT après une séance drone + marquage (Envoyer_mes_logs.bat) ; les lignes `[PERF] Boucles 30 s` disent quelle boucle pèse. Si aucune ne dépasse quelques ms, le coût vient du rendu de la caméra elle-même (régler QUALITÉ dans l'app Drone).

#### Tinder : portrait opérateur à la place du cœur (#733)

- `fn_pageDating` : photo 0 = portrait opérateur (`fn_avatarPath`, caméra r2t coupée) quand il existe ; photos 1 et 2 (et 0 sans portrait) en direct comme avant.
- Image sous la vue en direct (PiP coupé ou rendu en retard) : portrait, sinon `editorPreview` de la classe de l'unité, sinon aperçu Eden du soldat de base de son camp. Profils IA HVT / SSE compris.
- Lignes MATCHS (joueurs et IA) : portrait, sinon insigne de grade (`BIS_fnc_rankParams`). Le cœur reste l'icône de l'app et des écrans vides.
- Sous-titre « Ma carte » mis à jour, traduction anglaise dans i18n/en.txt. main.pbo (HEMTT) reconstruit.

#### Flux vidéo selon les relais (#734)

Non testé en jeu. Demande de TTRD : « Améliore la gestion des flux vidéo suivant les relais ».
- Nouveau `fn_videoLink` (functions/network) : chemin radio du flux, meilleur débit entre direct, un relais (source → relais → téléphone) et réseau (relais A → cœur de réseau → relais B, -20 %, +60 ms). Chaque bond suit la règle de `fn_relayNet "link"` (1 - (d/portée)², relief à 35 %), les répéteurs, brouilleurs, batteries et saturation déjà comptés par `fn_relayNet "list"`, la réception du téléphone bornée par `fn_linkQuality`. Paliers : 1080p dès 7 Mbit/s, 720p dès 3,5, 480p dès 1,2, image par image dès 0,15. Cache 1 s par flux.
- Débit adaptatif (`"adapt"`) : baisse après 2 s, remontée après 6 s, perte immédiate ; raison en français au journal du drone (Live cam et caméras fixes : notification).
- Drone : `["link"]` de `fn_droneAction` prend le meilleur chemin (direct ou relais) et renvoie le flux en 5e élément ; le retour automatique ne part que si aucun chemin ne passe. Drones Mavic : signal direct du mod Mavic inchangé, relais en plus.
- `fn_droneStatic` : 480p à 6 i/s et image par image à 1,5 i/s (la caméra se fige entre deux images, même mécanisme que le gel TNT), grain de compression en 480p et moins, qualité du chemin pour les défauts TNT. Caméra casque d'un équipier : même calcul (maillage 3 km ou relais). Rendu de base (r2t, camBind) non touché.
- Incrustations : palier, débit, relais et latence (vue caméra du drone, Live cam, caméra fixe, petite fenêtre de la carte), « Flux 720p via … » dans l'en-tête de l'app Drone.
- Caméras fixes : « Hors réseau des relais » quand aucun chemin ; Live cam : flux coupé quand le porteur sort du réseau.
- Réglage CBA serveur « Flux vidéo selon les relais » (coché par défaut). Aucun relais du camp dans la mission : mode arcade, comportement d'avant. Coût : quelques `terrainIntersectASL` par seconde, rien par image.

#### Vue caméra noire : explication claire et vue directe du drone (#736)

- Retour de TTRD (13/10, 12:35 en jeu) : vue caméra de l'app Drone noire, puis viseur de l'app Photo noir aussi, en plein jour, PiP déclarée active (aucun NO DATA ni VISEUR INDISPONIBLE). Cause commune aux deux rendus r2t, non confirmée : rien n'est testé en jeu.
- Nouveau `fn_pipCheck` (dossier camera) : `check` (PiP coupée → raison affichée sous NO DATA + notification + RPT ; distance de vue PiP < 300 m remontée), `watch` (RPT `[CAMERA]` étapes 1 à 5 : PiP, caméra et hauteur au-dessus du terrain, cible de rendu, texture, image ; `getVideoOptions` ; relevé à 2 s) et `rebind` (texture reposée sur l'image une image, deux images et 0,6 s après la création de la caméra).
- Drone : noms de cible de rendu par qualité (`comspec_dronecam<q>_<n>`), plus jamais un même nom avec une autre taille de texture ; `feedWatch` appelé par la page ; `direct` / `directEnd` : caméra plein écran sans r2t attachée au drone, pose recopiée par la boucle de la nacelle (`COMSPEC_ATAK_DroneShotCam`), téléphone rangé, Échap (KeyDown du display 46) rouvre l'app Drone ; coupée si joueur mort, drone perdu, liaison perdue ou téléphone ouvert.
- Photo : même contrôle et même journal à l'ouverture du viseur.
- Live cam, caméra Tinder et vue aérienne du JPC : même contrôle PiP (journal RPT et notification au lieu d'une image noire).

#### Textes du téléphone plus lisibles (#740)

Non testé en jeu. Demande de TTRD : « Améliore le font de notre application ».
- Échelle typographique unique dans `fn_layoutGet` : `font` (corps), `fontSmall` (0,78 → 0,82 × corps), nouvelles clés `fontCaption` (0,68 × corps), `fontTitle` (1,2 ×), `fontDisplay` (1,9 ×). Planchers en pixels réels (corps 16 px, courant 15 px, légende 13 px), hors écran-texture de la tablette JPC.
- `COMSPEC_ATAK_FontMin` (plancher des légendes, posé par `fn_layoutGet`, 0 au preInit) : les 83 `ctrlSetFontHeight` calculés sous ce seuil (`_fs * 0.66` à `_fs * 0.85`, tailles passées aux helpers, boucles d'ajustement des tuiles) sont bornés par `max COMSPEC_ATAK_FontMin`. Avant : jusqu'à ~10 px de haut sur un écran 1080p en mode mini.
- Réglages > Taille du texte : option TRÈS GRAND (1,3), borne haute 1,25 → 1,35.
- `ui/controls.hpp` : macros `ATAK_FONT`, `ATAK_FONT_BOLD`, `ATAK_FONT_LIGHT`, `ATAK_FONT_MONO`, `ATAK_FONT_MONO_BOLD` ; `COMSPEC_RscLabel` passe de EtelkaMonospacePro à RobotoCondensedBold ; nouvelles classes `COMSPEC_RscMono` / `COMSPEC_RscMonoRight` (en-tête de colonnes du BFT, valeurs de la solution CFF). Établi (accessoires) : PuristaBold → RobotoCondensedBold.
- Polices du jeu uniquement (rien d'embarqué, pas de question de licence).
- À tester en jeu : mode mini portrait et paysage en 1080p, plein écran à 60 % (#709), Réglages > Taille du texte sur les 4 valeurs ; vérifier qu'aucun libellé ne déborde (BFT, Alertes, Réglages, Drone, CFF, tuiles du lanceur).

#### Viseur photo plein écran : raccourcis et affichage refaits (#741)

Non testé en jeu. Demande de TTRD : « Améliore l'UI stp encore, surtout le bas avec les raccourcis » (vue plein écran de l'appareil photo, pas le viseur dans le téléphone).
- `fn_photoMode` « HUD » : barre de raccourcis faite de 8 emplacements (pastille + socle + action, idc 20-27 / 80-87 / 40-47) et 4 séparateurs (60-63), mesurés par `ctrlTextWidth` et centrés ; bandeau ajusté au contenu, espacements resserrés si la largeur dépasse 90 % de l'écran. CLIC en vert, H et ESPACE en gris. Repliée 8 s après l'ouverture (5 s après un passage selfie) en « CLIC photo | H aide, ESPACE quitter » ; nouveau mode « HELP » (touches H 0x23 et F1 0x3B) qui l'épingle ouverte ou la replie.
- Haut : un seul panneau sombre avec filet vert (envoi vers Athena) ou orange (hors réseau) et séparateur entre les deux lignes.
- Exposition en direct (idc 12-13) : ISO 100 à 6400 et vitesse 1/250 à 1/8 s d'après `getLightingAt` (ambiante + dynamique), f/1.7 ; ISO 1600 et plus en orange. Seuils choisis à l'estime, à vérifier en jeu. L'ancien bloc « ISO 100 / F1.8 1/200 » fixe est retiré de `camera_overlay.paa` (`tools/src/camera_overlay.png`).
- Échelle de zoom sur fond discret, palier en cours en gras.
- Prise de vue : tout le viseur affiché est masqué pendant la capture puis réaffiché tel quel (liste `allControls`), le HUD ne se redessine pas pendant la capture.
- `fn_lockAction` « draw3d » : cadre fin à 35 % d'opacité, coins épais (`drawLine3D` largeur 4), étiquette RobotoCondensedBold éclaircie, décalée au-dessus du cadre.
- L'avertissement « ACRE n'est pas connecté à TeamSpeak » est affiché par ACRE lui-même : il n'est pas déplacé, les éléments du viseur évitent sa zone (haut droite, sous l'exposition).
- Traductions anglaises : CLIC, MOLETTE, SOURIS, ESPACE, aide.

#### Messagerie réparée, SMS plus fiables, Live cam selon les relais (#743)

Non testé en jeu. Demandes de TTRD : clics sur les canaux sans effet, pas de défilement, pas de pastilles de non-lus ; améliorer les SMS ; améliorer les flux vidéo suivant les relais.
- Cause probable du volet de conversation vide : les initiales d'un nom d'un seul mot (« CH:general ») lisaient un deuxième mot absent, ce qui interrompait le dessin de l'en-tête et de toute la conversation. Corrigé.
- Non-lus : nouvelle fonction `fn_chatUnread` (canaux, comptes par conversation, « lu ») partagée par la liste et l'icône d'app (`fn_appBadge`). Un message est mémorisé par identifiant ou auteur|heure|texte ; l'historique présent dans les 90 premières secondes compte comme lu.
- Défilement : `fn_pageRender` relève la position des groupes défilants nommés (`COMSPEC_ATAK_ScrollGroups`) avant d'effacer la page ; la liste et le fil la retrouvent (le fil reste en bas sauf si on était remonté).
- SMS : brouillon par conversation, compteur 400 caractères, bandeau « pas de réseau », relais affiché, contacts triés par dernier SMS, accusé « HELD » quand le destinataire est hors réseau (`fn_p2pReceive`, `fn_p2pAck`), « non transmis » enregistré après 60 s et renvoi au clic.
- Live cam : nouvelle fonction `fn_feedLink` (liaison montante publiée toutes les 10 s par chaque client, liaison descendante du téléphone, caméra fixe via l'ATAK le plus proche) ; texture 512/256/128/64 px selon le débit, voile « signal instable » selon les pertes, palier réévalué toutes les 3 s, flux coupé avec sa raison.

#### Briefing : engrenage pour le lien des présentations (#745)

Non testé en jeu. Demande de TTRD : « un engrenage paramétrage pour pouvoir modifier les URL d'accès ». L'adresse du portail Athena reste fixe (1.72.0) : seuls les liens de présentation se règlent.
- Nouveau : `fn_briefingSettings` (panneau, onglet interne `SETTINGS`), `fn_briefingCfg` (actions), `fn_briefingUrl` (nettoyage et diagnostic du lien, sans réseau), icône `ui_gear.paa` (gen_assets.py).
- Réglages dans le profil du joueur : `COMSPEC_ATAK_BriefSource` (AUTO / ATHENA / GOOGLE), `COMSPEC_ATAK_BriefGoogleUrl`, `COMSPEC_ATAK_BriefAuto`.
- Chargement Google par COMSPEC Link (`LoadGoogleDeck` / `LoadGoogleSlide`) sans passer par `fn_loadGoogleBriefing` d'Overwatch, qui ouvrait l'ancien tableau par-dessus le téléphone. Un chargement local ne diffuse pas sa navigation ; DIFFUSER réutilise `broadcastGoogleBriefingState`.
- `fn_extensionCallback` garde le code de `google_deck_error` d'un chargement lancé par l'engrenage et referme le tableau 9970 ouvert par Overwatch sur `google_deck_ready` quand le téléphone est ouvert. Overwatch traite toujours les rappels ensuite.
- Source « Athena » : le deck Google partagé est ignoré par `fn_briefingSignature`. Source « Google Slides » : le lien enregistré est chargé à l'ouverture de l'app et par ACTUALISER.
- Pas de DLL à recompiler, pas de migration.

---

## App Réseau : stabilité calculée, passerelles lisibles (ATAK natif 1.79.0)

### Résumé joueur
Type : correctif
FR : App Réseau : la stabilité s'affiche enfin
- La tuile Stabilité montre un vrai pourcentage (stable, correcte, instable, très instable) calculé à partir des pertes, de la latence et des réponses d'Athena, ou « — » tant que rien n'a été mesuré.
- La carte COMSPEC Link indique Connecté, Dégradé, Connexion… ou Hors ligne.
- La section Passerelles et relais ne répète plus le maillage et ses lignes ne se collent plus.
EN : Network app: stability is finally shown
- The Stability tile shows a real percentage (stable, fair, unstable, very unstable) based on packet loss, latency and Athena replies, or "—" until something has been measured.
- The COMSPEC Link card reads Connected, Degraded, Connecting… or Offline.
- The Gateways and relays section no longer repeats the mesh line and its rows no longer run together.

### Détail technique

Non testé en jeu. Capture de TTRD : tuile Stabilité « any % » et section Passerelles et relais confuse.
- Stabilité : la valeur n'était jamais calculée (affichage « any % »). Elle vient maintenant de la perte de paquets (mesurée par COMSPEC Link et simulée par le téléphone), de la latence, de l'état de COMSPEC Link et des réponses d'Athena : stable, correcte, instable, très instable, ou « liaison coupée ». Sans aucune mesure, la tuile affiche « — » et « pas encore de mesure ».
- Chemin de la liaison : la carte COMSPEC Link affiche Connecté, Dégradé, Connexion… ou Hors ligne au lieu de LINKED.
- Passerelles et relais : l'état vide tient sur une ligne, sans répéter le maillage collé à la ligne « Maillage Wave Relay » ; celle-ci est espacée et indique « aucune passerelle » quand il n'y en a pas.
- Zone radio : « Simulation de liaison dégradée » affiche « désactivée » ou « active : pertes et délais simulés » ; le type de zone n'est plus remplacé par « ? » quand il est inconnu.
- Paquets envoyés affichés en entier (plus de notation 1e+06). Textes traduits en anglais.

## Module Zeus et Eden « Relever routes et villes » (ATAK natif 1.78.0)

### Résumé joueur
Type : nouveauté
FR : Nouveau module Zeus et Eden pour envoyer routes et villes à Athena
- Le module « Relever routes et villes » (COMSPEC Outils) envoie à Athena les routes, pistes, villes et villages de la carte, sans attendre le relevé complet des bâtiments et du relief.
- Posé dans l'éditeur, il part tout seul au début de la mission dès qu'Athena est liée ; dans Zeus, il part immédiatement.
EN : New Zeus and Eden module to send roads and towns to Athena
- The "Survey roads and towns" module (COMSPEC Tools) sends the map's roads, tracks, towns and villages to Athena, without waiting for the full survey of buildings and terrain.
- Placed in the editor, it runs on its own at mission start once Athena is linked; in Zeus, it runs right away.

### Détail technique
Non testé en jeu. Demande de TTRD : un module Zeus et Eden dédié aux routes et villes. Le relevé existait déjà (`fn_sampleGeoNetwork`, Geo.Ingest) mais seulement au bout du relevé complet du théâtre ou par le menu ACE.
- Eden : `COMSPEC_Module_GeoNetwork` (catégorie COMSPEC Outils), options « Au début de la mission » (défaut : oui) et « Renvoyer ». Exécuté par le Zeus ou le chef de mission (admin connecté, ou solo), qui attend la liaison Athena jusqu'à 3 minutes.
- Zeus : module « Relever routes et villes » par Zeus Enhanced et ACE Zeus, envoi immédiat même si la carte a déjà été transmise.
- Les réglages de communauté « Lieux » et « Routes » (modules du mod) restent respectés.

## Menu Échap et Dépannage liaison refondus (ATAK natif 1.77.0)

### Résumé joueur
Type : nouveauté
FR : Menu Échap et dépannage de la liaison refaits
- Les boutons COMSPEC du menu Échap sont maintenant en haut à droite, avec l'état de ta liaison, et ne cachent plus le titre du menu.
- « Dépannage liaison » vérifie tout de suite ta liaison Athena, ton compte, ton téléphone et la version du mod, explique ce qui ne va pas et propose la bonne correction en un clic.
- « Copier le rapport » copie le diagnostic pour le coller sur Discord ou dans un signalement.
- Le test pas à pas (pour trouver ce qui fait planter le jeu) se lance au choix en rapide ou en complet, avec une barre de progression.
EN : Escape menu and link troubleshooting redesigned
- The COMSPEC buttons in the Escape menu are now at the top right, with your link status, and no longer hide the menu title.
- "Link troubleshooting" instantly checks your Athena link, account, phone and mod version, explains what is wrong and offers the right fix in one click.
- "Copy report" copies the diagnosis so you can paste it on Discord or in a bug report.
- The step-by-step test (to find what makes the game crash) can run quick or full, with a progress bar.

### Détail technique
Non testé en jeu. Demande de TTRD : boutons du menu Échap en haut à droite, service de dépannage amélioré et UI refaite. Changements dans l'addon Overwatch connect embarqué (`comspec_overwatch_connect.pbo` repaqueté) ; pas de changement de DLL.
- Menu Échap : les deux boutons quittent le haut gauche (ils couvraient le titre du menu) pour une barre COMSPEC en haut à droite, aux couleurs du téléphone : état de la liaison (Liaison OK, Hors ligne, ATAK local, Overwatch coupé…), « Dépannage liaison » et « Service Athéna : oui / non ». Pendant un pas à pas, le bouton devient « Arrêter le pas à pas ».
- Dépannage liaison : le bouton ne coupe plus Overwatch pour 22 minutes d'office. Il ouvre une fenêtre de diagnostic immédiat : DLL COMSPEC Link, installation du mod (contrôles de #714 : PBO en double, versions mélangées, DLL périmée, mise à jour publiée), service Athéna, Overwatch, adresse Athena, liaison, compte et session, terminal ATAK porté, pertes, dernier pas à pas interrompu. Chaque problème donne sa cause probable et l'action à faire ; la correction proposée passe en vert (Reconnecter Athena, Lier mon compte, Rallumer Overwatch, Service Athéna).
- « Copier le rapport » met le diagnostic et les versions dans le presse-papiers ; « Signaler un problème » ouvre le signalement existant.
- Pas à pas (recherche de ce qui arrête le jeu) : au choix rapide (20 s par fonction) ou complet (55 s), lancé depuis la fenêtre. Nouveau bandeau compact en haut au centre avec barre de progression, temps restant et état de la liaison. L'étape mémorisée est effacée quand le pas à pas se termine ou est arrêté : elle ne reste affichée comme suspecte qu'après un vrai arrêt du jeu.
- Textes traduits en anglais (dictionnaire i18n/en.txt).

## App JTAC plus réaliste : terminal, fiche 9-LINE et carte tactique (ATAK natif 1.76.0)

### Résumé joueur
Type : nouveauté
FR : L'app JTAC ressemble à un vrai terminal de contrôleur air
- En haut, un bandeau de terminal (indicatif, code et état du laser, heure) et la frise de la mission en cases, de CHECK-IN à BDA, avec l'étape en cours en orange et le tir autorisé en rouge.
- Un bandeau en gros caractères dit ce qu'on attend (READBACK, IN…) ou la décision à prendre ; CLEARED HOT, CONTINUE DRY et ABORT sont de gros boutons.
- Le 9-LINE est une fiche dense, lignes numérotées, valeurs calculées en vert, remarques et restrictions en dessous.
- Nouvel onglet CARTE : cible, IP et axe d'entrée, cône de l'axe final d'attaque, distance de risque, amis, ligne laser et appareils en vol. On peut y poser la cible ou l'IP du doigt. En paysage, la carte reste à gauche des autres onglets, côté JTAC comme côté équipage.
EN : The JTAC app now looks like a real air controller terminal
- At the top, a terminal bar (callsign, laser code and state, time) and the mission tape from CHECK-IN to BDA, with the current step in orange and cleared hot in red.
- A large banner says what you are waiting for (READBACK, IN…) or the decision to make; CLEARED HOT, CONTINUE DRY and ABORT are big buttons.
- The 9-LINE is a dense card with numbered lines, computed values in green, remarks and restrictions below.
- New MAP tab: target, IP and run-in, final attack heading cone, risk distance, friendlies, laser line and airborne aircraft. You can set the target or IP with a tap. In landscape the map stays on the left of the other tabs, for the JTAC and for the aircrew.

### Détail technique

Non testé en jeu. Demande de TTRD : « JTAC l'app a une UI pas encore assez réaliste ». Mécanique de mission inchangée (fn_jtacAction), présentation refondue.
- fn_pageJtac : bandeau terminal (`strip`), frise d'étapes (`steps`), bandeau d'état (`banner`), gros boutons (`big`), 9-LINE et données cible en lignes « mono » (police à chasse fixe), journal radio horodaté avec appels critiques en rouge, onglets SITU / 9-LINE / CARTE / CTRL / LOG. Paysage hors mini : carte tactique à 44 % à gauche.
- fn_jtacDraw (nouveau) : dessin de la carte tactique (cône FAH, flèche IP → cible, cercle RED, ami le plus proche, LTL, appareils avec vecteur de cap). Vue gardée entre deux rendus. fn_jtacFah (nouveau) : lecture de la fenêtre FAH, partagée avec fn_jtacGeometry.
- fn_jtacAction : pickIn / mapPick / mapSet (pose sur la carte de l'app), center ; le brief transmet aussi la position de l'IP et la distance de risque (fin du tableau, ignorés par un ancien client).
- fn_formRender : nouvelles lignes `strip`, `steps`, `banner`, `big`, fond facultatif pour `text`, style « mono » pour `line` ; les autres apps ne changent pas.
- i18n : textes ajoutés à en.txt.
- À tester en jeu : onglets JTAC en portrait et paysage (mini et plein écran, échelle réglable), pose cible/IP sur la carte, cône FAH et cercle RED, vue équipage avec brief reçu, rafraîchissement CTRL toutes les 3 s (la carte garde sa vue), langue anglaise.

## Ligne de vue : de ma position vers le point (ATAK natif 1.75.0)

### Résumé joueur
Type : nouveauté
FR : Ligne de vue : de ta position vers un point dont tu entres les coordonnées
- Dans l'app Relief, onglet LIGNE DE VUE, tu entres les coordonnées du point (carroyage de 4 à 10 chiffres) puis TRACER : la ligne part de ta position.
- Le bouton SOUS MON REGARD disparaît ; SUR LA CARTE reste pour toucher le point.
- L'en-tête rappelle ta position, et un carroyage court vise le centre de sa case.
EN : Line of sight: from your position to a point whose coordinates you enter
- In the Relief app, LINE OF SIGHT tab, enter the point's coordinates (4 to 10-digit grid) then DRAW: the line starts from your position.
- The UNDER MY GAZE button is gone; ON THE MAP still lets you tap the point.
- The header shows your position, and a short grid aims at the centre of its square.

### Détail technique

Non testé en jeu. Demande de TTRD : remplacer « SOUS MON REGARD » par « de ma position vers le point », en entrant les coordonnées.
- App Relief, onglet LIGNE DE VUE : le bouton SOUS MON REGARD est retiré. L'en-tête devient « DE MA POSITION VERS LE POINT : » et rappelle ma position (carroyage 8 chiffres). On entre les coordonnées du point (carroyage de 4, 6, 8 ou 10 chiffres, espaces acceptés) puis TRACER, ou on touche le point avec SUR LA CARTE.
- Un carroyage court vise le centre de sa case (avant : le coin sud-ouest). La ligne part toujours de mes yeux, point à 1,7 m du sol. Tracé, profil, VOIR SUR LA CARTE et EFFACER inchangés.

## Langue du mod en tête des options CBA (ATAK natif 1.74.0)

Non testé en jeu. Demande de TTRD : la sélection de langue dans Options des addons > COMSPEC.
- Le réglage **Langue / Language** (Auto, Français, English) est maintenant tout en haut de la liste COMSPEC, onglet Client, au-dessus des sections « ATAK · … ». Il était rangé dans la section « ATAK · Langue / Language », au milieu de la liste, où on ne le trouvait pas.
- Défaut : Auto (suit la langue du jeu : français si Arma est en français, anglais sinon). Un joueur qui avait déjà choisi Français ou English garde son choix.
- Un seul réglage pour tout le mod : téléphone, menus CBA COMSPEC (ATAK natif, Overwatch, SSE) et écrans du SSE. Le choix dans le téléphone (Réglages > Langue / Language) est le même réglage.
- Changer de langue en cours de partie : le téléphone se redessine et les menus CBA COMSPEC sont retraduits à leur prochaine ouverture, sans relancer la mission (avant, il fallait relancer la mission).

## Texte du bouton PANIQUE (ATAK natif 1.73.0)

### Résumé joueur
Type : nouveauté
FR : Bouton PANIQUE : une consigne plus claire
- Sous le bouton PANIQUE, le téléphone explique qu'il alerte tous les opérateurs et qu'il faut appuyer deux fois d'affilée pour envoyer l'alerte.
EN : PANIC button: clearer instructions
- Under the PANIC button, the phone explains that it alerts all operators and that you must press twice in a row to send the alert.

### Détail technique

Non testé en jeu. Demande de TTRD : nouveau texte sous PANIQUE, écran Détresse et contact.
- Le bloc PANIQUE affiche « Alerte l'ensemble des opérateurs de votre détresse. Il est nécessaire d'appuyer deux fois d'affilée pour valider l'envoi. » (anglais : « Alerts all operators of your distress. You must press twice in a row to confirm sending. »).

## Adresse du portail Athena fixe (ATAK natif 1.72.0)

### Résumé joueur
Type : correctif
FR : L'adresse du portail Athena ne se règle plus
- Le téléphone et Overwatch se connectent toujours au portail officiel d'Athena : il n'y a plus d'adresse à saisir ni à vérifier.
- Si tu avais enregistré une autre adresse, elle est ignorée et effacée automatiquement.
- L'erreur « adresse invalide » à la liaison du compte ne peut plus se produire.
EN : The Athena portal address is no longer a setting
- The phone and Overwatch always connect to the official Athena portal: there is no address to type or check any more.
- If you had saved another address, it is ignored and cleared automatically.
- The "invalid address" error when linking your account can no longer happen.

### Détail technique

Non testé en jeu. Demande de TTRD : personne n'héberge son propre Athena, l'adresse du portail ne doit plus être modifiable. Pas de DLL à recompiler, pas de migration.
- Le téléphone et Overwatch connect utilisent toujours https://athena.ttrd.fr/public : plus de réglage CBA « Adresse du portail », plus de champ d'adresse dans la fenêtre Connexion Athena.
- Une ancienne adresse enregistrée chez un joueur (profil Arma, réglage CBA, tablette web) est ignorée, et celle du profil est effacée au lancement.
- Le second essai sur « invalid_url » (1.64.0) disparaît : l'adresse ne peut plus être fausse.
- Site : l'adresse du portail n'est plus présentée comme un réglage (première liaison, tutoriel ATAK, guide chef de mission, Configuration ATAK, guide Overwatch).
- À vérifier en jeu : onglet COMPTE du téléphone (code et Steam), fenêtre Connexion Athena d'Overwatch (code, Steam), et que la liaison reste active après un redémarrage d'Arma.

## Performance : arrivée en mission, arsenal et DLL (ATAK natif 1.71.0, COMSPEC Link 2.0.80)

### Résumé joueur
Type : performance
FR : Moins de lag en rejoignant un serveur et dans l'arsenal
- L'arsenal ne fige plus le jeu : ta fiche opérateur n'est envoyée à Athena qu'à la fermeture de l'arsenal.
- Rejoindre une mission est plus rapide : le mod prépare ses listes une seule fois, au lancement d'Arma.
- La connexion à Athena se fait en arrière-plan, sans bloquer l'image.
- Les menus ACE d'Overwatch et du SSE ne disparaissent plus quand on enchaîne deux missions.
EN : Less lag when joining a server and in the arsenal
- The arsenal no longer freezes the game: your operator sheet is only sent to Athena when you close the arsenal.
- Joining a mission is faster: the mod prepares its lists once, when Arma starts.
- Connecting to Athena happens in the background without freezing the screen.
- Overwatch and SSE ACE menus no longer go missing when you play two missions in a row.

### Détail technique

Non testé en jeu. Demande de TTRD : gros lag en rejoignant un serveur et en ouvrant l'arsenal. Il faut recompiler les DLL (build_mod.bat) ; pas de migration.
- Arsenal : la fiche opérateur n'est plus reconstruite et envoyée à Athena à chaque clic. Rien tant que l'arsenal (ACE ou BIS) est ouvert ; un seul envoi à la fermeture, et au plus un toutes les 3 s hors arsenal. L'envoi part dans un spawn (requête HTTP hors du thread du jeu) au lieu de figer l'image jusqu'à 8 s.
- Fiche opérateur : versions des mods et de la DLL lues une fois par partie (la DLL était interrogée à chaque construction), visage d'un mod cherché une fois dans CfgFaces.
- Tenues de l'arsenal Athena : liste paginée lue par la DLL sans bloquer l'arsenal.
- Arrivée en mission : la recherche des téléphones dans tout CfgWeapons et la liste du bouton COMSPEC de l'arsenal sont faites une fois au lancement du jeu (preStart), plus à chaque mission. Le dictionnaire anglais (10 000 paires) est gardé pour toute la session de jeu. fn_hasDevice cherche dans une table au lieu de parcourir la liste.
- Connexion Athena (COMSPEC Link et DLL native) : Init, reprise de session, Connect, IP et inscription bêta passent par extAwait, sans figer le jeu pendant les requêtes.
- Aéronefs occupés : un seul client remonte chaque aéronef (le joueur relié à bord, sinon le joueur relié de plus petit UID) au lieu de tous les clients.
- Menus ACE d'Overwatch et du SSE : les gardes « déjà installé » sont par mission (elles restaient dans uiNamespace : à la mission suivante sans relancer Arma, les menus manquaient).
- COMSPEC Link 2.0.80 : le minuteur de vidage n'est plus relancé à chaque mise en file (il pouvait ne jamais partir), un seul vidage à la fois, file sur disque écrite au plus toutes les 3 s, journaux d'appareil renvoyés avec une pause croissante hors ligne, version lue une fois, et préchauffage au chargement (file sur disque relue et connexion TLS vers Athena ouverte en tâche de fond).
- Journal : lignes `[PERF]` (durées du preInit, du postInit client et serveur, arrivée en jeu, parcours de config, dictionnaire, fiche opérateur, reprise de session) et `Image figée : N ms` pour toute image de plus de 400 ms, avec arsenal, téléphone et carte ouverts ou non.

## Correctif « Include file ...\ui\controls.hpp not found » au lancement (ATAK natif 1.70.0)

### Résumé joueur
Type : correctif
FR : Erreur au lancement d'Arma corrigée
- Le message d'erreur affiché au démarrage d'Arma, qui empêchait le mod de se charger, a disparu.
- Le téléphone ne signale plus à tort que COMSPEC Link n'est pas à jour.
EN : Arma startup error fixed
- The error message shown when Arma starts, which stopped the mod from loading, is gone.
- The phone no longer wrongly reports that COMSPEC Link is out of date.

### Détail technique

- `config.cpp` incluait `ui/controls.hpp` et `ui/displays.hpp` avec une barre oblique. HEMTT (main.pbo du dépôt) l'accepte, mais `build_mod.bat` empaquette avec Addon Builder en `-packonly` : la config reste en texte et c'est Arma qui la lit au démarrage, sans trouver le fichier. Les deux includes utilisent maintenant une barre oblique inverse (`ui\controls.hpp`), lue par les deux chaînes de build.
- `LINK_VERSION_STR` passe à 2.0.79 pour suivre la DLL de #710 (sinon le téléphone signalait la DLL comme périmée).

## Serveurs de jeu : l'adresse du serveur est retrouvée (COMSPEC Link 2.0.79, site)

### Résumé joueur
Type : nouveauté
FR : Serveurs de jeu : l'adresse de chaque serveur est retrouvée
- La page Serveurs de jeu d'Athena affiche l'adresse et le port d'un serveur même quand il ne les annonce pas lui-même.
- Les joueurs passés sur un serveur ajouté plus tard sont bien rattachés à ce serveur, et plus à « Ailleurs ».
- Deux serveurs hébergés sur la même machine ne sont plus confondus.
EN : Game servers: each server's address is found
- Athena's Game servers page shows a server's address and port even when the server does not announce them itself.
- Players who joined a server that was added later are now linked to that server instead of "Elsewhere".
- Two servers hosted on the same machine are no longer mixed up.

### Détail technique

Non testé en jeu. DLL COMSPEC Link à recompiler (build_mod.bat) ; pas de main.pbo à refaire. Côté site, la colonne `server_port` et la table `game_server_address_lookup` se créent toutes seules (ou `run-migrations.php`).
- L'adresse d'un serveur ne venait que du serveur lui-même (COMSPEC Link chargé sur le serveur dédié). Un serveur sans la DLL restait « inconnue » sur la page Serveurs de jeu.
- Site : quand l'adresse manque, Athena la retrouve par le nom exact du serveur dans la liste publique Steam (clé `STEAM_WEB_API_KEY`), sinon sur BattleMetrics, avec le port de jeu. Résultat gardé 24 h. Les serveurs déjà déclarés sans adresse sont complétés à l'ouverture de la page, et « C'est notre serveur » enregistre l'adresse et le port.
- Site : les joueurs déjà vus sont rapprochés des serveurs tels qu'ils sont déclarés maintenant (un serveur ajouté après leur passage ne les laisse plus en « Ailleurs »). Le port départage deux serveurs sur la même machine.
- COMSPEC Link 2.0.79 : le serveur dédié donne aussi son port de jeu (`-port`, 2302 par défaut) ; le client ajoute l'adresse de lancement (`-connect`/`-port` du lanceur Arma ou `+connect` de Steam) quand le serveur n'en diffuse pas.

## Mod ou DLL périmés signalés dans le téléphone, dernière version publiée sur Athena (ATAK natif 1.69.0, COMSPEC Link 2.0.78)

### Résumé joueur
Type : nouveauté
FR : Le téléphone te prévient quand ton mod n'est pas à jour
- Au lancement, le téléphone vérifie ton installation et te prévient si un fichier du mod ou COMSPEC Link est ancien ou en double.
- Une notification « Mise à jour disponible » apparaît quand une nouvelle version sort.
- La version du mod affichée dans les Réglages et les Crédits est enfin la bonne.
- La page ATAK natif du site affiche la dernière version.
EN : The phone warns you when your mod is out of date
- At startup, the phone checks your install and warns you if a mod file or COMSPEC Link is old or duplicated.
- An "Update available" notification appears when a new version is out.
- The mod version shown in Settings and Credits is finally correct.
- The site's native ATAK page shows the latest version.

### Détail technique

Non testé en jeu. Il faut recompiler la DLL (build_mod.bat) ; pas de migration. Cause d'origine : un ancien `Addons\comspec_atak_native_main.pbo` était chargé à la place du main.pbo à jour, sans aucun message (plus de journal ni de connexion).
- Au démarrage, le téléphone contrôle l'installation et prévient dans ses notifications (et dans le chat pour les erreurs), une fois par lancement : deux PBO du même addon chargés, code et config du main.pbo de versions différentes, DLL COMSPEC Link absente ou d'une autre version que celle attendue par le main.pbo.
- Athena expose les dernières versions (`GET /api/atak/version`, public, lues directement dans `script_component.hpp` et `COMSPECExtension.csproj`). Le mod les compare aux siennes au démarrage puis toutes les 2 h (service Athéna activé) et notifie « Mise à jour disponible ». La page /atak-natif affiche la dernière version.
- App Debug, onglet DLL : section « Installation » avec la DLL attendue, les versions du site et les anomalies.
- La version affichée du mod (Réglages, Crédits, Debug) vient maintenant du main.pbo (elle restait figée à 1.11.0).
- COMSPEC Link 2.0.78 : commande `LatestVersions`. La DLL annonçait encore 2.0.76 (csproj non monté en 1.64.0) ; csproj, constante et main.pbo sont alignés, un test PHP le vérifie.
- `build_mod.bat` supprime une sortie HEMTT `comspec_atak_native_main.pbo` / `_towers.pbo` copiée par erreur à côté de main.pbo.

## Carte : effacer le champ de vision et les zones interdites au drone (ATAK natif 1.68.0)

### Résumé joueur
Type : nouveauté
FR : Effacer le champ de vision et les zones interdites au drone depuis la carte
- Un appui long sur la carte propose « Effacer le champ de vision » et « Effacer la ligne de vue » dès qu'ils sont affichés.
- « Retirer toutes les zones » enlève d'un coup toutes les zones interdites au drone de ton camp.
- Poser un nouveau champ de vision remplace le précédent.
EN : Clear the viewshed and no-drone zones from the map
- A long press on the map offers "Clear viewshed" and "Clear line of sight" as soon as they are shown.
- "Remove all zones" clears every no-drone zone of your side in one go.
- Placing a new viewshed replaces the previous one.

### Détail technique

Non testé en jeu. Pas de DLL à recompiler. Demande de TTRD : un champ de vision posé depuis la carte ne pouvait plus être retiré.
- Menu de la carte (appui long / clic droit) : « Effacer le champ de vision » apparaît dès qu'un champ de vision est affiché (le bouton EFFACER n'existait que dans l'app Relief). Même chose pour la ligne de vue : « Effacer la ligne de vue ».
- Zones interdites au drone : « Retirer la zone interdite » (clic dans la zone) existait déjà ; ajout de « Retirer toutes les zones » dans le menu de la carte et de RETIRER TOUTES LES ZONES dans l'app Marquer Tracking (toutes les zones du camp).
- Un seul champ de vision existe à la fois par joueur : en poser un nouveau remplace le précédent.

## Intro au démarrage désactivée (ATAK natif 1.67.0)

### Résumé joueur
Type : correctif
FR : L'intro au démarrage d'Arma est désactivée
- L'animation et le son COMSPEC ne se lancent plus au démarrage d'Arma, car ils causaient des bugs.
- Tu peux la réactiver dans les réglages d'addons (COMSPEC > ATAK · Intro).
- L'écran de chargement des missions ne change pas.
EN : The Arma startup intro is turned off
- The COMSPEC animation and sound no longer play when Arma starts, because they caused bugs.
- You can turn it back on in the addon options (COMSPEC > ATAK · Intro).
- The mission loading screen does not change.

### Détail technique

Non testé en jeu. TTRD : l'intro du mod est buggée. Pas de DLL à recompiler, pas de migration.
- L'intro sur l'écran de démarrage d'Arma (marque COMSPEC, trait vert, carillon) ne se lance plus : ni affichage ni son.
- Le code est gardé. Nouveau réglage CBA « COMSPEC > ATAK · Intro > Intro au démarrage », décoché par défaut, pour la réactiver (pris en compte au lancement suivant).
- La marque COMSPEC sur les écrans de chargement de mission reste inchangée.
- À vérifier en jeu : lancer Arma, rien de COMSPEC sur l'écran de démarrage, menu principal normal.

## Taille du téléphone en plein écran réglable (ATAK natif 1.66.0)

### Résumé joueur
Type : nouveauté
FR : Choisis la taille du téléphone en plein écran
- Nouveau réglage de 60 à 100 %, dans l'app Réglages du téléphone ou dans les réglages d'addons.
- Le téléphone reste centré et tout s'adapte : écran, boutons et carte.
- La taille par défaut ne change pas.
EN : Choose the size of the full-screen phone
- New setting from 60 to 100%, in the phone's Settings app or in the addon options.
- The phone stays centred and everything scales with it: screen, buttons and map.
- The default size does not change.

### Détail technique

Non testé en jeu. Demande de TTRD : le téléphone ouvert en plein écran est trop gros pour certains.
- Nouveau réglage par joueur « Taille du téléphone en plein écran », de 60 à 100 % (100 % par défaut = taille actuelle), dans le menu CBA (COMSPEC > ATAK · Affichage) et dans l'app Réglages > Emplacement (60, 70, 80, 90, 100 %). Les deux sont le même réglage.
- Le téléphone reste centré ; l'écran, le texte, les boutons, les touches de la coque et la carte sont recalculés depuis cette taille. Le mode mini et sa taille ne changent pas.
- À vérifier en jeu : prendre le téléphone en main, passer en plein écran, essayer 60 % et 80 %, cliquer sur les apps, le dock, la carte et les touches de la coque ; changer la valeur téléphone ouvert (elle s'applique tout de suite).
---

## Pylônes relais 3D destructibles (ATAK natif 1.65.0)

### Résumé joueur
Type : nouveauté
FR : Des pylônes relais en 3D, destructibles
- Trois vrais pylônes radio de 30, 40 et 50 m à poser dans Eden et Zeus, rubrique Relais ATAK.
- Chaque pylône sert de relais au réseau des téléphones ATAK.
- Détruit à l'explosif ou au véhicule, il s'effondre et les téléphones qui l'utilisaient perdent le réseau.
EN : Destructible 3D relay towers
- Three real radio towers (30, 40 and 50 m) to place in Eden and Zeus, under ATAK relays.
- Each tower acts as a relay for the ATAK phone network.
- Destroyed by explosives or vehicles, it collapses and the phones using it lose the network.

### Détail technique

Non testé en jeu. Pas de DLL à recompiler. Demande de TTRD : de vrais pylônes radio en 3D, hauts, variés et destructibles.
- Trois modèles dans Eden et Zeus (Structures > Relais ATAK) : pylône treillis 30 m anthracite à panneaux et RRU, tour triangulaire galvanisée 40 m à tête à bras, faisceaux hertziens et fouets, pylône élancé 50 m à paliers, échelle et feu de balisage rouge. Massifs béton, armoire technique et passerelle de câbles au pied. LOD visuelles 1 / 4 / 12 / 30, ombre, géométrie et géométrie de tir.
- Rendu Arma complet : shader Super avec relief (_nohq) et reflets (_smdi) pour l'acier galvanisé ou peint, les radômes, les coffrets, le béton et le caillebotis ; deux LOD d'ombre (treillis presque complet de près, silhouette de loin).
- Chaque pylône posé est un relais du réseau ATAK (tour : 6, 9 ou 12 km, antennes à 28, 38 ou 48 m, sur secteur). Attributs Eden : nom, camp, raccordé ou répéteur, allumé, indestructible. Un module « ATAK · Relais » synchronisé passe avant.
- Destructible : abattu (charges, obus, véhicules), le pylône est remplacé par sa ruine (plié au-dessus d'un tronçon debout, couché au sol, panneaux et paraboles arrachés). Le relais passe « détruit » et les téléphones qui l'utilisaient reçoivent « Relais perdu ».
- Module « ATAK · Relais », type Tour fixe sans objet synchronisé : crée la tour 3D de 40 m (hauteur d'antenne réelle du pylône si la hauteur est laissée au type).
- Nouveau PBO `@COMSPEC_ATAK_Native/addons/towers.pbo` (modèles MLOD trop lourds pour main.pbo) : le copier avec main.pbo. `build_mod.bat` le construit.
- Outils : `tools/gen_tower_textures.py` (textures raccordables) et `tools/model_relay_towers.py` (modèles, ruines, aperçus).

## Connexion Athena : « invalid_url » mieux géré (ATAK natif 1.64.0, COMSPEC Link 2.0.77)

### Résumé joueur
Type : correctif
FR : Liaison du compte Athena plus fiable
- Si l'adresse du portail est vide ou mal écrite, le téléphone utilise automatiquement le portail officiel.
- En cas d'échec, le téléphone réessaie tout seul sur le portail officiel.
- Si le problème revient, le journal COMSPEC indique la cause exacte à transmettre au staff.
EN : More reliable Athena account linking
- If the portal address is empty or mistyped, the phone automatically uses the official portal.
- If linking fails, the phone retries on the official portal by itself.
- If the problem comes back, the COMSPEC log shows the exact cause to send to the staff.

### Détail technique

Non testé en jeu. Capture de TTRD : l'onglet COMPTE affichait « Athena n'a pas donné de code (invalid_url) ». Il faut recompiler la DLL (build_mod.bat) ; pas de migration.
- L'adresse du portail est nettoyée avant la demande de code ; si elle est vide ou sans http, le portail par défaut (athena.ttrd.fr/public) est utilisé.
- Si la DLL refuse quand même l'adresse, le jeu refait un essai sur le portail par défaut.
- COMSPEC Link 2.0.77 : en cas d'invalid_url, la cause exacte (type d'exception et message) est ajoutée à la réponse et écrite dans le journal ATHENA (« PairStart invalid_url sur <adresse> : ... »), pour lire la vraie cause si le problème revient.
- À vérifier en jeu : onglet COMPTE, un code s'affiche. Sinon, envoyer la ligne « PairStart ... » du journal COMSPEC.

## App JTAC : formulaire 9-LINE lisible (ATAK natif 1.63.0)

### Résumé joueur
Type : nouveauté
FR : App JTAC : un 9-LINE enfin lisible
- Les neuf lignes s'affichent en rangées claires : numéro, intitulé, valeur.
- Une valeur manquante te dit quoi faire, par exemple « pointez l'IP » ou « verrouillez la cible ».
- Les champs à remplir sont à côté de chaque ligne, avec une aide en gris.
- L'équipage reçoit le 9-LINE avec la même présentation.
EN : JTAC app: a readable 9-LINE at last
- The nine lines are shown as clear rows: number, label, value.
- A missing value tells you what to do, for example "mark the IP" or "lock the target".
- Fields to fill in sit next to each line, with a grey hint.
- The aircrew receives the 9-LINE with the same layout.

### Détail technique

Non testé en jeu. Demande de TTRD : « Ça faut améliorer le JTAC » (capture du 9-LINE). Pas de DLL à recompiler, pas de migration.
- Les neuf lignes et les remarques sont des rangées de même hauteur : numéro en pastille, libellé lisible à gauche, valeur à droite. Les valeurs calculées sont en couleur d'accent, une valeur manquante dit quoi faire (« pointez l'IP », « verrouillez la cible »), les amis passent en rouge en DANGER CLOSE.
- Les champs à saisir (IP, description, dégagement, axe final, menaces, initiales) sont dans la colonne valeur, avec l'aide en gris sous le libellé, au lieu des grandes cases vides sous des légendes en petite police.
- « IP → cible » s'affichait barré (la police du jeu n'a pas la flèche) : « IP vers cible ». Le 9-LINE reçu côté équipage a la même mise en page.
- À vérifier en jeu : onglet BRIEF en portrait et en paysage, saisie dans les champs (texte gardé après un redessin), lisibilité de nuit, vue ÉQUIPAGE.

## Intro au démarrage : plus de conflit avec le menu, nouveau son (ATAK natif 1.62.0)

### Résumé joueur
Type : correctif
FR : Intro au démarrage : plus de conflit avec le menu, nouveau son
- L'intro ne cache plus l'écran de démarrage ni le menu principal d'Arma.
- La marque COMSPEC s'affiche discrètement en bas à droite.
- Nouveau son, court et plus doux, coupé par une touche ou un clic et désactivable dans les réglages d'addons.
EN : Startup intro: no more clash with the menu, new sound
- The intro no longer hides Arma's startup screen or main menu.
- The COMSPEC brand shows discreetly in the bottom right corner.
- New sound, short and softer, skipped with any key or click and can be turned off in the addon options.

### Détail technique

- L'intro ne pose plus de fond noir plein écran : l'écran de démarrage d'Arma ou d'un pack d'unité reste visible. La marque COMSPEC apparaît en bas à droite avec un trait de balayage, en animations moteur (plus rien ne reste figé à l'écran pendant le chargement).
- Le menu principal n'est plus jamais recouvert (l'ancienne version y rejouait l'intro sur fond noir, par-dessus le menu et la fenêtre « service Athéna »).
- Nouveau son : carillon synthétique court (1,6 s), volume bas, coupé par une touche ou un clic. Réglage CBA **COMSPEC > ATAK · Intro > Son d'intro au démarrage** pour le désactiver (pris en compte au lancement suivant).
- Marque des écrans de chargement : textes affichés même quand les fonctions ne sont pas encore compilées.

## Musique : l'onglet ATHENA ne reste plus bloqué sur « Chargement » (ATAK natif 1.61.0)

### Résumé joueur
Type : correctif
FR : Musique : l'onglet ATHENA ne reste plus bloqué sur « Chargement »
- Après une fin de mission, l'onglet ATHENA de l'app Musique ne reste plus bloqué sur « Chargement depuis Athena… ».
- Si Athena ne répond pas, un message le dit et le bouton ACTUALISER redevient actif.
- La liste déjà reçue reste affichée pendant un nouveau chargement.
EN : Music: the ATHENA tab no longer gets stuck on "Loading"
- After a mission ends, the ATHENA tab of the Music app no longer stays stuck on "Loading from Athena…".
- If Athena does not answer, a message says so and the REFRESH button works again.
- The list you already have stays on screen while it reloads.

### Détail technique

Non testé en jeu. Pas de DLL à recompiler. Signalé par TTRD : onglet ATHENA, playlist globale, « Chargement depuis Athena… » sans fin et boutons grisés.
- Cause : l'état « chargement en cours » est gardé dans uiNamespace, qui survit à la fin de mission, alors que le script qui attendait la réponse d'Athena est arrêté. Revenu en jeu, l'onglet attendait une réponse qui ne viendrait jamais.
- Un chargement de plus de 35 s est maintenant considéré comme perdu : un garde-fou affiche « Athena n'a pas répondu à temps », ACTUALISER redevient actif et la vue se recharge seule 20 s après une erreur. La liste déjà reçue reste affichée pendant un nouveau chargement ; une réponse arrivée trop tard est ignorée.
- Messages plus justes : une erreur 503 d'Athena (surcharge, maintenance) n'est plus présentée comme « migrations à lancer ».

---

## Gestes : doigt d'honneur corrigé, désactivé par défaut (ATAK natif 1.60.0)

### Résumé joueur
Type : correctif
FR : Gestes : doigt d'honneur corrigé et désactivé par défaut
- La main du doigt d'honneur et du selfie est enfin dans le bon sens.
- Les gestes doigt d'honneur sont désactivés par défaut : à activer dans les réglages d'addons (COMSPEC · ATAK · Gestes).
- Les actions ACE « Doigt d'honneur » sont retirées.
EN : Gestures: middle finger fixed and off by default
- The hand for the middle finger and the selfie now faces the right way.
- Middle finger gestures are off by default: turn them on in the addon options (COMSPEC · ATAK · Gestures).
- The "Middle finger" ACE actions are removed.

### Modifié
- **Main du doigt d'honneur** : l'orientation de la paume était inversée (les doigts se repliaient vers le dos de la main). Toutes les poses du doigt d'honneur, du doigt d'honneur libre et du selfie sont régénérées par `tools/gen_gestures.py`, et la position du téléphone dans la main au selfie est recalculée.
- **Réglage d'addon « Gestes doigt d'honneur »** (COMSPEC · ATAK · Gestes), **désactivé par défaut** : tant qu'il n'est pas coché, les touches « Doigt d'honneur » et « Doigt d'honneur libre (maintenir) » ne font rien.

### Retiré
- Les actions ACE perso « Doigt d'honneur » et « Doigt d'honneur libre (6 s) ».

---

## Musique : les morceaux mis en ligne sur le site apparaissent sans redémarrer (ATAK natif 1.59.0)

### Résumé joueur
Type : nouveauté
FR : Musique : les morceaux envoyés sur le site arrivent en jeu sans redémarrer
- Dans l'app Musique, l'onglet ATHENA se met à jour tout seul : un morceau ajouté sur le site apparaît en moins d'une minute.
- Sur le site, on peut envoyer plusieurs morceaux d'un coup.
EN : Music: tracks uploaded on the site show up in game without a restart
- In the Music app, the ATHENA tab refreshes by itself: a track added on the site shows up within a minute.
- On the site, you can upload several tracks at once.

### Détail technique

Non testé en jeu. Pas de DLL à recompiler. Demande de TTRD : un morceau envoyé sur le site doit être visible et lisible en jeu sans redémarrer.
- App Musique, onglet ATHENA : chaque vue (playlist globale, communauté, organisation, mes envois, playlists) se recharge en arrière-plan quand on l'affiche et qu'elle date de plus de 60 s. Avant, une vue chargée restait figée jusqu'au bouton Actualiser ou au redémarrage du jeu.
- Site : envoi d'un ou plusieurs fichiers depuis `/admin/musique` (avec ajout direct à la playlist globale) et `/back-office/ressources/musique`.

## Traduction 2026-10-13 — Noms d'objets et modules Eden/Zeus du SSE en anglais (ATAK natif 1.58.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : tout traduire sauf les journaux.
- Mod SSE : 570 textes de config (noms d'objets, modules et attributs Eden, modules Zeus SEEK, catégories) passent par `addons/main/stringtable.xml` (clés `$STR_COMSPEC_SSE_CFG_*`, anglais et français). Ces textes suivent la langue du jeu, comme tout texte de config Arma.
- `tools/i18n.py --sse stringtable` ; dictionnaire partagé enrichi (environ 370 traductions). PBO du SSE concernés repaquetés.
- Les journaux techniques restent en français.

## Relais du réseau ATAK : module Eden/Zeus, tutoriel, passerelles de véhicule coupées (ATAK natif 1.57.0)

Non testé en jeu. Pas de DLL à recompiler. Demande de TTRD : améliorer les relais, un tuto dans Eden, couper par défaut les relais véhicule / mât de commandement.
- Passerelles de véhicule et PC mobile à mât : nouveau réglage serveur « Passerelles de véhicule et PC mobile », **désactivé par défaut** (plus d'action ACE, celles ouvertes se referment). Un véhicule relais voulu par la mission se fait avec le module synchronisé au véhicule.
- Nouveau module Eden et Zeus **ATAK · Relais** (catégorie COMSPEC — ATAK · Réseau) : le cercle du module dans Eden est la portée ; types radio portable, mât tactique, tour fixe, terminal satellite ; camp, débit, hauteur d'antenne, places, alimentation (secteur par défaut : un relais posé sur la carte n'a pas de batterie ; batterie seulement si la mission la choisit), liaison raccordée ou répéteur, allumé, indestructible, relais obligatoires. Objet synchronisé ou lâché dessus en Zeus = équipé, sinon l'objet du type est créé.
- Tutoriel : description du module dans Eden, infobulles des attributs et page « Relais du réseau ATAK » du manuel de terrain (Aide > Manuel de terrain).
- Relais plus réalistes : hauteur d'antenne pour le relief, répéteurs qui doivent voir un relais raccordé (sinon « sans liaison »), débit partagé au-delà des places, batterie qui se vide, brouilleurs, relais réservés à un camp. Le téléphone prend le meilleur relais reçu (plus le seul plus proche).
- Perte du relais utilisé (détruit, coupé, batterie vide, brouillé, sans liaison, hors de portée) : toast sur le téléphone « Relais perdu : X (raison), liaison dégradée » ou « bascule sur Y » (notification si le téléphone est fermé).
- ACE sur le relais : état, couper, allumer, changer la batterie. Carte et app Wave Relay : état de chaque relais (éteint, batterie vide, brouillé, sans liaison, saturé), répéteurs reliés en pointillé à leur relais.

À vérifier en jeu :
- Eden : module ATAK · Relais visible, cercle étirable, description et infobulles lisibles ; manuel de terrain.
- Mission sans module : ACE « Passerelle ATAK » absent sur les véhicules ; avec le réglage coché, il revient.
- Un raccordé et deux répéteurs en chaîne : le dernier s'éteint quand on coupe celui du milieu (ACE), la carte le dit.
- Relais posé sans toucher à l'alimentation : sur secteur, pas de batterie ni d'action « Changer la batterie ».
- Batterie 1 h choisie : la charge baisse, à vide le relais tombe ; « Changer la batterie » le relance.
- Couper (ACE) le relais qu'on utilise : toast sur le téléphone.
- Plus de joueurs que de places : débit partagé dans l'app Réseau et l'app Wave Relay.

## Réalisme 2026-10-13 — Balise IR du téléphone supprimée (ATAK natif 1.56.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : la balise IR intégrée à la coque n'était pas réaliste, à enlever complètement.
- Plus de balise IR sur le téléphone : tuile du volet rapide, interrupteur des Réglages, actions ACE « Allumer / Éteindre la balise IR », emplacement de l'établi (Accessoires) et consommation sur la batterie retirés. Pour marquer un joueur aux JVN : la balise IR d'ACE ou les grenades IR du jeu, comme avant le téléphone.
- Dos du téléphone (Voir l'arrière, établi, gilet, tablette JPC) redessiné sans le boîtier IR.
- Établi : cinq emplacements (3 + 2) au lieu de six.

## Traduction 2026-10-13 — Mod SSE et menus CBA d'Overwatch connect en anglais (ATAK natif 1.55.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : tout traduire.
- Mod SSE : textes affichés, textes fixes des écrans (terminal, résultats, graphe, SEEK…), actions ACE, menu ZEN et modules Zeus ZEN dans la langue du téléphone de l'ATAK natif (environ 1 650 nouvelles traductions). Sans l'ATAK natif, le SSE reste en français.
- Menus CBA d'Overwatch connect et du SSE : dans la langue du réglage « Langue du téléphone » (ou, avant son chargement, celle enregistrée dans le profil, sinon la langue du jeu). Nouveau `fn_trMenu` dans l'ATAK natif.
- `tools/i18n.py --sse wrap|extract` pour le SSE ; PBO du SSE et de connect repaquetés (`tools/pack_pbo.py`).
- Reste en français : noms d'objets et modules Eden du SSE (config), journaux techniques.

## Traduction 2026-10-13 — Fenêtres Overwatch connect et actions ACE/ZEN en anglais (ATAK natif 1.54.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : continuer de traduire le mod.
- Addon Overwatch connect embarqué (connexion Athena, hub, CAS, MEDEVAC, SALUTE, ordres, notes de renseignement, fiche SSE, NDA…) : textes affichés et textes fixes des fenêtres dans la langue du téléphone (même réglage et même dictionnaire que l'ATAK natif). Sans l'ATAK natif, rien ne change.
- Actions ACE (menu perso, objets), menu contextuel ZEN et modules Zeus ZEN des deux addons : noms traduits.
- Environ 2 000 nouvelles traductions (dictionnaire `i18n/en.txt`). `tools/i18n.py --connect wrap|extract` pour l'addon connect.
- Reste en français : réglages CBA de l'addon connect, journaux techniques, mod SSE.

## Relais : couverture réseau, triangulation, champs de vision ; calques de la carte qui disent ce qu'ils montrent (ATAK natif 1.53.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : triangulation par les relais, portée et couverture des relais, perte de réseau et débit, aperçu des champs de vision, et les relais invisibles sur la carte.
- **Relais sur la carte** (calque Wave Relay) : tous les relais Overwatch de la mission (plus seulement le plus proche) avec leur nom, leur portée en cercle et leur débit, les relais détruits barrés en gris, les passerelles de véhicule et PC mobiles. Le maillage se raccroche à n'importe quel relais (un équipier près d'un autre relais n'est plus « isolé »).
- **Couverture réseau** (Wave Relay, nouvel onglet COUVERTURE) : carte du débit reçu à hauteur d'homme sur 2, 5 ou 10 km autour de soi, relief et brouilleurs compris : vert 4 Mbit/s et plus, jaune, orange, rouge faible, gris zone blanche. Part du terrain couvert, débit moyen, débit à ma position, liste des relais avec ce qu'on en reçoit. Au survol de la carte, la barre du curseur donne le débit et la perte au point pointé.
- **Triangulation par les relais** : LOCALISER sur un équipier (onglet RÉSEAU ou clic droit sur lui dans la carte, « Localiser par relais »). Chaque relais ou téléphone du camp qui entend son appareil donne une distance estimée (plus le lien est faible, plus l'erreur est grande) ; la carte trace ces cercles et la zone trouvée « ± N m » pendant 10 min. Une seule source : un cercle ; deux : position ambiguë ; trois et plus : un point.
- **Champs de vision** : clic droit sur la carte, « Champ de vision ici » (observateur posé au point), ou sur un équipier, « Son champ de vision » (depuis ses yeux, dans la direction où il regarde). App Relief : hauteur d'œil ajoutée (toit 10 m, mât 50 m, drone 150 m), secteur 360°, 120° ou 60°, DEPUIS LE DRONE ; avec l'option « + bâtiments et arbres », les cases cachées par un mur, une maison ou des arbres sont en orange (rouge : par le relief) quand un drone est appairé. L'observateur et son secteur sont tracés sur la carte.
- **Calques de la carte** : allumer un calque dit ce qu'il montre ou pourquoi il est vide (aucune zone reçue d'Athena, aucun relevé EW, aucune demande logistique, Athena injoignable pour SIGINT...). Relief sans calcul lance le champ de vision depuis soi ; Wave Relay lance la couverture s'il y a des relais.
- À vérifier en jeu : relais et portées visibles sur la carte, couverture sur une mission avec relais (et brouilleur), LOCALISER un équipier balise coupée, champ de vision depuis un point, un équipier et le drone, chaque calque allumé puis éteint.

## Messagerie refaite (ATAK natif 1.52.0)

Non testé en jeu. Demande de TTRD : « améliorer l'UI de la messagerie ». Pas de DLL à recompiler, pas de migration.
- **Liste des conversations** : canaux Athena puis messages directs, avec icône, initiales ou photo de l'opérateur, dernier message, heure et pastille de non-lus ; + CANAL dans le bandeau d'état (Athena connecté, hors réseau…).
- **Conversation** : en-tête (retour, nom, sous-titre) et boutons icônes aide, tri, vider, corbeille (confirmation en rouge).
- **Bulles** ajustées au texte, regroupées par auteur, liseré de couleur par auteur, rouge pour l'urgent, séparateurs de jour en pastille, repère NON LUS.
- **Saisie** : champ souligné à la couleur d'accent, bouton d'envoi, aperçu des commandes au-dessus.
- **Paysage en main** : liste et conversation côte à côte. Téléphone porté : la conversation seule.
- Inchangé : canaux, commandes `/`, raccourcis `@grille`, SMS et accusés, vider, tri, création et suppression de canal.

---

## Correctif 2026-10-13 — Liaison : une adresse en erreur ne bloque plus tout (COMSPEC Link 2.0.76)

### Corrigé
- **Position et photos bloquées par « Rate limit — pause »** : Athena répond 503 (Retry-After 30) à toute exception d'une route `/api/atak/*`. Le DLL prenait ce 503 pour une saturation et mettait toute la liaison en pause (position « api_backoff », photos de drone retenues jusqu'à 150 s) dès que `/api/atak/vehicles` plantait.
- Désormais une erreur du serveur (5xx, hors 503 « maintenance ») ne met en pause que **l'adresse fautive** (30, 60, 120 puis 300 s), sans prévenir le jeu d'une saturation : la position, les autres envois et les photos continuent. Le lot de télémétrie en erreur repasse en envois unitaires pour que chaque adresse garde sa propre pause. Le vrai 429 (limiteur d'Athena) et le 503 de maintenance gardent la pause globale.
- La cause du 503 sur `/api/atak/vehicles` (clé `vehicle_callsign` absente) est corrigée côté site dans la PR #677.

### À reconstruire
- DLL COMSPEC Link (`mod/UptoDate/COMSPECExtension`, NativeAOT) : `build_mod.bat`.

## Correctif 2026-10-12 — ATAK sur le JPC et tablette du véhicule (ATAK natif 1.51.0)

Non testé en jeu. Retours de TTRD. Pas de DLL à recompiler, pas de migration.
- **Téléphone entre les jambes (vue à la première personne)** : le terminal du JPC n'est plus accroché à l'os de la poitrine (dont la position n'était pas fiable en vue à la première personne) mais au corps, à 22 cm devant et 40 cm sous les yeux, recalé quand la posture change (debout, accroupi, couché). Réglage pour les essais : `COMSPEC_ATAK_JpcOffset` (depuis les yeux).
- **Écran noir** : la face des coques à transparence recouvrait l'écran vivant (le verre éteint de la texture passait devant) ; l'ouverture est maintenant transparente. Les premières images de l'écran sont dessinées tout de suite, même sans le regarder. Sans « Image dans l'image » (Options > Vidéo), la page Carte montre la carte d'ensemble du terrain au lieu d'un rectangle noir, avec un rappel.
- **Tablette du véhicule** : c'est une tablette commune pour ceux qui n'ont pas d'ATAK. Un joueur qui a son téléphone garde son téléphone en montant dans un véhicule équipé (plus de bascule vers la tablette VT-10 ni de message « Tablette du véhicule disponible »). Sans téléphone, rien ne change.

## Télécommande drone sur son vrai téléphone (QR code), caméra de nuit, portée (ATAK natif 1.50.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Addon connect mis à jour (comspec_overwatch_connect.pbo reconstruit). Demande de TTRD : « tu scannes le QR code sur l'ordi ou dans le jeu, et ça ouvre le menu des boutons du drone sur ton téléphone IRL ».
- Site : nouvelle page **/telecommande-drone**, faite pour un téléphone (connexion Athena demandée la première fois). Choix du drone (les siens d'abord), état (mode, hauteur, vitesse, batterie), gros boutons : décoller, stationnaire, me suivre, retour au pilote, RTB, atterrir ; rôle du drone (me suivre, CQB, garde, recherche, aucun) ; altitude et vitesse ; essaim (tous me suivre, CQB, garde, recherche, stationnaire, RTB, atterrir) ; photo, veille, reprendre, forcer ; journal des ordres. Mêmes ordres que le poste (POST /api/atak/web-commands/uav, nouveaux ordres `role` et `swarm`).
- QR code : dans l'app Drone, onglet FLOTTE (« Télécommande sur votre téléphone », adresse du site de production) et dans le panneau Drones d'Overwatch sur le site (QR généré par le site, /telecommande-drone/qr.png).
- Jeu : un ordre web pour un drone de la flotte le prend en main (il devient le drone piloté) ; rôles et essaim passent par la flotte (fn_droneFleet).
- Pas de pilotage au joystick ni de vidéo en direct : les ordres passent par Athena (quelques secondes) et demandent que le pilote soit en jeu, Overwatch connecté, en liaison avec le drone.
- À vérifier : scanner le QR en jeu et sur le site, connexion puis page, chaque bouton, rôle et essaim avec deux drones, garde refusée quand le réglage admin est désactivé.
- **Caméra du drone noire la nuit** (retour de TTRD, 00:49, liaison 4/4 à 10 m) : l'image rendue dans l'écran ne s'adapte pas à l'obscurité comme la vue du joueur. En VISION : JOUR, le gain suit maintenant la lumière autour du drone : normal le jour, éclairci en pénombre, fortement éclairci la nuit (« BASSE LUMIÈRE » ou « MODE NUIT » dans l'incrustation). Même correction que le viseur de l'app Photo. NUIT et THERMIQUE ne changent pas.
- **Portée de la liaison drone** (retour de TTRD : « la portée est courte, le 4/4 ? ») : réglage admin par défaut 6000 m au lieu de 2500 m (jusqu'à 12 000 m), et la portée maximale du mod Mavic (mavic_setting_maxConnectionDistance) l'emporte si elle est plus grande. Barres plus douces : 4/4 jusqu'à la moitié de la portée (3000 m), 3/4 jusqu'à 71 %, 2/4 jusqu'à 87 %, 1/4 jusqu'au bout (avant : 4/4 seulement jusqu'à 625 m). Derrière le relief, toujours divisée par trois. Même règle pour la flotte et pour le signal des drones Mavic. Un réglage admin déjà enregistré à 2500 m reste à 2500 m : le remonter dans les paramètres CBA.
- **Boutons sous l'image du drone plus clairs** (retour de TTRD : « pas clair ») : une rangée principale en grands boutons, **PHOTO**, **MARQUER**, **ZOOM -**, **ZOOM +** et **VISION JOUR / NUIT / THERMIQUE**. Une rangée de vol : MONTER, DESCENDRE, STATIONNAIRE, CIBLES, MENUS DU DRONE. Le reste est replié sous **RÉGLAGES** : caméra vers le sol, recentrer, netteté et fermer. Le choix de la vidéo affichée (ex-« IMAGE : DRONE ») quitte l'app Drone, qui montre toujours le drone (retour de TTRD : « c'est pour la carte ») : il est sur la vue drone de la carte (**VIDÉO DU DRONE / DU TÉLÉPHONE / DU CASQUE**, puis **CASQUE DE …** pour choisir le porteur). Plus de titres de groupe CAMÉRA / CIBLES / VOL / AFFICHAGE. Le viseur photo ne change pas.
- **Performances du flux drone** (retour de TTRD : « le jeu rame ») : l'image du drone arrête d'être rendue 1,5 s après avoir quitté l'écran (avant 3 s), et la caméra est libérée après 20 s (avant 60 s). Toutes les 30 s, le journal Arma (RPT, ligne `[PERF]`) note les images par seconde et les caméras qui rendent dans une texture (drone, aperçu du cliché, viseur photo, live cam, carte JPC) : de quoi voir laquelle coûte, avec Envoyer_mes_logs.bat.

## Taille de la barre d'outils et style de boussole au choix (ATAK natif 1.49.0)

- Réglages de la carte : taille de la barre d'outils et style de la boussole au choix. Non testé en jeu.

---

## 2026-10-12 — Galerie peaufinée, clichés du drone supprimables, viseur Photo réparé (ATAK natif 1.48.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : écran de la Galerie plus propre, mêmes actions.

- Une seule rangée d'onglets : **TOUT**, **TÉLÉPHONE**, **DRONE** et **ENVOIS** (avant : onglets PHOTOS / ENVOIS puis un second filtre « Afficher »).
- Une ligne d'état de l'envoi au lieu du paragraphe d'explication (l'aide reste en infobulle).
- Une barre d'actions sans titres de rubrique : PRENDRE UNE PHOTO, ACTUALISER, ENVOYER LES NOUVELLES (en vert quand il y en a), TOUT SUPPRIMER.
- Lignes de photos : titre en gras, puis état d'envoi, date et grille en petit sur une seconde ligne ; titres de section avec leur nombre entre parenthèses.
- Boutons des lignes (DÉTAILS, RENVOYER…) au même gris-vert que les autres boutons du téléphone, au lieu du bleu ; vaut pour toutes les listes du téléphone.
- **Suppression** (retour de TTRD : impossible de supprimer les clichés du drone) : chaque ligne a son bouton SUPPRIMER (second appui sur CONFIRMER, en rouge), photos du téléphone comme clichés du drone. TOUT SUPPRIMER efface aussi les clichés du drone (fichier et fiche), sauf un cliché en cours d'envoi. Une photo déjà reçue reste sur Athena.
- **App Photo, viseur noir** (retour de TTRD, de nuit) : la caméra rendue dans l'écran ne s'adapte pas à l'obscurité comme la vue du joueur. Le viseur règle maintenant son gain selon la lumière : normal le jour, éclairci en pénombre, fortement éclairci la nuit (« MODE NUIT » affiché sur l'image). Texture du rendu dans le sens du viseur (paysage). Le journal note l'ouverture et l'arrêt du viseur (lumière, PiP, raison de l'arrêt) pour diagnostiquer un écran encore noir.
- **App Photo, viseur figé** (retour de TTRD : « on dirait que le visu reste sur place ») : la caméra du viseur restait à l'endroit où l'app avait été ouverte, et le joueur s'y voyait de loin. Elle suit maintenant les yeux du joueur à chaque image (même méthode que le plein écran). Un déclenchement interrompu ne fige plus le viseur plus de 5 s.

## App JTAC refaite : contrôle CAS réaliste du check-in au BDA (ATAK natif 1.47.0)

Non testé en jeu. Demande de TTRD : « JTAC faut une refonte complète, réaliste de l'app ». Pas de DLL à recompiler, pas de migration.
- Onglet SITUATION : cible verrouillée au laser, à la visée ou pointée sur la carte (grille 10 chiffres, altitude en ft MSL, amis les plus proches) ; appareils amis en station avec leur fiche de check-in lue sur l'appareil (armement restant, carburant, altitude, vitesse, nacelle).
- Onglet BRIEF : plan d'attaque (type de contrôle 1/2/3, BOT/BOC, armement, marquage, code laser) et 9-LINE calculé (cap et distance IP-cible en NM, altitude, position, marquage, amis) ; remarques FAH, LTL, menaces, DANGER CLOSE avec initiales du chef quand des amis sont sous la distance de risque de l'armement (valeurs indicatives).
- Onglet CONTRÔLE : frise de la mission (CHECK-IN, BRIEF, READBACK, CORRÉLATION, IN, AUTORISÉ, OFF, BDA), readback attendu et reçu, géométrie de l'appareil (vient d'où, cap, axe final d'attaque respecté ou non), LASER ON/OFF, CLEARED HOT (CLEARED TO ENGAGE en type 3) actif seulement quand la doctrine le permet, CONTINUE DRY, ABORT, BDA et re-attaque. Équipage IA : le JTAC note les appels du pilote entendus à la radio.
- Vue ÉQUIPAGE : le pilote joueur reçoit le 9-LINE sur son téléphone et répond par boutons (READBACK, CONTACT, TALLY, SPOT, VISUAL, NO JOY, IN, WEAPONS AWAY, OFF, UNABLE). Le 9-LINE part aussi sur Athena quand le téléphone est relié.
- À vérifier en jeu : à deux joueurs (JTAC au sol, pilote en vol), un passage complet jusqu'au BDA ; fiche de check-in sur plusieurs appareils (avion, hélicoptère, armements de mods) ; DANGER CLOSE avec amis près de la cible ; IP et cible pointées sur la carte ; ABORT puis nouvelle passe ; rafraîchissement de l'onglet CONTRÔLE sans saccade.

## Relief refaite : champ de vision, heatmap, ligne de vue, bâtiments vus (ATAK natif 1.45.0)

Non testé en jeu. Demande de TTRD : « Relief faut améliorer l'app : LIGNE DE VUE, HEATMAP, BÂTIMENT VISIBLE ». Pas de DLL à recompiler, pas de migration.
- **Écran** dans la famille de l'app Reco, en turquoise : en-tête (altitude, grille, pastille du calque carte cliquable), quatre tuiles d'outil, réglages en boutons, chiffres en tuiles.
- **Champ de vision** : obstacles au choix, RELIEF SEUL ou + BÂTIMENTS ET ARBRES (lignes de vue contre les objets, plus lent). Vues par secteur en barres de couleur, point haut sur la carte.
- **Heatmap** : nouvelle échelle à cinq couleurs (bleu nuit, cyan, vert, jaune, rouge) plus lisible, ou PAR RAPPORT À MOI (bleu en dessous, rouge au-dessus : ce qui me domine), légende avec les altitudes, part du terrain plus haut que moi.
- **Ligne de vue** : vers un carroyage saisi, le point visé (SOUS MON REGARD) ou un point touché sur la carte (outil LIGNE DE VUE de la carte, qui passe désormais par le même calcul). Verdict VUE DÉGAGÉE / VUE BLOQUÉE, nature de l'obstacle (relief, bâtiment, végétation, véhicule), distance, azimut, dénivelé, profil du terrain avec la ligne de visée.
- **Bâtiments vus** : bâtiments de plus de 4 m dans 200 m, 500 m ou 1 km, testés depuis mes yeux sur trois hauteurs ; en vert sur la carte s'ils sont vus, contour rouge sinon ; liste des dix plus proches avec CARTE.
- Nouvelles icônes : ligne de vue, heatmap, bâtiments.

À tester en jeu : chaque outil en ville et en campagne ; champ de vision avec bâtiments et arbres (temps de calcul à 2 km) ; heatmap par rapport à moi sur une colline ; ligne de vue derrière un mur, un arbre et une crête ; outil LIGNE DE VUE de la carte puis retour dans Relief ; bâtiments vus à 1 km (temps de calcul) et leurs couleurs sur la carte.

## Doigt d'honneur libre, qui suit la cible (ATAK natif 1.44.0)

Non testé en jeu. Demande de TTRD : « un doigt d'honneur libre, qui suit vraiment le curseur et les joueurs, comme GTA ». Pas de DLL à recompiler, pas de migration. Le doigt d'honneur classique (geste de 2,4 s) reste tel quel.
- Nouvelle touche CBA « Doigt d'honneur libre (maintenir) » (COMSPEC ATAK, non attribuée par défaut) : tant qu'elle est maintenue, bras gauche tendu, dos de la main vers la cible, majeur levé, petit coup sec en boucle. Action ACE perso « Doigt d'honneur libre (6 s) ».
- Cible revue 8 fois par seconde : l'homme sous le curseur (100 m), sinon le joueur ou l'IA le plus proche du centre de l'écran (moins de 30°, 60 m), sinon la direction du curseur. Le bras suit sur une grille de 24 poses (cap de -40° à +60° côté gauche, site de -20° à +40°, par pas de 20°) ; au-delà il reste au bord. Arma ne permet pas de tourner un os librement par script, d'où la grille.
- À vérifier en jeu : passage d'une case à l'autre (fluide ou saccadé), bras qui suit bien un joueur qui se déplace, cibles au-dessus et en dessous, fusil en main (la main gauche quitte puis retrouve le garde-main), geste vu par les autres.

## Correctif 2026-10-12 — App Plan : la zone terminée ne disparaît plus ; symboles et étiquettes de la carte lisibles (ATAK natif 1.43.1)

Non testé en jeu. Pas de DLL à recompiler, pas de migration.
- Une zone (ou tout élément) envoyée au plan **partagé** disparaissait à la fin du tracé : le téléphone vidait le tracé et attendait que le serveur écrive le plan, et rien ne revenait (serveur sans le mod ATAK natif, ou refus du serveur).
- L'élément reste affiché pendant l'attente. Sans réponse du serveur au bout de 5 s, il est gardé dans le brouillon, avec un message et une ligne `PLAN` dans le RPT.
- Le serveur annonce qu'il a le mod (`COMSPEC_ATAK_PlanSrvOn`). Sans lui, le téléphone place tout de suite les éléments dans le brouillon et le dit : le plan partagé avec les coéquipiers demande le mod sur le serveur.
- Carte du téléphone : les symboles suivent la taille de l'écran de l'ATAK (mini, porté, plein écran, portrait ou paysage) au lieu d'un plancher qui les gardait trop gros sur la petite carte. Les textes suivent aussi, sans descendre sous une taille lisible.
- Étiquettes de la carte : indicatifs et textes des marqueurs en blanc cerné de noir, un peu plus grands, au lieu de la couleur de l'unité avec une ombre légère. Cartouches plein écran plus grands, deuxième ligne plus claire. Même règle pour l'app Plan.

## Selfie : le téléphone dans la main, l'objectif sur le téléphone (ATAK natif 1.43.0)

Non testé en jeu. Retour de TTRD après essai : « anime OK et manque le S7 dans la main », la main passait aussi devant l'image. Pas de DLL à recompiler, pas de migration.
- Pendant le selfie, le téléphone du profil (S7 par défaut, coque teintée comme sur le JPC ; autres modèles du profil, la tablette à touches devient un S7) est tenu dans la main droite, en paysage, écran vers le visage. Objet global accroché à l'os de la main : il suit le bras et les autres joueurs le voient. Il redescend avec le bras puis disparaît.
- La caméra du selfie (app Photo objectif avant et plein écran R) est maintenant posée sur le téléphone, 3 cm devant l'écran, tournée vers les yeux : la main ne masque plus le visage. La souris (ou le glisser dans l'app) tourne la vue autour du téléphone.
- À vérifier en jeu : téléphone bien dans la paume (ni décalé ni traversant les doigts). Réglage sans recompiler, dans la console : `COMSPEC_ATAK_SelfiePhoneAttach = ["righthand", [x, y, z], vectorDir, vectorUp]` (valeur actuelle `["righthand", [0.013, -0.05, -0.066], [0.941, -0.134, 0.311], [0.293, 0.782, -0.55]]`), puis repasser en selfie.

## ALERTES refaite, SALUTE dans sa propre app (ATAK natif 1.42.0)

Non testé en jeu. Demande de TTRD : reprendre le style de l'app Reco pour les Alertes, bouton panique prioritaire, compte rendu SALUTE hors des Alertes. Pas de DLL à recompiler, pas de migration.
- **Alertes** : en-tête rouge (heure, ma grille, pastille CAMP + POSTE ou CAMP SEULEMENT selon Athena). **PANIQUE** en tête, sur toute la largeur : grand bouton rouge sombre, rouge vif quand il est armé (« Appuyez encore dans les 5 secondes pour ENVOYER »), toujours deux appuis. Alertes rapides en trois tuiles à icône (CONTACT, FIN DE CONTACT, APPAREIL ABATTU). Journal en cartes de la couleur de l'alerte, bouton CARTE sur chacune.
- **SALUTE** : nouvelle app (section Renseignement, à côté de Reco), en bleu. Une grande lettre par champ avec exemple ; L avec ICI et VISÉ (point sous mon regard) et la distance et l'azimut ; T avec MAINTENANT ; compteur « x/6 remplis » ; ENVOYER LE SALUTE et EFFACER ; derniers SALUTE de la mission en cartes. Raccourci depuis les Alertes. Même envoi qu'avant (camp et poste Athena).
- Nouvelles icônes : panique, contact, fin de contact, appareil abattu, SALUTE.

À tester en jeu : PANIQUE (un appui arme, le deuxième envoie, retour au repos après 5 s ; reçue par un autre joueur du camp) ; les trois alertes rapides ; app SALUTE : saisir, ICI / VISÉ / MAINTENANT sans perdre la saisie, envoyer avec moins de deux champs (refusé) puis complet ; le SALUTE apparaît dans les deux journaux ; affichage en portrait et en mode petit.

## Drone : flotte de 4 drones, rôles, essaim, garde rapprochée ; repérage automatique en réglage admin (ATAK natif 1.39.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Suite des demandes de TTRD sur le drone.
- **Flotte** : jusqu'à 4 drones appairés au même téléphone. Appairer un autre drone garde le premier, qui continue seul son ordre. Un seul est « piloté » (caméra, onglets VOL, TÂCHES, CIBLES) ; PILOTER dans le nouvel onglet FLOTTE change de drone. Désappairer ou perdre le drone piloté passe au suivant.
- **Rôles par drone** (onglet FLOTTE) : ME SUIVRE, CQB, GARDE, SURVEILLER LE POINT (point de l'onglet TÂCHES), SURVEILLER LE JOUEUR (joueur choisi dans VOL), RECHERCHE ; plus STATIONNAIRE, RTB, LIBÉRER. Exemple : drone 1 en CQB derrière soi, drone 2 qui surveille un point ou un joueur.
- **Essaim** : TOUS ME SUIVRE, TOUS EN CQB, TOUS EN GARDE, TOUS SUR LE POINT, TOUS EN RECHERCHE, TOUS STATIONNAIRE, TOUS RTB, TOUS ATTERRIR. Chaque drone garde sa place : suivi décalé sur le côté selon son rang, orbites de rayons différents, secteurs de recherche différents.
- **Garde rapprochée (IA de proximité)** : le drone suit le pilote, repère seul les ennemis à moins de 200 m en vue directe, prévient (notification, vibreur, journal, croix CONTACT sur la carte pendant 1 min), va observer le plus proche s'il passe sous 120 m, puis revient au pilote 20 s après l'avoir perdu.
- **Drones en arrière-plan** : retour automatique au point de décollage si la liaison ou la batterie lâche, sortie des zones interdites, dessinés sur la carte avec leur rôle.
- **Réglage admin « Repérage automatique par drone »** (désactivé par défaut, demande de TTRD : « trop pété ») : sans lui, la caméra du drone ne relève plus seule les ennemis (détections automatiques de l'onglet CIBLES, pistes sur la carte) et la garde rapprochée est refusée. Le pilote repère et marque lui-même.
- À vérifier en jeu : deux drones appairés, PILOTER, rôles mélangés (CQB + surveiller), essaim qui suit sans collision, garde avec le réglage activé, RTB d'un drone en arrière-plan hors liaison.

## Connexion Athena : e-mail et mot de passe proposés à côté du code (ATAK natif 1.38.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Demande de TTRD : « propose quand même la connexion par mail et mot de passe ».
- **App Athena, onglet COMPTE** (celui qui s'ouvre par défaut) : sous le code à autoriser sur le site, une section « Ou avec e-mail et mot de passe » permet de se connecter directement avec les identifiants du compte Athena, sans passer par le site. Même connexion que l'onglet E-MAIL (qui garde le code reçu par e-mail pour un mot de passe oublié).
- **Message au lancement** : il précise que l'app Athena du téléphone accepte aussi e-mail et mot de passe.
- Textes traduits en anglais.
- À vérifier en jeu : connexion e-mail + mot de passe depuis l'onglet COMPTE (bon et mauvais mot de passe), puis profil synchronisé.

## Retours en jeu 2026-10-12 — Volet rapide, Météo, CAS, Reco refaite (ATAK natif 1.37.0)

Non testé en jeu. Corrections tirées du journal Arma (.rpt) envoyé par TTRD.
- **Volet rapide** : les tuiles étaient trois fois trop larges et sortaient de l'écran (variable `_tW` de la fiche opérateur qui écrasait `_tw`, SQF ignore la casse). Même défaut corrigé dans l'établi des **Accessoires** (`_bW` / `_bw`).
- **Météo** : la page s'arrêtait dès l'ouverture avec ACE (`ace_weather_fnc_calculateBarometricPressure` appelé avec un tableau au lieu d'une altitude).
- **CAS** : APPEL DE FEU envoyait une erreur à chaque image (`find … max 0` sans parenthèses), d'où l'app « buggée » ; écran repassé aux couleurs sombres du téléphone (le thème clair délavait l'écran).
- **Écran opaque** : fonds du téléphone sans transparence, on ne voit plus le décor à travers selon l'app.
- **Marqueurs** : la liste Type commence par la palette (tactique, puis Ennemi / Ami / Neutre / Inconnu par type d'unité, avec icônes colorées), puis la bibliothèque sans doublons de libellé. Libellés MarkersPlus mal encodés corrigés.
- **Réglages** : icônes propres pour Langue (globe) et Personnalisation (palette).
- **Téléphone porté** : la carte suit le joueur à chaque image au lieu de sauter chaque seconde ; barre du bas avec ma grille, le MGRS et l'altitude.
- **Reco** refaite : en-tête façon FRS en ambre, tuiles QUOI à icône, FIABILITÉ, OÙ (MA POSITION, SOUS MON REGARD, distance et azimut), OBSERVATION avec compteur 140, ENVOYER, dernières notes en cartes colorées.

## Drone : zones interdites, app Marquer tracking, raccourcis, journal en fil (ATAK natif 1.36.0)

Non testé en jeu. Pas de DLL à recompiler, pas de migration. Suite des demandes de TTRD sur le drone (après 1.31.0).
- **Suivi (ME SUIVRE, escorte)** : le drone ne tourne plus en rond autour d'un joueur immobile. Il garde le cap de la dernière marche et, à moins de 15 m de sa place, reste en stationnaire, caméra sur le joueur, jusqu'à ce qu'il reparte.
- **Raccourcis CBA** (COMSPEC ATAK, non attribués par défaut) : « Drone : RECHERCHE », « Drone : ME SUIVRE », « Drone : RTB », « Drone : ATTERRIR ». Ordre noté au journal.
- **Cible marquée (LOCK)** : nouveaux boutons EN MARQUEUR (éditeur de marqueur prérempli à sa position), EN FICHE SSE (fiche FRO préremplie) dans l'onglet CIBLES du drone et dans la nouvelle app ; CAMÉRA DU DRONE accroche la caméra dessus et l'affiche en petit sur la carte.
- **App MARQUER TRACKING** (Renseignement) : toutes les cibles marquées, filtre TOUTES / ACCROCHÉES / PERDUES / NEUTRALISÉES, CARTE, EN MARQUEUR, EN FICHE SSE, CAMÉRA DU DRONE, OUBLIER, OUBLIER TOUTES ; plus la liste des zones interdites (CARTE, RETIRER).
- **Zones interdites au drone** : clic droit sur la carte > « Zone interdite au drone » (rayon 200 m, partagée avec le camp, cercle rouge hachuré). Un ordre dont le but y tombe est refusé ; un drone qui y entre en ressort par le bord le plus proche (sauf frappe, atterrissage, retour automatique).
- **Journal du drone en fil de discussion** : ordres du pilote à droite (PILOTE), retours du drone à gauche (DRONE).
- Pas encore fait : plusieurs drones à la fois, essaim et rôles simultanés (drone 1 en CQB, drone 2 sur un marqueur), IA de proximité. Le téléphone ne pilote qu'un drone appairé ; c'est un chantier à part.
- À vérifier en jeu : suivi joueur arrêté puis reparti, les 4 touches, zone posée/retirée et vue par un équipier, drone qui ressort d'une zone, ordre refusé, EN MARQUEUR / EN FICHE SSE, app Marquer tracking, journal.

## Vague 2026-10-12 (suite) — Serveur de la communauté, Tinder refait (ATAK natif 1.35.0, COMSPEC Link 2.0.75)

Non testé en jeu. DLL COMSPEC Link à recompiler (build_mod.bat) et `run-migrations.php` à lancer (tables `tenant_game_servers`, `tenant_game_server_presence`).
- Site, Gestion › Mod ATAK › **Serveurs de jeu** : la communauté déclare ses serveurs (IP ou nom d'hôte, port, nom dans la liste Arma). La page montre où ses joueurs reliés à Athena ont joué sur 72 h et propose d'ajouter en un clic un serveur inconnu.
- Téléphone : un joueur relié à Athena qui joue ailleurs que sur un serveur de sa communauté reçoit une notification (puis un rappel toutes les 30 min). L'app Réseau affiche le serveur reconnu.
- Le serveur dédié demande son adresse publique à Athena au lancement (`Server.PublicIp`) et la diffuse ; sans DLL côté serveur (Linux), le nom du serveur sert de repli. Le client se signale avec `Server.Presence` (POST `/api/atak/server-presence`) toutes les 10 min.
- Tinder : grande carte de profil avec le vrai visage de la personne rendu en direct (3 photos : portrait, buste, en pied), prénom, âge, grade, rôle, distance, bio, centres d'intérêt, compatibilité. PASSER, SUPER LIKE (le nom est donné, 1 toutes les 10 min), J'AIME. Onglet MON PROFIL (aperçu de sa carte, bio, visibilité, likes reçus).
- Tinder : de temps en temps un profil tiré des IA de la carte à moins de 5 km, HVT ou personne SSE, sans rien qui le signale. Un match ouvre une conversation qui livre le lieu-dit et un carroyage approximatif (300 m HVT, 150 m SSE), « OÙ ES-TU ? » toutes les 3 min, marquage de la zone sur sa carte.
- Réglage de mission « Tinder : profils HVT et SSE » (Jamais, Rare, Parfois, Souvent) et module Zeus « Tinder : profil HVT / SSE » (type, prénom, bio, chance de match). En Eden : `this setVariable ["COMSPEC_ATAK_TinderKind", "HVT", true];`.

## Gestes : doigt d'honneur et selfie (ATAK natif 1.34.0)

Non testé en jeu. Demande de TTRD : « une animation pour faire des doigts d'honneur et une animation pour le selfie ». Pas de DLL à recompiler, pas de migration.
- Deux animations maison (fichiers `anim/*.rtm`, générés par `tools/gen_gestures.py`, sans reprise d'un autre mod). Aperçus filaires : `tools/gen_gestures.py --preview dossier`.
- **Doigt d'honneur** : bras gauche levé devant l'épaule, dos de la main vers la cible, majeur tendu, deux coups secs vers l'avant (~2,4 s), puis retour. La main gauche quitte le garde-main et y revient : marche aussi fusil en main. Touche CBA « Doigt d'honneur » (COMSPEC ATAK, non attribuée par défaut) et action ACE perso (dans le menu Gestes d'ACE quand il est affiché). Pas en véhicule, inconscient, sous l'eau ni pendant l'appareil photo.
- **Selfie** : bras droit qui se lève (0,6 s), tendu devant le visage à hauteur des yeux, téléphone tourné vers soi, léger balancement tant que dure le selfie, puis redescend. Joué dans l'app Photo (objectif avant : l'arme est rangée et ressortie quand on repasse à l'objectif arrière ou qu'on quitte l'app) et dans le plein écran (R). Réglage CBA « Animation du selfie » : par défaut `comspec_atak_native_fnc_gestureSelfie` (l'ancien `gesturePoint` reste possible).
- À vérifier en jeu : cadrage du bras sur le perso (fusil, pistolet, mains vides, accroupi), main gauche qui revient bien sur le garde-main, bras visible dans la vue selfie, geste vu par les autres joueurs.

## 2026-10-12 — Tâches et ordres : crash, suivi par joueur, chef pour le groupe ; rejeux datés ; temps par rôle (ATAK natif 1.33.0)

Non testé en jeu. Pas de DLL à recompiler. Côté site : la table `atak_order_responses` se crée toute seule à la première réponse.
- Crash sur l'app Tâches (`can't resize AutoArray to negative size`, sans erreur de script avant) : les boutons des formulaires (SUIVRE, CARTE, REÇU…) redessinaient la page pendant leur propre clic, ce qui détruisait le bouton en cours d'exécution. Les clics passent maintenant à l'image suivante, et la colonne de texte d'une ligne n'a plus jamais une largeur nulle ou négative.
- TERMINÉ envoyait DELIVERED (« vu » pour Athena), refusé après REÇU : il envoie DONE. EN COURS ou TERMINÉ sur un ordre pas encore reçu envoie REÇU d'abord. Un ordre « vu » par le site n'est plus affiché TERMINÉ en vert.
- Suivi par ordre : « Vu x/y · reçu · en cours · terminé » et bouton QUI ? avec la photo, le groupe, le statut et l'heure de chaque destinataire. « Vu » part tout seul à l'affichage.
- Chef d'un groupe de plusieurs joueurs : sa réponse vaut pour tout le groupe (statut mis à jour sur les téléphones des membres, note « Pour le groupe … » sur le site).
- Rejeu (app AAR) : date et heure réelles de chaque image, début de la session. Clé de mission avec une date lisible (AAAAMMJJHHMM sur deux chiffres).
- Site : tâches transmises en cartes (étapes Transmis, Vu, Reçu, En cours, Terminé ; réponses de chaque joueur ; filtre En cours, Créées en jeu, Toutes), tâches créées en jeu listées. Rejeux : jour, début, fin et durée de chaque session, heures locales. Fiche personnel et fiche ATAK : temps passé dans chaque rôle.

## Drone : flux réparé, caméra, titre des clichés, caméra d'un équipier sur la carte (ATAK natif 1.31.0)

Non testé en jeu. Retours de TTRD après le test du 12 octobre au soir. Pas de DLL à recompiler, pas de migration.
- Flux du drone : le filtre NO DATA restait affiché en permanence, avec « true » écrit dessous. La fonction de la neige renvoyait toujours `true` au lieu de la raison de la coupure : n'importe quel flux passait pour coupé. Elle renvoie maintenant la vraie raison (vide quand le flux passe).
- « Nacelle » devient « caméra » partout à l'écran (CAMÉRA CAP … · SITE …, DIRIGER LA CAMÉRA, aide et infobulles), en français comme en anglais.
- Clichés du drone : plus rien ne part sur Athena ni sur Discord sans titre. ENVOYER sur une fiche sans titre rouvre la fiche avec un avertissement ; le champ s'appelle TITRE (obligatoire). L'envoi automatique depuis le dossier Screenshots (surveillance disque de COMSPEC Link) ignore désormais les fichiers COMSPEC_DRONE_ : c'est lui qui publiait les clichés aussitôt pris, sans titre, description ni SSE.
- Panneau SITUATION de la carte : le texte se réduit tout seul tant qu'il ne tient pas au-dessus des calques (plus de ligne coupée ni de texte sous CALQUES) ; la météo tient sur deux lignes (Pluie, Vent) ; le dernier calque seul sur sa rangée prend toute la largeur (« Altitudes (heatmap) » lisible en entier).
- VOIR CAMÉRA : dans l'app BFT (piste d'un équipier choisie) ou d'un clic droit sur un équipier de la carte (« Voir la caméra »), la carte se centre sur lui et sa caméra poitrine s'affiche en petite fenêtre en haut à gauche, à côté de la boussole (aussi sur la petite carte). Boutons : JOUR / VISION NOCT. / THERMIQUE, GRAND (app Vidéo live), FERMER. Le flux se coupe seul si l'équipier meurt ou perd son téléphone.

À tester en jeu : ouvrir la caméra du drone (plus de NO DATA tant que la liaison passe, NO DATA avec une vraie raison quand on éloigne le drone) ; prendre un cliché et vérifier qu'aucun message n'arrive sur Discord avant ENVOYER, et qu'ENVOYER sans titre est refusé ; VOIR CAMÉRA depuis le BFT et depuis le clic droit, avec un deuxième joueur ; panneau SITUATION ouvert avec un long nom de groupe.

## 2026-10-12 — Photos : vraie grille MGRS et heure Zulu du jeu (ATAK natif 1.30.0, COMSPEC Link 2.0.74)

Non testé en jeu. Demande de TTRD : les photos envoyées doivent porter une vraie grille militaire et l'heure Zulu du jeu, pas celle du PC.

- **Grille MGRS** : nouvelle fonction `fn_mgrsGrid` (connect). Le terrain est placé sur le globe par sa latitude et sa longitude (CfgWorlds), puis converti en UTM WGS84 : zone, bande, carré de 100 km et coordonnées à 1 m, par exemple `35S LC 12345 67890`. Remplace la grille Arma à 6 chiffres sur les photos envoyées.
- **Heure Zulu du jeu** : la photo transmet la date, l'heure et le terrain de la partie (COMSPEC Link 2.0.74, champs `game_date`, `game_time`, `world_name`). Le bandeau incrusté par Athena et le relais Discord affichent cette heure. Sans ces champs (ancienne DLL), l'heure réelle reste utilisée.
- À faire par TTRD : reconstruire la DLL avec `build_mod.bat`.

## Vague 2026-10-12 — Musique : bibliothèque du site et lecteur refait (ATAK natif 1.29.0, COMSPEC Link 2.0.73)

Non testé en jeu. Migrations à lancer (`music_library`) et DLL à recompiler (COMSPEC Link et COMSPECATAKNativeExtension, `build_mod.bat`).
- Site, **Mon espace > Musique** (`/back-office/ma-situation/musique`) : chaque membre met en ligne ses MP3, M4A, AAC ou WAV (25 Mo par fichier, 300 Mo par membre), dans la **bibliothèque communautaire** (toute la plateforme) ou celle de **son organisation**. Lecteur intégré (file, avance, volume), recherche titre / artiste / genre.
- **Playlists** privées, d'organisation ou publiques ; **playlist globale** unique tenue par l'administrateur de la plateforme (morceaux de la bibliothèque communautaire seulement).
- Modération : les gestionnaires masquent ou suppriment les morceaux de leur organisation, l'administrateur de la plateforme ceux de la bibliothèque communautaire.
- Téléphone, app Musique : nouvel onglet **ATHENA** (playlist globale, communauté, mon orga, playlists, mes envois), lu par COMSPEC Link (`AthenaMusic`, API `/api/atak/music`). Morceaux téléchargés par URL signée puis gardés en cache (60 morceaux) ; en haut-parleur, les joueurs proches entendent le même morceau.
- Lecteur : avance / recul de 10 et 30 s (suivi par les auditeurs du haut-parleur), file d'attente avec MONTER, RETIRER et VIDER LA FILE, **favoris** (bouton FAV sur chaque morceau, section Favoris dans LECTURE).
- Réglage CBA serveur **Bibliothèque Athena** pour couper l'onglet.

## Traduction 2026-10-12 — Menu CBA, objets et modules en anglais (ATAK natif 1.21.0)

Non testé en jeu.
- Menu CBA (Échap > Options > Options des addons > COMSPEC) : titres, aides, sections et choix des listes dans la langue du téléphone (`fn_addSetting`). Changer de langue en partie : relancer la mission pour que ce menu suive (le téléphone, lui, change tout de suite).
- Noms et descriptions des objets (arsenal, inventaire, Zeus) et modules Eden/Zeus d'extraction de données : `stringtable.xml`, dans la langue du jeu (Arma en anglais = anglais). Généré par `tools/i18n.py stringtable`.

## 2026-10-12 — Connexion au compte Athena sans clé ni identifiant (ATAK natif 1.20.0, COMSPEC Link 2.0.72)

Non testé en jeu. Demande de TTRD : celui qui installe le mod se connecte avec son compte Athena et tout se synchronise, sans clé de communauté ni identifiant de communauté dans les options d'addons.
- App Athena du téléphone, nouvel onglet **COMPTE** (ouvert par défaut) : un code de 8 caractères s'affiche tout seul. Le joueur l'autorise sur **athena.ttrd.fr/lier**, connecté à son compte (bouton OUVRIR LA PAGE : le navigateur s'ouvre avec le code déjà rempli). Le jeu reçoit sa session : profil, communauté, réglages.
- Le Steam du joueur est associé au compte à cette occasion : aux parties suivantes, la connexion se fait seule.
- Premier lancement : après « Oui » au service Athéna, la page s'ouvre avec le code. À chaque partie sans session : un code et une notification, sans fenêtre à remplir.
- Options d'addons : la clé d'accès et l'identifiant de communauté passent dans **COMSPEC > Overwatch · Serveur (optionnel)**, à laisser vides. Un serveur peut encore les imposer.
- La communauté de la session est reprise par le mod (fiches d'équipe, avatars, diapositives) sans identifiant saisi.
- Site : nouvelle page **/lier** (Mon compte > Appareils ATAK) qui valide le code du jeu ; le code de l'adresse est gardé si la personne doit d'abord se connecter.
- COMSPEC Link 2.0.72 (DLL à recompiler) : `OpenAthenaLink` (ouvre la page du portail, refuse tout autre site), `GetSessionTenant`, et `PairStart` renvoie l'adresse avec le code.

## Vague 2026-10-12 (suite) — App Photo dans le téléphone, nouvelle app Galerie (ATAK natif 1.18.0)

Non testé en jeu. Demande de TTRD : l'app Photo doit s'ouvrir sur l'appareil photo, dans l'écran du téléphone, et non en mode photo plein écran ; le plein écran devient un bouton ; les photos se revoient dans une app Galerie séparée.

- **App Photo** : s'ouvre sur le viseur en direct, dans l'écran du téléphone (caméra rendue dans l'écran, comme Live cam et Drone). L'image suit la vue du joueur ; glisser sur l'image décale l'objectif, la molette zoome (x1 à x8), un double clic recentre. Grille des tiers, réticule, grille MGRS, cap et heure incrustés.
- Boutons sur l'image : FLASH (AUTO / FORCÉ / COUPÉ), PLEIN ÉCRAN (l'ancien mode photo, inchangé) et zoom − / +. Sous l'image : GALERIE, déclencheur, SELFIE (objectif avant). Légende facultative en bas.
- Au déclenchement, l'écran bascule un instant sur une caméra posée comme le viseur (même zoom), téléphone et HUD masqués, puis la photo part vers Athena (gardée sur le téléphone hors réseau).
- **Nouvelle app Galerie** (section Renseignement, à côté de Photo) : onglets PHOTOS (photos du téléphone et clichés du drone, filtres, fiches, envoi, suppression) et ENVOIS (suivi de réception par Athena). C'est l'ancienne partie GALERIE / ENVOIS de l'app Photos, déplacée.
- Sans « Image dans l'image » (PiP) dans les options vidéo d'Arma, le viseur l'indique et propose le plein écran.

## Volet rapide redessiné, portrait opérateur du site (ATAK natif 1.17.0)

Non testé en jeu. Demande de TTRD : « Améliore l'UI du menu et affiche l'image de l'opérateur issue du web ».
- Volet Réglages rapides (tiré de la barre d'état) : en tête, la fiche de l'opérateur avec son portrait Athena encadré (couleur d'accent quand Athena est en ligne), indicatif, grade et nom, unité et communauté ; à droite l'heure en grand, la date du jour et l'état de la liaison Athena.
- Sans portrait (hors ligne, pas encore reçu ou aucun sur le site) : les initiales de l'indicatif à la place. Le portrait apparaît tout seul dès qu'il arrive.
- Tuiles avec icône (lune, avion, cloche barrée, vibreur, Bluetooth, balise IR, réseau, batterie) ; allumée = fond teinté de l'accent et liseré en haut. Luminosité avec deux soleils et la valeur en %, mise à jour pendant le glissé. TOUS LES RÉGLAGES en accent, FERMER discret.
- Site : `/api/atak/player-profile` renvoie le portrait opérateur (jamais la photo de compte ni Steam), en vignette JPEG carrée 256 px recadrée vers le visage (`uploads/portraits/atak/`), car Arma ne lit ni PNG ni WebP. Le même portrait s'affiche donc aussi dans les apps Athena, Profil, Groupe et Chat. Pas de DLL à recompiler, pas de migration.
- Textes traduits en anglais.

## Correctif 2026-10-12 — App CAS, alertes du poste, barre de dessin (ATAK natif 1.16.2)

Non testé en jeu. Pas de DLL à recompiler, pas de migration (déploiement PHP pour la correction des photos).
- App Feux renommée **CAS**. L'onglet APPEL DE FEU reprend l'écran du clip (motion design) : écran clair en panneaux.
  - POSITION DE TIR : liste des pièces du camp, MAN, PIÈCE À MA POSITION, grille et altitude.
  - CIBLE : GRILLE ou POLAIRE, depuis OBSERVATEUR (grille saisie) ou MA POS, AZ, DIST, cible calculée, ENNEMI, HE / ILM / FUM, nombre de coups, OFF / GERBE, EFFACER.
  - Bandeau de solution : unité, charge, dérive, élévation, durée de vol, flèche ; intervalle vertical et dérive du vent indicative. Balistique sans frottement à partir de la vitesse initiale et des charges de l'arme (pièce manuelle : mortier de 82 mm).
  - ÉTAT DE LA MISSION et JOURNAL (toucher une mission pour la rappeler, NOUVELLE MSN) ; en bas CALCULER, FIN DE MISSION, TIR.
  - En haut : onglets 9-LINE, 5-LINE, APPEL DE FEU, ma grille, mon altitude et le vent. En portrait, les panneaux s'empilent.
- Alerte plein écran, notification et vibration envoyées depuis le site (Overwatch beta) : elles arrivent de nouveau sur le téléphone natif. COMSPEC Link les passait au module `atak_athena` (ancien téléphone Iceman, livré dans le même mod et donc toujours chargé) au lieu du natif ; `fn_receiveOrder` les donne maintenant au natif quand il est chargé. `atak_athena` n'est pas modifié.
- Carte, barre TRAIT / DESSIN LIBRE : DÉFAIRE devient ANNULER et FERMÉ devient ZONE.
- Site : correction de couleur automatique des photos reçues du jeu (téléphone, tablette, caméra casque ; pas la vision nocturne, la thermique ni le drone). Niveaux étirés, tons moyens plus denses, saturation un peu relevée ; l'original est gardé hors de la racine web (`storage/private/recon/originals`) et supprimé avec la photo (`PhotoAutoLevel`).
- Appareil photo : F (flash) ne ressort plus l'arme. Les touches de l'appareil (Espace, R, F, T, zoom) restent au téléphone ; si le jeu voit quand même F, l'arme est rangée de nouveau.
- Traits et repères du téléphone absents du site : quand l'envoi au site est refusé ou mis en attente, le téléphone l'affiche avec la raison (synchro des marqueurs coupée, liaison Athena pas prête…). COMSPEC Link note maintenant la raison de chaque refus.
- Discord, photos postées (Quick Picture, drone) : champ « Téléphone » (« Terminal de contrôle » pour le drone) avec appareil et version ATAK, numéro, IMEI, MAC et batterie, chacun en texte masqué (spoiler, cliquer pour lire). Rien pour une photo confidentielle. La version ATAK arrive avec le nouveau connect de COMSPEC Link (`fn_phoneDeviceSync`, repaqueté).
- Photos : logo de l'unité en filigrane (logo de l'organisation dans les paramètres du site, PNG transparent), avec « © UNITÉ » dessous, dans le coin opposé au bandeau. Désactivable dans Config ATAK > bandeau photo (« Logo de l'unité en filigrane »). Le texte gauche du bandeau ne chevauche plus l'heure.
- Photos du téléphone et de la tablette (Discord, bandeau, fiche Athena) : « Unité » affichait le pseudo du profil Arma. Le site met maintenant l'unité de l'opérateur (unité principale, sinon celle de sa fiche personnel, sinon le nom de l'organisation) ; le jeu envoie le nom du groupe au lieu du pseudo quand le joueur n'est pas reconnu.

## Correctif 2026-10-12 — Langue dans l'app Réglages (ATAK natif 1.16.1)

Non testé en jeu. TTRD ne trouvait pas où passer le téléphone en anglais.
- Nouvelle catégorie **Langue / Language** en tête de l'app Réglages du téléphone : FRANÇAIS, ENGLISH ou AUTO (langue du jeu). Même réglage que le menu CBA, la page se redessine aussitôt dans la langue choisie.
- Le réglage CBA reste dans Échap > Options > Options des addons > COMSPEC, onglet Client, section **ATAK · Langue / Language** (plus bas dans la liste des sections ATAK).

## Vague 2026-10-12 (suite) — Réseau, drone, photos drone sur Discord, saleté (ATAK natif 1.16.0, COMSPEC Link 2.0.69)

Non testé en jeu. Corrige l'app Réseau qui affichait « Athena hors ligne · réponse jamais · 0 paquet envoyé » alors que la position arrivait sur le site et que l'icône de liaison était verte.
- Cause : la page lisait des compteurs que rien ne remplissait (dernière réponse posée seulement à la liaison, paquets jamais comptés).
- Nouveau `fn_athenaPulse` : lit en mémoire dans COMSPEC Link, sans requête réseau, les lots de positions et de télémétrie réussis (`GetTelemetryMetrics`) et le journal d'envoi. Marche avec la DLL actuelle.
- COMSPEC Link 2.0.69 (DLL à recompiler) : `SendQueue.Status` ajoute requêtes, réponses, âge de la dernière réponse, latence et perte sur 15 min, lots de télémétrie compris.
- Joueur immobile (rien à renvoyer, aucun échec) : Athena reste « en ligne ».
- Photos drone sur le même webhook Discord que les Quick Pictures (site seulement) : floutées si SSE, sans image si confidentielles (message, lien de lecture sur Athena et note de confidentialité).
- Saleté de l'écran et de la coque bien plus discrète : poussière fine au lieu de grosses taches floues.
- Drone, flux vidéo : la neige NO DATA ne s'affiche plus que pour une vraie perte (PiP coupée, drone perdu ou en veille, liaison perdue), raison toujours lisible dessous ; caméra libérée ou image sur une ancienne cible de rendu réparée sur place. Neige plus fine.
- Drone, liaison qui faiblit (dès L 3/4) : effets de perte de flux façon TNT (image figée, macroblocs, bandes déchirées, blocs vert/magenta), de plus en plus fréquents jusqu'au NO DATA.
- Drone, viseur photo : viser au glisser ou au clavier, zoom molette / + / -, MARQUER UNE CIBLE, cadres et cercles de surbrillance gravés dans la photo.
- Drone, menus en français clair avec info-bulles, regroupés (CAMÉRA, CIBLES, VOL, AFFICHAGE) ; vue drone de la carte : CAMÉRA DEVANT, OUBLIER LES CIBLES, PETITE FENÊTRE, RETOUR À LA CARTE.
- Drone, envoi de la photo : fenêtre plein écran (grand aperçu, titre, description, SSE, CONFIDENTIEL, ENVOYER À ATHENA), sans défilement.
- Saisie dans un champ du téléphone : les touches ne partent plus au jeu (taper « a » ou « e » faisait pencher le personnage).
- Carte de l'app Plan : les clics posent de nouveau les sommets des zones (le focus resté sur un bouton mettait la zone de contenu devant la carte).

## Correctif 2026-10-12 — ATAK sur le JPC : bon modèle et écran vivant (ATAK natif 1.15.1)

Non testé en jeu. Retour de TTRD sur la 1.15.0 : en mode JPC, la tablette A/B s'affichait au lieu du Samsung S7 choisi, et l'écran restait noir.
- Le terminal ouvert sur le JPC est maintenant celui du profil (Réglages > Personnalisation > Modèle du téléphone) : Samsung S7 par défaut, avec sa teinte de coque, ou Tactique, Premium, Support JPC, Étui multicam, Coque nervurée. La tablette A/B reste disponible. Changer de modèle pendant que l'écran est ouvert le transfère sur le nouveau. Nouveaux modèles 3D `jpc_<modèle>.p3d` (`tools/model_jpc_phones.py`), atlas `jpc_<modèle>_ca.paa` (`tools/gen_assets.py <hemtt> jpc`).
- Écran noir : la carte d'Arma ne se dessine pas dans une texture d'interface. Sur l'écran 3D, la page Carte affiche à la place une vue aérienne en direct, nord en haut, centrée sur le porteur, avec sa flèche et les alliés à pied proches (`fn_jpcMap`). Il faut l'image dans l'image activée (Options vidéo). Hauteur et champ réglables pour les essais : `COMSPEC_ATAK_JpcMapAlt` (300 m), `COMSPEC_ATAK_JpcMapFov` (0.75).
- Écran plus lumineux, de jour comme de nuit.

## Vague 2026-10-12 (suite) — Recherche sur la carte et données remontées au site (ATAK natif 1.15.0)

Non testé en jeu. `run-migrations.php` à lancer (clé de mission, table des pertes). Pas de DLL à recompiler.

### Nouveau — Carte : bouton RECHERCHER
- À côté d'OUTILS (et dans le menu Outils) : type IMEI, NUMÉRO, BALISE GPS ou INDICATIF, saisie ou choix dans la liste des identifiants connus (mon groupe, historique, GÉOLOC, SMS, fichiers extraits au câble DL-7), Entrée pour lancer.
- Téléphone : règles GÉOLOC (camps traçables, son propre camp, IA, réservé GE), requête opérateur de quelques secondes, cercle de précision ; il faut du réseau ; échec motivé : « Téléphone éteint », « Hors couverture » (sans signal, mode avion, brouillage), « Inconnu ».
- Balise GPS (Eden / Zeus d'Overwatch) : position exacte tant qu'elle est alimentée. Indicatif / nom : position du BFT de mon camp.
- La carte se centre sur le résultat : anneau pulsant, fiche (propriétaire, vu à, précision, grille), SUIVRE (balise et BFT toutes les 2 s, téléphone au rythme GÉOLOC), CENTRER, FERMER.

### Nouveau — Batterie et mémoire réglables par l'admin
- Réglages serveur CBA « COMSPEC › ATAK · Batterie et mémoire » :
  - autonomie écran en main de 0,5 à 24 h (3 h par défaut, tous les usages suivent) ;
  - charge au début de la mission ;
  - recharge en véhicule en % par minute (0 = aucune) ;
  - RAM du téléphone de 2 à 12 Go (4 Go par défaut) ;
  - nombre d'apps gardées en arrière-plan (6 par défaut).
- Mémoire pleine : Android ferme les apps d'arrière-plan les plus anciennes pour rester sous 90 %.
- Remplace « Vitesse de décharge » : un serveur qui l'avait changée doit régler l'autonomie à la place.

### Nouveau — Données du jeu exploitées par le site
- Clé de mission (`nom@carte#UTC`) stockée avec les positions, le temps d'écran et les comptes rendus ; onglet Relecture de /atak : sélecteur de mission.
- Pertes amies envoyées au site (un seul téléphone par mort) : points rouges sur la relecture et total dans la synthèse.
- Trajets des drones et véhicules (historique déjà stocké) tracés sur la relecture ; temps de vol approximatif par pilote.
- Pistes et BDA du drone : couche « Contacts vus par drone » sur la carte TOC de /atak (envoi limité : 6 pistes toutes les 5 s, seulement si nouvelles ou déplacées de plus de 50 m).
- Relèvements GE et détections RF de drones : lignes de relèvement sur la carte sigint.

## Vague 2026-10-12 — Anglais en option (ATAK natif 1.14.0, non testé en jeu)

### Nouveau — Langue du téléphone
- Réglage CBA par joueur « Langue du téléphone » : Français (par défaut), English, ou Auto (langue du jeu).
- Le code reste en français ; les textes affichés passent par `comspec_atak_native_fnc_tr` et le dictionnaire `i18n/en.txt` (modèles %1, %2… traduits aussi).
- Textes des apps Plan, Drone, Photo, socle CS-10, tablette du JPC et files d'envoi traduits ; `tools/i18n.py wrap` puis `extract` à relancer après chaque fusion.

---

## Vague 2026-10-12 — Socle de recharge CS-10 (ATAK natif 1.13.0, non testé en jeu)

### Nouveau — Socle de recharge 10 téléphones
- Objet **Socle de recharge CS-10** (`COMSPEC_ATAK_ChargingDock`, éditeur Eden > Objets > Électronique, Zeus) et son kit plié `COMSPEC_ATAK_DockKit` (ACE Équipement > « Poser le socle de recharge » : devant soi, sur une table ou au sol).
- Modèle 3D : socle anodisé sur deux gradins, berceaux et ailettes en caoutchouc, poignées, prise secteur ; les téléphones posés sont notre Samsung S7 en coque tactique (couleur de coque du propriétaire), écran de charge avec le niveau, voyant par emplacement (rouge, orange, vert) lumineux la nuit.
- ACE sur le socle : « Poser mon téléphone » (charge et état de l'appareil gardés), « Reprendre mon téléphone · 04 · 87 % », « État du socle », « Replier le socle » (vide). Sans ACE : actions molette en regardant le socle.
- Serveur : seul le propriétaire (identifiant Steam) reprend son téléphone, Zeus et l'admin toujours. Les emplacements sont mémorisés dans le profil du serveur : un téléphone laissé se retrouve à la partie suivante, chargé du temps écoulé ; les socles posés avec le kit sont recréés à leur place sur la même carte. Attribut Eden « Identifiant du socle » pour retrouver les mêmes téléphones d'une mission à l'autre.
- Réglages serveur **COMSPEC > ATAK · Socle de recharge** : vitesse de charge (1,5 % par minute), charge entre deux parties, mémoriser les socles posés, tout le monde peut reprendre n'importe quel téléphone.

---

## Vague 2026-10-12 — Tablette durcie et ATAK ouvert sur le JPC (ATAK natif 1.12.0, non testé en jeu)

### Nouveau — Tablette durcie à touches (demande de TTRD)
- Nouveau modèle de terminal « TABLETTE A/B » (Réglages > Personnalisation > Modèle du téléphone) : tablette durcie gris sable, mini-joystick de chaque côté, touches retour / accueil / apps récentes / fn à gauche, A / B / X / Y à droite, patte de fixation en haut, charnière au dos. Rendus 3D Blender (`tools/render_phones.py`, appareil `keys`), coques jour et nuit, dos (« Voir l'arrière ») et poussière de coque.

### Nouveau — ATAK ouvert sur le JPC, en 3D (demande de TTRD)
- Réglage « Terminal porté » : MINIATURE (comme avant) ou SUR LE JPC. Sur le JPC, la touche « porter » (Ctrl+U) relève la tablette du rabat du porte-plaques vers le visage et l'interface ATAK s'affiche en direct sur son écran 3D (texture UI). Rien n'est affiché à l'écran du joueur : vue libre, déplacement, arme et menu d'actions restent disponibles, on baisse les yeux pour lire.
- La touche principale prend toujours le terminal en main (plein écran, souris) ; le reposer rouvre l'écran sur le JPC. Ctrl+U à nouveau : la tablette se rabat.
- ACE « Téléphone ATAK > Ouvrir l'ATAK sur le JPC » / « Rabattre la tablette du JPC », et raccourci « Tablette sur le JPC » (sans touche par défaut).
- Les autres joueurs voient la tablette ouverte sur le gilet avec un écran ATAK figé ; rabattue, ils voient son dos. Sans gilet, en véhicule ou sans terminal : retour automatique à la miniature. Le téléphone du gilet n'est pas accroché en plus.
- Écran rafraîchi 6 fois par seconde, seulement s'il est visible et à moins de 6 m de la caméra ; un seul display par ouverture. Écran émissif plus doux la nuit.
- Réglages fins pour les essais (missionNamespace) : `COMSPEC_ATAK_JpcOffset` (position sur le torse), `COMSPEC_ATAK_JpcTilt` (inclinaison, 50° par défaut), `COMSPEC_ATAK_JpcRate` (images par seconde).
- Modèle `data/jpc_tablet.p3d` (`tools/model_jpc_tablet.py`), atlas `jpc_tablet_co.paa`, écran figé `jpc_screen_static_co.paa`, matériaux `jpc_screen*.rvmat` (`tools/gen_assets.py <hemtt> jpc`).

### Nouveau — Trois coques de référence (demande de TTRD)
- Trois nouveaux modèles dans Réglages > Personnalisation > Modèle du téléphone, à côté du S7 qui reste le modèle par défaut : SUPPORT JPC (coque sable sur platine de gilet, équerres noires aux coins, velcro central, patte crantée avec levier, triple objectif), ÉTUI MULTICAM (étui à rabat camouflage, charnières en haut et en bas, velcro en L, îlot double objectif) et COQUE NERVURÉE (coque fine vert-gris, nervures verticales, îlot triple objectif vertical). Géométrie modélisée par nos soins d'après les photos, sans logo de marque. Coques jour et nuit, dos et poussière.
- Tablette A/B : dos simplifié, caméra déplacée dans le coin façon S7, velcro au centre, mentions MIL-STD-810H et étiquette réglementaire.

## Vague 2026-10-12 — Retours de test en jeu (ATAK natif 1.11.0)

Non testé en jeu. Inclut les correctifs de performance de la PR #629 (même base). DLL à recompiler (`build_mod.bat`) pour le presse-papiers du Plan.

### Nouveau — App Plan (carte de briefing)
- Carte fixe nord en haut, indépendante du joueur et de la caméra ISR ; le plan est stocké en coordonnées monde, identique pour tous.
- Zones ENY, INFIL, EXFIL, OBJ, AO, NAI, EA, AA, HLZ, NFA ; lignes PL, LD, LOA, limite, itinéraire, axe d'effort, attaques principale et secondaire, stylo ; points CLEARED n / UNCLEARED n, unités amie/ennemie, note, CP, RP, TRP, OBJ, SBF, ABF.
- Stylo lissé ou segments clic par clic, pointillés, flèche dans le sens du tracé, épaisseur, couleur, étiquettes.
- Plan partagé par camp (serveur seul écrivain, JIP) ; droit d'édition par réglage CBA `comspec_atak_native_plan_edit` (tous / chefs de groupe + Zeus + admin par défaut / Zeus + admin).
- Plans nommés (aperçu, chargement, publication en ajout ou remplacement), export/import en texte `COMSPECPLAN1|…`. Onglet CARTE dans Briefing.

### Amélioré — Dessin libre et carte
- Unités : cadres OTAN par défaut, cartouche sombre avec l'indicatif puis « distance · azimut » ; contacts ennemis (losange rouge) avec l'heure et l'auteur du signalement ; boussole « 42° NE » ; ligne curseur CURSEUR / MGRS / ALT. Réglages > Carte > « Cartouches des unités » (complet, indicatif seul, aucun). Allègement automatique quand les cartouches se chevauchent ou au dézoom.
- Traits lissés et allégés, barre couleur / épaisseur / flèche / forme fermée / Défaire / Effacer mes traits ; épaisseur et flèche visibles par tous.
- Les dessins libres arrivent sur la carte Overwatch beta (le point provisoire est remplacé par le tracé ; décalage de carte appliqué aux polylignes).
- Clic droit : menu redessiné sans redessiner la page (en-tête grille, altitude, distance, azimut ; Y aller, Centrer ici, Copier la grille).
- Le léger voile noir des transitions de page est désactivé par défaut (Réglages > Animations et sons > « Transitions entre les pages »).

### Refonte — App Drone
- Onglets VOL / TÂCHES / CIBLES / PHOTOS / JOURNAL ; barre de boutons par groupes (Caméra, Cibles, Vol, Affichage) avec libellés clairs ; « Pistes » devient CIBLES (cibles marquées + détections automatiques).
- Suivi des cibles marquées : une ou plusieurs, dans l'ordre choisi, en filature ou en orbite, temps par cible réglable.
- Clichés photo depuis le drone, avec formulaire avant envoi : titre, description, SSE oui/non, confidentiel oui/non.
- La caméra ne voit plus le drone lui-même ; qualité du flux Basse / Moyenne / Haute ; nacelle plus douce.
- Mode petit : vue totale de la caméra ; plus d'écran noir à la réouverture ; neige grise « NO DATA » sans flux.
- Source du flux : drone, caméra ATAK ou caméra casque (soi ou un membre du groupe).
- Marquage corrigé sur les véhicules (« Rien sous le réticule »).

### Refonte — App Photo
- Onglets APPAREIL / GALERIE / ENVOIS, groupes de boutons libellés, état de la liaison Athena.
- Galerie filtrable (téléphone / drone) avec titres, étiquettes SSE et CONFIDENTIEL, statut d'envoi et page de détail (modifier, envoyer, supprimer).
- « Envoyer les nouvelles » n'envoie que les photos absentes d'Athena ; viseur : ligne de destination (direct ou hors réseau) et légende.

### Nouveau — Fiche des clichés drone jusqu'au site (COMSPEC Link 2.0.66)
- Titre, description, SSE (avec dossier), confidentiel, source et nom du drone partent en vrais champs par NotifyNewPhoto, avec la position et le cap du drone ; chaque envoi apparaît dans « Files d'envoi Athena » comme « Photo drone ».
- Site : champs stockés (migration `atak_recon_images_photo_meta`, lancée par run-migrations.php), affichés dans les photos Overwatch beta, la galerie caméras et la liste recon (badges SSE avec lien vers le dossier et CONFIDENTIEL) ; photos confidentielles masquées sans habilitation et jamais relayées sur Discord.
- DLL COMSPEC Link à recompiler (`build_mod.bat`) ; avec une DLL plus ancienne, les champs repartent dans la légende.

### Corrigé / amélioré — Interface
- Briefing : texte géant (« ||0.8% ») des fiches du journal de mission ramené à taille normale.
- Réseau : la page s'ouvre de nouveau.
- Écran de verrouillage redessiné (heure, puces d'état, cartes de notification avec icône).
- Applications V2 : logo du fond qui chevauchait la première section retiré, en-têtes de section, recherche.
- SSE : bouton « Fiche rapide » (FRM, FRC, FRO, FRT) qui ouvre une FRS préremplie.
- Photo : la souris vise de nouveau en zoom, flash moins violent (rendu délavé), cadres de visée retirés du cliché, interface revue.
- Mode porté : pastille main + « Ctrl+Maj+U » au lieu de la phrase, masquable.
- Personnalisation : style d'horloge, thèmes rapides (Opérations, Nuit, Désert, Athena), interrupteurs pastille et recherche.

## Vague 2026-10-12 — Service Athéna au premier lancement (ATAK natif 1.10.0, Overwatch connect)

### Nouveau — « Votre unité utilise-t-elle le service Athéna ? »
- Au premier lancement (menu principal, ou en mission si le joueur rejoint directement un serveur), un écran en français et en anglais (boutons FR / EN, langue du jeu par défaut) pose la question avec deux cartes **OUI** et **NON**. Il faut répondre (Échap bloqué la première fois).
- **Non** : l'ATAK reste en local (carte, BFT, messages et apps entre joueurs du serveur). Aucune connexion à Athena, aucune synchro, aucune note bêta ni inscription envoyée ; Overwatch est coupé (`comspec_overwatch_enabled` forcé à faux même si un réglage CBA le rallume). Les apps Athena, Synchro, Discord et Recherchés disparaissent du lanceur et du dock.
- **Oui** : comportement habituel (COMSPEC Link, restauration de la session, synchro). Sans session, la fenêtre Connexion Athena s'ouvre pour lier le compte tout de suite.
- Le choix est enregistré dans le profil Arma (`COMSPEC_AthenaMode` = `on` / `off`) et se change à tout moment : téléphone ATAK > Réglages > **Service Athéna** (Oui / Non, revoir la question, lier mon compte) ou menu Échap > bouton **Service Athéna**. Passer à Non garde la clé enregistrée : revenir à Oui ne demande pas de relier le compte.

---

## Vague 2026-10-11 (suite) — Un téléphone qui réagit (ATAK natif 1.9.0)

### Nouveau — Mouvement, toasts, volet rapide, écran de verrouillage
- Onde verte sous le doigt à chaque appui sur l'écran, clic doux et légère vibration de la coque (pas de son en mode discrétion ; rien après un glissé de carte ou de curseur).
- Une app s'ouvre en grandissant depuis l'icône touchée (0,2 s) ; l'accueil revient en fondu ; au retour, la page quittée glisse vers la droite.
- Toasts Android : petite bulle en bas de l'écran qui s'efface seule (`[texte, durée] call comspec_atak_native_fnc_toast`), utilisée pour les confirmations brèves (code de verrouillage et patch enregistrés, numéro ou journal copiés, marqueur enregistré, étape d'itinéraire ajoutée, raccourcis mode nuit / discrétion).
- Volet rapide tiré de la barre d'état (clic sur l'heure ou la batterie, petite poignée sous l'heure) : curseur de luminosité, mode nuit, mode avion, silencieux, vibration, Bluetooth, balise IR, état du réseau et batterie (ouvrent les apps Réseau et Statut), accès à tous les réglages. Il reprend les réglages existants (mode nuit, avion, discrétion, vibration, Bluetooth, balise IR).
- Luminosité de l'écran (nouveau réglage, aussi dans Réglages) : voile sombre, lueur de l'écran vue par les autres réduite, batterie un peu ménagée.
- Écran de verrouillage : grande heure, date, carte et température, batterie et réseau, trois dernières notifications ; à la première sortie en main de la mission, après une minute rangé ou après un redémarrage, une batterie vide ou un écran détruit. Clic, glissé vers le haut ou touche accueil : l'écran remonte.
- Réglages > Personnalisation > « Animations et sons » : animations, clic et vibration des appuis, écran de verrouillage, luminosité (chacun désactivable).

### Nouveau — Caméras IP fixes (demande de TTRD)
- Item « Caméra IP compacte (ATAK) » (`COMSPEC_ATAK_Camera`, arsenal et éditeur). ACE « Téléphone ATAK > Caméras > Poser une caméra » : visez à moins de 3 m un mur, le sol, un objet ou un véhicule (elle y reste accrochée et le suit) ; nommée CAM-1, CAM-2… L'objet est visible de tous et peut être détruit (tirs, explosion, véhicule détruit).
- ACE sur la caméra posée : « Récupérer la caméra » (rend l'item), « Renommer » (ouvre le téléphone sur la caméra), « Orienter » à gauche / à droite de 45° ; une caméra détruite se retire sans rendre l'item.
- App Vidéo live : section « Caméras fixes » sous les porteurs (nom, distance, état : en ligne, hors liaison, brouillée, pas de réseau, détruite). Même image que le Live cam (jour, JVN, thermique), panoramique local ◀ ▶ de 30°, bouton Renommer. Liaison : il faut du réseau sur son téléphone et un ATAK du camp à moins de 600 m de la caméra (`comspec_atak_native_camera_range`) ; un brouilleur de mission la coupe.

### Nouveau — Carte : symboles OTAN et menu du clic droit (demandes de TTRD)
- Réglages > Carte > « Icônes des unités » : POINTS (actuel) ou OTAN MIL-STD (MIL-STD-2525 / APP-6, textures OTAN d'Arma). En OTAN, chaque unité amie (carte, mini-carte, liste BFT) a son cadre bleu et sa fonction : véhicule (drone, avion, hélicoptère, navire, char, VBTT / mécanisé, artillerie, ambulance, véhicule motorisé, mortier ou arme fixe), sinon rôle (sanitaire via ACE / trait / rôle SAN, PC pour les fonctions de commandement, reco pour tireur d'élite / éclaireur, mortier, télépilote, sapeur / EOD) puis infanterie. Les chefs ont leur échelon au-dessus du cadre (équipe, groupe, section). Indicatifs, couleurs et ma propre icône inchangés ; symbole recalculé toutes les 4 s par unité.
- Clic droit sur la carte (sans glisser : le glisser droit déplace toujours la carte) : petit menu au curseur avec les coordonnées du point en tête. PING (cercles qui s'élargissent et indicatif pendant 10 s sur l'ATAK de tout mon camp, bandeau et vibration chez les autres ; Ctrl + clic envoie aussi ce ping partagé), METTRE UN MARQUEUR (formulaire de nouveau marqueur à cet endroit), SUPPRIMER (seulement sur un marqueur posé par un joueur). Fermeture : clic ailleurs, Échap ou choix d'une ligne.

### Nouveau — BFT en mode ORBAT et filtres
- App BFT : bouton « VUE : LISTE / ORBAT ». La vue ORBAT range les pistes en arbre repliable : formation (rattachement ORBAT Athena abrégé, sinon préfixe de l'indicatif : « Star 4 » → STAR) › groupe (échelon, effectif, chef marqué, distance au chef, « mon groupe ») › équipe de feu (nom et couleur des équipes) › membres (état, icône, véhicule à bord, distance). Clic sur un en-tête : replier / déplier (mémorisé) ; clic sur un membre : fiche à droite, CARTE / Y ALLER / SMS / VIBRER inchangés.
- Bouton « FILTRES » (tiroir sous l'en-tête, nombre de filtres actifs) : masquer les IA, les aériens, les véhicules (l'équipage reste dans son groupe), les pistes anciennes et perdues, les pistes hors ligne ; alliés suivis tous / mon groupe / mon unité ; distance max tout / 1 / 3 / 10 km. Réglages du profil, appliqués au BFT et à la carte (`comspec_atak_native_fnc_bftFilter`).
- Filtre VÉHICULES du BFT : compte aussi les équipiers à bord d'un véhicule.

### Nouveau — App Réseau en schéma, frappe drone guidée, caméra du drone à la taille de l'écran (demandes de TTRD)
- App Réseau refaite : chemin de la liaison en schéma (Téléphone → porteur → COMSPEC Link → Athena), chaque étape en carte avec sa couleur d'état, sa distance et ses barres de signal, le porteur utilisé marqué ACTIF, la coupure en rouge avec sa raison (mode avion, sous-sol, aucun porteur à portée, Athena hors ligne…). Tuiles d'indicateurs (jauge de stabilité, latence, pertes, paquets, âge de la dernière position et de la dernière réponse Athena). Liste des passerelles de véhicule, postes de commandement et relais radio proches avec distance, portée, signal, badge CONNECTÉ sur celui utilisé et la raison pour les autres (hors portée, relief masquant, détruit, moins bon débit). Zone radio, débit, facteurs, état du téléphone et tous les boutons gardés ; rafraîchie toutes les 5 s, lisible en portrait et en miniature.
- Drone, frappe : état clair (non armé / armé avec la charge), armement en trois étapes (distance au drone en direct ✓/✗, choix de la charge parmi celles de l'inventaire, ARMER), frappes expliquées (piquer sur le laser, sur une grille, depuis les pistes), nouveau DÉSARMER (charge rendue à l'inventaire), recherche et frappe expliquée selon l'armement.
- Drone, caméra : la vue tient dans l'écran sans défilement (image 16/9 ajustée, incrustations sur l'image, une barre de commandes, mode LISTE pour garder les onglets dessous). Nacelle pilotée à la souris (glisser : cap et site −90° à +20°, lissée et stabilisée), molette = zoom continu jusqu'à x8, flèches / ZQSD au survol, CENTRER, NADIR ; angles affichés ; clic = viser le point, et en mode MARQ. le marquer ; altitude ± et stationnaire dans la barre. Caméra posée sous le fuselage des petits drones (Mavic) : plus de bras ni d'hélice dans l'image (réglable par mission : COMSPEC_ATAK_DroneCamOffsets).

### Corrigé
- Réglages (et toutes les pages redessinées sur place) : un clic ne fait plus remonter la page en haut ; le défilement n'est remis à zéro qu'en changeant de page.
- BFT : « Star 4-1 » n'apparaît plus deux fois. La piste Athena et l'unité en jeu au même indicatif sont fusionnées même quand la position Athena est en retard de plus de 30 m ; des IA d'un même groupe sans indicatif propre sont distinguées par leur rôle.

---

## Vague 2026-10-11 (retours de test) — ATAK natif 1.8.1, SEEK, Overwatch embarqué

### Corrigé — Retours de test en jeu
- Poussière de l'écran beaucoup plus lente (couche complète en ~2 h debout, ~20 min couché sur du sable) et voile plus léger au début.
- « Nettoyer l'écran » nettoie vraiment (poussière, gouttes et traces partent, le sang demande deux passages) et l'écran se met à jour tout de suite.
- La saleté touche aussi la coque (poussière plus dense contre les arêtes, pour chaque modèle) ; le sang de la coque reste sur le rebord au lieu de flotter hors du téléphone.
- Pixels morts bien visibles : amas de pixels, taches noires de dalle morte, lignes verticales et horizontales plus épaisses.
- Carte : la carte « moi » ne passe plus sous la barre d'outils ; panneau du curseur réduit à deux lignes.
- Réglages : interrupteurs bien lisibles (libellé blanc, fond plus clair) ; bouton « Redémarrer le téléphone » à côté de « Nettoyer l'écran ».
- App Réseau : la page plantait (ligne 95) avec une batterie endommagée et masquait le bouton REDÉMARRER.
- Extraction DL-7 : formulaires Zeus des modules « ATAK · Données » qui plantaient (erreur `max`, deux endroits), module Zeus posé sur un objet reconnu sous la souris, action ACE « Brancher l'ATAK » sur un corps ou un prisonnier rangée sous les actions principales (atteignable), action à 3 m sur les objets, « Marquer sur la carte » au bon carré, réglages « forçage » et « usage unique » acceptés en nombre ou en texte.
- Liaison allié : écran d'accueil refait (état, trois étapes courtes, équipiers proches avec leur distance, RELIER à moins de 2 m).
- Dos du téléphone : dos simple de S7 dans sa coque (plus de plaque de fixation).
- Le « durci XT » devient le modèle « tactique » : coque EUD vert ranger à cadre fin, rail de fixation à ailettes et molette, dos à pad et double objectif.
- Patch au dos : en plus de nos patchs, l'insigne porté sur l'uniforme, ou n'importe quel insigne d'unité des mods chargés et de la mission (CfgUnitInsignia : RHS, 3CB, unités…), choisi dans Réglages avec l'aperçu ; visible au dos et sur le téléphone porté au gilet.
- Réglages : les insignes ne se parcourent plus un par un (chaque clic renvoyait en haut de la page et les flèches ◀ ▶ étaient vides) ; « Choisir un insigne » ouvre la page « Patch au dos » : recherche par nom ou classe (sans accents ni majuscules), sources Tous / Nos patchs / Mods / Mission, vignettes 4 × 3 avec le nom et le patch porté en surbrillance, pages PRÉC. / SUIV. ; un clic met le patch tout de suite sans bouger la page (recherche, source et page gardées, le champ garde son texte pendant la frappe).
- Téléphone lâché au sol : notre modèle 3D de la coque (couleur choisie) posé à plat, écran vers le haut, au lieu du petit objet générique.
- Liaison Athena : plus de spam connexion / déconnexion avec son (état tenu quelques secondes avant de changer, sons de perte et de retour limités à un toutes les 30 s, passerelles véhicule comptées comme relais).
- Alerte médicale Overwatch : erreur `!` (type Tableau) corrigée.
- SEEK : relevés empreintes, iris et ADN enregistrés avec le terminal seul (hors liaison aussi) et rattachés à la personne ; photo d'un sujet au sol cadrée au-dessus du visage ; textes de la page Terrain et de la barre d'état raccourcis.

### Nouveau — App Accessoires (établi)
- App « Accessoires » (Système) : un établi visuel au lieu d'une liste. Le dos du téléphone (modèle et coque choisis, antenne et batterie externe montées) au centre, six emplacements reliés à leur point de fixation par un trait fin : antenne (port SMA), balise IR, batterie de rechange, module DL-7, filtre d'écran JVN, batterie externe (plaque). Chaque emplacement montre l'objet monté, son état (débit, charge %, allumée…) ou « vide » ; un toucher ouvre un tiroir avec les objets compatibles de l'inventaire (FIXER / REMPLACER), RETIRER, et les actions propres (allumer la balise IR, changer la batterie comme l'action ACE, ouvrir l'Extraction ou débrancher le câble DL-7).

---

## Vague 2026-10-11 (suite) — Extraction de données au câble (ATAK natif 1.8.0)

### Nouveau — Personnalisation, dos du téléphone, antennes, menu ACE rangé

- Couleur de la coque : coyote, vert OD ou noir ; couleur du module DL-7 : olive, noir ou tan (app Réglages > Personnalisation, ou ACE « Téléphone ATAK > Personnaliser »). La coque de nuit reste la silhouette sombre.
- Module DL-7 et dos du téléphone en rendu 3D réaliste (métal peint, butées caoutchouc, vis, plaque aluminium anodisé, objectif, étiquette).
- Patchs velcro au dos : France, France basse visibilité, USA, USA basse visibilité, crâne, croix, alien, COMSPEC, nominatif (nom du joueur ou texte choisi). Réglages ou ACE « Personnaliser > Patch au dos ».
- Téléphone visible sur le gilet (poitrine gauche, centre ou droite, épaisseur réglable), accroché au torse sur tous les gilets et vu par les autres joueurs ; masqué pour soi en première personne. Notre propre modèle 3D de la coque, à la couleur choisie, dos et patch vers l'extérieur ou écran vers l'extérieur.
- Modèles de téléphone au choix : durci S7 (coque actuelle), durci XT (téléphone de chantier à butées) et premium (téléphone fin à cadre métal), sans marque. Même écran pour tous.
- Accessoires (nouveaux objets) :
  - batterie externe 10 000 mAh fixée sur la plaque du dos : recharge le téléphone à 2 % par minute, deux charges complètes, visible au dos ;
  - balise IR de la coque : clignotant infrarouge vu aux JVN par tous (identification ami), alimentée par le téléphone ;
  - filtre d'écran JVN : de nuit, un écran allumé éclaire son porteur et se voit de loin (encore plus aux JVN) ; le filtre réduit presque toute la lueur. Mode nuit et véhicule la réduisent aussi.
- Sécurité en cas de capture : menotté, inconscient ou mort, votre téléphone devient extractible au câble DL-7 par l'ennemi (messages, marqueurs, comptes rendus, position). Code de verrouillage dans les Réglages (3 essais puis effacement, forçable en 2 min). **Effacement d'urgence** (ACE ou Réglages, double confirmation) : plus rien d'exploitable jusqu'à la resynchronisation.

### Nouveau — Tablette des véhicules, passerelles et poste de commandement mobile

- Tablette durcie de bord : conducteur, tireur, chef de bord et tourelles d'un véhicule terrestre ou d'un bateau ont l'ATAK sans téléphone porté (réglage serveur : tous les véhicules, ceux équipés du « Kit terminal véhicule », ou aucun). La coque devient une tablette.
- Passerelle du véhicule (ACE, équipage à bord ou à côté) : le véhicule devient relais du réseau de son camp, 4 km, 6 Mbit/s, même en roulant.
- Poste de commandement mobile : véhicule à l'arrêt, mât dressé sur le toit, 10 km et 20 Mbit/s ; tablette du PC pour tous à bord et à moins de 8 m. Le véhicule qui repart replie le mât.
- Les téléphones à portée y prennent leur débit, même sans relais Overwatch ; le maillage Wave Relay s'y raccroche et la carte montre la portée.
- « Voir l'arrière » : le téléphone retourné au centre de l'écran (plaque de fixation, appareil photo, étiquette avec l'IMEI, antenne vissée). Clic ou touche pour fermer.
- Antennes d'extension de débit, nouveaux objets d'inventaire vissés sur le port SMA de la coque et dessinés autour du téléphone :
  - courte SMA : débit x1,25, liaison un peu plus tenace ;
  - fouet longue : débit x1,5, nettement plus tenace (relief, bâtiments, brouillage) ;
  - patch 9 dBi : débit x1,7 et meilleure tenue, à l'arrêt seulement (mal orientée en mouvement ou en véhicule).
  Une antenne externe contourne l'antenne interne endommagée. Sans aucun signal (sous-sol, sous l'eau), rien ne change.
- Menu ACE : tout est sous une seule entrée « Téléphone ATAK » (Voir l'arrière, Antenne, Personnaliser, Accessoires, Entretien, Passerelle du véhicule, Effacement d'urgence, Débrancher le câble DL-7). Les actions Overwatch « Rallumer », « Réparer écran » et « Diagnostic » sont reprises dans Entretien et retirées de « Équipement ».

### Nouveau — App Extraction et câble DL-7

Regarder un ordinateur, un téléphone, une clé ou n'importe quel objet porteur de données, « Brancher l'ATAK » : un câble sort du connecteur de la coque vers un module **COMSPEC DL-7** dessiné à côté du téléphone (écran LCD et voyants PWR / AUTH / DATA animés). L'app **Extraction** affiche l'écran du support à son thème, demande le mot de passe (essais comptés, verrou, effacement ou alarme) ou force l'accès, puis télécharge les fichiers choisis avec barre de transfert. Câble arraché si l'on s'éloigne : les fichiers déjà reçus restent. Fichiers lisibles dans l'app (texte, identifiants, image, coordonnées sur la carte, audio), partage au groupe. Objet « Câble de données DL-7 », obligatoire en réglage serveur.

### Nouveau — Modules Zeus et Eden « ATAK · Données »

**Données à extraire**, **Ajouter un fichier**, **Remettre / retirer les données** (Zeus). Sans objet visé, le module crée le support (portable, PC, serveur, téléphone, tablette, clé USB, radio). Code serveur et événement CBA `comspec_atak_native_dataDownloaded` en fin de téléchargement ; `comspec_atak_native_fnc_dataSetup` pour les scripts. Non testé en jeu.

---

## Vague 2026-10-11 — Marquage au viseur, vue drone FLIR, Overwatch beta

### Nouveau — Marquage au viseur (ATAK natif 1.7.0)

Réticule de la caméra du drone ou de l'appareil photo du téléphone sur une cible assez visible, un appui (**MARQUER**, **T** en mode photo, ou la touche CBA « Marquer la cible ») : la cible devient **LOCK_0xx**, un marqueur est posé sur la carte et la suit tant qu'elle est vue. Sortie du champ, masquée ou drone hors liaison : cible perdue après un délai, puis traitée selon les réglages serveur **COMSPEC > ATAK · Marquage au viseur** (calque retiré et marqueur gardé, marqueur effacé après délai, tout effacé, ou tout gardé). Raccrochage automatique quand elle revient. Clic sur l'image du drone : la nacelle vise ce point.

### Nouveau — Vue drone façon nacelle (FLIR)

Bouton drone de la carte (ou touche « Vue drone sur la carte ») : la vue du drone remplace la carte ou s'incruste dans un coin, aussi téléphone en miniature. Thermique à la première ouverture, réticule, coins de cadrage, cap, altitude, batterie, grille du drone et du point visé, distance, heure, cadres LOCK sur les cibles marquées.

### Corrigé — Overwatch beta

Paniques et alertes supprimables pour tous les postes (liste, carte, tchat), balises retirables. Acquittement immédiat à l'écran. « Acquittée par X à HH:MM », reçu / pas reçu par terminal (déduit du dernier signal du téléphone) et postes Athena qui l'ont vue. Le panneau Drones / Charges / Mon ATAK se ferme enfin (barre repliable, Échap) ; le drone affiche batterie, état et charges emportées (mod connect à jour).

---

## Vague 2026-10-11 — Photo du visage SEEK / ATAK natif 1.6.1 / Overwatch 1.6.16

### Nouveau — Photo du visage cadrée (SEEK et app SSE de l'ATAK)

Page **PHOTO** du terminal SEEK : viseur en direct sur la tête du sujet (face, trois quarts, profil, zoom), qualité estimée (nuit, visage masqué, sujet au sol), flash de nuit, cliché figé à l'écran. L'app **SSE** de l'ATAK prend aussi la photo du visage du sujet visé (4 m au plus) ; elle part avec la fiche SEEK. Le site recadre la capture en portrait 3:4 et garde la capture brute. Capture d'écran Arma : qualité HDR au moins Moyen. Non testé en jeu.

### Amélioré — Terminal SEEK

Appareil affiché sans déformation et cadré sur l'écran et le clavier (écran ~1,7 fois plus grand), écran calé dans le verre, nouvelles PAA (verre LCD, tuiles d'accueil illustrées, gabarit du viseur), touches A1 / A2 / QUERY / SIGN posées sur les vraies touches de l'appareil.

---

## Vague 2026-10-01 — Fieldwatch RF hits (Lot 1)

### Nouveau — Fieldwatch (veille RF simulée)

Inspiration Fieldwatch (scan Wi‑Fi / BLE passif) adaptée au milsim : Zeus / Eden pose des **émetteurs RF**, l’app téléphone **Fieldwatch** scanne à proximité et remonte les hits au poste (`POST /api/atak/rf-hits`). Overwatch Beta affiche le calque **Veille RF** ; l’onglet Identification ATAK liste les détections. Rebuild mod + DLL requis (`SendRfHit`). Doc : `docs/technique/fieldwatch-rf-hits-lot1.md`.

## Vague 2026-09-22 — Overwatch Beta / Athena

### Nouveau — Surveillance automatique dans Support

Dans **Support**, le poste affiche tout seul les incidents techniques : coupure brutale suspecte, gel de téléphone, versions Overwatch différentes, pack de développement, joueur en avance (parc en retard) ou joueur trop ancien. Les signalements manuels restent disponibles en dessous. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Espace Réseau au poste

Sur Overwatch Beta, **Réseau** dans la barre du haut liste les relais posés en jeu (état, portée, places, débit), les terminaux ATAK déjà remontés (certificat, dernière activité) et les satellites lorsqu’un catalogue est fourni. Un clic sur un relais recentre la carte ; un clic sur un terminal ouvre le contact lié. Rechargez Overwatch Beta (Ctrl+F5).

## Vague 2026-09-20 — Overwatch 1.6.9 / SSE 0.7.22 / Athena 1.0.165

### Nouveau — Notes de reconnaissance

Un opérateur note ce qu’il voit depuis l’application **Reco** du téléphone : une ligne de texte, un type (véhicule, groupe armé, position statique, obstacle ou mine, civil, infrastructure, autre) et la confiance (vu direct ou rapporté). Le point se place sous le regard. Toute l’équipe le voit en jeu ; au poste, le calque **Notes reco** et la liste à droite le montrent. Après vingt minutes le point pâlit, après quarante minutes il est presque gris. Relancez Arma complètement (Overwatch 1.6.9 · Athena 1.0.165).

## Vague 2026-09-20 — Overwatch 1.6.8 / SSE 0.7.22 / Athena 1.0.164

### Nouveau — Bâtiments en volume sur la carte du poste

Le relevé du théâtre alimente maintenant la carte du poste : chaque bâtiment apparaît comme un volume, avec la hauteur scannée. Plus besoin d’une carte plate pour juger un îlot. La couche **Bâtiments** se coupe depuis la barre de calques.

### Nouveau — Couches dans le téléphone

Sur la carte du téléphone, **Couches** ouvre une liste à cocher : contacts ennemis, alliés suivis, relais, zones réseau, rapports SIGINT. Le choix est local, il ne dépend plus d’un module posé au lancement. Il est mémorisé d’une session à l’autre.

### Nouveau — Zones réseau sur la carte

Les zones de brouillage, d’interférences, de couverture dégradée ou d’absence de signal se lisent en ombre sur la carte du poste et du téléphone. L’opacité suit l’intensité. On peut les éviter au lieu de les découvrir en perdant la liaison.

### Nouveau — Alerte de proximité IFF

Quand un contact non identifié entre dans le rayon, le téléphone vibre une fois et affiche la distance. L’alerte reste discrète et ne se répète pas tant que le contact reste à proximité. Relancez Arma complètement (Overwatch 1.6.8 · Athena 1.0.164).

## Vague 2026-09-20 — Overwatch 1.6.7 / SSE 0.7.22 / Athena 1.0.163

### Nouveau — Barres de signal Relais

Les barres de signal en haut du téléphone suivent le mât Relais le plus proche. Elles se vident hors de portée, passent au rouge si le mât est détruit ou si la zone est brouillée, et jaunissent dans une zone dégradée. La fiche Relais AT affiche le même état.

### Nouveau — Étages depuis le téléphone

Quand un bâtiment est désigné, une fiche apparaît sur la carte du téléphone : nom, étage, curseur. Changer d’étage se fait sans rouvrir le menu ACE. Le badge en jeu suit.

### Nouveau — Journal des identifications

Dans BII-10, chaque interrogation SEEK reste listée : sujet, statut, confiance, heure et grille. L’équipe du groupe voit le même journal.

### Nouveau — Messages en attente de synchro

Hors couverture, un bandeau indique combien de messages attendent encore d’être envoyés. Dès que la liaison revient, le libellé passe à « en cours de synchro ». Relancez Arma complètement (Overwatch 1.6.7 · Athena 1.0.163).

## Vague 2026-09-20 — Overwatch 1.6.6 / SSE 0.7.22 / Athena 1.0.162

### Correction — Transférer les vues de l’album

Dans Photo Library, **Transférer** et **Tout transférer** envoient maintenant les vues réellement présentes dans l’album du téléphone. Le dépannage de liaison prenait déjà une vue neuve et la trouvait ; les boutons de l’album retrouvent aussi les vues enregistrées avec ATAK Enhanced. Relancez Arma complètement (Athena 1.0.162).

## Vague 2026-09-20 — Overwatch 1.6.6 / SSE 0.7.22 / Athena 1.0.161

### Amélioration — Repères jeu vers le poste

Un point posé sur la carte d’Arma 3 arrive au poste avec le poseur, le canal et l’heure. Au survol, on lit qui l’a posé et à quelle heure. Un point sans nom s’affiche « Repère · » suivi du poseur. Un déplacement ou une suppression en jeu se voit tout de suite au poste.

### Nouveau — Repère du poste sur la carte Arma

Un point posé depuis Overwatch Beta apparaît maintenant sur la carte d’Arma 3, comme un vrai repère d’opérateur. Toute l’équipe le voit. S’il n’a pas de nom, il s’affiche « Repère poste ».

### Nouveau — Mât Relais sur la carte

Le mât Relais le plus proche se lit sur la carte : une pastille et un cercle de portée (vert à portée, jaune hors portée, rouge si le mât est détruit). En quittant la zone, un avis prévient. Relancez Arma complètement (Overwatch 1.6.6).

## Vague 2026-09-20 — Overwatch 1.6.5 / SSE 0.7.22 / Athena 1.0.160

### Correction — Repère carte Arma absent du poste

Un point posé sur la carte d’Arma 3 (canal global) apparaît maintenant sur la carte du poste, au même endroit. Fermez la carte en jeu : le repère remonte en quelques secondes, même s’il n’a pas de nom. Relancez Arma complètement (Overwatch 1.6.5).

### Correction — Journal de liaison trop bavard

Le journal n’écrit plus la même ligne vide toutes les quelques secondes. Une lecture inchangée (aucun message, aucune forme, aucun ordre) reste silencieuse.

## Vague 2026-09-20 — Overwatch 1.6.4 / SSE 0.7.22 / Athena 1.0.159

Changelog Steam (copier-coller) : [Overwatch 1.6.3](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.3.md).

### Correction — P2P réseau local vide

Dans le menu d’applications, **P2P — Réseau** ouvre de nouveau le réseau local : liste des contacts et messages. L’écran n’est plus blanc.

### Nouveau — Application Relais AT

**Relais AT** apparaît sur la première ligne du chevron, à côté de P2P. Elle affiche le mât le plus proche : position, débit, fiabilité, identité, adresse réseau, passerelle, certificat, places et puissance. S’il n’y a pas de mât à portée, l’écran l’indique clairement.

### Correction — Positions figées trop tôt

Un opérateur encore en liaison et en forme n’est plus annoncé comme « position figée » au bout de quelques secondes. Le bandeau n’apparaît que si personne n’est plus à jour depuis plus d’une minute, ou si le poste n’a plus reçu le théâtre depuis longtemps. Un cadre en pointillés n’entoure l’indicatif que dans ces mêmes cas. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Boussole seule sous jumelles

Sous jumelles, vous pouvez n’afficher que votre boussole dans le tube, sans la grille, la distance ni l’heure. Choix : Paramètres du téléphone, ligne Dans le tube, ou options du jeu (Affichage situation).

### Correction — Pictos du jeu sur la carte

Les points, cases et alertes posés en jeu réapparaissent en couleur sur la carte du poste. Auparavant le picto était une silhouette transparente : il ne restait que le nom, quand il y en avait un. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Espace Air du poste

Le poste Overwatch Beta ouvre un espace **Air** dans la barre du haut. Chaque aéronef déclaré apparaît avec son manifeste : indicatif, mission, emport, carburant, autonomie et arrivée estimée. Les demandes JTAC se lisent et se préparent dans le même tiroir. Clic droit sur la carte pour reprendre la grille. Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Manifeste de vol et repères texte

Le manifeste de vol s’enregistre même si l’emport est saisi en phrase libre. Les repères posés en jeu comme du texte seul, sans picto, apparaissent au poste dans un cadre coloré. Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Position différée, sans bandeau

Quand une position n’est plus à l’instant, l’indicatif reste lisible : le cadre passe en pointillés (ambre, puis rouge). Plus de bandeau « Différé » sous le nom. Le détail reste au survol et dans la fiche. Rechargez Overwatch Beta (Ctrl+F5).

### Amélioration — Bosquets en houppier

En 2D immersif, un groupe d’arbres dans un champ se lit comme un houppier collé à la photo, plus comme une grille de carrés verts. Les constructions restent des toits clairs. Rechargez Overwatch Beta (Ctrl+F5).

### Amélioration — Aide sur les options de vue

À côté des options de vue, un i ouvre une phrase d’aide : traces, densité de passages, replay, inspection des bâtiments, manques de relief.

### Correction — Positions figées et couvert végétal

Sans opérateur en liaison, le bandeau rouge ne parle plus de positions figées. Les arbres et le couvert n’apparaissent plus en pavés sur toute la carte : ils se lisent en se rapprochant, en taches de houppier. Un message indique le chargement du relevé.

### Amélioration — 2D immersif, emprises de constructions

En 2D immersif, les constructions du relevé se lisent comme des toits sur la photo : emprise claire, collée au terrain. Un rectangle sans nom posé en jeu n’affiche plus de nom technique au survol. Un clic ouvre toujours la fiche de la construction.

### Correction — Menu d’applications du téléphone

Le chevron reprend sa grille : icônes alignées, noms lisibles, Video Feeds à sa place. L’application réseau local s’ouvre toujours.

### Correction — Démarrage du téléphone ATAK

Le téléphone s’ouvre de nouveau au lancement. L’application réseau local ne bloquait plus le chargement.

### Nouveau — Affichage situation sous jumelles

Sous jumelles de vision nocturne, le champ reprend un tube à trois oculaires : grain, contraste, et un assombrissement si un véhicule éclaire vers vous. La boussole, la grille, la distance regardée et l’heure apparaissent dans le tube. Les pastilles sont des losanges. Un halo clair marque les alliés proches, un véhicule moteur allumé et le bâtiment désigné.

### Correction — Découpage d’étage

Quand un bâtiment est désigné et le découpage d’étage actif, une croix marque le plafond de l’étage regardé.

### Correction — Identité SEEK

Le module, les attributs de la personne et le pack SSE imposent le même nom et le même verdict (Signalé ou Recherché) au terminal SEEK.

### Correction — Surcharge mémoire

Moins d’arrêts liés aux tuiles de carte, au dépassement de carte et à un ordre mal formé.

### Nouveau — Canaux de discussion, photothèque, carte du poste

Canaux Commandement, Général, JTAC. Photothèque : envoi vers le site et le SSE. Carte du poste : prédiction de déplacement, pointage, données terrain. Dépannage de liaison via Échap.

Relancez Arma complètement (Overwatch 1.6.4, pack SSE 0.7.22, Athena 1.0.159).

---

## Pack SSE 0.7.22 — 2026-09-20

### Correction — Identité SEEK depuis l’éditeur du pack SSE

Dans l’éditeur, le pack SSE reprend désormais le même résultat que Overwatch : nom, prénom, alias, et le choix Signalé ou Recherché. Le terminal SEEK du pack affiche ce nom et ce verdict, même sans Overwatch. Relancez Arma complètement (pack SSE 0.7.22).

---

## Overwatch 1.6.3 — 2026-09-20

### Correction — Découpage d’étage sur la silhouette

Quand un bâtiment est désigné et le **découpage d’étage** actif, une croix marque désormais le plafond de l’étage que vous regardez. Les séparateurs d’étage restent visibles ; l’étage affiché se distingue clairement. Le choix découpage se mémorise au même endroit, que vous le changiez depuis le téléphone ATAK ou depuis les options du jeu. Relancez Arma complètement (Overwatch 1.6.3).

---

## Overwatch 1.6.2 — 2026-09-20

### Correction — Profil d’identité SSE dans l’éditeur

Dans l’éditeur, le module **Profil d’identité SSE** impose maintenant le même résultat que les attributs de la personne : nom, alias, et le choix Signalé ou Recherché. Le terminal SEEK voit ces informations sur les sujets synchronisés. Le mât Relais AT n’a plus de zone à redimensionner : la portée se règle uniquement en mètres. Relancez Arma complètement (Overwatch 1.6.2).

---

## Overwatch 1.6.1 — 2026-09-20

### Amélioration — Affichage situation sous jumelles

Sous jumelles de vision nocturne, le champ reprend un tube à trois oculaires : grain, contraste, et un assombrissement si un véhicule éclaire vers vous. La boussole, la grille, la distance regardée et l’heure apparaissent dans le tube. Les pastilles sont des losanges, le texte reste lisible, et les alliés lointains s’estompent. Un halo clair marque les alliés proches, un véhicule moteur allumé et le bâtiment désigné. Si une fusion thermique est déjà fournie par un autre pack, Overwatch n’ajoute pas la sienne. Relancez Arma complètement (Overwatch 1.6.1).

---

## Overwatch 1.6.0 / Athena 1.0.155 — 2026-09-19

### Amélioration — Manifeste de vol

Le manifeste reprend l’appareil dans lequel vous êtes : nom, personnes à bord, munitions, pods et autonomie. Vous confirmez les codes, le numéro de mission et le canevas d’attaque, puis vous transmettez. Le poste voit la même fiche. Relancez Arma complètement (Overwatch 1.6.0, Athena 1.0.155).

---

## Overwatch 1.5.99 — 2026-09-19

### Correction — Ordres C2

Un ordre donné en jeu n’arrête plus Arma. Il part sous votre nom Athena, pas sous le surnom du jeu. Le chat de groupe ne montre plus la ligne technique. Relancez Arma complètement (Overwatch 1.5.99).

---

## Overwatch 1.5.98 / Athena 1.0.154 — 2026-09-19

### Nouveau — Appui aérien et manifeste dans le téléphone

La demande d’appui aérien et le manifeste de vol s’ouvrent dans le téléphone, depuis le tiroir. Vous indiquez le type, la grille et une note, ou l’appareil, le rôle et les codes, sans recouvrir le terrain. Relancez Arma complètement (Overwatch 1.5.98, Athena 1.0.154).

---

## Overwatch 1.5.97 — 2026-09-19

### Amélioration — Manifeste de vol

Le manifeste de vol se remplit aussi depuis le sol. Vous choisissez le type d’appareil, le rôle, la destination, les personnes à bord et une note pour le poste. La grille et le carburant sont proposés automatiquement. En vol, l’appareil est toujours détecté. Une réponse **À poste** s’ajoute aux statuts pilote. Le poste affiche la fiche complète. Relancez Arma complètement (Overwatch 1.5.97).

---

## Overwatch 1.5.96 / Athena 1.0.153 — 2026-09-19

### Nouveau — Relais AT

Wave Relay devient Relais AT dans le tiroir du téléphone. L’application montre le mât le plus proche : position, débit, fiabilité, identité, adresse réseau, passerelle, certificat, places et puissance. La carte du poste affiche la même fiche. Un mât détruit passe hors service (débit et puissance à zéro). Posez et réglez le mât dans l’éditeur (Modules COMSPEC, notice de pose) ou via Zeus. Relancez Arma complètement (Overwatch 1.5.96, Athena 1.0.153).

### Nouveau — Annuler le pointage de bâtiment

Quand un bâtiment est désigné sous jumelles de vision nocturne, l’affichage situation propose d’annuler ce pointage. La silhouette, le badge et le repère local disparaissent.

---

## Overwatch 1.5.95 / Athena 1.0.152 — 2026-09-19

### Correction — Symboles Marker Dropper au poste

Un losange d’infanterie ou un autre symbole posé avec Marker Dropper sur le téléphone apparaît maintenant sur la carte du poste, au même endroit. Relancez Arma complètement (Overwatch 1.5.95).

---

## Overwatch 1.5.94 / Athena 1.0.151 — 2026-09-19

### Correction — Coupure sans panneau sur la carte

Une coupure de liaison n’affiche plus de panneau au milieu de la carte. Les barres de signal, à côté de l’heure et de la batterie, passent au rouge. La carte reste utilisable. Relancez Arma complètement (Overwatch 1.5.94).

---

## Athena 1.0.150 — 2026-09-19

### Correction — Paramètres ouverts sur la fiche

La page Paramètres s’ouvrait trop bas, sur la liaison au poste. Elle s’ouvre maintenant sur votre fiche (indicatif, rôle, carte, équipe). Enregistrer reste visible en haut. Relancez Arma complètement (Athena 1.0.150).

---

## Athena 1.0.149 — 2026-09-19

### Correction — Transférer dans Photo Library

Transférer et Tout transférer n’apparaissaient pas sous la liste. Ils se placent maintenant dans cette zone dès que Photo Library est ouverte. Relancez Arma complètement (Athena 1.0.149).

---

## Athena 1.0.148 — 2026-09-19

### Correction — P2P réseau local

P2P n’affichait plus que Retour et Send Data, sans correspondants ni messages. Le chat téléphone à téléphone revient. Relancez Arma complètement (Athena 1.0.148).

---

## Overwatch Beta — 2026-09-19

### Correction — Croquis qui disparaissaient

Un croquis tracé au crayon s’effaçait dès l’ouverture du panneau d’enregistrement. Le trait reste maintenant sur la carte. Dans le mode Tracé tactique, il se pose dès que vous relâchez le clic.

### Amélioration — Couleurs du tracé tactique

La barre au-dessus de la carte propose une couleur et une épaisseur de trait. Flèches, croquis, zones, surligneur et symboles OTAN utilisent cette teinte. Les pastilles ami, ennemi, neutre et inconnu restent disponibles, plus une teinte libre. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Plan rattaché à un bâtiment connu

Le plan d’étage se rattache à un bâtiment déjà désigné en jeu, ou à une construction relevée. Vous le choisissez dans la liste. Un clic sur une construction ouverte propose aussi d’ouvrir le plan. Rechargez Overwatch Beta (Ctrl+F5). Pour les désignations en jumelles, relancez Arma.

### Amélioration — À plat et 2D immersif

La vue À plat reste un plan : photo ou carte du jeu, sans volumes. Le 2D immersif montre les constructions du relevé collées au fond. Elles se chargent une fois à l’ouverture : zoomer ne les décale plus. Relief 3D et Tactique 3D dressent toujours le sol. Rechargez Overwatch Beta (Ctrl+F5).

### Amélioration — Onglet Contacts

Les contacts se séparent entre ceux encore en liaison et ceux qui ne le sont plus, par escouade. Chaque fiche montre le rôle, l’âge de la position, et si le lien est direct ou par relais. Un contact hors ligne se recentre sur sa dernière position connue.

### Amélioration — Fiche contact et groupes

La fiche d’un contact ne montre plus que l’essentiel : vitesse, altitude, dernière position, grille, puis centrer, suivre ou donner une tâche. Le téléphone et l’alerte plein écran se déplient à la demande. Dans Groupes, les tâches déjà transmises et l’alerte sont rangées de la même façon.

### Amélioration — Constructions du jeu

En 2D immersif, les bâtiments, forêts et obstacles relevés en jeu s’affichent collés à la carte. Un clic ouvre la fiche de la construction : marquer, poser un objectif, noter un étage. En Relief 3D, les volumes se dressent toujours au-dessus du sol. Rechargez Overwatch Beta (Ctrl+F5).

### Nouveau — Tracé tactique et briefing

Le crayon du rail ouvre une barre au-dessus de la carte : flèche, croquis, zone, surligneur, texte, plan de bâtiment. Les symboles OTAN posent un axe, une attaque, une ligne de phase, une limite de secteur, un rassemblement ou un objectif, selon l’affiliation (ami, ennemi, neutre, inconnu). Un plan d’étage se rattache à une position. Exporter PDF prépare une feuille de briefing (carte, légende, grille, horodatage, fil). Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Repères qui revenaient

Un compte rendu ou un repère retiré de la carte du poste réapparaissait quelques secondes plus tard. Le retrait tient désormais. Rechargez Overwatch Beta (Ctrl+F5).

---

## Athena 1.0.147 — 2026-09-19

### Amélioration — Icônes du menu

Athena, Briefing et Tutoriel avaient la même bulle. Chaque application a maintenant son pictogramme : tablette, presse-papiers, livre, bulles pour la messagerie. Relancez Arma complètement (Athena 1.0.147).

---

## Overwatch 1.5.93 — 2026-09-19

### Correction — Menu Échap

Le bouton COMSPEC Overwatch du menu Échap n’apportait plus rien d’utile. Il disparaît. Le dépannage de liaison reste disponible depuis Échap. Relancez Arma complètement (Overwatch 1.5.93).

---

## Athena 1.0.146 — 2026-09-19

### Nouveau — Transfert vers le poste

Dans Photo Library, **Transférer** envoie la vue sélectionnée vers le poste. **Tout transférer** envoie vos vues locales. Après l’envoi, elles quittent la bibliothèque du téléphone. Relancez Arma complètement (Athena 1.0.146).

---

## Athena 1.0.145 — 2026-09-19

### Correction — Icônes du menu

Les tuiles Athena du chevron n’affichaient plus d’icône. Le pictogramme revient au-dessus du nom, comme sur Video Feeds. Relancez Arma complètement (Athena 1.0.145).

---

## Athena 1.0.144 — 2026-09-19

### Correction — Menu d’applications

Le chevron ne montrait plus que deux ou trois icônes, mal placées. Le téléphone ne déplace plus les tuiles : la grille d’origine réapparaît. Relancez Arma complètement (Athena 1.0.144).

---

## Overwatch Beta — 2026-09-19

### Correction — Fond de carte en relief 3D

En vue Relief 3D ou Tactique 3D, le théâtre restait sombre : le plan et la photo aérienne n’arrivaient pas jusqu’au poste. Ils s’affichent de nouveau sur le relief. Rechargez Overwatch Beta (Ctrl+F5).

---

## Overwatch Beta — 2026-09-19

### Nouveau — Outils de commandement

Le poste mesure d’un clic à l’autre (distance, cap, grilles, temps de parcours). Un compte rendu de contact structuré, une 9-line et un CASEVAC se préparent depuis un point de la carte. Les contacts en mouvement montrent un vecteur d’anticipation. Le fil d’ordres se filtre par mot-clé. Des alertes sonores préviennent d’un contact trop proche d’un objectif ou d’un ralliement franchi. Replay et export du bilan sont accessibles depuis Mission et Plus. Une position qui n’est plus mise à jour est indiquée comme dernière position connue, figée. L’alerte plein écran à tous les opérateurs est réservée au commandement. Rechargez Overwatch Beta (Ctrl+F5).

---

## Athena 1.0.143 — 2026-09-19

### Correction — Menu d’applications

Le chevron mélangeait les icônes et les noms. Un clic ouvrait souvent Task et Video Feeds, et les applications Athena n’apparaissaient pas. Les tuiles reprennent la grille à trois colonnes du téléphone. Relancez Arma complètement (Athena 1.0.143).

---

## Overwatch 1.5.92 — 2026-09-19

### Amélioration — Journal du dépannage

Si le jeu s’arrête pendant le dépannage liaison, le journal de session indique désormais quelle étape était en cours. Relancez Arma complètement (Overwatch 1.5.92) avant le prochain essai.

---

## Athena 1.0.142 — 2026-09-19

### Correction — Quick Pictures vers le poste

Une vue prise depuis Quick Pictures n’arrivait pas au poste, ou seulement si l’on choisissait le bon destinataire. Dans Photo Library, le bouton **Vers Athena** envoie la photo sélectionnée au poste. Relancez Arma complètement (Athena 1.0.142).

---

## Overwatch 1.5.91 — 2026-09-19

### Correction — Photos du poste

Les photos reçues du terrain ou du dépannage affichaient l’année 1970 et ne proposaient que Envoyer. L’heure affichée est désormais celle de la prise ou de la réception. Sous chaque photo : Agrandir, Flouter, Passer en SSE, Supprimer. Rechargez Overwatch Beta. Relancez Arma complètement (Overwatch 1.5.91) pour les prochaines photos prises en jeu.

---

## Athena 1.0.141 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron déformait les tuiles : icônes décalées, libellés absents, cases vides. Les applications reprennent leur place en trois colonnes, avec le fond gris sombre. Relancez Arma complètement (Athena 1.0.141).

---

## Overwatch 1.5.90 — 2026-09-19

### Correction — File d’ordres

Les ordres déjà reçus du poste n’étaient pas remplacés à chaque lecture : la mémoire du téléphone s’allongeait jusqu’à fermer le jeu. Désormais seuls les ordres encore en attente au poste, plus ceux émis depuis le téléphone, restent en mémoire. Relancez Arma complètement (Overwatch 1.5.90).

---

## Overwatch 1.5.89 / Athena 1.0.140 — 2026-09-19

### Correction — Ordres à l’affichage

Pendant le dépannage, les ordres déjà en mémoire n’étaient pas marqués comme vus. Au passage à l’affichage, le téléphone les livrait tous d’un coup et le jeu se fermait. Désormais un seul ordre est affiché à la fois, et la file déjà reçue n’est plus rejouée. Relancez Arma complètement (Overwatch 1.5.89 · Athena 1.0.140).

---

## Overwatch 1.5.88 — 2026-09-19

### Amélioration — Dépannage liaison

Le dépannage envoie désormais un message de test, pose un repère de test, puis prend une photo et la transmet au poste. Le bandeau indique si l’essai est parti. Relancez Arma complètement (Overwatch 1.5.88).

---

## Athena 1.0.139 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron reprend le fond gris sombre et les libellés cyan. Les icônes ne se superposent plus : elles se calent en trois colonnes dans le tiroir.

---

## Overwatch 1.5.87 — 2026-09-19

### Correction — Ordres d’une partie précédente

Le téléphone ne reçoit plus les ordres encore en attente au poste s’ils ont été émis avant le début de cette partie. Le poste les conserve. Un ordre envoyé pendant la partie en cours arrive normalement. Relancez Arma complètement (Overwatch 1.5.87).

---

## Overwatch 1.5.86 — 2026-09-18

### Correction — Ordres d’une partie précédente

Les ordres encore en attente au poste, issus d’une partie précédente, ne sont plus livrés au téléphone. Seuls les ordres émis pendant la partie en cours apparaissent. Relancez Arma complètement (Overwatch 1.5.86).

---

## Athena 1.0.141 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron déformait les tuiles : icônes décalées, libellés absents, cases vides. Les applications reprennent leur place en trois colonnes, avec le fond gris sombre. Relancez Arma complètement (Athena 1.0.141).

---

## Overwatch 1.5.90 — 2026-09-19

### Correction — File d’ordres

Les ordres déjà reçus du poste n’étaient pas remplacés à chaque lecture : la mémoire du téléphone s’allongeait jusqu’à fermer le jeu. Désormais seuls les ordres encore en attente au poste, plus ceux émis depuis le téléphone, restent en mémoire. Relancez Arma complètement (Overwatch 1.5.90).

---

## Overwatch 1.5.89 / Athena 1.0.140 — 2026-09-19

### Correction — Ordres à l’affichage

Pendant le dépannage, les ordres déjà en mémoire n’étaient pas marqués comme vus. Au passage à l’affichage, le téléphone les livrait tous d’un coup et le jeu se fermait. Désormais un seul ordre est affiché à la fois, et la file déjà reçue n’est plus rejouée. Relancez Arma complètement (Overwatch 1.5.89 · Athena 1.0.140).

---

## Overwatch 1.5.88 — 2026-09-19

### Amélioration — Dépannage liaison

Le dépannage envoie désormais un message de test, pose un repère de test, puis prend une photo et la transmet au poste. Le bandeau indique si l’essai est parti. Relancez Arma complètement (Overwatch 1.5.88).

---

## Athena 1.0.139 — 2026-09-19

### Correction — Menu d’applications

Le menu du chevron reprend le fond gris sombre et les libellés cyan. Les icônes ne se superposent plus : elles se calent en trois colonnes dans le tiroir.

---

## Overwatch 1.5.87 — 2026-09-19

### Correction — Ordres d’une partie précédente

Le téléphone ne reçoit plus les ordres encore en attente au poste s’ils ont été émis avant le début de cette partie. Le poste les conserve. Un ordre envoyé pendant la partie en cours arrive normalement. Relancez Arma complètement (Overwatch 1.5.87).

---

## Overwatch 1.5.86 — 2026-09-18

### Correction — Ordres d’une partie précédente

Les ordres encore en attente au poste, issus d’une partie précédente, ne sont plus livrés au téléphone. Seuls les ordres émis pendant la partie en cours apparaissent. Relancez Arma complètement (Overwatch 1.5.86).

---

## Overwatch 1.5.85 — 2026-09-18

### Correction — Prise d’équipement

Récupérer le téléphone ATAK dans l’arsenal ne livre plus d’un coup les ordres déjà en attente et tous les repères du poste. Les échanges attendent la fermeture de l’arsenal. Les alertes et le fil n’apparaissent que lorsque le grand écran du téléphone est vraiment ouvert.

---

## Portail · Overwatch Beta — 2026-09-18

### Correction — Relief 3D vide

La vue Relief 3D restait un écran vert sombre, sans sol ni bâtiments. Après recharge de la page, le théâtre se relève et les constructions du relevé réapparaissent. Rechargez Overwatch Beta (Ctrl+F5).

### Correction — Relief 3D figé au redimensionnement

Agrandir la fenêtre, ouvrir le comparatif 2D / 3D ou basculer Relief 3D ne fige plus la carte. Le sol et les bâtiments restent affichés. Rechargez Overwatch Beta.

### Nouveau — Visibilité, coupe et lecture 3D

Un clic sur un observateur affiche les portions de terrain visibles et masquées. L’horizon dessine la silhouette du relief. Une coupe de A vers B montre le sol et les constructions. La comparaison 2D / 3D aligne les deux lectures. Les symboles restent lisibles derrière un obstacle (réaliste, silhouette ou toujours visibles). Une pile remplace les icônes empilées. Une note, une photo ou une tâche peut s’ancrer à une façade, un étage ou un toit. Un volume a une altitude basse et haute. Le replay peut suivre l’action, avec traces de déplacement et densité de passages. Les vues de caméra s’enregistrent. Rechargez Overwatch Beta.

### Nouveau — Lecture 3D du théâtre

En vue Tactique 3D, les murs, clôtures, ponts et pylônes se dressent avec les bâtiments. Un clic sur une construction ouvre sa fiche (grille, orientation, niveaux, hauteur) avec les actions déjà connues : marquer, objectif, entrée, photo, tâche. Double-clic pour incliner la caméra, Nord pour revenir à plat, Unité pour suivre le groupe, Sol pour descendre près du terrain. La visée nomme l’obstacle. Un tracé affiche montée, descente et pente. Rechargez Overwatch Beta. Relancez le relevé de carte si les obstacles manquent.

### Amélioration — Relief 3D : clic, masses et lumière

En vue Relief 3D, cliquer un bâtiment ouvre le même menu que sur la carte à plat (marqueurs, ralliement, tâches). Les petites constructions lointaines se regroupent ; le détail revient en se rapprochant. Les ombres suivent l’heure et la météo du bandeau. Rechargez Overwatch Beta.

---

## Athena 1.0.138 — 2026-09-18

### Correction — Fil d’ordres

Le fil du téléphone n’est plus rempli tant que le grand écran ATAK n’est pas ouvert. Un mini-écran 3D à la prise de l’objet ne déclenche plus ce remplissage.

---

## Overwatch 1.5.84 — 2026-09-18

### Correction — Dépannage : étape Ordres

Le dépannage liaison ne livre plus les ordres sur le téléphone en même temps qu’il les reçoit. D’abord la réception, ensuite l’affichage. Le bandeau affiche le débit, le volume transmis, le poste, les versions, le compte identifié et le taux d’erreur. Le journal indique le nombre de messages, de repères et d’ordres reçus.

---

## Athena 1.0.137 — 2026-09-18

### Correction — Ordres pendant le dépannage

Pendant le dépannage, les ordres ne sont plus collés dans le fil du téléphone avant l’étape d’affichage.

---

## Overwatch 1.5.83 — 2026-09-18

### Nouveau — Contrôle de mission

Le commandement dispose d’un écran unique pour imposer le réalisme, voir les fonctions actives, consulter les relais et activer ou couper ce que la mission utilise. Les opérateurs en liaison reçoivent les règles sous environ une minute. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.136 — 2026-09-18

### Nouveau — Message : P2P ou Via Athena

En ouvrant Message, vous choisissez d’abord le canal. **P2P — Réseau local** : messages entre téléphones à proximité, comme d’habitude. **Via Athena** : messagerie de compte à compte, comme au poste.

---

## Overwatch 1.5.82 — 2026-09-18

### Correction — Plus de double démarrage en mission

Recocher Overwatch dans les paramètres d’addons ne relance plus tout le mod par-dessus une session déjà ouverte. Les menus, la liaison et les alertes restent un seul jeu. Relancez Arma complètement après la mise à jour.

---

## Overwatch 1.5.81 — 2026-09-18

### Correction — Dépannage liaison visible sur Échap

Le dépannage se lance depuis le bouton orange en haut à gauche du menu Échap. Plus besoin de chercher dans le panneau, plus de seconde fenêtre par-dessus la pause. Un bandeau reste à l’écran : Overwatch est coupé, puis chaque fonction est rallumée une par une. Relancez Arma complètement après la mise à jour.

---

## Overwatch 1.5.80 — 2026-09-18

### Nouveau — Dépannage liaison

Un outil coupe Overwatch, puis rallume chaque fonction de la liaison une par une, avec 55 secondes d’écart. Un bandeau reste à l’écran : si le jeu s’arrête, c’est la fonction affichée. Échap → COMSPEC Overwatch → Dépannage liaison. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.135 / Overwatch 1.5.79 — 2026-09-17

### Correction — Plus d’arrêt avec Overwatch actif

Le jeu ne se ferme plus tout seul une fois Overwatch coché, téléphone ouvert ou non. Les repères déjà affichés depuis le poste ne relancent plus une avalanche d’envois. Décochez Overwatch : la position et les messages s’arrêtent aussi. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.134 — 2026-09-17

### Correction — Messagerie lisible, plus d’arrêt sans téléphone

La liste des canaux n’est plus recouverte par Envoyer / Effacer. Sans téléphone en poche, le jeu ne se ferme plus tout seul : la liaison avec le poste n’attend plus que l’objet soit porté. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.133 — 2026-09-17

### Correction — Plus d’arrêt pendant la carte et les repères

Le jeu ne se ferme plus tout seul pendant l’affichage de la carte, la pose d’un repère ou le rafraîchissement des indicatifs. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.132 — 2026-09-17

### Correction — Menu d’applications avec les icônes

Le chevron ouvre le menu d’applications avec les icônes. Ce n’est plus un panneau gris vide. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.131 — 2026-09-17

### Correction — Plus d’arrêt après quelques dizaines de secondes

Le jeu ne se ferme plus tout seul une fois le téléphone ouvert sur la carte, ni après une pause sans activité visible. La page d’état de liaison affiche l’état déjà calculé, sans relancer le calcul à chaque rafraîchissement. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.130 — 2026-09-17

### Correction — Menu d’applications qui se referme

Le menu d’applications s’ouvre et se referme de nouveau avec le chevron et Retour. La carte reste dans le cadre du téléphone. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.129 — 2026-09-17

### Correction — Carte dans l’écran et menu du téléphone

La carte reste dans le cadre du téléphone. Le menu d’applications reprend celui du téléphone : les icônes s’affichent, Retour referme le menu. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.128 — 2026-09-17

### Correction — Arrêt brutal au bout de quelques secondes

Le jeu ne se ferme plus tout seul après quelques secondes, même sans ouvrir le téléphone. Le menu ACE ne se réempile plus à chaque mission de la même session. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.144 — 2026-09-16

### Correction — Alertes et écran du téléphone

Une alerte du poste n’ajoute plus un voile sur le téléphone en miniature. Les cartouches de la carte restent ceux du téléphone, sans cadre collé par-dessus. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.143 — 2026-09-16

### Changement — Plus de barre de données en bas de carte

La ligne verte en bas de la carte (sync, fiabilité, versions) n’apparaît plus. La carte reprend toute la hauteur. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.142 — 2026-09-16

### Correction — Menu qui reste ouvert, messages dans la messagerie

Le menu d’applications s’ouvre au chevron et y reste jusqu’à ce qu’on le referme. Les messages du poste restent dans la messagerie : ils ne s’empilent plus en bandeau en bas de la carte. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.141 — 2026-09-16

### Correction — Plus de fermeture sans ouvrir le téléphone

Le jeu ne se ferme plus tout seul si l’opérateur n’a pas le téléphone en main et ne l’a pas ouvert. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.140 — 2026-09-16

### Correction — Plus de recadrage de la carte

Athena ne déplace plus et ne redimensionne plus la carte ni le menu d’applications. Le menu s’affiche ou se masque seulement, à la demande du chevron. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.139 — 2026-09-16

### Correction — Menu qui reste ouvert

Le menu d’applications s’ouvre et reste ouvert jusqu’au chevron. Il ne clignote plus. Sortir le curseur du cadre du téléphone n’immobilise plus l’écran. Relancez Arma complètement après la mise à jour.

---

## Liaison 2.0.42 — 2026-09-16

### Correction — Photographies sans surcharge

L’envoi d’une photographie volumineuse depuis le téléphone n’encombre plus la mémoire du jeu. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.138 — 2026-09-16

### Correction — Menu qui ne revient plus tout seul

Le menu d’applications ne s’ouvre plus tout seul juste après l’écran. Il reste fermé jusqu’au chevron. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.137 — 2026-09-16

### Correction — Menu vraiment fermé à l’ouverture

Le menu d’applications reste fermé à chaque ouverture du téléphone, même si la session précédente l’avait laissé ouvert. Seul le chevron l’ouvre et le referme. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.136 — 2026-09-16

### Correction — Menu fermé à l’ouverture

Le menu d’applications n’est plus déployé dès l’ouverture du téléphone. Le chevron l’ouvre et le referme. La carte ne se rétrécit plus à chaque ouverture. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.135 — 2026-09-16

### Amélioration — Affichage IceMan

Les pages du téléphone, les cartouches de la carte, la barre de liaison et l’alerte du poste reprennent l’affichage IceMan : couleurs, alignement et sauts de ligne. L’identité Indicatif / Nom / Rôle reste celle du téléphone. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.134 — 2026-09-16

### Correction — Pages du téléphone en texte simple

Messagerie, connexion, comptes-rendus, réglages, wiki et retours d’écran s’affichent en texte simple. Plus de mise en forme fragile à l’ouverture d’une page.

---

## Athena 1.0.133 — 2026-09-16

### Correction — Textes de la carte

Les cartouches grille / identité, la barre de liaison et l’alerte du poste s’affichent en texte simple. Plus de mise en forme fragile qui fermait le jeu à l’ouverture du téléphone.

---

## Athena 1.0.132 — 2026-09-16

### Correction — Menu dans l’écran du téléphone

Le menu d’applications reste dans l’écran, à droite de la carte. Il ne flotte plus à côté du boîtier. Refermé, il disparaît et la carte reprend toute la largeur. Sur l’accueil, l’encart d’identité ne recouvre plus le bureau. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.131 — 2026-09-15

### Correction — Menu d’applications

Le menu d’applications se déroule de nouveau : retour et défilement sont utilisables. Les boutons du bas restent sous le menu. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.130 — 2026-09-15

### Changement — Écran d’accueil

L’écran d’accueil du téléphone n’affiche plus les raccourcis Connexion Athena, messagerie, Resynch et les autres icônes COMSPEC. Ces fonctions restent dans le menu d’applications et dans le menu ACE. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.129 — 2026-09-15

### Correction — Alerte plein écran

Une alerte envoyée depuis le poste recouvre tout l’écran du téléphone, y compris le menu d’applications s’il était ouvert. Le titre et le texte restent lisibles. Relancez Arma complètement après la mise à jour.

---

## Athena 1.0.128 — 2026-09-15

### Correction — Boutons du tiroir d’applications

À l’ouverture du menu d’applications, les boutons du bas (photos, recherche, radio) restent collés sous le tiroir. Ils ne recouvrent plus la carte.

---

## Overwatch 1.5.78 / Athena 1.0.127 — 2026-09-15

### Correction — Fermeture brutale avec le téléphone ouvert

Avec le téléphone ATAK ouvert et la liaison active, le jeu pouvait se fermer tout seul. Le cadre de l’écran est figé. Relancez Arma complètement après la mise à jour.

### Correction — Messagerie

Depuis un canal, le retour ramène à la liste des canaux.

### Nouveau — Overwatch Beta et téléphone

Le poste envoie une alerte plein écran, une tâche de groupe, des points à atteindre et un ralliement. Cap, vitesse, altitude et liaison du terrain s’affichent sur la fiche contact.

---

## Athena 1.0.127 — 2026-09-15

### Correction — Écran d’accueil et cartouches carte

Les boutons masqués de l’écran d’accueil ne se calent plus à une taille nulle. Les cartouches de la carte n’interprètent plus un nom d’opérateur comme du texte enrichi. Relancez Arma complètement après la mise à jour.

### Amélioration — Overwatch Beta : cap, vitesse et liaison du terrain

Le poste Overwatch Beta affiche désormais le cap, la vitesse, l’altitude, l’état de liaison et le mode de transmission réellement remontés depuis Arma. Ce sont les mêmes données que celles déjà envoyées par le téléphone.

---

## Athena 1.0.126 — 2026-09-15

### Correction — Fermeture brutale du jeu avec le téléphone ouvert

Avec le téléphone ATAK ouvert et la liaison active, le jeu pouvait se fermer tout seul, sans message. Le cadre de l’écran est désormais figé : la carte et le tiroir d’applications ne se calent plus hors limites. Relancez Arma complètement après la mise à jour.

---

## Portail · Overwatch Beta — 2026-09-14

### Nouveau — Détection des marqueurs posés en jeu

Les gestionnaires de la communauté décrivent quels marqueurs posés dans Arma 3 doivent être suivis : un libellé, un symbole, un rayon. Dès qu’un opérateur pose un point correspondant, il apparaît au poste. Si la règle le demande, le point est confirmé lorsqu’un téléphone ATAK entre dans le rayon, et les opérateurs sont prévenus sur leur écran. Les points d’objectif libellés PO restent suivis comme avant. Relancez Arma complètement après la mise à jour du pack jeu.

### Nouveau — Alerte plein écran sur les téléphones ATAK

Le commandement envoie une alerte qui recouvre tout l’écran du téléphone ATAK. Le message s’affiche aussi lorsque le téléphone est en position mini dans le coin de l’écran. L’opérateur le lit, puis appuie sur Fermer, ou l’alerte disparaît après quelques secondes. Relancez Arma complètement après la mise à jour du pack jeu.

### Nouveau — Points de ralliement

Le commandement pose un point de ralliement depuis Overwatch Beta. Un clic place un lieu de regroupement avec un anneau de 50 mètres, visible au poste et en jeu. Les opérateurs réellement présents dans le rayon sont indiqués. Ce n’est pas un point à atteindre : c’est un lieu où se rassembler.

### Nouveau — Tâches de groupe

Le commandement transmet une tâche à un groupe depuis Overwatch Beta. Le groupe, le type d’action, l’urgence et éventuellement un point à atteindre se choisissent dans des listes. La tâche arrive sur les téléphones ATAK des opérateurs concernés. Le poste suit le statut et peut annuler.

### Nouveau — Poser des points à atteindre

Le commandement pose une suite de points à atteindre depuis Overwatch Beta. Chaque point est numéroté, un anneau de 20 mètres s’affiche, et le point est confirmé dès qu’un téléphone ATAK y entre. La suite est transmise aux opérateurs pour le guidage en jeu.

### Nouveau — Points d’objectif PO confirmés à 20 m

Les marqueurs libellés PO deviennent des points d’objectif. Dès qu’un opérateur ATAK entre dans un rayon de 20 mètres, le point est confirmé atteint. Le poste affiche l’anneau et l’indicatif. En jeu, l’anneau passe au gris.

### Nouveau — Visée, anneaux de portée et suivi

Vous vérifiez si le relief masque une visée. Des anneaux de portée s’affichent autour du contact ouvert. Le poste peut suivre un opérateur. Un tracé se retire. La grille part sur le canal. Les contacts se filtrent par ami, hostile ou inconnu.

### Nouveau — Groupes reliés, mesures et dessins

Les opérateurs d’un même groupe sont reliés sur la carte. Un clic sur un contact affiche les distances vers ses coéquipiers, ainsi que le cap et la vitesse s’ils sont transmis. Vous mesurez cap, distance et surface. Vous dessinez un cercle, un rectangle, un croquis ou un texte, avec la couleur choisie dans les réglages.

### Nouveau — Appui, renseignement et outils de poste

Sur Overwatch Beta, le commandement prépare une 9-Line complète, ouvre une évacuation avec le triage, consulte les fiches de renseignement et le journal de mission. Un temps de parcours à pied ou en véhicule se calcule sur la carte. Le profil de relief s’affiche si le théâtre a déjà été relevé.

### Amélioration — Zones, météo, replay et personnalisation

Une alerte signale l’entrée ou la sortie d’une zone. La météo transmise par la mission apparaît en overlay. Le replay parcourt les trajectoires déjà enregistrées. Les symboles, couleurs et largeurs de colonnes restent mémorisés sur cet ordinateur.

### Correction — La carte du poste Beta s’affiche

Overwatch Beta a son propre écran, distinct de la carte ATAK habituelle. La carte de l’opération est visible. Les réglages sont à gauche, le tchat de mission à droite. Un avertissement s’affiche à la première ouverture.

### Nouveau — Trois lectures de fond Altis

Dans les réglages du poste Beta : Classique, Aerial avec relief renforcé, et Noir et blanc. Le choix est mémorisé sur cet ordinateur. Les contacts et les symboles ne changent pas.

---

## Athena 1.0.124 — 2026-09-14

### Correction — Messagerie : retour à la liste des canaux

Après avoir ouvert un canal, **Retour aux canaux** ramène à la liste. Le bandeau du bas n’est plus recouvert par Live Feed. Relancez Arma complètement.

---

## Portail · Carte tactique — 2026-09-13

### Correction — La page s’ouvre à nouveau

Opérations → Carte tactique s’affiche de nouveau. Un incident empêchait l’ouverture de la page.

---

## Portail · Intégrations — 2026-09-13

### Nouveau — Photos Quick Picture vers Discord

Sur Intégrations, vous pouvez envoyer les photos Quick Picture (téléphone en jeu) vers un salon Discord de la communauté. Chaque événement a trois choix clairs : ne pas publier, salon commun, ou un autre salon.

---

## Overwatch 1.5.77 / Athena 1.0.123 — 2026-09-14

### Amélioration — Pack Overwatch, Athena et liaison à jour

Tout le pack Overwatch, le téléphone Athena et la liaison sont livrés ensemble. En jeu : le numéro de carte de l’opération, la reprise des envois après un plantage, le Super ping, et les correctifs Quick Picture déjà décrits.

---

## Portail · Carte ATAK — 2026-09-14

### Nouveau — Photo aérienne d’Altis

Sur Altis, le poste peut afficher la photo aérienne à la place du plan. Le calque s’aligne sur la carte de l’opération. Le téléphone en jeu continue d’utiliser le plan Overwatch.

### Nouveau — Calques de fond dans les réglages

Dans Réglages du poste, vous choisissez le fond de la carte : Plan, Carte du jeu, ou Photo aérienne. Les positions et les tracés restent en place.

---

## Portail · Carte ATAK — 2026-09-13

### Correction — Appuis ouvre la 9-Line

Un clic sur Appuis affiche de nouveau le module d’appui aérien. La 9-Line et les demandes en cours apparaissent, ou un état vide pour en créer une. Le poste ouvre ce module pour tout le monde.

### Nouveau — Relecture par date et par opérateur

Dans Journal → Relecture, vous pouvez choisir la journée et l’opérateur à suivre. La chronologie, la carte et les événements clés se limitent à ce filtre. Le type d’événement (contacts, ordres…) reste disponible.

### Correction — Fiches de renseignement lisibles

Le type de fiche et l’urgence s’affichent de nouveau en toutes lettres dans leur case, au lieu de grandes barres vides qui poussaient le texte hors du panneau.

### Nouveau — Fenêtres épinglées à gauche

Le tchat, la radio, la liaison, les ordres, le médical et les pings peuvent être épinglés. Ils restent visibles en raccourci à gauche de la carte lorsque vous changez de domaine. Le tchat épinglé garde les derniers messages et le champ d’émission. Jusqu’à trois raccourcis, mémorisés sur le poste.

---

## Overwatch 1.5.76 / Athena 1.0.122 — 2026-09-13

### Nouveau — Plusieurs opérations en parallèle

Chaque mission indique son numéro de carte Athena. Deux opérations ne mélangent plus leurs positions.

### Amélioration — File d’attente conservée après un redémarrage

Les envois qui n’avaient pas encore atteint le poste sont repris après un plantage ou un redémarrage d’Arma.

---

## Overwatch 1.5.75 / Athena 1.0.121 — 2026-09-13

### Correction — Quick Picture : Discord retrouve la photo

Discord reçoit de nouveau la photo prise depuis Quick Picture. Le dossier indiqué pour l’envoi n’existait pas, alors que le cliché était bien enregistré. Le poste continue de recevoir la vue.

---

## Overwatch 1.5.74 / Athena 1.0.120 — 2026-09-13

### Correction — Quick Picture vers Discord

Une photo prise depuis Quick Picture continue d’être transmise vers Discord. Overwatch n’interrompt plus cet envoi. Le poste reçoit toujours la vue.

---

## Overwatch 1.5.73 / Athena 1.0.119 — 2026-09-13

### Nouveau — Super ping

Un Super ping place un pulse animé sur la carte : au poste et sur le téléphone. Clic droit → Super ping au poste. Maj + clic gauche sur la carte du téléphone. Les cercles s’agrandissent quelques secondes, visibles de tous.

---

## Overwatch 1.5.72 / Athena 1.0.118 — 2026-09-13

### Nouveau — Zone de déplacement depuis la dernière position

Sur le téléphone ATAK, un contact vu dans les quinze dernières minutes reste visible à sa dernière position connue s’il vient de perdre la liaison. Un clic sur ce contact, ou dans la liste des effectifs, dessine deux cercles qui s’agrandissent : à pied et en véhicule. Au poste, le même dessin s’ouvre en cliquant l’indicatif.

---

## Overwatch 1.5.71 / Athena 1.0.117 — 2026-09-13

### Correction — Quick Picture

Chaque photo prise depuis Quick Picture part au poste. La suivante n’est plus traitée comme un doublon de la vue précédente.

---

## Overwatch 1.5.70 / Athena 1.0.117 — 2026-09-13

### Nouveau — Messagerie : d’abord les canaux

En ouvrant Messagerie, la liste des canaux s’affiche avec le nombre de messages non lus. La section Création en bas permet d’ouvrir un nouveau canal.

### Nouveau — Fil complet d’un canal

Un clic ouvre tout le fil. Retour à la liste par Canaux. Date, retour à la ligne, couleurs selon l’auteur et le canal.

### Amélioration — Messages limités et lisibles

Un message tapé dans le téléphone est limité à 100 caractères et passe à la ligne.

---

## Overwatch 1.5.69 / Athena 1.0.116 — 2026-09-13

### Correction — Barre de liaison en bas

La barre OK / NOK se replace en bas de la carte du téléphone, y compris avec l’interface tablette actuelle.

### Correction — Menu ACE Athena rangé

Les anciennes actions à plat sous COMSPEC Athena sont retirées. Les rubriques reviennent au premier niveau.

---

## Overwatch 1.5.68 / Athena 1.0.115 — 2026-09-13

### Correction — Pack complet rechargé

Le pack Workshop / FN inclut maintenant toutes les nouveautés du jour. Rechargez le pack et quittez Arma complètement.

### Nouveau — Supprimer un canal dans Messagerie

Le bouton Supprimer retire le canal radio personnalisé sélectionné. Les canaux système restent protégés. L’historique disparaît aussi du journal du poste.

### Correction — Messagerie et transmissions après connexion

Après une connexion Athena par mot de passe, les messages et les autres envois vers le poste restent autorisés. Plus de session refusée juste après l’ouverture du canal.

### Amélioration — Boutons Messagerie

Créer, Supprimer, Envoyer et Effacer l’affichage local répondent de façon fiable au clic.

---

## Overwatch 1.5.56 / Athena 1.0.101 — 2026-09-12

### Amélioration — Découpage d’étage sous JVN

Sur un bâtiment désigné, le découpage d’étage (désactivé par défaut) coupe la silhouette au plafond choisi, met en évidence la dalle et marque les points intérieurs. ACE permet de changer d’étage. Les murs ne s’ouvrent pas (limite du moteur) ; ce n’est plus un réglage « à venir ».

---

## Overwatch 1.5.55 / Athena 1.0.101 — 2026-09-12

### Amélioration — Messagerie : canaux, fil et Groups IceMan

Dans Messagerie, vous créez un canal radio personnalisé depuis le téléphone. Le fil indique clairement De / Vous / Du poste avec un meilleur contraste. L’entrée IceMan Groups / Group Messages disparaît du tiroir : l’opérateur arrive sur Messagerie COMSPEC, en français.

---

## Overwatch 1.5.54 / Athena 1.0.99 / liaison 2.0.32 — 2026-09-12

### Correction — Marqueurs téléphone → carte du poste

Les repères posés sur le téléphone (INF, Marker Widget, cTab) remontent sur la carte Athena dès que le canal poste est ouvert, même si Effectifs est encore vide. Une liaison momentanément dégradée ne fait plus perdre le marqueur.

---

## Overwatch 1.5.52 / Athena 1.0.97 — 2026-09-12

### Correction — Position invisible au poste malgré liaison OK

Dès que le canal poste est ouvert, la position part automatiquement vers le poste. Vous apparaissez dans Effectifs sans ouvrir Transmettre. Si un frein temporaire s’applique, la fiche Athena l’indique clairement.

---

## Overwatch 1.5.49 / Athena 1.0.93 — 2026-09-12

### Nouveau — Messagerie ATAK avec canaux

Application **Messagerie** COMSPEC dans le téléphone : canaux radio (Groupe, Commandement, Général, JTAC, Air + personnalisés), fil par canal, envoi vers le poste. Libellés en français. Groups / Group Messages IceMan est masqué au profit de Messagerie.

---

## Overwatch 1.5.43 — 2026-09-11

### Nouveau — Affichage situation sous toutes les JVN

Sous jumelles de vision nocturne, les alliés, marqueurs et véhicules proches apparaissent dans le champ de vision avec la distance. Depuis ACE, **Marquer ce bâtiment** dessine la silhouette du bâtiment regardé. Si le mod F-PANO ECOTI est déjà chargé, Overwatch n’affiche pas le sien. Réglages : Options → Extensions → COMSPEC Overwatch → Affichage situation. Relancer Arma complètement.

---

## Athena 1.0.88 / Overwatch 1.5.37 / liaison 2.0.27 — 2026-09-11

### Correction — Tenues communauté denses et collections

Dans l’arsenal Athena, la première collection s’ouvre dès l’affichage. L’aperçu montre lunettes, jumelles, JVN, radio et le contenu des poches. Les tenues très chargées se chargent correctement (plus de blocage « trop volumineuse »). La liste communauté n’est plus coupée quand il y a beaucoup de tenues. Relancer Arma complètement.

---

## Athena 1.0.88 / Overwatch 1.5.36 / liaison 2.0.26 — 2026-09-11

### Nouveau — Canaux radio, Wave, zones de vue

Canaux radio (Groupe, Commandement, Général, JTAC, Air + personnalisés), pastilles Wave sur la carte, ETA d’itinéraire, zones de vue terrain vers le poste.

### Nouveau — Barre de liaison OK / NOK

Sous la barre d’état du téléphone : OK ou NOK, débit estimé, taux de perte ; teinte de l’icône signal.

### Amélioration — Première liaison, Paramètres, arsenal

Parcours Appairage clarifié, Paramètres / Athena plus lisibles, arsenal Mes tenues / Communauté, remontée forcée du temps de mission.

### Correction — Radio, fantômes carte, Appairer

Journal radio jeu ↔ poste rétabli ; opérateurs hors liaison retirés de la carte ; canal poste rouvert après Appairer ; temps de mission à nouveau remonté.

Relancer Arma complètement. Portail : Ctrl+F5 + migrations si besoin.

---

## Athena 1.0.83 / Overwatch 1.5.27 — 2026-09-11

### Correction — Indicatif, nom, rôle et groupe BFT

Sur la carte, les trois lignes d’identité (indicatif, nom, rôle) restent visibles en bas à droite. L’identifiant de groupe saisi dans les données techniques (ex. Alpha 2-2) est synchronisé vers le suivi d’effectif et remonté au poste. Relancer Arma complètement après mise à jour du pack et de la liaison.

---

## Athena / Overwatch — 2026-09-11

### Correction — Effectifs réellement en liaison

La position d’un joueur est désormais transmise même si son identifiant Steam tarde à être disponible après une arrivée en cours de partie ; la liaison Athena authentifiée suffit pendant ce bref délai. Sur la carte web, les joueurs déconnectés disparaissent dès leur passage hors liaison, tandis que les dernières positions des IA suivies restent disponibles pour le COP. Relancer Arma complètement après la mise à jour du mod.

---

## Athena — 2026-09-10

### Correction — Encart d’identité sur la carte

Sur la carte du téléphone, un encart se superpose comme les outils carte : indicatif, nom, groupe, fonction et position. Il reste en bas à gauche, au-dessus des outils, hors du tiroir. Le menu d’applications est désormais reconstruit à chaque nouvelle ouverture d’écran et l’identité native reste visible en secours si l’encart ne peut pas être créé. Relancer Arma complètement.

---

## Portail 1.5.98 — 2026-09-04

### Administration — remise à zéro ATAK et Steam

L’effacement des remontées ATAK propose maintenant une option explicite pour désynchroniser tous les comptes Steam de la communauté. Lorsqu’elle est cochée, les identifiants sont retirés et les sessions de jeu ainsi que les associations de poste sont révoquées ; les opérateurs doivent ensuite refaire leur liaison.

---

## Portail 1.5.97 — 2026-09-03

### Correction — Grandes photos terrain

Les captures PNG Arma de plus de 16 Mo sont désormais acceptées par le poste de commandement. Le plafond passe à 96 Mo par image, avec la marge nécessaire au formulaire d’envoi. Déployer le portail et laisser PHP recharger sa configuration.

## Overwatch 1.5.16 — 2026-09-03

### Correction — Photos en rafale

Une photo prise depuis le téléphone n’écrit plus une série de copies identiques dans le dossier Captures. Un déclenchement = un fichier. Relancer Arma complètement.

### Correction — Identifiant de groupe

L’identifiant du groupe en jeu reprend l’indicatif de l’opérateur lorsque le champ contenait encore le nom de profil. Un nom de groupe déjà choisi n’est pas modifié. Relancer Arma complètement.

---

## Athena 1.0.80 — 2026-09-03

### Correction — Photos en rafale

Une photo prise depuis le téléphone n’écrit plus une série de copies identiques dans le dossier Captures. Relancer Arma complètement.

### Correction — Bandeau sous l’heure

Sous l’heure du téléphone, une bande noire affiche l’indicatif, le rôle, la grille et la radio.

### Changement — Tuile Athena

La tuile Athena ne garde qu’un bouton Connexion, qui devient Liaison OK une fois le compte associé. Un journal court indique si Steam n’est pas lié ou si le compte n’est pas connecté. Quand tout est en ordre, le nom, le prénom, le rôle, la fonction et l’affectation s’affichent. Relancer Arma complètement.

---

## Overwatch 1.18.10 — 2026-09-03

### Correction — Photos terrain refusées

Les photos prises depuis le téléphone arrivent de nouveau au poste. Le cliché n’est plus refusé alors qu’il existe déjà sur le PC. Relancer Arma complètement.

---

## Overwatch 1.5.15 / SSE 0.7.20 — 2026-09-03

### Correction — Cockpit Hatchet

En s’asseyant aux commandes d’un appareil Hatchet, le menu d’actions personnelles se referme. Clic et molette reviennent au tableau de bord : batterie, groupe auxiliaire et démarreurs répondent de nouveau. Relancer Arma complètement.

---

## Overwatch 1.18.9 — 2026-09-03

### Correction — Identifiant Steam sur la liaison de secours

L’identifiant Steam reste associé au lancement, y compris si la liaison passe par le chemin de secours. Une position sans identifiant n’est plus envoyée : le jeu réessaie dès que Steam est prêt. Le journal Liaison n’enregistre plus qu’un refus « identifiant manquant » de temps en temps, plus une ligne toutes les secondes. Relancer Arma complètement.

---

## Athena 1.0.79 — 2026-09-03

### Correction — Bouton zoom et bandeau d’identité

Le bouton moins qui se décalait en haut à droite de la carte est retiré. Sous la météo et l’heure, une bande noire affiche l’indicatif, le rôle, la grille et la fréquence radio. Relancer Arma complètement.

---

## Athena 1.0.78 — 2026-09-03

### Correction — Carte du téléphone

Le cartouche Indicatif / Rôle / Groupe / Grille est de nouveau visible, en bas à gauche, au-dessus des outils carte. Le menu qui s’ouvrait au clic droit et les boutons Mesure, Grille, Itinéraire, Zone, Couches et Signet sont retirés. Les outils carte du téléphone restent affichés, sans clignoter. Relancer Arma complètement.

---

## Athena 1.0.77 — 2026-09-03

### Correction — Liaison Steam, fiche opérateur, photos

La session Athena s’ouvre automatiquement lorsque Steam est déjà associé au compte. La fiche opérateur se transmet de nouveau. Les photos prises sur le terrain partent au poste.

### Correction — Carte du téléphone

Le bouton des outils carte retrouve son aspect d’origine. Le cartouche Indicatif / Rôle / Groupe s’affiche en haut à gauche, sous la boussole, sans recouvrir les outils.

### Amélioration — Page Athena

Journal, Alerter, Rapporter et Poste occupent chacun leur écran. Les boutons ne se superposent plus.

### Nouveau — Carte : filtres, mesure, signalements

L’indicatif d’un opérateur se lit plus simplement quand on s’éloigne, et redevient détaillé en se rapprochant. Un opérateur sans position récente reste visible, plus pâle. Un clic droit pose un marqueur, mesure ou signale. Un ordre reçu se confirme depuis le bas de l’écran. Les outils carte du téléphone restent tels quels.

---

## Athena 1.0.76 — 2026-09-02

### Correction — Cartouche indicatif / rôle / groupe

Sur la carte du téléphone, le cartouche d’identité (indicatif, rôle, groupe, grille) est de nouveau en bas à gauche. Il n’est plus coincé sous le tiroir d’applications. Relancer Arma complètement.

---

## Portail 1.5.96 — 2026-09-02

### Correction — Barre de vue et bande Réseau / Journal

La barre N, 2D, 3D et Zoom reste à droite, sous Outils, sans passer derrière la carte. Réseau et Journal s’affichent dans une bande lisible en bas. Rechargez la page du poste.

---

## Portail 1.5.95 — 2026-09-02

### Correction — Fantômes hors liaison

Un opérateur bloqué à l’écran de connexion, ou sans signal récent, n’est plus affiché En liaison. Les anciennes positions et les anciens indicatifs quittent la carte et le relief. Rechargez la page du poste.

---

## Portail 1.5.94 — 2026-09-02

### Amélioration — Certificats sur les fiches terminaux

L’onglet Terminaux du poste affiche l’état du certificat, sa référence et son échéance. Un compte connecté peut en émettre un nouveau ; l’ancien n’est plus accepté. Rechargez la page du poste.

---

## Overwatch 1.5.12 — 2026-09-02

### Correction — Charge Uniquement depuis ATAK

Une charge réglée sur Uniquement depuis ATAK saute désormais en jeu quand vous la déclenchez depuis la tablette ou le poste. Le poste n’affiche « A explosé » qu’après. Le choix n’apparaît plus deux fois dans ACE. Relancer Arma complètement.

---

## Carte du poste — 2026-09-02

### Correction — Opérateurs illisibles sur la carte

Les opérateurs en liaison affichent de nouveau leur indicatif sous le symbole, y compris à plat. Rechargez la page du poste.

---

## Overwatch 1.5.11 / Athena 1.0.75 — 2026-09-01

### Amélioration — Compte non connecté

Sans compte associé, un bandeau ambre « Compte non connecté » s’affiche sur la carte, au-dessus de l’indicatif, et en haut de la tuile Athena. Relancer Arma complètement.

---

## [1.5.51] / Overwatch 1.5.10 / Athena 1.0.72 — 2026-09-01

### Corrigé — Boussole et outils carte

La boussole en haut à gauche et les outils carte en bas à gauche ne sont plus recouverts. Les cartouches de grille et d’unité passent à droite. Relancer Arma complètement.

---

## [1.5.50] / Overwatch 1.5.0 / Athena 1.0.63 — 2026-09-01

### Corrigé — Identité, chat et journal

Le pack affiche l’opérateur, pas le titre de la communauté. Relancer Arma complètement.

- Écran Environnement prêt : photo si elle existe, prénom et nom, indicatif, rôle, grade et fonction.
- Téléphone Paramètres et bandeau de carte : indicatif de la fiche Effectifs.
- Les messages du poste restent dans le téléphone et le journal ; le chat de bord d’Arma n’est plus recopié.
- Journal Athena du téléphone : filtre, liste et détail lisibles.

### Carte du poste (web)

- Les symboles des opérateurs suivent la taille d’icône et de libellé des réglages.
- Le journal d’activité n’ajoute plus une carte à chaque position reçue.
- Si la liaison est bonne mais qu’un accès manque, une fenêtre permet de demander les autorisations.

---

## [1.5.48] / Overwatch 1.4.97 / Athena 1.0.58 — 2026-09-01

### Nouveau — Lecture de la tablette ATAK (IceMan)

Sur la tablette ATAK Enhanced, la carte se lit désormais comme un poste de terrain : fond charbon, chiffres cyan, cartouches sous le curseur et sur l’unité suivie. Le tiroir d’applications à droite n’est pas recouvert. Relancer Arma complètement.

- Sous le curseur : grille, distance, altitude du sol, gisement, portée et écart d’altitude.
- Sur l’unité suivie : groupe, indicatif, grille, altitude, vitesse et heure.
- Cap en degrés vrais en haut à gauche, zoom plus et moins sur le bord de la carte.
- Le tiroir d’applications, Drone Ops et les fenêtres caméra déjà présentes reprennent le même charbon / cyan.

### Nouveau — Connexion Athena avant la session

Au menu principal, l’opérateur s’identifie (e-mail, code temporaire, ou Steam déjà associé). La communauté, l’indicatif et les habilitations arrivent tout seuls. Rien n’est transmis tant que l’environnement n’est pas prêt.

### Carte du poste (web)

- Les rapports (observation, situation, renseignement immédiat, contact) apparaissent en pastilles compactes, comme en mission : type en capitales, barre colorée, temps écoulé.
- L’outil Route trace un itinéraire : les opérateurs le voient en jeu, les points déjà atteints passent en gris.
- La barre Position / Annoter / Tracer reste visible ; seul Masquer la replie, Outils la ramène.
- Réglages du poste : icônes de la communauté visibles ; le gestionnaire ouvre la bibliothèque pour en choisir ou en ajouter.
- Terminaux : l’état de liaison reste calé à droite ; la version Overwatch se lit sur sa propre ligne.

---

## [1.5.48] / Overwatch 1.4.96 / liaison 1.17.9 — 2026-09-01

### Pack Overwatch 1.4.96 — tablette, Zeus, arsenal

- La tablette ATAK Enhanced se charge à nouveau avec le pack actuel.
- Zeus : SSE, ATAK et OVERWATCH tiennent en haut du panneau d’édition d’une unité, plus sur les filtres des objets éditables.
- Arsenal ACE : bandeau des tenues Athena en haut de l’écran, sans masquer « Mes équipements ».
- Relancer Arma complètement.

---

## [1.5.48] / Overwatch 1.4.95 / liaison 1.17.9 — 2026-08-30

### Pack Overwatch 1.4.95 — guidage GPS + zones ATAK

Itinéraire posé au poste : points numérotés et trait visibles en jeu. Zones tactiques (poser, danger, ralliement) synchronisées ; alerte à l’entrée d’une zone dangereuse. Relancer Arma complètement.

---

## [1.5.34] / Overwatch 1.4.67 / extension 2.0.14 — 2026-08-24

### Corrigé — météo en erreur rouge au spawn

Le bandeau météo n’est plus envoyé pendant le handshake. Un timeout ou un refus temporaire ne s’affiche plus comme une panne. Dès que le poste a bien reçu, le terminal s’arrête de renvoyer le même ciel.

---

## [1.5.33] / Overwatch 1.4.66 / extension 2.0.13 — 2026-08-24

### Corrigé — rafale 0 / -1 / 503 au spawn

Si Athena ne répond pas tout de suite (timeout, saturation), le terminal ne réessaie plus en boucle les caméras, la météo, les photos et la position. Il attend quelques secondes, puis reprend. Une photo refusée pour saturation part dès que le poste respire, sans marteler le même cliché. Le journal ne traite plus ça comme une panne rouge.

---

## [1.5.32] / Overwatch 1.4.65 — 2026-08-24

### Corrigé — IA alliée sur l’ATAK

Le suivi posé depuis Zeus reste après la fermeture du curateur. On peut le retirer (menu « Retirer l’IA de l’ATAK » ou case décochée), sans étendre tout le groupe.

---

## [1.5.31] / Overwatch 1.4.64 / Athena ATAK 1.0.46 — 2026-08-24

### Amélioré — sons ATAK (web et jeu)

Nouveau pack d’alertes, identique sur la carte web et en mission : bip radio court, carillon de réception d’ordre, confirmation d’acceptation, transmission de renseignement, signal médical Motorola (trois fois), démarrage.

---

## [1.5.30] / Overwatch 1.4.63 / extension 2.0.12 — 2026-08-24

Vague 2026.08c — voir SPOTREP #00001 pour le détail opérateur.

- Poste de situation (dossiers SSE + localisation téléphone)
- Parc de terminaux (retrait appareils / sessions web)
- Relecture : joueurs, IA alliées, téléphones, GPS
- Relief autour de l’équipe (`getTerrainHeightASL`), plus de spam 401
- Overlays liaison, Zeus SSE/ATAK/Overwatch, proximité téléphone

---

## [1.4.50] / extension 2.0.10 — 2026-08-23

### Corrigé — lancement et signalement

Fenêtre Windows unique au menu principal (conditions d’utilisation + disclaimer bêta). Plus de parade de dialogues en mission. Le signalement in-game s’ouvre depuis Échap → gestion du mod et part bien vers l’équipe.

---

## [1.4.49] / Athena ATAK 1.0.42 — 2026-08-23

### Corrigé — gel à la photo ATAK

Les clichés ATAK (JPEG BCE / IceMan) ne déclenchent plus un second `screenshot` PNG synchrone sur le thread jeu. Aligné sur le flux SOAR Discord : un JPEG, envoi en file. Un PNG de repli n’est pris que si le fichier JPEG est vraiment introuvable, hors de la frame du clic.

---

## [1.4.19] - 2026-08-18

### Ajouté — Fiches de renseignement simplifiées

Entre « rien à signaler » et « j'ouvre un dossier d'intérêt », il manquait la marche la plus basse : noter tout de suite ce qu'on vient de voir, sans formulaire à remplir et sans rien conclure. Une plaque relevée, une attitude inhabituelle, un axe soudain désert, une conversation avec un habitant — autant d'éléments qui se perdaient parce que les consigner coûtait plus cher que de les oublier.

- **Menu dédié RENS dans le tiroir ATAK.** Le rédacteur s'ouvre en plein cadre sur **toute la surface de l'ATAK**, pas dans un panneau de téléphone : on choisit ce menu pour écrire, pas pour lire. Également accessible par l'icône « Fiche RENS » de l'écran d'accueil ATAK et par l'action ACE « Rédiger une fiche de renseignement… ».
- **Rédacteur identique dans le portail** (`Pilotage → Fiches de renseignement`) : même disposition, mêmes libellés, mêmes limites. Un opérateur qui passe du jeu au portail ne réapprend rien.
- **Cinq informations, pas une de plus** : un texte libre de 1000 caractères avec compteur vivant, une date, un lieu, un à quatre thèmes, et jusqu'à quatre pièces jointes. Le reste — classement, qualification, rapprochements — se fait ensuite au bureau.
- **Cinq types de fiche** (mission, observation, contact, ambiance, technique) et **douze thèmes** colorés par gravité, qui orientent la fiche vers le bon analyste.
- **Pièces jointes depuis le terrain** : capture de la scène, photographie déjà prise dans la bibliothèque ATAK, ou relevé de position et d'instant. Depuis le portail : photo, capture, PDF ou texte.
- **Suivi côté bureau** : prise en compte, exploitation ou classement sans suite, avec une phrase de justification, et rattachement facultatif à un dossier validé. Le rattachement fait apparaître la fiche dans le dossier sans rien conclure sur les personnes citées.
- Chaque fiche alimente le **journal des transmissions terrain** et le journal d'activité, comme les fiches personnes et les sites.

### Choix de conception

- **Le brouillon survit à la fermeture du rédacteur.** Une fiche est presque toujours interrompue — contact, déplacement, ordre radio. Perdre la saisie à chaque fermeture aurait appris aux opérateurs à ne plus rien rédiger, ce qui est exactement l'inverse du but.
- **Les captures d'écran sont prises après la fermeture du rédacteur.** Prises pendant, elles n'auraient montré que l'interface. Le rédacteur se referme, la scène redevient visible, la capture part.
- **Une clé d'idempotence par fiche.** Une double validation, ou une retransmission après coupure de liaison, retombe sur la même fiche. Sans elle, le bureau recevrait deux fois le même constat — précisément ce que l'automatisme de détection de doublons signale ensuite comme suspect.
- **Liaison coupée : la fiche est conservée, pas perdue**, et le message le dit ainsi. L'annoncer comme un échec pousserait à ressaisir, et deux fiches du même constat arriveraient au rétablissement.
- **Le référentiel des libellés est dupliqué côté jeu**, pour rester lisible sans liaison. L'ordre des thèmes est contractuel — chaque bascule du rédacteur est câblée sur un rang, pas sur un code — et un test compare les deux référentiels pour empêcher la dérive.
- **Une fiche n'identifie personne et ne vaut pas preuve.** Le rappel figure dans le rédacteur, dans la file et sur la fiche : c'est un constat daté et situé, rien de plus.
- **Les pastilles de thème sont de vrais contrôles**, pas du texte structuré : Arma ne sait pas peindre un fond derrière un fragment de texte, et des étiquettes sans couleur auraient perdu la lecture d'un coup d'œil qui fait tout l'intérêt du bandeau.

---

## [1.4.14] - 2026-07-30

### Ajouté — Corrélation d'exploitation

- **Graphe de corrélation du dossier** (`Dossiers → Voir les corrélations`) : personnes, sites, pièces et saisies, classés par nombre de liens. Trois provenances distinguées visuellement et jamais confondues — **déduit** (recalculé depuis les saisies), **automatisme** (posé par une règle), **analyste** (hypothèse assumée).
- Les liens déduits ne sont pas stockés : corriger une fiche corrige le graphe. Un lien stocké se périmerait dès qu'une saisie est rectifiée, et personne ne penserait à aller le corriger.
- **Pose de relation à la main** entre deux personnes du dossier, avec nature du lien, niveau de fiabilité et justification. Retirable.
- Table `sse_relations`, clé unique par arête : réenregistrer la même relation met à jour la fiabilité et la note plutôt que de dupliquer.

### Ajouté — Automatismes SSE

Un automatisme propose, il ne décide pas. Aucune règle ne clôt un site, ne fusionne des fiches ni ne déclare une identité — une règle qui se trompe en silence coûte plus cher que dix rappels à faire à la main.

- **Classement automatique** — une fiche transmise sans code dossier rejoint le dossier ouvert **s'il n'y en a qu'un**. Avec plusieurs dossiers ouverts, la règle s'abstient : une fiche non classée se voit, une fiche mal classée passe inaperçue jusqu'au débriefing.
- **Doublon probable** — deux fiches portant le même relevé biométrique sont signalées et reliées par « même individu que ». Aucune fusion : elle détruirait la fiche la moins complète, qui est parfois celle qui porte l'observation utile.
- **Correspondance forte** — au-delà de 85 % de similarité avec une liste de surveillance, le dossier passe en exploitation et reçoit une note. Le libellé rappelle qu'un score n'est pas une identification.
- **Co-présence** — les fiches du même dossier saisies à moins de 45 minutes d'écart sont reliées, en « non vérifié » : c'est une proximité d'horodatage, pas un lien constaté. Plafonné à 5 liens par fiche.
- **Site prêt pour clôture** — checklist complète signalée au poste de commandement, sans clôturer.
- **Saisie sensible** — armement, munitions, supports numériques et documents remontent immédiatement, sans attendre la clôture du site.
- Chaque règle laisse une trace en clair dans le journal d'activité : on peut répondre à « pourquoi cette fiche est-elle dans ce dossier ? » sans lire le code. Les réponses d'API portent un champ `automation` déjà rédigé en français.

### Ajouté — Déclassification et caviardage

- **Version expurgée du dossier** (`Dossiers → Version expurgée`) : on choisit le niveau de diffusion visé, tout ce qui est au-dessus part au noir automatiquement. Cinq catégories caviardables — Identité, Lieu, Biométrie, Source, Horodatage — chacune avec son niveau minimal de lecture en clair.
- **La source est la catégorie la plus protégée** : on peut souvent dire *ce qui* a été trouvé sans dire *qui* l'a trouvé, l'inverse est rarement vrai.
- **Caviardage manuel** : noircir une zone précise sur une fiche précise, quel que soit le niveau, avec motif obligatoire — c'est lui qu'on relira pour décider de le lever. Levable à tout moment.
- La page ouvre par défaut sur le niveau le plus large, donc le plus caviardé : ouvrir l'écran ne doit jamais exposer plus que ce qu'on a demandé à voir. Un tableau annonce ce qui restera en clair **avant** de produire le document.
- **Le texte caviardé n'est jamais envoyé au navigateur.** La substitution est faite côté serveur. Un trait noir posé en habillage CSS laisserait le texte dans la page — copier-coller, code source, lecteur d'écran, cache — ce qui reviendrait à ne rien caviarder.
- La longueur des barres est quantifiée par pas de 4 et plafonnée : une barre exactement proportionnelle révélerait la longueur du nom, ce qui suffit souvent à identifier quelqu'un sur un dossier à trois personnes.
- Le caviardage est branché sur la source unique des deux comptes rendus : une catégorie ne peut pas être noircie dans le flash et lisible dans le compte rendu initial.
- La date de recueil reste, l'heure part : savoir « le 14 » n'a pas la même valeur que savoir « le 14 à 03h12 ».

### Ajouté — Habilitation de lecture SSE

- **Plafond d'habilitation par session** : le niveau de diffusion demandé sur l'écran de déclassification est rabattu sur ce que la session a le droit de lire. Le paramètre de l'adresse exprime une demande, il n'accorde rien — le forcer à la main sert la version autorisée et inscrit la tentative au journal.
- Trois permissions explicites (`atak.sse.clearance.encadrement`, `.confidentiel`, `.tres_restreint`), et à défaut un **report des rôles déjà en place** : administration → très restreint, gestion des dossiers → confidentiel, accès portail → encadrement, rien → interne. Sans ce report, la mise à jour mettrait tout le monde au plancher tant qu'un administrateur n'a pas assigné les nouvelles permissions.
- **Habilitation portée par un code d'accès invité**, choisie à l'émission. Par défaut Diffusion interne : un invité ne voit ni identité, ni lieu, ni source. On n'accorde jamais par défaut de valeur, seulement par défaut de refus.
- L'écran affiche l'habilitation **et d'où elle vient**. Une habilitation qu'on ne peut pas expliquer se conteste mal.
- Les niveaux au-dessus du plafond apparaissent verrouillés au lieu de disparaître : un refus doit se voir refusé, pas manquer.
- Un dossier tenu au-dessus de l'habilitation du lecteur est signalé. La consultation reste possible — verrouiller d'un coup des dossiers ouverts hier est un arbitrage d'exploitation, pas une décision technique.

### Corrigé

- **L'écran de déclassification ne vérifiait pas qui demandait quoi.** Le niveau était lu tel quel dans l'adresse : n'importe qui pouvant ouvrir un dossier, invité compris, obtenait la version intégrale en changeant un paramètre. Produire un document expurgé et restreindre qui peut le lire sont deux choses différentes ; la première seule ne protégeait rien.

### Ajouté — Diffusion dirigée des rapports tactiques (phase A)

- Le moteur de règles de diffusion **existait sans aucun appelant** : `atak_report_routing_rules`, `atak_report_routing_history` et `AtakReportRoutingRepository` étaient en place, avec conditions, destinataires, escalade et accusé de réception, mais rien ne les invoquait. Le chantier avait été commencé puis laissé avant branchement.
- Les règles s'appliquent désormais à la soumission d'un rapport tactique. Les destinataires apparaissent dans la réponse (`routed_to`) et sur la consultation du rapport (`routing`).
- **Pas d'interrupteur, volontairement** : sans règle enregistrée, le moteur ne route vers personne et n'écrit rien. Une table de règles vide *est* l'état désactivé, et ajouter un réglage donnerait deux endroits à vérifier quand un rapport n'arrive pas.
- Un échec de routage n'échoue jamais la soumission : le rapport est enregistré d'abord, la diffusion tentée ensuite et tracée si elle échoue. Perdre un compte rendu de contact parce qu'une règle est mal formée serait un échange calamiteux.
- **La cible a changé par rapport à la demande initiale.** Le branchement devait porter sur `atak_intel` ; cette table n'a ni `report_type`, ni `priority`, ni `tenant_id`, et la clé étrangère de l'historique de routage pointe sur `atak_tactical_reports`. Router `atak_intel` demanderait d'altérer une clé étrangère en base vivante et d'ajouter un cloisonnement à une table qui n'en a pas — ce n'est pas un branchement mais une phase de schéma, renvoyée à la suite du plan.

### Ajouté — Écran des règles de diffusion

- `/admin/atak-diffusion-rapports` : création, activation et suppression des règles. Sans lui, le moteur branché en phase A tournait à vide, faute de règle à appliquer.
- Conditions exprimées en clair — types de rapport, priorités, mots-clés — plutôt qu'en JSON brut. Aucune case cochée signifie « tous », ce qui est écrit sous chaque groupe.
- **Une règle sans destinataire est refusée** : elle donnerait l'illusion d'une diffusion en place tout en n'en produisant aucune.
- L'écran annonce lui-même qu'une liste vide est l'état normal après installation, pas une panne.
- **Les notifications ne sont pas émises, et l'écran le dit.** La diffusion enregistre qui doit lire et l'affiche sur la fiche du rapport ; l'envoi en jeu, par courriel ou vers Discord n'est pas branché. Mieux vaut le lire à l'écran que le découvrir en opération.

### Ajouté — Émission réelle des notifications de diffusion

- Une diffusion produit désormais une **notification effective**, écrite dans `atak_realtime_notifications` avec ses destinataires, sa position sur la carte et son urgence.
- **Une seule notification par rapport**, portant tous les destinataires — et non une par destinataire. Trois lignes identiques dans le bandeau pour un même compte rendu font passer l'alerte pour du bruit, et c'est le bruit qu'on finit par ignorer.
- L'urgence reprend celle du rapport : un contact `FLASH` s'affiche en critique, une routine en bas de pile. Un compte rendu immédiat n'a pas à ressembler à une routine.
- Expiration à deux heures : une alerte qui reste affichée une semaine devient un décor.
- **Nouvelle route de relève `GET /api/atak/notifications`.** `AtakNotificationRepository` avait `create()`, `listActive()` et `pollSince()` mais **aucune route ne l'exposait** : les notifications écrites n'étaient lisibles par personne. Sans cette relève, émettre revenait à écrire dans un tiroir fermé.
- Une notification qui échoue n'annule pas la diffusion — elle est tracée avec la mention explicite « destinataires à prévenir de vive voix », pour que le silence ne passe pas inaperçu.

### Corrigé — L'historique de diffusion affirmait des notifications jamais envoyées

- `createRoutingEntry()` inscrivait `notification_sent = 1` et le canal « in-game » alors qu'aucun envoi n'avait lieu. Le drapeau est désormais posé **après** émission réelle, par `markNotified()`, et décrit donc ce qui s'est passé.

### Corrigé — Angle mort du contrôle d'intégrité

- Le contrôle ne vérifiait que le premier argument de `view()`. Or le back-office passe la vraie vue par `'content' => …`, la mise en page seule étant en premier argument : **une vue de back-office absente échappait entièrement au contrôle**, alors que c'est précisément le cas qui produit une page blanche en HTTP 200. La couverture passe de 68 à 365 vues.

### Corrigé — Deux lectures inter-communautés sur les rapports tactiques

- `GET /api/atak/reports/{id}` chargeait le rapport **sans filtrer sur la communauté** : un identifiant deviné suffisait à lire le rapport d'une autre communauté.
- `POST /api/atak/reports/{id}/acknowledge` de même — et acquitter est un acte, pas une lecture.
- Les deux sont désormais cloisonnés. `atak_report_routing_history` ne portant pas de `tenant_id`, la lecture de l'historique de diffusion s'appuie sur le cloisonnement du rapport, ce qui est maintenant vrai et documenté à l'endroit qui en dépend.
- Même classe de défaut relevée sur `AtakPoiRepository` et `AtakMedevacRepository`, hors périmètre de cette phase et signalée dans le plan.

### Corrigé — Cinq appels réseau étaient rejetés en silence

`CfgRemoteExec >> Functions` est en `mode = 1`, c'est-à-dire liste blanche stricte : une fonction absente de la liste voit ses appels distants **rejetés sans message**. Cinq fonctions y manquaient alors qu'elles sont bien appelées via `remoteExec`.

- **`receiveOrder`** — les ordres émis n'arrivaient pas à leur destinataire en multijoueur.
- **`createRoleplayZoneFromZeus`** et **`createRoleplayZone`** — les zones posées depuis Zeus n'étaient jamais créées côté serveur.
- **`restoreAtakSession`** et **`clearDisconnectedAtakState`** — la reprise de session après plantage ne se faisait pas.

Chaque entrée porte un `allowedTargets` correspondant à la cible réelle de l'appel, au plus juste plutôt qu'au plus permissif.

- **Le moteur roleplay n'était pas activé sur les autres machines**, ce qui faisait paraître les zones inertes. L'activation passait par `remoteExecCall ["call", 0, true]`, or `Commands` est aussi en liste blanche et `call` n'y figure pas — l'appel était rejeté. L'y ajouter aurait ouvert l'exécution de code arbitraire à distance à n'importe quel client ; l'activation passe désormais par des variables publiques, ce qui couvre en plus les joueurs qui rejoignent en cours de partie — le drapeau JIP de l'ancien appel ne le pouvait pas, `jip = 0` étant posé dans la configuration.
- Seuls les deux réglages désactivés par défaut sont forcés. « Effets visuels de dégradation » est déjà actif par défaut : le forcer n'aurait touché que les joueurs l'ayant volontairement coupé.

### Corrigé — Administration : l'audit système était inaccessible

- **`SystemAuditController` ne se chargeait pas** : une méthode `rollback()` y était déclarée deux fois — un accesseur privé vers le service de reprise, et l'action de route publique. PHP échoue à la compilation de la classe, ce qui rendait inaccessibles les quatre routes `/admin/audit` (consultation, détail, reprise, alerte). `php -l` ne le détecte pas : ce n'est pas une erreur d'analyse syntaxique, ce qui explique que le défaut soit passé inaperçu.
- L'accesseur est renommé `rollbackService()`.

### Corrigé — Ce qui était saisi hors liaison était perdu

- Le mod simulait la coupure réseau mais **perdait les données saisies pendant**. Une fiche SSE renseignée dans une cave sans couverture partait dans le vide ; l'opérateur ne l'apprenait qu'au débriefing. La file d'attente existante ne couvrait que les marqueurs de carte.
- **Tampon hors ligne** pour tout ce qu'un humain rédige : fiche SSE et relevés, point d'intérêt, MEDEVAC, QRF. Rejoués dans l'ordre au rétablissement de la liaison.
- **Les positions ne sont jamais rejouées**, délibérément : restituer une position vieille de dix minutes montrerait l'élément là où il n'est plus. Une position périmée trompe le poste de commandement au lieu de l'informer.
- Le tampon vit dans le profil du joueur et survit à un plantage ou une reconnexion — la coupure qui fait perdre des données est rarement propre. Les entrées sont horodatées sur l'horloge murale et non sur le temps de mission : ce dernier repart à zéro à chaque partie, si bien qu'une entrée aurait été relue avec un âge négatif à la session suivante, donc jamais périmée — une demande MEDEVAC de samedi se serait rejouée le week-end suivant.
- Temporisation croissante entre les tentatives : une liaison qui vient de revenir est souvent instable, et marteler l'extension produit une salve d'échecs qui ressemble à une panne.
- **Péremption assumée et annoncée** : au-delà de 30 minutes ou 5 échecs, l'entrée est écartée et l'opérateur en est informé. Un compte rendu de contact arrivant trois quarts d'heure après les faits se lirait comme une information fraîche. L'abandon n'est jamais silencieux — sinon l'opérateur croit avoir rendu compte.
- La saisie hors liaison affiche « fiche conservée, ne la ressaisissez pas » plutôt qu'une erreur : une ressaisie produirait deux fiches du même sujet, que l'automatisme A2 signalerait ensuite comme doublon.
- Compteur `N EN ATTENTE` dans la barre d'état du terminal SEEK : un tampon invisible ne vaut guère mieux qu'une perte.

### Corrigé — Le compte rendu et le PDF contournaient la déclassification

- **`/compte-rendu` servait le dossier intégral sans aucun contrôle d'habilitation.** Il suffisait de ne pas passer par l'écran de déclassification pour obtenir le même contenu en clair : l'écran expurgé ne protégeait donc rien. Le compte rendu est désormais servi au plafond du lecteur, avec un bandeau quand il est partiel.
- **L'export PDF produisait lui aussi le dossier intégral.** C'était le pire des trois : un PDF circule seul une fois transmis, un caviardage manquant ne se rattrape plus. L'export est rabattu, et le document porte un bandeau rouge indiquant son niveau de production et les catégories noircies — sans quoi une version expurgée est indiscernable d'une version complète une fois imprimée.
- La mention « Ce document est intégral » du compte rendu était devenue fausse ; elle est remplacée.

### Ajouté — Caviardage des écrans de travail

- Second interrupteur, distinct du verrou : registre des personnes, fiche dossier et corrélations rabattus sur l'habilitation du lecteur.
- **Désarmé par défaut**, pour une raison différente du verrou : ces écrans sont ce que la cellule regarde toute la séance, et l'identité étant classée « Confidentiel », les armer retire les noms à un simple membre. À armer une fois les habilitations réparties, ou après ajustement de la doctrine des catégories.
- Les caviardages manuels d'un dossier s'appliquent aussi sur ces écrans : une zone noircie à la main doit l'être partout, sinon le caviardage ne veut rien dire.
- Restent intégraux dans les deux régimes : registre des sites, fiche site, écran de croisement.

### Ajouté — Verrou d'ouverture par classification

- La classification d'un dossier peut désormais **fermer** le dossier, et plus seulement le signaler : fiche, personnes rattachées, notes, preuves, corrélations, compte rendu et export deviennent inaccessibles à qui n'a pas l'habilitation.
- **Désarmé par défaut.** La classification n'a jamais filtré depuis la création du portail : les valeurs déjà posées ont été choisies sans conséquence, et les armer d'office les transformerait rétroactivement en décisions d'exclusion que personne n'a prises.
- **Écran de revue avant d'armer** : le registre porte une colonne « Qui pourra encore l'ouvrir » sur chaque dossier, la répartition par classification, et le nombre de dossiers que le verrou fermerait au lecteur courant. Le portail ne mesure l'effet que pour la session en cours — il ne parle pas à la place des habilitations des autres.
- Armement réservé aux détenteurs du droit d'octroi : le verrou ferme des dossiers à d'autres, il ne doit pas être desserrable par celui qu'il gêne. Armement et désarmement partent au journal.
- Si la table de réglages est injoignable, le verrou est considéré désarmé. Un portail qui verrouille tout parce qu'une table manque est plus dangereux qu'un verrou temporairement inactif : on découvre le second, on subit le premier en pleine opération.
- Nouvelle table `sse_portal_settings` — le portail n'avait aucun stockage serveur pour un réglage, le thème passant par un cookie, ce qui ne convient pas à un verrou.

### Ajouté — Configuration mission maker et Zeus

- **Attributs Eden sur l'unité**, catégorie « COMSPEC — Exploitation SSE » : ce que la base doit répondre (génération automatique, inconnu, signalé, recherché), état civil, nationalité déclarée, langue, référence de dossier antérieur, indice de confiance imposé, graine. Poser un module par PNJ était intenable sur trente civils.
- **Trois modules Eden / Zeus**, catégorie « COMSPEC SSE » : *Dossier SSE actif*, *Profil d'identité SSE*, *Doter en terminal SEEK*. Variantes Zeus Enhanced avec boîtes de dialogue, ignorées automatiquement quand les modules de configuration sont déjà visibles — même garde anti-doublon que les zones roleplay.
- Les champs laissés vides ne sont pas écrits : un profil partiel complète la génération déterministe au lieu de l'écraser. On peut ne forcer que l'alias d'un sujet.
- Le module « Doter en terminal SEEK » ne dote que les joueurs : un terminal récupérable sur un cadavre ennemi n'est pas l'effet recherché.
- Fixer la graine rend un sujet identique d'une session à l'autre — pour un scénario rejoué ou une séance de formation.

### Documentation

- Nouveau [guide SSE chef de mission / Zeus](mod/UptoDate/docs/guide-sse-chef-mission.md) : préparation Eden, pilotage Zeus, tableau des six automatismes avec ce que chaque règle ne fait pas, lecture du graphe, trame de séance, limites assumées.
- Contrat d'API complété (table `sse_relations`, champ `automation`, variables d'unité réglables par le chef de mission).

---

## [1.4.13] - 2026-07-30

### Ajouté — Terminal SEEK

- **Terminal en pages, dans l'écran de l'appareil** : accueil à six tuiles (Sujet, Contexte, Biométrie, Constat, Photo, Dossier), navigation par flèches et bouton Home. Les contrôles sont posés dans la zone d'écran réelle de l'illustration, mesurée sur la texture ; les touches A1, A2, QUERY et SIGN sont posées sur le clavier de l'appareil.
- **Dossier SSE actif** : la référence est posée une fois pour l'élément, à l'arrivée sur objectif, puis héritée par toutes les fiches sans ressaisie. Visible dans la barre d'état du terminal, réglable depuis le menu ACE ou la page Dossier. Le champ de la fiche devient un repli manuel.
- **Requête d'identité** : bouton REQUÊTE, interrogation de la base fictive (6 s, barre ACE), verdict `Aucune correspondance` / `Correspondance possible` / `Correspondance confirmée` avec indice de confiance et référence de dossier. Affiché dans le panneau d'analyse et le bandeau LCD, transmis à Athena et repris sur la fiche du portail.
- **Résultat déterministe** : chaque personne reçoit une graine stable, dérivée de son identifiant réseau ou posée par le chef de mission. Deux interrogations du même sujet donnent le même verdict. La qualité des relevés module le résultat — une acquisition pauvre ne permet pas de confirmer.
- Le chef de mission peut imposer le verdict par variables d'objet : `COMSPEC_SSE_MatchResult`, `COMSPEC_SSE_Confidence`, `COMSPEC_SSE_RecordRef`.
- **Dotation du terminal SEEK** depuis Zeus (module Zeus Enhanced et module ACE Zeus), l'objet étant requis pour ouvrir une fiche.

### Ajouté — Exploitation de site

- Dossiers de site avec référence lisible, **checklist de fouille prégarnie selon le type** (habitation, dépôt, poste ennemi, cache, véhicule), saisies catégorisées rattachables à une pièce et à une personne, compte rendu de clôture.
- Endpoints `/api/sse/sites` (ouverture, consultation, pièces, saisies, clôture) et écrans portail « Sites exploités ».

### Ajouté — Portail SSE

- **Comptes rendus d'exploitation** : flash et compte rendu initial structuré (Situation, Exploitation du site, Personnel, Matériel, Faits marquants, Appréciation, Suites à donner), générés à la lecture depuis les éléments déjà versés au dossier. Remplace l'inventaire par un produit de renseignement : chaque personne y est reprise avec ses relevés et son verdict d'identité, chaque site avec les pièces non traitées.
- **Sites rattachés au dossier** : un site ouvert depuis le terrain avec la référence active rejoint le dossier, qui agrège désormais personnes, sites et saisies.

- **Charte « SSE Case File »** : palette de station de travail (vert `#12d18e`, trois couleurs sémantiques), Archivo condensé et JetBrains Mono, vignette à balayage sur les portraits, hachures pour les portraits absents.
- Portrait d'enrôlement et **chaîne de possession** sur la fiche personne — les événements étaient enregistrés depuis la 1.4.0 sans être affichés nulle part.
- Volumétrie des dossiers (personnes, notes, pièces) et jauge de similarité sur les croisements.

### Corrigé

- **Le terminal ne pouvait plus enregistrer de fiche** (HTTP 422 « identité requise » alors que l'alias était saisi) : l'échappement JSON tronquait les chaînes accentuées — « Décédée » apparaît systématiquement pour un sujet décédé — et n'échappait pas les caractères de contrôle. Le motif de refus du serveur est désormais affiché au lieu d'un générique « vérifiez la liaison ».
- **Panneau biométrique tronqué** : trois modalités sur deux lignes débordaient, l'ADN était coupé à l'écran.
- Diagnostic de résolution photo : les dossiers réellement balayés sont listés.

---

## [1.4.12] - 2026-07-29

### Ajouté — Terminal biométrique SEEK (renseignement SSE)

- **Nouvelle interface du terminal** : châssis durci, colonne identité, colonne relevé (platine de lecture, analyse, bandeau LCD) et pied de page procédure. Remplace le formulaire à une colonne.
- **Objet transportable** « Terminal biométrique SEEK » (sac, gilet, uniforme) : sans lui, plus de fiche. Réglage CBA « Terminal SEEK requis » pour les communautés qui préfèrent l’accès sans objet.
- **Fiche SSE depuis le menu ACE** sur une autre personne (blessé, inconscient, détenu, corps) : nouveau PBO optionnel `sse_ace`, nœud « Renseignement SSE ». Aucun écran ACE / KAT n’est modifié ; sans ACE chargé, la couche se retire en silence.
- **Constat de terrain ACE Medical** repris automatiquement sur la fiche : état, pouls, volémie, douleur, localisation des lésions. Les localisations alimentent « signes distinctifs ». Conforme à la règle 1.4.8 — ni SpO2, ni voies aériennes, ni donnée KAT.
- **Relevés biométriques simulés** : empreintes, iris et ADN, avec barre de progression, indice de qualité, nombre de points caractéristiques, algorithme et référence de laboratoire fictive. Analyse locale explicitement simulée — le rapprochement reste à la main du poste de commandement.
- **Code dossier SSE** saisi sur le terrain : la fiche est classée directement dans le dossier correspondant du portail. Code inconnu = fiche enregistrée mais non classée.
- **Signature par l’ATAK** : indicatif, identifiant de terminal et horodatage scellés dans la fiche, en guise de procès-verbal.
- **Exploitation d’un corps** : le terminal préremplit désormais identité, armement et équipement sur une personne décédée (le formulaire restait vide).

### Ajouté — Portail SSE

- **Comptes rendus d'exploitation** : flash et compte rendu initial structuré (Situation, Exploitation du site, Personnel, Matériel, Faits marquants, Appréciation, Suites à donner), générés à la lecture depuis les éléments déjà versés au dossier. Remplace l'inventaire par un produit de renseignement : chaque personne y est reprise avec ses relevés et son verdict d'identité, chaque site avec les pièces non traitées.
- **Sites rattachés au dossier** : un site ouvert depuis le terrain avec la référence active rejoint le dossier, qui agrège désormais personnes, sites et saisies.

- **Registre des personnes** en fiches plutôt qu’en tableau : constat de terrain, relevés biométriques avec jauge de qualité, état de signature et classement.
- `GET /api/sse/persons/by-unit` — fiche déjà ouverte pour une unité Arma donnée.
- Table `sse_biometric_samples`, colonnes de constat et de signature, index de recherche par unité.

### Corrigé — Erreurs de script

- `fn_canTransmit` : condition d’écran endommagé mal parenthésée — `exitWith` recevait un bloc de code (`Error exitwith: Type code, if attendu`), l’état « position seule » n’était jamais évalué.
- `fn_updatePosition` : deux conditions d’anomalie de suivi écrites `if {…} && {…}` (Code au lieu de Booléen) — immobilité et déplacement incohérent ne se déclenchaient pas.

### Corrigé — Zeus

- **Effets ATAK sans effet en solo** : le relais Zeus rejetait toute cible dont `owner` vaut 0, ce qui est le cas de toutes les unités hors multijoueur. Casse d’écran, gel, brouillage et extinction fonctionnent à nouveau ; le routage se fait sur la localité.
- **« ID ATAK » vide** dans le panneau « ATAK — Éditer joueur » : les identifiants étaient lus juste après un appel asynchrone, donc avant la synchronisation. Cible locale = lecture directe ; cible distante = une seule nouvelle tentative après l’aller-retour réseau.
- **Modules roleplay en double** dans l’arbre Zeus : les quatre zones étaient déclarées à la fois en config et comme modules Zeus Enhanced. La variante ZEN n’est plus enregistrée quand les modules config sont visibles.

### Corrigé — Photos

- **Captures ATAK introuvables** : les dossiers `Screenshot` des mods Workshop (`…\Arma 3\!Workshop\@<mod>\`) n’étaient pas balayés, alors que BCE y écrit ses clichés — et le chemin qu’il annonce peut pointer vers une installation qui n’existe plus sur le poste.
- **Dossier de captures COMSPEC** : toute capture résolue est recopiée dans `Documents\Arma 3 - COMSPEC\Captures` (200 fichiers conservés), emplacement stable indépendant de l’endroit où Arma ou BCE écrivent.
- `file_not_found` indique désormais si le dossier d’origine existait et combien de dossiers ont été balayés.
- Séparateur de chemin corrigé dans la collecte des photos locales (SQF n’échappe pas les antislashs : `"\\"` en produisait deux).

---

## [1.4.11] - 2026-07-29

### Corrigé

- Gel violent à la prise de photo ATAK : résolution fichier image allégée dans `COMSPECExtension` (fin du polling 7 s + scans récursifs disque à chaque cliché) ; délais pré-upload SQF réduits.

### Ajouté — Carte web

- Style **Point discret** pour les effectifs (même repère violet que les clichés terrain), choix à l’étape profil de session et dans Compte → Affichage.
- Option pour **masquer les points des photos** sur la carte (panneau Cams inchangé).
- Fin du doublon **Photo tablette + Aperçu casque** à chaque cliché ATAK (aperçu auto ne recycle plus la capture BCE).
- **Demande caméra casque** depuis le menu contextuel d’un opérateur en liaison : photo, photo HD, ou flux d’aperçus rapides (~5 s / 3 min).
- **Écran endommagé** : l’opérateur reste visible sur le web (position seule, liaison dégradée) au lieu de disparaître ; libellé terminal corrigé (plus de « éteint · écran endommagé » cumulé).
- **Fin de brouillage** : la liaison ne reste plus bloquée sur « Hors liaison » (recalcul automatique de l’état Athena).
- **Signalement in-game** : le formulaire ACE peut joindre le **journal de session** Overwatch (fichier + tampon mémoire) pour faciliter le diagnostic côté admin.
- **Journaux Overwatch** : un **nouveau fichier par lancement Arma** (`%LOCALAPPDATA%\\Arma 3\\COMSPEC\\logs\\COMSPEC_*.log`), purge auto des plus anciens (12 conservés).
- **parseSimpleArray** : plus de crash Arma sur les réponses extension mal formées ou chemins Windows.
- **Ordres C2** : « En cours » uniquement après **Confirmé** ; refus possible dès la réception (Reçu/Émis).
- **Points de mission** : clic droit carte → ordre de déplacement avec grille, itinéraire, ETA ; transmission ATAK ; confirmé/refusé in-game ; marqueur + trait après acceptation.
- **Marker Dropper** : journal web affiche le libellé (« helico », etc.) au lieu de `_USER_DEFINED #…` ; moins de doublons au resync.

---

## [1.4.8] - 2026-07-29

### Ajouté — Médical ACE (roleplay)

- Détection auto : inconscient, arrêt cardiaque (ACE Medical uniquement — états transmissibles via l’ATAK)

### Retiré — Détections KAT non roleplay

- Plus d’alertes auto voies obstruées, pneumothorax ou hypoxie SpO2 (données internes KAT non visibles sur un terminal tactique)

### Ajouté — Portail ATAK (carte & photos)

- Barre d’outils carte : traits, zones, périmètres, mesure, personnalisation
- Formulaire zones : icône au centre en liste déroulante
- Photos recon : flou, commentaire, masquage local, transfert SSE, effets roleplay visuels
- Détections « Au sol / suivi » retirées à la déconnexion opérateur

### Corrigé

- Flou photo recon en aperçu agrandi (lightbox)
- Upload photos sans gel ; marqueurs web JSON ; scroll page statut ATAK

---

## [1.4.1] - 2026-07-28

### Ajouté — Portail SSE classifié (`/atak/sse`)

- Sas d’accès double entrée : membre habilité + code, ou invité code seul
- Dossiers d’affaire (classification, notes, preuves, rattachement fiches personnes)
- Codes temporaires délivrés par le commandement (`atak.sse.grant`)
- Croisements listes de surveillance + export PDF classifié
- Permissions `atak.sse.*`, update config tenants `SSE_PORTAL_V1`
- Guide / formation module 7 + lien depuis l’onglet Personnes du Tacmap

---

## [1.4.0] - 2026-07-28

### Ajouté — Renseignement interpersonnel (SSE)

#### Mod
- Terminal « Renseignement interpersonnel » (idd 9991) : identité, statut, circonstances, déclarations
- Photo du visage (`UploadSsePhoto`) + simulation empreintes (`SubmitSseBiometricsSim`)
- Préremplissage inventaire / ACE restrain ; menu ACE « Enregistrer une personne »
- Extension : `SubmitSsePerson`, `UploadSsePhoto`, `SubmitSseBiometricsSim`
- Versions addons `main` / `connect` / `mavik_compat` → **1.4.0**

#### Portail
- API `/api/sse/persons` (+ photos, biométrie sim)
- Tables `sse_persons`, `sse_person_photos` (+ sites / watchlist / custody préparées)
- Onglet TOC **Personnes** (`atak-sse-persons.js`)
- Module pont `sse_person` + update config tenants `SSE_PERSONS_V1`
- Guide Overwatch + formation module 7 mis à jour ; changelog site `/nouveautes`

Voir aussi `mod/UptoDate/STEAM_CHANGELOG.txt` et `mod/UptoDate/docs/contrat-api-sse.md`.

---

## [1.3.1] - 2026-07-28

### Corrigé — Messagerie Groups / radio jeu ↔ web

#### Cause
- Le pont `Iceman_ATAK_GroupMessage` n’envoyait plus vers Athena (journal local seul)
- Aucun poll chat pour faire remonter les messages TOC → inbox Athena en jeu

#### Mod
- Restauration envoi `GROUPE|…` via `SendChat` (`fn_athena_bridgeIcemanGroup`)
- `GetChatMessages` (extension) + `fn_pollChatMessages` → messages web / HQ dans l’inbox Athena
- Rebuild : `connect.pbo`, `atak_athena.pbo`, `COMSPECExtension_x64.dll`

#### Portail
- Enrichissement `GroupMessageParser` sur `POST /api/chat`

### Ajouté — Signalements tactiques lisibles (FRAGO / Reports)

#### Mod
- Alertes tactiques : corps métier seul (plus de duplication type / indicatif / grille)
- FRAGO : SMEAC structuré + `ORDER_ID` pour ouvrir l’ordre lié
- Inbox Athena : détail FRAGO par rubriques

#### Portail
- `TacticalAlertParser` : `cleanSummary`, `parseFragoSections`, `activityLabel`, `order_id` / `frago`
- `tacmap-tactical-alerts.js` : cartes + modal **Ouvrir le FRAGO** / **Ouvrir** / carte / ordre
- `atak-activity.js` / `atak-chat.js` / `atak-orders.js` : ouverture fiche + `ATAKOpenOrder`
- Styles modal / boutons (`tacmap.css`, `atak.css`)

### Ajouté — Photos CTAB automatiques vers ATAK web

#### Mod (`atak_athena`)
- Upload auto à la capture (EH BCE / Iceman + poll Photo Library)
- Retry si fichier pas encore écrit ou liaison absente ; flush à la reconnexion
- UI : « Renvoyer la photo » en secours uniquement

### Corrigé — Marqueurs Marker Widget / Dropper (BCE) vers ATAK web

#### Cause
- Widget = BCE Compat cTab (`_USER_DEFINED` / `_IcTab_DEFINED #…` + `setMarker*Local`), pas Iceman
- Filtre / timing COMSPEC rataient ces marqueurs ; « Forcer une resynchronisation » ne poussait que la position

#### Mod (`connect` / `atak_athena`)
- `fn_isSyncableMapMarker` / `fn_forceSyncMapMarkers`
- Acceptation noms BCE ; re-sync différé ; hooks PlaceMarker / onMapDoubleClick
- EH marqueurs dès PostInit ; diagnostic **Renvoyer les marqueurs carte**
- Force sync hub / pause manager inclut les marqueurs

### Versions
- `connect` **1.3.1** · `atak_athena` **1.0.11**
- Rebuild : `connect.pbo`, `atak_athena.pbo`, `COMSPECExtension_x64.dll` + déploiement PHP/JS/CSS Athena

---

## [1.3.0] - 2026-07-28

### Ajouté — Réalisme liaison ATAK (roleplay réseau & appareil)

#### Mod (`connect` 1.3.0)
- **`fn_canTransmit`** : gate central avant envois extension (position, polls, marqueurs) — modes `full` / `position_only` / `none`
- **Hub overlays** IDC 9200–9204 : déconnexion, zone, pertes, écran cassé, glitch (`display_hub.hpp`)
- **Dommages enrichis** : chocs Hit/Explosion, bras blessé, lien KAM (pneumothorax, SpO2 → capteur HR roleplay)
- **`fn_getMedicalState`** : champs KAM optionnels (SpO2, voies aériennes, pneumothorax) propagés vers Athena
- **État crash ATAK** : gel terminal distinct offline réseau (`fn_triggerAtakCrash`, ppEffects)
- **Modules Zeus/Eden** réactivés : `COMSPEC_Module_NoCoverage`, `Interference`, `Degraded`, `Jammer`
- **Sync zones portail** : `fn_pollRoleplayConfig` / `fn_syncRoleplayZonesFromPortal` (poll 90 s)
- **Reprise JIP** : `fn_initCrashRecovery`, `fn_restoreAtakSession`, `fn_clearDisconnectedAtakState`
- **Callbacks extension** : `NetworkDisconnected` / `NetworkReconnected`
- **Assets** : overlays roleplay + logo web (brouillons IA) — doc `docs/design/atak-assets-roleplay.md`

#### Extension `COMSPECExtension`
- `GetRoleplayConfig` → `GET /api/atak/roleplay-stats` (format tabulaire SQF)
- `GetSessionRestore` → `GET /api/atak/session-restore`

#### Portail Athena
- `roleplayStats` : `zones_enabled`, `zones_json`, `session_ttl_sec`
- `GET /api/atak/session-restore?steam_uid=…` — snapshot TTL 10 min (`AtakDisconnectRecoveryRepository`)
- Snapshot auto à chaque `POST /api/atak/position` (indicatif, liaison, position)
- Assets web : `atak-eagle-logo.png`, `atak-link-lost-icon.png` (alerte roleplay JS)

### Modifié
- Versions addons `main` / `connect` / `mavik_compat` → **1.3.0**
- `atak-roleplay-effects.js` : icône liaison perdue au lieu de l’emoji seul

### Rebuild pack
- **Obligatoire** : `connect.pbo`, `COMSPECExtension_x64.dll`
- Recommandé : `main.pbo`, `mavik_compat.pbo` (version affichée hub)

---

## [1.2.2] - 2026-07-27

### Ajouté — Waypoints partagés & itinéraires de patrouille (portail)

#### API & schéma
- Tables `atak_waypoint_routes` / `atak_waypoints` (`migrations/2026_07_27_001_atak_waypoints.sql`) ; filet lazy `AtakWaypointsSchema` si la base était déjà installée
- Routes : `/api/atak/waypoint-routes` (CRUD), `/api/atak/waypoints` (CRUD), `POST /api/atak/waypoints/{id}/reached`
- Création d’itinéraire avec points en un seul appel ; `GET …/waypoint-routes/{id}` expose `next_waypoint` pour le guidage client
- Progression automatique de l’itinéraire : Planifié → Actif (1er point atteint) → Terminé

#### Pack mod
- Changelog Workshop / dépôt pack alignés sur **1.2.2** ; versions addons `main` / `connect` / `mavik_compat` → `1.2.2`, `atak_athena` → `1.0.7`
- Guidage SQF (marqueurs numérotés + sondage `reached`) prévu dans une prochaine livraison pack — API déjà consommable

### Ajouté — Réalisme ATAK (terminal & certificat)

#### API mod ↔ registre communauté
- `GET/POST /api/atak/terminals` et `POST /api/atak/certificates` — protégés par clé d’accès communauté (pas d’exemption dans `config/tactical_api.php`)
- Le mod (extension `RegisterTerminal`, `RegisterCertificate`, `GetTerminalRealism`) s’authentifie via la clé ATAK ; résolution `user_id` par `steam_uid` à l’enregistrement
- `GET /api/atak/terminals?terminal_uid=…` : état terminal + dernier certificat + réglages `atak_defaults` (dont `automatic_pairing`) ; le client jeu ne peut pas lister tous les terminaux
- `POST` certificat refusé si `automatic_pairing` désactivé pour la communauté (`403 automatic_pairing_disabled`)
- Back-office **Certificats et terminaux** (`/back-office/atak/realisme`) ; tables `atak_terminals` / `atak_certificates` (migration lazy)

### Ajouté — Réglages d’exécution portail (runtime admin)

#### Persistance & API
- `TenantAdminSettingsRepository` — réglages `admin_runtime` par communauté (fusion dans `tenant_settings`)
- `GET/POST /api/back-office/runtime-settings` — lecture / enregistrement pour les admins organisation
- Quatre blocs métier : **Portail**, **Notifications**, **Sécurité**, **Défauts ATAK** (sanitisation serveur, valeurs bornées)

#### Portail
- Inscriptions publiques, validation manuelle des comptes, mur public, fuseau horaire

#### Notifications
- Rappels RSVP automatiques, notifications Discord, SMS d’urgence, récapitulatif hebdomadaire

#### Sécurité
- Authentification à deux facteurs, expiration de session, verrouillage après échecs, journal d’audit étendu

#### Défauts ATAK (consommés par le réalisme terminal)
- Appairage automatique des certificats, version client minimale, durée de validité des certificats, partage de position hors opération

### Ajouté — Comptes rendus post-op structurés (AAR)

- Back-office **Comptes rendus post-op** (`/back-office/atak/comptes-rendus`) : liste, fiche, édition, dépôt
- Lien optionnel avec un **cycle de mission** ; statuts **En attente** / **Validé**
- Champs structurés : synthèse, points forts et faibles, actions ouvertes / clôturées, scores et métriques opérationnelles
- Filtres rapides (en attente, validés, actions ouvertes) ; KPIs en tête de liste
- Table `aar_reports` (migration lazy `aar_reports_migration`)

### Ajouté — Matrice rôles & permissions

- Page **Rôles & permissions** (`/back-office/roles-permissions`) : vue matricielle par rôle et par module
- Modules : Membres, Opérations, ATAK, Finances, Systèmes — niveaux d’accès en libellés métier (Complet, Sa section, Lecture, etc.)
- Filtres (recherche, périmètre, niveau, actif), édition inline, export CSV, marquage **revue d’accès** trimestrielle
- APIs `/api/admin/roles-permissions` (liste, export, sauvegarde par rôle)
- Migration `role_permission_matrix_migration`

### Ajouté — Réponses nominatives (événements)

- Vue **Réponses nominatives** (`/back-office/events/{id}/reponses-nominatives`) depuis la fiche créneau
- Tableau par membre : réponse RSVP (Confirmé, Peut-être, Sans réponse, Décliné), section, état terminal / certificat ATAK
- Filtres par réponse, section et état ATAK ; export CSV ; édition des métadonnées orga par ligne
- APIs `/api/events/{id}/reponses-nominatives` (liste, export, mise à jour)
- Migration `community_event_rsvp_nominative_migration`

### Ajouté — Contrôle d’accès modules (`ModuleFeatureAccess`)

- Garde unifiée `guardAtak` / `guardOperations` / `guardSystems` branchée sur la matrice RBAC
- Appliquée au réalisme ATAK, aux comptes rendus post-op, aux réponses nominatives et à la matrice permissions
- Redirection back-office avec message métier si droits insuffisants (pas de vocabulaire technique côté utilisateur)

### Ajouté — Préférences carte ATAK (panneau compte)

- **Recentrage personnel** : recadrer la carte sur sa position dès qu’elle remonte en début de session
- **Contacts en retard** : afficher ou masquer les effectifs dont la position arrive avec délai
- **Alertes sonores par catégorie** : liaison, ordres / urgences, médical — cases à cocher + persistance locale (`atak_alert_categories`)
- Volume et style des sons d’alerte regroupés dans le même panneau (complète la barre latérale)

### Ajouté — Quick wins Athena / Tacmap

#### Overwatch — libellés français
- Panneaux **Relecture mission**, **État logistique**, **Calculateur d’appui-feu**, **Zones de danger**, **Identification ami / ennemi**
- Onglet et santé « Relecture » ; bilan après-action en libellés métier (instantanés, signalements, anomalies)

#### Barre d’outils — profils TOC / Chef d’équipe / Médecin
- Presets dans **Personnaliser** : TOC (tout), Chef d’équipe, Médecin (outils adaptés)
- Préférence enregistrée en localStorage (`atak_map_tools_visible_v1` + `atak_map_tools_preset_v1`)

#### Badge opérateur téléphone + temps restant
- Sur Tacmap en session téléphone : badge **Opérateur téléphone** + compte à rebours jusqu’à expiration

#### Cams — aperçus assumés + demande de vue
- Rappel UI : aperçus photo uniquement (pas de vidéo en direct)
- Bouton **Demander une nouvelle vue** → journal TOC + message radio (confirmation)

#### Effectifs BFT — Vibrer
- Action **Vibrer** sur la liste / tableau des effectifs (API existante), avec confirmation

#### Cycle de mission (briefing → exécution → après-action)
- Hub **Cycle de mission** (`/back-office/atak/cycle-mission`) : créer, ouvrir, clôturer
- Statuts métier : Préparation · En cours · Clôturée (`theatre_mission_cycles`)
- Badge mission sur Tacmap / ATAK ; à la clôture, relecture + bilan bornés (`from` / `to`)
- API `/api/mission-cycle/*` ; migration idempotente + ensure lazy

#### Équipes de feu sur la carte (prio. moyenne)
- Filtre BFT par équipe de feu (liste Effectifs + marqueurs carte)
- Panneau **Composition des équipes de feu** pendant l’opération (couleur, liaison)
- Couleurs d’équipe déjà sur marqueurs / puces conservées

#### Identification IFF de conduite (prio. moyenne)
- Alertes TOC + Overwatch pour contact / véhicule **inconnu**, **suspect**, défi **expiré**
- Compte à rebours d’expiration du défi ; délai de grâce (5 min) → « Contact inconnu »
- Panneau Identification TOC opérationnel (plus seulement Overwatch)

#### Logistique mission (prio. moyenne)
- Seuils stock bas / critique (≤ 35 % / ≤ 15 %) + pastilles d’alerte
- Bouton **Ravitailler** → demande dans le journal d’activité TOC
- Lien vers les évacuations sanitaires en cours depuis le suivi logistique

#### Briefing diapos (prio. moyenne)
- Actions **Monter / Descendre** pour l’ordre ; publication rapide Visible en jeu / brouillon
- Présence briefing enrichie (compteurs téléphone / tableau en jeu)

#### Rapports bugs Overwatch (prio. moyenne)
- Suivi métier **Nouveau → En cours → Corrigé** (`new` / `in_progress` / `fixed`)
- Affichage **version du pack** (+ extension) ; filtre par statut

### Ajouté — Pack priorité haute (TOC / terrain)

#### Parité téléphone ATAK
- Session `/connect` : entrée directe carte (plus de hub invité) + caps BFT / médical / journal radio
- Badge **Opérateur téléphone** + TTL (déjà en place) ; auteur radio = libellé téléphone
- Journal d’activité à l’ouverture carte téléphone

#### Fidélité des repères
- Normalisation API enrichie (couleur Arma → hex, type, forme, dir, alpha)
- Libellés FR ACE (POI, MEDEVAC, renfort, service) ; couleurs hex sans `#`

#### Photos terrain
- Messages d’échec métier (manquant / trop lourd / liaison dégradée)
- Galerie TOC horodatée Zulu + message si image indisponible
- Notifs mod selon détail d’échec d’envoi

#### SITREP ops-ready
- Overwatch : plus de cadrage « test » — signalement poste de commandement
- ACE SITREP / CONTACT / SPOTREP → tableau de situation fusionné

#### Replay AAR exploitable
- Timeline contacts / MEDEVAC / ordres / repères (`/api/replay/events`)
- Bilan + PDF export en français avec compteurs opérationnels

### Déploiement
- Cache-bust Tacmap `?v=202607270730` (session-profile, chat, sitrep, cams, replay, arma-map-markers, atak-sounds, atak-map, atak.css)
- FTP Hostinger → `athena.ttrd.fr` (`public_html` + dual `assets/`) ; `.env` non modifié
- Rebuild mod OK (`build_mod.bat`) — réalisme terminal / certificat + sync liaison
- Lancer `run-migrations` (ou UI migrations) : `atak_realism_registry`, `aar_reports`, `role_permission_matrix`, `community_event_rsvp_nominative` ; `theatre_mission_cycles` et `workflow_status` rapports mod si absent
---

## [1.2.1] - 2026-07-26

### Ajouté — Athena WEB / TOC ATAK (branchement Overwatch)

#### Carte — marqueurs Marker Dropper & cTab
- Les repères posés en jeu (Marker Dropper, marqueurs carte Arma, marqueurs utilisateur cTab / ATAK Enhanced) remontent sur la carte Athena du poste de commandement
- Pont cTab : écoute immédiate des mises à jour + file d’attente si la liaison Athena n’est pas encore prête
- Marqueurs `_USER_DEFINED` (Dropper / carte Arma) inclus dans le miroir web
- Sync immédiate vers le miroir web dès qu’un repère est posé ou mis à jour (sans attendre le prochain cycle long)
- Les points d’intérêt ACE (LZ d’évacuation, renfort, service véhicule, POI) s’affichent aussi sur la carte web
- **Diamants hostiles** : alerte, destruction, objectif ou points rouges simples en diamant (lisibles d’un coup d’œil, sans confusion avec un effectif ami)
- **Badges de préfixe** : libellés courts type « T », « T1 », « A-3 » en pastille à côté du symbole
- Libellés français sur la carte et dans l’historique (« Alerte », « Objectif », « Point d’intérêt », « Repère · … »)
- Déduplication : un point anonyme ou au même indicatif qu’un contact déjà en liaison ne double plus le symbole OTAN de l’effectif
- Pop-up carte : précision « ce point n’est pas un effectif en liaison — c’est un repère posé sur la carte »

#### En-tête carte tactique (Tacmap)
- Barre d’en-tête redesignée : actions regroupées en **clusters** (contexte mission, liaison, état système)
- Bouton **Lier le jeu** (code d’appariement Arma ↔ compte) intégré au cluster liaison
- Badge **BÊTA** à côté de la marque (accès anticipé) ; tagline d’état recentrée sur **Liaison** (plus de libellé « théâtre » en en-tête)
- Plus de bouton fluo / accent agressif : actions primaires sobres, cohérentes avec le reste du TOC

#### Connexion téléphone — page `/atak/connect`
- Page web téléphone : saisie du **code affiché sur le PC / tablette** pour ouvrir la carte ATAK mobile sans compte
- Flux : code → token de session → vue connectée (expiration gérée avec message clair)
- Complète l’écran de liaison en jeu (adresse mobile + code d’appariement)

#### Rapports d’erreurs mod → Athena
- Remontée des diagnostics / bugs Overwatch vers le portail (`POST /api/atak/mod-report`)
- Journal admin **Rapports erreurs** (liste, filtre, retrait)
- Exemption de clé d’accès pour ce chemin (signalement possible avant ou sans liaison complète) + rate-limit
- Correctif `Database::getPdo` : accès PDO stable pour le dépôt des rapports (évite échec au boot / lazy-init)

#### Menu contact — Faire vibrer le terminal
- Action **Faire vibrer le terminal** dans le menu contextuel d’un contact
- Disponible uniquement si le contact est **en liaison** (sinon message clair : hors liaison)
- Confirmation opérateur : « Le terminal de [indicatif] vibre en jeu »
- Journal d’activité TOC : « Terminal — vibration — [indicatif] » (signal haptique, pas un ordre de manœuvre)
- Action voisine : **Envoyer une notification…** (bandeau cliquable sur le terminal du joueur)

#### Blue Force / indicatif / identifiant de suivi
- Chaque contact en liaison reçoit un **identifiant de suivi** stable lié à son indicatif (réutilisé d’une session à l’autre)
- Affichage liste BFT : ligne **« Suivi … »** sous l’indicatif
- Fiche pop-up unité : ligne **« Identifiant de suivi »**
- Même identité partagée entre carte, tablette et TOC

#### Messagerie HQ (poste de commandement)
- Contact permanent **HQ** dans la messagerie ATAK / cTab en jeu : messages destinés au PC → journal radio / messagerie Athena
- Journal d’activité TOC : **« Message HQ — [auteur] : … »**

#### Sons & alertes TOC
- Préférences sonores TOC avec libellés métier (silencieux avec/sans vibration, ambiance tension, signal médical)
- Assistances médicales : bandeaux et toasts restent visibles même si le son est coupé
- Escalade médicale : les alertes moins graves du même indicatif sont clôturées pour éviter le doublon

### Modifié

#### Journal radio — moins de bruit technique
- Messages de **réglages d’affichage** (camps adversaire / indépendants / civils) appliqués en silence côté carte et **hors journal radio** du TOC
- Variantes anciennes (auteur « REGLAGES » + corps « AFFICHAGE|… ») également filtrées
- Sync Blue Force / positions : hors journal d’activité par défaut

#### Messages de groupe vs canal HQ
- Les **messages de groupe** restent en jeu et **ne spamment plus** le journal radio web du TOC
- Canal officiel jeu → TOC : destinataire **HQ**

#### Ordres / signaux terminal
- Types « Faire vibrer » et « Notifier » traités comme **signaux terminal**, distincts des ordres de manœuvre

### Corrigé

- Confusion repère / effectif : libellé court type indicatif préfixé **« Repère · … »** ; ne remplace plus le symbole Blue Force
- Messages techniques d’affichage camps filtrés côté serveur et interface
- Contacts hors liaison : vibration / notification refusées avec message opérateur compréhensible
- Demande de renfort (QRF) sans position de contact : réponse **400** avec message métier (« Indiquez la position du contact pour demander le renfort ») au lieu d’un échec opaque

### Stabilité & ACE (volet jeu Overwatch)
- REAPP / respawn durci ; menu ACE ATAK rebranché ; NDA qui ne revient plus à chaque lancement
- Terminal ATAK requis par défaut ; features prioritairement dans ATAK Enhanced / cTab
- Adresse mobile + code d’appariement sur l’écran de liaison téléphone
- Briefing / diaporama, demande d’appui aérien (CAS) et manifeste de vol branchés côté mod (formulaires dédiés + viewer 9-lignes) — détail Steam / changelog Overwatch

### Déploiement prod (Athena WEB — 26/07/2026)

- FTP Hostinger → `athena.ttrd.fr` (`/domains/athena.ttrd.fr/public_html/`)
- Assets synchronisés en dual : `assets/` **et** `public/assets/` (JS + CSS)
- Cache-bust TOC : `views/atak.php` — `?v=202607261735` sur scripts / styles critiques
- Contrôle post-upload : ping HTTPS **200** ; JS vérifiés (vibrer, diamants, suivi, filtre réglages)
- Périmètre web : carte / marqueurs, unités BFT, menu vibrer, tchat filtré, contrôleur & dépôts ATAK, routes
- `.env` non modifié ; pas de rebuild PBO dans ce déploiement web (packs déjà rebuild côté mod)

Voir aussi `mod/UptoDate/STEAM_CHANGELOG.txt` (texte Steam) et `mod/UptoDate/@COMSPECOverwatch/CHANGELOG.md`.

---

## [1.2.0] - 2026-07-24

### Ajouté - Phase 2.5 : Intelligence & Automatisation

[Contenu complet Phase 2.5 déjà inséré ci-dessus]

### Ajouté - Phase MOD : Intégration Arma 3

[Contenu complet Phase MOD déjà inséré ci-dessus]

---

## [1.0.0] - 2026-07-24

### Ajouté - Phase 1 : Fondations coordination

#### Système de rapports tactiques structurés
- **Tables** : `atak_tactical_reports`, `atak_report_attachments`, `atak_report_templates`
- **Vue** : `v_atak_tactical_reports`
- **Repository** : `AtakTacticalReportRepository` avec 9 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/reports` : Liste rapports avec filtres (type, priorité, statut, émetteur, dates)
  - `POST /api/atak/reports` : Créer rapport (SPOTREP, SITREP, SALUTE, CONTACT)
  - `GET /api/atak/reports/{id}` : Détail rapport avec attachements
  - `POST /api/atak/reports/{id}/acknowledge` : Acquitter rapport
- **Features** :
  - Support 4 types : SPOTREP, SITREP, SALUTE, CONTACT
  - Génération automatique numéro rapport (`SPOTREP-20260724-001`)
  - Données structurées JSON (SALUTE : Size, Activity, Location, Unit, Time, Equipment)
  - Classification : UNCLASSIFIED, RESTRICTED, CONFIDENTIAL, SECRET
  - Priorités : ROUTINE, PRIORITY, IMMEDIATE, FLASH
  - Statuts : DRAFT, SUBMITTED, ACKNOWLEDGED, ACTIONED, ARCHIVED
  - Système visibilité : ALL, COMMAND, RESTRICTED, PRIVATE
  - Géolocalisation (pos_x, pos_y, grid_reference)
  - Multi-tenant et context-aware

#### Système POI (Points d'Intérêt) tactiques
- **Tables** : `atak_poi`, `atak_poi_observations`, `atak_poi_photos`
- **Vue** : `v_atak_poi` avec compteurs observations/photos
- **Repository** : `AtakPoiRepository` avec 10 méthodes publiques
- **API** : 3 endpoints REST
  - `GET /api/atak/poi` : Liste POI avec filtres (catégorie, affiliation, statut, menace)
  - `POST /api/atak/poi` : Créer POI
  - `PUT /api/atak/poi/{id}` : Mettre à jour POI
- **Features** :
  - 13 catégories : OBJECTIVE, BUILDING, CACHE, ENEMY_POSITION, HVT, PATROL_BASE, CHECKPOINT, STRUCTURE, INFRASTRUCTURE, ROUTE, TERRAIN, HAZARD, OTHER
  - Affiliation : FRIENDLY, ENEMY, NEUTRAL, UNKNOWN
  - Certitude : CONFIRMED, PROBABLE, POSSIBLE, DOUBTFUL
  - Niveau menace : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - Statuts : ACTIVE, NEUTRALIZED, DESTROYED, ABANDONED, OCCUPIED, UNDER_SURVEILLANCE
  - Recherche proximité géographique (`findNearPosition`)
  - Historique observations multiples
  - Photos géolocalisées
  - Source fiabilité (échelle A-F NATO)
  - Génération automatique code POI

#### Zones tactiques enrichies
- **Tables** : `atak_tactical_zones`, `atak_zone_alerts`
- **Vue** : `v_atak_active_zones` avec calcul `is_currently_active`
- **Repository** : `AtakTacticalZoneRepository` avec 14 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atak/zones` : Liste zones avec filtres
  - `POST /api/atak/zones` : Créer zone
  - `POST /api/atak/zones/check-position` : Vérifier position dans zones
  - `GET /api/atak/zones/alerts` : Liste alertes non acquittées
- **Features** :
  - 9 types zones : LZ, DZ, OBJECTIVE, DANGER_ZONE, NO_GO_AREA, PATROL_AREA, SECTOR, BOUNDARY, OTHER
  - 3 géométries : CIRCLE, RECTANGLE, POLYGON
  - Algorithmes géométriques :
    - `isInCircle()` : Calcul distance euclidienne
    - `isInRectangle()` : Test rotation + bounds
    - `isInPolygon()` : Ray casting algorithm
  - Système alertes entrée/sortie configurable
  - Sons alertes personnalisables
  - Temporalité (`active_from`, `active_until`)
  - Priorités et niveaux menace
  - Style visuel (couleurs, opacité, contours)
  - Log détaillé alertes avec position exacte

### Ajouté - Phase 2 : Capacités spécialisées

#### Extension système MEDEVAC 9-Line avec triage TCCC
- **Tables** : `atak_medevac_requests`, `atak_medevac_patients`, `atak_medevac_status_updates`
- **Vue** : `v_atak_active_medevac` avec golden hour et patients
- **Triggers** :
  - `trg_medevac_golden_hour` : Calcul automatique golden hour pour patients T1
  - `trg_medevac_status_log` : Logging changements statut
- **Repository** : `AtakMedevacRepository` avec 12 méthodes publiques
- **API** : 6 endpoints REST
  - `GET /api/atak/medevac` : Liste MEDEVAC
  - `POST /api/atak/medevac` : Créer demande MEDEVAC 9-Line
  - `GET /api/atak/medevac/{id}` : Détail avec patients
  - `PATCH /api/atak/medevac/{id}/status` : Mettre à jour statut
  - `POST /api/atak/medevac/{id}/assign` : Assigner asset
  - `POST /api/atak/medevac/{id}/patients` : Ajouter patient
- **Features** :
  - Format 9-Line NATO complet
  - Triage TCCC : T1 (urgent), T2 (urgent surgical), T3 (delayed), T4 (expectant)
  - Golden hour tracking automatique (T1)
    - Calcul expiration : request_time + 60min
    - Statut : `OK` (> 30min), `WARNING` (15-30min), `CRITICAL` (< 15min), `EXPIRED` (> 60min)
    - Minutes restantes calculées
  - Catégories patients : Litter vs Ambulatory
  - Équipement spécialisé : hoist, ventilator, blood, etc.
  - Statut sécurité LZ : NO_ENEMY, POSSIBLE_ENEMY, ENEMY_IN_AREA, ENEMY_SUPPRESSED
  - Marquage LZ : NONE, SMOKE, PANEL, STROBE, FLARE, VS17, MIRROR
  - Contamination NBC tracking
  - Workflow complet : REQUESTED → ACKNOWLEDGED → ASSIGNED → INBOUND → ON_SITE → EVACUATING → COMPLETED
  - Historique changements statut avec timestamps
  - Données médicales détaillées par patient :
    - Conscience : ALERT, VERBAL, PAIN, UNRESPONSIVE
    - Respiration : NORMAL, ABNORMAL, ABSENT
    - Circulation : NORMAL, COMPROMISED, ABSENT
    - Blessures structurées (location, type, severity)
    - Traitements appliqués
    - Médicaments administrés (nom, dose, heure)

#### Système QRF (Quick Reaction Force)
- **Tables** : `atak_qrf_requests`, `atak_qrf_sitrep_updates`, `atak_qrf_waypoints`
- **Vue** : `v_atak_active_qrf` avec distance et urgence
- **Trigger** : `trg_qrf_urgency_deadline` : Calcul deadline urgence
- **Repository** : `AtakQrfRepository` avec 13 méthodes publiques
- **API** : 5 endpoints REST
  - `GET /api/atak/qrf` : Liste QRF
  - `POST /api/atak/qrf` : Créer demande QRF
  - `POST /api/atak/qrf/{id}/assign` : Assigner QRF
  - `POST /api/atak/qrf/{id}/position` : Mettre à jour position QRF
  - `POST /api/atak/qrf/{id}/sitrep` : Ajouter SITREP
- **Features** :
  - Types menace : AMBUSH, ATTACK, TROOPS_IN_CONTACT, CASEVAC_URGENT, IED_STRIKE, OTHER
  - Taille ennemi : FIRE_TEAM, SQUAD, PLATOON, COMPANY, UNKNOWN
  - Statut unité amie : SECURE, ENGAGED, PINNED, OVERRUN, RETREATING
  - Workflow : REQUESTED → ACKNOWLEDGED → QRF_ASSIGNED → QRF_ENROUTE → QRF_ENGAGED → SITUATION_STABILIZED → COMPLETED
  - Tracking position QRF temps réel
  - Calcul distance vers zone contact (formule euclidienne)
  - ETA dynamique
  - SITREP multi-source (demandeur + QRF)
  - Types SITREP : STATUS_CHANGE, POSITION_UPDATE, SITUATION_UPDATE, CONTACT_REPORT
  - Waypoints route QRF
  - Deadline urgence (FLASH : 5min, IMMEDIATE : 15min, PRIORITY : 30min)
  - Support demandé multiples : infantry, armor, aviation, cas, medevac, eod, engineers

#### Suivi véhicules et assets lourds enrichi
- **Tables** : `atak_vehicle_tracking`, `atak_vehicle_position_history`, `atak_vehicle_events`, `atak_vehicle_service_requests`
- **Vue** : `v_atak_active_vehicles` avec statut fuel/ammo
- **Triggers** :
  - `trg_vehicle_deployed` : Logging déploiement
  - `trg_vehicle_destroyed` : Logging destruction
- **Repository** : `AtakVehicleTrackingRepository` avec 16 méthodes publiques
- **API** : 4 endpoints REST
  - `GET /api/atek/vehicles` : Liste véhicules
  - `POST /api/atak/vehicles` : Upsert véhicule (create or update intelligent)
  - `POST /api/atak/vehicles/{id}/service` : Demander service
  - `GET /api/atak/vehicles/service-requests` : Liste demandes service
- **Features** :
  - 10 classes véhicules : LIGHT_VEHICLE, TRUCK, APC, IFV, TANK, ARTILLERY, HELICOPTER, FIXED_WING, UAV, BOAT
  - Côté : BLUFOR, OPFOR, INDEPENDENT, CIVILIAN
  - Statuts : OPERATIONAL, DAMAGED, IMMOBILIZED, DESTROYED, ABANDONED
  - Types mission : TRANSPORT, COMBAT, RECON, SUPPLY, MEDEVAC, CAS, CAP, PATROL, LOGISTICS
  - Tracking complet :
    - Position GPS (pos_x, pos_y)
    - Cap et vitesse
    - Fuel % (alerte < 20%)
    - Munitions % (alerte < 30%)
    - Santé composants : moteur, coque, chenilles/roues, tourelle
  - Upsert intelligent par callsign :
    - Si véhicule existe : mise à jour sélective (seulement champs fournis)
    - Si nouveau : création complète
    - Update automatique `last_seen_at`
  - Historique positions (table séparée pour replay)
  - Événements automatiques :
    - DEPLOYED, DESTROYED, DAMAGED, REPAIRED, ABANDONED, RECOVERED, REFUELED, REARMED
  - Demandes service :
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Priorités : LOW, MEDIUM, HIGH, CRITICAL
    - Statuts : REQUESTED, ACKNOWLEDGED, IN_PROGRESS, COMPLETED, CANCELLED
  - Calcul distance vers destination
  - Statut "véhicule actif" (vu < 30min)
  - Labels fuel/ammo : CRITICAL, LOW, MEDIUM, OK, FULL
  - Équipage et passagers tracking

### Documentation

#### Guides utilisateur et intégration
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Guide complet 31 endpoints
  - Exemples SQF pour mod Arma
  - Exemples JavaScript pour interface web
  - Formats requêtes/réponses détaillés
  - Codes erreurs
  - Notes performance et sécurité

#### Documentation technique
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Synthèse technique complète
  - Architecture système
  - Détails base de données (15 tables, 5 vues, 4 triggers)
  - Pattern repositories
  - Sécurité et performance
  - Tests recommandés
  - Roadmap Phase 3-5

#### Proposition features
- **`docs/NOUVELLES-FEATURES-ATAK-MOD.md`** : Proposition 15 features sur 5 phases
  - Features détaillées avec cas d'usage
  - Notes implémentation
  - Priorités P0/P1/P2

#### Documentation produit
- **`docs/COMPARAISON-PRODUIT-COMSPEC-CTAB-SIT.md`** : Comparaison produits
- **`docs/ATAK-WEB-DOCUMENTATION-PRODUIT.md`** : Doc ATAK Web
- **`docs/ATHENA-MYTHOLOGIE.md`** : Lien mythologique
- **Variantes forum** : `*-VERSION-FORUM.md` (sans URLs/tableaux)

### Migration

#### Scripts SQL
- **`migrations/2026_07_24_001_atak_tactical_reports.sql`** : Phase 1.1
- **`migrations/2026_07_24_002_atak_poi_intelligence.sql`** : Phase 1.2
- **`migrations/2026_07_24_003_atak_tactical_zones.sql`** : Phase 1.3
- **`migrations/2026_07_24_004_atak_medevac_extended.sql`** : Phase 2.1
- **`migrations/2026_07_24_005_atak_qrf_system.sql`** : Phase 2.2
- **`migrations/2026_07_24_006_atak_vehicle_tracking.sql`** : Phase 2.3

Toutes les migrations :
- Sont idempotentes (`IF NOT EXISTS`)
- Commentées en détail
- Incluent contraintes FK
- Définissent index stratégiques
- Multi-tenant natives

### Sécurité

#### Multi-tenant
- Isolation complète par `tenant_id` + `context_id`
- Contraintes FK vers `tenants` et `contextes`
- Filtrage systématique dans repositories

#### Soft delete
- Implémenté sur : reports, POI, zones
- Permet restauration et audit
- Vues filtrent automatiquement

#### Protection SQL
- Requêtes préparées PDO
- Pas de concaténation SQL
- Paramètres bindés

### Performance

#### Optimisations base de données
- Index composites sur (tenant_id, context_id)
- Index géographiques sur (pos_x, pos_y)
- Colonnes calculées STORED
- Vues enrichies pour éviter N+1 queries

#### Optimisations API
- Pagination par défaut (limit: 100-200)
- Filtres côté SQL
- Sélection colonnes via vues

### Ajouté - Phase 2.5 : Intelligence & Automatisation

#### Auto-routage intelligent des rapports
- **Tables** : `atak_report_routing_rules`, `atak_report_routing_history`
- **Repository** : `AtakReportRoutingRepository` avec 7 méthodes publiques
- **Features** :
  - Règles routage configurables par tenant
  - Critères : type rapport, priorité, mots-clés, position géographique
  - Distribution automatique aux destinataires pertinents (rôles, utilisateurs)
  - Escalade temporelle (ROUTINE : +24h, PRIORITY : +4h, IMMEDIATE : +1h)
  - Historique complet distributions
  - État lecture/accusé réception par destinataire
  - Méthode `applyRoutingRules()` : matching intelligent
  - Méthode `routeReport()` : distribution multi-canal

#### Calcul dynamique menace zones
- **Tables** : `atak_zone_events`
- **Repository** : `AtakZoneThreatRepository` avec 9 méthodes publiques
- **Trigger** : `trg_zone_threat_recalc` : Recalcul automatique après événement
- **Vue enrichie** : `v_atak_zone_threat` avec `current_threat_level`
- **Features** :
  - Événements trackés : CONTACT, EXPLOSION, GUNFIRE, IED, CASUALTY, OBSERVATION
  - Impact événement sur score menace (CONTACT: +30, EXPLOSION: +40, IED: +50)
  - Expiration temporelle (2h par défaut, décroissance progressive)
  - Prise en compte POI hostiles dans rayon 500m
  - Seuils adaptatifs : LOW (<30), MEDIUM (30-60), HIGH (60-80), CRITICAL (>80)
  - Recalcul automatique toutes zones affectées
  - Méthode `calculateThreatImpact()` : formule complexe
  - Méthode `countNearbyThreats()` : agrégation géospatiale

#### Notifications temps réel enrichies
- **Table** : `atak_realtime_notifications`
- **Repository** : `AtakNotificationRepository` avec 8 méthodes publiques
- **Features** :
  - Types : REPORT, MEDEVAC, QRF, VEHICLE, ZONE, GENERAL
  - Priorités : INFO, WARNING, URGENT, CRITICAL
  - Destinataires : ALL, COMMAND, SPECIFIC_ROLE, SPECIFIC_USER
  - Expiration automatique (TTL configurable)
  - État lu/non-lu par utilisateur
  - Polling endpoint `/api/atak/notifications/poll?since={timestamp}`
  - Notifications spécialisées :
    - Golden hour warnings (< 15min)
    - Alertes véhicule critique (fuel <5%, dégâts >70%)
    - Zone menace CRITICAL dépassée
    - Nouveaux rapports IMMEDIATE/FLASH
  - Cleanup automatique notifications expirées

#### Scoring urgence MEDEVAC & Asset optimal
- **Table** : `atak_medical_assets`
- **Repository** : `AtakMedevacIntelligenceRepository` avec 8 méthodes publiques
- **Features** :
  - **Scoring urgence patients** :
    - Patients T1 (urgent) : +50 points
    - Golden hour < 30min : +30 points
    - Zone pickup menace HIGH/CRITICAL : +20 points
    - Conditions météo défavorables : -10 points
    - Formule finale : `urgency_score = base_score + modifiers`
  - **Sélection asset optimal** :
    - Calcul ETA précis (distance euclidienne + vitesse asset)
    - Capacité patients (litter vs ambulatory)
    - Disponibilité temps réel
    - Risque trajet (zones menace traversées)
    - Méthode `findOptimalAsset()` : algorithme complet
    - Score asset : `(100 - distance_score) + capacity_score - risk_score`
  - **Évaluation menace LZ** :
    - Comptage POI hostiles <500m
    - Événements récents zone
    - Niveau menace zone tactique
    - Retour : NONE, LOW, MEDIUM, HIGH, CRITICAL
  - **Vue enrichie** : `v_atak_medevac_urgency` avec score et asset recommandé

#### Route QRF optimale
- **Table** : `atak_qrf_coordination`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes QRF)
- **Features** :
  - **Calcul route optimale** :
    - Algorithme A* avec pénalités zones menace
    - Évitement NO-GO zones (pénalité infinie)
    - Contournement zones HIGH threat (+40% distance)
    - Préférence zones LOW threat (-10% distance)
    - Méthode `calculateOptimalQrfRoute()` : pathfinding complet
  - **Génération waypoints** :
    - Waypoints intermédiaires espacés 500m
    - Ordre séquence automatique
    - Calcul distance totale route
    - ETA basé vitesse unité
    - Méthode `generateWaypoints()` : interpolation intelligente
  - **Hazards route** :
    - Détection obstacles/menaces sur trajet
    - Liste zones menace traversées
    - Suggestions contournement
    - Méthode `findHazardsAlongRoute()` : analyse géospatiale
  - **Coordination multi-QRF** :
    - Évite duplication efforts
    - Suggestions split objectifs
    - Calcul distance inter-QRF
    - Table `atak_qrf_coordination` pour sync

#### Maintenance prédictive véhicules
- **Table** : `atak_vehicle_maintenance_log`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes véhicules)
- **Vue enrichie** : `v_atak_vehicle_health` avec `maintenance_score`
- **Features** :
  - **Calcul score maintenance** :
    - Distance parcourue depuis dernière maintenance (40% poids)
    - Historique pannes/dommages (30% poids)
    - Santé composants actuels (30% poids)
    - Formule : `score = distance_factor * 0.4 + history_factor * 0.3 + health_factor * 0.3`
    - Échelle 0-100 (100 = maintenance urgente)
  - **Prédiction panne** :
    - Temps estimé avant panne critique
    - Basé tendance dégradation composants
    - Machine learning simple (régression linéaire)
    - Méthode `predictFailureTime()` : extrapolation
  - **Recommandations automatiques** :
    - "Maintenance préventive sous 48h" (score 60-80)
    - "Inspection moteur urgente" (engine_health < 50%)
    - "Remplacement chenilles recommandé" (track_health < 40%)
    - "Véhicule à immobiliser" (score > 90)
    - Méthode `generateMaintenanceRecommendations()` : règles métier
  - **Log maintenance détaillé** :
    - Type : ROUTINE, PREVENTIVE, CORRECTIVE, EMERGENCY, INSPECTION
    - Composants concernés
    - Pièces remplacées
    - Coût et durée
    - Technicien et lieu

#### Corrélation POI intelligence
- **Table** : `atak_poi_correlations`, `atak_intelligence_analysis`
- **Repository** : `AtakAdvancedIntelligenceRepository` (méthodes POI)
- **Vue enrichie** : `v_atak_poi_enriched` avec `confidence_score`
- **Features** :
  - **Détection corrélations** :
    - Proximité géographique (<500m)
    - Affiliation identique (ENEMY)
    - Compatibilité type (CACHE ↔ ENEMY_POSITION)
    - Temporalité (observations <24h)
    - Méthode `detectPoiCorrelations()` : matching multi-critères
  - **Scoring confiance POI** :
    - Nombre observations (20% poids)
    - Source fiabilité (30% poids)
    - Fraîcheur temporelle (25% poids)
    - Corrélations avec autres POI (25% poids)
    - Formule : `confidence = obs_score + source_score + fresh_score + corr_score`
    - Échelle 0-100 (100 = haute confiance)
    - Méthode `calculatePoiConfidence()` : algorithme complet
  - **Analyse intelligence** :
    - Patterns détectés (clusters hostiles)
    - Suggestions tactiques ("Surveillance zone recommandée")
    - Niveau confiance analyse
    - Méthode `analyzePoiPair()` : corrélation détaillée
  - **Types corrélation** :
    - PROXIMITY : Proximité géographique simple
    - PATTERN : Pattern comportemental
    - ACTIVITY : Activité liée
    - TEMPORAL : Séquence temporelle
    - NETWORK : Réseau hostile

### Migration Phase 2.5

#### Script SQL
- **`migrations/2026_07_24_007_atak_intelligence_enhancements.sql`** : Phase 2.5
  - 9 nouvelles tables
  - 5 vues enrichies
  - 1 trigger calcul menace
  - ~400 lignes SQL commentées
  - Alter tables existantes (ajout colonnes calculées)

### Documentation Phase 2.5

#### Guide technique enrichissements
- **`docs/PHASE-2.5-INTELLIGENCE-ENRICHMENTS.md`** : Documentation complète
  - Détails 6 capacités intelligence
  - Algorithmes expliqués (pseudocode)
  - Cas d'usage opérationnels
  - Diagrammes workflow
  - ~1000 lignes

#### Mise à jour guides existants
- **`docs/GUIDE-INTEGRATION-API-ATAK.md`** : Ajout endpoints Phase 2.5
- **`docs/SYNTHESE-TECHNIQUE-ATAK-PHASES-1-2.md`** : Mise à jour stats (15 tables → 24 tables)
- **`CHANGELOG-ATAK.md`** : Mise à jour version 1.1.0

### Ajouté - Phase MOD : Intégration Arma 3

#### Fonctions SQF tactiques (11 fichiers, ~800 lignes)
- **Localisation** : `mod/@COMSPECOverwatch/addons/connect/functions/`
- **Fonctions principales** :
  - `fn_submitTacticalReport.sqf` : Soumettre rapport (SPOTREP, CONTACT, SITREP, SALUTE)
    - Validation type rapport
    - Sérialisation données structurées
    - Appel extension HTTP
    - Feedback visuel (hint + son confirmation)
    - Logging RPT détaillé
  - `fn_createPOI.sqf` : Créer POI
    - Position automatique (player ou cursorTarget)
    - Marker local temporaire (5min)
    - Catégories prédéfinies
    - Transmission immédiate backend
  - `fn_requestMEDEVAC.sqf` : Demander MEDEVAC 9-Line
    - Format standard NATO
    - Calcul automatique patients litter/ambulatory
    - Intégration ACRE/TFAR (fréquence radio)
    - Hints critiques visuels
    - Son radio transmission
  - `fn_requestQRF.sqf` : Demander QRF
    - Types menace standardisés
    - Estimation force amie automatique (count units group)
    - Suggestions support selon situation
    - Marker local contact (cercle rouge)
    - Son alerte urgence
  - `fn_updateVehicleTracking.sqf` : Update véhicule
    - Données complètes (callsign, classe, side, crew)
    - Position, heading, speed
    - Fuel %, ammo %, health composants
    - Détection automatique état critique
    - Trigger service requests si nécessaire
  - `fn_requestVehicleService.sqf` : Service véhicule
    - Types : REFUEL, REARM, REPAIR, MAINTENANCE, RECOVERY
    - Feedback priorité (fumée verte si CRITICAL)
    - Marker temporaire service
    - Son alerte si urgent
  - `fn_initVehicleTracking.sqf` : Init tracking auto
    - Event handlers : GetInMan, GetOutMan, Killed
    - CBA PerFrameHandler (update 10s)
    - Start/stop automatique
    - Report destruction immédiat
- **Fonctions helpers** :
  - `fn_hashMapToJson.sqf` : Sérialisation HashMap → JSON
    - Support types : STRING, SCALAR, BOOL, ARRAY, HASHMAP
    - Récursif pour structures imbriquées
    - Échappement strings correct
    - ~100 lignes
  - `fn_formatTimestamp.sqf` : Format timestamp SQL
    - Input : systemTime array Arma
    - Output : `YYYY-MM-DD HH:MM:SS`
    - Padding zéros

#### Système menus ACE Interact (~250 lignes)
- **Fichiers** :
  - `fn_initATAKMenu.sqf` : Création menus ACE
  - `fn_initATAK.sqf` : Initialisation principale
  - `XEH_postInitClient.sqf` : Hook CBA Extended Event Handlers
- **Structure menus** :
  ```
  ACE Self-Interact → 📡 ATAK Tactique
    ├─ 📝 Rapports Tactiques
    │   ├─ SPOTREP (Observation)
    │   ├─ CONTACT (Ennemi) [priorité IMMEDIATE auto]
    │   └─ SITREP (Situation)
    ├─ 📍 Marquer POI
    │   ├─ Cache d'armes (ENEMY, PROBABLE)
    │   ├─ Position Ennemie (CONFIRMED)
    │   └─ Objectif (NEUTRAL)
    ├─ 🚁 Demander Appui
    │   ├─ MEDEVAC (9-Line, transmission radio auto)
    │   └─ QRF (marker contact + alerte)
    └─ 🔧 Service Véhicule [si dans véhicule]
        ├─ ⛽ Ravitaillement [si fuel <30%]
        ├─ 🔫 Réarmement
        └─ 🔨 Réparation [si damage >20%]
  ```
- **Conditions dynamiques** :
  - Sous-menu véhicule uniquement si `vehicle player != player`
  - Actions service selon état véhicule (fuel, damage)
- **Feedback visuel** :
  - Hints notifications structurées
  - Markers locaux temporaires (POI, contact, service)
  - Fumée signalisation (verte pour demandes critiques)
  - Sons : radio, alerte, confirmation
- **Raccourcis clavier CBA** :
  - **Shift+R** : Rapport contact rapide (`comspec_atak_quick_report`)
  - **Shift+P** : POI rapide position actuelle (`comspec_atak_quick_poi`)
- **Initialisation automatique** :
  - Vérification extension au boot (`GetVersion`)
  - Init tracking véhicules
  - Création menus ACE (si addon détecté)
  - Event handlers respawn (réinit système)
  - Boucle maintenance 60s (polling futures)

#### Extension C# v2.0 (~350 lignes)
- **Localisation** : `mod/@COMSPECOverwatch/extension-source-example/`
- **Fichiers** :
  - `ExtensionMain.cs` : Code principal
  - `COMSPECExtension.csproj` : Projet .NET 6
  - `build.sh` / `build.bat` : Scripts compilation
  - `README.md` : Documentation complète
- **Architecture** :
  ```
  Arma 3 (SQF)
    ↓ callExtension "COMSPECExtension"
  RVExtensionArgs (C# DLL)
    ↓ ProcessCommand(command, jsonData)
  SendHttpRequest(method, endpoint, json)
    ↓ HttpClient.PostAsync()
  Backend API PHP
  ```
- **Commandes implémentées** :
  - `GetVersion` → Retourne "2.0"
  - `Connect` → Init API (URL + Token)
  - `SubmitTacticalReport` → POST `/api/atak/reports`
  - `CreatePOI` → POST `/api/atak/poi`
  - `RequestMEDEVAC` → POST `/api/atak/medevac`
  - `RequestQRF` → POST `/api/atak/qrf`
  - `UpdateVehicleTracking` → POST `/api/atak/vehicles`
  - `RequestVehicleService` → POST `/api/atak/vehicles/service`
- **Optimisations** :
  - HttpClient singleton (connection pooling)
  - Retry policy 3x avec backoff exponentiel (5xx seulement)
  - Timeout 10s configurable
  - Cache vehicle_id local (évite lookups répétés)
  - Logs détaillés : `%LOCALAPPDATA%\Arma 3\COMSPECExtension.log`
- **Gestion erreurs** :
  - Codes retour : `OK`, `ERROR`, `NETWORK_ERROR`, `TIMEOUT`
  - Messages structurés JSON : `["status", "message"]`
  - Logging toutes erreurs avec timestamp
- **Sécurité** :
  - Tokens passés dynamiquement (config CBA)
  - Headers `X-ATAK-Token`, `User-Agent`
  - HTTPS obligatoire production
  - Validation inputs côté SQF avant envoi
- **Technologies** :
  - .NET 6.0
  - Newtonsoft.Json (sérialisation)
  - UnmanagedExports (export DLL pour Arma)

#### Configuration et intégration
- **`mod/@COMSPECOverwatch/addons/connect/config.cpp`** (modifié) :
  - Déclarations 11 nouvelles fonctions
  - Hook CBA Extended Event Handlers :
    ```cpp
    class Extended_PostInit_EventHandlers {
        class comspec_overwatch_connect {
            clientInit = "call compile preprocessFileLineNumbers '...XEH_postInitClient.sqf'";
        };
    };
    ```
  - Version bumped : 1.1.3 → 1.2.0
- **Prérequis** :
  - Arma 3
  - CBA A3 (obligatoire)
  - ACE3 (recommandé pour menus)
  - Extension `COMSPECExtension_x64.dll` v2.0
- **Configuration CBA** :
  - **Option A : Liaison rapide** (recommandée)
    1. Athena → ATAK → "Générer code liaison"
    2. En jeu : K → "Connecter compte" → coller code
  - **Option B : Manuelle**
    - URL : `https://athena.ttrd.fr/public`
    - Token ATAK : Généré sur interface web
    - ID Communauté : Fourni par admin
- **BattlEye** :
  - Whitelist DLL dans `battleye/beserver_x64.cfg` :
    ```cpp
    allowedLoadFileExtensions[] = {"dll"};
    allowedPreloadFileExtensions[] = {"dll"};
    ```

### Documentation MOD

#### Guides utilisateur (4 documents, ~2000 lignes)
- **`mod/@COMSPECOverwatch/README.md`** : Documentation principale enrichie
  - Section complète features ATAK détaillées
  - Raccourcis clavier (ajout Shift+R, Shift+P)
  - Menus ACE avec hiérarchie complète
  - Tracking véhicules automatique
  - Prérequis et installation
- **`mod/@COMSPECOverwatch/GUIDE-INSTALLATION-TEST.md`** : Guide complet
  - Installation étape par étape (mod, config CBA, extension)
  - 7 tests manuels détaillés (rapport, POI, MEDEVAC, QRF, tracking, service)
  - Script test automatique SQF
  - Troubleshooting complet (extension not found, 401, menus absents, tracking inactif)
  - Checklist déploiement production
- **`mod/@COMSPECOverwatch/EXTENSION_C#_SPECIFICATION.md`** : Spec technique extension
  - Détails 8 commandes avec formats JSON
  - Exemples C# implémentation
  - Configuration HTTP client (headers, timeout, retry)
  - Gestion erreurs et logging
  - Optimisations recommandées (cache, batching, async)
  - Tests recommandés (unitaires + intégration)
  - Compilation et déploiement
- **`mod/@COMSPECOverwatch/extension-source-example/README.md`** : Doc extension
  - Prérequis développement (Visual Studio, .NET 6)
  - Commandes disponibles avec exemples
  - Architecture code (diagramme)
  - Performance et optimisations
  - Contribution et ajout nouvelles commandes
  - Sécurité (tokens, HTTPS)
  - Troubleshooting compilation

### Documentation projet

#### Synthèses et récapitulatifs
- **`docs/RECAPITULATIF-INTEGRATION-MOD-ATAK.md`** : Synthèse MOD
  - Composants livrés détaillés
  - Structure menus ACE complète
  - Statistiques code (~2900 lignes MOD)
  - Intégration backend (31 endpoints)
  - Tests et validation
  - Déploiement et configuration
- **`docs/SYNTHESE-FINALE-INTEGRATION-ATAK.md`** : Document maître (~850 lignes)
  - Vue d'ensemble Backend + MOD
  - Architecture complète (BDD, repos, API, SQF, extension)
  - Algorithmes intelligence Phase 2.5 détaillés
  - Statistiques globales (17100 lignes produites)
  - Fonctionnalités opérationnelles (joueurs + commandement)
  - Tests & validation
  - Guide déploiement complet
  - Roadmap futures phases

### Dépendances

#### Backend
- PHP >= 8.0
- MySQL >= 8.0
- Extension PDO MySQL
- Authentification COMSPEC existante

#### Frontend (en attente Phase JS)
- Leaflet.js
- JavaScript ES6+

#### Mod Arma 3
- ✅ CBA A3 (obligatoire)
- ✅ ACE3 (recommandé)
- ✅ Extension C# .NET 6
- ✅ SQF functions (11 fichiers)
- ✅ Arma 3 >= 2.0

---

## [À venir] - Phase 3 : Coordination avancée

### Planifié

#### Waypoints et routes partagées
- Table `atak_shared_waypoints`
- Synchronisation bidirectionnelle web ↔ jeu
- Calcul distance et temps estimé
- Routes partagées entre unités
- Visualisation temps réel sur carte

#### Timeline mission interactive
- Table `atak_mission_timeline`
- Agrégation tous événements (rapports, contacts, MEDEVAC, QRF, etc.)
- Filtres par type, unité, criticité
- Navigation temporelle
- Export PDF/Excel pour AAR

#### Contrôle artillerie et mortiers
- Table `atak_fire_missions`
- Calcul balistique (élévation, azimut, charge)
- Visualisation zone impact
- Workflow mission feu NATO
- Corrections tir (shot, splash, impact)

---

## [À venir] - Phase 4 : Capacités avancées

### Planifié

#### Système UAV et reconnaissance
- Table `atak_uav_tracking`
- Flux vidéo (captures périodiques)
- Détection automatique contacts
- Zones surveillance
- Handoff entre opérateurs

#### IFF avancé
- Extension système IFF existant
- Interrogation active
- Code du jour dynamique
- Alertes véhicule inconnu
- Intégration avec véhicules

#### Intégration météo opérationnelle
- Table `atak_weather_log`
- Impact visibilité/portée
- Alertes conditions critiques
- Prévisions mission
- Historique pour AAR

---

## [À venir] - Phase 5 : Immersion totale

### Planifié

#### Mode replay complet
- Reconstruction mission 3D
- Contrôles vidéo (play, pause, vitesse)
- Changement point de vue
- Export MP4
- Analyse post-mission

#### Système certifications LMS
- Intégration avec LMS existant
- Déblocage capacités selon certification
- Badges visibles in-game
- Progression utilisateur
- Rapports performance

#### Contrôle caméra et observation
- Stream images caméras terrain
- Demande vues spécifiques
- Contrôle PTZ (pan, tilt, zoom)
- Archive pour AAR
- Multi-flux simultanés

---

## Versionning

**Format** : [MAJOR.MINOR.PATCH]

- **MAJOR** : Changements incompatibles API
- **MINOR** : Ajout features rétrocompatibles
- **PATCH** : Corrections bugs rétrocompatibles

**Version actuelle** : 1.2.0 (Phases 1, 2, 2.5 + MOD Arma 3 complètes)

---

*Document maintenu par l'équipe développement COMSPEC*
