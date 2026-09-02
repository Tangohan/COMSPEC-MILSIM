# COMSPEC ATAK Web — Présentation des fonctionnalités

**Version** : 1.0  
**Date** : 24 juillet 2026

---

## Vue d'ensemble

**COMSPEC ATAK Web** est l'interface de carte tactique temps réel accessible via navigateur. Elle permet au commandement et aux opérateurs de visualiser la situation opérationnelle, coordonner les actions, et maintenir la liaison avec le terrain (Arma 3).

**Accès** : `/atak` (connexion requise)

---

## Architecture interface

### En-tête (Header)

| Élément | Fonction |
|---------|----------|
| **Logo ATHENA ATAK** | Identité visuelle |
| **État réseau** | Indicateur « Réseau actif » / « Hors ligne » (pastille live) |
| **Heure Zulu** | Affichage UTC en temps réel |
| **Sélecteur serveur/mission** | Changement de contexte opérationnel (workspace) |
| **Sélecteur carte** | Choix du théâtre (Altis, Tanoa, etc.) |
| **Boutons d'action** | Connexion en jeu, Lier téléphone, Configuration, État, Compte |

### Barre de métriques (OS Strip)

Affichage des indicateurs de liaison :

- **Qualité** : Bonne / Acceptable / Dégradée
- **Latence** : Délai réseau en millisecondes
- **Pertes de paquets** : (À venir)
- **Théâtre** : Dernière activité reçue depuis Arma

### Panneau gauche (onglets)

9 onglets fonctionnels :

1. **Cams** — Flux photos intel
2. **Marqueurs** — Gestion des marqueurs tactiques
3. **Tchat** — Journal radio partagé
4. **Ordres** — Émission et réception d'ordres tactiques
5. **Assistances** — Alertes médicales
6. **Radio** — Proximité radio (TFAR/ACRE)
7. **Pings** — Alertes rapides géolocalisées
8. **JTAC** — Appui aérien (9-Line CAS) et codes laser
9. **Liaison** — Journal d'activité et présence web

### Carte centrale (Leaflet)

Affichage cartographique interactif avec :

- Fond de carte théâtre Arma (tuiles personnalisées)
- Coordonnées alignées sur le monde Arma
- Zoom/pan, échelle métrique
- HUD coin bas : grille souris, état réseau

### Panneau droit

- **Appui aérien** — Liste des aéronefs déclarés (Flight Manifest)
- **Effectifs** — Liste des unités connectées avec filtres

### Tiroir effectifs (tableau)

Tableau dépliable fixe affichant :

- Indicatif
- Rôle
- État liaison
- Cap (heading)
- Position grille

---

## Fonctionnalités détaillées

### 1. Carte tactique interactive

#### 1.1 Visualisation

| Fonctionnalité | Description |
|----------------|-------------|
| **Fonds de carte** | Altis, Tanoa, cartes personnalisées |
| **Système de coordonnées** | Aligné sur Arma (mètres, repère cartésien) |
| **Tuiles** | Chargement progressif, gestion d'erreur |
| **Échelle** | Échelle métrique dynamique |
| **HUD grille** | Position souris en coordonnées carte |
| **Multi-théâtres** | Changement de carte à la volée |

#### 1.2 Couches cartographiques

| Couche | Contenu |
|--------|---------|
| **Unités** | Positions joueurs en temps réel |
| **Marqueurs** | Symboles tactiques MIL-STD-2525 / APP-6 |
| **Intel** | Photos de reconnaissance géolocalisées |
| **Désignateur** | Cibles laser |
| **SIGINT** | Zones renseignement électromagnétique |
| **Air assets** | Aéronefs déclarés |
| **Pings** | Alertes temporaires |
| **Formes** | Zones, polygones, lignes |

#### 1.3 Interactions carte

| Action | Résultat |
|--------|----------|
| **Clic gauche sur unité** | Popup détails (indicatif, rôle, position, actions) |
| **Clic droit** | Menu contextuel (ping, marqueur, ordre, zoom) |
| **Zoom molette** | Zoom in/out |
| **Drag** | Panoramique |
| **Double-clic** | Zoom avant centré |

### 2. Unités et effectifs

#### 2.1 Liste des effectifs (panneau droit)

| Fonctionnalité | Description |
|----------------|-------------|
| **Liste temps réel** | Tous les contacts en liaison |
| **Filtrage** | Recherche par indicatif ou rôle |
| **Modes d'affichage** | « En liaison » (actifs) / « Tous » (historique) |
| **Tri** | Alphabétique par indicatif |
| **Indicateurs visuels** | Pastille verte (actif), grise (inactif) |

#### 2.2 Affichage carte

| Élément | Description |
|---------|-------------|
| **Marqueur unité** | Symbole militaire avec indicatif |
| **Orientation** | Flèche direction selon cap |
| **Couleur** | Bleu (ami), rouge (ennemi), jaune (neutre) |
| **Popup** | Détails complets : indicatif, rôle, position, grille, actions rapides |

#### 2.3 Tableau effectifs

| Colonne | Contenu |
|---------|---------|
| **Indicatif** | Callsign joueur |
| **Rôle** | Spécialité (Rifleman, Medic, JTAC, etc.) |
| **Liaison** | Temps depuis dernière mise à jour |
| **Cap** | Direction en degrés |
| **Grille** | Coordonnées X Y |

### 3. Communications

#### 3.1 Tchat (Journal radio)

| Fonctionnalité | Description |
|----------------|-------------|
| **Messagerie partagée** | Canal SQUAD pour toute l'équipe |
| **Envoi** | Champ de saisie + bouton Envoyer |
| **Affichage** | Messages horodatés avec auteur |
| **Vider** | Bouton pour nettoyer l'affichage local |
| **Synchronisation** | Temps réel bidirectionnel (web ↔ Arma) |

#### 3.2 Pings

| Fonctionnalité | Description |
|----------------|-------------|
| **Création** | Clic droit sur carte → « Envoyer un ping » |
| **Contenu** | Position + message texte |
| **Affichage carte** | Marqueur temporaire + cercle pulsant |
| **Liste** | Onglet Pings avec historique |
| **Durée de vie** | Disparition automatique après 30 min |

### 4. Ordres tactiques

#### 4.1 Émission d'ordres

| Champ | Options |
|-------|---------|
| **Type** | Se déplacer, Tenir position, Reconnaissance, Appui aérien, Force de réaction |
| **Priorité** | Routine, Important, Urgent, Contact |
| **Destinataire** | Toute l'équipe, Utilisateur, Groupe, Fire team, Canal, ATAK Solo |
| **Précisions** | Texte libre (max 400 caractères) |
| **Options** | Conditions radio réalistes (délai, brouillage fictif) |

#### 4.2 Réception et suivi

| Fonctionnalité | Description |
|----------------|-------------|
| **Liste ordres** | Tous les ordres émis et reçus |
| **Statuts** | En attente, Reçu, En cours, Terminé, Annulé |
| **Actions** | Confirmer réception, Marquer terminé, Annuler |
| **Badge** | Compteur d'ordres non lus sur l'onglet |

### 5. JTAC et appui aérien

#### 5.1 Demandes CAS 9-Line

| Ligne | Contenu |
|-------|---------|
| **1** | Type (IP/FFP/CAS/…) |
| **2** | Position cible |
| **3** | Élévation |
| **4** | Description cible |
| **5** | Marqueur (fumée, laser, etc.) |
| **6** | Position amis/ennemis |
| **7** | Retrait (heading et distance) |
| **8** | Autres informations |
| **9** | Remarques (DANGER CLOSE, etc.) |

**Workflow** :

1. Bouton « Nouvelle 9-Line CAS »
2. Formulaire avec 9 champs
3. Validation et envoi
4. Affichage dans la liste avec statut
5. Pilotes peuvent accuser réception depuis Arma

#### 5.2 Codes laser

| Fonctionnalité | Description |
|----------------|-------------|
| **Génération** | Codes désignateur automatiques |
| **Attribution** | Associés aux demandes CAS |
| **Affichage** | Liste des codes actifs |
| **Synchronisation** | Avec désignateurs en jeu |

#### 5.3 Air Support Assets

| Information | Description |
|-------------|-------------|
| **Type aéronef** | Identifié automatiquement depuis Arma |
| **Indicatif pilote** | Callsign |
| **Statut** | En vol, En attente, Engagé |
| **Liaison** | Dernière mise à jour |

### 6. Médical et assistances

#### 6.1 Alertes médicales

| Fonctionnalité | Description |
|----------------|-------------|
| **Déclenchement** | Joueur inconscient ou mort (ACE Medical) |
| **Contenu** | Indicatif, position, type de blessure |
| **Priorité** | Critique (rythme cardiaque 0), Urgent, Routine |
| **Affichage** | Liste dans onglet Assistances + bannière top si critique |
| **Durée** | Disparition auto après 30 min |
| **Actions** | Masquer, Trier (Traité, KIA, Annulé) |

#### 6.2 Triage (rôle Medic/Chef)

| Statut | Description |
|--------|-------------|
| **En attente** | Alerte active, non traitée |
| **En cours** | Médecin sur place |
| **Traité** | Blessé stabilisé |
| **KIA** | Killed In Action |
| **Annulé** | Fausse alerte ou résolu autrement |

### 7. Radio et proximité

#### 7.1 Surveillance radio (TFAR/ACRE)

| Fonctionnalité | Description |
|----------------|-------------|
| **Opérateur de référence** | Choix manuel ou automatique |
| **Rayon** | 50–200 mètres configurable |
| **Détection émission** | Qui émet près de l'opérateur |
| **Réseau** | Canal radio surveillé |
| **Options** | Émissions uniquement, Masquer si aucun module |
| **Affichage** | Liste + pastilles sur carte |

#### 7.2 Actions radio

| Action | Description |
|--------|-------------|
| **Surveiller réseau** | Bascule canal radio actif (depuis tablette Arma) |
| **Alertes visuelles** | Pastilles clignotantes sur carte |
| **Badge** | Compteur d'émissions actives sur onglet |

### 8. Marqueurs tactiques

#### 8.1 Création

| Méthode | Procédure |
|---------|-----------|
| **Clic droit carte** | « Placer un marqueur » → Sélecteur symbole + texte |
| **Menu unité** | Clic unité → « Marquer position » |

#### 8.2 Symbologie

| Standard | Support |
|----------|---------|
| **MIL-STD-2525** | Oui (via milsymbol.js) |
| **APP-6** | Oui |
| **SIDC** | Sélecteur par code ou catalogue visuel |

#### 8.3 Gestion

| Action | Description |
|--------|-------------|
| **Éditer** | Clic marqueur → Modifier texte/symbole |
| **Supprimer** | Clic marqueur → Supprimer |
| **Synchronisation** | Bidirectionnelle avec Arma |

### 9. Photos et reconnaissance

#### 9.1 Flux Cams (onglet)

| Fonctionnalité | Description |
|----------------|-------------|
| **Réception** | Photos envoyées depuis Arma (type CTAB) |
| **Affichage** | Grille miniatures avec timestamp |
| **Géolocalisation** | Position associée à chaque photo |
| **Zoom** | Clic pour agrandissement |
| **Partage** | Visible par tous les opérateurs du théâtre |

#### 9.2 Intel photos sur carte

| Fonctionnalité | Description |
|----------------|-------------|
| **Marqueur photo** | Icône caméra à la position de prise de vue |
| **Popup** | Aperçu miniature + bouton « Voir » |
| **Couche dédiée** | Activable/désactivable |

### 10. Formes et zones

#### 10.1 Types supportés

| Type | Description |
|------|-------------|
| **Polygone** | Zone irrégulière |
| **Cercle** | Rayon depuis point central |
| **Ligne** | Axe, frontière |
| **Rectangle** | Zone rectangulaire |

#### 10.2 Zones de danger

| Fonctionnalité | Description |
|----------------|-------------|
| **Création** | Depuis Arma ou web |
| **Alerte entrée** | Notification automatique si joueur entre |
| **Visualisation** | Zone rouge sur carte |
| **Gestion** | Modification, suppression |

### 11. Liaison et activité

#### 11.1 Journal liaison (onglet)

| Type événement | Description |
|----------------|-------------|
| **Connexion** | Joueur se connecte |
| **Déconnexion** | Joueur se déconnexte |
| **Changement indicatif** | Modification callsign |
| **Échange** | Message tchat, ping, photo |

#### 11.2 Présence web

| Fonctionnalité | Description |
|----------------|-------------|
| **Liste opérateurs** | Qui est sur la carte Athena (navigateur) |
| **Mise à jour** | Rafraîchissement toutes les 20 secondes |
| **Affichage** | Indicatif ou nom affiché |

#### 11.3 Actions

| Action | Description |
|--------|-------------|
| **Vider** | Nettoyer l'affichage local |
| **Voir tout** | Page dédiée `/atak/liaison` avec historique complet |

### 12. Configuration et administration

#### 12.1 Panneau Compte

| Section | Contenu |
|---------|---------|
| **Connexion en jeu** | Génération code liaison Arma (30 min, usage unique) |
| **Connexion téléphone** | Génération QR code pour mobile |
| **Compte** | Email, nom affiché, indicatif |
| **Liaison Steam** | Identifiant Steam pour corrélation |
| **Indicatif Arma** | Callsign utilisé en jeu |
| **Serveur** | Adresse serveur Arma + port |
| **Préférences son** | Volume alertes, choix du son (Stalker, Santé, Silence) |

#### 12.2 Panneau Configuration

| Information | Description |
|-------------|-------------|
| **Adresse liaison** | URL à saisir dans le mod Arma |
| **Bouton Copier** | Copie dans presse-papiers |
| **Adresse réseau visiteur** | IP publique (diagnostic) |
| **Pack Overwatch** | Lien téléchargement mod (si déposé par admin) |
| **Serveur Arma** | Host:port |
| **Identifiants mod** | Texte libre config |
| **Instructions** | Procédures équipe |
| **Liens** | Assistant mod, Guide complet |

#### 12.3 Panneau État de santé

| Vérification | Indicateurs |
|--------------|-------------|
| **Liaison carte** | URL + statut (Opérationnel / Indisponible) |
| **Mises à jour** | État connexion temps réel |
| **Données** | Disponibilité base de données |
| **Mod en jeu** | Dernière activité Arma |
| **Contacts actifs** | Nombre + liste indicatifs |
| **Tchat** | Incidents éventuels |
| **Pings** | Incidents éventuels |
| **Bouton Actualiser** | Rafraîchir manuellement |

### 13. Gestion multi-contextes

#### 13.1 Workspaces (serveurs/missions)

| Fonctionnalité | Description |
|----------------|-------------|
| **Sélecteur** | Dropdown liste des contextes |
| **Isolation données** | Chaque workspace a ses propres unités, marqueurs, messages |
| **Changement à la volée** | Pas besoin de recharger la page |
| **Configuration admin** | Définition des workspaces par l'admin |

#### 13.2 Cartes multiples

| Fonctionnalité | Description |
|----------------|-------------|
| **Sélecteur** | Dropdown liste des théâtres |
| **Mémorisation** | Dernier choix sauvegardé en localStorage |
| **Configuration** | Tuiles, centre, zoom par carte |
| **Support** | Altis, Tanoa, cartes custom |

### 14. Temps réel et synchronisation

#### 14.1 Mécanisme de mise à jour

| Méthode | Description |
|---------|-------------|
| **Polling** | Requêtes HTTP périodiques (actuellement) |
| **Fréquence** | 3 secondes (unités, tchat, pings, ordres, médical) |
| **WebSocket** | Prévu pour remplacement du polling |
| **Latence mesurée** | Ping API toutes les 15 secondes |

#### 14.2 Données synchronisées

| Donnée | Fréquence refresh |
|--------|------------------|
| **Positions unités** | 3 secondes |
| **Tchat** | 3 secondes |
| **Pings** | 3 secondes |
| **Ordres** | 4 secondes |
| **Alertes médicales** | 5 secondes |
| **Marqueurs** | 3 secondes |
| **Photos intel** | 3 secondes |
| **Air assets** | 3 secondes |
| **Codes laser** | 3 secondes |
| **Formes carte** | 3 secondes |

### 15. Sons et notifications

#### 15.1 Événements sonores

| Événement | Son |
|-----------|-----|
| **Démarrage** | Son de connexion établie |
| **Déconnexion** | Son de perte liaison |
| **Alerte médicale** | Son urgent (configurable) |
| **Nouvel ordre** | Son notification |
| **Ping reçu** | Son alerte |
| **Message tchat** | Son notification |

#### 15.2 Réglages audio

| Paramètre | Options |
|-----------|---------|
| **Volume alertes** | 0–100% (curseur) |
| **Mode silence** | Coupe sons, garde vibration |
| **Mode silence sans vibration** | Coupe tout |
| **Choix du son** | Stalker, Santé, Silence (avec/sans vib) |
| **Prévisualisation** | Bouton « Écouter » |

### 16. UI/UX et accessibilité

#### 16.1 Thèmes

| Thème | Description |
|-------|-------------|
| **System** | Suit les préférences système (light/dark) |
| **Light** | Thème clair (à venir) |
| **Dark** | Thème sombre (actuel par défaut) |

#### 16.2 Densité

| Densité | Description |
|---------|-------------|
| **Comfortable** | Espacement généreux (défaut) |
| **Compact** | Affichage dense pour petits écrans |

#### 16.3 Responsive

| Breakpoint | Adaptations |
|------------|-------------|
| **Mobile** | Panneaux empilés, carte pleine largeur |
| **Tablette** | Tiroirs repliables |
| **Desktop** | Layout 3 colonnes (panneau gauche + carte + panneau droit) |

#### 16.4 Accessibilité

| Fonctionnalité | Support |
|----------------|---------|
| **ARIA** | Rôles, labels, live regions |
| **Clavier** | Navigation tab, raccourcis |
| **Contrastes** | Conformité WCAG (thème sombre) |
| **Screen readers** | Annonces événements importants |

### 17. Performance et scalabilité

#### 17.1 Optimisations carte

| Technique | Description |
|-----------|-------------|
| **Clustering** | Regroupement marqueurs si nombreux |
| **Lazy loading tuiles** | Chargement progressif fond carte |
| **Debounce resize** | Évite recalculs excessifs |
| **ResizeObserver** | Détection changements taille conteneur |

#### 17.2 Gestion données

| Aspect | Implémentation |
|--------|----------------|
| **Cache local** | Mémorisation dernières données |
| **Incrémental** | Chargement par pages si volume important |
| **Filtrage côté client** | Recherche instantanée sans requête serveur |
| **Expiration** | Suppression auto données anciennes (pings, alertes) |

---

## API endpoints utilisés

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
| `/api/atak/map-shapes` | GET | Formes carte |
| `/api/atak/presence` | GET | Qui est sur la carte web |
| `/api/atak/activity` | GET | Journal liaison |
| `/api/atak/phone-pairing` | GET | Génération QR téléphone |
| `/api/atak/phone-pairing/status` | GET | État liaison téléphone |
| `/atak/game-link` | POST | Génération code liaison Arma |
| `/api/health` | GET | État santé plateforme |

---

## Capacités par rôle utilisateur

| Capacité | Description |
|----------|-------------|
| **Connecté** | Accès carte, tchat, pings, visualisation ordres/médical |
| **Opérateur terrain** | Envoi position depuis Arma, réception ordres |
| **Commandement** | Émission ordres, visualisation complète, gestion marqueurs |
| **JTAC** | Création 9-Line CAS, gestion codes laser |
| **Médecin** | Triage alertes médicales |
| **Pilote** | Déclaration Flight Manifest, réponse CAS |
| **Admin** | Configuration carte/serveur, accès panneau santé étendu |

---

## Raccourcis clavier (à implémenter)

| Raccourci | Action |
|-----------|--------|
| **C** | Ouvrir tchat |
| **M** | Centrer sur ma position |
| **P** | Créer un ping à la position souris |
| **O** | Ouvrir panneau ordres |
| **J** | Ouvrir panneau JTAC |
| **Échap** | Fermer panneau actif |

---

## Dépendances techniques

| Bibliothèque | Usage |
|--------------|-------|
| **Leaflet 1.9.4** | Cartographie interactive |
| **milsymbol.js** | Symbologie militaire MIL-STD-2525 |
| **milstd2525.js** | Support symboles tactiques |
| **Vanilla JavaScript** | Logique application (pas de framework) |
| **CSS Grid/Flexbox** | Layout responsive |
| **Inter Font** | Typographie |

---

## Points d'amélioration (roadmap)

### Court terme

- [ ] WebSocket pour remplacer polling
- [ ] Édition marqueurs par drag & drop
- [ ] Mesures de distance sur carte
- [ ] Export carte en image
- [ ] Filtres marqueurs par type
- [ ] Thème clair

### Moyen terme

- [ ] Replay historique positions
- [ ] Heatmap activité
- [ ] Outils dessin avancés
- [ ] Partage écran briefing
- [ ] Mode plein écran carte
- [ ] Raccourcis clavier complets

### Long terme

- [ ] Application mobile native (iOS/Android)
- [ ] Support VR (visualisation 3D)
- [ ] IA prédiction mouvements
- [ ] Intégration vidéo UAV live
- [ ] Mode collaboratif planning mission

---

## Conclusion

**COMSPEC ATAK Web** offre une interface C2 complète et moderne, conçue pour les unités MILSIM exigeantes. Son intégration étroite avec Arma 3 via le mod Overwatch, combinée à une architecture web performante, en fait une solution unique pour le commandement et le contrôle en simulation militaire.

**Pour aller plus loin** :

- [Documentation complète ATAK](/docs/ATAK-Documentation-Produit.md)
- [Comparaison COMSPEC vs CTAB/SIT/ATAK](/docs/COMPARAISON-COMSPEC-CTAB-SIT-ATAK.md)
- [Guide utilisateur équipement](/docs/utilisateur/equipement-modpacks-atak.md)
- [Architecture technique](/docs/technique/architecture.md)

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
