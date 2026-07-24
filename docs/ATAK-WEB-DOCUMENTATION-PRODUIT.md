# COMSPEC ATAK Web — Documentation produit

**Version** : 1.0  
**Date** : 24 juillet 2026  
**Public** : Utilisateurs, administrateurs, responsables technique  
**Style** : Documentation produit, guide opérationnel

---

## 1. Présentation du produit

### 1.1 Qu'est-ce que COMSPEC ATAK Web ?

**COMSPEC ATAK Web** est l'**interface de carte tactique temps réel** accessible via navigateur. C'est le cœur du système de commandement et contrôle (C2) de COMSPEC, permettant de visualiser la situation opérationnelle, coordonner les actions, et maintenir la liaison avec le terrain (Arma 3).

**Accès** : `/atak` (connexion requise)

### 1.2 Utilisateurs types

| Rôle | Usage principal |
|------|-----------------|
| **Commandement / Overwatch** | Suivi situation depuis PC de commandement, coordination générale |
| **JTAC / Contrôleur aérien** | Gestion CAS 9-Line, codes laser, coordination appuis |
| **Pilote** | Déclaration Flight Manifest, réception missions CAS |
| **Médecin / Medic** | Triage alertes médicales, coordination évacuations |
| **Opérateur terrain** | Consultation carte (écran secondaire), réception ordres |
| **Administrateur** | Configuration serveur, carte, mod Arma |

### 1.3 Objectifs opérationnels

- **Conscience situationnelle** : Visualisation positions temps réel, marqueurs, zones
- **Coordination** : Tchat, pings, ordres, CAS, alertes médicales
- **Renseignement** : Flux photos terrain, rapports intel
- **Traçabilité** : Journal liaison, historique événements

---

## 2. Architecture de l'interface

### 2.1 Vue d'ensemble

L'interface suit un layout **3 colonnes** :

```
┌──────────────────────────────────────────────────┐
│ En-tête (logo, réseau, heure, sélecteurs)        │
├─────────┬────────────────────────┬────────────────┤
│ Panneau │                        │  Panneau       │
│ gauche  │  Carte centrale        │  droit         │
│ (9      │  (Leaflet tactique)    │  (Effectifs    │
│ onglets)│                        │   + Air)       │
│         │                        │                │
│         ├────────────────────────┤                │
│         │ Tiroir effectifs       │                │
└─────────┴────────────────────────┴────────────────┘
```

### 2.2 En-tête (Header)

| Élément | Fonction |
|---------|----------|
| **Logo ATHENA ATAK** | Identité, retour accueil |
| **Pastille état réseau** | « Réseau actif » (vert) / « Hors ligne » (rouge) |
| **Heure Zulu** | UTC en temps réel (HH:MM:SS Z) |
| **Liaison** | Dernière activité théâtre (ex: "il y a 12 s") |
| **Sélecteur serveur/mission** | Changement contexte opérationnel (si plusieurs workspaces) |
| **Sélecteur carte** | Choix théâtre (Altis, Tanoa, etc.) |
| **Boutons** | Connexion en jeu, Lier téléphone, Configuration, État, Compte |

### 2.3 Barre de métriques (OS Strip)

Indicateurs de qualité liaison :

| Métrique | Description | Seuils |
|----------|-------------|--------|
| **Qualité** | Perception globale liaison | Bonne (< 120ms), Acceptable (< 350ms), Dégradée (> 350ms) |
| **Latence** | Délai réseau mesuré (ms) | Vert < 120, Orange < 350, Rouge > 350 |
| **Pertes de paquets** | (À venir) | — |
| **Théâtre** | Dernière activité depuis Arma | Vert si < 60s, Orange sinon |

### 2.4 Panneau gauche — 9 onglets

| Onglet | Contenu | Badge |
|--------|---------|-------|
| **Cams** | Flux photos intel géolocalisées | — |
| **Marqueurs** | Liste marqueurs tactiques | — |
| **Tchat** | Journal radio partagé | — |
| **Ordres** | Émission + réception ordres | Compteur non lus |
| **Assistances** | Alertes médicales actives | Compteur critiques |
| **Radio** | Proximité radio (TFAR/ACRE) | Compteur émissions |
| **Pings** | Alertes rapides géolocalisées | — |
| **JTAC** | 9-Line CAS + codes laser | — |
| **Liaison** | Journal activité + présence web | — |

### 2.5 Carte centrale (Leaflet)

**Caractéristiques** :
- Fond de carte théâtre Arma (tuiles personnalisées)
- Coordonnées alignées sur monde Arma (mètres)
- Zoom/pan, échelle métrique
- HUD coin bas : grille souris, état réseau

**Couches cartographiques** :
1. Unités (marqueurs joueurs temps réel)
2. Marqueurs tactiques (MIL-STD-2525 / APP-6)
3. Photos intel géolocalisées
4. Zones de danger
5. Formes tactiques (polygones, cercles, lignes)
6. Pings temporaires
7. Air assets (aéronefs)

### 2.6 Panneau droit

| Section | Contenu |
|---------|---------|
| **Air Support Assets** | Liste aéronefs déclarés (Flight Manifest) avec indicatif pilote, type, statut |
| **Effectifs** | Liste unités connectées, filtres (En liaison / Tous), recherche par indicatif/rôle |

### 2.7 Tiroir effectifs (tableau)

Tableau dépliable affichant :

| Colonne | Description |
|---------|-------------|
| **Indicatif** | Callsign joueur |
| **Rôle** | Spécialité (Rifleman, Medic, JTAC, etc.) |
| **Liaison** | Temps depuis dernière mise à jour (ex: "il y a 8 s") |
| **Cap** | Direction en degrés (0–359°) |
| **Grille** | Coordonnées X Y (ex: "15420 12850") |

---

## 3. Fonctionnalités détaillées

### 3.1 Carte tactique

#### 3.1.1 Visualisation

| Fonctionnalité | Description |
|----------------|-------------|
| **Multi-théâtres** | Altis, Tanoa, cartes personnalisées (sélecteur en-tête) |
| **Coordonnées Arma** | Système aligné sur monde jeu (mètres cartésiens) |
| **Tuiles** | Chargement progressif, gestion d'erreur (fallback transparent) |
| **Échelle** | Échelle métrique dynamique (coin bas gauche) |
| **HUD grille** | Position souris affichée en temps réel |

#### 3.1.2 Interactions

| Action | Résultat |
|--------|----------|
| **Clic gauche sur unité** | Popup détails : indicatif, rôle, position, grille, actions (centrer, ping, ordre) |
| **Clic droit** | Menu contextuel : Ping, Marqueur, Ordre, Zoom |
| **Zoom molette** | Zoom in/out |
| **Drag** | Panoramique |
| **Double-clic** | Zoom avant centré |

### 3.2 Unités et effectifs

#### 3.2.1 Liste (panneau droit)

| Fonctionnalité | Description |
|----------------|-------------|
| **Temps réel** | Mise à jour toutes les 3 secondes |
| **Modes** | « En liaison » (actifs) / « Tous » (historique) |
| **Filtrage** | Recherche texte par indicatif ou rôle |
| **Tri** | Alphabétique par indicatif |
| **Indicateurs** | Pastille verte (actif < 30s), grise (inactif) |

#### 3.2.2 Affichage carte

- Symboles militaires avec indicatif
- Flèche orientation selon cap
- Couleurs : Bleu (ami), rouge (ennemi), jaune (neutre)
- Popup clic : Détails complets + actions rapides

### 3.3 Communications

#### 3.3.1 Tchat (Journal radio)

| Fonctionnalité | Description |
|----------------|-------------|
| **Canal** | SQUAD (partagé toute l'équipe) |
| **Envoi** | Champ saisie + Enter ou bouton Envoyer |
| **Affichage** | Messages horodatés avec auteur |
| **Vider** | Bouton pour nettoyer affichage local (historique serveur conservé) |
| **Synchronisation** | Bidirectionnelle web ↔ Arma (via mod) |

#### 3.3.2 Pings

| Fonctionnalité | Description |
|----------------|-------------|
| **Création** | Clic droit carte → « Envoyer un ping » + message |
| **Affichage carte** | Marqueur + cercle pulsant |
| **Liste** | Onglet Pings avec position, message, auteur, timestamp |
| **Durée de vie** | 30 minutes (configurable) |

### 3.4 Ordres tactiques

#### 3.4.1 Émission

| Champ | Options / Format |
|-------|------------------|
| **Type** | Se déplacer, Tenir position, Reconnaissance, Appui aérien, Force de réaction |
| **Priorité** | Routine, Important, Urgent, Contact |
| **Destinataire** | Toute l'équipe, Utilisateur spécifique, Groupe, Fire team, Canal, ATAK Solo |
| **Précisions** | Texte libre (max 400 caractères) |
| **Options** | ☑ Conditions radio réalistes (délai, brouillage fictif) |

#### 3.4.2 Réception et suivi

- Liste tous ordres émis et reçus
- Statuts : En attente, Reçu, En cours, Terminé, Annulé
- Actions : Confirmer réception, Marquer terminé, Annuler
- Badge sur onglet : Compteur ordres non lus

### 3.5 JTAC et appui aérien

#### 3.5.1 CAS 9-Line

| Ligne | Contenu |
|-------|---------|
| **1** | Type (IP/FFP/CAS/…) |
| **2** | Position cible (grille ou coordonnées) |
| **3** | Élévation (mètres ASL) |
| **4** | Description cible (infanterie, véhicule, bunker…) |
| **5** | Marqueur (fumée, laser, panneau, aucun) |
| **6** | Position amis/ennemis (cardinal + distance) |
| **7** | Retrait (heading + distance sécurité) |
| **8** | Autres informations (météo, dangers…) |
| **9** | Remarques (DANGER CLOSE, ROE…) |

**Workflow** :
1. Bouton « Nouvelle 9-Line CAS »
2. Formulaire 9 champs
3. Validation et envoi
4. Affichage liste avec statut (En attente, Accepté, En cours, Terminé)
5. Pilotes accusent réception depuis Arma

#### 3.5.2 Codes laser

- Génération automatique codes désignateur (1111–1888)
- Attribution aux demandes CAS
- Liste codes actifs
- Synchronisation avec désignateurs en jeu

#### 3.5.3 Air Support Assets

| Information | Description |
|-------------|-------------|
| **Type aéronef** | Identifié auto depuis Arma (A-10C, AH-64, etc.) |
| **Indicatif pilote** | Callsign |
| **Statut** | En vol, En attente, Engagé |
| **Liaison** | Dernière mise à jour |

**Déclaration** : Pilotes déclarent depuis menu Arma (touche K → Flight Manifest).

### 3.6 Médical et assistances

#### 3.6.1 Alertes médicales

| Fonctionnalité | Description |
|----------------|-------------|
| **Déclenchement** | Automatique si joueur inconscient ou mort (ACE Medical) |
| **Contenu** | Indicatif, position, type blessure, rythme cardiaque |
| **Priorité** | Critique (rythme 0), Urgent, Routine |
| **Affichage** | Liste onglet Assistances + bannière top si critique |
| **Durée** | Disparition auto après 30 min |

#### 3.6.2 Triage (réservé Medic/Chef)

| Statut | Description |
|--------|-------------|
| **En attente** | Alerte active, non traitée |
| **En cours** | Médecin sur place |
| **Traité** | Blessé stabilisé |
| **KIA** | Killed In Action |
| **Annulé** | Fausse alerte ou résolu autrement |

**Actions** : Masquer, Changer statut (selon permissions)

### 3.7 Radio et proximité (TFAR/ACRE)

| Fonctionnalité | Description |
|----------------|-------------|
| **Opérateur référence** | Choix manuel ou automatique (opérateur le plus actif) |
| **Rayon** | 50–200 mètres (configurable) |
| **Détection** | Qui émet près de l'opérateur référence |
| **Réseau** | Canal radio surveillé |
| **Options** | ☑ Émissions uniquement, ☑ Masquer si aucun module |
| **Affichage** | Liste + pastilles clignotantes sur carte |
| **Badge** | Compteur d'émissions actives |

### 3.8 Marqueurs tactiques

#### 3.8.1 Création

| Méthode | Procédure |
|---------|-----------|
| **Clic droit carte** | « Placer un marqueur » → Sélecteur symbole + texte |
| **Menu unité** | Clic unité → « Marquer position » |

#### 3.8.2 Symbologie

| Standard | Support |
|----------|---------|
| **MIL-STD-2525** | ✅ Complet (via milsymbol.js) |
| **APP-6** | ✅ Complet |
| **SIDC** | Sélecteur par code ou catalogue visuel |

**Catalogue** : Plus de 100 symboles (infanterie, véhicules, installations, activités).

#### 3.8.3 Gestion

- **Éditer** : Clic marqueur → Modifier texte/symbole
- **Supprimer** : Clic marqueur → Supprimer
- **Synchronisation** : Bidirectionnelle avec Arma (créations visibles en jeu et vice versa)

### 3.9 Photos et reconnaissance

#### 3.9.1 Flux Cams

- Réception photos envoyées depuis Arma (captures type CTAB)
- Grille miniatures avec timestamp
- Géolocalisation automatique (position joueur au moment capture)
- Zoom : Clic pour agrandissement
- Visible par tous opérateurs du théâtre

#### 3.9.2 Intel photos sur carte

- Marqueur icône caméra à position de prise de vue
- Popup : Aperçu miniature + bouton « Voir » (ouvre grande taille)
- Couche activable/désactivable

### 3.10 Formes et zones

#### 3.10.1 Types

- **Polygone** : Zone irrégulière (ex: secteur, AO)
- **Cercle** : Rayon depuis point central (ex: blast radius)
- **Ligne** : Axe, frontière, route
- **Rectangle** : Zone rectangulaire

#### 3.10.2 Zones de danger

| Fonctionnalité | Description |
|----------------|-------------|
| **Création** | Depuis Arma ou web (admin) |
| **Alerte entrée** | Notification automatique si joueur entre dans zone |
| **Visualisation** | Zone rouge translucide sur carte |
| **Gestion** | Modification rayon/position, suppression |

**Cas d'usage** : No-go zones, zones contaminées, secteurs ennemis.

---

## 4. Configuration et administration

### 4.1 Panneau Compte

| Section | Contenu |
|---------|---------|
| **Connexion en jeu** | Génération code liaison Arma (30 min, usage unique) |
| **Connexion téléphone** | Génération QR code pour mobile |
| **Compte** | Email, nom affiché, indicatif |
| **Liaison Steam** | Identifiant Steam pour corrélation automatique |
| **Indicatif Arma** | Callsign utilisé en jeu (doit correspondre) |
| **Serveur** | Adresse serveur Arma + port (info) |
| **Préférences son** | Volume alertes (0–100%), Choix son (Stalker, Santé, Silence) |

### 4.2 Panneau Configuration

| Information | Description |
|-------------|-------------|
| **Adresse liaison** | URL à saisir dans mod Arma (Paramètres → Addons → COMSPEC Overwatch) |
| **Bouton Copier** | Copie dans presse-papiers |
| **Adresse réseau visiteur** | IP publique (diagnostic) |
| **Pack Overwatch** | Lien téléchargement mod (si déposé par admin) |
| **Serveur Arma** | Host:port |
| **Identifiants mod** | Texte libre config (clé API, etc.) |
| **Instructions** | Procédures équipe |
| **Liens** | Assistant mod (`/atak/setup`), Guide complet (`/atak/tuto`) |

### 4.3 Panneau État de santé

| Vérification | Indicateurs |
|--------------|-------------|
| **Liaison carte** | URL + statut (Opérationnel / Indisponible / Délai dépassé) |
| **Mises à jour** | État connexion temps réel (Actives / API PHP polling) |
| **Données** | Disponibilité base de données (Disponibles / Indisponibles) |
| **Mod en jeu** | Dernière activité Arma (ex: "il y a 12 s" ou "Jamais") |
| **Contacts actifs** | Nombre + liste indicatifs |
| **Tchat** | Incidents éventuels (Aucun incident / Erreur API) |
| **Pings** | Incidents éventuels |

**Bouton Actualiser** : Rafraîchir manuellement tous les indicateurs.

---

## 5. Gestion multi-contextes

### 5.1 Workspaces (serveurs/missions)

| Fonctionnalité | Description |
|----------------|-------------|
| **Sélecteur** | Dropdown liste des contextes (ex: "Serveur Principal Altis", "Serveur Training Tanoa") |
| **Isolation données** | Chaque workspace a ses propres unités, marqueurs, messages, ordres |
| **Changement à la volée** | Pas besoin de recharger la page, rafraîchissement auto |
| **Configuration** | Définis par admin (`/admin/atak-config`) |

**Cas d'usage** : Séparer serveur principal vs serveur entraînement, ou plusieurs missions simultanées.

### 5.2 Cartes multiples

| Fonctionnalité | Description |
|----------------|-------------|
| **Sélecteur** | Dropdown liste théâtres (Altis, Tanoa, etc.) |
| **Mémorisation** | Dernier choix sauvegardé en localStorage |
| **Configuration** | Tuiles, centre, zoom par carte (définis par admin) |

**Cas d'usage** : Même workspace, cartes différentes (ex: mission commence sur Altis, se poursuit sur Tanoa).

---

## 6. Temps réel et synchronisation

### 6.1 Mécanisme de mise à jour

| Méthode | Description | État |
|---------|-------------|------|
| **Polling HTTP** | Requêtes périodiques toutes les 3–5 secondes | ✅ Actuel |
| **WebSocket** | Connexion bidirectionnelle persistante | 🔜 Prévu |

### 6.2 Fréquences de rafraîchissement

| Donnée | Fréquence |
|--------|-----------|
| Positions unités | 3 secondes |
| Tchat | 3 secondes |
| Pings | 3 secondes |
| Ordres | 4 secondes |
| Alertes médicales | 5 secondes |
| Marqueurs | 3 secondes |
| Photos intel | 3 secondes |
| Air assets | 3 secondes |
| Codes laser | 3 secondes |
| Formes carte | 3 secondes |

---

## 7. Sons et notifications

### 7.1 Événements sonores

| Événement | Son | Quand |
|-----------|-----|-------|
| **Démarrage** | Connexion établie | 1ère connexion réussie |
| **Déconnexion** | Perte liaison | Coupure réseau |
| **Alerte médicale** | Urgent | Nouvelle alerte critique (rythme 0) |
| **Nouvel ordre** | Notification | Ordre reçu pour vous |
| **Ping reçu** | Alerte | Nouveau ping |
| **Message tchat** | Notification | Nouveau message |

### 7.2 Réglages audio

| Paramètre | Options |
|-----------|---------|
| **Volume alertes** | 0–100% (curseur) |
| **Mode silence** | Coupe sons, garde vibration (si mobile) |
| **Mode silence sans vib** | Coupe tout |
| **Choix du son** | Stalker, Santé, Silence (avec vib), Silence (sans vib) |
| **Prévisualisation** | Bouton « Écouter » pour tester |

**Emplacements réglages** :
- Rail audio gauche (barre latérale)
- Panneau Compte → Préférences son

---

## 8. UI/UX et accessibilité

### 8.1 Thèmes

| Thème | Description | État |
|-------|-------------|------|
| **System** | Suit préférences système (light/dark) | ✅ |
| **Dark** | Thème sombre (défaut actuel) | ✅ |
| **Light** | Thème clair | 🔜 |

### 8.2 Densité

| Densité | Description | Écrans |
|---------|-------------|--------|
| **Comfortable** | Espacement généreux | Desktop (défaut) |
| **Compact** | Affichage dense | Mobile, petits écrans |

### 8.3 Responsive

| Breakpoint | Adaptations |
|------------|-------------|
| **Mobile (< 768px)** | Panneaux empilés, carte pleine largeur, menu hamburger |
| **Tablette (768–1024px)** | Tiroirs repliables, layout 2 colonnes |
| **Desktop (> 1024px)** | Layout 3 colonnes complet |

### 8.4 Accessibilité

| Fonctionnalité | Support |
|----------------|---------|
| **ARIA** | Rôles, labels, live regions pour annonces |
| **Clavier** | Navigation tab, raccourcis (🔜) |
| **Contrastes** | Conformité WCAG AA (thème sombre) |
| **Screen readers** | Annonces événements importants (alertes, ordres) |

---

## 9. Performance et limitations

### 9.1 Capacité

| Métrique | Valeur typique | Note |
|----------|----------------|------|
| **Unités simultanées** | 100+ | Dépend serveur et réseau |
| **Marqueurs carte** | 500+ | Clustering automatique si > 100 |
| **Messages tchat** | Illimité | Historique paginé |
| **Photos intel** | Illimité | Chargement lazy |

### 9.2 Optimisations

| Technique | Bénéfice |
|-----------|----------|
| **Clustering marqueurs** | Évite surcharge carte |
| **Lazy loading tuiles** | Charge uniquement zone visible |
| **Debounce resize** | Réduit recalculs layout |
| **Cache local** | Réduit requêtes serveur |

---

## 10. Roadmap

### Court terme (Q3 2026)

- [ ] WebSocket pour remplacer polling
- [ ] Édition marqueurs par drag & drop
- [ ] Mesures de distance sur carte
- [ ] Export carte en image
- [ ] Thème clair

### Moyen terme (Q4 2026)

- [ ] Replay historique positions
- [ ] Heatmap activité
- [ ] Outils dessin avancés
- [ ] Partage écran briefing
- [ ] Mode plein écran carte

### Long terme (2027)

- [ ] Application mobile native (iOS/Android)
- [ ] Support VR (visualisation 3D)
- [ ] Intégration vidéo UAV live

---

## 11. FAQ

### Q1: Puis-je utiliser ATAK Web sans mod Arma ?

**R** : Oui, l'interface est accessible. Cependant, sans mod, vous ne verrez pas de positions temps réel depuis le jeu. Utile pour planification pré-mission ou post-mission (RETEX).

### Q2: Combien de personnes peuvent être connectées simultanément ?

**R** : Pas de limite technique côté client (navigateur). Limite côté serveur dépend de l'hébergement (typiquement 100+ simultanés sans problème).

### Q3: Puis-je utiliser ATAK Web sur mobile/tablette ?

**R** : Oui, l'interface est responsive. Cependant, l'expérience est optimisée pour desktop (écran large recommandé pour voir carte + panneaux latéraux).

### Q4: Les données sont-elles persistées ?

**R** : Oui, toutes les données (unités, marqueurs, messages, ordres, photos) sont stockées en base de données MySQL. Historique conservé selon configuration (typiquement 30 jours).

### Q5: Puis-je intégrer ATAK Web dans un iframe ?

**R** : Non, pour raisons de sécurité (protection CSRF, clickjacking). Utilisez l'URL directe `/atak`.

---

## Annexes

### A. API endpoints (référence)

| Endpoint | Méthode | Usage |
|----------|---------|-------|
| `/api/atak/ping` | GET | Test connexion + latence |
| `/api/atak/stats` | GET | Métriques (dernière activité Arma, nb unités) |
| `/api/atak/units` | GET | Liste unités connectées |
| `/api/atak/chat` | GET/POST | Messages tchat |
| `/api/atak/pings` | GET/POST | Pings tactiques |
| `/api/atak/markers` | GET/POST/DELETE | Marqueurs |
| `/api/atak/orders` | GET/POST | Ordres tactiques |
| `/api/atak/medical-alerts` | GET | Alertes médicales |
| `/api/atak/cas` | GET/POST | Demandes CAS 9-Line |
| `/api/atak/laser-codes` | GET | Codes laser |
| `/api/atak/air-assets` | GET | Aéronefs déclarés |
| `/api/atak/intel-photos` | GET | Photos reconnaissance |
| `/api/atak/presence` | GET | Qui est sur la carte web |
| `/api/atak/activity` | GET | Journal liaison |
| `/api/health` | GET | État santé plateforme |

### B. Dépendances techniques

| Bibliothèque | Version | Usage |
|--------------|---------|-------|
| Leaflet | 1.9.4 | Cartographie interactive |
| milsymbol.js | Dernière | Symbologie MIL-STD-2525 |
| milstd2525.js | Dernière | Catalogue symboles |
| Inter Font | Variable | Typographie |

### C. Raccourcis clavier (à venir)

| Raccourci | Action |
|-----------|--------|
| **C** | Ouvrir tchat |
| **M** | Centrer sur ma position |
| **P** | Créer ping à position souris |
| **O** | Ouvrir panneau ordres |
| **J** | Ouvrir panneau JTAC |
| **Échap** | Fermer panneau actif |

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
