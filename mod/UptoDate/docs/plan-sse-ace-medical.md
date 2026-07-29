# Plan — Couche SSE sur l’interaction ACE (médical / capture)

Objectif : permettre d’ouvrir et d’alimenter une **fiche SSE depuis un blessé / un détenu**, via le menu ACE, sans passer par le téléphone ATAK.

Base de départ : version **1.4.11**. Contrat technique existant : [contrat-api-sse.md](contrat-api-sse.md). Vision produit : [terminal-sse-renseignement.md](terminal-sse-renseignement.md).

---

## 1. Constat — ce qui est déjà en place

Vérifié dans le dépôt, avec les points d’ancrage exacts.

| Composant | Emplacement | État |
|---|---|---|
| Dialog SSE (IDD 9991) | `Sources/…/connect/display_sse_person.hpp`, inclus par `connect/config.cpp:450` | Livré 1.4.0 |
| Ouverture du terminal | `connect/functions/fn_ssePersonDialogShow.sqf` | **Accepte déjà `[_target]` en paramètre** |
| Préremplissage Eden / inventaire / ACE restrain | `fn_ssePersonDialogOnLoad.sqf` | Livré |
| Soumission fiche + photo + biométrie | `fn_ssePersonDialogSubmit.sqf` | Livré |
| Commandes extension | `COMSPECExtension/Extension.cs` — `SubmitSsePerson`, `UploadSsePhoto`, `SubmitSseBiometricsSim` | Livré |
| API REST | `app/Controllers/Api/SseApiController.php` (index / store / show / photos / biometrics-sim) | Livré |
| Portail classifié | `/atak/sse` — `SsePortalController`, croisements, PDF | Livré |
| Module optionnel `sse_person` | `app/Services/Tactical/AtakBridgeModulesService.php:72`, condition `fn_initATAKMenu.sqf:146` | Livré |
| Accès actuel | ACE → sous-menu ATAK → « Enregistrer une personne » (`fn_initATAKMenu.sqf:155`) | Sur soi, pas sur un blessé |

**Conséquence pratique** : `ssePersonDialogShow` prenant déjà une cible, la couche ACE sur autrui est essentiellement une **action d’interaction supplémentaire**, pas une refonte d’UI.

Le patron exact à recopier existe déjà : `fn_initACE.sqf:268-292` (« Saisir l’ATAK ») ajoute une action sur `CAManBase` via `ace_interact_menu_fnc_addActionToClass`, avec conditions distance / inconscient / captif.

---

## 2. Refonte proposée — packaging

### Ce qui est écarté : un mod séparé `@COMSPECOverwatch_SSE_ACE`

Trois raisons factuelles :

1. Le dialog SSE, les trois fonctions et les libellés vivent dans **`connect.pbo`**. Un mod séparé devrait soit les dupliquer, soit dépendre en dur de `comspec_overwatch_connect` — donc il n’est de toute façon **pas utilisable sans Overwatch**.
2. Les commandes réseau (`SubmitSsePerson`…) sont dans **`COMSPECExtension_x64.dll`**, livrée dans `@COMSPECOverwatch`. Un second dossier `@` n’emporte pas la DLL.
3. Coût réel : second item Workshop, second cycle de build (`build_mod.bat`), second changelog, second point de désynchronisation de version. Pour ~1 fichier SQF.

### Ce qui est proposé : un **PBO** `sse_ace` dans `@COMSPECOverwatch`

```text
Sources/comspec-overwatch-addons/
├── main/
├── connect/
├── atak_athena/
├── mavik_compat/
└── sse_ace/          ← nouveau : couche interaction SSE (ACE)
```

Préfixe : `z\comspec_overwatch\addons\sse_ace`.

| Propriété | Traitement |
|---|---|
| Optionnel | Retirer le PBO du dossier `addons` → la couche disparaît, le reste fonctionne |
| ACE absent | **Garde runtime**, pas `requiredAddons[] = {"ace_interact_menu"}` — une dépendance dure sort une popup d’erreur au démarrage chez les joueurs sans ACE. On copie la garde de `fn_initACE.sqf:2-10` (`isClass (configFile >> "CfgPatches" >> "ace_interact_menu")` + sortie silencieuse journalisée) |
| Overwatch absent | `requiredAddons[] = {"comspec_overwatch_connect"}` — dépendance légitime, même pack |
| Désactivation par communauté | Réutilise le module existant **`sse_person`** (aucune nouvelle entrée de configuration tenant) |
| Désactivation par mission | Setting CBA `comspec_sse_ace_enabled` (défaut : activé) |

Si l’on veut malgré tout un `@` séparé, c’est faisable — mais il faudra assumer la duplication du dialog et documenter que le pack principal reste obligatoire.

---

## 3. Intégration — Option A retenue

### Option A (retenue) — actions ACE sur autrui

Nœud `COMSPEC_SSE` (« Renseignement SSE ») greffé sur `["ACE_MainActions"]` de `CAManBase`, avec quatre entrées :

| Entrée | Condition | Action |
|---|---|---|
| Ouvrir la fiche SSE | toujours (si nœud visible) | `[_target] call …fnc_ssePersonDialogShow` |
| Scanner les empreintes | fiche existante **ou** création implicite | progression + `SubmitSseBiometricsSim` |
| Photographier le visage | joueur face à la cible | réutilise le chemin photo existant |
| Prélèvement ADN (simulation) | cible inconsciente / décédée / menottée | lot 3 |

Condition d’affichage du nœud :

```text
module sse_person actif
ET setting CBA actif
ET terminal ATAK possédé (fnc_hasTerminal)
ET cible ≠ joueur, isKindOf CAManBase
ET distance < 4 m
ET (inconsciente OU menottée OU décédée OU civile non hostile)
```

**Aucune modification de l’UI ACE. Aucun IDC ACE / KAT touché.**

### Option B (écartée) — onglet dans l’écran médical ACE/KAT

L’écran médical est un display ACE dont les IDC changent entre versions, et que KAT surcharge. Y injecter un onglet, c’est accepter une casse à chaque montée de version ACE ou KAT — pour un gain d’immersion qui existe déjà avec l’option A (mêmes 4 gestes, un cran plus haut dans le menu).

**Compromis proposé si l’immersion prime** : détection runtime du nœud d’interaction médical d’ACE et, s’il existe, greffe d’une **copie du sous-menu SSE** à cet endroit — sans jamais lire ni écrire dans le display médical. Si le nœud n’existe pas (version ACE différente), on retombe silencieusement sur `ACE_MainActions`. Coût : ~2 h, risque quasi nul, échec dégradé et non bloquant.

---

## 4. Contraintes techniques relevées dans le code

À traiter, sinon le lot 1 ne fonctionnera pas correctement.

### 4.1 Une condition ACE ne doit jamais faire d’appel réseau

Le code `condition` d’une action ACE est évalué **à chaque frame** tant que le menu est ouvert. Le statut « fiche existante / pas de fiche » ne peut donc pas venir d’un appel HTTP dans la condition.

Modèle imposé :

- Cache `uiNamespace` / `missionNamespace` : `netId → { personId, statut, horodatage }`.
- La condition lit **uniquement** le cache (retour immédiat, jamais `nil`).
- L’interrogation réseau se fait à l’**exécution** d’une action (ouverture de fiche), ou via un rafraîchissement groupé à faible fréquence.
- Cache vide = libellé neutre « Fiche SSE… », jamais « Pas de fiche » (une absence de réponse n’est pas une absence de fiche).

### 4.2 La recherche de fiche par unité n’existe pas

`target_unit_netid` est **écrit** mais jamais **lu** :

- `SsePersonRepository::listForContext` (`app/Repositories/SsePersonRepository.php:178`) ne filtre que `status`, `since_id`, `limit`, `offset`.
- La colonne `target_unit_netid VARCHAR(64)` (`bootstrap/atak_sse_persons_migration.php:67`) **n’a pas d’index**.

À ajouter : filtre côté dépôt + endpoint de consultation + index `(tenant_id, context_id, target_unit_netid)`.

### 4.3 Les corps décédés sont exclus du préremplissage

- `fn_ssePersonDialogShow.sqf:14` — reprise du curseur conditionnée à `alive _cursor`.
- `fn_ssePersonDialogOnLoad.sqf:43` — **tout** le préremplissage (identité Eden, armes, équipement, statut) est sous `alive _target`.

Or l’exploitation d’un corps est un cas SSE central. Correctif à intégrer au lot 0 : accepter `_target isKindOf "CAManBase"` sans condition de vie, et n’utiliser `alive` que pour choisir le libellé affiché (« Personne décédée » vs cible vivante).

### 4.4 L’ADN n’est pas accepté par l’API

`SseApiController::personsBiometricsSim` (ligne 272) restreint `kind` à `['empreintes', 'iris']` et retombe sur `empreintes` sinon. Ajouter `adn` à la liste blanche, sinon le lot 3 enregistrera silencieusement le mauvais type.

### 4.5 Aucune colonne pour le contexte médical

`sse_persons` n’a pas de champ pour l’état ACE. Deux voies :

- **Retenue** : nouvelle colonne `medical_context_json` (migration additive, `NULL` par défaut) + rendu portail.
- Écartée : injection dans `statements` — pollue les déclarations et casse le PDF.

### 4.6 Le croisement watchlist ne remonte pas en jeu

`SseCrossMatchService` tourne **à la demande depuis le portail** (`/atak/sse/croisements`), seuil de score 60, sur nom / prénom / alias uniquement. Aucun retour vers Arma aujourd’hui.

Position roleplay recommandée : **ne pas** afficher de résultat watchlist instantané dans le terminal. Un « correspondance recherchée » immédiat à la seconde où la fiche part détruit le rôle du TOC. Si retour il y a, il passe par la messagerie / les alertes existantes, avec délai, et à l’initiative du TOC.

### 4.7 Règle 1.4.8 — déjà respectée

`fn_getMedicalState.sqf` renvoie `spo2 / airway / pneumothorax` figés à `"-"`. La couche SSE consomme cette fonction telle quelle : **aucune donnée KAT n’est exposée** sur le terminal SSE. KAT reste utilisé en parallèle pour le soin.

---

## 5. Lots redécoupés

L’estimation initiale (MVP 1–2 j) sous-évalue le lot « fiche existante » (traverse SQF + DLL + PHP + migration, donc rebuild de la DLL et déploiement portail) et surévalue le MVP (le dialog accepte déjà une cible).

| Lot | Contenu | Portée | Estimation |
|---|---|---|---|
| **L0 — MVP** | PBO `sse_ace`, nœud d’interaction sur `CAManBase`, ouverture fiche sur cible, correctif corps décédés | SQF + build | **0,5 j dev + 0,5 j test in-game** |
| **L1 — Fiche existante** | Recherche par `target_unit_netid`, cache SQF, libellé « Fiche #124 — Civil » vs « Pas de fiche » | SQF + DLL + PHP + migration (index) | **1,5–2 j** |
| **L2 — Contexte médical** | `medical_context_json`, préremplissage signes distinctifs depuis les blessures ACE, rendu portail | SQF + PHP + migration | **1 j** |
| **L3 — Biométrie** | `sse_biometric_samples` (empreintes / iris / ADN), barre de progression, référence labo fictive, affichage portail | Complet | **1,5 j** |

Total ≈ 5 jours si les quatre lots sont menés. L0 est livrable et utile seul.

---

## 6. Détail par lot

### L0 — MVP

**Créer**

- `Sources/comspec-overwatch-addons/sse_ace/config.cpp` — `CfgPatches` (`requiredAddons[] = {"comspec_overwatch_connect"}`), `CfgFunctions`, setting CBA `comspec_sse_ace_enabled`.
- `sse_ace/XEH_postInit.sqf` — garde ACE runtime + appel de l’init.
- `sse_ace/functions/fn_initSseAce.sqf` — nœud `COMSPEC_SSE` + entrée « Ouvrir la fiche SSE » (patron `fn_initACE.sqf:268-292`).
- `sse_ace/functions/fn_sseCanExploit.sqf` — condition unique et centralisée (module, setting, terminal, distance, état de la cible), retour booléen strict.

**Modifier**

- `connect/functions/fn_ssePersonDialogShow.sqf` — accepter une cible décédée.
- `connect/functions/fn_ssePersonDialogOnLoad.sqf` — sortir le préremplissage de la garde `alive` ; libellé « Personne décédée » le cas échéant.
- `mod/UptoDate/build_mod.bat` — entrée de build du cinquième PBO.

**Recette**

Cible vivante / inconsciente ACE / menottée / décédée ; sans ACE chargé (aucune erreur, aucun menu) ; module `sse_person` désactivé (nœud absent) ; hors liaison Athena (message d’échec propre, déjà géré par `fn_ssePersonDialogSubmit.sqf:125`).

### L1 — Fiche existante

**Base de données** — migration additive : index `(tenant_id, context_id, target_unit_netid)` sur `sse_persons`.

**PHP**

- `SsePersonRepository::findByTargetUnit(tenant, context, netId)`.
- `SseApiController` : `GET /api/sse/persons/by-unit?netid=…&mapId=…` → `{ "person": {…} | null }`, 200 dans les deux cas (l’absence de fiche n’est pas une erreur).
- Route dans `routes/`, même garde d’authentification que les autres endpoints SSE.

**Extension** — commande `LookupSsePersonByUnit` (synchrone, format `OK|id|statut` / `OK|` si aucune fiche), au format des autres retours (`OK|payload`).

**SQF**

- `fn_sseFicheCache.sqf` — lecture / écriture du cache, TTL court, invalidation après soumission.
- Libellé dynamique de l’action ; condition alimentée **uniquement** par le cache (cf. 4.1).

**Point ouvert** : `netId` n’est pas stable après un respawn joueur. Pour les PNJ de scénario — le cas d’usage réel — il l’est pour la session. Traitement retenu : pas de garantie affichée, le lien reste indicatif ; le rapprochement de référence reste l’identité + la photo.

### L2 — Contexte médical

- Migration : `sse_persons.medical_context_json JSON NULL`.
- SQF `fn_sseCollectMedical.sqf` — consomme `fnc_getMedicalState` (jamais les variables ACE en direct) : conscient / inconscient / arrêt cardiaque, fréquence cardiaque, douleur, horodatage de constat.
- Résumé des blessures : lecture **défensive** des blessures ACE, uniquement pour en tirer des localisations (bras gauche, torse…) alimentant « signes distinctifs ». Toute lecture est encapsulée dans une fonction unique qui renvoie `""` en cas de structure inattendue — les variables de blessures ACE changent de forme entre versions.
- Portail : bloc « Contexte au moment du relevé » sur la fiche, mention explicite « constat de terrain, non médical ».
- **Interdit** : SpO2, voies aériennes, pneumothorax, données KAT (règle 1.4.8).

### L3 — Biométrie

- Table `sse_biometric_samples` : `id`, `tenant_id`, `person_id`, `kind` (`empreintes` | `iris` | `adn`), `quality` (indice simulé), `lab_reference` (référence fictive, ex. `LAB-2026-0412`), `operator_callsign`, `pos_x/y/z`, `created_at`. Clé étrangère `person_id` en cascade, comme `sse_person_photos`.
- API : `POST /api/sse/persons/{id}/biometrics-sim` étendu — `adn` ajouté à la liste blanche (cf. 4.4), création d’un échantillon en plus du drapeau `biometrics_simulated` et de l’événement `sse_custody_events` existant.
- SQF : barre de progression ACE (`ace_common_fnc_progressBar`), durée par type (empreintes ~8 s, iris ~10 s, ADN ~20 s), annulation si la cible bouge ou si l’opérateur s’éloigne.
- Portail : liste des prélèvements sur la fiche + reprise dans le PDF de dossier.

---

## 7. Modèle cible

```text
Médecin / opérateur ouvre le menu ACE sur la cible
  → Renseignement SSE
      ├─ « Pas de fiche »        → [Ouvrir la fiche SSE]  → dialog 9991 prérempli
      └─ « Fiche #124 — Civil »  → [Ouvrir la fiche SSE]  → dialog + rappel du dossier
  → Scanner empreintes / Photo visage / ADN (simulation)
  → SubmitSsePerson + biométrie (netId, position, contexte médical)
  → Portail SSE — croisement watchlist à l’initiative du TOC, jamais en retour immédiat
```

---

## 8. Hors scope volontaire (inchangé)

Reconnaissance faciale, lecture d’iris réelle, OCR de pièce d’identité, fusion avec le dossier RH des membres. Ce sont des simulations roleplay : barre de progression, référence de laboratoire fictive, rendu portail.

---

## 9. Décisions prises et état

| Point | Décision | État |
|---|---|---|
| PBO `sse_ace` ou intégration dans `connect` | PBO séparé, dans `@COMSPECOverwatch` | **Fait** (L0) |
| Compromis Option B (greffe sur le nœud médical ACE) | **Écarté** — la sonde de config n’est pas fiable : ACE ajoute une partie de ses actions médicales au runtime, pas en config. Une greffe non vérifiable est exactement la fragilité que l’option B devait éviter | Abandonné |
| Retour watchlist en jeu | Hors L1, à l’initiative du TOC | Inchangé |

### Livré en L0

- PBO `sse_ace` : `config.cpp`, `CfgEventHandlers.hpp`, `XEH_preInit.sqf` (réglage CBA), `XEH_postInitClient.sqf`, `fn_initSseAce.sqf`, `fn_sseCanExploit.sqf`, `fn_sseExploitTargetLabel.sqf`.
- Nœud `COMSPEC_SSE` sur `ACE_MainActions` de `CAManBase`, libellé contextuel via `modifierFunction`.
- Correctif corps décédés dans `fn_ssePersonDialogShow` / `fn_ssePersonDialogOnLoad`.
- Entrée de build du cinquième PBO dans `build_mod.bat`.

**Non exécuté** : compilation PBO et recette in-game (chaîne Windows / AddonBuilder absente de l’environnement de développement). Les fichiers passent le contrôle statique SQF (précédence de condition, équilibrage) et l’équilibrage des accolades de config.

### Reste à faire

L1 (fiche existante), L2 (contexte médical), L3 (biométrie) — inchangés, sections 5 et 6.
