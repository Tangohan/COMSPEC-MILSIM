# ATAK natif : inventaire de ce qui peut être retiré ou fusionné

Date : 2026-11-15. Version de départ : ATAK 4.26.0. Ce document est un rapport : rien n'a été supprimé pour l'écrire.

## Méthode

Scripts d'analyse : `fn2.py`, `settings.py`, `classes.py`, `data.py`, `i18n.py`, `zh.py` (dossier de travail de la PR,
hors dépôt ; à relancer avant d'agir, les chiffres bougent avec la PR).

Périmètre : `mod/COMSPEC_ATAK_Native/Sources/addons/{main,items,towers}`, les PBO livrés de
`mod/COMSPEC_ATAK_Native/@COMSPEC_ATAK_Native/addons`, puis, plus rapidement, `mod/@COMSPEC_SSE` et
`mod/UptoDate` (Overwatch connect).

Les chiffres viennent de scripts d'analyse statique (Python, hors dépôt) lancés sur l'arbre de travail de la PR en
cours, donc avec les changements des autres volets de la PR déjà présents :

| Analyse | Règle |
| --- | --- |
| Fonctions | Chaque `class X {};` de `CfgFunctions` (main et towers). Références au nom complet `comspec_atak_native_fnc_X` dans tous les `.sqf .cpp .hpp .xml .ext .sqm .cs` de `mod/` (ATAK, pack SSE, Overwatch), commentaires retirés. Puis atteignabilité depuis les racines : config, `XEH_*.sqf`, attributs `postInit`, autres mods. |
| Réglages CBA | Chaque `comspec_atak_native_*` déclaré dans `XEH_preInit.sqf`, cherché partout ailleurs, y compris les noms construits par `format`. |
| Classes de config | Chaque `COMSPEC_ATAK_*` de `config.cpp`, `modules.hpp`, `toc_*.hpp` et des `.hpp` de towers, compté dans le SQF, les configs, les autres mods et les missions. |
| Textures et modèles | Tous les fichiers de `main/data`, `items/data`, `towers/data`, `main/fonts`, `main/anim`. Un fichier est « référencé » si son nom de base apparaît dans un `.sqf .cpp .hpp .xml .rvmat`, « référencé par motif » si un nom construit par `format` le couvre (`phone_%1%2.paa`, `crack_%1_%2`, `%1wall_%2%3_%4.paa`…), « par le modèle » s'il n'est cité que dans un `.p3d`. |
| Dictionnaires | `main/i18n/*.txt` lus ligne à ligne. Une clé est « présente » si elle est une sous-chaîne exacte du code. |

Vocabulaire : **mort** = prouvé par une recherche au résultat nul ; **incertain** = la recherche ne tranche pas (nom
construit à l'exécution, API offerte aux missions, texte venu du site) ; **vivant mais lourd** = utilisé, le gain vient
d'une réduction, pas d'un retrait.

Relecture à la main des résultats de l'analyse des fonctions (15 fonctions) : `tocRoster`, `tocPanels`, `mapBandGrid`,
`infraPowered`, `btInit`, `introSplash`, `loadingBadge`, `loadingLogo`, `pageOsint`, `osintAction`, `pageBreach`,
`jpcScreen`, `importLegacyData`, `pageSms`, `trMenu`. Le détail est dans la section « Fonctions ».

### Limites

- Analyse statique seulement : rien n'a été lancé en jeu, aucun PBO n'a été reconstruit.
- Les gains en octets sont ceux des fichiers source. Le PBO binarisé diffère : `towers.pbo` pèse 89 380 262 octets pour
  56 064 067 octets de `towers/data`.
- Les gains « estimés » des lignes « réduire » sont des ordres de grandeur, pas des mesures.
- Les missions, tenues et arsenaux des communautés ne sont pas dans le dépôt : le risque qui les concerne est évalué,
  pas mesuré. Aucune mission du dépôt (`.sqm`) ne cite une classe listée ici comme retirable.
- Les textes envoyés par le site ou par la DLL ne sont pas dans le code SQF : une clé de dictionnaire « absente » peut
  servir à les traduire.

## Tableau de synthèse

Classé par gain décroissant (octets retirables, puis complexité). Tailles en octets des sources.

| Rang | Élément | Chemin | Gain estimé | Risque | Recommandation |
| --- | --- | --- | --- | --- | --- |
| 1 | Images PNG dans le PBO Overwatch connect | `mod/UptoDate/Sources/comspec-overwatch-addons/connect/img` | 24,3 Mo au plus ; 11,0 Mo jamais cités | Faible, à confirmer (incertain) | Retirer du PBO |
| 2 | 13 fonds d'écran (52 fichiers) | `main/data/wall_*`, `items/data/walls/wall_*` | 8 à 12 Mo sur 20,2 Mo | Faible (repli « aucun ») | Fusionner : garder 5 ou 6 fonds |
| 3 | Deux DLL d'extension | `@COMSPEC_ATAK_Native/*.dll` | 10,2 Mo si une seule DLL | Élevé | Garder (incertain) |
| 4 | Modèles de ruine des pylônes relais | `towers/data/relay_*_ruin.p3d` | 9 à 12 Mo sur 18,1 Mo | Faible (visuel) | Réduire |
| 5 | Usure et dégâts d'écran (253 textures) | `main/data/{crack,gx,bloodc,bloodd,dirt,shatter,glitch,fx}_*`, `items/data/dmg` | 5 à 8 Mo sur 21,1 Mo ; dont 2,7 Mo de sang séché | Faible | Réduire |
| 6 | Coques de téléphone (41 textures) | `main/data/phone_*.paa` | 4 à 6 Mo sur 19,0 Mo | Moyen (qualité visible) | Garder, réduire |
| 7 | Tablette sur le JPC, fermée (W.I.P) | `fn_jpcScreen`, `fn_jpcMap`, `main/data/jpc_*` | 1,46 Mo sûr ; 12,4 Mo d'atlas au total | Moyen (geste selfie) | Finir ou retirer |
| 8 | Polices embarquées hors du choix | `main/fonts/{b612,rajdhani,sharetech}` | 3,73 Mo (+1,95 Mo avec Barlow) | Faible | Retirer |
| 9 | Kit de terminal de véhicule | `items/data/item3d_vehiclekit.p3d`, `main/data/item_vehiclekit.paa` | 1,47 Mo | Moyen (tenues enregistrées) | Caché dans cette PR ; retirer plus tard |
| 10 | Dictionnaire anglais : clés absentes du code | `main/i18n/en.txt` | 345 Ko au plus (15,3 %) | Moyen (incertain) | Trier, puis retirer |
| 11 | Petites textures sans référence | `main/data/logo_soar.paa`, `dirt_smear_*`, `dirt_drop` | 84 Ko | Nul | Retirer |
| 12 | Dictionnaire français : clés absentes | `main/i18n/fr.txt` | 52 Ko au plus | Moyen (incertain) | Trier |
| 13 | API des panneaux TOC non consommée | `fn_tocPanels`, `fn_tocRoster` | 27,7 Ko, 412 lignes | Moyen (API documentée) | Garder (incertain) |
| 14 | App Brèche grisée « W.I.P » | `functions/tactical/fn_*reach*` | 18,7 Ko, une tuile | Faible | Cacher comme OSINT, puis finir ou retirer |
| 15 | App OSINT | `fn_pageOsint`, `fn_osintAction` | 10,9 Ko | Faible | Retirée du lanceur dans cette PR ; finir ou supprimer |
| 16 | Regroupements du lanceur | `config.cpp` (`COMSPEC_ATAK_Apps`) | 22 tuiles en moins sur 73 | Faible si les pages restent | Fusionner (dossiers) |
| 17 | Menus ACE en double | `functions/shell/fn_aceMenu.sqf` | 9 entrées, environ 60 lignes | Faible | Fusionner |
| 18 | Chemins de compatibilité | `fn_importLegacyData`, `fn_tocAccess` « legacy », `fn_tocWall` | 2,2 Ko + gardes | Moyen (mods mélangés) | Garder, dater le retrait |
| 19 | Notifications Arma déclarées, plus utilisées | `config.cpp` (`CfgNotifications`) | 16 lignes | Nul | Retirer |
| 20 | Réglages réseau sans effet en liaison satellite | `XEH_preInit.sqf` | Lisibilité (jusqu'à 89 réglages) | Faible | À compléter par la PR |
| 21 | Réglages CBA morts | `XEH_preInit.sqf` | 0 | Sans objet | Rien à retirer |

## 1. Objets et classes de config

308 classes `COMSPEC_ATAK_*` sont déclarées (134 dans `config.cpp`, 78 dans `cfg_relays.hpp`, 32 dans
`toc_screens3d.hpp`, 17 modules, 17 caméras, 14 objets d'infrastructure, 12 écrans d'origine, 4 dans `toc_pc.hpp`). La
grande majorité sont des objets à poser (pylônes, caméras, écrans, variantes de couleur) : ne pas être cités par le SQF
est leur état normal. Seuls les cas ci-dessous méritent une décision.

### Kit de terminal de véhicule (fait dans cette PR, retrait complet proposé)

- Chemin : `main/config.cpp`, classes `COMSPEC_ATAK_VehicleKit` (ligne 388) et `Item_COMSPEC_ATAK_VehicleKit`
  (ligne 718).
- Ce que c'est : l'objet « ATAK vehicle terminal kit », qui donnait la tablette de bord à un véhicule.
- Fait dans cette PR : classe cachée (`scope=1; scopeCurator=0; scopeArsenal=0`) et rendue inerte ; la valeur `"kit"`
  du réglage `comspec_atak_native_vehicle_terminal` est traitée comme `"all"` (`fn_vehicleTerminal.sqf`, ligne 7).
- Preuve : `grep -ri vehiclekit` sur les sources ne trouve plus que `config.cpp` (6 occurrences, les deux classes) et
  un commentaire de `fn_vehicleTerminal.sqf`. Aucune mission du dépôt ne le cite.
- Risque d'un retrait complet : une tenue, un arsenal ou une caisse de mission enregistrés avec la classe affichent
  une erreur d'objet manquant. C'est la raison de la garder cachée pendant quelques versions.
- Gain du retrait complet : `item3d_vehiclekit.p3d` 1 418 943 octets, `item_vehiclekit.paa` 40 919 octets,
  `item3d_vehiclekit_preview.jpg` 8 310 octets, soit 1 468 172 octets, plus les deux classes.
- Recommandation : cacher (fait), retirer les fichiers à la prochaine version majeure.

### Notifications Arma déclarées et plus utilisées

- Chemin : `main/config.cpp`, lignes 14 à 29, `CfgNotifications` : `COMSPEC_ATAK_AiReply` et
  `COMSPEC_ATAK_AiReplyWarn`.
- Preuve : `grep -rn "AiReply\|showNotification" main --include=*.sqf` renvoie une seule ligne, un commentaire de
  `functions/teams/fn_aiOrder.sqf` (ligne 132) : « déclarée dans config.cpp, plus utilisée ». Aucun appel à
  `BIS_fnc_showNotification`.
- Risque : nul. Gain : 16 lignes de config. Recommandation : retirer. Mort.

### Tablette sur le JPC (fermée, W.I.P)

- Chemin : `functions/shell/fn_jpcScreen.sqf` (14 759 octets), `fn_jpcMap.sqf` (7 371 octets), classes
  `COMSPEC_ATAK_JpcTablet` et `COMSPEC_ATAK_JpcPhone_*` (`config.cpp`, lignes 605 à 637), 17 fichiers
  `main/data/jpc_*` (12 474 688 octets).
- Preuve : `fn_jpcScreen.sqf`, ligne 40 : `private _allowed = false;` ; `["wip"]` renvoie donc toujours vrai. Les deux
  actions ACE (`COMSPEC_ATAK_JpcOpen`, `COMSPEC_ATAK_JpcClose`) et la touche « Tablette sur le JPC » sont masquées par
  ce test. La boucle `["tick"]` tourne quand même (`XEH_postInitClient.sqf`, ligne 576).
- Ce qui reste vivant : les classes `COMSPEC_ATAK_JpcPhone_*` et les atlas `jpc_<modèle>_ca.paa` servent au geste
  selfie (`fn_gestureSelfie.sqf`, lignes 53 à 55). Seuls `jpc_tablet_co.paa` (1 441 079 octets) et `jpc_tablet.p3d`
  (18 363 octets) ne servent qu'à la fonction fermée.
- Risque : faible pour la tablette ; moyen pour les atlas (le téléphone du selfie perdrait sa coque).
- Gain : 1 459 442 octets sûrs et 22 Ko de code si la fonction est abandonnée ; les huit atlas (10 789 552 octets,
  entre 1,0 et 1,8 Mo pièce) peuvent être réduits pour un accessoire tenu à bout de bras.
- Recommandation : décider (finir ou retirer). En attendant, réduire les atlas.

### Classes sans référence à garder

| Classe | Constat | Recommandation |
| --- | --- | --- |
| `COMSPEC_ATAK_RelayTower_*_ruins` (11 classes) | Citées par la config seulement : c'est le mécanisme des ruines du jeu | Garder |
| `Item_COMSPEC_ATAK_*` (37 classes) | Objets au sol des items, pour l'éditeur | Garder |
| `COMSPEC_ATAK_Module_DataReset` | `scope=1` (caché), fonction `moduleDataReset` citée une fois, par `modules.hpp` | Garder (incertain : missions enregistrées) |
| `toc_screens_legacy.hpp` (12 classes) | Premiers écrans TOC refaits avec les modèles du mod ; noms gardés pour `fn_tocPersist` | Garder |

## 2. Apps du lanceur

Le registre est `class COMSPEC_ATAK_Apps` (`main/config.cpp`) : 73 apps dans l'ATAK, plus 4 déclarées par le pack SSE
(`SseField`, `IdCheck`, `Fichiers`, `IdCover`). Répartition : Renseignement 13, Système 13, Mission 12,
Communication 9, Navigation 8, Spécialités 7, Civil 6, Appui et feux 5. La visibilité passe par `fn_appVisible.sqf`.

### Fait dans cette PR

- **OSINT** : l'app grisée « W.I.P » est retirée du lanceur (`hidden=1`, testé dans `fn_appVisible.sqf`). Le code
  reste : `fn_pageOsint.sqf` (4 495 octets) et `fn_osintAction.sqf` (6 367 octets), encore branchés dans
  `fn_pageRender.sqf` (ligne 133) et `fn_appSpaces.sqf` (ligne 163). À finir ou à supprimer. Attention : l'icône
  `app_osint.paa` reste utilisée par la recherche de la carte (`fn_pageMap.sqf`, lignes 316 et 334), elle ne part pas
  avec l'app.
- **Dossier SSE** : les apps SSE sont regroupées derrière une tuile « SSE » (`class SseHub`, `fn_sseHub.sqf`,
  `fn_pageSseHub.sqf`). Aucune classe ni page n'est renommée.
- Icônes en double corrigées : après la PR, aucune icône n'est partagée par deux apps (contrôle : 73 apps, 73 icônes).

### Brèche : même cas qu'OSINT, non traité

- Chemin : `class Breach` (`wip=1`, sans `hidden`), `functions/tactical/fn_pageBreach.sqf` (12 234 octets),
  `fn_breachAction.sqf` (3 071), `fn_breachBuilding.sqf` (2 020), `fn_breachTop.sqf` (1 405).
- Preuve : `wip=1` affiche la tuile grisée et bloque l'ouverture (`fn_launcherRender.sqf`, ligne 513 ;
  `fn_pageStore.sqf`, ligne 109). 18 730 octets de code livrés pour une app qui ne s'ouvre pas.
- Risque : faible. Recommandation : cacher comme OSINT (`hidden=1`), puis finir ou retirer.

### SMS : doublon de Messages

- Preuve : `fn_pageSms.sqf` pèse 898 octets et se termine par `[] call comspec_atak_native_fnc_pageChat`. La
  description de l'app Messages dit déjà « Canaux de l'unité et SMS entre joueurs ».
- Recommandation : fusionner (un onglet SMS dans Messages), en gardant la page `SMS` pour les raccourcis.

### Regroupements proposés, non faits

Chaque regroupement garde les classes et les pages (raccourcis, épingles et profils enregistrés continuent de marcher),
sur le modèle du dossier SSE.

| Regroupement | Apps | Tuiles en moins |
| --- | --- | --- |
| Photo et vidéo | Photo, Galerie, Vidéo live | 2 |
| Comptes rendus de renseignement | FRS / FRM, Reco, SALUTE | 2 |
| Messages | Messages, SMS | 1 |
| Appui feu | FIRES court, CAS, JTAC | 2 |
| Comptes rendus de mission | Comptes rendus, CR fin d'op, Bilan de l'année | 2 |
| Itinéraires | GPS, Itinéraire, Routes, Distances | 3 |
| État et liaison | Réseau, Synchro, Statut, Debug | 3 |
| Mon téléphone | Profil, Accessoires, Temps d'écran | 2 |
| Civil | Musique, Calculette, Bloc-notes, Vidéo YouTube, UberEats, Tinder | 5 |

Total : 22 tuiles en moins sur 73. Les deux premiers sont ceux déjà proposés ; les autres sont relevés ici. Restent à
part : Carte, BFT, Groupe, Tâches, Athena (dock), Drone, Médical, Zones, Réglages, App Store.

## 3. Réglages CBA

- 341 réglages `comspec_atak_native_*` sont déclarés dans `XEH_preInit.sqf` (un de plus dans
  `towers/functions/infra/fn_infra.sqf`).
- Preuve : 11 réglages n'ont aucune lecture hors de `XEH_preInit.sqf`. Aucun n'est mort :
  - 8 réglages `relay_{portable,mat,tour,satellite}_{range,mbps}` sont lus par nom construit :
    `format ["comspec_atak_native_relay_%1_range", _kind]` (`fn_moduleRelay.sqf`, `fn_relayConvert.sqf`) ;
  - `intro_enabled` et `intro_sound` sont recopiés dans le profil par leur fonction de rappel, puis lus par
    `fn_introSplash.sqf` (ligne 19) ;
  - `shot_check` est une case-action qui se remet à faux elle-même (`XEH_preInit.sqf`, ligne 343).
- Conclusion : aucun réglage mort. Le sujet est le nombre : `mesh_*` 37, `toc_*` 26, `relay_*` 15, `mpu*` 15,
  `net_*` 13, `radio_*` 9.
- À surveiller : les deux réglages « Intro » pilotent une intro « désactivée par défaut (1.67.0 : buggée) » d'après le
  commentaire de `XEH_preInit.sqf`. Incertain : à réparer ou à retirer avec `fn_introSplash.sqf`, `intro_logo_ca.paa`
  (62 083 octets) et `intro_chime.ogg` (20 586 octets).

### Réglages réseau sans effet en liaison satellite

La PR ajoute le réglage « Liaison du téléphone » (`comspec_atak_native_link_mode` : `auto`, `sat`, `relay`), lu par
`fn_linkMode.sqf`, seule source de vérité de `fn_relayNet` (`"only"`, `"required"`).

- En liaison satellite, `fn_relayNet "only"` et `"required"` sont faux : `relay_only` et `relay_gate` n'ont plus
  d'effet ; les relais présents « ne font qu'améliorer le débit » (en-tête de `fn_linkMode.sqf`).
- Candidats à vérifier, 89 réglages : `relay_*` (15), `mesh_*` (37), `mpu*` (15), `radio_*` (9), `net_*` (13).
- **À compléter par la PR** : la liste exacte des réglages ignorés en mode satellite dépend du volet réseau en cours
  d'écriture. Recommandation : les griser ou les regrouper sous une sous-catégorie « Liaison par relais » plutôt que de
  les retirer (réglages de serveur enregistrés).

## 4. Actions ACE et menus en double

Chemin : `functions/shell/fn_aceMenu.sqf` (236 lignes) et les actions d'entretien de `XEH_postInitClient.sqf`.

| Doublon | Preuve | Recommandation |
| --- | --- | --- |
| Personnaliser : couleur du module DL-7 | Trois entrées pour le même profil `COMSPEC_ATAK_ModuleTint` : `fn_aceMenu.sqf` ligne 74, `fn_pageSettings.sqf` ligne 244, `fn_accessoriesAction.sqf` ligne 78 | Garder l'app Accessoires, retirer l'entrée ACE |
| Personnaliser : patch au dos, téléphone sur le gilet | `fn_aceMenu.sqf` lignes 75 à 78, déjà dans `fn_pagePatches.sqf` et `fn_vestMount.sqf` | Fusionner dans l'app |
| Accessoires : batterie externe, filtre JVN | Quatre actions ACE (`COMSPEC_ATAK_PbOn/PbOff/FilterOn/FilterOff`) et les mêmes gestes dans `fn_accessoriesAction.sqf` (lignes 87 à 154) | Garder un seul chemin |
| Quatre « réparer » | « ATAK : réparer l'affichage » (racine), « Réparer mes données », « Réparer le téléphone », « Réparer l'écran (caisse à outils) » | Fusionner en une entrée qui choisit |
| Identifiant d'action réutilisé | `COMSPEC_ATAK_Repair` est déclaré deux fois avec deux sens : `fn_aceMenu.sqf` ligne 32 (racine) et `XEH_postInitClient.sqf` ligne 960 (sous Entretien). Chemins différents, donc pas de collision, mais prêtant à confusion | Renommer l'un des deux |
| Actions Overwatch reprises | `fn_aceMenu.sqf` retire après coup `COMSPEC_RepairAtakPower`, `COMSPEC_RepairAtakScreen`, `COMSPEC_DiagnosticAtak` posées par Overwatch | Ne plus les poser côté Overwatch |

Risque : faible (aucun état enregistré ne dépend d'une action ACE). Gain : 9 entrées de menu, environ 60 lignes.

## 5. Fonctions

- 731 fonctions déclarées, 731 fichiers trouvés, 9 777 952 octets, 134 770 lignes.
- 380 racines, 727 fonctions atteintes, **4 non atteintes** :

| Fonction | Taille | Relecture | Verdict |
| --- | --- | --- | --- |
| `tocPanels` | 11 903 octets, 173 lignes | Aucun appel dans le code ; se cite elle-même ; API décrite dans `docs/TOC-DATA-API.md` | Incertain : API non consommée ici |
| `tocRoster` | 15 769 octets, 239 lignes | Appelée seulement par `tocPanels` | Incertain, même raison |
| `mapBandGrid` | 5 292 octets, 78 lignes | Fichier nouveau, non suivi par git, d'un autre volet de la PR | En cours dans cette PR, pas mort |
| `infraPowered` | 514 octets, 9 lignes | Aucune référence ; l'en-tête donne un exemple pour les missions | Incertain : API de mission |

- `btInit` n'a aucun appel par son nom mais porte `postInit=1` : vivante.
- `trMenu` n'est appelée que par les autres mods (`@COMSPEC_SSE/addons/core/functions/fn_addSetting.sqf`,
  Overwatch connect) : vivante, ne pas la retirer sur la foi d'une recherche limitée à l'ATAK.
- Trois fichiers ne sont pas déclarés dans `CfgFunctions` et sont chargés par `compile preprocessFileLineNumbers`
  (`config.cpp`, lignes 1131 à 1133, et `fn_introSplash.sqf` ligne 28) : `fn_introSplash.sqf`, `fn_loadingLogo.sqf`,
  `fn_loadingBadge.sqf`. Vivants.
- Un seul appel dynamique : `format ["comspec_atak_native_fnc_%1", _this]` dans `fn_tocWall.sqf` (ligne 50), pour tester
  l'existence de `tocBroadcast`, `tocManager`, `tocLayout`. Toutes trois sont déclarées : ces gardes sont toujours
  vraies (voir « Compatibilité »).
- 163 fonctions n'ont qu'une seule référence (2 140 962 octets). Elles ne sont pas mortes ; les plus petites
  (`dataBarEnabled` 221 octets, `schedulerStart` 342, `notifCenter` 359, `firesGuns` 367) peuvent rentrer dans leur
  appelant.

Conclusion : pas de fonction morte prouvée. Le code atteignable mais fermé est ailleurs : OSINT, Brèche, JPC.

## 6. Chemins de compatibilité

| Élément | Chemin | Constat | Recommandation |
| --- | --- | --- | --- |
| PBO hérités `ATAK_*`, `BCE_cTab_*`, addons `.off` | `@COMSPEC_ATAK_Native/addons` | Aucun. Le dossier livré contient 5 PBO : `main` 95 535 630, `towers` 89 380 262, `comspec_overwatch_connect` 48 463 257, `items` 22 254 547, `comspec_overwatch_main` 178 077 octets | Rien à retirer |
| `fn_importLegacyData` | `functions/network/` (2 246 octets) | Reprend `COMSPEC_Orders` publié par COMSPEC Link ; appelée toutes les 10 s (`fn_schedulerTick.sqf` ligne 39) et par `fn_offline.sqf`. La variable est encore écrite par Overwatch (`atak_athena/functions/fn_athena_taskRespond.sqf`) | Garder tant que Link est livré |
| Événement d'avant la 4.12.0 | `fn_tocHall.sqf` ligne 101, `fn_tocAccess.sqf` cas « legacy » | Accepte les demandes de joueurs dont le mod est plus ancien | Retirer quand la version minimale dépasse 4.12.0 |
| Gardes « fonction présente » | `fn_tocWall.sqf` lignes 50 à 138 | Dix tests d'existence de fonctions toujours présentes dans le PBO actuel | Retirer (incertain : PBO mélangés) |
| `COMSPEC_ATAK_LegacyBootstrapSuppressed` | `XEH_postInitClient.sqf` ligne 35 | Variable écrite, lue nulle part dans `mod/` | Retirer. Mort |
| Deux DLL | `COMSPECATAKNativeExtension_x64.dll` 10 391 040, `COMSPECExtension_x64.dll` 10 208 256 octets | Les deux sont appelées : la première par l'ATAK, la seconde par Overwatch connect | Garder ; fusion incertaine et coûteuse |
| `toc_screens_legacy.hpp` | `main/` (128 lignes) | Classes d'origine gardées pour les salles TOC enregistrées | Garder |

## 7. Textures, modèles et polices

Total mesuré : 1 673 fichiers, 198 287 809 octets (`main/data` 104 559 041, `towers/data` 56 064 067, `items/data`
29 582 650, `main/fonts` 5 682 997, `main/anim` 2 318 040). Par type : `.paa` 105,5 Mo (1 263 fichiers), `.p3d` 88,4 Mo
(128 fichiers).

### Sans aucune référence

| Fichier | Taille | Preuve | Recommandation |
| --- | --- | --- | --- |
| `main/data/logo_soar.paa` | 50 693 | Nom absent des sources ; cité par `tools/gen_assets.py` seulement | Retirer. Mort |
| `main/data/dirt_smear_{0,1,2}.paa` | 30 155 | Cités par `tools/gen_dirt.py` seulement | Retirer. Mort |
| `main/data/dirt_drop.paa` | 3 068 | Idem | Retirer. Mort |
| `towers/data/relay_glass.rvmat` | 1 308 | Absent du code et des `.p3d` | Incertain |

Le reste des 1 673 fichiers est référencé : par son nom dans le code (937 fichiers), par un nom construit par `format`
(529, plus les 52 fonds d'écran et 4 écrans du socle de recharge reclassés à la main), par un modèle `.p3d` (24), par
le pack SSE (10 : les `mod_*.paa` et `wall_athena_blur_land.paa`). Les 96 pages `.paa` des polices sont chargées par
leur fichier `.fxy` et ne sont jamais citées par leur nom. Les 17 glyphes de `main/data/toc/led` (56 854 octets)
portent un numéro de caractère et ne sont cités que par `tools/gen_toc_led_glyphs.py` : incertain, non creusé.

### Polices embarquées hors du choix

- Chemin : `main/fonts/b612` (1 809 339 octets, 49 fichiers), `rajdhani` (1 262 993, 49), `sharetech` (660 971, 25),
  et leurs familles dans `main/ui/fonts.hpp`.
- Preuve : `fn_fontSet.sqf` ne propose que `roboto`, `purista` et `barlow2` ; son en-tête dit « les polices embarquées
  sont retirées du choix en 1.84.0 ». `grep "COMSPEC_B612\|COMSPEC_Rajdhani\|COMSPEC_ShareTech"` sur l'ATAK, le pack
  SSE et Overwatch ne trouve que les 5 déclarations de `fonts.hpp`.
- Risque : faible. Un ancien choix enregistré retombe sur `roboto` (`fn_fontSet.sqf` : clé inconnue).
- Gain : 3 733 303 octets et environ 90 lignes de `fonts.hpp`. `barlow` (1 949 694 octets) n'est proposée que comme
  « Barlow Condensed (test) » : 1,95 Mo de plus si l'essai est abandonné.
- Recommandation : retirer les trois familles. Mort.

### Fonds d'écran

- Chemin : `main/data/wall_*` (8 fonds, 14 009 068 octets) et `items/data/walls/wall_*` (5 fonds, 6 183 973 octets).
  Chaque fond existe en quatre fichiers : portrait, paysage, et les deux versions floues.
- Preuve : la liste est dans `fn_wallPath.sqf` ; les plus lourds sont `ops` 2 775 209, `soar` 1 851 763, `sof`
  1 829 718, `dark` 1 824 037. L'en-tête du fichier note que `main.pbo` « approche la limite de 100 Mo ».
- Risque : faible. Une clé inconnue renvoie `""`, soit « aucun fond ».
- Gain : 8 à 12 Mo en gardant 5 ou 6 fonds, ou en produisant le flou à l'affichage.
- Recommandation : fusionner. Vivant mais lourd.

### Usure et dégâts d'écran

- Familles : `crack_*` 5 239 201 (18 fichiers), `gx_*` 4 923 400 (45), `bloodd_*` 2 694 850 (43), `bloodc_*`
  2 431 220 (43), `dirt_*` 2 364 706 (28), `shatter_*` 1 022 055 (4), `fx_*` 707 031 (14), `glitch_*` 447 028 (12),
  `items/data/dmg` 1 263 333 (46). Total : 21 092 824 octets.
- Preuve d'usage : toutes construites par `format` (`fn_deviceOverlay.sqf`, `fn_screenFaults.sqf`,
  `fn_screenSurface.sqf`, `fn_screenFx.sqf`).
- Redondance : `bloodd_*` est la version séchée, fichier pour fichier, de `bloodc_*` (`fn_screenSurface.sqf`, ligne
  78). La plupart des familles existent aussi en portrait et en paysage.
- Gain : 2,7 Mo si le sang séché devient une teinte de la texture fraîche (incertain : rendu à vérifier), 5 à 8 Mo avec
  moins de variantes.
- Recommandation : réduire. Vivant mais lourd.

### Modèles

- `towers/data` : 32 modèles de relais, 50 494 580 octets, dont 18 110 992 octets pour les ruines seules
  (`relay_tower_50_ruin.p3d` 4 000 800, `relay_guyed_60_ruin.p3d` 3 089 114). Une ruine n'a pas besoin du détail du
  pylône debout : 9 à 12 Mo à gagner. Incertain tant que les modèles ne sont pas rouverts.
- `main/data/toc` : 47 modèles, 23 626 432 octets. Vivants.
- `main/data/phone_*.paa` : 41 textures, 18 970 068 octets, pour 9 téléphones. Vivantes ; à réduire.

## 8. Dictionnaires

| Fichier | Taille | Entrées | Clés en double | Identiques (clé = traduction) | Absentes de tout le code |
| --- | --- | --- | --- | --- | --- |
| `en.txt` | 2 262 874 | 24 850 | 0 | 193 (4 686 octets) | 2 243 (345 309 octets, 15,3 %) |
| `fr.txt` | 201 873 | 1 558 | 0 | 0 | 115 (51 714 octets, 25,6 %) |
| `zh_cn.txt` | 2 059 939 | 25 667 | 0 | 53 (1 392 octets) | non mesurable (voir plus bas) |

- Aucune clé en double au sens strict dans les trois fichiers. En revanche 1 118 clés de `en.txt` ne diffèrent d'une
  autre que par la casse, alors que `fn_tr` cherche déjà « texte exact, texte en capitales » : incertain, une partie
  peut être retirée.
- `en.txt` : 16 333 clés sont des sous-chaînes exactes du code de l'ATAK, 447 à la casse près, 5 827 (533 248 octets)
  ne se trouvent que dans le pack SSE, Overwatch, la DLL ou les missions (l'ATAK porte leur traduction), 2 243 nulle
  part. Parmi ces dernières : 352 modèles avec `%1`, 141 de 12 caractères au plus, 130 tout en capitales.
- Ces 2 243 entrées sont **incertaines**, pas mortes : texte assemblé à l'exécution, texte venu du site, ou phrase
  réécrite depuis. À trier avec `tools/i18n.py` avant tout retrait.
- `zh_cn.txt` a deux sortes de clés : 12 617 sont la traduction anglaise d'une entrée de `en.txt`, 12 900 sont la clé
  française elle-même, 150 (7 849 octets) ne sont ni l'une ni l'autre. Ces 150 sont les seules orphelines prouvées.
  11 124 traductions anglaises n'ont pas d'entrée chinoise.

Vingt exemples de clés de `en.txt` absentes de tout le code :

1. `Photographier tout de suite ce que vous voyez, sans viseur (le téléphone se masque un instant)`
2. `%1 m/s arrière`
3. `Verrouille l'ATAK du joueur (message à l'écran) ; recliquer : déverrouiller`
4. `Part de la population totale du camp (inclus dans les enrôlés).`
5. `Tension et SpO2 mesurées : %1`
6. `deux appuis pour éviter les fausses alertes`
7. `2. Découpage automatique`
8. `Grille de 41 x 41 cases autour de moi, œil à hauteur actuelle, cible à 1,7 m du sol`
9. `Ouvre le manuel du Zeus`
10. `Ctrl+Maj+U : prendre en main`
11. `Chef : %3`
12. `Appairer un appareil`
13. `SAT %1 : largeur de la vue %2 m`
14. `clic sur le 1er ou clic droit : fermer`
15. `OBJECTIF ARRIÈRE     ZOOM x1     FLASH AUTO`
16. `Ce groupe n'existe plus`
17. `Transmission de position impossible.`
18. `pilote %1`
19. `Aucun allié sur le réseau ATAK`
20. `choix du poste`

Risque : une clé retirée à tort laisse un texte en français pour un joueur anglophone, sans erreur. Gain : 345 Ko au
plus sur `en.txt`, et un chargement du dictionnaire plus court (`fn_langApply`).

## 9. Pack SSE et Overwatch connect (survol)

### Overwatch connect : images PNG dans le PBO

- Chemin : `mod/UptoDate/Sources/comspec-overwatch-addons/connect/img` ; PBO livré dans l'ATAK :
  `comspec_overwatch_connect.pbo`, 48 463 257 octets.
- Preuve : le dossier `connect` contient 37 `.png` (24 251 492 octets) à côté de 74 `.paa` (20 152 847 octets). Les
  noms `broken-screen.png`, `comspec_overlay_no_signal_ca.png` et `athena_tablet.png` sont présents dans le PBO livré.
  13 PNG ont un jumeau `.paa` de même nom (16 831 411 octets). 26 PNG ne sont cités par aucun
  `.sqf .cpp .hpp .html .js .css` des addons Overwatch (11 045 224 octets).
- Risque : faible pour les 26 non cités ; à vérifier pour les 11 autres (pages HTML du navigateur intégré).
- Gain : 11,0 Mo sans rien changer d'autre, jusqu'à 24,3 Mo, soit la moitié du PBO.
- Recommandation : exclure les PNG du PBO. Incertain tant que le PBO n'a pas été reconstruit et essayé.

À noter dans le même dossier : 44 autres fichiers dont le nom de base n'apparaît dans aucun code (11 262 398 octets),
surtout `connect/data/device/item*_{co,nohq,smdi,ca}.paa`. Ils sont probablement cités par des modèles ou des classes
héritées : incertain, non creusé.

### Pack SSE

- 24 PBO et un PBO optionnel de débogage, 19,0 Mo au total ; `comspec_sse_identity.pbo` en fait 11 078 395 octets.
- `workshop-pack.ps1` exclut déjà les sources et les dossiers d'addons décompressés.
- `comspec_sse_ext_example_documents.pbo` (457 552 octets) est une extension d'exemple : à vérifier qu'elle doit être
  livrée.
- Aucun addon `.off`. Pas d'analyse des fonctions du pack dans ce passage.

### Dépôt

Hors du mod livré, mais relevé au passage : `mod/Workshop` (295 Mo) et `mod/Overwatch 2026` comptent 1 883 fichiers
suivis par git ; `mod/UptoDate/@COMSPECOverwatch`, `@COMSPECOverwatch_StableBeta` et `@COMSPECOverwatch.zip`
(38 683 860 octets) sont des dossiers construits suivis eux aussi. Aucun fichier `ATAK_*` ou `BCE_cTab_*` n'y figure.

## Ordre proposé

1. Sans risque, tout de suite : notifications Arma mortes, `COMSPEC_ATAK_LegacyBootstrapSuppressed`, cinq petites
   textures, trois familles de polices (3,8 Mo).
2. Après un essai en jeu : PNG d'Overwatch connect (11 à 24 Mo), Brèche cachée comme OSINT, menus ACE fusionnés.
3. Décisions de produit : fonds d'écran, regroupements du lanceur, tablette du JPC, intro.
4. À la prochaine version majeure : fichiers du kit véhicule, événement d'avant la 4.12.0, gardes de `fn_tocWall`.
5. Chantiers longs : ruines des pylônes, variantes d'usure d'écran, tri du dictionnaire.
