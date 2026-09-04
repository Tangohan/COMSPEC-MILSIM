# COMSPEC Overwatch — Comparaison avec CTAB, SIT et ATAK

**Version** : 1.0  
**Date** : 24 juillet 2026  
**Public** : Décideurs, responsables technique, administrateurs d'unité  
**Style** : Documentation produit comparative, positionnement marché

---

## 1. Présentation du document

### 1.1 Objectif

Ce document compare **COMSPEC Overwatch** avec les principales solutions de commandement et contrôle (C2) pour Arma 3 : **CTAB** (Commander's Tactical Tablet), **SIT 1erGTD** (cTab IRL) et **ATAK** (Android Tactical Assault Kit). L'objectif est d'aider les unités MILSIM à comprendre le positionnement unique de COMSPEC et à choisir la solution adaptée à leurs besoins.

### 1.2 Public visé

- **Décideurs d'unité** : Commandants, chefs d'état-major cherchant une solution C2
- **Responsables technique** : Administrateurs système, gestionnaires d'infrastructure
- **Administrateurs d'unité** : Responsables de la configuration et du déploiement

### 1.3 Périmètre

- **Solutions analysées** : COMSPEC Overwatch, CTAB, SIT 1erGTD, ATAK réel
- **Critères** : Fonctionnalités, architecture, usage opérationnel, coût (temps/ressources)
- **Hors périmètre** : Détails d'implémentation technique interne

---

## 2. Vue d'ensemble des solutions

### 2.1 Positionnement marché

Ces quatre solutions visent à améliorer le commandement et le contrôle dans les opérations militaires simulées (principalement Arma 3). Chacune répond à des besoins différents selon la maturité et l'ambition de l'unité.


| Solution | Type | Plateforme | Orientation | Public cible |
|----------|------|-----------|-------------|--------------|
| **COMSPEC Overwatch** | Plateforme MILSIM complète | Arma 3 + Web | Écosystème intégré (RH + Formation + C2) | Unités établies, communautés organisées |
| **CTAB** | Mod Arma 3 standalone | Arma 3 uniquement | Tablette tactique en jeu | Joueurs casual/MILSIM, découverte |
| **SIT 1erGTD** | Extension CTAB + webapp | Arma 3 + mobile/navigateur | Visualisation mobile légère | Petites unités, usage occasionnel |
| **ATAK** (réel) | Application militaire tactique | Android (natif) | Solution professionnelle forces armées | Militaires en opérations réelles |

### 2.2 Philosophies produit

#### COMSPEC Overwatch : Plateforme unifiée

**Vision** : Fournir un **écosystème complet** pour gérer une unité MILSIM de A à Z, du recrutement à l'après-action, en passant par la formation et les opérations.

**Principe** : Un seul système, une seule source de vérité, tous les modules interconnectés.

#### CTAB : Outil tactique autonome

**Vision** : Offrir une **tablette tactique** immersive en jeu, sans dépendance externe.

**Principe** : Simplicité, légèreté, focus sur l'expérience joueur individuelle.

#### SIT 1erGTD : Extension mobile de CTAB

**Vision** : Permettre au **commandement déporté** de suivre la mission depuis un écran secondaire (mobile/tablette).

**Principe** : Extension de CTAB avec liaison web minimale, sans infrastructure lourde.

#### ATAK : Standard militaire professionnel

**Vision** : Solution **certifiée pour opérations réelles**, référence mondiale pour C2 mobile.

**Principe** : Fiabilité, sécurité, interopérabilité militaire.

---

## 3. COMSPEC Overwatch — Solution complète

### 3.1 Positionnement produit

**COMSPEC Overwatch n'est pas un simple mod C2** : c'est une **plateforme de gestion d'unité militaire simulée** qui intègre :

- 🎓 **Système de formation** (LMS) avec parcours, certifications, studio de création
- 📋 **Gestion RH** : ORBAT, dossiers opérateurs, grades, progression
- 📄 **Gestion documentaire** : Bibliothèque, courrier officiel, workflow de validation
- 💬 **Forum communautaire** : Discussions, modération, partage
- 🗓️ **Événements** : Calendrier, pointage, rappels
- 📧 **Recrutement** : Formulaires publics, gestion candidatures
- 🎯 **C2 tactique** : Mod Arma + carte web temps réel
- 🏢 **Multi-tenant** : Support de plusieurs organisations isolées

### 3.2 Fonctionnalités C2 (mod Arma + web)

#### 3.2.1 Liaison terrain ↔ commandement

| Fonctionnalité | Description | Avantage opérationnel |
|----------------|-------------|----------------------|
| **Position temps réel** | Envoi automatique position joueur | Conscience situationnelle continue |
| **Connexion sécurisée** | Code liaison à usage unique (30 min) | Authentification simple et sûre |
| **Synchronisation indicatif** | Callsign bidirectionnel Arma ↔ web | Cohérence identification |
| **Multi-contextes** | Support plusieurs serveurs/missions | Flexibilité opérationnelle |

#### 2.3.1 Overlay tactique (Tacmap/ATAK)

| Fonctionnalité | Description |
|----------------|-------------|
| **Carte tactique temps réel** | Affichage des unités, marqueurs, positions sur fond de carte Arma |
| **Multi-théâtres** | Support Altis, Tanoa, cartes personnalisées |
| **Unités connectées** | Liste filtrable, recherche par indicatif |
| **Flux photos intel** | Galerie des captures terrain géolocalisées |
| **Tchat partagé** | Communication bidirectionnelle terrain ↔ commandement |
| **Pings tactiques** | Alertes rapides géolocalisées |
| **Panneau JTAC** | Gestion des demandes 9-Line CAS |
| **Codes laser** | Attribution et suivi des codes désignateur |
| **Air Support Assets** | Liste des aéronefs déclarés (Flight Manifest) |
| **Marqueurs tactiques** | Symboles MILSTD-2525 et APP-6 |
| **Formes et zones** | Polygones, cercles, lignes |
| **Désignateur** | Visualisation cibles laser |
| **SIGINT** | Rapports renseignement électromagnétique |
| **État de santé** | Monitoring connexions, activité DLL, erreurs |
| **Changement contexte** | Bascule serveur/mission/carte |

#### 2.3.2 Gestion communauté et personnel

| Fonctionnalité | Description |
|----------------|-------------|
| **Multi-tenant** | Organisations/unités isolées |
| **ORBAT** | Organigramme, unités, grades |
| **Fiches personnel** | Dossiers opérateurs détaillés |
| **Annuaire** | Recherche et consultation membres |
| **Groupes et équipes** | Structure hiérarchique |
| **Rôles métier** | Spécialisations (JTAC, Medic, RTO, etc.) |

#### 2.3.3 Formations (LMS)

| Fonctionnalité | Description |
|----------------|-------------|
| **Catalogue de cours** | Parcours de formation structurés |
| **Leçons interactives** | Contenu multimédia (vidéo, quiz, documents) |
| **Suivi progression** | Taux de complétion, résultats |
| **Certifications** | Validation de compétences |
| **Studio de création** | Éditeur de cours pour instructeurs |
| **Canvas pédagogique** | Mise en page et interactivité |

#### 2.3.4 Documents et courrier

| Fonctionnalité | Description |
|----------------|-------------|
| **Bibliothèque documentaire** | Manuels, procédures, SOP |
| **Courrier officiel** | Éditeur de courriers avec en-tête |
| **Workflow validation** | Circuit d'approbation |
| **Génération PDF** | Exports avec signatures |
| **Modèles de document** | Templates personnalisables |

#### 2.3.5 Forum

| Fonctionnalité | Description |
|----------------|-------------|
| **Forum multi-catégories** | Discussions par thématique |
| **Modération** | Outils de gestion de contenu |
| **Mentions et notifications** | Système de mentions @utilisateur |
| **Upload de fichiers** | Pièces jointes |
| **API REST** | Intégration programmatique |

#### 2.3.6 Événements et pointage

| Fonctionnalité | Description |
|----------------|-------------|
| **Calendrier partagé** | Planification d'opérations et entraînements |
| **Pointage présence** | Suivi d'assiduité |
| **Rappels automatiques** | Notifications pré-événement |

#### 2.3.7 Recrutement

| Fonctionnalité | Description |
|----------------|-------------|
| **Formulaires publics** | Candidatures externes |
| **Back-office recrutement** | Gestion des candidatures |
| **Messages préfaits** | Réponses types |

#### 2.3.8 Administration

| Fonctionnalité | Description |
|----------------|-------------|
| **Gestion utilisateurs** | Comptes, rôles, permissions |
| **Paramètres organisation** | Configuration tenant |
| **Audit et logs** | Traçabilité des actions |
| **Modération contenu** | Analyse fichiers (Cloudflare Stream, vérification MIME) |
| **Invitations** | Parrainage et codes d'accès |

### 2.4 API tactiques (serveur C2)

| Endpoint | Fonction |
|----------|----------|
| `/api/atak/position` | Mise à jour positions joueurs |
| `/api/atak/units` | Liste des unités connectées |
| `/api/atak/chat` | Messages tchat |
| `/api/atak/pings` | Pings tactiques |
| `/api/atak/intel` | Rapports de renseignement |
| `/api/atak/markers` | Marqueurs carte |
| `/api/atak/cas` | Demandes CAS 9-Line |
| `/api/atak/medical` | Alertes médicales |
| `/api/atak/orders` | Ordres et directives |
| `/api/atak/fireteams` | Équipes de feu |
| `/api/operations/*` | Endpoints opérationnels avancés |

### 2.5 Architecture technique

| Composant | Technologie |
|-----------|-------------|
| **Backend** | PHP 8.4 (monolithique structuré) |
| **Base de données** | MySQL (multi-tenant avec `tenant_id`) |
| **Frontend web** | JavaScript vanilla + Leaflet.js (cartographie) |
| **Mod Arma** | SQF (Arma scripting) + CBA |
| **Extension native** | C# .NET 8 (Native AOT, DLL x64) |
| **API** | REST JSON |
| **Communication temps réel** | Polling (prévu : WebSocket) |
| **Symbologie militaire** | MIL-STD-2525 / APP-6 |
| **Cartographie** | Leaflet + tuiles personnalisées Arma |
| **Paiements** | Stripe (création communautés premium) |

---

## 3. CTAB (Commander's Tactical Tablet)

### 3.1 Présentation

**CTAB** (Commander's Tactical Tablet, aussi orthographié **cTab**) est un mod Arma 3 open source (GPL v2) qui fournit des appareils tactiques portables virtuels : tablettes, micro-DAGR, Android, TAD (Tactical Awareness Display).

**Créateur original** : Riouken  
**Maintien actuel** : jetelain (GrueArbre) et contributeurs (cTab+)  
**Licence** : GNU GPL v2  
**Lien Steam** : [cTab 1erGTD](https://steamcommunity.com/workshop/filedetails/?id=2262006564)  
**GitHub** : [github.com/jetelain/cTab](https://github.com/jetelain/cTab)

### 3.2 Fonctionnalités principales

#### 3.2.1 Appareils tactiques

| Appareil | Description |
|----------|-------------|
| **Tablette MicroDAGR** | GPS/carte de poche, position, waypoints |
| **Tablette Android** | Carte tactique complète, marqueurs, messages |
| **Tablette Commander** | Interface commandement étendue |
| **TAD (Tablet Air Display)** | Interface pilote/air |
| **FBCB2** | Force XXI Battle Command Brigade and Below (véhicule) |

#### 3.2.2 Capacités

| Fonctionnalité | Description |
|----------------|-------------|
| **Carte tactique** | Affichage position joueur et unités du même camp |
| **Marqueurs** | Création et partage de marqueurs locaux |
| **Messages** | Système de messagerie entre tablettes |
| **Listes UAV** | Suivi des drones disponibles |
| **Flux vidéo UAV** | Visualisation caméras drones |
| **Waypoints** | Gestion des points de navigation |
| **Gestion interface** | Interactions tactiles, zoom, panoramique |
| **Notifications** | Alertes sonores et visuelles |

#### 3.2.3 Limitations

| Aspect | Limitation |
|--------|------------|
| **Portée** | Strictement en jeu (aucune liaison web) |
| **Données** | Synchronisation locale Arma uniquement |
| **Persistance** | Pas de stockage centralisé |
| **Intégration externe** | Aucune API ni connectivité externe standard |
| **Multi-théâtre** | Dépend de la mission en cours |

### 3.3 Éditions dérivées

#### 3.3.1 cTab NSWDG Edition (ctav-b2)

**Auteur** : Fredipedia  
**Base** : cTab (Riouken/Gundy) + éditions intermédiaires  
**Particularité** : Interface graphique améliorée, design moderne  
**Lien Steam** : [cTab NSWDG](https://steamcommunity.com/sharedfiles/filedetails/?id=2511318948)

---

## 4. SIT 1erGTD / cTab IRL

### 4.1 Présentation

**SIT 1erGTD** (Système d'Information Tactique) est une extension du projet cTab qui ajoute une **liaison entre Arma 3 et un téléphone mobile** ou navigateur web. Développé par l'unité francophone 1er GTD (GrueArbre/jetelain).

**Auteur** : GrueArbre (jetelain) / 1er GTD  
**Nom actuel** : cTAB Connect [BETA]  
**Licence** : GNU GPL v2 (projet cTab)  
**Lien Steam** : [SIT 1erGTD](https://steamcommunity.com/sharedfiles/filedetails/?id=2262009445) | [cTAB Connect BETA](https://steamcommunity.com/sharedfiles/filedetails/?id=3438247879)  
**Site** : [ctab.plan-ops.fr](https://ctab.plan-ops.fr/)  
**GitHub** : [github.com/jetelain/cTab](https://github.com/jetelain/cTab)

### 4.2 Fonctionnalités principales

#### 4.2.1 Connexion mobile

| Fonctionnalité | Description |
|----------------|-------------|
| **QR Code pairing** | Couplage téléphone ↔ session Arma via QR |
| **Visualisation mobile** | Carte tactique sur téléphone/tablette |
| **Position temps réel** | Affichage de la position du joueur |
| **Marqueurs** | Consultation des marqueurs de la mission |

#### 4.2.2 Architecture technique

| Composant | Technologie |
|-----------|-------------|
| **Extension Arma** | DLL C# .NET |
| **Serveur web** | ASP.NET Core (Kestrel) |
| **Frontend web** | HTML5 + JavaScript + Leaflet.js |
| **Communication** | WebSocket ou polling HTTP |

#### 4.2.3 Cas d'usage

- **Commandement déporté** : Suivre la mission depuis un écran secondaire
- **Spectateur** : Observer sans être en jeu
- **Mobile** : Consultation depuis smartphone/tablette

#### 4.2.4 Limitations

| Aspect | Limitation |
|--------|------------|
| **Scope fonctionnel** | Focalisé sur visualisation carte/position |
| **Intégration** | Pas d'écosystème complet (RH, formations, docs) |
| **Fonctions avancées** | Pas de CAS, ordres, médical, logistique intégrés |
| **Multi-tenant** | Non conçu pour plusieurs organisations isolées |

---

## 5. ATAK (Android Tactical Assault Kit)

### 5.1 Présentation

**ATAK** est une application militaire tactique développée par l'US Air Force Research Laboratory. C'est une **vraie solution professionnelle** utilisée par les forces armées américaines et alliées.

**Créateur** : US Air Force Research Laboratory  
**Plateforme** : Android (application native)  
**Public** : Forces armées (version civile : ATAK-CIV / WinTAK pour Windows)  
**Licence** : Propriétaire (accès contrôlé)

### 5.2 Fonctionnalités principales

#### 5.2.1 Cartographie et situation tactique

| Fonctionnalité | Description |
|----------------|-------------|
| **Cartes offline** | Tuiles préchargées, support multi-formats |
| **GPS précis** | Localisation haute précision |
| **Marqueurs tactiques** | Symbologie MIL-STD-2525 complète |
| **Formes et zones** | Polygones, cercles, lignes, corridors |
| **Couches d'information** | Superposition de données terrain, météo, intel |

#### 5.2.2 Communication et partage

| Fonctionnalité | Description |
|----------------|-------------|
| **CoT (Cursor on Target)** | Protocole de partage de position et objets tactiques |
| **Chat et messagerie** | Communication texte chiffrée |
| **Partage de fichiers** | Photos, vidéos, documents |
| **Streaming vidéo** | Flux caméras en temps réel |

#### 5.2.3 Outils spécialisés

| Fonctionnalité | Description |
|----------------|-------------|
| **9-Line CAS** | Formulaire d'appui aérien rapproché |
| **MEDEVAC 9-Line** | Demande d'évacuation médicale |
| **Calculs balistiques** | Solutions de tir |
| **Suivi d'objectifs** | Gestion de cibles |
| **Routes et navigation** | Planification d'itinéraires |

#### 5.2.4 Extensibilité

| Aspect | Capacité |
|--------|----------|
| **Plugins** | Système de plugins extensible |
| **API** | Intégration avec systèmes C2 militaires |
| **Capteurs** | Intégration matériel (lasers, caméras thermiques, etc.) |

#### 5.2.5 Sécurité

| Fonctionnalité | Description |
|----------------|-------------|
| **Chiffrement** | Communications chiffrées de bout en bout |
| **Authentification** | Contrôle d'accès robuste |
| **Traçabilité** | Logs d'audit |

### 5.3 ATAK vs simulations

**Important** : ATAK est une **solution militaire réelle**, pas un mod de jeu. Les comparaisons avec COMSPEC, CTAB ou SIT concernent l'**inspiration conceptuelle** et les **fonctionnalités simulées** dans Arma 3.

| Aspect | ATAK réel | Mods Arma (COMSPEC/CTAB/SIT) |
|--------|-----------|------------------------------|
| **Usage** | Opérations militaires réelles | Simulation/entraînement/jeu |
| **Certification** | Validé pour usage opérationnel | Aucune certification |
| **Support** | Support gouvernemental | Communauté/bénévoles |
| **Sécurité** | Niveau militaire | Standard grand public |
| **Coût** | Gratuit mais accès restreint | Gratuit et ouvert |

---

## 6. Tableau comparatif synthétique

### 6.1 Comparaison fonctionnelle

| Fonctionnalité | COMSPEC Overwatch | CTAB | SIT 1erGTD | ATAK réel |
|----------------|-------------------|------|------------|-----------|
| **Carte tactique en jeu** | ✅ (tablette + web) | ✅ | ✅ | ✅ |
| **Liaison web/mobile** | ✅ | ❌ | ✅ | ✅ |
| **Position temps réel** | ✅ | ✅ | ✅ | ✅ |
| **Marqueurs tactiques** | ✅ | ✅ | ✅ | ✅ |
| **Tchat/messagerie** | ✅ | ✅ | ❌ | ✅ |
| **Photos/intel** | ✅ | ❌ | ❌ | ✅ |
| **CAS 9-Line** | ✅ | ❌ | ❌ | ✅ |
| **Ordres et C2** | ✅ | ❌ | ❌ | ✅ |
| **Médical/TCCC** | ✅ | ❌ | ❌ | ✅ |
| **Logistique** | ✅ | ❌ | ❌ | ✅ |
| **IFF** | ✅ | ❌ | ❌ | ✅ |
| **Zones de danger** | ✅ | ❌ | ❌ | ✅ |
| **Briefing intégré** | ✅ | ❌ | ❌ | ✅ |
| **Flight Manifest** | ✅ | ❌ | ❌ | ✅ |
| **Codes laser** | ✅ | ❌ | ❌ | ✅ |
| **Portail web complet** | ✅ | ❌ | ⚠️ (limité) | ⚠️ (pro) |
| **Gestion RH/ORBAT** | ✅ | ❌ | ❌ | ❌ |
| **Formations LMS** | ✅ | ❌ | ❌ | ❌ |
| **Documents/courrier** | ✅ | ❌ | ❌ | ❌ |
| **Forum** | ✅ | ❌ | ❌ | ❌ |
| **Multi-tenant** | ✅ | ❌ | ❌ | ⚠️ |
| **Open source** | ⚠️ (hybride) | ✅ | ✅ | ❌ |

### 6.2 Comparaison technique

| Aspect | COMSPEC | CTAB | SIT | ATAK |
|--------|---------|------|-----|------|
| **Plateforme** | Arma + Web | Arma | Arma + Web | Android |
| **Backend** | PHP + MySQL | Arma local | ASP.NET | Java/Kotlin |
| **Extension native** | .NET C# | ❌ | .NET C# | ❌ (natif) |
| **API REST** | ✅ | ❌ | ⚠️ | ✅ |
| **WebSocket** | 🔜 | ❌ | ✅ | ✅ |
| **Base de données** | MySQL centralisée | Arma variables | ⚠️ (session) | SQLite + serveur |
| **Cartographie web** | Leaflet.js | ❌ | Leaflet.js | Natif Android |
| **Symbologie militaire** | MIL-STD-2525 / APP-6 | Limitée | Limitée | MIL-STD-2525 |

### 6.3 Comparaison d'usage

| Critère | COMSPEC | CTAB | SIT | ATAK |
|---------|---------|------|-----|------|
| **Installation** | Mod + compte Athena | Mod seul | Mod + serveur web | App Android |
| **Complexité** | Moyenne-Élevée | Faible | Moyenne | Élevée |
| **Public cible** | Unités MILSIM organisées | Joueurs Arma casual/MILSIM | Unités MILSIM petites/moyennes | Forces armées |
| **Maintenance** | Active (COMSPEC) | Active (communauté) | Active (1er GTD) | Active (militaire) |
| **Documentation** | ✅ Complète | ⚠️ Partielle | ⚠️ Partielle | ✅ Complète |
| **Support** | Équipe COMSPEC | Communauté | 1er GTD | Militaire/gouvernement |

---

## 7. Avantages distinctifs de COMSPEC

### 7.1 Écosystème complet vs outils isolés

| Aspect | COMSPEC | Autres solutions |
|--------|---------|------------------|
| **Vision** | Plateforme MILSIM unifiée | Outils tactiques ponctuels |
| **Périmètre** | RH + Formations + Docs + Forum + C2 | Uniquement C2 (ou moins) |
| **Intégration** | Tous modules interconnectés | Silos fonctionnels |
| **Workflow** | Recrutement → Formation → Opérations → RETEX | Seulement opérations |

### 7.2 Fonctionnalités avancées

#### 7.2.1 C2 et opérations

**COMSPEC** offre un **C2 mature** absent des autres mods :

- **Ordres formalisés** : Émission, réception, accusés de lecture
- **Chaîne médicale** : TCCC structuré, triage, évacuation
- **Logistique** : Suivi munitions/carburant/équipement
- **IFF** : Identification ami/ennemi
- **Zones de danger** : Alertes automatiques
- **Intel structuré** : Rapports typés (INFANTRY, VEHICLE, ARMOR, AIR_DEFENSE)

#### 7.2.2 Gestion organisationnelle

**COMSPEC** est la **seule solution** intégrant :

- **ORBAT complet** : Unités, grades, organigramme
- **Dossiers opérateurs** : Fiches individuelles détaillées
- **LMS** : Parcours de formation, certifications, studio de création
- **Courrier officiel** : Workflow de validation, génération PDF
- **Forum intégré** : Discussions communautaires
- **Événements et pointage** : Calendrier, suivi de présence

#### 7.2.3 Multi-tenant et scalabilité

**COMSPEC** supporte **plusieurs organisations isolées** :

- **Tenant ID** : Isolation données par communauté
- **Permissions granulaires** : Rôles et droits d'accès
- **Branding** : Personnalisation par organisation
- **Paiements** : Intégration Stripe pour communautés premium

### 7.3 Architecture moderne

| Composant | COMSPEC | CTAB | SIT | ATAK |
|-----------|---------|------|-----|------|
| **Backend structuré** | ✅ (MVC, services, repos) | ❌ | ⚠️ | ✅ |
| **Base centralisée** | ✅ | ❌ | ⚠️ | ✅ |
| **API REST** | ✅ | ❌ | ⚠️ | ✅ |
| **Extension native** | ✅ (Native AOT) | ❌ | ✅ | N/A |
| **Symbologie standardisée** | ✅ (MIL-STD-2525) | ❌ | ❌ | ✅ |

### 7.4 Roadmap et vision produit

**COMSPEC** a une **feuille de route ambitieuse** :

#### P0 — Impact fort (déjà en cours)

- **Readiness opérationnelle** : Score individuel/collectif
- **Cycle mission complet** : OPORD → EXORD → SITREP → AAR
- **Gestion rôles critiques** : JTAC, Medic, RTO, SL, PL
- **Journal tactique unifié** : Timeline multi-source

#### P1 — Réalisme avancé (en planification)

- **Logistique structurée** : Inventaires, consommation, ravitaillement
- **Médical TCCC-lite** : États blessure, timers, évacuation
- **Communications radio** : Canaux virtuels, accusés de réception
- **Météo opérationnelle** : Impact sur missions

#### P2 — Différenciation premium

- **Doctrine versionnée** : SOP, checklists, formats de rapport
- **XP réaliste** : Historique missionnel pondéré
- **Wargaming léger** : Simulateur de préparation mission
- **AAR assisté par IA** : Génération automatique de RETEX

---

## 8. Conclusion et recommandations

### 8.1 Synthèse

| Solution | Meilleur pour... | Limites |
|----------|------------------|---------|
| **COMSPEC Overwatch** | Unités MILSIM sérieuses cherchant un écosystème complet (RH + Formation + C2) | Complexité d'installation, nécessite serveur web |
| **CTAB** | Joueurs voulant une tablette tactique simple en jeu | Pas de liaison externe, fonctions limitées |
| **SIT 1erGTD** | Petites unités voulant visualisation mobile sans infrastructure lourde | Scope fonctionnel limité |
| **ATAK (réel)** | Forces armées en opérations réelles | Non applicable au jeu |

### 8.2 Positionnement de COMSPEC

**COMSPEC Overwatch** se distingue comme la **seule plateforme MILSIM intégrée** combinant :

1. **Mod Arma 3** riche (120+ fonctions)
2. **Portail web complet** (RH, LMS, docs, forum, C2)
3. **API tactiques** temps réel
4. **Architecture multi-tenant** scalable

**COMSPEC n'est pas simplement un "CTAB avec une webapp"** : c'est un **système de gestion d'unité militaire simulée** incluant le C2 comme composant parmi d'autres.

### 8.3 Recommandations d'usage

#### Pour une unité MILSIM débutante

- **CTAB** : Démarrage simple, apprentissage tablette tactique
- **Évolution vers COMSPEC** : Quand organisation se structure

#### Pour une unité MILSIM établie

- **COMSPEC Overwatch** : Solution complète
- **Alternative** : CTAB + outils externes séparés (moins intégré)

#### Pour une petite équipe occasionnelle

- **SIT 1erGTD** : Juste assez pour visualisation mobile
- **CTAB** : Si pas besoin de liaison externe

#### Pour forces armées réelles

- **ATAK** : Solution professionnelle certifiée

### 8.4 Évolutions futures

**COMSPEC** continue d'évoluer avec :

- **WebSocket** : Remplacement du polling par temps réel bidirectionnel
- **Fonctions avancées** : Logistique, médical structuré, wargaming
- **Intégrations** : TFAR/ACRE (radio), ACE (médical, logistique)
- **Mobile natif** : Application iOS/Android dédiée (au-delà du navigateur mobile)

**CTAB** et **SIT** restent des projets communautaires solides mais avec un scope plus restreint.

### 8.5 Inspiration mutuelle

**COMSPEC s'inspire ouvertement de CTAB et SIT** :

- **UI tablette** : Design influencé par cTab NSWDG
- **Liaison mobile** : Concept de SIT/cTab IRL
- **Nomenclature ATAK** : Terminologie inspirée du vrai ATAK

**COMSPEC reconnaît ces sources** (voir `CREDITS.md`) et respecte les licences GPL v2 des parties dérivées.

---

## Annexes

### A. Liens utiles

| Projet | Lien |
|--------|------|
| **COMSPEC Athena** | [athena.ttrd.fr/public](https://athena.ttrd.fr/public) |
| **CTAB+ (GitHub)** | [github.com/jetelain/cTab](https://github.com/jetelain/cTab) |
| **CTAB Steam** | [Workshop cTab 1erGTD](https://steamcommunity.com/workshop/filedetails/?id=2262006564) |
| **SIT 1erGTD Steam** | [Workshop SIT](https://steamcommunity.com/sharedfiles/filedetails/?id=2262009445) |
| **cTAB Connect BETA** | [Workshop cTAB Connect](https://steamcommunity.com/sharedfiles/filedetails/?id=3438247879) |
| **SIT Site** | [ctab.plan-ops.fr](https://ctab.plan-ops.fr/) |
| **1er GTD** | [1ergtd.fr](https://www.1ergtd.fr/) |
| **ATAK-CIV** | [Rechercher "ATAK-CIV" ou "WinTAK"] |

### B. Glossaire

| Terme | Signification |
|-------|---------------|
| **C2** | Command and Control (Commandement et Contrôle) |
| **ATAK** | Android Tactical Assault Kit |
| **CTAB / cTab** | Commander's Tactical Tablet |
| **SIT** | Système d'Information Tactique |
| **CoT** | Cursor on Target (protocole ATAK) |
| **CAS** | Close Air Support (Appui Aérien Rapproché) |
| **JTAC** | Joint Terminal Attack Controller |
| **TCCC** | Tactical Combat Casualty Care |
| **IFF** | Identification Friend or Foe |
| **ORBAT** | Order of Battle (Organigramme) |
| **LMS** | Learning Management System |
| **SOP** | Standard Operating Procedures |
| **AAR** | After Action Review (RETEX) |
| **OPORD** | Operations Order |
| **EXORD** | Execution Order |
| **SITREP** | Situation Report |
| **MEDEVAC** | Medical Evacuation |
| **SIGINT** | Signals Intelligence |
| **MIL-STD-2525** | Standard militaire américain pour symbologie tactique |
| **APP-6** | Symbologie OTAN |

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
