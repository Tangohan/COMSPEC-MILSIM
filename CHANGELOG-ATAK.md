# CHANGELOG - Features ATAK

Toutes les modifications notables des features ATAK sont documentées ici.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.0.0/),
et ce projet adhère au [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Journal développeur (style Bohemia) : [SPOTREP #00003](docs/dev/SPOTREP-00003.md) · [TECHREP #00003](docs/dev/TECHREP-00003.md).

Changelog Steam (copier-coller) : [Overwatch 1.6.12](docs/dev/STEAM-CHANGELOG-2026-09-22-overwatch-1.6.12.md) · [Overwatch 1.6.9](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.9.md) · [Overwatch 1.6.8](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.8.md) · [Overwatch 1.6.7](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.7.md) · [Overwatch 1.6.3](docs/dev/STEAM-CHANGELOG-2026-09-20-overwatch-1.6.3.md) · [Overwatch 1.5.78](docs/dev/STEAM-CHANGELOG-2026-09-15-overwatch-1.5.78.md) · [Overwatch 1.5.77](docs/dev/STEAM-CHANGELOG-2026-09-14-overwatch-1.5.77.md) · [Overwatch 1.5.76](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.76.md) · [Overwatch 1.5.75](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.75.md) · [Overwatch 1.5.74](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.74.md) · [Overwatch 1.5.73](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.73.md) · [Overwatch 1.5.72](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.72.md) · [Overwatch 1.5.71](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.71.md) · [Overwatch 1.5.70](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.70.md) · [Overwatch 1.5.69](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.69.md) · [Overwatch 1.5.68](docs/dev/STEAM-CHANGELOG-2026-09-13-overwatch-1.5.68.md) · [Overwatch 1.5.36](docs/dev/STEAM-CHANGELOG-2026-09-11-overwatch-1.5.36.md).

---

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
