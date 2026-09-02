# Nouvelles features ATAK pour COMSPEC Overwatch

**Proposition d'enrichissement fonctionnel du mod Arma 3**

---

## Résumé

Ce document propose des features additionnelles pour le mod COMSPEC Overwatch, inspirées des capacités ATAK réelles et des besoins MILSIM avancés. Les features sont classées par priorité et difficulté d'implémentation.

---

## 1. Waypoints partagés et routes de patrouille

**Objectif** : Permettre au commandement de tracer des routes de patrouille sur la carte web, visibles en jeu par les opérateurs.

**Fonctionnalités** :
- Création de waypoints depuis l'interface ATAK web avec numéro de séquence
- Synchronisation bidirectionnelle : waypoints créés en jeu visibles sur le web et inversement
- Calcul automatique de distance et temps de déplacement estimé entre waypoints
- Affichage d'une ligne reliant les waypoints pour visualiser l'itinéraire
- Marquage de waypoints comme « atteints » avec horodatage automatique
- Export d'itinéraire pour réutilisation sur d'autres missions

**Cas d'usage** :
- Briefing pré-mission : le commandement trace l'itinéraire d'infiltration
- Mission en cours : ajustement dynamique de la route selon la menace
- Patrouille : suivi de progression le long d'un circuit défini
- Exfiltration : route pré-planifiée vers zone d'extraction

**Implémentation** :
- API : nouveaux endpoints `/api/atak/waypoints` pour CRUD
- SQF : polling périodique des waypoints, création de marqueurs locaux avec numéros
- Web : outil de dessin de route avec drag & drop des waypoints
- Base de données : table `atak_waypoints` avec `context_id`, `sequence`, `pos_x`, `pos_y`, `label`, `reached_at`

**Priorité** : Moyenne — Impact fort pour coordination, complexité modérée

---

## 2. Gestion de zones (LZ, DZ, objectives, danger zones)

**Objectif** : Permettre la création et synchronisation de zones géographiques tactiques avec affichage en jeu.

**Fonctionnalités** :
- Création de zones depuis le web : cercle, ellipse, rectangle, polygone libre
- Types de zones : Landing Zone, Drop Zone, Objective, Danger Zone, No-Go Area, Extract Point, Rally Point
- Propriétés : nom, type, couleur, rayon ou points de polygone, statut actif/inactif
- Alertes automatiques quand une unité entre dans une danger zone
- Calcul de distance minimale entre unité et zone la plus proche
- Zones temporaires avec activation et désactivation automatique selon chronologie mission

**Cas d'usage** :
- LZ de dépose hélicoptère définie avant insertion
- Zone d'objectif avec alerte quand atteint
- Danger zone artillerie avec alerte sonore à l'entrée
- No-Go Area pour éviter le fratricide avec forces alliées
- Rally Point de regroupement en cas de contact

**Implémentation** :
- Enrichissement de l'API `/api/atak/shapes` pour zones typées
- SQF : détection d'entrée/sortie de zone avec trigger local (`inArea`)
- Web : outils de dessin de zones avec palette de types
- Notification in-game quand franchissement de zone critique

**Priorité** : Haute — Fonctionnalité très demandée, déjà partiellement existante (shapes)

---

## 3. Système de rapports structurés (SPOTREP, SITREP, SALUTE)

**Objectif** : Formaliser la remontée d'information avec des formats militaires standardisés.

**Fonctionnalités** :
- Templates de rapports : SPOTREP (spot report), SITREP (situation report), SALUTE (size, activity, location, unit, time, equipment), CONTACT
- Formulaires assistés in-game avec champs pré-remplis (position actuelle, timestamp)
- Validation des champs obligatoires avant envoi
- Affichage des rapports sur la carte ATAK web avec icône différenciée selon type
- Historique de tous les rapports avec filtrage par type, unité émettrice, période
- Export des rapports pour AAR

**Cas d'usage** :
- Contact ennemi : rapport SALUTE immédiat avec effectifs estimés et armement observé
- SPOTREP pour signaler un élément d'intérêt tactique
- SITREP périodique de chaque section pour mise à jour commandement
- Rapport de tir d'artillerie pour validation BDA (Battle Damage Assessment)

**Implémentation** :
- Nouvelle interface tablette « Rapports » avec boutons par type de rapport
- API : endpoint `/api/atak/reports` avec type de rapport et champs structurés JSON
- Web : panneau dédié « Rapports » dans ATAK avec timeline et filtres
- Intégration au journal de liaison existant

**Priorité** : Haute — Structure la communication tactique, faible complexité technique

---

## 4. Suivi des véhicules et assets lourds

**Objectif** : Enrichir le BFT pour afficher distinctement les véhicules avec leurs caractéristiques.

**Fonctionnalités** :
- Détection automatique quand joueur monte dans véhicule
- Envoi des données véhicule : type, classe, carburant, munitions, passagers à bord
- Icônes ATAK différenciées : véhicule léger, blindé, hélico, avion, navire
- Couleur d'icône selon carburant : vert > 50%, orange 20-50%, rouge < 20%
- Badge indiquant nombre de passagers dans le véhicule
- Alerte automatique si véhicule immobilisé (fuel = 0 ou détruit)
- Historique de déplacement du véhicule pour tracer la route

**Cas d'usage** :
- Convoi logistique : suivi des camions et alertes carburant
- Insertion héliportée : tracking de l'hélico depuis le décollage jusqu'à la LZ
- Véhicules blindés : coordination des mouvements entre sections mécanisées
- Récupération de véhicule abandonné : localisation précise du dernier emplacement

**Implémentation** :
- Extension `fn_updatePosition` pour détecter `vehicle player != player`
- Payload enrichi avec `vehicle_type`, `vehicle_fuel`, `vehicle_ammo`, `passengers`
- Serveur : distinction unités/véhicules dans base de données
- Web : icônes véhicules et tooltip détaillé

**Priorité** : Moyenne — Améliore la conscience situationnelle, complexité modérée

---

## 5. Système de brevets et certifications tactiques

**Objectif** : Lier les capacités in-game aux certifications obtenues sur la plateforme Athena LMS.

**Fonctionnalités** :
- Synchronisation des certifications LMS vers le profil in-game
- Déblocage de fonctionnalités mod selon certification : appel CAS 9-Line réservé aux JTAC certifiés, triage médical réservé aux medics certifiés, accès radio tactique réservé aux RTO certifiés
- Badge de certification visible sur la carte ATAK pour identifier les spécialistes
- Restriction des interfaces selon rôle : dialogue CAS grisé si pas certifié JTAC
- Système d'override pour mission maker ou admin si besoin

**Cas d'usage** :
- Joueur non certifié JTAC tente d'appeler un CAS : message « Certification JTAC requise »
- Commandement identifie rapidement les medics disponibles via badge sur carte
- Évite les erreurs de procédure par des joueurs non formés
- Renforce l'intérêt de suivre les formations LMS

**Implémentation** :
- API : endpoint `/api/player/certifications` pour récupérer la liste des certifications joueur
- SQF : cache local des certifications après connexion Athena
- Conditions `if (hasPlayerCertification "JTAC")` avant ouverture des dialogues spécialisés
- Web : affichage des badges certification dans panneau effectifs

**Priorité** : Basse — Forte valeur pédagogique mais nécessite intégration LMS

---

## 6. Système de QRF (Quick Reaction Force) et demande d'appui

**Objectif** : Faciliter les demandes d'appui immédiat avec procédures formalisées.

**Fonctionnalités** :
- Bouton « Demande QRF » accessible rapidement depuis tablette
- Formulaire simplifié : nature de la menace, urgence, effectifs amis, effectifs ennemis estimés
- Transmission automatique de la position et du contexte au commandement
- Notification sonore et visuelle pour le commandement avec position sur carte
- Statut de la demande : en attente, validée, QRF en route, QRF sur zone, terminée
- Chronomètre affichant temps écoulé depuis la demande
- Historique des demandes QRF par mission

**Cas d'usage** :
- Section embusquée et débordée demande QRF immédiat
- Commandement valide et assigne une section en réserve pour intervenir
- Section QRF voit le waypoint automatique vers la zone de contact
- Coordination temps réel entre section en difficulté et QRF en approche

**Implémentation** :
- Nouveau dialogue « Demande QRF » dans tablette avec priorité haute
- API : endpoint `/api/atak/qrf` pour créer et gérer les demandes
- Web : notification push + marqueur rouge clignotant sur carte
- SQF : création automatique de waypoint pour section assignée QRF

**Priorité** : Moyenne — Améliore réactivité tactique, complexité modérée

---

## 7. Mode observateur avec contrôle caméra

**Objectif** : Permettre au commandement de prendre contrôle de caméras déployées ou de demander des vues spécifiques.

**Fonctionnalités** :
- Caméras fixes déployables par opérateurs (objet dans Arma)
- Vue caméra streamée vers ATAK web sous forme d'images périodiques
- Contrôle de l'orientation caméra depuis le web (pan/tilt si caméra motorisée)
- Demande de vue spécifique : commandement clique sur carte et demande à une unité de filmer cette zone
- Notification in-game pour le joueur demandé avec direction à filmer
- Enregistrement des captures caméra dans le journal intel

**Cas d'usage** :
- Caméra de surveillance déployée sur un axe d'approche
- Commandement suit en direct les mouvements suspects sur la zone
- Demande à un sniper de filmer une zone spécifique avant décision d'engagement
- Constitution de preuves visuelles pour AAR

**Implémentation** :
- Objet caméra déployable avec interaction ACE
- SQF : capture périodique `screenshot` avec position et orientation
- Extension : upload des captures vers serveur
- Web : affichage images caméra avec contrôles basiques

**Priorité** : Basse — Forte immersion mais complexité technique élevée

---

## 8. Système de chronologie et timeline mission

**Objectif** : Afficher une timeline interactive de tous les événements mission pour analyse temps réel et AAR.

**Fonctionnalités** :
- Timeline horizontale affichant tous les événements horodatés
- Types d'événements : contacts ennemis, rapports, ordres émis, alertes médicales, CAS, mouvements majeurs
- Filtrage par type d'événement et unité concernée
- Curseur déplaçable pour revenir à un instant T de la mission
- Export de la timeline en PDF pour AAR
- Mode replay : rejouer visuellement la mission en accéléré sur la carte

**Cas d'usage** :
- Commandement suit en temps réel la chronologie des événements clés
- Post-mission : analyse de la séquence de décisions pour RETEX
- Identification des moments critiques (premier contact, blessé, CAS)
- Formation : replay de mission exemplaire pour briefing

**Implémentation** :
- Backend : agrégation de tous les événements mission dans une API `/api/atak/timeline`
- Web : composant timeline JavaScript avec zoom temporel
- Mode replay : requête positions historiques pour animer la carte
- Export : génération PDF avec timeline et captures carte

**Priorité** : Moyenne — Très forte valeur AAR, complexité modérée

---

## 9. Intégration météo et environnement

**Objectif** : Afficher les conditions environnementales sur ATAK et leur impact sur les opérations.

**Fonctionnalités** :
- Récupération des conditions météo Arma : heure, couverture nuageuse, pluie, vent, visibilité
- Affichage sur ATAK : widget météo avec icônes
- Calcul d'impact sur les capacités : réduction portée observation, dégradation des communications, conditions de vol
- Alertes automatiques si conditions deviennent critiques (tempête, brouillard dense)
- Historique météo pour corrélation avec événements tactiques

**Cas d'usage** :
- Commandement décide de reporter l'exfiltration hélio à cause du vent
- Réduction de la portée de détection ennemie calculée selon visibilité
- Briefing pré-mission avec prévisions météo pour planification
- AAR : corrélation des difficultés avec les conditions météo

**Implémentation** :
- SQF : lecture des commandes météo Arma (`date`, `overcast`, `rain`, `wind`, `fogParams`)
- Payload enrichi envoyé périodiquement avec état météo
- Web : widget météo dans header ATAK
- Calculs d'impact : facteurs multiplicateurs selon conditions

**Priorité** : Basse — Enrichit le réalisme mais impact gameplay modéré

---

## 10. Système de contrôle d'artillerie et mortiers

**Objectif** : Gérer les demandes de tir indirect avec calcul balistique et visualisation impact.

**Fonctionnalités** :
- Formulaire de demande de tir : coordonnées cible, type munition, nombre de salves
- Calcul automatique des paramètres de tir : azimut, élévation, charge selon position batterie
- Validation par commandement avant exécution
- Visualisation sur carte : zone d'impact prévue (cercle de dispersion)
- Alerte danger zone pour unités amies trop proches
- Confirmation d'impact et BDA (Battle Damage Assessment)

**Cas d'usage** :
- Observateur demande tir de mortier sur position ennemie fortifiée
- Commandement valide après vérification de l'absence de forces amies dans la zone
- Équipe mortier reçoit les paramètres calculés pour tir
- Impact visualisé sur carte pour correction éventuelle

**Implémentation** :
- Formulaire dédié « Demande de tir indirect » dans tablette
- Calcul balistique SQF ou serveur selon position batterie et cible
- API : gestion des demandes de tir avec workflow validation
- Web : visualisation zone d'impact avec animation

**Priorité** : Moyenne — Feature très MILSIM, complexité élevée pour calculs

---

## 11. Système de blessés et évacuation médicale (MEDEVAC)

**Objectif** : Enrichir le système médical existant avec workflow complet d'évacuation.

**Fonctionnalités** :
- Classification des blessés : T1 (urgent chirurgical), T2 (urgent), T3 (différé), T4 (expectant)
- Formulaire MEDEVAC 9-Line : localisation, fréquence radio, nombre de blessés par catégorie, moyens de marquage LZ, sécurité zone
- Demande d'évacuation avec priorité calculée automatiquement selon triage
- Coordination avec assets aériens disponibles
- Suivi du blessé : position, état, soins administrés, temps avant dégradation
- Chronomètre « golden hour » pour blessés critiques
- Notification au médecin le plus proche

**Cas d'usage** :
- Blessé critique suite à explosion : triage T1, demande MEDEVAC immédiate
- Médecin stabilise en attendant l'hélico
- Commandement assigne hélico disponible vers LZ
- Pilote reçoit MEDEVAC 9-Line complet avec toutes les infos
- Suivi temps réel de l'évacuation jusqu'à l'hôpital de campagne

**Implémentation** :
- Extension système médical existant avec classification triage
- Dialogue MEDEVAC 9-Line pour medic et commandement
- API : gestion des demandes MEDEVAC avec workflow
- Web : panneau MEDEVAC avec liste des demandes et assignation assets

**Priorité** : Haute — Extension naturelle du système médical existant, forte demande MILSIM

---

## 12. Système IFF (Identification Friend or Foe) avancé

**Objectif** : Améliorer le système IFF existant avec interrogation active et validation.

**Fonctionnalités** :
- Mode IFF passif : affichage automatique statut ami/ennemi/inconnu sur carte
- Mode IFF actif : interrogation d'une unité avec réponse code du jour
- Changement du code IFF quotidien ou par mission
- Alerte automatique si unité inconnue approche des forces amies
- Historique des interrogations IFF pour traçabilité
- Mode silencieux : désactivation temporaire du transpondeur IFF

**Cas d'usage** :
- Patrouille détecte unité inconnue : interrogation IFF pour éviter tir fratricide
- Unité interrogée répond avec code correct : marquée comme amie
- Unité ne répond pas ou mauvais code : marquée comme hostile
- Infiltration : désactivation IFF pour éviter détection par systèmes ennemis simulés

**Implémentation** :
- Extension du système IFF existant (`receiveIFFChallenge`, `submitIFFResponse`)
- Génération code du jour par mission ou quotidien
- Détection proximité avec unités inconnues
- Web : interface interrogation IFF avec réponse en temps réel

**Priorité** : Moyenne — Améliore système existant, complexité modérée

---

## 13. Mode replay mission complète

**Objectif** : Permettre de rejouer intégralement une mission sur ATAK pour analyse approfondie.

**Fonctionnalités** :
- Enregistrement complet de toutes les positions unités à intervalle régulier
- Sauvegarde de tous les événements (rapports, ordres, alertes, tirs)
- Mode replay dans ATAK : lecture de la mission avec contrôles vidéo (play, pause, vitesse)
- Curseur temporel pour aller à un instant précis
- Affichage simultané des positions de toutes les unités à l'instant T
- Export vidéo de la mission pour formation
- Annotations post-mission sur le replay

**Cas d'usage** :
- AAR approfondi avec rejeu complet de la mission
- Identification de l'erreur tactique ayant conduit à l'échec
- Formation : analyse de mission exemplaire avec replay commenté
- Constitution d'archive vidéo des missions importantes

**Implémentation** :
- Backend : enregistrement périodique toutes positions + tous événements
- Table `mission_replay_snapshots` avec timestamp et state complet
- Web : lecteur vidéo avec contrôles + carte animée
- Export : génération MP4 avec overlay carte et événements

**Priorité** : Basse — Très forte valeur AAR mais complexité technique très élevée

---

## 14. Système de reconnaissance et UAV

**Objectif** : Intégrer les drones de reconnaissance dans le système C2.

**Fonctionnalités** :
- Détection automatique quand joueur contrôle un drone UAV
- Transmission du flux vidéo drone vers ATAK (captures périodiques)
- Marquage automatique des contacts détectés par le drone
- Contrôle basique du drone depuis ATAK : waypoints, altitude, retour base
- Autonomie batterie drone affichée
- Alerte quand drone détecté par ennemi (simulation)
- Historique des vols drone avec captures

**Cas d'usage** :
- Drone lancé pour reconnaissance zone avant infiltration
- Flux vidéo visible par commandement sur ATAK
- Détection véhicules ennemis : marquage automatique sur carte
- Guidage du drone depuis ATAK pour suivre colonne ennemie
- Alerte batterie faible : ordre retour base automatique

**Implémentation** :
- Détection contrôle UAV via `getConnectedUAV` Arma
- Capture écran et upload vers serveur
- API : contrôle basique UAV (waypoints) via commandes Arma
- Web : panneau UAV avec flux vidéo et contrôles

**Priorité** : Moyenne — Capacité très moderne mais complexité élevée

---

## 15. Système de points d'intérêt (POI) et intelligence

**Objectif** : Créer et gérer une base de données d'intelligence tactique géolocalisée.

**Fonctionnalités** :
- Création de POI sur carte : bâtiment, cache d'armes, PC ennemi, position défensive
- Catégorisation : ami, ennemi, neutre, inconnu
- Niveau de certitude : confirmé, probable, possible, à vérifier
- Horodatage et source de l'information (rapport, observation directe, SIGINT)
- Photos associées au POI
- Évolution du statut POI : actif → neutralisé → détruit
- Filtrage par catégorie et niveau de certitude
- Export de la carte d'intelligence pour briefing

**Cas d'usage** :
- Reconnaissance identifie position de tir ennemie : création POI ennemi confirmé
- Photo du POI uploadée automatiquement
- Briefing pré-mission : affichage de tous les POI connus sur zone
- Post frappe : mise à jour statut POI « PC ennemi » vers « détruit »
- Constitution progressive d'une carte d'intelligence tactique

**Implémentation** :
- Table `atak_poi` avec catégorie, statut, certitude, source
- Interface création POI sur web et tablette
- Affichage POI sur carte avec icônes différenciées
- Gestion du cycle de vie POI (actif → neutralisé → détruit)

**Priorité** : Haute — Structure l'intelligence tactique, complexité faible

---

## Priorisation et roadmap recommandée

### Phase 1 : Fondations coordination (4 semaines)
1. **Rapports structurés SPOTREP/SITREP** — Formalise communication
2. **Points d'intérêt et intelligence** — Structure l'intel
3. **Zones tactiques enrichies** — LZ, DZ, danger zones

### Phase 2 : Capacités spécialisées (6 semaines)
4. **MEDEVAC 9-Line complet** — Étend système médical
5. **QRF et demande d'appui** — Améliore réactivité
6. **Suivi véhicules et assets** — Enrichit BFT

### Phase 3 : Coordination avancée (6 semaines)
7. **Waypoints et routes partagées** — Planification tactique
8. **Timeline mission interactive** — AAR en temps réel
9. **Contrôle artillerie/mortiers** — Appuis indirects

### Phase 4 : Capacités avancées (8 semaines)
10. **Système UAV et reconnaissance** — Capacité moderne
11. **IFF avancé** — Sécurité forces amies
12. **Intégration météo** — Réalisme environnemental

### Phase 5 : Immersion totale (long terme)
13. **Mode replay complet** — AAR avancé
14. **Système certifications** — Intégration LMS
15. **Contrôle caméra et observation** — Surveillance avancée

---

## Conclusion

Ces 15 features transformeraient COMSPEC Overwatch en un système C2 complet couvrant toutes les dimensions des opérations MILSIM :

**Communication formalisée** : rapports structurés, ordres, demandes d'appui
**Intelligence tactique** : POI, SIGINT, reconnaissance UAV
**Coordination forces** : waypoints partagés, zones tactiques, QRF
**Appuis** : CAS (existant), artillerie, MEDEVAC
**Analyse et formation** : timeline, replay, AAR enrichi

La priorisation proposée équilibre **valeur opérationnelle immédiate** et **complexité technique** pour des livrables réguliers tout en construisant progressivement vers un système C2 professionnel complet.
