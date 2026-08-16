# ACRE / COMMS — vision ATAK · SSE · SIGINT

Date : 2026-08-16  
Statut : **spécification cible** (pas encore un lot livré)

## Principe

```text
compat_acre (soft-load, zéro dépendance hard du core)
        │
        ├── Overwatch BFT (déjà partiel) ──► ATAK C2 radio
        ├── bus CBA COMSPEC_SSE_* ─────────► SSE exploitation physique
        └── événements SIGINT contrôlés ──► DF / emitters / couverture
```

- Le **cœur SSE** ne dépend jamais d’ACRE (même règle que TFAR / cTAB / UAV).
- ACRE reste une **source interchangeable** ; TFAR peut fournir un sous-ensemble.
- **Gameplay renseignement** : aucune lecture automatique massive des radios ennemies via API pour les montrer au joueur. L’exploitation SSE exige une **action terrain** (fouille / ACE « Exploiter radio » / découverte scénarisée).

## État actuel (audit)

| Domaine | Existant | Manquant vs vision |
|---------|----------|-------------------|
| ATAK live | Overwatch : `radio_tx`, `radio_freq`, canal, pastilles TX, onglet Radio proximité (`docs/technique/radio-proximite-overwatch.md`) | Fiche opérateur multi-postes, dBm, LINK GOOD/…, layer COMMS coloré, Network View, matrice de connectivité |
| SSE | Générateur PRC-152/148/343, ACE exploit **local** (hint), labels source `ACRE`/`TFAR` dans le contrat LOT 1 | `compat_acre`, remonte Athena, presets découverts → SIGINT d’intérêt, fiche radio dossier |
| SIGINT | `atak_sigint_reports` + cercles heuristiques | Fréquence, dBm, ellipse DF multi-capteurs, jamming, couverture pré-mission, GSA |
| Bridges | `compat_ace`, `compat_bii` seulement | `compat_acre` / `compat_tfar` (doc LOT 1 uniquement) |

## Contrat payload opérateur (cible)

Envelope BFT / bus (JSON) — une unité, N radios :

```json
{
  "unit": "VIPER 1-1",
  "radios": [
    {
      "id": "acre_radio_42",
      "type": "ACRE_PRC152",
      "label": "AN/PRC-152",
      "role": "PRIMARY",
      "active": true,
      "channel": 3,
      "frequency_mhz": 42.5,
      "band": "SR",
      "volume": 0.8,
      "powered": true,
      "tx": false,
      "rx": false,
      "tx_power_w": null,
      "antenna": null,
      "signal_dbm": -71,
      "link": "GOOD"
    }
  ],
  "updated_at": "2026-08-16T20:12:00Z"
}
```

### Champs métier (libellés UI, pas de jargon brut côté joueur)

| Donnée | Usage |
|--------|--------|
| Type de poste | PRC-343, PRC-152, PRC-148, PRC-117F, SEM, … |
| Radio active | Poste sélectionné pour TX |
| Canal / preset | Distinct par radio (modèle ACRE) |
| Fréquence | Prioritaire ATAK ; SR / LR selon poste |
| Volume / ON-OFF | États ACRE |
| Puissance d’émission | Si exposée (ex. SEM 70 0,4 W / 4 W) |
| Antenne | Composant ACRE (ex. GSA) |
| Signal reçu | dBm ; typiquement ~0 à −100 ; seuils radio-dépendants |
| État de liaison | `GOOD` / `DEGRADED` / `MARGINAL` / `LOST` — dérivé signal + sensibilité, **sans inventer** une valeur absente d’ACRE |
| PTT TX / RX | Indicateurs temporaires carte |
| Réseau logique | Regroupement fréquence + canal + type |
| Rack / intercom | Statut véhicule / équipage (lot ultérieur) |
| GSA | Asset COMMS sur carte si déployée |

Mapping LINK (indicatif) :

| Condition | LINK | Couleur layer |
|-----------|------|---------------|
| powered=false / données absentes | UNKNOWN | GREY |
| au-dessus seuil « bon » | GOOD | GREEN |
| entre seuils | DEGRADED / MARGINAL | AMBER |
| sous sensibilité / hors portée modèle | LOST | RED |

## ATAK — trois surfaces

### 1. Fiche radio (opérateur)

```text
VIPER 1-1
────────────────────
COMMS        ONLINE

PRIMARY
AN/PRC-152
CH 03
42.500 MHz
SIGNAL       -71 dBm
LINK         GOOD

LONG RANGE
AN/PRC-117F
CH 07
55.750 MHz
LINK         DEGRADED

TX           NO
LAST UPDATE  4 s
```

Réutiliser / étendre `atak-radio.js` + métadonnées `extra` Overwatch (aujourd’hui mono-radio).

### 2. Layer COMMS

Icônes simples : GREEN / AMBER / RED / GREY selon LINK.  
Calque dédié (catalogue LOT 5 étendu : `comms`) — distinct du calque SIGINT.

### 3. Radio Network View + matrice

```text
NET 42.500 MHz
├── VIPER 1-1 … VIPER 1-4

NET 55.750 MHz
├── TOC · VIPER 1-1 · EAGLE 1 · REAPER 1
```

Matrice de connectivité (✓ / ~ / ✕) calculée côté Athena à partir des `signal_dbm` / LINK croisés **quand ACRE expose la relation**, sinon « inconnu » (pas de portée inventée).

API proposée : `GET /api/atak/radio-nets?mapId=` (+ détail `GET …/radio-nets/{netId}/matrix`).

## SSE — exploitation physique uniquement

Découverte terrain → fiche dossier :

```text
RADIO-0042 · AN/PRC-152
CURRENT CHANNEL 07 · 48.325 MHz
PRESETS DISCOVERED 01…07
POWER ON
SOURCE Physical exploitation
CONFIDENCE A1
```

- Brancher `fn_doExploitRadio.sqf` → `raiseSseEvent` (`COMSPEC_SSE_RADIO_EXPLOITED`, `source_system=ACRE` ou `ARMA_SSE` si radio scénarisée).
- Transfert **manuel / workflow** des fréquences vers SIGINT « fréquences d’intérêt » — pas d’auto-dump de toutes les radios ennemies.
- Identité / confiance : jamais `CONFIRMED` auto (règle LOT 1).

## SIGINT / EW (cible avancée)

| Capacité | Entrée ACRE / capteur | Sortie Athena |
|----------|----------------------|---------------|
| Emitter detected | freq, bearing, dBm, sensor id, time | Contact SIGINT |
| Multi-écoute | ≥2 bearings / positions | Ellipse d’incertitude |
| DF simulé | capteurs désignés | Zone probable |
| Jamming | hook signal custom ACRE | Zone / statut COMMS dégradé |
| Couverture pré-mission | modèle propagation (LOS / multipath / Longley-Rice si dispo) | Carte GREEN/AMBER/RED + proposition relais / GSA |

Enrichir `atak_sigint_reports` : `frequency_mhz`, `signal_dbm`, `sensor_id`, `uncertainty_m`, `source_system`, `payload` JSON.

**Règle** : un joueur allié en PTT peut alimenter un contact SIGINT **seulement** si un capteur / mode SIGINT / règle Zeus l’autorise — pas de miroir global des TX ennemis.

## Bridge `compat_acre` (structure)

```text
addons/compat_acre/
  config.cpp                 # soft-load ; requiredAddons sans acre_main hard si possible
  XEH_preInit.sqf
  XEH_postInitClient.sqf
  functions/
    fn_detectACRE.sqf
    fn_getRadios.sqf
    fn_getActiveRadio.sqf
    fn_getChannel.sqf
    fn_getFrequency.sqf
    fn_getSignal.sqf
    fn_getTxState.sqf
    fn_buildCommsPayload.sqf
    fn_publishComms.sqf      # → Overwatch extra + optionnellement bus SSE
```

Événements bus (noms figés) :

- `COMSPEC_SSE_RADIO_TX_STARTED` / `COMSPEC_SSE_RADIO_TX_ENDED`
- `COMSPEC_SSE_RADIO_EXPLOITED`
- `COMSPEC_SSE_RADIO_NET_OBSERVED`
- `COMSPEC_SSE_SIGINT_HIT` (si capteur autorisé)

Réutiliser Overwatch existant (`fn_getRadioTxState`, `acre_remoteStartedSpeaking`) — le bridge SSE **ne duplique pas** le moniteur BFT ; il normalise et publie.

## Découpage recommandé (lots)

| Lot | Contenu | Critère done |
|-----|---------|--------------|
| **A — Payload & bridge** | `compat_acre` + payload multi-radios dans BFT `extra` ; rétrocompat champs plats `radio_*` | Un PRC-152 actif → fiche PRIMARY avec freq/ch/TX |
| **B — ATAK COMMS UI** | Fiche opérateur, layer COMMS, Network View basique (liste nets) | GREEN/AMBER/RED + nets par fréquence |
| **C — Matrice & GSA** | Matrice connectivité ; asset GSA sur carte si détecté | Matrice ✓/~ /✕ sans inventer hors données |
| **D — SSE exploit** | ACE exploit → event → dossier + presets → file d’attente SIGINT | Pas d’auto-scan ennemi ; A1 manuel |
| **E — SIGINT DF** | Reports enrichis, ellipse multi-capteurs, contact carte | Ellipse avec ≥2 observations |
| **F — Couverture / EW** | Carte couverture pré-mission, jamming, relais | Couverture reflète modèle ACRE ou marquage « estimation » |

Ordre de dépendance : A → B → (C ‖ D) → E → F.

## Hors scope explicite (pour ne pas dériver)

- Audio dans le navigateur (déjà exclu — écoute en jeu).
- Remplacer BII / ACE.
- Forcer ACRE si la mission est TFAR-only (subset TFAR documenté).
- Afficher automatiquement toutes les radios IA ennemies.

## Fichiers d’ancrage existants

- Overwatch radio : `mod/UptoDate/Sources/comspec-overwatch-addons/connect/functions/fn_getRadioTxState.sqf`, `fn_initRadioMonitor.sqf`
- Web : `public/assets/js/atak-radio.js`, pastilles `atak-map.js`
- SSE exploit local : `mod/@COMSPEC_SSE/addons/interaction/functions/fn_doExploitRadio.sqf`
- SIGINT : `AtakApiController::sigintStore` / `sigintZones`, table `atak_sigint_reports`
- Contrat events : `docs/technique/sse-intelligence-workspace-lot1.md`

## Prochaine étape d’implémentation

Démarrer par **Lot A** : stub `compat_acre` + enrichissement payload Overwatch multi-radios (sans casser l’onglet Radio actuel), puis fiche ATAK Lot B.
