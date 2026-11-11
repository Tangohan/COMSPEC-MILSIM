# TOC en jeu : état des lieux et feuille de route (novembre 2026)

Branche `docs/toc-feuille-de-route`, lue sur `origin/main` (ATAK natif 4.7.1, commit `5dfe1dc33`). Document de
cadrage : aucun code n'est changé ici. Rien n'a été lancé dans Arma : tout vient de la lecture du code
(`mod/COMSPEC_ATAK_Native/Sources/addons/main/functions/toc/`, 26 fichiers, 10 800 lignes), des docs
(`docs/TOC-THEME.md`, `docs/TOC-WEB.md`) et du journal des versions. Ce qui demande un essai en jeu est marqué
« à vérifier en jeu ».

Périmètre demandé par TTRD : le TOC **dans le jeu et sur le serveur** (écrans, murs, postes PC et leur console,
modules, outils Zeus et Eden, sauvegarde, logique serveur, actions ACE, multijoueur), sans dépendre d'Athena ni du
site.

Principe directeur, posé par TTRD : **« Il faut que tout le TOC soit utilisable sans téléphone. »** Le téléphone
ATAK devient une source facultative, plus riche, jamais une condition. C'est le premier lot du plan.

## En bref

- **Le socle est bon.** L'état de la salle est tenu par le serveur, chaque joueur dessine ses propres écrans et rend
  ses propres caméras, seuls les réglages circulent. Les écrans ne coûtent que devant celui qui les regarde. La
  sauvegarde reprend salle, écrans, chronos, registres, dessins et plans.
- **Le TOC est aveugle sans téléphone.** La console s'ouvre sans téléphone, mais presque tout ce qu'elle montre vient
  des téléphones : positions, états, comptes rendus, demandes, pertes, accusés de réception, caméras poitrine. Une
  mission sans aucun téléphone donne une carte vide et des registres vides. Vingt dépendances sont listées plus bas.
- **Le serveur fait confiance aux clients.** Camp, nom et identifiant de l'auteur d'une action de salle viennent du
  client ; aucun droit par rôle, aucun verrou, aucun journal d'audit. L'état complet de la salle est renvoyé à tous
  les joueurs, tous camps confondus, à chaque changement.
- **Il manque la conduite.** Pas d'ORBAT vivant du jeu, pas de tableau d'effectifs, pas de 9 lignes au poste, pas de
  suivi des questions (RFI), pas d'alertes de zone, pas de relève, pas de mode briefing, pas de rejeu de l'opération.
- **Plan proposé : 4 lots**, de 4.9.0 à 4.12.0, après la 4.7.2 et la 4.8.0 déjà en cours. Le lot 1 est
  l'autonomie sans téléphone, avec un scénario d'essai « mission à zéro téléphone ».

## 1. État du TOC en jeu aujourd'hui

### 1.1 Ce qui est solide

| Sujet | Pourquoi c'est solide | Fichiers |
|---|---|---|
| État de la salle tenu par le serveur | Chronos, satellites, main courante, EXCHECK, demandes, FLASH, postes, pertes dans une variable par camp, révision comprise ; les clients envoient des demandes | `fn_tocRoom.sqf` |
| Rendu local, état partagé | Texture UI et caméras créées par chaque joueur ; seul le réglage de l'écran circule (variables publiques de l'objet, donc reçues par qui rejoint) | `fn_tocScreen.sqf`, `fn_tocCam.sqf`, `fn_tocSat.sqf` |
| Coût selon ce qu'on regarde | Trois états (allumé, figé, veille), grille de 100 m, test de mur toutes les 3 s, 8 images redessinées par tour au plus | `fn_tocScreen.sqf` (« tick », l. 700-860) |
| Flux du réseau économe | Publié seulement aux lecteurs du camp (PC ouvert ou écran à moins de 200 m), renvoyé sur changement, sinon toutes les 9 s | `fn_tocFeed.sqf` (l. 105-208) |
| Sauvegarde continue | Écriture regroupée 3 s après un changement, relevé des positions toutes les 15 s, dernière écriture en fin de mission, profil écrit au plus une fois par minute | `fn_tocPersist.sqf` |
| Pose automatique | Écran posé par n'importe quel moyen : désigné, lié à une salle, gardé | `fn_tocAuto.sqf`, `fn_tocHallBuild.sqf` |
| Serveur plus ancien | Repli sur les joueurs, dit dans l'interface avec les deux versions | `fn_tocConfig.sqf` |
| Thème unique | Une seule table de couleurs, tailles et régions, vérifiée par un test et des maquettes | `fn_tocTheme.sqf`, `docs/TOC-THEME.md` |
| Tests statiques | 20 tests `AtakToc*` (mise en page, thème, textes, placements, satellite) | `tests/Unit/AtakToc*.php` |

### 1.2 Ce qui est fragile

| Sujet | Constat | Fichiers |
|---|---|---|
| Autorité du serveur | `["do", [camp, action, args, libellé, UID]]` : le camp, le libellé et l'UID viennent du client, sans contrôle. Un client peut écrire dans la salle d'un autre camp ou se faire passer pour un autre opérateur. Même chose pour les salles (`create`, `rename`, `roomRemove`) | `fn_tocRoom.sqf` l. 212-216, `fn_tocHall.sqf` l. 137-139 |
| Diffusion de l'état | `missionNamespace setVariable [_var, _st, true]` à chaque action : tout l'état (150 lignes de main courante comprises) part à tous les clients, des quatre camps. Fuite d'information entre camps et charge réseau inutile. Le flux, lui, est bien ciblé | `fn_tocRoom.sqf` l. 209 et 419, `fn_tocHall.sqf` l. 32 |
| Aucun droit, aucun verrou | Tout joueur du camp peut changer un écran, effacer la main courante, supprimer une ligne d'EXCHECK, prendre un satellite. Pas de rôle, pas de verrouillage, pas d'historique de qui a fait quoi | `fn_tocRoom.sqf`, `fn_tocHall.sqf`, `fn_tocScreen.sqf` (ACE) |
| Retours envoyés au téléphone | Les actions ACE des écrans, le lien « viser un écran », le refus d'un PC d'un autre camp, le repli serveur écrivent par `fn_notify`, dont le bandeau n'est dessiné que si le téléphone est affiché (`fn_notificationsRender` sort si le display est absent). Sans téléphone : aucun retour | `fn_tocScreen.sqf` l. 1583-1678, `fn_tocHallUi.sqf` l. 77 et 89, `fn_tocPost.sqf` l. 79, `fn_tocConfig.sqf` l. 83 et 521, `fn_projector.sqf` l. 61 |
| État « perdu » | L'état vient de l'âge du dernier point PLI du téléphone. Joueur hôte, serveur sans le mod ou joueur dont le téléphone ne balise pas : « perdu » alors que l'unité est vivante et visible | `fn_tocFeed.sqf` l. 117-119 et 153-164 |
| Format de sauvegarde | Un seul tableau positionnel `[2, date, écrans, flux, salle, salles, [marqueurs, plans]]` dans le profil du serveur, clé `carte_mission`. Pas de table de migration, pas de copie de secours, pas de limite de taille, pas d'export | `fn_tocPersist.sqf` l. 71 |
| Boucles par image | Caméra d'écran, mosaïque et satellite : un gestionnaire à 0 s (chaque image) par source rendue ; texte à LED à 30 images par seconde. Bornés par `toc_cam_max`, mais sans budget commun ni mesure affichée | `fn_tocCam.sqf` l. 160, `fn_tocMosaic.sqf` l. 155, `fn_tocSat.sqf` l. 259, `fn_tocText.sqf` l. 169 |
| Client headless | Le flux lit `allUnits` sur le serveur : les IA d'un client headless sont vues, mais `COMSPEC_ATAK_Pli` et `COMSPEC_ATAK_Beacon` dépendent de la machine qui les pose. À vérifier en jeu | `fn_tocFeed.sqf`, `fn_aiAtak` |
| Fichiers très gros | `fn_tocLaptop.sqf` 2 009 lignes, `fn_tocScreen.sqf` 1 869 lignes : toute nouvelle fonction y passe, d'où les conflits entre branches | — |
| Nœuds plafonnés | 150 nœuds par camp, sans le dire à l'écran | `fn_tocFeed.sqf` l. 190 |

### 1.3 Ce qui manque

Salles et postes comme objets à part entière (rôles, relève, droits), ORBAT du jeu, effectifs et munitions, 9 lignes
au poste, suivi des questions, alertes de zone, rythme de bataille, mode briefing, pointeur, rejeu, tableau de santé
pour le Zeus, export de diagnostic, fonctions publiques documentées pour les créateurs de mission, raccourcis
clavier, thème nuit. Le détail est dans la liste de la section 3.

### 1.4 Dépendances au téléphone (audit complet)

Règle visée : **le TOC lit le jeu lui-même** (unités, véhicules, aéronefs, groupes, santé, munitions, marqueurs) et
**fusionne** ce que les téléphones rapportent quand il y en a. Aucune fonction du TOC ne doit exister seulement au
téléphone.

| N° | Où | Dépendance aujourd'hui | Comment la lever |
|---|---|---|---|
| D1 | `fn_tocFeed.sqf` l. 126-146 | Un nœud n'existe que si l'unité porte un ATAK (`fn_hasDevice`). Sans téléphone : carte, GROUPES, RÉSEAU, SQUAD, NET vides | Source « jeu » sur le serveur : toutes les unités du camp, position lue directement ; la source est notée sur le nœud (JEU ou ATAK) |
| D2 | `fn_tocFeed.sqf` l. 153-164 | Position et fraîcheur tirées du point PLI ; téléphone éteint : « hors ligne » | Position du jeu par défaut ; l'état PLI n'est gardé que si le réglage « réseau réaliste » est choisi |
| D3 | `fn_tocFeed.sqf` (événements, l. 30-80) | Le flux n'est alimenté que par des événements émis par des téléphones (comptes rendus, alertes, SALUTE, MEDEVAC, BDA) | Saisie des mêmes comptes rendus depuis la console ; événements du jeu (contact, perte, véhicule détruit) ajoutés par le serveur |
| D4 | `fn_tocRoom.sqf` l. 108-166 | Main courante, demandes et pertes « automatiques » ne viennent que du réseau ATAK | Mêmes entrées créées à la console (formulaires) et par le serveur |
| D5 | `fn_tocRoom.sqf` l. 168-174 | Un mort n'est compté que s'il portait un ATAK | Compter toute unité du camp ; la source est écrite (« jeu ») |
| D6 | `fn_tocRoom.sqf` l. 94-99 | La réponse du poste à une demande part en bandeau du téléphone du demandeur | Réponse lisible aussi sur les écrans (REQ), à la console, et par une action ACE sur soi « Mes demandes au TOC » (fenêtre) |
| D7 | `fn_tocAction.sqf` l. 37-43 | Un message du poste est jeté si le destinataire n'a pas de téléphone | Messages du poste affichés sur les écrans (bandeau, LOG) et dans une fenêtre « Messages du TOC » ouverte par ACE |
| D8 | `fn_orderLocal.sqf` | Un ordre arrive dans l'app Tâches ; l'accusé de réception se fait au téléphone | Tableau « Ordres » sur les écrans, accusé par ACE sur soi ou par l'opérateur au nom du groupe (compte rendu radio) |
| D9 | `fn_tocCam.sqf` l. 40, `fn_tocMosaic.sqf` | Caméra poitrine seulement pour un porteur d'ATAK ; qualité selon la liaison du téléphone | Caméra de casque de tout joueur si le réglage l'autorise ; qualité fixe quand la source n'a pas de téléphone |
| D10 | `fn_tocSat.sqf` (suivi) | Pistes amies du satellite : seulement les nœuds ATAK | Pistes de la source « jeu » (D1) |
| D11 | `fn_moduleTocScreen.sqf` l. 79, `fn_tocConfig.sqf` l. 379 | Escouades proposées : groupes avec joueurs ou porteurs d'ATAK ; la fiche SQUAD montre « l'état ATAK » | Tous les groupes du camp ; fiche avec santé, munitions, véhicule lus dans le jeu |
| D12 | `fn_tocScreen.sqf` l. 1719 | « Diffuser mes diapositives » demande un téléphone (les diapositives y sont) | Diapositives choisies à la console (briefing de la mission, images de la mission) |
| D13 | Voir 1.2, « retours envoyés au téléphone » | Aucun retour visible sans téléphone | Ligne d'état de la console, pied de l'écran concerné (« lié », « refusé : PC d'un autre camp »), ligne de retour du Zeus |
| D14 | `fn_tocBoard.sqf` l. 533, `fn_tocLaptop.sqf` l. 1137-1143 et 1568 | ORBAT : celui du site (`fn_bftOrbat`), lu avec le compte Athena du joueur ; vide sans Athena | ORBAT du jeu (groupes, chefs, rôles, véhicules) par défaut ; celui du site reste un calque en plus |
| D15 | `fn_tocDraw.sqf` l. 24-26 | Les outils de dessin sont ceux du téléphone (état `COMSPEC_ATAK_State`, `COMSPEC_ATAK_MapCtrlOverride`, réglages du profil du téléphone) | À vérifier en jeu sans téléphone ; sinon état propre à la console |
| D16 | `XEH_postInitClient.sqf` l. 296-304 | `comspec_overwatch_require_item` suit `fn_hasDevice` : sans téléphone, COMSPEC Link garde ses boucles de synchronisation en attente (« Pas de terminal ATAK ») ; tout ce qui passait par Athena s'arrête pour l'opérateur | Le TOC ne lit jamais ces boucles ; un opérateur à un poste compte comme terminal pour Link (à traiter avec l'équipe Link, hors `fn_radioGuard`) |
| D17 | `fn_tocFeed.sqf` l. 198 | Compteurs « joueurs, IA » du réseau : porteurs seulement | Effectif réel du camp, part « sur le réseau ATAK » en second |
| D18 | `fn_tocLaptop.sqf` l. 110-121 | Un groupe sans nœud est listé sans grille ni état | Grille et état lus dans le jeu |
| D19 | `fn_pageToc.sqf`, `fn_tocAction.sqf` « pc » | App Poste TOC du téléphone : seconde entrée vers le PC | À garder comme raccourci ; rien n'y est exclusif (à tenir vrai à chaque version) |
| D20 | `XEH_postInitClient.sqf` l. 573 | « App Poste TOC disponible » en bandeau du téléphone | Rien à faire : information propre au téléphone |

Hors périmètre : `fn_tocWeb.sqf` (envoi de la salle au site par un joueur relié). Il ne conditionne rien en jeu et
ne part qu'après acceptation de la licence.

Réglage proposé pour trancher réalisme et confort, expliqué avec un exemple dans les options :

- « Source des positions du TOC » : **Jeu** (défaut : toute unité du camp est vue à sa vraie position ; exemple :
  une section sans téléphone apparaît sur le mur), **Réseau ATAK** (seuls les porteurs sont vus, à leur dernier
  point ; exemple : un groupe qui perd ses téléphones disparaît du mur), **Mixte** (tout le monde est vu, les
  porteurs ont en plus batterie, signal et fraîcheur).

### 1.5 Performances et multijoueur

| Sujet | Aujourd'hui | Faiblesse | Piste |
|---|---|---|---|
| Localité | Écrans créés par le serveur ; en repli, par le joueur | Écran local à un joueur qui part : objet transféré au serveur, non gardé | Reprise par le serveur à la déconnexion (n° 12) |
| Arrivée en cours de partie | Variables publiques des objets et de la salle : reçues à l'arrivée | Événements globaux (clignotement, test d'image) perdus, sans gravité ; file JIP jamais purgée des écrans supprimés, à vérifier en jeu | Plan d'essai (section 6) |
| Charge réseau | Flux ciblé ; état de salle diffusé à tous | Main courante renvoyée entière à chaque ligne | Diffusion ciblée et par morceaux (n° 9) |
| Serveur | `allUnits` parcouru toutes les 3 s par camp lu, inventaire vérifié toutes les 8 à 12 s par unité | Coût qui croît avec les IA ; aucune mesure | Tranches et mesure dans le tableau de santé (n° 33, 37) |
| Caméras (RTT) | Plafond par joueur `toc_cam_max`, portée `toc_cam_range`, résolution selon la distance | Pas de budget commun écrans + mosaïque + satellites + console ; un mur 4x3 en mosaïque sature | Budget unique (branche en cours, `fn_tocRtt.sqf`), puis priorités (n° 36) |
| Par image | Voir 1.2 | Un gestionnaire par source | Une seule boucle pour toutes les caméras (n° 36) |
| Sauvegarde | Profil du serveur | Profil partagé avec le reste du mod ; pas de secours | Format versionné, secours, export (n° 10) |
| Client headless | Non traité explicitement | Variables d'IA posées côté headless | Essai dédié (section 6) |

## 2. Déjà en cours (à ne pas refaire)

| Branche | Contenu | Plaintes de TTRD couvertes |
|---|---|---|
| `fix/atak-poste-pc-sidergb` (4.7.2) | Correctif `_sideRgb` du PC du poste | Erreur `_sideRgb` |
| `feat/atak-toc-tableau-ecrans` | Gestionnaire écrans / sources / sorties (Zeus et Eden), mode BROADCAST avec mire SMPTE, dispositions incurvées et multitâches, arbre ACE plus clair, texte du bandeau sans étirement, écrans qui clignotent sans liaison, état « perdu », budget des caméras (`fn_tocBroadcast.sqf`, `fn_tocRtt.sqf`) | Clignotement, module réutilisé qui ne fait que retirer, menus de module laids, gestionnaire d'écrans, BROADCAST, dispositions, ACE, bandeau étiré, « perdu » |
| `feat/atak-toc-app-poste-refonte` | Console du PC refaite en quatre rubriques : Situation, Conduite, Plans, Salle | Zoom et boutons du PC, console à refaire |
| `feat/atak-toc-bureaux-3d` | Bureaux et chaises | Bureaux laids ou à l'envers |

Toutes les plaintes de la session sont donc prises par une branche en cours. La feuille de route part de leur
résultat : elle ne retouche ni la mire, ni l'arbre ACE, ni la mise en page de la console, ni les meubles. L'état
« perdu » est corrigé en surface par la branche des écrans ; sa cause (état tiré du téléphone) est traitée au lot 1.

## 3. Améliorations proposées (38)

Taille : S (un ou deux jours, un fichier), M (une semaine, quelques fichiers), L (plusieurs semaines, nouveau
sous-système). « Serveur interne » : oui si tout marche sans Athena ni site. « Web » : la fonction existe sur le
poste TOC web (`docs/TOC-WEB.md`) et vaut d'être reprise en jeu.

### 3.1 Autonomie sans téléphone

| N° | Amélioration | Ce que le joueur y gagne | Taille | Dépend de | Risques | Serveur interne | Web |
|---|---|---|---|---|---|---|---|
| 1 | Pistes du jeu : le serveur construit les nœuds depuis les unités, véhicules et aéronefs du camp (D1, D2, D10, D17, D18), fusionnés avec les téléphones | Le mur montre toute la force, même sans un seul téléphone | L | Réglage « Source des positions » | Coût serveur avec beaucoup d'IA ; réalisme (d'où le réglage) | Oui | Oui (pistes véhicules et aéronefs, altitude, vitesse) |
| 2 | État d'une piste lu dans le jeu : vivant, blessé, inconscient, à terre, en véhicule ; la fraîcheur PLI ne sert qu'en mode réseau | Plus de « perdu » pour une unité vivante | M | 1 | ACE médical absent : repli sur les dégâts du jeu | Oui | Non |
| 3 | Saisie au poste : comptes rendus (SITREP, SALUTE, contact), MEDEVAC, appui feu, ravitaillement, pertes, créés à la console au nom d'un élément (D3, D4) | L'opérateur transcrit ce qu'il entend à la radio, comme un vrai poste | M | Console refaite | Doublons avec un téléphone : rapprochement par élément et heure | Oui | Oui (fenêtres message, ordre, marqueur de la console) |
| 4 | Ordres sans téléphone : tableau « Ordres » sur les écrans, accusé par ACE sur soi ou saisi par l'opérateur (D8) | Un chef sans téléphone reçoit et accuse un ordre | M | 3 | Deux accusés pour un même ordre : le dernier fait foi, l'auteur est écrit | Oui | Non |
| 5 | Messages et réponses du poste hors téléphone : bandeau des écrans, LOG, fenêtre « Messages du TOC » par ACE (D6, D7) | Personne ne rate un message du poste faute de téléphone | S | — | Aucune alerte du jeu : tout reste dans les écrans et une fenêtre | Oui | Non |
| 6 | Retours dans le TOC : ligne d'état de la console, pied de l'écran, ligne de retour du Zeus à la place des bandeaux du téléphone (D13) | Chaque action dit ce qu'elle a fait, là où on regarde | S | — | Textes à tenir courts sur les bandeaux 4:1 | Oui | Non |
| 7 | ORBAT du jeu : groupes, chefs, rôles, véhicules, lu sur le serveur ; l'ORBAT du site devient un calque (D14) | L'ORBAT est juste dès le lancement, sans compte | M | 1 | Groupes créés par le Zeus : mis à jour en direct | Oui | Oui (lignes repliables « 3 / 5 en jeu ») |
| 8 | Caméras sans téléphone : casque de tout joueur selon réglage, diapositives choisies à la console (D9, D12) | Le mur d'images marche avec une section sans téléphone | S | Budget des caméras | Vie privée du joueur : réglage serveur, coupé par défaut pour le camp adverse | Oui | Non |

### 3.2 État serveur qui fait foi

| N° | Amélioration | Ce que le joueur y gagne | Taille | Dépend de | Risques | Serveur interne | Web |
|---|---|---|---|---|---|---|---|
| 9 | Diffusion ciblée et par morceaux : état de salle envoyé aux seuls clients du camp, main courante en ajouts | Moins de saccades réseau, un camp ne lit plus la salle de l'autre | M | — | Arrivée en cours de partie : état complet à la demande | Oui | Non |
| 10 | Sauvegarde versionnée : champs nommés, table de migration, copie de secours de la partie précédente, export et import en texte | Une salle n'est plus perdue par une mise à jour ou un profil abîmé | M | — | Taille du profil : plafonds par registre | Oui | Non |
| 11 | Contrôle des demandes : le serveur retrouve lui-même le joueur, son camp et son identité au lieu de croire le client | Personne ne peut écrire dans la salle d'un autre camp | S | — | Passage de `CBA_fnc_serverEvent` à un appel qui porte l'émetteur : à faire partout d'un coup | Oui | Non |
| 12 | Reprise et retour : écran d'un joueur parti repris par le serveur, poste et satellites libérés puis rendus à qui revient dans les 5 minutes | Une déconnexion ne casse pas la salle | S | 11 | — | Oui | Non |

### 3.3 Tenue de la salle

| N° | Amélioration | Ce que le joueur y gagne | Taille | Dépend de | Risques | Serveur interne | Web |
|---|---|---|---|---|---|---|---|
| 13 | Postes nommés : chaque PC porte un rôle (chef de quart, opérations, renseignement, feux, logistique, transmissions) avec sa vue par défaut | Chacun s'assoit et trouve sa console prête | M | Console refaite | Trop de rôles : liste courte, réglable | Oui | Non |
| 14 | Droits par rôle, camp et grade : qui lit, qui tient un poste, qui reconfigure les écrans ; réglables par la mission et le Zeus | Un joueur de passage ne dérègle plus le mur | M | 11, 13 | Blocage si personne n'a le droit : le Zeus et l'administrateur passent toujours | Oui | Oui (droits `use` et `manage`) |
| 15 | Verrou de salle et d'écran : un écran ou toute la salle verrouillés pendant un briefing | Le mur ne bouge plus pendant qu'on parle | S | 14 | Verrou oublié : levé à la relève et par le Zeus | Oui | Non |
| 16 | Relève : passage de consignes avec résumé automatique (ordres en cours, demandes ouvertes, pertes, prochaines lignes d'EXCHECK) signé par l'entrant | Le quart suivant sait où on en est en une minute | M | 13, 17 | Résumé trop long : une page | Oui | Non |
| 17 | Journal des actions du TOC : qui a changé quoi (écran, registre, verrou), consultable à la console et par le Zeus | On sait qui a effacé la ligne | S | 11 | Volume : 500 lignes, gardées avec la salle | Oui | Oui (journal `atak_toc.*`) |
| 18 | Journal de quart : notes entre opérateurs, attachées à un poste, distinctes de la main courante | Les opérateurs se parlent sans polluer le registre officiel | S | 13 | — | Oui | Non |

### 3.4 Conduite des opérations

| N° | Amélioration | Ce que le joueur y gagne | Taille | Dépend de | Risques | Serveur interne | Web |
|---|---|---|---|---|---|---|---|
| 19 | Tableau des effectifs : par élément, présents, blessés, tués, munitions (vert, ambre, rouge), carburant et état des véhicules | Le chef voit d'un coup d'œil qui peut encore combattre | M | 1, 2 | Lecture des chargeurs coûteuse : par tranches, 30 s | Oui | Non |
| 20 | Objectifs et phases : tableau des objectifs (à prendre, en cours, tenu), phase en cours, chronos liés, EXCHECK lié | Tout le monde sait dans quelle phase on est | M | — | Double saisie avec l'EXCHECK : une seule liste, deux vues | Oui | Oui (phases des plans web) |
| 21 | Rythme de bataille : matrice de synchronisation (éléments en lignes, temps en colonnes) et rendez-vous du poste (points de situation, relèves) | Les actions des éléments se lisent côte à côte dans le temps | L | 20 | Lisibilité sur un 16:9 : 8 lignes par page | Oui | Non |
| 22 | Suivi des questions (RFI) : question, demandeur, poste chargé, échéance, réponse | Une question posée au poste ne se perd plus | S | 3 | — | Oui | Non |
| 23 | MEDEVAC 9 lignes au poste : fiche complète, moyen désigné, heures de départ et de poser, ZP sur la carte | L'évacuation est suivie de l'appel au poser | M | 3 | Deux formats (9 lignes, MIST) : champs facultatifs | Oui | Non |
| 24 | Appui feu et CAS : file des demandes (9 lignes, 5 lignes), moyen attribué, autorisation, état, cible sur la carte | Le poste arbitre les feux au lieu de les subir | M | 3 | Lien avec l'app JTAC du téléphone : même enregistrement, deux entrées | Oui | Non |
| 25 | Tâches ISR : file de demandes d'observation, attribution d'un satellite, d'un drone ou d'une caméra, mur d'images à bascule automatique sur l'alerte | Les capteurs sont employés sur ordre, pas au hasard | M | Budget des caméras, 29 | Bascule intempestive : délai minimal par source | Oui | Non |
| 26 | Commande des caméras depuis la console : orientation et zoom des caméras fixes et des nacelles de drone | L'opérateur pointe la caméra sans quitter son poste | M | 25 | Conflit avec le pilote du drone : la main se prend et se rend | Oui | Non |
| 27 | Logistique : stocks des caisses et véhicules de ravitaillement suivis, demandes rapprochées des stocks | Le ravitaillement part avant la panne | M | 19 | Inventaires gros : seulement les objets marqués par la mission ou le Zeus | Oui | Non |
| 28 | Santé du réseau : relais, portée, brouilleurs, éléments hors couverture, en tableau et en calque | On voit qui va perdre la liaison avant qu'il la perde | S | — | Reprend le calcul des relais, sans le refaire | Oui | Oui (relais et portée sur la carte) |
| 29 | Alertes : entrée ou sortie de zone, contact, munitions basses, perte de liaison, pertes ; gravité qui choisit l'écran, la couleur et le son de la salle | L'événement important s'impose au mur sans que personne le cherche | L | 1, 2 | Trop d'alertes : seuils et silence par type ; jamais d'alerte du jeu, tout dans les écrans | Oui | Non |
| 30 | Situation ennemie : pistes déclarées avec fiabilité et effacement dans le temps (récent plein, ancien pâle) ; contacts des IA amies en option | La carte ne ment plus avec des contacts vieux d'une heure | M | 3 | Réalisme : rien d'automatique sans réglage | Oui | Oui (signaler hostile, ami, inconnu) |
| 31 | Mode briefing : dérouler les phases du plan sur le mur (suivant, précédent), pointeur visible par tous, annotations au mur | Le briefing se fait devant le mur, sans téléphone ni carte papier | M | 15, 20 | Pointeur fluide : position envoyée 5 fois par seconde au plus | Oui | Non |
| 32 | Rejeu et bilan : traces des éléments et événements enregistrés par le serveur, rejoués sur un écran avec une ligne de temps | Le débriefing montre ce qui s'est vraiment passé | L | 1, 10 | Taille : un point par 10 s et par élément, plafond d'heures | Oui | Oui (traces des pistes) |
| 33 | Météo, heure et éphémérides : vent, visibilité, pluie, lever et coucher du soleil, lune, prévision de la mission | Les aéronefs et la nuit se préparent au mur | S | — | — | Oui | Non |

### 3.5 Console et écrans

| N° | Amélioration | Ce que le joueur y gagne | Taille | Dépend de | Risques | Serveur interne | Web |
|---|---|---|---|---|---|---|---|
| 34 | Clavier : raccourcis des rubriques et onglets, flèches et Entrée dans les listes, Échap, recherche, aide des touches | On tient la console sans souris | S | Console refaite | Touches déjà prises par le jeu : seulement fenêtre ouverte | Oui | Oui (Alt + 1 à 4, touche L) |
| 35 | Thèmes jour, nuit et faible lumière : jetons de couleur de rechange, luminosité de salle, écrans sombres sous JVN | Les écrans n'éblouissent plus la nuit | S | `fn_tocTheme.sqf` | Contraste : mêmes tests que le thème actuel | Oui | Non |
| 36 | Budget unique des caméras : une seule boucle, priorité à l'écran regardé, cadence réduite pour les vignettes, mesure affichée | Plus d'images, moins de chute de fluidité | M | `fn_tocRtt.sqf` en cours | Régressions de rendu : essai sur mur 4x3 | Oui | Non |
| 37 | États vides, de chargement et d'erreur dans la console et sur les écrans ; plafonds dits à l'écran (« 150 nœuds affichés sur 212 ») | On sait si c'est vide, en attente ou en panne | S | 6 | — | Oui | Oui (« le dit clairement ») |
| 38 | Menu de la carte au clic droit, comme sur le web : ping, marqueur, mesurer d'ici, cercle de portée, ordre vers ce point | Les gestes de la carte sont les mêmes partout | S | Console refaite | — | Oui | Oui |

### 3.6 Outils Zeus, Eden et créateurs de mission

Ces quatre points s'ajoutent à la liste, sans numéro de fonction joueur (ils servent le Zeus et le créateur de
mission).

| Réf. | Amélioration | À quoi ça sert | Taille | Dépend de | Risques | Serveur interne |
|---|---|---|---|---|---|---|
| Z1 | Tableau de santé du TOC (Zeus) : écrans, salle, images par seconde de chaque source, coût serveur du flux, dernière sauvegarde, version serveur et joueurs | Le Zeus voit pourquoi un écran est noir sans lire le journal | M | 36 | — | Oui |
| Z2 | Export de diagnostic : un bloc de texte dans le journal et dans une fenêtre à copier | Un rapport de bogue complet en un clic | S | Z1 | Aucun identifiant de joueur dans l'export | Oui |
| Z3 | Bibliothèque de préréglages : salles et scènes nommées, gardées dans le profil du serveur, reposées dans une autre mission | Une salle réglée une fois sert partout | M | 10 | Classes absentes dans une autre mission : remplacées, dit à l'écran | Oui |
| Z4 | Fonctions publiques et événements : `comspec_atak_native_fnc_tocApi` (ajouter une ligne d'EXCHECK, pousser une alerte, changer un écran, lire l'état) et événements CBA (`comspec_atak_native_tocChanged`, `…tocAlert`, `…tocOrderAck`), avec une page de doc et un exemple par fonction | Le créateur de mission pilote le TOC par script | M | 11 | Contrat à tenir d'une version à l'autre : version de l'API publiée | Oui |

### 3.7 Écarté ou reporté

- Bouton d'alternat radio depuis la console : ni ACRE2 ni TFAR n'offrent de fonction publique pour cela (déjà noté
  dans `fn_tocLaptop.sqf`). La lecture des radios reste.
- Tout ce qui toucherait `fn_radioGuard` : exclu.
- Vidéo du satellite ou des caméras vers le site : hors périmètre (liaison web).
- Alertes par `hint`, `systemChat`, `titleText`, `cutText`, notification du jeu ou bandeau ZEN : exclues partout ;
  chaque retour va dans la console, sur un écran ou dans une fenêtre.

## 4. Plan de versions

Numéros selon `docs/VERSIONING.md` (fonctions : MINEUR + 1). Une demande de fusion par version.

| Lot | Version | Contenu | Valeur | Risque |
|---|---|---|---|---|
| En cours | 4.7.2, 4.8.0 | Voir section 2 | — | — |
| 1. Autonomie sans téléphone | 4.9.0 | n° 1 à 8, 11, 37, et le registre des panneaux (ci-dessous) | Très forte : c'est le principe | Moyen (coût serveur, réglage de réalisme) |
| 2. Salle tenue | 4.10.0 | n° 9, 10, 12 à 18, 34, 35 | Forte | Faible à moyen (droits) |
| 3. Conduite | 4.11.0 | n° 19, 20, 22 à 24, 27 à 31, 33, 38 | Forte | Moyen (beaucoup de panneaux) |
| 4. Mémoire et outils | 4.12.0 | n° 21, 25, 26, 32, 36, Z1 à Z4 | Moyenne à forte | Élevé (rejeu, budget des caméras) |

**Registre des panneaux (préalable du lot 1, taille M).** Aujourd'hui tout nouveau tableau oblige à toucher
`fn_tocLaptop.sqf`, `fn_tocBoard.sqf` et `fn_tocScreen.sqf`. Proposition : chaque tableau devient un fichier
(`fn_tocPanel<Nom>.sqf`) qui se déclare (titre, données lues, dessin au mur, dessin à la console, actions). Les trois
gros fichiers ne font plus qu'appeler le registre. C'est ce qui permet aux lots suivants d'avancer en parallèle sans
conflit.

### 4.1 Fichiers par lot et travail en parallèle

| Lot | Fichiers qu'il possède | Fichiers partagés qu'il touche (ajouts d'une ligne) |
|---|---|---|
| 1 | Nouveaux : `fn_tocTracks.sqf` (pistes du jeu), `fn_tocEntry.sqf` (saisies du poste), `fn_tocInbox.sqf` (messages et ordres hors téléphone), `fn_tocPanels.sqf` (registre). Existants : `fn_tocFeed.sqf`, `fn_tocRoom.sqf` (entrées et contrôle des demandes), `fn_tocCam.sqf` (sources) | `fn_tocLaptop.sqf`, `fn_tocBoard.sqf`, `fn_tocScreen.sqf` (branchement du registre, une seule fois), `config.cpp`, réglages CBA, `XEH_postInit*.sqf` |
| 2 | Nouveaux : `fn_tocAccess.sqf`, `fn_tocStation.sqf`, `fn_tocHandover.sqf`, `fn_tocJournal.sqf`, `fn_tocSync.sqf` (diffusion ciblée), `fn_tocKeys.sqf`. Existants : `fn_tocPersist.sqf`, `fn_tocHall.sqf`, `fn_tocTheme.sqf`, `docs/TOC-THEME.md` | `config.cpp`, réglages CBA |
| 3 | Nouveaux : un `fn_tocPanel*.sqf` par tableau (effectifs, objectifs, RFI, MEDEVAC, feux, logistique, réseau, météo, ennemi), `fn_tocAlerts.sqf`, `fn_tocBrief.sqf` | `config.cpp` |
| 4 | Nouveaux : `fn_tocReplay.sqf`, `fn_tocIsr.sqf`, `fn_tocPtz.sqf`, `fn_tocHealth.sqf`, `fn_tocApi.sqf`, `fn_tocPresets.sqf`, `docs/TOC-API.md`. Existants : `fn_tocRtt.sqf`, `fn_tocMosaic.sqf`, `fn_tocSat.sqf` | `config.cpp` |

- **En parallèle dès maintenant, sans conflit** : dans le lot 2, les n° 10 (sauvegarde), 17 (journal) et 35
  (thèmes) ; dans le lot 4, Z2 et Z4. Ils ne touchent ni la console ni les écrans.
- **Après la fusion de la console refaite et des écrans (4.8.0)** : lot 1, par un seul agent pour le registre, puis
  deux agents (données serveur d'un côté, saisies et boîte de réception de l'autre).
- **Après le lot 1** : lots 2 (reste) et 3 en parallèle, un agent par famille de panneaux ; le lot 4 en dernier pour
  le rejeu, qui lit les pistes du lot 1 et la sauvegarde du lot 2.
- **Points de conflit connus** : `config.cpp` (liste des fonctions), les réglages CBA, `XEH_postInitClient.sqf`,
  `CHANGELOG-ATAK.md`. Règle : chaque lot ajoute ses lignes dans un bloc à son nom ; les têtes de branche sont
  réalignées sur `main` avant chaque demande de fusion.

## 5. Dix gains rapides (tiennent dans un seul lot)

Tous de taille S, sans dépendance lourde ; les sept premiers servent directement l'autonomie sans téléphone.

1. Retours dans le TOC au lieu du téléphone (n° 6) : ligne d'état, pied d'écran, ligne de retour du Zeus.
2. Morts comptés pour toute unité du camp, pas seulement les porteurs d'ATAK (D5, `fn_tocRoom.sqf` l. 170).
3. Demandes créées à la console : MEDEVAC, appui feu, ravitaillement (partie simple du n° 3 ; le serveur sait déjà
   les ranger, `reqAdd`).
4. Messages et réponses du poste sur les écrans et dans une fenêtre ACE (n° 5).
5. Escouades proposées : tous les groupes du camp (D11).
6. Caméra de casque sans téléphone, selon réglage (n° 8).
7. Contrôle des demandes par le serveur (n° 11).
8. Plafonds et états vides dits à l'écran (n° 37).
9. Météo, heure et éphémérides en panneau (n° 33).
10. Export de diagnostic du TOC (Z2).

## 6. Plan d'essais

### 6.1 Scénario « mission à zéro téléphone » (critère d'acceptation du lot 1)

Mission d'essai : réglage « objet obligatoire » actif, **aucun objet téléphone** dans les inventaires ni les caisses,
Athena coupé, COMSPEC Link absent du serveur. Une salle TOC moyenne posée par le module, deux groupes de joueurs, un
groupe d'IA, un véhicule, un hélicoptère, un drone.

- [ ] Le PC du poste s'ouvre par ACE et par la molette ; aucun message ne parle de téléphone ou de terminal.
- [ ] Carte du PC et écrans : tous les groupes, le véhicule, l'hélicoptère et le drone sont vus, à leur vraie
      position, aucun en « perdu » ni « hors ligne ».
- [ ] GROUPES, ORBAT, RÉSEAU : remplis ; l'ORBAT montre les groupes du jeu.
- [ ] Un joueur est blessé puis tué : l'état de la piste change, le tableau des pertes compte le mort.
- [ ] L'opérateur saisit un compte rendu de contact, une demande MEDEVAC et une demande de tir : ils apparaissent
      dans le flux, la main courante et les demandes, sur tous les écrans.
- [ ] L'opérateur émet un ordre à un groupe de joueurs : le chef le lit sur un écran ou par ACE et l'accuse ;
      l'accusé remonte à la console.
- [ ] L'opérateur envoie un message au camp : visible sans téléphone.
- [ ] Dessin d'un trait, d'un marqueur et d'un élément de plan à la console : vus sur le mur.
- [ ] Caméra du drone et caméra de casque d'un joueur sur un écran et en mosaïque.
- [ ] Satellite : pistes amies affichées.
- [ ] Chaque action ACE d'un écran donne un retour visible (console, pied d'écran).
- [ ] Journal du jeu : aucune erreur de script, aucune ligne « Pas de terminal ».
- [ ] Même mission avec un téléphone donné à un joueur : sa piste gagne batterie et signal, rien d'autre ne change.
- [ ] Redémarrage du serveur : salle, registres et saisies revenus.

### 6.2 Multijoueur (chaque lot)

| Cas | À vérifier |
|---|---|
| Serveur dédié, mod à jour | Pose Eden et Zeus, état identique pour deux joueurs, sauvegarde et reprise |
| Serveur dédié plus ancien ou sans le mod | Repli dit dans la console ; écrans allumés ; rien de bloqué |
| Partie hébergée par un joueur | L'hôte voit le même état que les autres ; sa piste n'est pas « perdue » |
| Arrivée en cours de partie | Écrans, salle, registres, verrous et droits reçus ; aucune action rejouée deux fois |
| Déconnexion et retour | Poste et satellites libérés puis rendus ; écran posé par le joueur gardé |
| Client headless | IA du headless vues, état correct ; coût serveur mesuré avec 100 et 300 IA |
| Deux camps | Un camp ne reçoit ni ne lit la salle de l'autre ; écran d'un camp noir pour l'autre |
| Deux opérateurs en même temps | Même ligne changée par les deux : dernière action gardée, les deux écrites au journal |
| Charge | Mur 4x3 en mosaïque, 12 écrans, 30 joueurs : images par seconde avant et après, trafic réseau |
| Changement de carte ou de mission | Sauvegarde séparée ; préréglage reposé ailleurs |
| Athena coupé, licence non acceptée | Aucune requête vers Athena ; tout le TOC en jeu fonctionne |

## 7. Risques principaux

1. **Coût serveur des pistes du jeu** avec plusieurs centaines d'IA : à traiter par tranches et à mesurer avant de
   livrer (tableau de santé).
2. **Conflits de fichiers** : trois branches en cours réécrivent `fn_tocLaptop.sqf`, `fn_tocScreen.sqf` et
   `fn_tocBoard.sqf`. Rien du lot 1 ne doit partir avant leur fusion, sauf les fichiers nouveaux.
3. **Réalisme contre confort** : voir toute la force sans téléphone change le jeu. Le réglage « Source des
   positions » doit être clair, avec son exemple, et son choix par défaut validé par TTRD.
4. **Droits trop stricts** : une salle que personne ne peut plus régler. Le Zeus et l'administrateur passent
   toujours ; sans réglage, tout le camp garde les droits d'aujourd'hui.
5. **Migration de la sauvegarde** : le passage au format versionné doit relire le format 2 actuel, avec une copie
   de secours avant la première écriture.
6. **COMSPEC Link et le terminal** (D16) : la mise en attente des boucles sans téléphone se règle côté Link ; à
   coordonner, sans toucher `fn_radioGuard`.
7. **Budget des caméras** : toute nouvelle source (casque, bascule automatique, rejeu) pèse sur le même plafond.

## 8. Décisions attendues de TTRD

- Valeur par défaut du réglage « Source des positions du TOC » : Jeu, Réseau ATAK ou Mixte.
- Caméra de casque sans téléphone : permise par défaut ou non.
- Droits : liste des rôles de poste et qui peut reconfigurer les écrans par défaut.
- Ordre des lots 2 et 3 si un seul peut partir après le lot 1.
