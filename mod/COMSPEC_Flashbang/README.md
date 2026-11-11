# COMSPEC Flashbang

Mod indépendant de grenades assourdissantes pour Arma 3 : grenades de 1 à 9 détonations consécutives et grenade
d'entraînement DRILL. Seule dépendance : **CBA_A3**. Il ne dépend d'aucun autre mod COMSPEC (ni ATAK, ni SSE, ni
Overwatch, ni Athena). ACE3, ACRE2, TFAR et ZEN sont reconnus s'ils sont chargés, mais ne sont pas obligatoires.

*English version below.*

## Contenu

| Classe | Objet |
|---|---|
| `COMSPEC_FB_Flash_1` … `COMSPEC_FB_Flash_9` | Grenade assourdissante FB-N : N détonations (flash + bang) |
| `COMSPEC_FB_Drill` | Grenade d'entraînement bleue « DRILL » : petit pop et bouffée de fumée, aucun effet |
| `COMSPEC_FB_Box` | Caisse de grenades : 10 de chaque modèle (Eden, Zeus) |
| `COMSPEC_FB_Module` | Module Eden (et Zeus vanilla) : grenade à N flashs, effet sur une zone, ou grenade DRILL |

- **Lancer** : comme une grenade vanilla. G pour lancer, Ctrl+G pour changer de grenade. Chaque modèle a son
  « muzzle » de lancer, donc on passe de l'une à l'autre comme entre les fumigènes.
- **Reconnaître une grenade** : le grand chiffre N et les N pastilles sont imprimés sur deux faces du corps, et le
  chiffre est répété autour de la bande marron. La DRILL est bleue, marquée « DRILL », sans évents.
- **Arsenal** : toutes les grenades apparaissent dans l'Arsenal virtuel (vanilla et ACE), avec les objets à lancer.
- **Zeus** : la caisse se pose comme un objet normal. Avec ZEN, la catégorie « COMSPEC Flashbang » propose deux
  modules :
  - « Lancer un flashbang » : nombre de détonations, DRILL, délai ;
  - « Effet de flashbang sur une zone » : rayon, force, nombre, flash visible ou non.
- **Eden** : module « Flashbang (COMSPEC) » (Modules > COMSPEC Flashbang). Il a trois modes :
  - grenade posée à la position du module ;
  - effet sur une zone ;
  - DRILL.

  Il se déclenche au début de la mission après le délai, ou par un déclencheur synchronisé.
- **En script** (serveur) :
  - `["COMSPEC_FB_Ammo_7", getPosASL _obj] call comspec_fb_fnc_serverDetonate` : 7 détonations à la position ;
  - `[getPosASL _obj, 8, 0.9, 3, true] call comspec_fb_fnc_serverArea` : effet de zone de 8 m, force 0,9, 3 fois ;
  - `_unit setVariable ["comspec_fb_eyeProtection", 0.5]` : protection des yeux de 50 % (masque, visière…).

## Ce que ressent le joueur touché

Ces effets ne touchent que lui. La lumière et le bang, eux, sont vus et entendus par tout le monde.

- **Éblouissement** : voile blanc instantané. L'image est pleinement blanche environ 1,2 s, puis elle revient
  d'abord vite, ensuite lentement. Le contraste et les couleurs sont réduits pendant ce temps.
- **Image rémanente** : une tache reste à l'endroit de l'écran où était le flash. Elle est claire pendant 0,8 s, puis
  sombre, avec une teinte qui dérive. Elle bouge avec le regard (elle est sur la rétine) et s'efface en 25 s au plus.
  Si le flash était hors du champ de vision, il n'y a pas de tache.
- **Perte d'acuité** : flou léger qui se dissipe en 10 s environ. Il n'y a ni distorsion ni effet « psychédélique ».
- **JVN allumées** : tube saturé, voile beaucoup plus fort et grain marqué.
- **Optique ou jumelles pointées sur le flash** : halo dans l'optique et effet multiplié par 1,5.
- **Ouïe** : on entend le bang. Ensuite les sons du jeu, la voix et la radio baissent (jusqu'à 25 % du volume, jamais
  le silence total), puis reviennent lentement (35 s à pleine exposition).
- **Acouphène** : sifflement aigu continu. Il part fort et retombe vite les premières secondes, puis traîne
  (jusqu'à 45 s, réglable).
- **Motricité** : la visée devient instable pendant environ 8 s, avec une secousse brève de la caméra due à l'onde de
  pression. Le joueur n'est ni immobilisé ni mis à terre, et ne subit aucun dégât par défaut.
- **Plusieurs flashs** : chaque flash relance et prolonge les effets. Le total est plafonné : 15 s d'éblouissement
  cumulé par défaut, 60 s d'ouïe étouffée, environ 45 s d'acouphène.

**Ce qui fait varier l'effet** :
- la distance ;
- la ligne de vue (murs, portes fermées et coques de véhicule arrêtent le flash) ;
- l'orientation du regard (de face, de côté, de dos) ;
- le jour ou la nuit (adaptation de l'œil) ;
- l'intérieur ou l'extérieur ;
- les JVN et l'optique ;
- les lunettes teintées ACE ;
- la protection auditive (bouchons ACE, casque ACE) ;
- un véhicule fermé.

**IA** :
- précision, vitesse de visée et repérage fortement réduits pendant 3 à 12 s ;
- suppression (elle hésite, se couvre, tire moins) ;
- réflexe de détourner la tête si le flash est fort ;
- perte de la trace des ennemis au-delà d'un fort éblouissement.

Elle n'est jamais figée (pas de `disableAI`), et ses compétences sont rendues exactement ensuite. Les civils sont
touchés de la même façon. La DRILL ne provoque aucune réaction.

## Intérieur et extérieur

Le mod analyse l'endroit où la grenade éclate. Il n'y a jamais de calcul bloquant :

- **Rayons lancés** : 11 rayons (`lineIntersectsSurfaces`), répartis sur 4 images à raison de 3 par image :
  - 1 vers le haut pour détecter un toit ou un plafond ;
  - 8 à l'horizontale, en étoile, à 0,9 m du sol.
- **Coût** : environ 0,02 ms par rayon, soit moins de 0,1 ms par grenade et par machine.
- **Classement** : extérieur, couloir, petite pièce, pièce ou hangar, selon la distance aux murs et la forme du local.
- **Réglage** : la détection se coupe dans « Détecter intérieur / extérieur » ; on garde alors un seul rayon vers le
  haut.

Ce que ça change :

- **Local fermé** :
  - bang plus fort (+2 à +6 dB de réverbération selon le local) ;
  - éblouissement plus fort et de plus loin (multiplicateur intérieur, 1,4 en petite pièce) ;
  - dans la même pièce, même de dos ou sans vue directe, au moins 25 à 30 % de l'éblouissement (lumière renvoyée par
    les murs) ;
  - la lumière de la scène est limitée à la taille de la pièce + 4 m (les lumières d'Arma traversent les murs).
- **Mur plein entre le flash et l'auditeur** :
  - pas d'éblouissement ;
  - le bang passe sourd (-25 dB, son « étouffé » avec réverbération), donc la pièce voisine entend sans être
    éblouie.
- **Porte ou fenêtre ouverte** : la ligne de vue passe. Un joueur dehors face à la fenêtre est ébloui, de dos il l'est
  peu.
- **Extérieur** :
  - le bang est sec et l'effet chute plus vite avec la distance ;
  - au-delà de 150 m, on entend un bang lointain avec écho ;
  - le son arrive avec le retard dû à la distance (343 m/s) ;
  - de jour l'éblouissement est moindre (0,65), de nuit il est plus fort (multiplicateur nuit).

## Réglages

Options > Réglages de l'addon > COMSPEC Flashbang. Chaque réglage a une info-bulle avec un exemple.
Les réglages « client » sont propres à chaque poste ; tous les autres sont imposés par le serveur.

| Réglage | Défaut | Rôle | Exemple |
|---|---|---|---|
| Préréglage | Réaliste | Réaliste (valeurs des sources), Arcade (+25 % de force, +60 % de durée), Léger (-30 %, -40 %) | Arcade pour une soirée détente |
| Intensité globale | 1 | Multiplie tous les effets | 0.5 = moitié moins fort |
| Rayon plein effet (m) | 6 | Plein éblouissement jusqu'à cette distance | 4 pour un effet plus localisé |
| Rayon maximal (m) | 25 | Au-delà, plus d'éblouissement ; le bang assourdit jusqu'à ~60 m | 15 |
| Cécité cumulée max (s) | 15 | Plafond d'éblouissement quand les flashs s'enchaînent | 10 |
| Détecter intérieur / extérieur | oui | Analyse du local, sinon 1 rayon vers le haut | non sur un serveur très chargé |
| Multiplicateur intérieur | 1.4 | Force et portée en petite pièce | 1.6 |
| Blessure à bout portant | non | Petite blessure à moins de 1 m (jamais avec ACE Médical) | oui |
| Éblouissement | oui | Voile, image rémanente, halo | non pour ne garder que le son |
| Perte d'acuité (flou) | oui | Flou léger qui se dissipe | non |
| Multiplicateur nuit | 1.3 | Force la nuit / dans le noir (jour : 0,65) | 1.5 |
| Multiplicateur JVN | 2 | Force avec JVN allumées | 3 |
| Acouphène | oui | Sifflement qui décroît | non |
| Durée max de l'acouphène (s) | 45 | Durée audible maximale | 20 |
| Ouïe étouffée | oui | Sons, voix et radio baissent puis reviennent | non |
| Radio étouffée | oui | Sans ACE : radio vanilla, ACRE2, TFAR baissent aussi | non |
| Protection auditive (dB) | 25 | Réduction pour bouchons ou casque ACE | 30 |
| IA affectée | oui | IA éblouie : vise et repère mal, hésite | non |
| Effet sur son camp | oui | Non : le camp du lanceur n'est pas affecté | non pour un entraînement |
| Mèche (s) | 1.5 | Délai avant la première détonation | 2.3 (M84 lent) |
| Intervalle entre détonations (s) | 0.5 | Temps moyen entre deux bangs | 0.3 |
| Irrégularité (s) | 0.12 | Écart aléatoire de chaque intervalle | 0 = régulier |
| Pops de la grenade DRILL | 1 | Nombre de pops de la DRILL | 3 |
| Lueur de la DRILL | non | Petite lueur brève, aucun autre effet | oui |
| Qualité des effets visuels (client) | Moyenne | Nombre de particules | Basse sur un petit PC |
| Volume du bang (client) | 1 | Volume des détonations sur ce poste | 0.6 au casque |
| Acouphène : son (client) | Enregistrement | Son enregistré (sous licence) ou synthèse | Synthèse |
| Journal de débogage (client) | non | Écrit chaque étape dans le RPT, rien à l'écran | oui pour un test |

**Sans CBA** : le mod ne se charge pas, car CBA est requis. CBA fournit les réglages, les événements des grenades
et l'horloge commune des détonations. Il n'existe pas de réglage par `description.ext`. En mission, on peut forcer
une valeur dans `cba_settings.sqf`, par exemple `force comspec_fb_maxStack = 10;`.

## Sons : un seul endroit pour les changer

La table effet → fichier est dans `Sources/addons/main/CfgSons.hpp` (classe `COMSPEC_FB_Sons`). Pour changer le son
d'un effet, on modifie une seule ligne `fichiers[]`. Suffixe `_enreg` : son enregistré, sous licence, acquis par COMSPEC.
Suffixe `_synth` : synthétisé pour le mod.

| Effet | Fichier | Origine | Durée | Usage |
|---|---|---|---|---|
| Bang d'un flash | `bang_unique_a_enreg.ogg`, `_b_`, `_c_` | enregistrement | 2,4 / 2,4 / 1,2 s | rejoué N fois (1 par flash) |
| Couche grave | `bang_grave_enreg.ogg` | enregistrement filtré | 1,0 s | avec le 1er flash seulement |
| Bang derrière un mur | `bang_interieur_etouffe_enreg.ogg` | enregistrement + réverbération synthétique | 2,8 s | auditeur séparé par un mur |
| Bang lointain | `bang_lointain_enreg.ogg` | enregistrement + échos synthétiques | 3,8 s | dehors, au-delà de 150 m |
| Acouphène | `acouphene_decroissance_enreg.ogg` | enregistrement + enveloppe calculée | 60 s | joué 1 fois par joueur, prolongé par chaque flash |
| Acouphène (variante) | `acouphene_decroissance_synth.ogg` | synthèse | 60 s | réglage « Acouphène : son » |
| Pop DRILL | `pop_drill_synth.ogg` | synthèse | 0,5 s | 1 par détonation DRILL |
| Cuillère | `levier_cuillere_synth.ogg` | synthèse | 0,4 s | au lancer |
| (réserve) | `acouphene_boucle_enreg.ogg`, `bang_rafale_enreg.ogg` | enregistrement | 3 / 3,1 s | non utilisés |

Détails des bangs :
- Chaque bang est une lecture indépendante (`playSound3D` local). Les bangs se superposent sans se couper.
- Hauteur ±5 %, volume ±1,5 dB, tirage parmi les 3 bangs sans répéter deux fois de suite le même.
- Tous les fichiers sont en OGG Vorbis 44,1 kHz, sans écrêtage (crête ≤ -1 dBTP).
- Régénération : `tools/gen_fb_sounds.py`.

## Réalisme et sources

Les valeurs par défaut suivent les fiches techniques et la littérature publique. Les choix purement « jeu » sont
signalés.

| Effet | Valeur du mod | Référence réelle | Simplification de jeu |
|---|---|---|---|
| Bruit | 170 dB à 1,5 m, -20 log10(d) | M84 : 170-180 dB à 1,5 m ([Wikipedia, M84](https://en.wikipedia.org/wiki/M84_stun_grenade)) ; Primetake PT4523C : 165 ± 5 dB ([fiche](https://www.primetake.com/wp-content/uploads/2023/10/PT_DataSheet_Pyro-Distraction_PT4523C_v5.pdf)) ; Rheinmetall 6 Bang : 165 dB ([fiche distributeur](https://rampartcorp.com/wp-content/uploads/2024/09/Rheinmetall-Sound-Flash-6-Bang.pdf)) | propagation sphérique simple, réverbération forfaitaire par type de local |
| Seuil d'atteinte auditive | effet à partir de 140 dB crête, maximal à 170 dB | directive 2003/10/CE : valeur limite d'exposition 140 dB(C) crête | courbe d'effet en puissance 0,7 |
| Acouphène | aigu continu qui décroît, ≤ 45 s | acouphène et assourdissement temporaires, de quelques secondes à quelques minutes (description M84) | durée bornée pour le jeu |
| Ouïe étouffée | jusqu'à 25 % du volume, retour en ~35 s | décalage temporaire du seuil d'audition après un bruit impulsionnel | pas de vraie courbe audiométrique |
| Lumière | flash bref et très intense | 4 à 8 millions de candelas (M84 6-8 Mcd ; Primetake 8 Mcd ; Rheinmetall 4 Mcd) | lumière de jeu, pic ~2 images puis décroissance en 0,3 s |
| Éblouissement | plein blanc ~1,2 s, récupération ~6 s, image rémanente jusqu'à 25 s | récupération de 5 s à 2 min selon l'intensité et la tâche ([HFES 2023, Flash Blindness Recovery for Pilots in the Modern Cockpit](https://katalog.lib.cas.cz/KNAV/EdsRecord/edo,ejs64963113)) | durées fixes x exposition^0,6 |
| Adaptation | jour 0,65, nuit 1,3 | la récupération dépend de la taille de la pupille et de l'adaptation (rapports DTIC, ex. [AD0627332](https://apps.dtic.mil/sti/pdfs/AD0627332.pdf)) | `sunOrMoon` et toit au-dessus de la tête au lieu d'une vraie luminance |
| Regarder ailleurs | 100 % à < 25°, 35 % à 90°, 10 % de dos | l'éblouissement dépend de l'éclairement de la rétine | courbe linéaire par morceaux |
| Mèche | 1,5 s | M84 : 1 à 2,3 s ; Rheinmetall : 1,0 s ; NonLethal Technologies MP-FB-2 : 1,5 s | réglable 0,5-4 s |
| Multi-coups | 0,5 s ± 0,12 s, 1 à 9 coups | MP-FB-2 : 2e coup 0,5 s après le 1er ; Combined Systems 7290 : familles 2 à 9 coups | irrégularité aléatoire uniforme |
| Empilement | chaque coup relance et prolonge, plafond 15 s | « chaque détonation relance la désorientation » | +50 % de la durée restante par coup |
| Corps | Ø40 mm, 114 mm, 0,56 kg, évents en bas, bande marron | Rheinmetall 6 Bang Ø40 x 112 mm, 563 g ; Primetake Ø40 x 115 mm, 600 g | évents en texture et relief |
| DRILL | pop sans flash, corps bleu | Rheinmetall : version d'entraînement 150 dB, corps bleu | pas de flash par défaut |
| Motricité | visée instable ~8 s, secousse brève | désorientation, perte d'équilibre possible (Primetake), pas d'incapacité | pas de chute ni de ragdoll |
| Dégâts | aucun par défaut | non létale par conception ; brûlures possibles au contact | réglage « Blessure à bout portant » |

**Retiré parce que non réaliste** :
- flou radial ;
- distorsion « mouillée » ;
- vision en tunnel ;
- animation forcée du joueur ;
- recul d'arme modifié ;
- IA figée ;
- dégâts par défaut.

## Conception technique

- **Nombre exact de détonations** :
  1. La machine du lanceur compte la mèche sur SA grenade.
  2. Si la grenade a disparu entre-temps (véhicule, Zeus, script), rien ne se passe.
  3. Sinon, le serveur pose un engin synchronisé (ThingX, poussé par les explosions, supprimable par Zeus). Il tire
     une fois les instants des N détonations, puis envoie UN seul message aux machines concernées : serveur,
     headless, joueurs à moins de 2,5 km, morts et Zeus.
  4. Chaque machine programme exactement N exécutions sur l'horloge commune `CBA_missionTime`. Un message en retard
     rattrape les détonations passées sans en sauter.
  5. Si l'engin disparaît, les détonations restantes sont annulées partout, sans détonation fantôme.
- **Localité** :
  - lumière, particules et sons sont créés localement par chaque client ;
  - les effets s'appliquent aux unités locales (le joueur sur son poste, les IA sur le serveur ou le headless) ;
  - aucun `remoteExec` par détonation.
- **Coût au repos** : aucun. Les boucles n'existent que pendant une détonation ou un effet actif. Tous les
  post-traitements sont détruits à la fin (`ppEffectDestroy`), et tout est rétabli à la mort, à la réapparition, au
  changement d'unité, en Zeus et en spectateur.
- **Compatibilité** :
  - **ACE3** :
    - bouchons et casques ACE réduisent le bang ;
    - lunettes teintées ACE ;
    - ouïe étouffée via `ace_common_fnc_setHearingCapability`, ce qui gère aussi radio, ACRE2 et TFAR ;
    - fatigue ACE respectée ;
    - Arsenal ACE ;
    - aucun effet médical ACE.
  - **ACRE2 et TFAR** : volume global baissé puis rendu (sans ACE).
  - **ZEN** : modules Zeus.
- **Liste blanche `remoteExec`** : si la mission a sa propre liste blanche `CfgRemoteExec`, il faut y ajouter :
  - `comspec_fb_fnc_serverDetonate` et `comspec_fb_fnc_serverArea` (serveur) ;
  - `comspec_fb_fnc_timeline`, `comspec_fb_fnc_cancel` et `comspec_fb_fnc_aiRestore`.

## Limites connues

- **Rien n'a été testé en jeu à la livraison** : rendus visuels, niveaux sonores, comportement de l'IA et
  synchronisation multijoueur sont relus mais pas encore vus en partie.
- **Lumière à travers les murs** : les lumières d'Arma traversent les murs. Le flash est donc limité à la taille de la
  pièce, mais une lueur peut se voir de l'autre côté d'un mur fin.
- **Engin au sol** : un ThingX de 0,56 kg peut trembler légèrement sur certaines surfaces.
- **Grenade ramassée** : elle ne peut pas être ramassée entre deux détonations. L'engin n'est pas un objet
  d'inventaire.
- **Détection intérieur / extérieur** : par rayons, donc approximative. Un auvent ou un arbre au-dessus de la tête
  compte comme un toit pour l'adaptation de l'œil.
- **Acouphène** : un flash plus fort relance l'acouphène plus tôt dans le fichier. La reprise est masquée par le bang.

## Construire

Depuis PowerShell :

    cmd /c "<dépôt>\mod\COMSPEC_Flashbang\build_mod.bat"

Le script :
1. lance `hemtt build`, qui rapifie la config et binarise les p3d ;
2. copie le PBO dans `@COMSPEC_Flashbang\addons` ;
3. copie `mod.cpp`, `logo.paa` et ce README ;
4. signe le PBO avec la clé du mod. La clé privée est créée une seule fois, hors du dépôt
   (`%APPDATA%\COMSPEC\signing\flashbang`, ou `COMSPEC_FB_SIGNING_DIR`), puis réutilisée à chaque build : il faut la
   garder et ne jamais la committer. La clé publique va dans `@COMSPEC_Flashbang\keys`.

Le dossier `@COMSPEC_Flashbang` se charge avec `-mod=` (avec CBA_A3).

Publication Workshop (objet 3837805212) : voir [workshop/PACKAGING.md](workshop/PACKAGING.md) (pack propre,
description, galerie, note de version, annonce).

Régénérer les ressources (dossier `tools\`) :
- textures : `py -3 gen_fb_textures.py` ;
- modèles : `blender -b --factory-startup -P model_flashbang.py` (extension Arma 3 Object Builder) ;
- icônes et logo : `py -3 make_fb_icons.py` ;
- sons : `py -3 gen_fb_sounds.py` ;
- panneau de la mission : `py -3 gen_range_sign.py`.

## Liste de test

Mission : `missions/COMSPEC_FB_Range.Stratis` (Eden). Elle se lance avec CBA et le mod. Les consignes sont sur la
carte (onglet Flashbang) et sur le panneau.

1. Zone A, de jour : lancer une FB-1 puis une FB-9 au cercle. Compter les bangs (exactement 1, puis 9, irréguliers).
   Vérifier :
   - lumière, étincelles et fumée à chaque coup ;
   - les IA proches et de face sont très touchées, celles de dos peu ;
   - l'IA à 35 m n'est pas éblouie.
2. Joueur à 3 m face à la grenade : voile blanc, tache rémanente à l'endroit du flash, flou qui se dissipe, sons
   étouffés, acouphène qui décroît. Même test de dos : effet bien plus faible.
3. Même test de nuit (action « Nuit » de la caisse), puis de nuit avec JVN (grain, voile plus fort), puis lunette
   HAMR pointée sur la grenade (halo).
4. Zone B : grenade lancée dans la pièce fermée, joueur dedans (effet fort même de dos), puis joueur dehors derrière
   le mur (bang sourd, aucun éblouissement).
5. Zone C : joueur dehors face à la fenêtre (ébloui), puis dos à la fenêtre.
6. Zones D et E : comparer couloir et hangar. Avec le réglage « Journal de débogage », le RPT affiche la classe du
   local (lignes `COMSPEC_FB|env`).
7. FB-DRILL : pop et fumée seulement, aucun effet. Avec « Pops de la grenade DRILL » = 3, trois pops. Avec « Lueur
   de la DRILL », lueur brève.
8. Lancer une FB-9 puis la supprimer en Zeus pendant les détonations : les bangs s'arrêtent, sans erreur.
9. Mourir ou passer en Zeus pendant un effet : tout disparaît tout de suite (image, son, volume, visée).
10. Multijoueur (serveur dédié + 2 clients) : même nombre de bangs chez tous, sons synchronisés, effets seulement sur
    les joueurs concernés.
11. Avec ACE : bouchons mis (effet auditif réduit), puis retirés. Avec ACRE2 ou TFAR : radio étouffée puis rendue.
12. Avec ZEN : modules « Lancer un flashbang » et « Effet de flashbang sur une zone ».

## Crédits

- **Conception, code, modèle 3D, textures, icônes** : COMSPEC.
- **Sons** :
  - **sons sous licence, acquis par COMSPEC** : bangs, couche grave et acouphène enregistré ;
  - **synthétisés par COMSPEC** : pop DRILL, cuillère, acouphène de synthèse, réverbérations et échos.
- **Références techniques** : fiches publiques Primetake, Rheinmetall, NonLethal Technologies et Combined Systems,
  Wikipedia (M84), directive 2003/10/CE, études sur l'éblouissement (HFES, DTIC).

---

# COMSPEC Flashbang (English)

Standalone stun grenade mod for Arma 3. It adds 1- to 9-bang grenades and an inert DRILL training grenade. The only
dependency is **CBA_A3**. It does not depend on any other COMSPEC mod. ACE3, ACRE2, TFAR and ZEN are detected when
loaded but not required.

**Content**
- `COMSPEC_FB_Flash_1` … `_9` (N bangs) and `COMSPEC_FB_Drill` (blue, pop and smoke only, no effect);
- crate `COMSPEC_FB_Box` (10 of each);
- Eden module `COMSPEC_FB_Module` with three modes: grenade, area effect, drill;
- ZEN modules « Throw a flashbang » and « Flashbang area effect ».

Grenades are thrown like vanilla ones (G to throw, Ctrl+G to switch) and appear in the Arsenal.

**Effects on the player who is hit**
- white-out about 1.2 s, then recovery;
- retinal afterimage at the flash's screen position (bright, then dark), up to 25 s;
- light acuity loss;
- NVG wash-out with grain;
- scope halo;
- muffled game sounds, voice and radio, never full silence;
- one decaying high-pitched tinnitus;
- unsteady aim for about 8 s and a brief camera shake;
- no damage, stun or ragdoll by default.

Several bangs refresh and extend the effects up to a cap (15 s of dazzle by default).

**What changes the effect**: distance, line of sight (walls, closed doors and vehicle hulls block the flash), gaze
angle, day or night, NVG, optics, tinted ACE glasses, ACE ear protection, indoor or outdoor.

**AI**: lower accuracy and spotting for 3 to 12 s, suppression and a head-turn reflex. AI is never frozen and its
skills are restored exactly.

**Indoor and outdoor**: 11 rays spread over 4 frames classify the spot as outdoor, corridor, small room, room or
hangar (under 0.1 ms per grenade).
- Closed rooms: louder bang, stronger and wider dazzle, and reflected light even with your back turned.
- A solid wall stops the flash; the bang still goes through, muffled.
- Outdoors: the effect falls off faster, there is a distant echo beyond 150 m, and sound is delayed at 343 m/s.

**Exact bang count**:
1. The thrower's machine runs the fuse on its own grenade.
2. The server places a synchronised device, rolls the N detonation times once, and sends a single message.
3. Every machine schedules exactly N detonations on `CBA_missionTime`.
4. If the device is deleted, the remaining bangs are cancelled everywhere.

There is no per-frame cost when idle, and every post-process effect is destroyed at the end.

**Settings**: see the French table above. Every setting has a tooltip with an example. The presets are Realistic
(default), Arcade and Light.

**Sounds**: the single effect → file table is in `CfgSons.hpp`.
- `_enreg`: licensed recordings acquired by COMSPEC.
- `_synth`: synthesised.
- Each bang is replayed once per detonation, with pitch and volume variation and overlapping playback. The tinnitus
  plays once per player.

**Realism**: defaults are based on public data. The table « Réalisme et sources » above has the links.
- M84: 170-180 dB and 6-8 Mcd at 1.5 m.
- Primetake and Rheinmetall multi-bang: Ø40 mm, about 0.56 kg, 165 dB, 4-8 Mcd.
- NonLethal Technologies: 0.5 s between bangs.
- EU directive 2003/10/EC: 140 dB peak limit.
- Flash blindness recovery takes 5 s to 2 min depending on intensity.

**Build**: `cmd /c mod\COMSPEC_Flashbang\build_mod.bat` from PowerShell (HEMTT, signing with the mod's own key).
Load `@COMSPEC_Flashbang` with CBA_A3.

**Test mission**: `missions/COMSPEC_FB_Range.Stratis`. See the « Liste de test » checklist above.

**Credits**: COMSPEC (code, model, textures, icons, synthesised sounds); recorded sounds licensed by COMSPEC.
