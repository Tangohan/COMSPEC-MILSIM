# Poste TOC web et plans d'opération

La console du poste de commandement du jeu (PC du poste, ATAK natif) dans le navigateur, et un éditeur de plans
d'opération sur les terrains Arma, reliés au jeu dans les deux sens.

## Pages et droits

| Page | Adresse | Droit |
|---|---|---|
| Poste TOC web (console) | `/atak/toc` (onglet direct : `/atak/toc?onglet=demandes`) | `atak.toc.use` |
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

## Console : onglets et sources

Présentation : mêmes jetons que le thème du jeu (`fn_tocTheme.sqf`, `docs/TOC-THEME.md` de l'ATAK natif). Ils sont recopiés dans `public/assets/css/atak-toc.css` par `php tools/toc/sync_toc_tokens.php [chemin de fn_tocTheme.sqf]` (`--check` pour comparer) et vérifiés par `AtakTocApiAccessTest`. Polices : Roboto Condensed (libellés), JetBrains Mono (heures, grilles, nombres). Aucun texte sous 12 px. Console sombre seulement.

| Onglet | Contenu | Source |
|---|---|---|
| Groupes, ORBAT, Réseau | groupes et nœuds ATAK, état (en direct, ancien, perdu), grilles | `/api/atak/units` |
| Flux | comptes rendus (heure, type, émetteur, grille) | `/api/atak/reports` |
| Ordres | ordres en cours (lecture ; ils se donnent dans ATAK web) | `/api/atak/orders` |
| Camp | plan du camp dessiné en jeu | `/api/atak/briefing-plan` |
| Radio | rien ne remonte du jeu (poste local ACRE2/TFAR) : le dit clairement | — |
| Plans | plans visibles, affichage sur la carte, éditer, envoyer en jeu | API plans |
| SAT | « vue carte ISR » : terrain, emprise et opérateur des satellites ; pas la caméra du jeu, dit clairement | salle (instantané du jeu) |
| Salle | écrans de la salle et leur affichage (lecture ; changer un écran se fait en jeu) | salle |
| Chronos, Main courante, EXCHECK, Pertes, Demandes | comme sur le PC du poste, avec actions | salle partagée |
| Calques | panneau des calques sur la carte | — |

Carte : quadrillage numéroté (plaques à 3 chiffres, grille à 6 chiffres), échelle, nord, grille à 8 chiffres sous le curseur, recherche par grille, groupe ou indicatif.

## Calques (console et éditeur)

Case à cocher, opacité, source, heure de la dernière mise à jour et nombre d'éléments par calque. Filtres : camp (mon camp ou tous), groupe, « session » (tout, 2 dernières heures, 30 dernières minutes). Dans l'éditeur, « Ajouter au plan » sur chaque élément copie un élément du jeu dans le plan.

| Calque | Source | Retour vers le jeu |
|---|---|---|
| Pistes BFT (un symbole OTAN par groupe ; les nœuds quand on est près du sol) | jeu | non |
| Traces (depuis l'ouverture du poste, 15 min) | jeu | non |
| Marqueurs (mission et joueurs) | jeu + web | oui (ATAK web) |
| Formes et dessins | jeu + web | oui (ATAK web) |
| Plan du camp | jeu | oui (« Envoyer en jeu ») |
| Zones (battleplan) | jeu + web | oui (ATAK web) |
| Itinéraires et points de passage | jeu + web | oui (ATAK web) |
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
- **Ordres** : se donnent dans ATAK web, pas depuis la console.
- **Temps réel** : sondage (3 s pour la salle, 2,5 s pour un plan), pas de websocket.
