# Indépendance, couche addons, interopérabilité et API

Document source équipe mod — **@COMSPECOverwatch** **1.4.11**.

> Copie miroir portail : `docs/technique/overwatch-mod/independance-couche-interoperabilite-api.md`

---

## 1. Notre indépendance

COMSPEC Overwatch est un **client tactique autonome** : liaison Athena, hub, tablette, téléphone, rapports, SSE natif, réalisme liaison.

**Obligatoire :** CBA_A3, A3_Modules_F, `COMSPECExtension_x64.dll`, addons `main` + `connect`.

**Optionnel :** `atak_athena` (cTab/BCE), `mavik_compat`, ACE, KAT, ACRE, TFAR.

Sans cTab, le pack reste pleinement utilisable via l’UI Overwatch. Sans ACE/KAT, les fonctions associées se désactivent (`isClass` guards). Assets visuels originaux COMSPEC.

Configuration communauté (modules pont, réalisme) **tirée du portail**, pas codée en dur.

---

## 2. Notre couche sur les autres addons

Strates : **portail → DLL → connect → (atak_athena | ACE/KAT | radio) → mods tiers**.

- **`connect`** : socle, UI, roleplay, SSE, BFT, alertes médicales ACE.
- **`atak_athena`** : ponts `athena_bridge*` vers cTab / BCE / Iceman — **interfaces publiques uniquement**.
- Chaque pont : module admin (`COMSPEC_AthenaModules`) + présence runtime du mod tiers.

Catalogue modules : `AtakBridgeModulesService` (weather, ctab_markers, iceman_photo, sse_person, comspec_mirror, …).

---

## 3. Interopérabilité

| Flux | Description |
|---|---|
| Jeu ↔ portail | Position, polls, uploads, session restore |
| Jeu ↔ cTab/BCE | Marqueurs, photos, alertes, miroir HQ |
| Portail ↔ clients tiers | Gateways mirror, clé API |
| Normalisation | Libellés marqueurs, dédoublonnage journal, formats API stables |

Évolutions : `CHANGELOG-ATAK.md`, `@COMSPECOverwatch/CHANGELOG.md`.

---

## 4. Notre API

### Extension (`callExtension "COMSPECExtension"`)

Retour `OK\|payload` / `ERR\|code`. Familles : Connect, polls (GetOrders, GetModModules…), écritures (UpdatePosition, SendMarker, SubmitSsePerson…), CAS, réalisme terminal.

Source : `COMSPECExtension/Extension.cs`.

### REST portail

Préfixe `/api/atak/*` (+ `/api/sse/*`). Auth : clé communauté + Steam (`ComspecApiKeyAuth`, `AtakArmaWriteGuard`).

Domaines : position, ordres, alertes, rapports, mod-modules, marqueurs, waypoints, gateways, SSE.

Routes : `routes/web.php`. Contrat SSE : [contrat-api-sse.md](contrat-api-sse.md).

Secrets et détails crypto : hors doc publique.

---

## Voir aussi

- [Architecture et addons](architecture-et-addons.md)
- [Réalisme liaison](realisme-liaison-atak.md)
- [Terminal SSE](terminal-sse-renseignement.md)
- [Compilation](compilation-et-publication.md)
