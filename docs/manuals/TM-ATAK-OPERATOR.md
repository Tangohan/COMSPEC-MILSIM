ATHENA C2
TECHNICAL PUBLICATION

Document: ATHENA ATAK — Operator Manual
Reference: TM-A3-21
Revision: 1.0
Status: CONTROLLED
Classification: INTERNAL
Authority: COMSPEC
System: ATHENA C2

| Field           | Value          |
| --------------- | -------------- |
| Document ID     | TM-A3-21       |
| Revision        | 1.0            |
| Status          | CONTROLLED     |
| Owner           | COMSPEC        |
| System          | ATHENA C2      |
| Last Review     | 2026-09-02     |
| Source of Truth | Git repository |

## Revision History

| Revision | Date       | Author  | Changes                        |
| -------- | ---------- | ------- | ------------------------------ |
| 1.0      | 2026-09-02 | COMSPEC | Initial controlled publication |

---

# ATHENA ATAK — Operator Manual

**Public:** operators, team leaders, overwatch / TOC, JTAC, pilots, medics.

This manual describes **FIELDED** behaviour only. Check REG-A3-01 before assuming a tool exists. Commercial plan details are in TM-A3-31; operators only need to know that the **web map** may be unavailable on a free community plan.

In-game guide on the portal: `/atak/mod/guide`. Formation: `/atak/mod/formation`. Download: `/atak/mod`.

---

## 1. Access and authentication

### 1.1 ATHENA account

1. Create or use an ATHENA account (e-mail / password).
2. Join the **community** that runs the serial. One e-mail can belong to several communities; you choose the community at login or by switching.
3. Set **indicatif** on the personnel profile. Overwatch uses this callsign on the picture, not the community title.

### 1.2 Web Tacmap

1. Log in.
2. Open the ATAK map (`/atak`) **in that community**.
3. If the page asks to upgrade the plan, the community does not have the ATAK web feature. The in-game liaison may still work if the administrator issued an access key.

### 1.3 Phone / mobile shell

Routes exist for a mobile shell and QR pairing (`/connect`, phone-pairing API). Pairing is a TOC/phone convenience, not a second identity.

---

## 2. Profile and callsigns

| Field | Where it is set | Used for |
| ----- | --------------- | -------- |
| Indicatif ATHENA | Profile / Effectifs | BFT identity on Tacmap |
| Indicatif Arma | Derived from profile after bootstrap; not the groupId community name | Same |
| Rôle | Profile / in-game settings | Icon / label |
| Steam | Linked account | Silent auth in mission when already linked |

Do not put the community name in the callsign. Do not use “Unknown” / “Inconnu”.

---

## 3. Install COMSPEC Overwatch

### 3.1 Prerequisites

- Arma 3
- **CBA_A3** (required)
- Pack **@COMSPECOverwatch** including `COMSPECExtension_x64.dll` at the pack root
- Recommended: ACE (self-actions)
- Optional: cTab / ATAK Enhanced / BCE (Athena ATAK apps), KAT, Mavic

### 3.2 Activation

1. Enable the pack and CBA in the launcher / server mod list.
2. The DLL must load (64-bit). If ACE “Connexion Athena” never appears, the pack or ACE menus setting is off.

### 3.3 CBA (operators)

Typical settings (names as in CBA):

| Setting | Operator action |
| ------- | --------------- |
| Overwatch enabled | On |
| API URL | Portal URL; if empty the mod falls back to `https://athena.ttrd.fr/public` |
| API key | Only if the administrator issued a community key and you are not using account login |
| Tenant id | **Do not use as authority.** Athena selects the community after login |
| Position / heartbeat intervals | Leave default unless TOC says otherwise |
| Required item | Honour the mission maker: tablet/phone item may be mandatory for PLI |
| Sounds / quiet mode | Personal |
| ACE menus | On, unless using the slim Athena menu |

Network profile may be forced by the server.

---

## 4. Connect from Arma

1. Join the **mission** (not only the briefing).
2. If Steam is already linked to the ATHENA account, the session can restore by itself.
3. Otherwise: ACE → COMSPEC → **Connexion Athena** (e-mail / password or OTP).
4. Account link: redeem a game-link code or link by Steam when prompted.
5. Success: link state **linked**, Athena ready, sync loops start.
6. Failure: “Compte non lié” / offline — see §11.

You do **not** pick a workspace in Arma. Planning workspaces live on the portal (`/operations`).

---

## 5. Tacmap (web)

Once linked, the post sees you on the map.

| Tool | What you see |
| ---- | ------------ |
| Carte | Arma world metres, same grid as the game |
| Contacts / unités | Live BFT; stale after ~2 minutes without update |
| Cams | Video feeds when the field sends them |
| Photos | Recon / camera uploads |
| Chat | C2 journal (not Arma side chat) |
| Pings | Point + message |
| Marqueurs | Shared markers and shapes |
| Intel | Reports / overlay depending on modules |
| JTAC | Laser codes; designator when used |
| 9-Line | MEDEVAC 9-line |
| Flight Manifest | Air assets / crew manifest |
| Itinéraires | Waypoints from the post (polled in game) |
| État réseau | Badges / liaison tile |

Coordinates: `[x, y]` world metres (X west→east, Y south→north). Optional altitude is ASL Z. No Lat/Long.

---

## 6. In-game tools (ACE / Athena tile)

Typical ACE self-actions under COMSPEC (when `comspec_overwatch_ace_menus` is on):

- Connexion Athena / téléphone ATAK
- Resynch
- Ping
- Rapports / FRS
- CAS request (order path)
- Flight Manifest
- Photos / recon
- Laser
- Ordres / messages
- Bilan santé / inbox médical
- Wardrobes (arsenal collections)
- Signalement

cTab / BCE apps appear when `atak_athena` loads.

---

## 7. Quick Start — Mission Ready

```text
Compte ATHENA
↓
Configuration du profil (indicatif, communauté)
↓
Installation COMSPEC Overwatch + DLL
↓
Configuration CBA (Overwatch on, URL, ACE menus)
↓
Connexion Arma (Steam lié ou e-mail / mot de passe)
↓
Vérification PLI (vous apparaissez sur Tacmap)
↓
Accès ATAK web (si le plan communauté le permet)
↓
Mission Ready
```

Pass criteria:

1. Link state linked.
2. Own icon moves on Tacmap.
3. A test ping or chat is seen by TOC.
4. Callsign matches the ATHENA profile.

---

## 8. Network and health (first line)

| Indicator | Meaning |
| --------- | ------- |
| Linked | Extension reached the API with a valid session/key |
| Compte non lié | Auth ok enough to talk, account not bound to the community |
| Offline | No URL, invalid URL, or Connect failed |
| Backoff / 429 | API asked to slow down — PLI pauses then resumes |
| Maintenance | Community ATAK flag — API returns 503 |
| Simulated loss | Realism / roleplay — expected |

First-line diagnostic:

1. DLL present next to the pack.
2. CBA URL is `http(s)://…` (12+ characters).
3. You are in mission (not editor menu at 0,0).
4. Required terminal item if the setting is on.
5. Correct ATHENA community.
6. Ask TOC whether Tacmap shows any unit at all.

Logs: RPT `[COMSPEC]` lines; optional file log if CBA `log_to_file` is on. Bug report ACE action can send a diag tail.

---

## 9. What this manual does not promise

- Persistent queue if you restart Arma
- Choosing tenant 1 or a workspace id in CBA
- Full NATO CAS 9-line (use MEDEVAC 9-line and the CAS order/check-line tools)
- ACRE/TFAR as a SIGINT product (radio metadata on the map is limited; see REG-A3-01)
- Geographic Lat/Long Tacmap

---

## References

- ATHENA C2 Field Manual — FM-A3-01
- ATHENA C2 Standard Operating Procedures — SOP-A3-01
- ATHENA C2 Administrator Manual — TM-A3-31
- COMSPEC Overwatch Technical Manual — TM-A3-11
- ATHENA C2 Capability Registry — REG-A3-01
