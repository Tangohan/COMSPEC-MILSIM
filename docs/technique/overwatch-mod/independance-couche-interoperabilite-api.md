## Indépendance, couche addons, interopérabilité et API

Document de référence pour **moddeurs**, **intégrateurs serveur** et **développeurs portail** — pack **@COMSPECOverwatch** (version documentée **1.4.11**).

Ce texte complète [Architecture](architecture.md) et [Bibliothèques & dépendances](bibliotheques-et-dependances.md). Il décrit **comment le mod se tient seul**, **comment il s’appuie sur les mods tiers sans les remplacer**, **comment les échanges se font entre écosystèmes**, et **où se situe l’API**.

---

## 1. Notre indépendance

### Principe

COMSPEC Overwatch est un **client tactique autonome** relié au poste de commandement web Athena. Il n’est **ni un fork de cTab**, ni un patch BCE, ni une dépendance runtime à ACE ou KAT pour fonctionner.

Le socle jouable repose sur :

| Composant | Rôle |
|---|---|
| `comspec_overwatch_main` | Métadonnées, branding |
| `comspec_overwatch_connect` | Liaison, hub, rapports, roleplay, SSE natif |
| `COMSPECExtension_x64.dll` | Pont réseau natif (HTTP(S) vers le portail) |
| **CBA_A3** | XEH, settings, socle commun |
| **A3_Modules_F** | Modules Eden (zones roleplay) |

Tout le reste est **optionnel** ou **addon séparé**.

### Ce que le mod possède en propre

Sans cTab ni BCE, un opérateur peut déjà :

- se connecter à Athena (handshake, clé communauté, Steam) ;
- remonter position et métadonnées BFT ;
- utiliser le **hub**, la **tablette** et le **téléphone** Overwatch (UI COMSPEC) ;
- envoyer rapports SALUTE / SPOTREP, POI, MEDEVAC, QRF, ordres, chat ;
- utiliser le **terminal SSE** (dialog natif `connect`, pas l’UI cTab) ;
- bénéficier du **réalisme liaison** (zones, dommages terminal, coupures) ;
- recevoir ordres, alertes et configuration roleplay depuis le portail.

Les assets visuels (overlays, cadres tablette/téléphone) sont **originaux COMSPEC** — pas de recopie d’assets cTab sous licence GPL.

### Addons optionnels isolés

| Addon | Charge si | Sans lui |
|---|---|---|
| `comspec_overwatch_atak_athena` | cTab + BCE présents dans le launcher | Pas de pont tablette ATAK Enhanced ; le reste du pack fonctionne |
| `comspec_overwatch_mavik_compat` | `Mavic_Core` détecté | Pas de shim settings Mavic ; pas d’impact sur Overwatch |

L’addon `atak_athena` déclare explicitement `cTab`, `ctab_core`, `BCE_Core`, `BCE_cTab_ATAK` en `requiredAddons`. Il peut être **retiré du mod pack** sur un serveur minimaliste : le PBO `connect` reste valide.

### Dégradation gracieuse côté SQF

Le cœur `connect` **ne référence jamais** les mods tiers en dur dans ses `requiredAddons`. À l’exécution, chaque intégration est protégée :

```sqf
// Exemple — ACE : sortie immédiate si absent
if (!isClass (configFile >> "CfgPatches" >> "ace_interact_menu")) exitWith { false };

// Exemple — KAT : branche optionnelle après détection ACE
private _hasKam = isClass (configFile >> "CfgPatches" >> "kat_advancedMedical")
    || { isClass (configFile >> "CfgPatches" >> "kat_medical") };
```

Conséquences :

- **ACE** : menus tactiques et réparation ATAK si présent ; sinon accès via hub / raccourcis natifs.
- **KAT** : métriques médicales étendues si présent ; alertes transmissibles basées sur **ACE Medical** uniquement (pas de variables internes KAT obligatoires).
- **ACRE2 / TFAR** : métadonnées radio en BFT si l’un des deux est chargé ; sinon champs radio vides ou absents.
- **cTab / Iceman** : détectés via `fn_detectLoadedMods.sqf` ; les ponts ne s’activent que si l’addon `atak_athena` est chargé **et** le module admin correspondant est activé.

### Indépendance vis-à-vis du portail

- Le mod **ne connaît pas** le schéma SQL ni la logique métier PHP : il parle uniquement à l’**API REST** via la DLL.
- La configuration communauté (modules pont, réalisme, expérience) est **tirée du portail** (`GetModModules`, `GetExperience`, `GetRoleplayConfig`) — pas codée en dur dans le PBO.
- Les tenants historiques et nouveaux sont gérés côté portail ; le mod consomme l’état courant à chaque session.

### Ce que nous ne faisons pas (volontairement)

- Pas de duplication du code ou des dialogs BCE / cTab.
- Pas de remplacement de la tablette cTab par défaut pour les serveurs qui n’utilisent que cTab seul.
- Pas de dépendance à un mod médical particulier pour la liaison Athena.
- Pas d’exposition de secrets, jetons ou mécanismes d’auth dans la doc publique (voir § 4.4).

---

## 2. Notre couche sur les autres addons

### Modèle en strates

```text
┌─────────────────────────────────────────────────────────┐
│  Portail Athena (PHP, UI web, stockage, RBAC)           │
└───────────────────────────┬─────────────────────────────┘
                            │ HTTPS /api/atak/*
┌───────────────────────────▼─────────────────────────────┐
│  COMSPECExtension_x64.dll  (auth, polls, uploads)       │
└───────────────────────────┬─────────────────────────────┘
                            │ callExtension
┌───────────────────────────▼─────────────────────────────┐
│  comspec_overwatch_connect  — socle, UI, roleplay, SSE  │
└───────────────────────────┬─────────────────────────────┘
                            │ variables / events publics
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
  atak_athena          ACE / KAT           ACRE / TFAR
  (cTab, BCE,          (medical,           (radio BFT)
   Iceman ATAK)         interact)
        │
        ▼
  cTab · BCE · Iceman apps · Marker Dropper · Drone Ops…
```

**Règle d’or :** la couche COMSPEC **observe et relaie** ; elle **n’injecte pas** de logique métier dans les mods tiers. Les fonctions pont vivent dans `atak_athena` (`athena_bridge*`, `athena_send*`) ou derrière des guards `isClass` dans `connect`.

### Addon `atak_athena` — pont dédié

Responsabilités :

| Domaine | Fonctions typiques | Source mod tiers |
|---|---|---|
| Marqueurs | `athena_bridgeCtabMarkers` | `cTabUserMarkerList`, `Iceman_ATAK_UserMarkers`, Dropper |
| Photos | `athena_bridgeIcemanPhoto`, `athena_sendPhoto` | Photo Library BCE, Iceman |
| Alertes / BDA | `athena_bridgeIcemanAlert`, `athena_bridgeIcemanBda` | Iceman ATAK Alerts / BDA |
| Météo, route, saut | `athena_bridgeWeather`, `athena_bridgeRoute`, `athena_bridgeJump` | Iceman apps cTab |
| ISR / vidéo | `athena_bridgeDroneContacts`, `athena_bridgeVideoFeeds` | Drone Ops, caméras casque |
| Miroir HQ → cTab | `athena_bridgeComspecSent` | notifications cTab sortantes |

Chaque pont vérifie **deux verrous** avant d’agir :

1. **Module admin** — `["module_id"] call comspec_overwatch_connect_fnc_isModModuleEnabled`
2. **Présence runtime** — variables / patches du mod tiers disponibles

Exemple (marqueurs) :

```sqf
if (!(["ctab_markers"] call comspec_overwatch_connect_fnc_isModModuleEnabled)) exitWith {};
// … lecture cTabUserMarkerList, traduction, envoi SendMarker via extension
```

### Catalogue des modules pont (portail)

Réglables par communauté via `AtakBridgeModulesService` → exposés au jeu par `GET /api/atak/mod-modules` :

| ID module | Sens principal |
|---|---|
| `weather` | Jeu → portail (bandeau météo) |
| `drone` | Contacts ISR → carte |
| `video_feeds` | Caméras → panneau Cams |
| `ctab_markers` | Repères cTab / Dropper → Tacmap |
| `route` | Itinéraire actif → tracé web |
| `jump` | Plan HAHO/HALO → carte |
| `wave_relay` | État MPU-5 → fiches opérateurs |
| `iceman_alerts` | Alertes ATAK Enhanced → TOC |
| `iceman_bda` | BDA → inbox / portail |
| `iceman_photo` | Photos terrain → galerie |
| `iceman_group` | Messages de groupe → messagerie |
| `sse_person` | Fiches SSE → onglet Personnes |
| `comspec_mirror` | Portail → notifications cTab in-game |

Par défaut, tant que le catalogue n’est pas reçu, les modules sont considérés **activés** (`fn_isModModuleEnabled.sqf`) — le staff peut ensuite affiner dans l’administration web.

### Couche médicale et radio (dans `connect`)

| Mod | Couche COMSPEC |
|---|---|
| ACE Medical | Lecture `ace_medical_*` pour état joueur et alertes transmissibles |
| KAT | Enrichissement optionnel (SpO₂, airway, pneumothorax) si patch détecté |
| ACRE2 | Fréquence, net, proximité TX pour BFT |
| TFAR | Repli si ACRE absent |

Ces intégrations restent dans `connect` car elles alimentent le **BFT et les alertes médicales Athena**, pas l’UI cTab.

### Ordre de chargement recommandé

1. CBA_A3  
2. ACE / KAT / ACRE / TFAR / Mavic (selon pack serveur)  
3. cTab / ATAK Enhanced / BCE  
4. **@COMSPECOverwatch** (en dernier)

---

## 3. Interopérabilité

### 3.1 Jeu ↔ portail Athena

Flux bidirectionnel standard :

```text
[Arma client]
  Connect → auth (clé + Steam + indicatif)
  UpdatePosition → Tacmap (positions, groupes, véhicules)
  Polls ← ordres, alertes, CAS, roleplay, modules
  Uploads → photos recon, marqueurs, rapports, SSE
  Session restore ← snapshot court post-déconnexion
```

**Réalisme liaison** : la fonction `fn_canTransmit.sqf` peut bloquer les envois même si le réseau IP fonctionne — le portail reflète alors la dernière position connue ou un état « hors liaison ».

**Reprise session** : `HandleDisconnect` + `GetSessionRestore` évitent les doublons d’indicatif et restaurent l’état terminal après crash serveur ou client.

### 3.2 Jeu ↔ écosystème cTab / BCE / Iceman

| Donnée | Direction | Mécanisme |
|---|---|---|
| Marqueurs utilisateur | Jeu → web | Variables cTab + résolution libellés (ex. Marker Dropper) |
| Photos Photo Library | Jeu → web | BCE records → `UploadReconImage` / pont photo |
| Alertes / BDA Iceman | Jeu → web | Events / fonctions Iceman → API rapports |
| Ordres / alertes HQ | Web → jeu | Poll `GetOrders` / `GetTacticalAlerts` → miroir cTab si `comspec_mirror` |
| Météo mission | Jeu → web | Iceman Weather app → bandeau Tacmap |

Le mod **normalise** les données tierces avant envoi (positions, libellés lisibles, dédoublonnage journal) — le portail ne consomme pas directement le format interne cTab.

### 3.3 Portail ↔ clients tactiques externes

Endpoints **miroir** (`/api/atak/gateways/mirror/*`) et API clé pour outils mobiles ou scripts tiers — même contrat de positions / marqueurs que le client Arma, sans passer par la DLL.

**Intel View**, **SSE**, **waypoints mission** : fonctionnalités web avec contrat API dédié ; le mod implémente la moitié client (extension + SQF).

### 3.4 Contrats stables vs évolutifs

| Stable (intégrateurs) | Évolutif (suivre CHANGELOG) |
|---|---|
| Auth clé + Steam + indicatif | Nouveaux champs JSON position |
| `POST /api/atak/position` | Types d’ordres (`move_waypoint`, etc.) |
| `GET/POST /api/atak/mod-modules` | Modules pont du catalogue |
| Format `OK\|…` / `ERR\|…` extension | Nouvelles fonctions `callExtension` |

Toute évolution breaking est documentée dans `CHANGELOG-ATAK.md` et `@COMSPECOverwatch/CHANGELOG.md`.

### 3.5 Interop scénario typique

1. Zeus pose une zone sans couverture (module Eden COMSPEC).  
2. Opérateur sur cTab pose un repère « hélico » via Marker Dropper.  
3. Pont `ctab_markers` traduit le libellé et envoie le marqueur au portail.  
4. Chef de mission place un **point de mission** sur la Tacmap web.  
5. Ordre `move_waypoint` pollé par le client → marqueur in-game + notification cTab si miroir actif.  
6. Opérateur enregistre une personne SSE → fiche visible onglet Personnes (module `sse_person`).

---

## 4. Notre API

L’API Overwatch se lit sur **deux niveaux** : l’**extension native** (côté Arma) et l’**API REST** (côté portail). Les deux sont implémentées pour le client officiel ; l’API REST est aussi consommable par des intégrateurs autorisés.

### 4.1 Extension native — `callExtension "COMSPECExtension"`

Point d’entrée unique depuis le SQF. Format de retour :

```text
OK|<payload>     — succès (payload souvent lignes TAB ou texte structuré)
ERR|<code>       — échec (unauthorized, forbidden, http_4xx, …)
```

Callbacks asynchrones (`ExtensionCallback`) : connexion, erreur réseau, coupure, etc.

#### Familles de fonctions (référence)

| Famille | Exemples | Rôle |
|---|---|---|
| **Session** | `Connect`, `RedeemGameLink`, `LinkBySteam`, `RegisterBeta` | Auth et enregistrement |
| **Lecture (poll GET)** | `GetOrders`, `GetTacticalAlerts`, `GetMedicalAlerts`, `GetChatMessages`, `GetRoleplayConfig`, `GetModModules`, `GetExperience`, `GetSessionRestore`, `GetBriefingSlides`, `GetMarkers`, `GetUnits` | Tirer l’état portail |
| **Écriture (POST)** | `UpdatePosition`, `SendChat`, `SendMarker`, `SendIntel`, `SubmitTacticalReport`, `CreatePOI`, `RequestMEDEVAC`, `RequestQRF`, `UpdateOrderStatus`, `UploadReconImage`, `UploadSsePhoto`, `SubmitSsePerson` | Pousser données jeu |
| **CAS / feu** | `FireSupport.Request`, `GetCASForCallsign`, `SendCASState`, `SendCASAck`, `SyncLaserCode` | Appui aérien |
| **Réalisme** | `RegisterTerminal`, `RegisterCertificate`, `CompromiseTerminal`, `GetTerminalRealism` | Terminaux et certificats |
| **Utilitaires** | `GetVersion`, `LogWrite`, `ReportDiag`, `LoadGoogleDeck` | Diagnostic, briefing slides |

Implémentation : `mod/UptoDate/COMSPECExtension/Extension.cs`.

Le SQF ne parse pas de JSON lourd : la DLL **simplifie** les réponses API en lignes tabulées quand c’est possible (`SimplifyModModulesJson`, etc.).

### 4.2 API REST portail — préfixe `/api/atak/`

Authentification :

| Contexte | Mécanisme |
|---|---|
| Client Arma (DLL) | Clé API communauté (`ComspecApiKeyAuth`) + identité Steam / session jeu |
| Écritures Arma | `AtakArmaWriteGuard` (vérifie cohérence Steam, indicatif, tenant) |
| Admin web | Session utilisateur + permission `admin.access` |
| Scripts externes | Clé API + contrat documenté par endpoint |

#### Domaines principaux

| Domaine | Méthodes (extrait) | Usage |
|---|---|---|
| **Présence & BFT** | `POST /position`, `GET /presence`, `GET /units` | Carte tactique |
| **Messagerie** | `GET/POST` activity, chat via extension | TOC, historique |
| **Ordres** | `GET/POST /orders`, `PATCH /orders/{id}/status` | Ordres mission, waypoints |
| **Alertes** | `GET /tactical-alerts`, `GET /medical-alerts`, triage | TOC alertes |
| **Rapports** | `GET/POST /reports`, SALUTE, POI | Intel structuré |
| **CAS & laser** | laser-codes, designator, flight-manifest, air-assets | Feu et aviation |
| **Logistique** | `GET /logistics`, resupply | Snapshot logistique |
| **MEDEVAC / QRF** | CRUD dédiés | Demandes médicales et QRF |
| **Roleplay** | `GET /roleplay-stats`, terminals, certificates | Réalisme liaison |
| **Config pont** | `GET/POST /mod-modules`, `GET /experience` | Modules et expérience |
| **Marqueurs** | `GET /markers`, `POST /marker` | Repères web ↔ jeu |
| **Waypoints** | `/waypoint-routes` | Routes mission web |
| **Gateways** | `/gateways/mirror/units`, `…/markers` | Clients tactiques tiers |
| **SSE** | `/api/sse/persons` (+ photos, biometrics-sim) | Renseignement interpersonnel |

Routes complètes : `routes/web.php` (bloc `/api/atak/` et `/api/sse/`).

Contrat SSE détaillé : `mod/UptoDate/docs/contrat-api-sse.md`.

### 4.3 Chaîne d’appel type (marqueur cTab → web)

```text
cTabUserMarkerList (BCE/Iceman)
  → fn_athena_bridgeCtabMarkers.sqf
  → ["SendMarker", […]] callExtension "COMSPECExtension"
  → POST /api/atak/marker
  → AtakDataRepository + libellé ArmaMarkerLabel côté PHP
  → Tacmap + journal TOC
```

### 4.4 Périmètre non documenté ici

Conformément à la politique doc mod :

- détails cryptographiques des certificats et jetons ;
- rotation des secrets et clés API ;
- schémas SQL complets (voir migrations bootstrap et contrats annexes) ;
- procédures d’attaque ou contournement du réalisme liaison.

Pour une intégration tierce, contacter l’équipe COMSPEC avec le **cas d’usage**, le **sens des flux** (lecture / écriture) et la **communauté cible** — les endpoints et champs exacts évoluent avec le CHANGELOG.

---

## 5. Fichiers de référence dans le dépôt

| Sujet | Chemin |
|---|---|
| Config addons | `mod/UptoDate/Sources/comspec-overwatch-addons/*/config.cpp` |
| Détection mods | `connect/functions/fn_detectLoadedMods.sqf` |
| Gate modules | `connect/functions/fn_isModModuleEnabled.sqf` |
| Pont cTab | `atak_athena/functions/fn_athena_bridge*.sqf` |
| Extension C# | `mod/UptoDate/COMSPECExtension/Extension.cs` |
| Catalogue modules | `app/Services/Tactical/AtakBridgeModulesService.php` |
| Contrôleur API | `app/Controllers/Api/AtakApiController.php` |
| Auth clé | `app/Support/ComspecApiKeyAuth.php` |
| SSE API | `app/Controllers/Api/SseApiController.php` |
| Historique | `CHANGELOG-ATAK.md` |

---

## 6. Voir aussi

- [Architecture](architecture.md)
- [Bibliothèques & dépendances](bibliotheques-et-dependances.md)
- [Compilation](compilation.md)
- `mod/UptoDate/docs/architecture-et-addons.md` — copie source équipe mod
- `mod/UptoDate/docs/contrat-api-sse.md` — contrat SSE
- `mod/UptoDate/docs/realisme-liaison-atak.md` — réalisme terminal
