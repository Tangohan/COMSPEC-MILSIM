# Poste TOC web et plans d'opération

La console du poste de commandement du jeu (PC du poste, ATAK natif) dans le navigateur, et un éditeur de plans
d'opération sur les terrains Arma, reliés au jeu dans les deux sens.

## Pages et droits

| Page | Adresse | Droit |
|---|---|---|
| Poste TOC web (console) | `/atak/toc` (onglet direct : `/atak/toc?onglet=demandes` ; fenêtre : `#fenetre=ordre` ; point : `#carte=15200,17350`) | `atak.toc.use` |
| Plans d'opération | `/atak/toc/plans` | `atak.toc.use` |
| Éditeur d'un plan | `/atak/toc/plans/{id}` | `atak.toc.use` + partage du plan |
| Lecture par lien | `/atak/toc/plans/lien/{jeton}` | membre connecté de la communauté |

- `atak.toc.use` : tenir le poste, créer, éditer et envoyer des plans en jeu.
- `atak.toc.manage` : modèles de la communauté, plans de tous les membres, remise à zéro de la salle web.
- `admin.organization` vaut les deux ; l'administrateur de la plateforme passe partout.
- L'étape 4380 crée les deux droits dans chaque communauté. Elle les donne au propriétaire, à l'administrateur, au commandant adjoint et aux gestionnaires d'unité. `atak.toc.use` va aussi aux rôles qui ont `atak.mission_cycle.manage`. Les autres rôles se cochent dans Gestion › Accès (niveau « complet » du module ATAK, et « SA section » pour `atak.toc.use`).
- Entrées : « TOC » dans l'en-tête ATHENA et le menu Espaces, « Poste TOC » et « Plans d'opération » dans le menu Opérateur, « TOC » et « Plans » dans la barre d'ATAK web (`/atak`) et son menu Plus. Elles ne s'affichent qu'à qui a le droit.
- Toute écriture demande le jeton CSRF (`X-CSRF-Token`). Elle est limitée par membre (actions de salle : 60 par minute ; éléments de plan : 240 par minute ; envois en jeu : 6 par minute) et journalisée (`audit_logs`, actions `atak_toc.*`).
- Un plan d'une autre communauté, ou non partagé avec le membre, répond 404 (son existence n'est pas révélée).

## Migrations (à lancer par TTRD)

- `4370_atak_toc_plans` : tables `atak_plans`, `atak_plan_elements`, `atak_plan_phases`, `atak_plan_versions`, `atak_plan_shares`, `atak_plan_comments`, `atak_plan_presence`, `atak_toc_rooms`, `atak_toc_actions`.
- `4380_atak_toc_permissions` : droits `atak.toc.use` et `atak.toc.manage`.

## Console : rubriques, onglets et sources

Quatre rubriques, chacune avec ses onglets (Alt + 1 à 4, flèches sur la barre) :

| Rubrique | Onglets |
|---|---|
| Situation | Groupes, ORBAT, Réseau, Flux |
| Conduite | Ordres, Demandes, Chronos, EXCHECK, Main courante, Pertes |
| Plans | Plans web, Camp |
| Salle | Écrans, SAT, Radio |

Les calques ne sont plus un onglet : bouton « Calques » de la barre de la carte, commande de carte ou touche L.

Présentation : mêmes jetons que le thème du jeu (`fn_tocTheme.sqf`, `docs/TOC-THEME.md` de l'ATAK natif). Ils sont recopiés dans `public/assets/css/atak-toc.css` par `php tools/toc/sync_toc_tokens.php [chemin de fn_tocTheme.sqf]` (`--check` pour comparer) et vérifiés par `AtakTocApiAccessTest`. Polices : Roboto Condensed (libellés), JetBrains Mono (heures, grilles, nombres). Aucun texte sous 12 px. Console sombre seulement.

| Onglet | Contenu | Source |
|---|---|---|
| Groupes, ORBAT, Réseau | groupes et nœuds ATAK, état (en jeu, ancien, perdu ou hors ligne), grilles | `/api/atak/units?toc=1` |
| Flux | comptes rendus (heure, type, émetteur, grille) | `/api/atak/reports` |
| Ordres | ordres en cours ; « Donner un ordre » et « Message » ouvrent une fenêtre de la console | `/api/atak/orders` |
| Camp | plan du camp dessiné en jeu | `/api/atak/briefing-plan` |
| Radio | rien ne remonte du jeu (poste local ACRE2/TFAR) : le dit clairement | — |
| Plans | plans visibles, affichage sur la carte, éditer, envoyer en jeu | API plans |
| SAT | « vue carte ISR » : terrain, emprise et opérateur des satellites ; pas la caméra du jeu, dit clairement | salle (instantané du jeu) |
| Salle | écrans de la salle et leur affichage (lecture ; changer un écran se fait en jeu) | salle |
| Chronos, Main courante, EXCHECK, Pertes, Demandes | comme sur le PC du poste, avec actions | salle partagée |

Carte : quadrillage numéroté (plaques à 3 chiffres, grille à 6 chiffres), échelle, grille à 8 chiffres sous le curseur. Commandes groupées en haut à droite : vue d'ensemble (nord en haut), zoom, calques, mesure (distance, azimut en degrés et en millièmes), plein écran. Recherche avec suggestions (grille, groupe, indicatif, marqueur, point), Entrée pour aller au premier résultat. Légende en bas à droite.

Listes : un nœud n'apparaît qu'une fois. Groupes : une ligne par groupe (symbole, effectif en jeu, état, grille, âge). ORBAT : un seul en-tête de colonnes, un groupe d'un seul membre sur une ligne, un groupe de plusieurs membres en ligne repliable (« 3 / 5 en jeu ») avec ses membres ; filtre ; en jeu d'abord. Réseau : un nœud par ligne (liaison, dernier contact, batterie).

### Pistes, symboles et plaques

- Sources : `/api/atak/units`, `/api/atak/vehicles` et `/api/atak/air-assets`, demandées avec `toc=1`. Le site renvoie alors une ligne par identifiant stable (compte Steam du joueur, sinon indicatif), le genre du symbole, l'altitude, la vitesse et l'âge sur son horloge (`App\Services\AtakToc\TocTracks`, après le brouillage). La console fusionne les trois sources : un aéronef suivi remplace le pilote du même indicatif, un véhicule suivi remplace le nœud du même indicatif.
- Symboles OTAN (milsymbol) par genre : infanterie, motorisée, mécanisée, blindés, reconnaissance, artillerie, mortier, sol-air, génie, soutien, maintenance, santé, PC, arme en poste fixe, hélicoptère, avion, drone, embarcation (`App\Services\AtakToc\TocSymbols`). Couleur d'affiliation vue du camp du poste (ami, hostile, neutre, inconnu).
- États : en direct (moins de 60 s), ancien (jusqu'à 5 min ou liaison différée), perdu au-delà ou hors liaison. Un état ancien ou perdu se lit au style (symbole atténué, cadre en tirets, âge « il y a 4 min »), jamais à un mot dans le libellé. Sans partie en liaison, la dernière position connue reste affichée avec son âge (véhicules : 12 h au plus).
- Plaque : indicatif en gras et ligne secondaire (groupe, genre, âge ; aéronef : altitude, vitesse, cap ; véhicule : type et équipage). Les plaques ne se recouvrent pas et ne recouvrent aucun symbole ; une plaque sans place est masquée (le compte est écrit sous la carte) et un trait de rappel la relie à son symbole quand elle est écartée. Détail selon l'échelle : complet de près, indicatif seul ensuite, symboles seuls de loin. La piste choisie et l'unité de l'opérateur gardent leur plaque.
- Regroupement : des pistes au sol qui se recouvriraient deviennent un symbole « BRAVO · 5 » ; un clic rapproche jusqu'à les séparer. Les aéronefs ne sont jamais regroupés ; ils portent une flèche de cap et une courte traînée.
- Survol : fiche (grille à 8 chiffres, état, dernière position reçue, véhicule, équipage, altitude, vitesse, cap, carburant, batterie). Clic : la piste est choisie, sa fiche et ses actions (centrer, suivre, message, ordre) passent en tête du panneau de droite.

### Marqueurs, points et tracés du jeu

- Marqueurs : le type Arma donne l'icône (`TocSymbols::MARKER_ICONS` : mil_*, hd_*, loc_*, respawn_*, flag_*, contacts) ; les marqueurs OTAN (b_/o_/n_/c_/u_) deviennent un symbole milsymbol ; couleur Arma, texte, taille. Zones ELLIPSE et RECTANGLE avec leur pinceau (plein, bordure, hachures), tracés POLYLINE, flèches orientées. Un marqueur qui porte l'indicatif d'une piste en liaison n'est pas dessiné (la piste fait foi).
- Points et itinéraires : étapes numérotées le long du tracé, vertes une fois atteintes ; points isolés selon leur genre (contrôle, ralliement, objectif, zone de poser, zone de largage, observation, assaut).
- Relais (portée), comptes rendus (couleur selon la priorité), demandes (couleur selon le statut), photos, ordres avec point.

### Menu de la carte (clic droit)

Même ordre que le menu de la carte de l'ATAK en jeu (`fn_mapCtxMenu.sqf`) : ping, marqueur, point de passage, centrer ici, copier la grille. Clic droit, Maj + F10 ou touche Menu (sur la piste choisie, sinon au centre), appui long sur un écran tactile ; flèches, Entrée, Échap. Le menu du navigateur ne s'ouvre jamais sur la carte.

| Sous le clic | Lignes |
|---|---|
| Carte | Ping · Marqueur… · Signaler (hostile, ami, inconnu) · Point de passage… · Centrer ici · Copier la grille · Mesurer d'ici · Cercle de portée (100 m à 1 km) · Ajouter au plan affiché · Partager (lien `#carte=`) |
| Piste (unité, véhicule, aéronef) | Fiche · Centrer · Suivre · Message… · Ordre… · Traces · Voir dans l'ORBAT · Mesurer d'ici · Cercle de portée · Copier la grille |
| Regroupement | Rapprocher · Copier la grille |
| Marqueur, point, compte rendu | Fiche · Centrer · Modifier… et Supprimer (marqueur : son auteur ou `atak.toc.manage`) · Partager · Mesurer d'ici · Copier la grille |
| Tracé du plan web affiché | Ouvrir dans l'éditeur · Masquer le plan · Centrer ici · Copier la grille |

Écritures : API ATAK existantes (`POST /api/pings`, `POST|PATCH|DELETE /api/markers`, `POST /api/atak/waypoints`, `POST /api/atak/orders`, `POST /api/chat`, éléments de plan), avec la session du membre, le cloisonnement par communauté et le jeton CSRF. Un marqueur posé sur le web part aussi en jeu par le relais existant des marqueurs.

### Fenêtres de la console

Message, ordre, marqueur et point de passage s'ouvrent dans une fenêtre latérale de la console (piège du focus, Échap, lien `#fenetre=message&pour=ALPHA%201` qui la rouvre), sans passer par `/atak`. Chaque fenêtre lit et écrit par les API existantes ; « Ouvrir dans la page complète » reste en lien secondaire.

| Fonction | Dans la console | Reste une page |
|---|---|---|
| Message à un groupe ou à tous, derniers messages | fenêtre Message | transmissions complètes : ATAK web |
| Ordre (type, destinataire, priorité, consigne) | fenêtre Ordre | FRAGO et modèles d'ordre : ATAK web |
| Marqueur (poser, modifier, supprimer) | fenêtre Marqueur | — |
| Point de passage | fenêtre Point de passage | itinéraires complets : ATAK web |
| ORBAT prévu et comparé | — | ATAK web (outil complet) |
| Éditeur de plans | — | `/atak/toc/plans/{id}` (éditeur plein écran) |
| Quitter le poste | — | `/atak` |

## Calques (console et éditeur)

Case à cocher, nombre d'éléments, opacité, source et heure de la dernière mise à jour par calque ; « Tout afficher » et « Tout masquer » ; « Luminosité du fond » (voile sombre posé sur la photo, 55 % au départ). Filtres : camp (mon camp ou tous), groupe, fraîcheur (tout, 2 dernières heures, 30 dernières minutes). Les réglages sont gardés par membre dans le navigateur (`athena:toc:layers:console:<id du membre>`). Affichés au départ : unités, véhicules, aéronefs, marqueurs, points et itinéraires, formes, plan du camp, zones, comptes rendus, demandes, plan web. Dans l'éditeur, « Ajouter au plan » sur chaque élément copie un élément du jeu dans le plan.

| Calque | Source | Retour vers le jeu |
|---|---|---|
| Unités au sol (symbole OTAN par nœud, regroupées à petite échelle) | jeu | non |
| Véhicules (type, équipage) | jeu | non |
| Aéronefs : hélicoptères, avions, drones (altitude, vitesse, cap, traînée) | jeu | non |
| Traces (depuis l'ouverture du poste, 15 min) | jeu | non |
| Marqueurs (mission et joueurs) | jeu + web | oui (console : menu de la carte ; ATAK web) |
| Formes et dessins | jeu + web | oui (ATAK web) |
| Plan du camp | jeu | oui (« Envoyer en jeu ») |
| Zones (battleplan) | jeu + web | oui (ATAK web) |
| Points et itinéraires | jeu + web | oui (console : « Point de passage… » ; ATAK web) |
| Relais et couverture réseau | jeu | non |
| Comptes rendus géolocalisés | jeu | non |
| Photos géolocalisées (12 h, camp du poste) | jeu | non |
| Demandes du poste | jeu + web | réponse (onglet Demandes) |
| Ordres avec point | web | oui (ATAK web) |
| Plan web affiché | web | « Envoyer en jeu » |
| Tracés de l'app Traçage, cibles et tâches | — | affichés « pas remonté par le jeu au site pour l'instant » |

Réalisme : les calques lisent les mêmes API que la carte ATAK web, avec les mêmes droits et le même brouillage. Rien n'y entre que ce que le réseau connaît. Limite connue : `/api/recon/images` ne filtre pas par carte. Les photos sont donc posées seulement si elles sont dans le terrain, récentes (12 h) et du camp du poste.

## Fonds de carte

Sélecteur : **Satellite** (par défaut), **Carte du jeu**, **Sombre**.

- Satellite = nos tuiles « Photo COMSPEC » (`storage/map-photo/<terrain>/sat`, servies par `/map-photo/…`). Pyramide en mètres Arma chargée à la demande (`loading=lazy`), cache d'une semaine puis revalidation par ETag. Une tuile manquante est découpée dans son ancêtre côté site. Si elle manque encore, la carte du jeu reste visible dessous.
- Tant qu'aucune tuile n'est publiée pour un terrain, Satellite affiche la photo aérienne existante (Altis) ou la carte du jeu, et le dit sous la carte.
- Carte du jeu = « Plan COMSPEC » (`storage/map-photo/<terrain>/plan`) s'il est publié, sinon les tuiles du jeu déjà utilisées par ATAK web.
- Terrains : ceux de la table `atak_maps` (Altis, Stratis, Malden, Tanoa, Livonia/Enoch, Chernarus, Takistan, Kimmirut). Au 11/11, **aucun terrain n'a de tuiles Photo COMSPEC publiées** sur athena-comspec.eu (`/public/api/atak/map-photo/list` répond `[]`). La pyramide satellite d'Altis est déjà fabriquée sur le poste de TTRD (`COMSPEC_ATAK_Photo_build\Altis_sat`, 160 846 tuiles, z0 à z10).
- Publier un fond (hors dépôt, `storage/map-photo` est ignoré par git), avec la clé plateforme dans la variable d'environnement `COMSPEC_KEY` :
  - fond déjà fabriqué : `python mod/COMSPEC_ATAK_Native/tools/map_stitch.py E:\Developpement\compsec.ttrd.fr\COMSPEC_ATAK_Photo_build\Altis_sat E:\Developpement\compsec.ttrd.fr\COMSPEC_ATAK_Photo_build --upload https://athena-comspec.eu/public`
  - depuis les captures du jeu : `mod\COMSPEC_ATAK_Native\build_photo_pack.bat --world Altis --upload https://athena-comspec.eu/public`
  - envoi par paquets ZIP de 64 Mo au plus, le fond n'est remplacé qu'à la fin. Vérifier ensuite `/public/api/atak/map-photo/list`.

## Salle partagée avec le jeu : règle d'autorité

- **Le serveur du jeu fait foi quand il est en liaison** (instantané reçu depuis moins de 2 min). Son état remplace celui du site à chaque envoi. Une révision plus ancienne de la même mission est ignorée.
- **Une action du web** (chrono, main courante, EXCHECK, pertes, réponse à une demande, FLASH) est gardée (`atak_toc_actions`), affichée tout de suite et marquée « en attente » (◷). Elle est envoyée au jeu par la file des commandes web. Elle disparaît de la surcouche quand l'instantané du jeu la cite (liste `wc` des commandes appliquées) ou quand la file l'a déclarée faite.
- **Deux changements sur la même ligne** : le serveur du jeu les applique dans l'ordre d'arrivée, et le dernier gagne. Chaque commande n'est appliquée qu'une fois (id de commande), même remise par plusieurs joueurs du camp.
- **Sans jeu en liaison**, le web tient la salle seul : ses actions restent affichées 12 h. Elles partent en jeu à la prochaine liaison du même terrain si leur délai le permet : FLASH 3 min, lancer, arrêter ou remettre à zéro un chrono 10 min, le reste 12 h. Une action dont la commande a expiré sans être reprise disparaît dès que le jeu est de nouveau en liaison.
- Une ligne née sur le web porte l'id `W<action>`. Le serveur du jeu la relie à son propre id (T…, E…) pour les commandes suivantes.
- L'heure LOCAL de la console est l'heure de la mission quand le jeu est en liaison, sinon celle du poste.

### Jeu vers site

Le mod envoie la salle par l'appel COMSPEC Link existant `BriefingPlan.Push` (POST `/api/atak/briefing-plan`), avec `"channel": "toc_room"`. Il n'y a **aucune nouvelle commande dans la DLL**.

```json
{"channel":"toc_room","side":"WEST","mapId":1,"mission_key":"…","world":"Altis","rev":42,"wc":[501,502],
 "room":{"timers":[[id,nom,"UP|DOWN|AT",valeur,en_marche,cumul_s]],"log":[[hh:mm:ss,genre,auteur,texte]],
  "excheck":[[id,n,événement,mot_de_code,élément,prévu,réel]],"requests":[[id,heure,genre,demandeur,"",grille,texte,statut,réponse,[x,y]]],
  "flash":[texte,auteur,hh:mm],"posts":[[poste,opérateur,"",heure]],"kills":[[élément,wia,kia,ekia,ewia]],
  "sats":[[x,y,demi_largeur,capteur,suivi,"",opérateur]],"screens":[[nom,affichage,salle]],"local":"13:35:00","date":"2035-6-14"}}
```

- Envoyé par un seul joueur du camp : celui qui tient le poste, sinon le plus petit UID ; relais par un autre après 90 s sans envoi. Fréquence : toutes les 3 s au plus quand la salle change, toutes les 60 s sinon.
- Aucun UID de joueur ne part.
- Rien n'est envoyé avant l'acceptation de la licence, ni en mode ATAK local.

### Site vers jeu

Les commandes web passent par le genre `toc` du fil WC1 (`atak_web_commands`), lu par le sondage existant des charges (`GetExplosiveCommands`). Format : `WC1~toc~*~<commande>~<réf>~s=<camp>~…`. Le fil fait au plus 480 caractères et ne contient que de l'ASCII (les accents sont retirés à l'envoi).

| Commande | Réf | Arguments |
|---|---|---|
| `timeradd` | – | `w` id web, `n` nom, `k` UP/DOWN/AT, `v` valeur |
| `timerstart`, `timerstop`, `timerreset`, `timerdel` | id du chrono (T… ou W…) | – |
| `log`, `flash` | – | `t` texte (120 caractères) |
| `flashclear` | – | – |
| `exadd` | – | `w`, `ev`, `cw`, `el`, `at` |
| `exdone`, `exundo`, `exdel` | id de la ligne | – |
| `reqset` | id de la demande | `st` RECUE/PRISE/REFUSEE/TERMINEE, `an` réponse (le jeu la renvoie au téléphone du demandeur) |
| `kill` | – | `el`, `k` wia/kia/ekia/ewia, `v` nombre |
| `plnclr` | préfixe `W<plan>_` | `pl`, `n` |
| `plnadd` | id de l'élément `W<plan>_<n>` | `k` type du jeu, `c` couleur, `f` (d tirets, a flèche, w épaisseur), `t` texte, `p0`… points `x:y,x:y` |

Côté mod (branche `feat/atak-toc-web-sync`) :
- `fn_webCmdDispatch` envoie le genre `toc` à `fn_tocWeb` « cmd ».
- Chaque client du camp remet la commande au serveur (`comspec_atak_native_tocWeb`).
- Le serveur l'applique une fois, par `fn_tocRoom` « do » ou par `fn_planServer` « webPatch », puis la cite dans l'instantané suivant.

## Plans d'opération

- **Outils** :
  - sélection (Maj+clic pour ajouter), main, mesure (distance, azimut en degrés et en millièmes, longueur totale) ;
  - unité OTAN (affiliation, fonction, échelon, indicatif ; milsymbol du site, licence MIT, `public/assets/vendor/milsymbol`), texte, repère ;
  - points : CP, RP, TRP, objectif, bâtiment fouillé ou non ;
  - traits : trait, flèche, itinéraire, axe, attaque, appui, mouvement, PL, LD, LOA, limite, SBF, ABF ;
  - zones : polygone, rectangle, ellipse, cercle, objectif, AO, NAI, EA, AA, zone ennemie, HLZ, infiltration, exfiltration ;
  - appuis feux et obstacles : NFA, FFA, RFA, champ de mines.
- **Édition** : couleurs (les 9 du jeu ou une autre), épaisseur, tirets, flèches, remplissage, taille, rotation, rayons, note ; sommets et rayons par poignées ; aimantation aux sommets ; déplacer à la souris ou aux flèches ; pivoter, agrandir, dupliquer, copier-coller (entre plans), supprimer, verrouiller, grouper, ordre d'affichage ; annuler et rétablir (250 pas).
- **Organisation** :
  - phases ordonnées (élément dans une ou plusieurs phases, sans phase = toutes ; briefing pas à pas avec ← →) ;
  - calques du plan (visibles, verrouillés) ;
  - ordre d'opération SMEAC ; légende automatique et lignes libres ;
  - commentaires sur le plan ou sur un élément ; présence des membres qui ont le plan ouvert.
- **Versions** : enregistrées à la demande, avant chaque envoi en jeu, avant une restauration et avant une suppression (60 par plan) ; restauration.
- **Partage** : privé (et partages nominatifs), unité, communauté ; verrou ; lien de lecture pour les membres ; modèles de la communauté ; duplication.
- **Export et import** :
  - PNG (vue affichée, fond compris si les tuiles autorisent la copie) ;
  - PDF (fiche de briefing ATHENA : carte, phases, ordre, éléments et leurs grilles) ;
  - JSON (format `athena-plan` version 1) ; import JSON contrôlé (2 Mo, 1 500 éléments, schéma vérifié élément par élément).
- **Plusieurs éditeurs** : chaque élément porte l'heure de son dernier enregistrement, une horloge du plan strictement croissante. Le dernier enregistré gagne, élément par élément. Un éditeur est prévenu quand sa version a remplacé celle d'un autre. Les changements se relèvent toutes les 2,5 s (`?since=` + ETag).
- **Appareils** : tablette et bureau ; téléphone en lecture seule. Raccourcis clavier : touche « ? ».
- Les volets, le temps, le relief et la vue 3D sont décrits plus bas.

## Éditeur de plans : volets, glisser-déposer, temps, relief et vue 3D

Écran : barre d'outils en haut ; à gauche **ORBAT**, **Calques**, **Bibliothèque** ; la carte au centre (2D ou 3D) ; à droite **Propriétés**, **Plan**, **Échanges** ; la frise du temps en bas. Les volets se replient. Bureau à partir de 1280 px et tablette ; téléphone en lecture seule.

- **Bibliothèque** : symboles par famille (unités, matériels et installations, points, manœuvre, mesures de coordination, zones, appuis feux, obstacles et NRBC, santé et logistique, dessin libre), recherche, favoris, récents. Un clic arme le symbole (clic sur la carte pour le poser, ou tracer), un glisser le pose où on le lâche, en 2D comme en 3D. Affiliation et échelon des unités posées se choisissent en tête du volet. Les matériels et les fonctions hors liste passent par un code 2525C (`props.sidc`) ; ceux que milsymbol ne sait pas dessiner ne sont pas proposés.
- **ORBAT du plan** : arbre des unités par affiliation. Glisser une unité sur une autre la rattache, entre deux la range, sur la carte la pose ou la déplace. Œil et verrou par unité, recherche, « à poser » pour une unité qui n'est pas encore sur la carte. « ORBAT du site » reprend les unités de la communauté (celles que le membre voit déjà sur `/orbat`, sans donnée personnelle) ; « Depuis le jeu » ajoute les groupes ATAK en liaison à leur position.
- **Calques** : ordre par glisser (celui du haut est dessiné devant), œil, verrou, opacité, calque actif ; calques du jeu en dessous, avec « Ajouter au plan ».
- **Sélection** : clic ; Maj ou Ctrl+clic ; cadre en glissant dans le vide ; lasso (Q) ; Alt+clic pour l'élément du dessous ; tout le calque, toute la phase, tout le même type ; groupes. Poignées : sommets (glisser un milieu ajoute un sommet, double-clic en retire un), rayons, échelle aux quatre coins, rotation (Maj : pas de 15°), portée, azimut et ouverture d'un éventail. Aligner, répartir, avancer ou reculer d'un cran, masquer, verrouiller. Flèches : 10 m (Maj 100 m, Alt 1 m).
- **Presse-papiers** : copier, couper, coller sous le curseur, coller à la même place, dupliquer, Alt+glisser pour dupliquer en déplaçant. Le presse-papiers du système porte notre format (`athena-plan-clip`) : on colle d'un plan à l'autre, d'un onglet à l'autre ; un plan JSON ou un GeoJSON collé est importé. Copier et coller le style. Copier l'image de la vue.
- **Palette de commandes** (Ctrl+K) : toute action, symbole, unité, calque, phase, fond de carte, grille (`0425 1169`) ou heure (`H+0:30`). Clic droit : menu sur la carte, un élément ou l'arbre. Touche « ? » : raccourcis.
- **Graphismes** ajoutés : axe avec sa largeur, limite avec marque d'échelon, lignes de coordination nommées aux deux bouts (PL, LD, LOA, FSCL, CFL, RFL, FLOT), position de combat, TAI, PZ, DZ, zone contaminée, ligne et ceinture d'obstacles, cercles de portée concentriques, éventail de portée (masqué par le relief si demandé), itinéraire avec points de passage numérotés et délais (vitesse), texte avec renvoi, points MEDEVAC, nid de blessés, ravitaillement, poste d'observation, cible, décontamination.
- **Unités** : indicatif, affiliation, fonction, échelon, statut présent ou prévu, renforcé ou réduit, PC, groupement, formation supérieure, informations, direction de mouvement, unité mère.
- **Import et export** : JSON du site, **GeoJSON** et **KML** (exports `/atak/toc/plans/{id}/export.geojson` et `export.kml`, import par le menu Importer ou en déposant le fichier sur la carte ; KMZ non pris en charge). Coordonnées en mètres Arma (`crs: arma-metres`), élément complet joint pour un retour sans perte. Un fichier étranger en degrés est ajusté dans la vue affichée. L'import renvoie des éléments contrôlés que l'éditeur pose : il s'annule par Ctrl+Z.

### Temps

- Chaque phase a un début en minutes par rapport à l'heure H (`meta.timeline.phases`) ; l'heure H (ZULU), la durée et l'affichage H+ ou ZULU se règlent dans la frise.
- Une unité peut porter des **images clés** (`props.track`, 60 au plus) : avec « Clé auto », la déplacer à un instant de la frise pose une image clé ; entre deux images clés elle va en ligne droite, ou le long d'un tracé choisi (`props.route`). Fenêtre de présence par élément (`t0`, `t1`).
- Frise : lecture, pause, vitesse, tête de lecture à glisser, débuts de phase à glisser, repères (heure H, événements, lignes EXCHECK du poste reprises par « EXCHECK » quand leur heure est en H+), images clés de la sélection à glisser.
- **Briefing** : plein écran, phase par phase (← →), les unités glissent d'une position à la suivante.
- **Envoi en jeu** : une phase part avec les positions des unités au début de cette phase ; le plan entier part avec les positions de base. Éléments masqués ou pas encore posés : jamais envoyés.
- Même calcul côté site (`App\Services\AtakToc\PlanTimeline`) et côté éditeur (`TocPlanLogic.positionAt`).

### Relief et vue 3D

- **Source** : le relief que le site a déjà (relevé du théâtre, cellule de 50 m, `AtakTerrainRepository`), le même que pour l'ombrage et la ligne de vue d'ATAK web. Rien de nouveau à stocker. L'éditeur le lit en binaire : `GET /atak/toc/api/terrain/{carte}/dem` (droit `atak.toc.use`, int16 petit-boutiste, rangée 0 au sud, en-têtes `X-Dem-*`, ETag, cache privé de 5 min ; 404 `relief_unavailable` sans relevé).
- **Relever un terrain** (une fois par terrain, par TTRD ou un Zeus) : en jeu avec Overwatch connect relié à Athena, poser le module Zeus « Relevé du théâtre » et laisser tourner sur la carte entière. Le relevé envoie le terrain par blocs de 32 × 32 cellules à `/api/atak/terrain/chunk` : 400 blocs pour Altis (615 × 615 cellules). L'avancement se lit dans la fenêtre du relevé et sur `/api/atak/terrain?mapId=…` (`coverage_pct`). L'ATAK natif n'a pas d'outil de relevé : aucun changement de mod ni de DLL n'a été fait.
- Sans relevé : l'éditeur le dit (« Relief non disponible pour ce terrain : lancez le relevé du théâtre en jeu »), les outils de relief ne répondent pas et la vue 3D reste à plat.
- **Outils** : altitude sous le curseur et dans Propriétés ; mesure (distance, azimut, surface, dénivelé) ; ligne de vue entre deux points avec hauteurs d'observateur et de cible, et sa coupe ; coupe du relief le long d'un tracé (montée, descente, pente max) ; zone vue depuis un point ; teinte des pentes ; masque des éventails de portée. Calculs dans le navigateur (`plan-logic.js`), relief seul : ni bâtiments ni végétation.
- **Vue 3D** (bouton 2D / 3D, touche 3) : three.js r160 déjà fourni par le site (`public/assets/vendor/three`, licence MIT), module `public/assets/js/toc/plan/plan-3d.js` chargé seulement à l'ouverture. Terrain entier en maillage grossier (192 × 192 au plus) et zone du plan à la cellule du relevé (512 × 512 au plus), texture du fond affiché (nos tuiles satellite ou plan) ou teinte hypsométrique à défaut. Traits, zones, flèches, cercles, libellés, zone vue et pentes sont **drapés** sur le relief ; unités et repères sont des étiquettes dressées à taille d'écran constante, avec tige et point au sol ; la ligne de vue est tracée dans l'air. Réglages : exagération du relief, heure du soleil, nord en haut.
- **Ce qui se fait en 3D** : choisir (clic, Maj), déplacer en glissant sur le relief, poser un symbole ponctuel (clic, ou glisser depuis la bibliothèque ou l'ORBAT), mesure, ligne de vue, coupe, zone vue, menus, propriétés, frise et briefing. **Le tracé des traits et des zones, les poignées, le cadre et le lasso restent en 2D.** Sans WebGL, la carte reste en 2D et le dit.
- Limites : cellule de 50 m (pas de micro-relief), pas de bâtiments en volume (le module sait les extruder, l'éditeur ne les lui fournit pas encore), cadence non mesurée sur un vrai poste (rendu vérifié en logiciel seulement).

### Importer les marqueurs présents en jeu

Menu Importer › « Marqueurs présents en jeu » (`plan-markers.js`). Un volet s'ouvre à droite, la carte reste visible.

- **Source** : les marqueurs que le site connaît pour le terrain du plan, par les API du calque « Marqueurs » du poste TOC web (`/api/atak/markers`, plus les points ATAK de `/api/atak/waypoints`) et l'état de la partie (`/atak/toc/api/state`). Aucune nouvelle route : mêmes droits et même cloisonnement par communauté.
- **Une seule conversion** marqueur Arma → symbole : `TocTracks.markerRecord` (dans `toc-tracks.js`), lue par le calque du poste et par l'import. Icône → repère nommé ; marqueur OTAN (b_, o_, n_, c_, u_) → unité avec son affiliation ; ellipse → ellipse ; rectangle → zone à quatre sommets (tournée) ; pinceau → remplissage, hachures en bord à tirets ; tracé → trait ou flèche ; point ATAK → point de contrôle, de ralliement, objectif ou cible. Couleur ramenée à notre palette (le noir d'Arma, illisible sur la console sombre, devient blanc), texte gardé.
- **Liste** : groupée par genre, canal ou camp ; recherche ; vignettes ; compteurs ; tout cocher. Filtres : ignorer les marqueurs de service (réapparition, modules, marqueurs vides sans texte), mon camp seulement, dans la vue affichée seulement. Aperçu des marqueurs cochés sur la carte.
- **Destination** : calque et phase au choix. « Garder le lien avec le jeu » (non coché au départ) : sans lien c'est une copie ; avec le lien, « Actualiser les marqueurs liés » (Ctrl+K) remet l'élément à jour depuis le jeu.
- **Ré-import** : un marqueur déjà importé (même identifiant en jeu, `props.gid`) est signalé ; au choix mettre à jour l'élément (il garde son calque, ses phases, sa note), ne pas y toucher, ou importer en double.
- **Une seule opération** : tout l'import se défait d'un Ctrl+Z.
- **États** : « Aucune partie en liaison », « Terrain de la partie différent de celui du plan », « Aucun marqueur » ; sans liaison mais avec des marqueurs déjà reçus, la liste le dit (« marqueurs du dernier relevé reçu »).
- **Ce que le jeu n'envoie pas (à faire côté mod, rien n'a été changé ici)** : le canal du marqueur et son auteur ne sont pas dans ce que le site reçoit pour tous les marqueurs (le relevé local envoie identifiant, position, texte, type, couleur, forme, taille, direction, opacité) ; les groupes « par canal » et « par camp » rangent donc ces marqueurs sous « non précisé par le jeu ». Les pings ne sont pas proposés (éphémères).

### Disposition de l'écran

`plan-layout.js`. Chaque volet se replie en un rail d'icônes (boutons, `[` et `]` quand rien n'est choisi), la frise aussi (`9`), la barre d'outils par la palette ; « carte seule » par `0` (Échap pour revenir). Les bords se glissent (largeur des volets, hauteur de la frise ; double-clic : replier ou déplier). Sections repliables : camps de l'ORBAT, familles de la bibliothèque, calques du plan et du jeu, phases, ordre d'opération, légende ; groupes d'outils. Tout est gardé par membre dans le navigateur. À 1366 px et moins, le volet de droite reste replié tant que rien n'est choisi.

### Tutoriel et aide

`plan-help.js`. Tutoriel guidé à la première ouverture (et par l'aide) : dix étapes sur l'écran réel qui avancent quand le geste est fait, à passer, quitter et reprendre. « Plan d'exemple » : un plan privé créé pour s'exercer. Tiroir d'aide (bouton d'aide, touche `?`) : modes d'emploi illustrés, raccourcis, lexique des graphismes, recherche. Astuce au premier usage de chaque outil. Guide écrit : `docs/TOC-PLANS-TUTORIEL.md`, servi par `/atak/toc/plans/aide`.

### Banc d'essai

- `tests/Frontend/fixtures/plan-backend.php` : la vraie vue et les vrais services (PlanService, AtakPlanRepository, PlanValidator, PlanInterop) sur une base sqlite jetable ; tout avertissement PHP y est une erreur 500. `php -d extension=pdo_sqlite -S 127.0.0.1:8790 -t . tests/Frontend/fixtures/plan-backend.php`.
- `tests/Frontend/cdp.py` : pilote Edge ou Chrome sans écran par DevTools (vrais événements de souris et de clavier, captures, relevé des erreurs de console et des réponses 4xx / 5xx).
- `tests/Frontend/scenarios/` : `edition`, `disposition`, `marqueurs`, `temps_3d`, `fichiers_acces`, `tutoriel`, `logique` ; `python tests/Frontend/scenarios/tout.py` les enchaîne.
- Limites du banc : le jeu, la salle, les marqueurs, l'ORBAT de la communauté et le relief y sont simulés dans le navigateur ; le presse-papiers du système n'existe pas sans écran (les événements copier / coller sont joués dans la page) ; le rendu 3D est logiciel.

### ORBAT Mapper

L'ergonomie s'inspire d'[ORBAT Mapper](https://github.com/orbat-mapper/orbat-mapper) (licence **MIT**) : arbre ORBAT à glisser sur la carte, volets, palette de commandes, frise et lecture, propriétés à onglets. **Aucun code, aucune image ni marque n'en est repris** : l'éditeur reste en JavaScript sans cadre applicatif, sur Leaflet, milsymbol et three.js du site.

### Fichiers

| Fichier | Rôle |
|---|---|
| `public/assets/js/toc/toc-plan-editor.js` | cœur : état, enregistrement, carte 2D, sélection, poignées, dessin, presse-papiers, clavier, envoi en jeu |
| `public/assets/js/toc/plan/plan-logic.js` | calculs sans navigateur (sélection, presse-papiers, temps, relief, ORBAT), testés |
| `public/assets/js/toc/plan/plan-panels.js` | volets, bibliothèque, ORBAT, calques, propriétés, palette, menus, dépôt de fichiers |
| `public/assets/js/toc/plan/plan-timeline.js` | frise, lecture, repères, briefing |
| `public/assets/js/toc/plan/plan-terrain.js` | relief, outils de relief, pont vers la vue 3D |
| `public/assets/js/toc/plan/plan-3d.js` | vue 3D (module ES, three.js) |
| `public/assets/js/toc/toc-plan-render.js`, `toc-geo.js` | dessin partagé avec la console (ajouts seulement) |
| `public/assets/css/atak-toc-plan.css` | présentation de l'éditeur (jetons du thème TOC) |
| `app/Services/AtakToc/PlanTimeline.php`, `PlanInterop.php` | temps ; GeoJSON et KML |

Essais : `tests/Unit/AtakTocPlanV2Test.php`, `AtakTocPlanV2ApiTest.php` ; `tests/Frontend/toc-plan-logic.test.cjs` (Node, ou `tests/Frontend/run-in-browser.html` sans Node). Banc d'essai : voir plus bas ; démonstration de la vue 3D seule : `tests/Frontend/fixtures/plan-3d-demo.html`.

### Envoi en jeu et import depuis le jeu

- « Envoyer en jeu » pose le plan entier ou une phase dans le plan partagé du camp choisi : app Plan des téléphones, carte du PC du poste et écrans de la salle.
- Les éléments déjà envoyés par ce plan sont d'abord retirés.
- Seul le plan part, jamais une position d'unité.
- « Importer depuis le jeu » ajoute le plan du camp tel que les téléphones l'ont envoyé (moins de 12 h) dans un calque « Jeu ». Un nouvel import met à jour les mêmes éléments.

| Web | Jeu (app Plan) | Adaptation |
|---|---|---|
| unité amie / hostile | UNITF / UNITH | – |
| unité neutre / inconnue | NOTE | texte « NEUTRE … » / « INCONNU … » |
| texte, repère | NOTE | repère : son libellé |
| CP, RP, TRP, objectif (point), bâtiment fouillé / non fouillé | CP, RP, TRP, OBJPT, CLEARED, UNCLEARED | – |
| itinéraire, axe, attaque, appui, mouvement, PL, LD, LOA, limite | ROUTE, AXIS, ATK, SPT, MOVE, PL, LD, LOA, BDY | – |
| SBF, ABF | SBF, ABF | premier et dernier point |
| trait, flèche | FREE | flèche gardée |
| objectif, AO, NAI, EA, AA, ennemi, HLZ, infiltration, exfiltration, NFA | OBJ, AO, NAI, EA, AA, ENY, HLZ, INFIL, EXFIL, NFA | cercle → polygone à 24 côtés |
| FFA, RFA, champ de mines | ZONE | titrée FFA, RFA, MINES |
| polygone, rectangle, ellipse, cercle | ZONE | en polygone |

- Couleur : la plus proche des 9 couleurs du jeu.
- Points : 28 au plus par élément (tracé simplifié).
- Texte : ASCII, 40 caractères.
- 300 éléments au plus par envoi (sinon : envoyer phase par phase).
- Retour (jeu vers web) : le type inverse ; une zone à 2 points devient un trait ; un type inconnu est ignoré.

## Ce qui n'est pas fait

- **Caméras et SAT** : pas de vidéo du jeu dans le navigateur (vue carte seulement).
- **Écrans de la salle** : changer l'affichage d'un écran depuis le web n'est pas possible (lecture seule).
- **Radio** : non remontée par le jeu.
- **Traçage et tâches** : l'app Traçage, les cibles et les tâches ne remontent pas au site.
- **Ordres** : la console émet les ordres simples ; FRAGO et modèles d'ordre restent dans ATAK web.
- **Préférences** : les calques sont gardés dans le navigateur, pas sur le site.
- **Temps réel** : sondage (3 s pour la salle, 2,5 s pour un plan), pas de websocket.
- **Éditeur de plans** : image en surimpression calée sur deux points, pièces jointes sur un élément, aimantation aux routes, curseurs et sélections des autres éditeurs, bâtiments en volume dans la vue 3D, tracé des traits et des zones en 3D, KMZ, noms de lieux dans la palette.
