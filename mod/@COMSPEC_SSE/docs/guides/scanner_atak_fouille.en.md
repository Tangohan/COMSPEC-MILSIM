# ATAK phone: scanner, SSE apps and searching

French version: [scanner_atak_fouille.md](scanner_atak_fouille.md). Guides index: [index.en.md](index.en.md).

## What it is for

The ATAK phone (native COMSPEC ATAK mod) is the SSE field tool: it **scans** identity documents level by level, receives **notifications** (requests for papers, results, alerts), keeps the **journal**, the **check logs** and the **evidence seals**, and serves as a terminal when you have no SEEK. All SSE feedback goes through it (or through the status line of the windows, or to the RPT): no more Arma interface hints.

## Where to find it

| App (Intelligence section) | Role |
|---|---|
| **SSE terrain** (SSE Field) (Ctrl+Shift+S if the phone is carried) | Tabs **CIBLE** (TARGET) (object or person aimed at within 6 m), **SITE** (50 m: progress, exfiltration green light, Site triage, Link board), **SCELLÉS** (SEALS) (Items I hold, Seals register), **JOURNAL** (SSE Journal, **Guide SSE** (SSE Guide), Technical journal, Campaign) |
| **Contrôle ID** (ID Check) | Tabs **CONTRÔLE** (CHECK) (N1 MRZ, N2 chip, N3 database, N4 face, verdict, Send, FRM, SALUTE, SPOTREP, Log the document, Share, NFC, On my phone), **HISTORIQUE** (HISTORY) (last 50 checks), **BOLO** |
| **Ma couverture** (My cover) | Infiltrator record (see [ma_couverture.en.md](ma_couverture.en.md)) |
| **Photo** | Evidence photo (framed entities within 15 m) |
| **Extraction** (DATALINK) | DL-7 cable: connection, password, forcing, transfer |
| Other COMSPEC apps | **FRS / FRM**, **SALUTE**, **Comptes rendus** (Reports), **Recherchés** (Wanted), **Tinder** (HVT hunting: FIND / FIX / JACKPOT) |

## Scanning a document

Two entry points: the **SCANNER (ATAK)** (SCAN (ATAK)) button of the viewer (closes the viewer, opens Contrôle ID on this document) or the **Contrôle ID** app after a presentation. Then, in the CONTRÔLE tab: **Lire la MRZ (N1)** (Read the MRZ (N1)) (3 s), **Lire la puce (N2)** (Read the chip (N2)) (4 s, within 1.5 m), **Interroger la base (N3)** (Query the database (N3)) (3 s), **Comparer le visage (N4)** (Compare the face (N4)) (8 s, within 3 m). Each level adds its line and the verdict banner updates. Level details: [controle_identite.en.md](controle_identite.en.md).

Then: **Relever le document** (Log the document) (log in Contrôles / Checks; Athena « Contrôles subis » / Checks undergone), **Transmettre à Athena** (Send to Athena), **Joindre à une FRM** (Attach to an FRM), **SALUTE** / **SPOTREP** (pre-filled report to proofread before sending), **Partager** (Share) (Bluetooth to a paired teammate), **NFC** (nearest phone within 2 m), **Sur mon téléphone** (On my phone) (QR code to your real phone). **Confirmer le PID** (Confirm PID) appears for an HVT or wanted verdict.

## Notifications received

- « Papiers, s'il vous plaît » (Papers, please) (a player or an AI guard asks for your papers: answer through **Mes papiers** (My papers)).
- You are being searched; a document is returned to you; a blown cover (vibration only).
- « Contrôle ID : VERDICT — nom » (ID Check: VERDICT — name); flagged person: vibration (no sound in stealth mode).
- Short phrases from checked AI within 15 m; cover-story questions from a checkpoint.
- SSE editor: « Au téléphone » (On the phone) and « Annonce » (Announcement) contents; « SSE · Analyse » (SSE · Analysis) for analysis notes.
- Zeus and administrators: « Trouvé par un joueur » (Found by a player) (editor), checkpoint alerts.

## Searching

| Action | Where | Duration | What it gives |
|---|---|---|---|
| **Fouiller** (Search) (SSE) | ACE menu on a person › **SSE** | 5 s (quick search, « Fouille SSE rapide… » / Quick SSE search…) | The person's SSE items and data (partial reveal, quality according to skill), exploitation tier +1, journal, DOSSIER SSE (SSE FILE) window |
| **Fouiller** (SSE) | ACE menu on an SSE-marked object or vehicle | 15 s (full search, « Fouille SSE complète… » / Full SSE search…) | Same; vehicle: cab and cargo, « Exploitation véhicule : x % » (Vehicle exploitation: x %) |
| **Fouiller les papiers** (Search for papers) | ACE menu on a person › **Contrôle d'identité** (Identity check) | 6 s (4 s on a body) | Their identity documents, under **Documents trouvés** (Documents found) (180 s): Examine / Seize; without consent, only dead, handcuffed, surrendered or unconscious |
| Search by an **AI checkpoint** | "search" outcome | 8 s | Weapons, papers in several names: suspicion |
| **Relever les empreintes (kit)** (Take fingerprints (kit)), **Prélèvement ADN** (DNA sample), **Test de résidus de tir** (Gunshot residue test) | ACE menu on a person › SSE (kit carried) | 8 s / 6 s / 6 s | Samples without a SEEK |

A searched player is **notified on their ATAK**. A progress bar; only one SSE action at a time.

## Step by step: a complete check log

1. A civilian hands you their card: it opens (setting « Ouvrir les papiers tendus à l'écran » / Open handed-over papers on screen). **SCANNER (ATAK)**.
2. Contrôle ID › **Lire la MRZ (N1)** › **Lire la puce (N2)** › **Interroger la base (N3)**: RAS : DOCUMENT CONFORME (ALL CLEAR: DOCUMENT VALID).
3. **Relever le document**: « Relevé : ALF814703 noté dans votre téléphone (Contrôles) » (Logged: ALF814703 recorded in your phone (Checks)). The button changes to « Document relevé » (Document logged).
4. **Transmettre à Athena** (becomes « Transmis » / Sent); **HISTORIQUE** keeps the line.
5. Later, Athena › record of the checked member › **Contrôles subis** shows your log.

## Common pitfalls

- **Without a phone**, no scanner (N1 to N3), no log, no notifications: only the visual check remains.
- **Language**: the window texts follow the native ATAK's "Phone language" setting; without native ATAK, everything stays in French.
- Checks and logs are **queued** while off-link and sent when the link returns.
- **Partager / NFC / Sur mon téléphone** require ATAK 3.0.2 or later.
- The SSE search of a person is **always quick** (5 s); the full search is reserved for objects and vehicles.
- An object with no SSE menu is **not SSE-marked**: Rendre exploitable (Make exploitable) module or Eden attribute.
- An identity search **without consent** requires a dead, handcuffed, surrendered or unconscious person.

## Athena

- Checks (CTRL-…), logs (Contrôles subis), FRM with document photo, SALUTE / SPOTREP, evidence records: everything goes through the phone and COMSPEC Link.
- Athena documents and notices (stolen, lost, revoked, BOLO, wanted persons of the operation) come back into the scanner (N3) and into the Recherchés app.
