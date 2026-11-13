# COMSPEC ATAK : description longue (FR)

Version détaillée de la description Steam Workshop (objet 3684656708), état 4.4.0 (salle d'opérations).
La description publiée est la version compacte bilingue `mod/COMSPEC_ATAK_Native/publisher/STEAM_DESCRIPTION.txt`
(limite Steam : 8 000 octets UTF-8, français et anglais compris), envoyée par `mod/COMSPEC_ATAK_Native/tools/steam_set_description.py`.
Ce texte sert pour le site, Discord ou une future page « Guide » Steam.

## Le mod

COMSPEC ATAK met un vrai smartphone tactique ATAK dans Arma 3. `Ctrl+U` le porte dans un coin de l'écran
(on continue à jouer, la carte suit), `Ctrl+Maj+U` le prend en main (souris, clavier, portrait ou paysage,
mini ou plein écran de 60 à 100 %). À l'horizontale, les apps s'affichent sur deux colonnes. Plus de 60 apps,
un réseau radio réaliste, des objets 3D, une vraie salle d'opérations et une liaison en direct avec
Athena (https://athena-comspec.eu), le portail web COMSPEC. Français, anglais et chinois simplifié intégrés ;
d'autres langues par des mods de traduction communautaires. Jouable sans Athena.

## Nouveautés récentes

- Salle d'opérations (TOC) : de vrais grands écrans 3D à taille réelle, faits pour le mod. Murs d'images de 4 écrans (2×2) et de 6 écrans (3×2), écran de 98 pouces au mur ou sur pied roulant, écran de 65 pouces suspendu au plafond, bandeau horloge LED et plaques de nom des éléments. Un mur montre une grande image, ou un contenu différent par écran.
- Vue satellite en direct, nord en haut, en jour, vision nocturne verte ou thermique. Chaque allié porteur d'un ATAK y est encadré avec son symbole et son indicatif (jamais une unité sans ATAK, jamais un ennemi). Deux satellites par camp, pilotés depuis le PC du poste ou le téléphone.
- PC du poste refait : carte, satellite, chronos, radio, main courante, liste d'exécution (EXCHECK), pertes, demandes des éléments et bouton FLASH.
- La salle entière est gardée en continu par le serveur et revient seule à la partie suivante.
- Drones armés : prenez la main depuis l'app Drone, mettez-le en orbite, marquez une cible et il tire seul ; le mode Rechercher et tuer propose les cibles qu'il repère.
- Carte lisible à tous les zooms, style tactique, vrais symboles OTAN (2525D) et silhouette des véhicules.

D'autres affichages pour la salle sont en préparation (par exemple une mosaïque des caméras ATAK, le pilotage de
tous les écrans d'une salle depuis un seul PC, une salle toute prête à poser dans Eden ou en Zeus) : ils seront
annoncés à leur sortie.

## Prérequis et compatibilités

- Obligatoire : CBA_A3 (clients et serveur). C'est la seule dépendance.
- Détectés automatiquement s'ils sont chargés :
  - ACE3 : médical (moniteur, triage, groupe sanguin sur les alertes), arsenal (Tenues Athena), menus d'interaction, explosifs, JVN, laser, météo, antennes à déployer.
  - ACRE2 ou TFAR : téléphone relié à la radio par la MPU5, voix relayée par le maillage (réglage serveur, désactivé par défaut), radios affichées au PC du poste, brouilleurs TFAR.
  - ZEN : modules COMSPEC dans les modules Zeus.
  - Live Presentation (FENRIR) : présentations lues dans l'app Briefing.
  - COMSPEC SSE : papiers d'identité et contrôle, apps Contrôle ID, Fichiers et Ma couverture, terminal SEEK II, données SSE.
  - Brouilleurs de Crows Electronic Warfare et d'autres mods de guerre électronique.
- COMSPEC Overwatch connect est embarqué : ne pas charger l'ancien `@COMSPECOverwatch` en plus.
- COMSPEC Link (deux extensions Windows livrées avec le mod) : liaison Athena, musique, transfert Bluetooth, configuration locale, contrôle de version. Rien à installer.

## Les apps (lanceur, App Store)

- Navigation : Carte, GPS, Routes, Itinéraire, Relief, Météo, Distances, Garmin.
- Communication : Messages (canaux), SMS, Groupe, Alertes, BFT, Inter-team, Wave Relay, Numéros, Discord.
- Appui et feux : CAS (appel de feu typé), JTAC, Drone (drones armés compris).
- Spécialités : Médical, Sniper, Explosifs, GE, Anti-drone, Liaison allié.
- Renseignement : FRS / FRM, Reco, SALUTE, Photo, Galerie, Vidéo live, Marquer tracking, Extraction, SSE, Recherchés.
- Mission : Poste TOC, Tâches, Comptes rendus, CR fin d'op, Briefing, Academy, Plan, Logistique, Rejeu, Communauté.
- Système : App Store, Athena, Admin, Réseau, Bluetooth, Synchro, Statut, Profil, Accessoires, Réglages, Temps d'écran, Debug, Crédits.
- Civil : Musique, Calculette, Bloc-notes, UberEats, Bilan de l'année, Vidéo YouTube (si l'administrateur l'active), Tinder (cache une chasse aux HVT avec des tuyaux d'informateurs).

L'encadrement peut imposer ou interdire des apps (App Store, réglages serveur). Le mode pilote et les apps
regroupées sont des réglages d'addon, désactivés par défaut.

## Carte et navigation

- Suivi des alliés (BFT) par l'ATAK, cercles de précision GPS, GPS réaliste (bâtiments, forêts, acquisition des satellites).
- Style tactique : pions à anneau, plaques de nom, contacts reconnaissables à leur forme (losange ennemi, carré neutre, cercle ami), position ancienne estompée avec son âge. Icônes 2525D au choix. Noms lisibles à tous les zooms, sans chevauchement.
- Une unité à bord d'un engin apparaît avec la silhouette de l'avion, de l'hélicoptère, du véhicule ou du navire, et l'équipage n'est compté qu'une fois.
- Marqueurs nommés et modifiables (heure de pose, auteur, canal), diffusion par le seul réseau ATAK en option ; maintenir le clic gauche pointe un endroit pour tout le camp.
- Traits par les routes (plus rapide / plus court, grands axes ou voies discrètes, éviter les ponts), ligne de vue, champ de vision, recherche par grille, fonds Topo, Satellite, Hybride et ARDOISE, capture photo HD de la carte.
- Bandeau du curseur : grille MGRS, altitude, distance, gisement et cap.
- App Zones (battleplan) : découpage, axes nommés, état des zones.
- App Routes : routes à pied, en véhicule, aériennes ou nautiques, étapes typées (checkpoint, ligne de phase, LZ…) avec heure H+ et consigne, guidage étape par étape (vibreur, montre Garmin), partage à l'équipe, au groupe ou au camp, affichage carte d'Arma, Athena et 3D.
- Carte web par QR code depuis la carte du téléphone.

## Réseau et radio

- Réseau qui suit le relief, les forêts, les bâtiments, la météo et la charge des relais ; brouilleurs (Zeus, modules, autres mods). Réalisme activé par défaut, réglable dans Échap › Réalisme.
- Hors maillage, le téléphone nomme le lien qui manque et sa cause (relief, distance, bâtiments) avec un conseil, et la carte le montre en tirets rouges.
- MPU5 maillée reliée par câble EUD ou Wi-Fi, batterie de radio, multi-sauts par canal ; relais, pylônes 3D et monopoles destructibles, mini-antennes de bâtiment, passerelles de véhicule ; antenne satellite et antenne sur mât à déployer par ACE.
- Voix ACRE2 / TFAR relayée par le maillage jusqu'aux radios reliées des équipiers (option serveur). App Réseau › DÉPANNAGE MPU : l'assistant trouve le maillon cassé et propose la correction.
- Hors réseau : messages, SMS, comptes rendus et routes partent au retour du signal. Bluetooth entre téléphones (photos, musiques, itinéraires, fiches SEEK), NFC dos à dos pour partager une caméra.

## Feux, drones, renseignement

- Appel de feu typé : mortiers 60, 81 et 120, obusier, lance-roquettes, naval ou CAS, avec portées, munitions, délais et distance de sécurité ; choix de l'unité à appeler ; l'IA tire, un servant joueur reçoit la mission ; corrections + / – / G / D, EFFICACE, FIN DE MISSION.
- JTAC : terminal, frise CHECK-IN à BDA, 9-LINE (saisie ou complétion), 5-LINE, briefs envoyés aux pilotes, carte tactique, cap magnétique, laser.
- Drones : flotte, manette de pilotage, ordres sur la carte, zones interdites, vue nacelle jour / nuit / thermique, marquage depuis le flux d'un drone du camp. Drones armés : orbite, cible marquée, tir sous vos yeux, mode Rechercher et tuer.
- Vidéo live : caméras IP, caméra d'un porteur d'ATAK, focale réglable, partage par relais, Bluetooth ou NFC.
- Photo et Galerie : grille MGRS et heure Zulu, flou des visages automatique (plusieurs styles).
- Extraction DL-7 : brancher le téléphone sur un ordinateur, un téléphone ou une clé, forcer le mot de passe, télécharger les fichiers posés par le créateur de mission.
- Un ATAK perdu peut être ramassé par l'IA ennemie et servir à traquer l'équipe ; alerte de compromission à valider.

## Poste TOC et salle d'opérations

- PC du poste : carte du poste (COP) en grand, onglets SAT, chronos, radio (vos radios ACRE2 ou TFAR, voyants RX / TX), diffusion sur les écrans, main courante en heure ZULU, liste d'exécution (EXCHECK), pertes, demandes des éléments (MEDEVAC, appels de feu, ravitaillements : la réponse part sur le téléphone du demandeur), groupes du camp, flux des comptes rendus, rédacteur d'ordres, messages au camp (ROUTINE, URGENT, FLASH). PRENDRE LE POSTE inscrit l'opérateur ; FLASH affiche un message en rouge sur tous les écrans du poste pendant 3 minutes.
- App Poste TOC sur le téléphone, à côté d'un PC du poste : messages au camp, ordres, groupes, satellites, caméras sur les écrans.
- Écrans : écrans TOC, écrans COMSPEC (trépied, double, TV, moniteur), vidéoprojecteur, et les grands écrans de la salle d'opérations (murs de 4 et 6 écrans, 98 pouces au mur ou sur pied roulant, 65 pouces au plafond, bandeau horloge, plaques de nom). Chaque écran, ou chaque dalle d'un mur, garde son affichage : carte et flux, ORBAT, caméra en direct, diapositives (Google Slides compris), leçons Academy, PIP du PC, satellite, horloge ZULU et locale avec les chronos, écran d'escouade (un groupe, ses membres et leur état, son dernier compte rendu), main courante, pertes, demandes.
- Satellites : deux par camp, un seul opérateur à la fois (les autres voient « Lecture seule ») ; vue de 80 m à 5 km, jour, nuit ou thermique, suivi d'un groupe ou d'une unité tel que le réseau ATAK le connaît.
- Chronos : chrono, compte à rebours, heure H (H- puis H+), communs à tous les PC du camp et affichés sur les écrans horloge.
- Sauvegarde : écrans (place, nom, affichage), chronos, satellites, main courante, liste d'exécution, demandes et pertes sont gardés en continu et remis à la partie suivante sur la même carte et la même mission (réglage serveur, activé par défaut).
- Ordres aux IA alliées (clic droit sur la carte, app Tâches, PC du poste) : aller, patrouiller, attaquer, tenir, se replier, suivre, embarquer, posture, occuper un bâtiment, règle de tir ; « Ensuite… » met un ordre en file ; le groupe signale lui-même ses contacts. IA équipées d'un ATAK visibles sur le BFT.

## Matériel

- 9 téléphones et tablettes : Samsung S7 (coyote, olive, noir ; défaut), Samsung S23, iPhone 17 Pro, V2 Enhanced, Xiaomi X3D, Sonim XP8, tablette durcie ; téléphone sur le gilet, ATAK sur le JPC, tablette de véhicule.
- Téléphone personnel : chaque téléphone a un propriétaire, code de 4 à 8 chiffres avec indice, écran de verrouillage, fonds d'écran, trois thèmes (SOMBRE, JOUR, NUIT JVN), centre de notifications par app.
- GPS de poignet : objet autonome sans téléphone (position, boussole, points et routes sur un écran monochrome à boutons).
- Batterie (plus faible par grand froid), batterie externe, socle de recharge CS-10 (10 téléphones), antennes (courte, fouet, patch), filtre JVN, kit de réparation, câble DL-7, câble EUD.
- Montres Garmin (4 coloris + 3 Instinct) : carte, alliés, lecteur de musique, heure H, Jumpmaster, guidage.
- Infos tactiques dans les JVN, gestes, saleté, fêlures et sang sur l'écran, mode furtif.

## Zeus et créateurs de mission

- Modules Eden / Zeus rangés en catégories : contrôle Zeus (désactiver, verrouiller, rendre muet, brouiller, abîmer un ATAK), IA avec ATAK, brouilleur de zone, relais (condition « actif seulement si »), écran COMSPEC, écran TOC (affichage, satellite et escouade montrés, dalles d'un mur, liste d'exécution préparée), données à extraire, fichiers, profil Tinder HVT, capture de carte, relevé des routes et villes. Fenêtre de configuration des objets.
- Centre Zeus : état des téléphones de tous les joueurs (batterie, liaison Athena, réseau, drones, alertes), onglet SERVEUR (état de la partie) et étiquettes au-dessus des joueurs qui signalent ce qui ne va pas.
- App Admin : ATAK de chaque joueur, effets Zeus, parc ATAK de la communauté, debug de l'IA.
- CR de fin d'opération imposé dans le téléphone (onglet IMPOSER ou menu Zeus).
- COMSPEC Modules : un addon séparé peut ajouter ses apps et ses calques de carte (voir `MODULES.md`).

## Réglages serveur

Plus de 200 réglages CBA dans Options › Options des addons › COMSPEC, rangés par rubrique « ATAK · … » :
accès au téléphone, carte, géolocalisation, équipes et suivi, batterie et mémoire, radio de données (MPU5),
appui feu, drones armés, routes, photo, médical, capture par l'ennemi, extraction de données, mode local et poste TOC
(mode full local sans Athena, écrans TOC gardés), apps retirées, imposées ou interdites, Garmin, Tinder, debug.
Les réglages de réalisme se règlent aussi dans Échap › Réalisme, chacun expliqué avec un exemple.

## Athena (athena-comspec.eu)

- Liaison du compte depuis l'app Athena : Steam, e-mail et mot de passe, code e-mail ou code d'appairage du portail.
- Ce qui remonte : positions, marqueurs, messages, ordres, alertes, comptes rendus, photos, plan de briefing, routes partagées, distances, heures de vol, temps de jeu, données Garmin, CR de fin d'op. La communauté choisit sur Athena ce que le jeu envoie (tout, ou seulement les comptes rendus).
- Ce qui descend : tenues de l'arsenal ACE, diapositives, cours Academy, dossiers médicaux de l'unité, musique du site, consignes et kit obligatoire de la communauté (app Communauté), identité et papiers (avec COMSPEC SSE), avis de l'administration.
- SUR MON TÉLÉPHONE : un QR code ouvre vos comptes rendus et la carte sur votre vrai téléphone.
- Sans Athena : mode full local (réglage serveur), ou réponse « Non » au service Athena ; ordres, messages du poste, salle TOC, 9-line et diapositives de mission fonctionnent en jeu.

## Installation et serveur dédié

1. S'abonner à CBA_A3 et à COMSPEC ATAK.
2. Charger les deux mods sur les clients et sur le serveur (`-mod=@CBA_A3;@COMSPEC_ATAK_Native`).
3. Serveur avec vérification des signatures : copier `keys\comspec_atak_native.bikey` dans le dossier `keys` du serveur.
4. Après une mise à jour, quitter Arma complètement.

## Support, crédits, licence

- Bug : double-cliquer `Envoyer_mes_logs.bat` dans le dossier du mod (zip sur le bureau, mots de passe masqués) et le poster sur le Discord COMSPEC (https://discord.gg/CCpJA6Nks). Menu Échap › Dépannage liaison pour diagnostiquer la liaison.
- Crédits : idées inspirées de Better CAS Environment (BCE, Aaren) et des outils carte de Simple Map Tools (POLPOX), sans code repris ; traduction chinoise RickLeo et MOYA ; polices Barlow Condensed, B612, Rajdhani, Share Tech (SIL OFL 1.1).
- Licence : © 2026 COMSPEC, licence COMSPEC (tous droits réservés) : usage personnel depuis la page officielle du Workshop ; copie, modification, ré-empaquetage et redistribution interdits sans autorisation écrite.
