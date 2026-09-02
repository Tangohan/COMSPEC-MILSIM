# COMSPEC Overwatch — Comparaison produit avec CTAB, SIT et ATAK

**Version** : 1.0  
**Date** : 24 juillet 2026  
**Public** : Décideurs, responsables technique, administrateurs d'unité  
**Style** : Documentation produit comparative, aide à la décision

---

## 1. Résumé exécutif

### 1.1 Positionnement

**COMSPEC Overwatch** n'est pas un simple mod de carte tactique pour Arma 3 : c'est une **plateforme complète de gestion d'unité MILSIM** incluant formations, RH, documents, forum ET commandement-contrôle (C2).

**Différence clé** : Les autres solutions (CTAB, SIT) se concentrent uniquement sur le C2 en jeu. COMSPEC couvre tout le cycle de vie de l'unité.

### 1.2 Pour qui ?

| Solution | Profil unité idéal |
|----------|-------------------|
| **COMSPEC** | Unités établies (> 30 membres), structure organisée, ambition long terme, besoin RH/formation |
| **CTAB** | Petites équipes, découverte, jeu casual, pas de structure formelle |
| **SIT** | Unités moyennes, commandement déporté occasionnel, infrastructure légère |
| **ATAK** | Forces armées réelles (hors simulation) |

### 1.3 Décision rapide

**Choisissez COMSPEC si** :
- ✅ Vous avez > 20–30 membres actifs
- ✅ Vous organisez des formations obligatoires
- ✅ Vous gérez un ORBAT structuré
- ✅ Vous produisez de la documentation (SOP, ordres écrits, RETEX)
- ✅ Vous voulez une solution unique pour tout

**Restez sur CTAB/SIT si** :
- ✅ Vous êtes < 15 membres
- ✅ Vous jouez occasionnellement
- ✅ Vous n'avez pas besoin de RH/formation
- ✅ Vous préférez des outils séparés simples

---

## 2. Comparaison fonctionnelle

### 2.1 Tableau synthétique

| Domaine | COMSPEC | CTAB | SIT | ATAK réel |
|---------|---------|------|-----|-----------|
| **C2 — Carte tactique** | ✅ Web + Arma | ✅ Arma seul | ✅ Web basique | ✅ Android |
| **C2 — Positions temps réel** | ✅ | ✅ | ✅ | ✅ |
| **C2 — Marqueurs** | ✅ MIL-STD | ✅ Limité | ✅ Limité | ✅ MIL-STD |
| **C2 — Tchat** | ✅ | ✅ | ❌ | ✅ |
| **C2 — Photos intel** | ✅ | ❌ | ❌ | ✅ |
| **C2 — CAS 9-Line** | ✅ Complet | ❌ | ❌ | ✅ |
| **C2 — Ordres formels** | ✅ | ❌ | ❌ | ✅ |
| **C2 — Médical TCCC** | ✅ | ❌ | ❌ | ✅ MEDEVAC |
| **C2 — Logistique** | ✅ | ❌ | ❌ | ✅ |
| **C2 — IFF** | ✅ | ❌ | ❌ | ✅ |
| **C2 — Radio proximité** | ✅ TFAR/ACRE | ❌ | ❌ | ✅ |
| | | | | |
| **RH — ORBAT** | ✅ Complet | ❌ | ❌ | ❌ |
| **RH — Dossiers opérateurs** | ✅ | ❌ | ❌ | ❌ |
| **RH — Grades/progression** | ✅ | ❌ | ❌ | ❌ |
| | | | | |
| **Formation — LMS** | ✅ Complet | ❌ | ❌ | ❌ |
| **Formation — Certifications** | ✅ | ❌ | ❌ | ❌ |
| **Formation — Studio création** | ✅ | ❌ | ❌ | ❌ |
| | | | | |
| **Documents — Bibliothèque** | ✅ | ❌ | ❌ | ❌ |
| **Documents — Courrier officiel** | ✅ + PDF | ❌ | ❌ | ❌ |
| **Documents — Workflow** | ✅ | ❌ | ❌ | ❌ |
| | | | | |
| **Communauté — Forum** | ✅ | ❌ | ❌ | ❌ |
| **Communauté — Événements** | ✅ | ❌ | ❌ | ❌ |
| **Communauté — Recrutement** | ✅ | ❌ | ❌ | ❌ |
| | | | | |
| **Multi-tenant** | ✅ | ❌ | ❌ | ⚠️ |
| **Open source** | ⚠️ Hybride | ✅ GPL v2 | ✅ GPL v2 | ❌ |

### 2.2 Détail C2 avancé

#### Fonctionnalités COMSPEC absentes des autres mods

| Fonctionnalité | Description | Cas d'usage |
|----------------|-------------|-------------|
| **Ordres tactiques formalisés** | Émission, réception, accusés de lecture, statuts | Chaîne de commandement structurée |
| **Alertes médicales auto** | Détection ACE Medical + triage 5 statuts | Chaîne TCCC réaliste |
| **Logistique** | Suivi munitions/carburant/équipement | Friction logistique simulation |
| **IFF (Identification)** | Défi/réponse ami/ennemi | Réduction fratricide |
| **Zones de danger** | Alertes automatiques entrée zone | No-go zones, blue-on-blue |
| **Briefing intégré** | Diapositives depuis web affichées en jeu | Briefing standardisé |
| **Flight Manifest** | Déclaration aéronefs + liaison CAS | Gestion air assets |
| **Codes laser** | Attribution/sync désignateurs | Coordination JTAC |
| **Radio proximité** | Surveillance émissions TFAR/ACRE | Conscience situationnelle comms |

---

## 3. Solutions comparées

### 3.1 CTAB (Commander's Tactical Tablet)

#### Présentation

Mod Arma 3 open source (GPL v2) offrant des tablettes tactiques virtuelles en jeu.

**Créateurs** : Riouken (origine), jetelain/GrueArbre (maintien actuel)  
**Éditions** : cTab+, cTab NSWDG (visual variant)

#### Fonctionnalités

- Tablettes MicroDAGR, Android, Commander, TAD (pilote), FBCB2 (véhicule)
- Carte avec position joueur + unités même camp (local Arma)
- Marqueurs locaux partagés
- Messages entre tablettes
- Flux vidéo UAV
- Waypoints

#### Limites

- ❌ Aucune liaison externe (tout en jeu uniquement)
- ❌ Pas de persistance centralisée
- ❌ Pas de fonctionnalités C2 avancées (ordres, médical, logistique)
- ❌ Pas d'écosystème RH/formation/docs

#### Pour qui ?

- Joueurs découvrant la simulation tactique
- Petites équipes sans besoin de structure
- Préférence pour la simplicité

### 3.2 SIT 1erGTD / cTab IRL

#### Présentation

Extension de CTAB ajoutant une liaison web/mobile pour visualisation déportée.

**Créateurs** : GrueArbre/jetelain (1er GTD)  
**Nouveau nom** : cTAB Connect [BETA]

#### Fonctionnalités

- Tout CTAB de base
- + QR code pour coupler téléphone/tablette
- Visualisation carte depuis mobile/navigateur
- Affichage position temps réel
- Consultation marqueurs mission

#### Limites

- ❌ Scope limité à la visualisation
- ❌ Pas de tchat/pings web
- ❌ Pas de fonctions C2 avancées
- ❌ Pas d'écosystème complémentaire

#### Pour qui ?

- Petites/moyennes unités (< 20 membres)
- Besoin ponctuel commandement déporté
- Infrastructure légère

### 3.3 ATAK (Android Tactical Assault Kit) — Référence militaire

#### Présentation

Application militaire tactique développée par l'US Air Force Research Laboratory pour opérations réelles.

**Variantes** : ATAK (militaire), ATAK-CIV (civil), WinTAK (Windows)

#### Fonctionnalités

- Cartographie offline haute précision
- GPS militaire
- Symbologie MIL-STD-2525 complète
- CoT (Cursor on Target) protocol
- Chat + partage fichiers chiffrés
- Streaming vidéo
- 9-Line CAS + MEDEVAC
- Calculs balistiques
- Extensible (plugins)

#### Limites pour simulation

- ❌ Application Android native (pas mod Arma)
- ❌ Accès restreint (forces armées)
- ❌ Non applicable au jeu

**Rôle** : **Inspiration conceptuelle** pour COMSPEC, pas concurrent direct.

---

## 4. Avantages distinctifs COMSPEC

### 4.1 Écosystème complet vs outils isolés

**COMSPEC** couvre **tout le cycle de vie** de l'unité :

```
Recrutement → Formation → Opérations → RETEX
     ↓            ↓            ↓          ↓
  Formulaires   LMS + Cert   C2 ATAK   Documents
```

**Autres solutions** : Uniquement la partie "Opérations" (C2).

### 4.2 Une seule source de vérité

| Donnée | COMSPEC | CTAB/SIT |
|--------|---------|----------|
| **Indicatifs** | Synchronisés compte ↔ Arma | Saisis manuellement en jeu |
| **ORBAT** | Web + visible en jeu | N/A |
| **Qualifications** | LMS ↔ Rôles C2 | N/A |
| **Historique** | Centralisé SQL | Aucun |
| **Documentation** | Liée aux opérateurs | N/A |

### 4.3 Multi-tenant : Hébergement mutualisé

**COMSPEC** supporte plusieurs organisations isolées sur la même instance.

**Cas d'usage** :
- Fédération d'unités partageant l'infrastructure
- Sous-unités d'une même coalition
- Environnements test/prod séparés

**Autres solutions** : Une installation = une unité.

### 4.4 Roadmap et évolution

**COMSPEC** développe activement des features avancées :

- **P0** (en cours) : Readiness opérationnelle, cycle mission OPORD→AAR, journal tactique unifié
- **P1** (6–12 mois) : Logistique structurée, médical TCCC-lite, communications radio formalisées
- **P2** (long terme) : Doctrine versionnée, XP réaliste, wargaming, AAR assisté IA

**CTAB/SIT** : Maintenance + correctifs, peu de nouveautés majeures.

---

## 5. Recommandations par profil

### 5.1 Unité débutante (< 15 membres)

**Recommandation** : **CTAB**

- Installation simple
- Pas de serveur web requis
- Découverte de la simulation tactique
- Évolution possible vers COMSPEC si croissance

### 5.2 Unité moyenne établie (15–30 membres)

**Recommandation** : **SIT 1erGTD** ou **COMSPEC**

**SIT si** :
- Jeu occasionnel (1–2 fois/semaine)
- Pas de besoins RH/formation formels
- Préférence simplicité

**COMSPEC si** :
- Opérations régulières (3+ fois/semaine)
- Programme de formation en place ou prévu
- ORBAT structuré
- Production documentation (SOP, ordres)

### 5.3 Unité large organisée (> 30 membres)

**Recommandation** : **COMSPEC**

**Pourquoi** :
- À partir de 30+ membres, les besoins RH/formation deviennent critiques
- La gestion manuelle (Discord, Google Docs) ne scale pas
- ROI positif : gain de temps administratif > coût de mise en place

**Alternative** : Patchwork d'outils (Discord + Google Drive + CTAB) mais :
- ❌ Données éparpillées
- ❌ Pas de synchronisation
- ❌ Charge admin élevée

### 5.4 Unité compétitive / réalisme maximal

**Recommandation** : **COMSPEC**

**Pourquoi** :
- C2 avancé (ordres, médical, logistique, IFF)
- Traçabilité complète (audit, RETEX)
- Features inspirées ATAK réel
- Possibilité d'ajouter friction opérationnelle (quotas logistiques, readiness)

---

## 6. Considérations d'implémentation

### 6.1 Complexité d'installation

| Solution | Difficulté | Prérequis | Temps setup |
|----------|------------|-----------|-------------|
| **CTAB** | ★☆☆☆☆ | Arma + CBA | 10 min |
| **SIT** | ★★☆☆☆ | Arma + CBA + serveur web ASP.NET | 1–2h |
| **COMSPEC** | ★★★☆☆ | Arma + CBA + serveur LAMP/MySQL + config | 4–8h (initial) |

**Note** : COMSPEC offre un **assistant d'installation** guidé pour réduire la friction.

### 6.2 Coûts (hors matériel serveur)

| Solution | Licence | Coût communauté | Coût opérateur |
|----------|---------|-----------------|----------------|
| **CTAB** | GPL v2 gratuit | 0€ | 0€ |
| **SIT** | GPL v2 gratuit | 0€ | 0€ |
| **COMSPEC** | Freemium/Premium | 0–XX€/mois selon plan | 0€ |

**COMSPEC Plans** (exemple, à vérifier) :
- **Free** : Limité (max membres, features restreintes)
- **Standard** : ATAK inclus, max XX membres
- **Pro** : Analytics, intégrations avancées
- **Pro+** : Tout illimité

### 6.3 Maintenance

| Aspect | CTAB/SIT | COMSPEC |
|--------|----------|---------|
| **Mises à jour mod** | Manuelles (Workshop) | Manuelles (Workshop) |
| **Mises à jour serveur** | N/A (SIT: auto si Docker) | Auto ou manuelles (dépend hébergement) |
| **Support** | Communauté (forums) | Équipe COMSPEC + communauté |
| **Bugs critiques** | Délai variable | SLA si plan payant |

---

## 7. Conclusion

### 7.1 En résumé

| Critère | COMSPEC | CTAB | SIT |
|---------|---------|------|-----|
| **Scope** | Plateforme complète | Outil tactique | Outil + visualisation |
| **Complexité** | Élevée | Faible | Moyenne |
| **Profondeur C2** | Avancée | Basique | Basique |
| **RH/Formation** | Intégré | Aucun | Aucun |
| **Scalabilité** | > 100 membres | < 20 membres | < 30 membres |
| **Coût** | Freemium/Premium | Gratuit | Gratuit |

### 7.2 Décision finale

**Pas de "mauvais choix"** : Chaque solution répond à des besoins différents.

**Question clé** : **Où en est votre unité dans sa maturité organisationnelle ?**

- **Phase découverte** (< 10 membres, casual) → **CTAB**
- **Phase croissance** (10–30 membres, régulier) → **SIT** ou **COMSPEC** selon ambition
- **Phase maturité** (> 30 membres, structuré) → **COMSPEC**

**Évolution** : Vous pouvez commencer par CTAB/SIT et migrer vers COMSPEC plus tard. Les concepts (marqueurs, positions) sont similaires.

---

## Annexes

### A. Glossaire

| Terme | Signification |
|-------|---------------|
| **C2** | Command and Control (Commandement et Contrôle) |
| **ATAK** | Android Tactical Assault Kit |
| **CTAB** | Commander's Tactical Tablet |
| **SIT** | Système d'Information Tactique |
| **ORBAT** | Order of Battle (organigramme) |
| **LMS** | Learning Management System |
| **TCCC** | Tactical Combat Casualty Care |
| **IFF** | Identification Friend or Foe |
| **CAS** | Close Air Support |
| **JTAC** | Joint Terminal Attack Controller |
| **RETEX** | Retour d'Expérience |
| **SOP** | Standard Operating Procedures |

### B. Liens utiles

| Ressource | Lien |
|-----------|------|
| **COMSPEC Athena** | [athena.ttrd.fr/public](https://athena.ttrd.fr/public) |
| **CTAB+ GitHub** | [github.com/jetelain/cTab](https://github.com/jetelain/cTab) |
| **CTAB Workshop** | [Workshop cTab 1erGTD](https://steamcommunity.com/workshop/filedetails/?id=2262006564) |
| **SIT Workshop** | [Workshop SIT](https://steamcommunity.com/sharedfiles/filedetails/?id=2262009445) |
| **cTAB Connect BETA** | [Workshop](https://steamcommunity.com/sharedfiles/filedetails/?id=3438247879) |
| **SIT Site** | [ctab.plan-ops.fr](https://ctab.plan-ops.fr/) |

### C. Crédits

**COMSPEC** reconnaît ouvertement ses sources d'inspiration :

- **UI tablette** : Design influencé par cTab NSWDG (Fredipedia)
- **Liaison mobile** : Concept de SIT/cTab IRL (GrueArbre/1er GTD)
- **Nomenclature ATAK** : Terminologie inspirée du vrai ATAK

Les parties dérivées de CTAB respectent la **GNU GPL v2**. Voir `CREDITS.md` du mod.

---

**Document rédigé pour COMSPEC MILSIM — Juillet 2026**
