# COMSPEC ATAK : description longue (FR)

Version détaillée de la description Steam Workshop (objet 3684656708), état 2.9.34.
La description publiée est la version compacte bilingue `mod/COMSPEC_ATAK_Native/publisher/STEAM_DESCRIPTION.txt`
(limite Steam : 8 000 caractères), envoyée par `mod/COMSPEC_ATAK_Native/tools/steam_set_description.py`.
Ce texte sert pour le site, Discord ou une future page « Guide » Steam.

## Le mod

COMSPEC ATAK met un vrai smartphone tactique ATAK dans Arma 3. `Ctrl+U` le porte dans un coin de l'écran
(on continue à jouer, la carte suit), `Ctrl+Maj+U` le prend en main (souris, clavier, portrait ou paysage,
mini ou plein écran de 60 à 100 %). Plus de 60 apps, un réseau radio réaliste, des objets 3D et une liaison
en direct avec Athena (https://athena-comspec.eu), le portail web COMSPEC. Français, anglais et chinois
simplifié intégrés ; d'autres langues par des mods de traduction communautaires. Jouable sans Athena.

## Prérequis et compatibilités

- Obligatoire : CBA_A3 (clients et serveur). C'est la seule dépendance.
- Détectés automatiquement s'ils sont chargés :
  - ACE3 : médical (moniteur, triage, groupe sanguin sur les alertes), arsenal (Tenues Athena), menus d'interaction, explosifs, JVN, laser, météo.
  - ACRE2 ou TFAR : téléphone relié à la radio par la MPU5, voix relayée par le maillage (réglage serveur, désactivé par défaut), brouilleurs TFAR.
  - ZEN : modules COMSPEC dans les modules Zeus.
  - Live Presentation (FENRIR) : présentations lues dans l'app Briefing.
  - COMSPEC SSE : appareil photo, terminal SEEK, données SSE.
  - Brouilleurs de Crows Electronic Warfare et d'autres mods de guerre électronique.
- COMSPEC Overwatch connect est embarqué : ne pas charger l'ancien `@COMSPECOverwatch` en plus.
- COMSPEC Link (deux extensions Windows livrées avec le mod) : liaison Athena, musique, transfert Bluetooth, configuration locale, contrôle de version. Rien à installer.

## Les apps (lanceur, App Store)

- Navigation : Carte, GPS, Routes, Itinéraire, Relief, Météo, Distances, Garmin.
- Communication : Messages (canaux, SMS), Groupe, Alertes, BFT, Inter-team, C2, Wave Relay, Numéros, Discord.
- Appui et feux : CAS (appel de feu typé), JTAC, Drone ; app NINE LINE (brouillons CAS / MEDEVAC).
- Spécialités : Médical, Sniper, Brèche, Explosifs, GE, Anti-drone, Liaison allié.
- Renseignement : FRS / FRM, Reco, SALUTE, Photo, Galerie, Vidéo live, Marquer tracking, Extraction, SSE, OSINT (en chantier), Recherchés.
- Mission : Poste TOC, Tâches, Comptes rendus, CR fin d'op, Briefing, Academy, Plan, Logistique, Rejeu.
- Système : App Store, Athena, Admin, Réseau, Bluetooth, Synchro, Statut, Profil, Accessoires, Réglages, Temps d'écran, Debug, Crédits.
- Civil : Musique, Calculette, Bloc-notes, UberEats, Tinder (cache une chasse aux HVT avec des tuyaux d'informateurs).

L'encadrement peut imposer ou interdire des apps (App Store, réglages serveur).

## Carte et navigation

- Suivi des alliés (BFT) par l'ATAK, cercles de précision GPS, GPS réaliste (bâtiments, forêts, acquisition des satellites).
- Marqueurs nommés et modifiables (heure de pose, auteur, canal), diffusion par le seul réseau ATAK en option.
- Traits par les routes (plus rapide / plus court, grands axes ou voies discrètes, éviter les ponts), ligne de vue, champ de vision, recherche par grille, fonds Topo, Satellite et Hybride, capture photo HD de la carte.
- Bandeau du curseur : grille MGRS, altitude, distance, gisement et cap.
- App Routes : routes à pied, en véhicule, aériennes ou nautiques, étapes typées (checkpoint, ligne de phase, LZ…) avec heure H+ et consigne, guidage étape par étape (vibreur, montre Garmin), partage à l'équipe, au groupe ou au camp, affichage carte d'Arma, Athena et 3D.
- Carte web par QR code depuis la carte du téléphone.

## Réseau et radio

- Réseau qui suit le relief, les forêts, les bâtiments, la météo et la charge des relais ; brouilleurs (Zeus, modules, autres mods).
- MPU5 maillée reliée par câble EUD ou Wi-Fi, batterie de radio, multi-sauts par canal ; relais, pylônes 3D de 30, 40 et 50 m et monopoles de 12 et 20 m, destructibles ; passerelles de véhicule.
- Voix ACRE2 / TFAR relayée par le maillage jusqu'aux radios reliées des équipiers (option serveur).
- Hors réseau : messages, comptes rendus et routes partent au retour du signal. Bluetooth entre téléphones (photos, musiques, itinéraires, fiches SEEK), NFC dos à dos pour partager une caméra.

## Feux, drones, renseignement

- Appel de feu typé : mortiers 60, 81 et 120, obusier, lance-roquettes, naval ou CAS, avec portées, munitions, délais et distance de sécurité ; l'IA tire, un servant joueur reçoit la mission ; corrections + / – / G / D, EFFICACE, FIN DE MISSION.
- JTAC : terminal, frise CHECK-IN à BDA, 9-LINE (saisie ou complétion), carte tactique, cap magnétique, laser.
- Drones : flotte, manette de pilotage, ordres sur la carte, zones interdites, vue nacelle jour / nuit / thermique, marquage depuis le flux d'un drone du camp.
- Vidéo live : caméras IP, caméra d'un porteur d'ATAK, focale réglable, partage par relais, Bluetooth ou NFC.
- Photo et Galerie : grille MGRS et heure Zulu, flou des visages automatique (plusieurs styles).
- Extraction DL-7 : brancher le téléphone sur un ordinateur, un téléphone ou une clé, forcer le mot de passe, télécharger les fichiers posés par le créateur de mission.
- Un ATAK perdu peut être ramassé par l'IA ennemie et servir à traquer l'équipe ; alerte de compromission à valider.

## Poste TOC, écrans, IA

- PC portable de TOC : carte en grand, groupes du camp, flux des comptes rendus, rédacteur d'ordres, messages au camp (ROUTINE, URGENT, FLASH).
- Écrans TOC, écrans COMSPEC (trépied, double, TV, moniteur) et vidéoprojecteur : carte du TOC, diapositives (Google Slides compris), leçons Academy, caméras et drones en direct, PIP du PC du poste.
- Ordres aux IA alliées (clic droit sur la carte, app Ordres, PC du poste) : aller, patrouiller, attaquer, tenir, se replier, suivre, embarquer, posture ; IA équipées d'un ATAK visibles sur le BFT.

## Matériel

- 9 téléphones et tablettes : Samsung S7 (coyote, olive, noir ; défaut), Samsung S23, iPhone 17 Pro, V2 Enhanced, Xiaomi X3D, Sonim XP8, tablette durcie ; téléphone sur le gilet, ATAK sur le JPC, tablette de véhicule.
- Batterie, batterie externe, socle de recharge CS-10 (10 téléphones), antennes (courte, fouet, patch), filtre JVN, kit de réparation, câble DL-7, câble EUD.
- Montres Garmin (4 coloris + 3 Instinct) : carte, alliés, lecteur de musique, heure H, Jumpmaster, guidage.
- Infos tactiques dans les JVN, gestes, saleté et sang sur l'écran, mode furtif, verrouillage.

## Zeus et créateurs de mission

- Modules Eden / Zeus : contrôle Zeus (désactiver, verrouiller, rendre muet, brouiller, abîmer un ATAK), IA avec ATAK, brouilleur de zone, relais (condition « actif seulement si »), écran COMSPEC, écran TOC (et poste TOC), données à extraire, fichiers, profil Tinder HVT, capture de carte, relevé des routes et villes.
- App Admin : ATAK de chaque joueur, effets Zeus, parc ATAK de la communauté, debug de l'IA.
- CR de fin d'opération imposé dans le téléphone (onglet IMPOSER ou menu Zeus).
- COMSPEC Modules : un addon séparé peut ajouter ses apps et ses calques de carte (voir `MODULES.md`).

## Réglages serveur

Plus de 200 réglages CBA dans Options › Options des addons › COMSPEC, rangés par rubrique « ATAK · … » :
accès au téléphone, carte, géolocalisation, équipes et suivi, batterie et mémoire, radio de données (MPU5),
appui feu, routes, photo, médical, capture par l'ennemi, extraction de données, mode local et poste TOC
(mode full local sans Athena), apps retirées, imposées ou interdites, Garmin, Tinder, debug.

## Athena (athena-comspec.eu)

- Liaison du compte depuis l'app Athena : Steam, e-mail et mot de passe, code e-mail ou code d'appairage du portail.
- Ce qui remonte : positions, marqueurs, messages, ordres, alertes, comptes rendus, photos, plan de briefing, routes partagées, distances, heures de vol, temps de jeu, données Garmin, CR de fin d'op. La communauté choisit sur Athena ce que le jeu envoie (tout, ou seulement les comptes rendus).
- Ce qui descend : tenues de l'arsenal ACE, diapositives, cours Academy, dossiers médicaux de l'unité, musique du site, avis de l'administration.
- Sans Athena : mode full local (réglage serveur), ou réponse « Non » au service Athena ; ordres, messages du poste, 9-line et diapositives de mission fonctionnent en jeu.

## Installation et serveur dédié

1. S'abonner à CBA_A3 et à COMSPEC ATAK.
2. Charger les deux mods sur les clients et sur le serveur (`-mod=@CBA_A3;@COMSPEC_ATAK_Native`).
3. Serveur avec vérification des signatures : copier `keys\comspec_atak_native.bikey` dans le dossier `keys` du serveur.
4. Après une mise à jour, quitter Arma complètement.

## Support, crédits, licence

- Bug : double-cliquer `Envoyer_mes_logs.bat` dans le dossier du mod (zip sur le bureau, mots de passe masqués) et le poster sur le Discord COMSPEC (https://discord.gg/CCpJA6Nks). Menu Échap › Dépannage liaison pour diagnostiquer la liaison.
- Crédits : idées inspirées de Better CAS Environment (BCE, Aaren) et des outils carte de Simple Map Tools (POLPOX), sans code repris ; traduction chinoise RickLeo et MOYA ; polices Barlow Condensed, B612, Rajdhani, Share Tech (SIL OFL 1.1).
- Licence : © 2026 COMSPEC, licence COMSPEC (tous droits réservés) : usage personnel depuis la page officielle du Workshop ; copie, modification, ré-empaquetage et redistribution interdits sans autorisation écrite.
