# Evidence, seals and intelligence

French version: [preuves.md](preuves.md). Guides index: [index.en.md](index.en.md).

## What it is for

Evidence only has value if you know who handled it, when and where. SSE keeps that register: **collecting and sealing** an exhibit gives it a number (P-case-0001) and a seal (SC-000000); every action (SAISIE (SEIZURE), COLLECTE (COLLECTION), MISE SOUS SCELLÉ (SEALING), OUVERTURE DU SCELLÉ (SEAL OPENING), EXPLOITATION, ANALYSE (ANALYSIS), REMISE (HANDOVER), PHOTOGRAPHIE (PHOTOGRAPH)) is written to a **tamper-proof log** (each line carries the hash of the previous one). The **documents, phones, radios and computers** found on a site are read and exploited in tiers (NONE → TACTICAL → FIELD → DETAILED → FUSION) and feed the link chart and Athena.

## Where to find it

| Need | Path |
|---|---|
| On an SSE item | ACE menu › **SSE** › **Fouiller** (Search) (full search, 15 s), **Mettre sous scellé** (Seal) (one bag consumed), **Relever des empreintes latentes** (Lift latent prints), **Mettre en sac de Faraday** (Put in a Faraday bag) (4 s, item with data), **Poser un cône de preuve** (Place an evidence cone) (3 s) |
| On a teammate | ACE menu › **Remettre mes pièces scellées** (Hand over my sealed exhibits) (5 s, countersigned; full inventory: bags dropped at their feet) |
| Read, exploit, photograph | ATAK phone › **SSE terrain** (Field SSE) › **CIBLE** (TARGET) tab (item aimed at within 6 m): **Photo de preuve** (Evidence photo), **Approfondir** (Dig deeper), **Documents**, **Chronologie** (Timeline), **Exploiter la radio** (Exploit the radio) (10 s), **Brancher le DL-7** (Connect the DL-7), **Transmettre à Athena** (Transmit to Athena), **Fiche FRS** (FRS sheet), **Marquer exploité** (Mark as exploited) |
| Seal register | SSE terrain › **SCELLÉS** (SEALS) tab (« Pièces que je détiens » (Exhibits I hold)) › **Registre des scellés** (Seal register) (« PREUVES — CHAÎNE DE CONSERVATION » (EVIDENCE — CHAIN OF CUSTODY) screen, METTRE SOUS SCELLÉ (SEAL) button) |
| Site progress | SSE terrain › **SITE** tab: « Avancement du site » (Site progress), « EXFILTRATION : SSE INCOMPLET (x %) » (EXFILTRATION: SSE INCOMPLETE (x %)) / « FEU VERT » (GREEN LIGHT), **Triage du site** (Site triage), **Tableau des liens** (Link chart) |
| Equipment | « Sachet à scellés » (Evidence bag), « Gants d'examen » (Examination gloves), « Appareil photo de preuve » (Evidence camera), « Kit empreintes » (Fingerprint kit), « Kit ADN » (DNA kit), « Écouvillons ADN / résidus de tir » (DNA / gunshot residue swabs), « Sac à documents » (Document bag), « Sac de Faraday » (Faraday bag), « Cônes de preuve numérotés » (Numbered evidence cones); crates **Caisse kit SSE d'équipe** (Team SSE kit crate), **Caisse kit exploitation numérique** (Digital exploitation kit crate), **Valise DOMEX de terrain** (Field DOMEX case) (speeds up the DL-7 within 10 m) |

Items and vehicles only have the SSE menu if they are **SSE-marked** (Eden attribute « Activer le renseignement SSE » (Enable SSE intelligence), **Rendre exploitable** (Make exploitable) module, generated site); people always have it.

## The life cycle of an exhibit

1. **Photo de preuve** before touching anything (ATAK Photo app, entities framed within 15 m; fallback: Photo de preuve button of SSE terrain): the exhibit is marked « en place » (in place), the collection quality goes up.
2. **Fouiller** (bar « Fouille SSE complète… » (Full SSE search…)): content generation, quality (70 or 45 base + skill, 95 at most), notification of the lines found, « DOSSIER SSE » (SSE FILE) window (buttons FEUILLE (SHEET), TRANSMETTRE (TRANSMIT), FERMER (CLOSE)).
3. **Mettre sous scellé** (bar « Collecte SSE… » (SSE collection…)): exhibit P-…, seal SC-…, you become the holder. Notification « Preuve collectée · Par · Heure · Grid · Qualité » (Evidence collected · By · Time · Grid · Quality).
4. **Approfondir** (SSE terrain › CIBLE) raises the exploitation tier; exploiting a sealed exhibit breaks the seal (logged): reseal (new number).
5. **Remettre mes pièces scellées** to the team leader: countersigned change of holder.
6. **Transmettre à Athena**: « Fiche envoyée vers Athena (file d'attente si hors liaison) » (Sheet sent to Athena (queued if offline)).

## Step by step: a laptop in a safehouse

1. Aim at the laptop, SSE terrain › CIBLE › **Photo de preuve**.
2. ACE menu › SSE › **Fouiller** (15 s): « Exploitation : niveau TACTICAL » (Exploitation: TACTICAL level), files and an email appear.
3. **Mettre sous scellé** (one bag): P-SSE-2026-0007-0003, seal SC-000112.
4. SSE terrain › CIBLE › **Approfondir** (FIELD tier, then DETAILED): contacts, places; the link chart gains suspected nodes.
5. With the native ATAK and a DL-7 cable: **Brancher le DL-7** (login screen, password or FORCER L'ACCÈS (FORCE ACCESS), transfer); a **Valise DOMEX** within 10 m speeds it up.
6. **Transmettre à Athena**, then **Remettre mes pièces scellées** to the leader.

## Common pitfalls

- **One SSE action at a time** (« Une action SSE est déjà en cours. » (An SSE action is already in progress.)); it is interrupted if you board a vehicle, move more than 4 m away or if the target disappears. A kit is only consumed on success.
- **Bag required** to seal: « Sachet de preuve SSE (ou kit médical ACE compatible) requis. » (SSE evidence bag (or compatible ACE medical kit) required.); « Pièce déjà collectée par un équipier. » (Exhibit already collected by a teammate.), « Pièce déjà sous scellé (…) » (Exhibit already sealed (…)).
- **On a person, the search is always quick** (5 s); the full search (15 s) is for items and vehicles (cab and cargo).
- **Exploiting a sealed exhibit breaks the seal**: it is logged; reseal.
- A **tampered log** appears in red in the register with the faulty line (Zeus module **Altérer un scellé (exercice)** (Tamper with a seal (exercise)) to practise).
- **Overwatch Connect** loaded: the screens (terminal, register…) open in its host.
- « Exploitation déjà au niveau Fusion. » (Exploitation already at Fusion level.): nothing more to get; « Aucun document exploitable. » (No exploitable document.) / « Aucune donnée radio exploitable. » (No exploitable radio data.).
- Settings: Durées (Durations) (quick search 5 s, full 15 s, fingerprints 8 s, phone 15-60 s, computer 30-120 s), Matériel SSE obligatoire (SSE equipment required), Accepter le matériel d'autres mods (Accept equipment from other mods), Préfixe des pièces (Exhibit prefix) (P) et des scellés (and seals) (SC), Intel (Niveau de l'opérateur SSE (SSE operator level), Tri automatique après une fouille (Automatic triage after a search), Recoupement automatique (Automatic cross-referencing)).

## Athena

- **Transmettre à Athena** (SSE terrain, terminal, SEEK, DOSSIER SSE window) sends the sheet with the exhibit's log to the case (**Référence du dossier SSE** (SSE case reference), setting or module **Fixer la référence du dossier** (Set the case reference)).
- The seal register of a campaign copied into Athena can be read in Bureau SSE › Campagnes (SSE Desk › Campaigns).
- Evidence photos: attached to the sheets (4 at most per FRM).
