# COMSPEC Flashbang

**BetterFlash (BF)** : mod indépendant de grenades assourdissantes pour Arma 3 : grenades de 1 à 9 détonations
consécutives (« 1-BANG BF » à « 9-BANG BF »), GANG BANG BF de 50 flashs et grenade d'entraînement DRILL BF, en mèche
standard (réglable), en mèche à délai 3 s ou 8 s, et en rafale hyper rapide (« R »). Seule dépendance : **CBA_A3**. Il ne dépend d'aucun autre mod COMSPEC (ni ATAK, ni SSE, ni
Overwatch, ni Athena). ACE3, ACRE2, TFAR et ZEN sont reconnus s'ils sont chargés, mais ne sont pas obligatoires.

**Où charger le mod** : le mod doit être chargé sur les clients ; le serveur n'est pas obligatoire pour lancer des
grenades (recommandé pour une mission avec Zeus ou le module Eden). Avec le mod sur le serveur, l'engin au sol est
synchronisé, supprimable par Zeus, et les IA du serveur sont touchées. Sans le mod sur le serveur, chaque client
lanceur fait autorité : il tire les instants et prévient les autres clients (événement CBA, CBA suffit sur le serveur),
chacun pose son engin local ; les IA locales au serveur ne sont pas touchées et Zeus ne peut pas supprimer l'engin.

*English version below.*

## Contenu

| Classe | Nom affiché (FR / EN) | Objet |
|---|---|---|
| `COMSPEC_FB_Flash_1` … `COMSPEC_FB_Flash_9` | « 1-BANG BF » … « 9-BANG BF » | Grenade assourdissante : N détonations (flash + bang), mèche du réglage « Mèche » |
| `COMSPEC_FB_Flash_50` | « GANG BANG BF » | 50 flashs en 15 à 20 s, rafales et pauses, corps noir à bande rouge |
| `COMSPEC_FB_Flash_<N>_D3` / `_D8` | « N-BANG BF · 3 s » / « · 8 s » (« GANG BANG · 3 s ») | Même grenade, mèche fixe 3 s / 8 s |
| `COMSPEC_FB_Flash_<N>_R` (N = 2 à 9, 50) | « N-BANG BF R », « GANG BANG BF R » | Même grenade en rafale hyper rapide (~0,065 s entre deux flashs), icône éclair « R » |
| `COMSPEC_FB_Drill`, `_D3`, `_D8` | « DRILL BF », « DRILL BF · 3 s », « · 8 s » | Grenade d'entraînement bleue : pop (son au choix) et fumée, aucun effet |
| `COMSPEC_FB_Box` | « Caisse BF » / « BF crate » | 10 de chaque grenade standard, 2 GANG BANG, 2 de chaque R (Eden, Zeus) |
| `COMSPEC_FB_Box_Delay` | « Caisse délais BF » / « BF delay crate » | 5 de chaque grenade à délai, 2 de chaque GANG BANG à délai |
| `COMSPEC_FB_Module` | « BetterFlash » | Module Eden (et Zeus) : grenade de 1 à 50 flashs, effet sur une zone, ou grenade DRILL |

Noms courts (16 caractères au plus) : la liste d'inventaire et l'Arsenal coupent vers 20 caractères. Le nom commence
par ce qui distingue (« 5-BANG BF · 3 s ») ; le texte explicatif, marqué « BF -BetterFlash- », est dans l'info-bulle.
Catégories : « BetterFlash (COMSPEC) » dans Eden, « BetterFlash » dans Zeus (ZEN), « COMSPEC Flashbang -BetterFlash- »
dans les réglages d'addon.

**GANG BANG BF : 50 flashs, très long et très éprouvant ; à utiliser avec parcimonie.** Réglage « Autoriser les
grenades à plus de 9 flashs » (coché par défaut) : décoché, tout est limité à 9 flashs. Les 50 détonations partent
d'un point dispersé autour de la grenade (0 à 1,5 m), la grenade saute entre deux bangs ; lumières (au plus 8) et
sons (au plus 12 lectures) sont budgétés ; les effets s'empilent sans dépasser les plafonds réalistes, et la
récupération commence après la dernière détonation.

### Grenades à délai (3 s et 8 s)

Schéma des classes : suffixe `_D3` / `_D8` sur le magasin (`COMSPEC_FB_Flash_5_D8`), la munition
(`COMSPEC_FB_Ammo_5_D8`), le muzzle de lancer (`COMSPEC_FB_Muzzle_5_D8`) et l'engin au sol (`COMSPEC_FB_Device_5_D8`).
Les 40 classes sont générées par des macros (`FB_AMMO_DELAYS`, `FB_MAG_DELAYS`, `FB_MUZZLE_DELAYS`, `FB_DEVICE_DELAYS`,
`FB_D2`) : une ligne par variante de base, rien n'est recopié à la main.

| Mèche | Classes | Usage |
|---|---|---|
| standard (réglage « Mèche », 1,5 s) | `COMSPEC_FB_Flash_1` … `_9`, `COMSPEC_FB_Drill` | entrée classique : lancer puis entrer |
| 3 s | `COMSPEC_FB_Flash_1_D3` … `_9_D3`, `COMSPEC_FB_Drill_D3` | lancer, laisser rebondir ou rouler jusqu'à la pièce, détonation 3 s après le lancer |
| 8 s | `COMSPEC_FB_Flash_1_D8` … `_9_D8`, `COMSPEC_FB_Drill_D8` | 8 s : pour lancer à l'avance depuis une position sûre, ou synchroniser une entrée sur un décompte |

- La mèche démarre quand la grenade quitte la main (lancer vanilla) ou au dégoupillage (lancer avancé ACE).
- La mèche des variantes à délai est fixe : le réglage « Mèche » ne s'y applique pas. Le réglage « Irrégularité »
  s'y ajoute (± 0,12 s par défaut).
- Pas de détonation à l'impact : la grenade rebondit, roule et explose à l'heure prévue.
- Reconnaissance : 2e bande sous la bande marron (jaune = 3 s, rouge = 8 s) avec « 3s » / « 8s » tout autour,
  cartouche « 3s » / « 8s » à côté du chiffre sur les deux flancs et en grand au dos. La DRILL garde son bleu et sa
  bande blanche et reçoit la même bande et le même cartouche. Icônes : pastille horloge « 3s » / « 8s » en haut à
  droite, en plus du badge du nombre.

- **Lancer** : comme une grenade vanilla. G pour lancer, Ctrl+G pour changer de grenade. Chaque modèle (délais
  compris) a son « muzzle » de lancer, donc on passe de l'une à l'autre comme entre les fumigènes. Le lancer avancé
  d'ACE (Maj+G) marche aussi.
- **Reconnaître une grenade** : le grand chiffre N et les N pastilles sont imprimés sur deux faces du corps, et le
  chiffre est répété autour de la bande marron. La DRILL est bleue, marquée « DRILL », sans évents.
- **Arsenal** : toutes les grenades apparaissent dans l'Arsenal virtuel (vanilla et ACE), avec les objets à lancer.
- **Zeus** : les caisses se posent comme des objets normaux. Avec ZEN, la catégorie « BetterFlash » propose deux
  modules, exécutés sur le poste du Zeus (le serveur n'a pas besoin du mod) :
  - « Lancer BF » : nombre de détonations (1 à 50), DRILL, délai de mèche (par défaut = réglage « Mèche », 3 s, 8 s ou
    personnalisé de 0,5 à 20 s), cadence, son de la DRILL ;
  - « Effet de zone BF » : rayon, force, nombre, flash visible ou non, délai, cadence.
  Le module « BetterFlash » posé en Zeus (sans fenêtre) fait une grenade de 3 flashs à l'endroit posé. Chaque exécution
  écrit `[COMSPEC FB] module | type=… pos=… N=… délai=…` (et `ÉCHEC : …` si rien n'est déclenché).
- **Eden** : module « BetterFlash » (Modules > BetterFlash (COMSPEC)). Il a trois modes :
  - grenade posée à la position du module ;
  - effet sur une zone ;
  - DRILL.

  Il se déclenche au début de la mission ou par un déclencheur synchronisé, après le délai puis le « Délai de mèche »
  (par défaut : aucune mèche, comme avant ; 3 s ; 8 s ; personnalisé 0,5-20 s, champ « Mèche personnalisée »).
  En mode grenade et DRILL, 3 s / 8 s posent l'engin marqué 3s / 8s. Champ « Son de la DRILL » : selon le réglage de
  l'addon, ou un son imposé.
- **En script** (serveur) :
  - `["COMSPEC_FB_Ammo_7", getPosASL _obj] call comspec_fb_fnc_serverDetonate` : 7 détonations à la position ;
  - `["COMSPEC_FB_Ammo_Drill", getPosASL _obj, nil, nil, nil, nil, 3, 0, -1, "Sifflet"] call comspec_fb_fnc_serverDetonate` :
    DRILL à 3 pops, son « Sifflet d'exercice » ;
  - `[getPosASL _obj, 8, 0.9, 3, true] call comspec_fb_fnc_serverArea` : effet de zone de 8 m, force 0,9, 3 fois ;
  - `_unit setVariable ["comspec_fb_eyeProtection", 0.5]` : protection des yeux de 50 % (masque, visière…).

## Ce que ressent le joueur touché

Ces effets ne touchent que lui. La lumière et le bang, eux, sont vus et entendus par tout le monde.

- **Éblouissement** : voile blanc instantané. À 3 m, l'écran est entièrement blanc environ 1,5 s, le blanc s'efface
  en 1,5 s environ, la vision reste très dégradée jusqu'à 6-8 s (voile, contraste et couleurs réduits), puis revient
  vers 12 s. Chaque flash suivant redonne un blanc plein écran bref.
- **Image rémanente** : une grande tache reste à l'endroit de l'écran où était le flash. Elle est claire pendant 0,8 s, puis
  sombre, avec une teinte qui dérive. Elle bouge avec le regard (elle est sur la rétine) et s'efface en 25 s au plus.
  Si le flash était hors du champ de vision, il n'y a pas de tache.
- **Perte d'acuité** : flou fort au début (DynamicBlur ~45 à 3 m de face, ~35 à 3 s, ~17 à 6 s), qui se dissipe en
  12 s environ. Il n'y a ni distorsion ni effet « psychédélique ».
- **JVN allumées** : tube saturé, voile beaucoup plus fort et grain marqué.
- **Optique ou jumelles pointées sur le flash** : halo dans l'optique et effet multiplié par 1,5.
- **Ouïe** : on entend le bang. Ensuite les sons du jeu, la voix et la radio baissent (jusqu'à 25 % du volume, jamais
  le silence total), puis reviennent lentement (35 s à pleine exposition).
- **Acouphène** : sifflement aigu continu. Il part fort et retombe vite les premières secondes, puis traîne
  (jusqu'à 45 s, réglable).
- **Désorientation** : secousse brève de la caméra (onde de pression), puis oscillation lente qui décroît (vertige),
  visée très instable (dispersion jusqu'à x5) et pas plus lents (jusqu'à 80 % de la vitesse) pendant environ 10 s.
  Le joueur n'est ni immobilisé ni mis à terre, et ne subit aucun dégât par défaut.
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
  - le bang est très fort sous 25 m, net jusqu'à 100 m, puis lointain avec écho ; on ne l'entend plus au-delà de la
    « Portée du son » (250 m ; moitié à travers un mur ou depuis un local fermé) ;
  - le son arrive avec le retard dû à la distance (343 m/s) ;
  - la nuit l'éblouissement est plus fort (multiplicateur nuit).
- La portée du son ne change pas les effets : éblouissement et désorientation restent limités au rayon maximal
  (25 m).

## Rafales, hasard, volume, flammes (1.0.0)

- **Cadence des flashs** (réglage, ou par grenade : classes `_R` ; ou par module) : Normal (~0,5 s, irrégulier),
  Rapide (~0,15 s), Hyper rapide (0,045 à 0,09 s : les 9 bangs partent en moins d'une demi-seconde, effet
  stroboscopique), Aléatoire (rafales et pauses). Intervalle réglable de 0,03 à 1,5 s. En rafale, une détonation à
  moins de 0,08 s de la précédente relance la lumière en cours (scintillement) au lieu d'en créer une, sans flammes ;
  la planification suit l'horloge commune (aucune détonation perdue à 20 images/s, N exact, dans l'ordre).
- **Variation aléatoire** (60 % par défaut, 0 % = tout régulier) : intervalles irréguliers (quelques flashs quasi
  simultanés), 1er bang le plus fort puis bangs qui « roulent » (0,65 à 1), lumière 60 à 140 %, teinte blanc / blanc
  bleuté / blanc chaud, particules et éclats, son (fichier différent, hauteur ±8 %, volume ±2 dB, micro-retard) ; par
  victime : éblouissement ±10 %, durée du blanc ±25 %, flou et courbe de retour variables, 1 à 3 taches rémanentes qui
  dérivent, récupération en vagues avec clignements, « plocs » de l'ouïe, visée et pas qui fluctuent, petites
  hésitations ; sensibilité propre à chaque joueur (±15 %) ; IA : durée ±30 %, réaction tirée au hasard (se couvre,
  courte rafale réflexe, hésite). La moyenne reste celle de la table de calibration (test : moyenne ±5 %, variance
  bornée).
- **Volume** : bangs à environ -11 LUFS (avant : -14 en 1.0.0 précédente, -19 à l'origine), limiteur doux, crête sous
  -0,5 dBFS ; « Volume des flashbangs » 500 % par défaut (50 à 1000 %), « Volume de l'acouphène » 250 % (50 à 1000 %) ;
  au-delà de la limite d'une lecture 3D, 2 à 4 lectures superposées ; sous 25 m une couche proche 2D non atténuée
  (plein volume jusqu'à 8 m) ; x1,6 jusqu'à 30 m ; « ducking » des autres sons 150 ms au pic ; le bang suivant compense
  l'ouïe déjà étouffée. **Très fort : baissez le volume de votre casque avant de tester.**
- **Acouphène continu** : un seul par joueur et par train de flashs (attaque une fois, boucles enchaînées, fin une
  fois) ; les flashs suivants prolongent et remontent le niveau, sans jamais relancer l'attaque.
- **Flammes (visuel)** (client) : Aucune / Légère (défaut) / Marquée : courtes langues de gaz incandescents par les
  évents, lueur chaude très atténuée ; aucun feu, aucun dégât.
- **Instants** : toutes les heures échangées sont sur `serverTime` en multijoueur ; un message en retard saute les
  détonations déjà passées (jamais rejouées en rafale) ; le journal note pour chaque détonation l'instant prévu et
  l'instant exécuté, et pour le joueur l'instant où l'effet s'affiche (avertissement au-delà de 0,3 s).

## Réglages

Options > Réglages de l'addon > COMSPEC Flashbang. Chaque réglage a une info-bulle avec un exemple.
Les réglages « client » sont propres à chaque poste ; tous les autres sont imposés par le serveur.

| Réglage | Défaut | Rôle | Exemple |
|---|---|---|---|
| Préréglage | Réaliste | Réaliste (valeurs des sources), Arcade (+25 % de force, +60 % de durée), Léger (-30 %, -40 %) | Arcade pour une soirée détente |
| Intensité globale | 1 | Multiplie tous les effets | 0.5 = moitié moins fort |
| Rayon plein effet (m) | 3 | Plein éblouissement jusqu'à cette distance (table de calibration) | 5 pour un effet plus fort |
| Portée du son (m) | 250 | Distance d'audibilité du bang en terrain dégagé (moitié à travers un mur) ; n'agit pas sur les effets | 250 = un quart de kilomètre ; moins pour un petit terrain |
| Rayon maximal (m) | 25 | Au-delà, plus d'éblouissement ; le bang assourdit jusqu'à ~60 m | 15 |
| Cécité cumulée max (s) | 15 | Plafond d'éblouissement quand les flashs s'enchaînent | 10 |
| Détecter intérieur / extérieur | oui | Analyse du local, sinon 1 rayon vers le haut | non sur un serveur très chargé |
| Multiplicateur intérieur | 1.4 | Force et portée en petite pièce | 1.6 |
| Blessure à bout portant | non | Petite blessure à moins de 1 m (jamais avec ACE Médical) | oui |
| Éblouissement | oui | Voile, image rémanente, halo | non pour ne garder que le son |
| Perte d'acuité (flou) | oui | Flou fort qui se dissipe | non |
| Multiplicateur nuit | 1.3 | Force la nuit / dans le noir (jour : 1) | 1.5 |
| Multiplicateur JVN | 2 | Force avec JVN allumées | 3 |
| Acouphène | oui | Sifflement qui décroît | non |
| Durée max de l'acouphène (s) | 45 | Durée audible maximale | 20 |
| Ouïe étouffée | oui | Sons, voix et radio baissent puis reviennent | non |
| Radio étouffée | oui | Sans ACE : radio vanilla, ACRE2, TFAR baissent aussi | non |
| Protection auditive (dB) | 25 | Réduction pour bouchons ou casque ACE | 30 |
| IA affectée | oui | IA éblouie : vise et repère mal, hésite | non |
| Effet sur son camp | oui | Non : le camp du lanceur n'est pas affecté | non pour un entraînement |
| Mèche (s) | 1.5 | Délai avant la première détonation des grenades standard (pas des grenades 3 s / 8 s) | 2.3 (M84 lent) |
| Intervalle entre détonations (s) | 0.5 | Temps moyen entre deux bangs en cadence Normale (0,03 à 1,5) | 0.3 |
| Cadence des flashs | Normal | Normal, Rapide, Hyper rapide, Aléatoire | Hyper rapide : les 9 bangs en moins d'une demi-seconde |
| Variation aléatoire | 60 % | Hasard par détonation et par victime (moyenne inchangée) | 20 % pour un entraînement reproductible |
| Autoriser les grenades à plus de 9 flashs | oui | GANG BANG BF et modules jusqu'à 50 flashs | non pour limiter à 9 |
| Flammes (visuel) (client) | Légère | Langues de gaz incandescents aux évents | Aucune sur un petit PC |
| Irrégularité (s) | 0.12 | Écart aléatoire de chaque intervalle et de la mèche fixe 3 s / 8 s | 0 = régulier |
| Pops de la grenade DRILL | 1 | Nombre de pops de la DRILL | 3 |
| Lueur de la DRILL | non | Petite lueur brève, aucun autre effet | oui |
| Son de la grenade d'entraînement | Pop | Son de chaque pop de la DRILL : Pop, Pop sourd, Bang léger, Bang réel, Rafale courte, Sifflet d'exercice, Cloche d'exercice, Aucun son | « Pop » pour un exercice discret, « Bang réel » pour s'entraîner au bruit d'une vraie détonation |
| Volume du son d'entraînement | 100 % | Volume du son de la DRILL pour tous (20 à 150 %) | 60 % la nuit près des habitations |
| Qualité des effets visuels (client) | Moyenne | Nombre de particules | Basse sur un petit PC |
| Volume des flashbangs (client) | 500 % | Volume des bangs et sons de la DRILL sur ce poste (50-1000 %) ; très fort : baissez le casque avant de tester | 100 % = niveau d'origine |
| Volume de l'acouphène (client) | 250 % | Volume du sifflement (50-1000 %) | 100 % si trop fort au casque |
| Acouphène : son (client) | Enregistrement | Son enregistré (sous licence, acquis par COMSPEC) ou synthèse | Synthèse |
| Journal de diagnostic (RPT) (client) | oui | Une dizaine de lignes `[COMSPEC FB]` par grenade dans le RPT de ce poste, rien à l'écran ; désactivable | laissez-le coché, lancez une grenade, puis envoyez votre RPT |
| Journal détaillé (client) | non | Ajoute son, lumière, chaque unité, intérieur / extérieur, IA | coché le temps d'un test |

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
| Bang lointain | `bang_lointain_enreg.ogg` | enregistrement + échos synthétiques | 3,8 s | dehors, au-delà de 100 m |
| Acouphène | `acouphene_attaque/segment/fin_enreg.ogg` | enregistrement + enveloppes calculées | 0,5 / 3 / 6 s | un acouphène continu par joueur : attaque une fois, boucles, fin une fois |
| Acouphène (variante) | `acouphene_attaque/segment/fin_synth.ogg` | synthèse | 0,5 / 3 / 6 s | réglage « Acouphène : son » |
| Cuillère | `levier_cuillere_synth.ogg` | synthèse | 0,4 s | au lancer |
| (réserve) | `acouphene_boucle_enreg.ogg` | enregistrement | 3 s | non utilisé |

Sons de la DRILL (réglage « Son de la grenade d'entraînement », ou champ « Son de la DRILL » des modules, qui l'emporte
pour cette grenade). Un son par pop (1 à 9), lectures indépendantes et superposées, hauteur ±5 % et volume ±1,5 dB
comme les bangs. « Aucun son » : fumée seule.

| Choix | Entrée de `CfgSons.hpp` | Fichier | Origine | Durée |
|---|---|---|---|---|
| Pop (défaut) | `Drill_Pop` | `pop_drill_synth.ogg` | synthèse | 0,5 s |
| Pop sourd | `Drill_PopSourd` | `pop_sourd_synth.ogg` | synthèse (étouffé, petite pièce) | 0,7 s |
| Bang léger | `Drill_BangLeger` | `bang_leger_enreg.ogg` | enregistrement raccourci, grave allégé | 0,8 s |
| Bang réel | `Drill_BangReel` | `bang_unique_a/b/c_enreg.ogg` | enregistrement (vrai bang, aucun effet) | 2,4 / 2,4 / 1,2 s |
| Rafale courte | `Drill_Rafale` | `bang_rafale_enreg.ogg` | enregistrement | 3,1 s |
| Sifflet d'exercice | `Drill_Sifflet` | `sifflet_exercice_synth.ogg` | synthèse (sifflet à bille) | 0,8 s |
| Cloche d'exercice | `Drill_Cloche` | `cloche_exercice_synth.ogg` | synthèse | 1,6 s |
| Aucun son | (aucune) | - | - | - |

Les fichiers d'écoute sont copiés dans `H:\StreamLabs\ogg\` (tableau dans `LISEZ-MOI.txt`).

Détails des bangs :
- Niveaux des fichiers : bangs à crête -1 dBFS après un limiteur doux (environ -14 à -16 LUFS, +4,5 LU par rapport à
  la première version), acouphène à crête -6 dBFS (+17 LU). Volume de lecture : volume de la table x « Volume des
  flashbangs » (200 %) ; `playSound3D` plafonne à 5 par lecture, au-delà le bang est joué en 2 ou 3 lectures
  superposées. Limiteur : -1 dB par bang au-delà du 4e (9 bangs ne saturent pas, le premier garde son impact).
- Chaque bang est une lecture indépendante (`playSound3D` local). Les bangs se superposent sans se couper.
- Hauteur ±5 %, volume ±1,5 dB, tirage parmi les 3 bangs sans répéter deux fois de suite le même.
- Tous les fichiers sont en OGG Vorbis 44,1 kHz, sans écrêtage (crête ≤ -1 dBTP).
- Régénération : `tools/gen_fb_sounds.py` (`--drill` : seulement les sons au choix de la DRILL).

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
| Éblouissement | plein blanc ~1,5 s à 3 m, vision très dégradée 6-8 s, récupération ~12 s, image rémanente jusqu'à 25 s | récupération de 5 s à 2 min selon l'intensité et la tâche ([HFES 2023, Flash Blindness Recovery for Pilots in the Modern Cockpit](https://katalog.lib.cas.cz/KNAV/EdsRecord/edo,ejs64963113)) | durées fixes x exposition^0,6 |
| Adaptation | jour 1, nuit 1,3 | la récupération dépend de la taille de la pupille et de l'adaptation (rapports DTIC, ex. [AD0627332](https://apps.dtic.mil/sti/pdfs/AD0627332.pdf)) | `sunOrMoon` et toit au-dessus de la tête au lieu d'une vraie luminance |
| Distance et regard | table ci-dessous | à quelques mètres, plusieurs millions de candelas saturent toute la rétine, y compris en vision périphérique, et la pupille n'a pas le temps de se fermer ; de dos, la scène entière s'illumine | interpolation linéaire par morceaux |
| Mèche | 1,5 s | M84 : 1 à 2,3 s ; Rheinmetall : 1,0 s ; NonLethal Technologies MP-FB-2 : 1,5 s | réglable 0,5-4 s ; variantes fixes 3 s et 8 s (choix de jeu, pour les entrées synchronisées) |
| Multi-coups | 0,5 s ± 0,12 s, 1 à 9 coups | MP-FB-2 : 2e coup 0,5 s après le 1er ; Combined Systems 7290 : familles 2 à 9 coups | irrégularité aléatoire uniforme |
| Empilement | chaque coup relance et prolonge, plafond 15 s | « chaque détonation relance la désorientation » | +50 % de la durée restante par coup |
| Corps | Ø40 mm, 114 mm, 0,56 kg, évents en bas, bande marron | Rheinmetall 6 Bang Ø40 x 112 mm, 563 g ; Primetake Ø40 x 115 mm, 600 g | évents en texture et relief |
| DRILL | pop sans flash, corps bleu | Rheinmetall : version d'entraînement 150 dB, corps bleu | pas de flash par défaut |
| Désorientation | visée très instable, pas plus lents (0,8), oscillation de la caméra ~10 s, secousse brève | désorientation, perte d'équilibre possible (Primetake), pas d'incapacité | pas de chute ni de ragdoll |
| Dégâts | aucun par défaut | non létale par conception ; brûlures possibles au contact | réglage « Blessure à bout portant » |

**Table de calibration de l'éblouissement** (vision 0 à 1, extérieur, de jour, réglages par défaut ; distance
œil-grenade, 3,5 m = grenade au sol à 3 m ; intérieur : x multiplicateur intérieur ; nuit et JVN : plus fort) :

| Distance | 0-3,5 m | 5 m | 10 m | 15 m | 25 m |
|---|---|---|---|---|---|
| Regard ≤ 65° du flash | 1,00 | 0,85 | 0,50 | 0,30 | 0,04 |
| De côté (90°) | 0,92 | 0,50 | 0,25 | 0,10 | 0 |
| De dos (180°) | 0,35 | 0,20 | 0,08 | 0,03 | 0 |

Mesure en jeu (autotest, joueur debout, grenade au sol, midi) : 2 m 1,00 / 0,98 (60°) / 0,90 (90°) ; 3 m 0,97 /
0,96 / 0,82-0,84 ; 5 m 0,84 / 0,83 / 0,48 ; 10 m 0,50 / 0,50 / 0,25 ; 15 m 0,30 / 0,30 / 0,10 ; 25 m 0,04 / 0 / 0.
De dos, l'angle réel dépend de l'inclinaison de la tête (125 à 160°) : 0,47 à 0,70 à 2-3 m.

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
  1. La machine du lanceur reçoit le tir (XEH « fired » de CBA ; le lancer avancé d'ACE rejoue ce même événement au
     dégoupillage, avec la grenade qu'il a créée). Elle fixe l'heure de la 1re détonation sur l'horloge commune
     `CBA_missionTime` : lancer + mèche (réglage, ou 3 s / 8 s fixes).
  2. Elle compte la mèche sur SA grenade. Si la grenade a disparu entre-temps (véhicule, Zeus, script), rien ne se
     passe.
  3. Sinon, 0,25 s avant l'heure prévue, le serveur pose un engin synchronisé (ThingX, poussé par les explosions,
     supprimable par Zeus). Il tire une fois les instants des N détonations à partir de l'heure prévue, puis envoie
     UN seul message aux machines concernées : serveur, headless, joueurs à moins de 2,5 km, morts et Zeus.
  4. Chaque machine programme exactement N exécutions sur `CBA_missionTime`. Un message en retard rattrape les
     détonations passées sans en sauter. La 1re détonation tombe donc à lancer + 3 s (ou 8 s) sur toutes les machines,
     sans dérive due au réseau.
  5. Si l'engin disparaît, les détonations restantes sont annulées partout, sans détonation fantôme.
- **Pas de détonation à l'impact, grenade qui vit assez longtemps** (vérifié dans la référence CfgAmmo du wiki
  Bohemia et dans ACE3) : les munitions héritent de `SmokeShell` (simulation `shotSmokeX`, comme la M84 d'ACE).
  `explosionTime` y est le début de la fumée, placé à 30 s (au-delà de la durée de vie) avec un effet de fumée vide :
  le moteur ne fait rien exploser, ni au contact ni au bout d'un temps ; `fuseDistance` (armement des projectiles à
  impact) ne s'applique pas. `timeToLive` (« durée avant que la munition disparaisse ») vaut 6 s pour la mèche
  réglable (4 s max), 10 s pour 3 s et 15 s pour 8 s. `deflecting = 15` garde les rebonds de la fumigène.
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
- **Messages réseau** : événements CBA (`CBA_fnc_serverEvent`, `CBA_fnc_ownerEvent`, `CBA_fnc_targetEvent`, transport
  par `publicVariable`), aucun `remoteExec`. Une liste blanche `CfgRemoteExec` de mission ou un filtre BattlEye
  `remoteexec.txt` ne peuvent donc pas bloquer le mod. Événements : `comspec_fb_serverDetonate`, `comspec_fb_serverArea`
  (serveur), `comspec_fb_timeline`, `comspec_fb_cancel`, `comspec_fb_aiRestore`.

## Diagnostic

- Au chargement, une ligne RPT est toujours écrite : `[COMSPEC FB] chargé 1.0.0 · CBA ok · ACE=… ACRE=… TFAR=… ZEN=…
  · autres flashbang: tsp=…/dka=…`.
- En multijoueur, sur un client, une ligne 15 s après le chargement : `[COMSPEC FB] SERVEUR : mod présent (version …)`,
  `… mod absent (clients seulement)` ou `… version différente`.
- Réglage « Journal de diagnostic (RPT) » (client, **actif par défaut** en 1.0.0) : une dizaine de lignes
  `[COMSPEC FB]` par grenade (lancer et chemin vanilla / ACE / secours, mèche, serveur ou client qui fait autorité,
  programmation, une ligne par détonation, effet sur le joueur). Réglage « Journal détaillé » (décoché) : en plus son et
  lumière, chaque unité (distance, angle, vue, intérieur / extérieur, intensités, effets appliqués ou raison du refus).
- Test intégré, depuis la console de débogage (n'importe quelle mission, Eden compris) :
  `[3] call comspec_fb_fnc_debugTest;` (3-BANG BF), `[5, "D8"] call comspec_fb_fnc_debugTest;` (5-BANG BF · 8 s),
  `[1, "", true] call comspec_fb_fnc_debugTest;` (DRILL). La grenade est posée à 4 m devant le joueur et passe par la
  vraie chaîne ; le rapport (réglages, XEH, étapes, intensité pour le joueur) va dans le RPT et le presse-papiers.
- Test automatique en jeu (développeur) : `tools/ingame_test.ps1` lance Arma (profil `COMSPECTest`, CBA + ACE + le
  build) sur `missions/COMSPEC_FB_Autotest.Stratis` : lancers vanilla et ACE ×1, ×3, ×9, délais 3 s / 8 s, DRILL, puis
  lignes `AUTOTEST|OK|…` / `AUTOTEST|KO|…`.

## Limites connues

- **Vérifié en jeu par l'autotest** (solo, CBA + ACE, avant l'arrêt des tests demandé par TTRD) : lancer avancé ACE
  x1, x3, x9 (1re détonation à 1,50 s), Flash x1 · 8 s ACE (7,97 s, aucune détonation à l'impact), DRILL · 3 s ACE,
  lancer vanilla x1 (1,51 s) et x9 (9/9 détonations), effets appliqués au joueur et à une IA, test console, calibration
  extérieure mesurée. **Pas vu en jeu** : lancers vanilla x3 / 3 s / 8 s / DRILL avec le banc final, chemin de secours
  ProjectileCreated, intérieur mesuré, rendu visuel du blanc, du flou et de la tache, désorientation recalibrée,
  niveaux sonores (rien n'a été écouté), multijoueur.
- **Lumière à travers les murs** : les lumières d'Arma traversent les murs. Le flash est donc limité à la taille de la
  pièce, mais une lueur peut se voir de l'autre côté d'un mur fin.
- **Engin au sol** : un ThingX de 0,56 kg peut trembler légèrement sur certaines surfaces.
- **Grenade ramassée** : elle ne peut pas être ramassée entre deux détonations. L'engin n'est pas un objet
  d'inventaire.
- **Détection intérieur / extérieur** : par rayons, donc approximative. Un auvent ou un arbre au-dessus de la tête
  compte comme un toit pour l'adaptation de l'œil.
- **Acouphène** : un flash plus fort relance l'acouphène plus tôt dans le fichier. La reprise est masquée par le bang.
- **Noms** : « × » et « · » des noms courts sont des caractères Latin-1 ; ils doivent s'afficher avec les polices du
  jeu (« · » est déjà utilisé par d'autres mods COMSPEC), à vérifier une fois dans l'inventaire.

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
- modèles : `blender -b -P model_flashbang.py` (extension Arma 3 Object Builder ; `DELAYED_ONLY=1` : seulement les
  variantes à délai) ;
- icônes et logo : `py -3 make_fb_icons.py` (`--delai` : ne convertit que les textures et icônes à délai) ;
- sons : `py -3 gen_fb_sounds.py` (`--drill --ecoute H:\StreamLabs\ogg` : sons de la DRILL et dossier d'écoute) ;
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
   de la DRILL », lueur brève. Essayer chaque « Son de la grenade d'entraînement » (Pop, Pop sourd, Bang léger, Bang
   réel, Rafale courte, Sifflet, Cloche, Aucun son) et le volume ; puis le champ « Son de la DRILL » du module Eden et
   de « Lancer BF » (ZEN), qui doit l'emporter sur le réglage.
8. Lancer une FB-9 puis la supprimer en Zeus pendant les détonations : les bangs s'arrêtent, sans erreur.
9. Mourir ou passer en Zeus pendant un effet : tout disparaît tout de suite (image, son, volume, visée).
10. Multijoueur (serveur dédié + 2 clients) : même nombre de bangs chez tous, sons synchronisés, effets seulement sur
    les joueurs concernés.
11. Avec ACE : bouchons mis (effet auditif réduit), puis retirés. Avec ACRE2 ou TFAR : radio étouffée puis rendue.
12. Avec ZEN : modules « Lancer BF » (mèche par défaut, 3 s, 8 s, personnalisée 12 s) et « Effet de zone BF ».
13. Couloir « délais » (caisse « Caisse délais BF ») : lancer une « 3-BANG BF » standard, une « 3-BANG BF · 3 s » et une
    « 3-BANG BF · 8 s » vers la pièce. La grenade 8 s explose-t-elle bien 8 s après le lancer, sans exploser à
    l'impact ? Même question pour 3 s. Elle doit rebondir et rouler sans détoner avant l'heure.
14. Lancer avancé ACE (Maj+G) d'une 3-BANG BF puis d'une 1-BANG BF · 8 s : même nombre de bangs et même mèche qu'au
    lancer vanilla.
15. Module Eden en mode grenade, « Délai de mèche » = 8 s : détonation 8 s après l'activation ; par défaut : comme
    avant (aucune mèche).
16. Noms dans l'inventaire et l'Arsenal : « 5-BANG BF · 3 s » lisible en entier, « · » bien affiché.
17. GANG BANG BF : 50 détonations en 15 à 20 s, rafales et pauses, la grenade saute ; le jeu reste fluide (journal :
    lignes « coût »), la désorientation et l'acouphène plafonnent, la récupération commence après la dernière.
18. « 9-BANG BF R » et cadence « Hyper rapide » : les 9 bangs en moins d'une demi-seconde, blanc stroboscopique.
19. Volume à 500 % : le bang de près doit couvrir tout le reste ; acouphène : un seul départ, prolongé par les flashs
    suivants, une seule fin (journal « acouphène | démarrage / prolongé / fin »).
20. Modules Zeus « Lancer BF », « Effet de zone BF » et « BetterFlash » posés sur un serveur sans le mod : effet à
    l'endroit posé (journal « module | type=… »).
21. Flammes Aucune / Légère / Marquée ; « Variation aléatoire » à 0 % : tout redevient régulier.

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
- `COMSPEC_FB_Flash_1` … `_9` (« 1-BANG BF » … « 9-BANG BF », N bangs) and `COMSPEC_FB_Drill` (« DRILL BF », blue, pop and
  smoke only, no effect), standard fuse (« Fuse » setting);
- delayed versions with a fixed fuse: `_D3` (3 s) and `_D8` (8 s) on every class, e.g. `COMSPEC_FB_Flash_5_D8`
  (« 5-BANG BF · 8 s »), `COMSPEC_FB_Drill_D3`. 8 s: to throw ahead from a safe position. No detonation on impact; the
  first bang comes at throw + 3 s / 8 s on every machine. Yellow (3 s) or red (8 s) second band, « 3s » / « 8s »
  stamps, clock badge on the icon;
- crates `COMSPEC_FB_Box` (« BF crate », 10 of each standard grenade) and `COMSPEC_FB_Box_Delay` (« BF delay crate »,
  5 of each delayed grenade);
- Eden module `COMSPEC_FB_Module` (« BetterFlash ») with three modes (grenade, area effect, drill), a « Fuse delay » field
  (default = none as before, 3 s, 8 s, custom 0.5-20 s) and a « DRILL sound » field;
- ZEN modules « Throw BF » (fuse: default setting, 3 s, 8 s, custom; DRILL sound) and « BF area effect ».

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
- Outdoors: very loud under 25 m, clear up to 100 m, distant echo beyond, inaudible beyond the « Sound range » (250 m,
  half through a wall); sound is delayed at 343 m/s. The sound range does not change the effects (25 m max).

**Calibration**: dazzle 1.0 within 3.5 m eye distance when looking within 65°, 0.92 at 90°, 0.35 from behind; 5 m
0.85/0.5; 10 m 0.5/0.25; 15 m 0.3/0.1; 25 m negligible. Full white screen ~1.5 s at 3 m, heavy blur (DynamicBlur ~45),
recovery ~12 s, unsteady aim, slower steps (0.8) and slow camera sway for ~10 s. Bang files peak at -1 dBFS (soft
limiter), tinnitus at -6 dBFS; « Flashbang volume » 500 % and « Tinnitus volume » 250 % by default.

**Exact bang count**:
1. The thrower's machine runs the fuse on its own grenade.
2. The server places a synchronised device, rolls the N detonation times once, and sends a single message.
3. Every machine schedules exactly N detonations on `CBA_missionTime`.
4. If the device is deleted, the remaining bangs are cancelled everywhere.

There is no per-frame cost when idle, and every post-process effect is destroyed at the end.

**Settings**: see the French table above. Every setting has a tooltip with an example. The presets are Realistic
(default), Arcade and Light. New: « Training grenade sound » (Pop, Muffled pop, Light bang, Real bang, Short burst,
Drill whistle, Drill bell, No sound) and « Training sound volume » (20-150 %). The « Fuse » setting only applies to
standard grenades; « Irregularity » also applies to the fixed 3 s / 8 s fuse.

**BetterFlash 1.0.0**: 50-flash « GANG BANG BF » (`COMSPEC_FB_Flash_50`, very long and harsh: use sparingly; « Allow
grenades with more than 9 flashes » setting), hyper fast « R » classes (`_R`), « Flash rate » setting (Normal, Fast,
Hyper fast, Random), « Random variation » (60 %), louder sound (bangs about -11 LUFS, 500 % default, close 2D layer,
ducking; turn your headset down before testing), one continuous tinnitus per player, light vent flames.

**Where to load it**: the mod must be loaded on clients; the server is optional for thrown grenades (recommended with
Zeus or the Eden module). Without the mod on the server, the throwing client is the authority and broadcasts one CBA
event; every client places its own local device (no Zeus deletion, server-local AI not affected).

**Network**: CBA events only (no `remoteExec`), so mission `CfgRemoteExec` whitelists and BattlEye filters cannot
block the mod.

**Diagnostics**: one `[COMSPEC FB] chargé …` RPT line at load and one `[COMSPEC FB] SERVEUR : …` line on MP clients;
« Diagnostic log (RPT) » setting, on by default (about ten `[COMSPEC FB]` lines per grenade; « Detailed log » for more); console test `[3] call comspec_fb_fnc_debugTest;` (report to RPT and clipboard); automatic in-game
test `tools/ingame_test.ps1`.

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
