# COMSPEC SSE: modules, tutorials and equipment

Guide for mission makers and Zeus. French version: [Modules_et_materiel_SSE.md](Modules_et_materiel_SSE.md).

A shorter version of the same content is in the game: **Escape › Field Manual › COMSPEC SSE**, or in Eden the **COMSPEC SSE** menu in the top bar. Every module field shows a tooltip when hovered. Menus and field names below are given in English as the game shows them with English selected; with French selected the game shows the French names.

---

## Contents

1. [Vocabulary on one page](#1-vocabulary-on-one-page)
2. [Where to find each piece of information](#2-where-to-find-each-piece-of-information)
3. [Tutorial 1: a first SSE mission](#3-tutorial-1-a-first-sse-mission)
4. [Tutorial 2: playing the FALCON dataset](#4-tutorial-2-playing-the-falcon-dataset)
5. [Tutorial 3: filing records in an Athena case](#5-tutorial-3-filing-records-in-an-athena-case)
6. [Tutorial 4: a campaign across several missions](#6-tutorial-4-a-campaign-across-several-missions)
7. [Tutorial 5: a computer to hack (DOMEX and DL-7)](#7-tutorial-5-a-computer-to-hack-domex-and-dl-7)
8. [The modules, one by one](#8-the-modules-one-by-one)
9. [Eden attributes of people and objects](#9-eden-attributes-of-people-and-objects)
10. [SSE equipment](#10-sse-equipment)
11. [Player actions (ACE menu)](#11-player-actions-ace-menu)
12. [CBA settings](#12-cba-settings)
13. [Pitfalls and troubleshooting](#13-pitfalls-and-troubleshooting)

---

## 1. Vocabulary on one page

| Word | What it is | Who chooses it |
|---|---|---|
| **SSE** | *Sensitive Site Exploitation*: exploiting everything found on an objective (people, bodies, documents, phones, computers, caches). | — |
| **Dataset** | A **ready-to-play scenario**: a list of named roles (leader, bomb maker, courier...), the links between them and a fixed seed. The same roles always give the same names, aliases, phones, documents and fingerprints. Today: **FALCON**. | You pick it from a list. You do not create it. |
| **Reveal level** | 0 to 3. Decides how many roles of a dataset are handed out, so how far players can trace the network. | You, in the Scenario director module. |
| **Model** | A **generation recipe**: profile, region, theme, aliases, messages, documents. Gives a *style*, not fixed characters. 24 built-in models, plus the ones you save in Zeus. | You, from a list; or you create one in Zeus. |
| **Campaign** | What survives from one mission to the next: biometric seed, recurring characters, organisations, what players know, evidence register. | **You make up its identifier** (e.g. `OP-SABLE`). Nothing to ask anyone. |
| **Case reference** | The number of a **case opened on Athena** (e.g. `SSE-2026-0007`). Records sent by players are filed there. | **Athena assigns it** when you open the case. You copy it into the mission. |
| **Seed** | A number or text that makes generation reproducible: same seed, same results. 0 or empty = automatic. | Rarely needs changing. |
| **Access stage (DOMEX)** | How far access to a digital device has gone: unidentified, discovered, access in progress, access established, exploited. | You, or the Zeus during the game. |
| **SSE Office** | Athena's SSE portal (`/atak/sse`): cases, campaigns, digital exploitation, map. | — |

**Dataset or model?** A model says "make me a believable Iraqi financier". A dataset says "this man is Omar Saadi, known as LE CHANGEUR, and he funds ABU KARIM". The dataset is fixed and the same for everyone; the model varies.

---

## 2. Where to find each piece of information

| I am looking for... | In game | On Athena |
|---|---|---|
| **The list of datasets** | The *Dataset* list of the **Scenario director** module; in Zeus, that module with the *List datasets* action; Field Manual, *Datasets and levels* chapter. | SSE Office › **23 Mission models** › *Mission kits (datasets)*. |
| **The roles of a dataset** | The *Role in the dataset* list in a person's attributes; Field Manual. | Same page, roles table. |
| **A case reference** (`SSE-2026-0007`) | It does not exist in game until you copy it in. | SSE Office › **04 Validated cases**: on each row and at the top of the case page. To create one: **Open a case** button (SSE Office management right). |
| **Campaign identifiers** | In Zeus: *Campaign: load or create* (server saves list), *Campaign: fetch from Athena* (Athena list). ACE self menu › *Current campaign* for the loaded one. | SSE Office › **28 Campaigns**: under the name, the global identifier `CODE:KEY`. **In game, only use the part after the colon** (`OP-SABLE` for `COMSPEC:OP-SABLE`). |
| **Model identifiers** | The *Built-in model* list of the **Apply an SSE model** module; in Zeus: **List SSE models** module (also shows your saved models). | — |
| **Reveal levels** | Tooltip of the *Reveal level* field; Field Manual. | — |
| **What players sent** | — | SSE Office › Cases, the case page. |
| **Scripted digital devices** | — | SSE Office › **17 Digital exploitation**. |

---

## 3. Tutorial 1: a first SSE mission

Goal: a three-man cell in a house, a computer and papers to exploit. Ten minutes in Eden.

1. **Place the set**: a house, three enemy AI inside, a computer (`Land_Laptop_unfolded_F`) and a crate.
2. **Let players exploit**: nothing to do, the ACE actions are always there. For the biometric terminal, place the **Issue SEEK terminal** module (category *3. Mission and scenario*) near the players' start, radius 25 m.
3. **Generate the site**: place the **Create a full SSE site** module (category *1. Sites and objects*) in the centre of the house. Radius 35 m, type *Insurgent cell*, richness *Detailed*. Everything in the radius gets identities, phones, documents and links.
4. **Choose the record look** (optional): **SSE document presentation** module, template *Field — stained sheet*.
5. **Play**: in game, ACE menu on a prisoner › **SSE** › *Inspect*, *Search*, *Biometrics › Full capture*; on the computer › *Digital exploitation*; on the crate › *Search*, *Collect*, *Seal*. ACE self menu › **COMSPEC SSE** › *Link chart* to see the network appear.

No-prep variant: in Zeus, **COMSPEC SSE · Tools › Tutorial: place an exercise here** places a full cell ready to exploit.

---

## 4. Tutorial 2: playing the FALCON dataset

FALCON is an Iraqi cell from 2012 (seed `FALCON-IQ-2012-A`):

| Role | Name, alias | Appears from level |
|---|---|---|
| Sector leader (HVT) | Karim Al-Rashid, **ABU KARIM** | 0 |
| Civilian (background noise) | Ahmed Saleh, shopkeeper | 0 |
| IED technician | Yusuf Mahdi, **L'INGÉNIEUR** | 1 |
| Border courier | Hassan Qadir, **LE CHAUFFEUR** | 1 |
| Financial relay | Omar Saadi, **LE CHANGEUR** | 2 |
| Safehouse keeper | Bilal Nuri, **LE LOCATAIRE** | 2 |

Links: the leader gives orders to the technician, tasks the courier, funds the financial relay; the courier drops at the safehouse keeper's; the technician stashes his gear there.

**Method A: let the module hand out the roles**

1. Place at least **4 people** (level 1) or **6** (level 2), grouped, **away from the players**.
2. Place the **Scenario director** module in the middle of the group.
3. *Action*: Apply the dataset. *Dataset*: FALCON. *Reveal level*: 1 — Tactical. *Radius*: enough to cover the group (50 m).
4. At start, the person **closest to the module** becomes ABU KARIM, then the next ones in table order.

**Method B: place each role by hand** (more precise)

1. Double-click a person › **COMSPEC SSE — Intelligence** category.
2. *Mission dataset*: FALCON. *Role in the dataset*: for example *FALCON · Sector leader ABU KARIM (HVT)*.
3. Repeat for each role, once each.

**The levels**

| Level | Name | Handed out | For |
|---|---|---|---|
| 0 | Surface | The leader (under his alias) and the civilian | A recon patrol |
| 1 | Tactical | + technician and courier | The standard mission (recommended) |
| 2 | Field | + financier and safehouse | A takedown operation |
| 3 | Full truth | Everything | Debrief, Zeus |

In Zeus, the same module with the **Change the level only** action changes the level during the game without touching anyone.

> Players inside the radius count as people: if a player is closer to the module than the AI, that player becomes the leader.

---

## 5. Tutorial 3: filing records in an Athena case

When a player checks a person with SEEK or transmits a record, it goes to Athena. The case reference says **which case** to file it in.

1. **On Athena**: open the SSE Office (*SSE Portal* button on the ATAK page, or Back-office › Intelligence — access codes for leadership).
2. Menu **04 Validated cases** › **Open a case**. Give a title ("Operation Sable, phase 1"). You need the SSE Office management right.
3. Athena assigns the reference: `SSE-` + the year + a number, for example **SSE-2026-0007**. It is shown at the top of the case and in the list.
4. **In Eden**: **SSE case reference** module (category *3. Mission and scenario*), field *Case reference*: `SSE-2026-0007`. Place it anywhere.
5. **In Zeus**, the same module switches case during the game (empty = no case).

Other way, for a whole server: CBA setting **COMSPEC SSE › Network › SSE case reference**.

**Without a reference**, Athena files each record in the only open case if there is just one, otherwise in a daily case "Checks of dd/mm/yyyy (to sort)".

> **Watch your typing.** An unknown reference (`SSE-2026-007` instead of `SSE-2026-0007`) creates a new case under that name.

---

## 6. Tutorial 4: a campaign across several missions

A campaign keeps from one mission to the next: recurring characters (same names, same fingerprints), organisations, what players discovered and the evidence register.

**You make up the identifier.** A short name, for example `OP-SABLE`. It is upper-cased, spaces and punctuation become dashes (`op sable` becomes `OP-SABLE`).

1. **Mission 1**: **SSE campaign (load or create)** module (category *3. Mission and scenario*).
   - *Identifier*: `OP-SABLE`. *Name*: "Operation Sable".
   - *Type*: **Permanent** (everything is kept), **Recurring** (bootcamp: same characters, everything becomes unknown again at each drill), **Temporary** (disposable drill, nothing is kept).
   - *Autosave*: every 15 minutes.
   - *Save at mission end*: checked.
2. At first start, the campaign is **created** in the server profile.
3. **Recurring characters**: on a person, **COMSPEC SSE — Biometrics** attributes › *Biometric identifier (campaign)*: a fixed identifier (`KARIM-01`). They will have the same identity and fingerprints in every mission.
4. **Save at a precise moment**: **SSE campaign: save** module, synced to a trigger (right click › Connect › Sync to).
5. **Mission 2**: same module, **same identifier** `OP-SABLE`: the campaign is reloaded.
6. **On Athena**: in Zeus, *Campaign: send to Athena*; it shows in SSE Office › **28 Campaigns** (copy for a drill, reset, share with other communities). *Campaign: fetch from Athena* brings it back to a server.

**Run a drill on the real campaign network**: a second mission with *Type* Temporary and *Copy structure from*: `OP-SABLE`. Players train on the real characters without damaging anything.

> A server stopped abruptly does not trigger the end save: keep the autosave.

---

## 7. Tutorial 5: a computer to hack (DOMEX and DL-7)

Two complementary tools:

- **DOMEX** (*Digital intelligence*): you write what the device holds; Athena (SSE Office › Digital exploitation) receives exactly that text once access is established.
- **DL-7** (with the **native ATAK**): the operator plugs their ATAK phone in with the DL-7 cable and downloads the files, with password, tries, cracking, alarm.

**A password-protected computer (DL-7)**

1. Place a computer. Place the **DL-7 · Data to extract by cable** module (category *4. Digital*) and sync it with the computer.
2. *Device name*: `PC-KESTREL`. *Login screen*: Windows. *Password*: `sable2012`. *Hint*: "year of the cell". *Tries allowed*: 3. *Too many tries*: lock. *Cracking possible*: checked, 90 s.
3. *Files*: one block per file, separated by `---`. First line `name | type`, then the content:
   ```
   ordre_mission.txt | texte
   Meeting at the west well, Thursday 21:00.
   ---
   cache.coord | coord
   045123
   ```
4. Hide the password elsewhere: on a document, another phone, or in a prisoner's answers.
5. Add files with the **DL-7 · Add a file** module, placed **after** the first one.

Without a module: double-click the object › **COMSPEC SSE — DL-7 extraction** › *Extractable with DL-7 cable*, content *SSE data* (what the generator put in the device) or *Typed files*.

**A phone whose content reaches the SSE Office (DOMEX)**

1. **Digital intelligence (DOMEX)** module synced with the phone.
2. *Device type*: phone. *Apparent owner*: "ABU KARIM". *Security*: medium. *Access time*: 3 min.
3. *Packet — type*: Message. *Packet — text*: "Delivery postponed, the changer is waiting". *Quality*: Fragment (to cross-check).
4. In Zeus, **Set access stage** › *Access established* once players are done: the packet is sent to Athena.

---

## 8. The modules, one by one

In Eden: right panel › **Systems (F5) › Modules › COMSPEC SSE**. In Zeus: Modules tab (ZEN). General rule: **in Eden, sync the module with its targets** (right click › Connect › Sync to); **in Zeus, place it on the target**, a form opens.

### 1. Sites and objects

| Module | What it does | Key fields |
|---|---|---|
| **Generate an SSE profile** | Gives identity, phone, documents and biometrics to the targeted people or objects. | *Profile* (insurgent, civilian, military, leader / HVT, courier, financier, technician, intelligence, logistics, random), *Richness* (light to high value), Real identity / Phone / Documents / Biometrics / Link targets boxes, *Useless data* (0 to 75 % of irrelevant messages to bury the clues). |
| **Create a full SSE site** | Links everything in the radius into one network. Place the set first, the module in the centre. | *Radius* (15 to 150 m), *Site type*, *Richness*, *Exploitable objects (max)*, Digital devices, Documents, Network links. |
| **Make exploitable (SSE)** | Marks an object as searchable; its content is generated on first inspection (light on the server). | *Object nature* (automatic, document, phone, computer, radio, crate, weapon, vehicle), *Profile*, *Richness*. |
| **Place an intelligence object** | Creates the object at the module position and makes it exploitable. | *Object* (laptop, smartphone, basic phone, satellite phone, radio, paper file, photos, suitcase, weapons crate), *Profile*, *Richness*, *Generate content at start*. |
| **Apply an SSE model** | Applies a model (ready-made recipe) to the targets. | *Built-in model* (list of the 24 models, or "Choose in Zeus"), *Other model (identifier)* for a model you saved. |
| **SSE training site** | A full generated site for practice with no preparation. | *Scenario* (random, cell, smuggling, depot, CP, safehouse, IED workshop, financial node, intelligence cell), *Radius*. |

### 2. People and links

| Module | What it does | Key fields |
|---|---|---|
| **Subject identity (SEEK query)** | What the SEEK terminal will answer about this subject. | *What the database must answer* (automatic, unknown, flagged, wanted), *Last name*, *First name*, *Alias*, *Declared nationality*, *Language*, *Previous record reference*, *Seed*. Empty = generated. |
| **Organisation (network, cell, clan...)** | Declares an organisation. Sync its members (or a group leader for the whole group) and the objects it owns. | *Name*, *Nature*, *Attached to* (parent organisation), *Threat* (colour on the chart), *Description*, *Roles of synced people* (typed on each, or generated in cells), *Seed*, *Players initially know*. |
| **Link SSE entities** | Draws links on the link chart. | *Relation* (associate, contact, owner of, refers to), *Confidence* (0.3 to 0.9), *Layout* (chain or star). |

### 3. Mission and scenario

| Module | What it does | Key fields |
|---|---|---|
| **SSE campaign (load or create)** | Loads or creates the campaign at start. One per mission. | See [tutorial 4](#6-tutorial-4-a-campaign-across-several-missions). *Autosave*: never, 5, 10, 15 (recommended), 30 or 60 minutes. |
| **SSE campaign: save** | Saves when a synced trigger fires. | *Action* (save now, or mission end), *Reason* (campaign log text). |
| **Scenario director (dataset / level)** | Applies a dataset and sets the reveal level. | See [tutorial 2](#4-tutorial-2-playing-the-falcon-dataset). *Dataset* is now a list. |
| **Issue SEEK terminal** | Gives the SEEK II (backpack, else vest, else uniform). | Synced units, or *Issue radius* (25 m) around the module. |
| **Generate from a brief / scenario** | Generates a network around the module from free text or a pack. | *Narrative brief* ("leader and courier", "IED workshop"), *Scenario pack* (takes priority over the brief, includes *FALCON dataset*), *Radius*. |
| **SSE document presentation** | Look of the in-game records. One per mission. | *Template*, then only to change a text: paper, banner, titles, footer, buttons. See below. |
| **SSE case reference** | Athena case where records are filed. | See [tutorial 3](#5-tutorial-3-filing-records-in-an-athena-case). |

**Presentation templates** (record texts stay in French by design)

| Template | Paper | Banner | Person record title | Buttons |
|---|---|---|---|---|
| Standard — restricted distribution | clean | DIFFUSION RESTREINTE — EXPLOITATION TERRAIN | DOSSIER SSE | FEUILLE · TRANSMETTRE · FERMER |
| Field — stained sheet | stained | SAISIE TERRAIN — EXPLOITATION | FICHE D'EXPLOITATION | DÉTAIL · ENVOYER · FERMER |
| Draft — crumpled paper | crumpled | BROUILLON — NE PAS DIFFUSER | NOTES D'EXPLOITATION | LIRE · TRANSMETTRE · FERMER |
| Archives — yellowed paper | yellowed | ARCHIVES — CONSULTATION CONTRÔLÉE | FICHE D'ARCHIVES | FEUILLE · TRANSMETTRE · FERMER |
| Office — spotless paper | clean | DIFFUSION CONTRÔLÉE — BUREAU SSE | FICHE D'IDENTITÉ | FEUILLE · TRANSMETTRE · FERMER |

### 4. Digital (DOMEX, DL-7)

| Module | What it does | Key fields |
|---|---|---|
| **Digital intelligence (DOMEX)** | Scripted digital device: you write its content. | *Identifier* (`PC-KESTREL-04`, empty = auto), *Device type*, *Apparent owner*, *Organisation*, *Fictional network*, *Security*, *Content profile*, *Access time* (1 to 15 min), *Remote access*, then a packet: *type* (message, document, coordinate, contact, frequency), *text*, *quality* (complete, fragment, decoy), *entities* (`Name | type`). |
| **Set access stage** | Changes a device's stage. At *Access established*, the planned intelligence is sent to Athena. | *Stage*: unidentified, discovered, access in progress, access established, exploited. |
| **Place a map point** | Point on the Athena map, invisible to players. | *Label*. |
| **DL-7 · Data to extract by cable** (native ATAK) | Files downloadable with the DL-7 cable. | See [tutorial 5](#7-tutorial-5-a-computer-to-hack-domex-and-dl-7). *Device to create*: if nothing is synced, creates an object on the spot. *Port*: USB-C, RJ45 (faster), serial (slower). *Alarm*: siren, Zeus warned, position revealed to enemies within 400 m. |
| **DL-7 · Add a file** (native ATAK) | Adds a file to a DL-7 device. | *File name*, *Type* (text, document, credentials, coord, image, audio), *Content*. |

### 5. Zeus tools

Placed during the game: **Clear SSE data** (also in Eden, synced), **Reset an SSE site** (a whole radius), **SSE Zeus panel**, **View SSE data**, **Known to players / truth**, **SSE site manager**, **SSE report (AAR) + chart**, **Save as SSE model**, **List SSE models**, **SSE inspector (debug)**, **Add intelligence** (DOMEX during the game).

Extra ZEN modules: **COMSPEC SSE · Mission** (campaign: load, save, status, reset, end, export, import, send to Athena, fetch from Athena; active case), **COMSPEC SSE · Tools** (Tutorial: Zeus manual, **Tutorial: choose a chapter**, Tutorial: place an exercise here).

### Built-in models

| Identifier | Name |
|---|---|
| `builtin_cellule_insurgee_irak` | Insurgent cell — Iraq |
| `builtin_chef_hvt` | HVT leader |
| `builtin_reseau_courriers` | Courier network |
| `builtin_financier` | Financier |
| `builtin_technicien_ied` | IED technician |
| `builtin_safehouse` | Urban safehouse |
| `builtin_contrebande_sahel` | Border smuggling |
| `builtin_civil_bruit` | Irrelevant civilian (noise) |
| `builtin_drone_isr` | Drone / ISR cell |
| `builtin_propagande` | Propaganda / media |
| `builtin_iq_2010_2020_cache_armes` | Iraq 2010-2020 — Weapons cache |
| `builtin_iq_2010_2020_ied` | Iraq 2010-2020 — IED cell |
| `builtin_iq_2010_2020_hvt` | Iraq 2010-2020 — Sector leader |
| `builtin_iq_2010_2020_courrier` | Iraq 2010-2020 — Border courier |
| `builtin_iq_2010_2020_financier` | Iraq 2010-2020 — Financial relay |
| `builtin_iq_2010_2020_safehouse` | Iraq 2010-2020 — Urban safehouse |
| `builtin_ru_2020_2024_recon` | Russia 2020-2024 — Reconnaissance |
| `builtin_ru_2020_2024_logistics` | Russia 2020-2024 — Logistics |
| `builtin_ru_2020_2024_command` | Russia 2020-2024 — Command post |
| `builtin_ru_2020_2024_drone` | Russia 2020-2024 — Drone cell |
| `builtin_ru_2020_2024_ew` | Russia 2020-2024 — Radio / EW |
| `builtin_ru_2020_2024_infoops` | Russia 2020-2024 — Info ops |
| `builtin_ru_2020_2024_courier` | Russia 2020-2024 — Civilian courier |
| `builtin_ru_2020_2024_civil` | Russia 2020-2024 — Cover civilian |

---

## 9. Eden attributes of people and objects

Double-click a unit or an object. The SSE categories:

| Category | For | What you set there |
|---|---|---|
| **COMSPEC SSE — Intelligence** | Everything | Enable SSE intelligence, object nature, profile, generation timing, richness, identity (automatic, invent a name, force the Eden name), what the database must answer, names, alias, confidence score, seed, declared role, nationality, language, known number, biometrics (generated or none), Zeus notes, network ID (same name = same network), SSE model, **dataset and role in the dataset (lists)**, region. |
| **COMSPEC SSE — Documents** | People | Up to 3 written items: title, content, grid, code word. |
| **COMSPEC SSE — Phone** | People | Number, model, contacts, messages (`Sender: text`), notes, places (`Label | grid`). |
| **COMSPEC SSE — Computer** | People | Computer name, account, files, mail, network, access hint. |
| **COMSPEC SSE — Biometrics** | People | Biometric record (absent, enrolled, watchlist 1 to 4), flag reason, false papers, true identity, **biometric identifier (campaign)** for a recurring character, kinship, missing fingers, iris. On objects: whose latent prints. |
| **COMSPEC SSE — Organisation** | People, objects | Organisation, cell, role (leader to sympathiser), nom de guerre, what players know at start; on an object: which organisation it belongs to. |
| **Athena — Digital intelligence** | Objects | Same fields as the DOMEX module. |
| **COMSPEC SSE — DL-7 extraction** | Objects | Same fields as the DL-7 module, plus *Content*: generated SSE data, typed files, or both. |

---

## 10. SSE equipment

"**Equip SSE kit**" (ACE self menu › COMSPEC SSE) gives the basic kit. With the *Require SSE equipment* setting unchecked, no item is needed.

| Item | Class | Used for | In the kit |
|---|---|---|---|
| **SEEK II terminal** | `COMSPEC_SSE_SEEKII` | Open SEEK, iris, full capture. Also replaces the fingerprint kit, DNA kit and camera. Also given by the *Issue SEEK terminal* module. | yes |
| **SSE fingerprint kit** | `COMSPEC_SSE_FingerprintKit` | Fingerprints, latent prints on objects and dropped weapons. | yes |
| **SSE DNA kit** | `COMSPEC_SSE_DNKit` | DNA swab; the result comes back from the lab after a delay (15 min by default). | yes |
| **SSE camera** | `COMSPEC_SSE_Camera` | Take photos, face photo. | yes |
| **SSE evidence bag** | `COMSPEC_SSE_EvidenceBag` | Collect, seal, collect a USB/SD device, reveal a cache. **Not used up.** | yes |
| **SSE exam gloves** | `COMSPEC_SSE_Gloves` | Flavour: no action requires them. | yes |
| **SSE document pouch** | `COMSPEC_SSE_DocumentBag` | Flavour. | yes |
| **SSE field terminal** | `COMSPEC_SSE_Terminal` | Flavour: the SSE terminal opens without it. | yes |
| **Head bag (capture)** | `COMSPEC_SSE_HeadBag` | Put on a handcuffed, surrendered, unconscious or dead prisoner: they can no longer see, their face and iris cannot be captured. Given back when removed. | no |
| **Seized USB key**, **Seized memory card** | `COMSPEC_SSE_USB`, `COMSPEC_SSE_SDCard` | Appear in the inventory when collecting a device. | no |
| **Seized hard drive, laptop, phone, smartphone, satellite phone, notebook** | `COMSPEC_SSE_HardDrive`, `_Laptop`, `_Phone`, `_Smartphone`, `_SatPhone`, `_Notebook` | Inventory items for roleplay and the Arsenal; no action reads them. | no |
| **DL-7 data cable** (native ATAK) | `COMSPEC_ATAK_DataCable` | Plug the ATAK into a device. Required only if the ATAK setting *DL-7 cable required* is checked. | no |

Items from other mods are accepted as substitutes when *Accept items from other mods* is checked (for example an ACE medical kit for the bag).

---

## 11. Player actions (ACE menu)

**On a person** (Windows key › SSE): Inspect (5 s), Photograph (3 s, camera), Search (5 s), Mark as exploited, Read documents (6 s), **Open SSE terminal (files)**, Read dog tag (with ACE), Deepen exploitation, SSE timeline.
**Biometrics** sub-menu: Open SEEK II, Fingerprints (8 s), Iris (5 s), Face photo (3 s), DNA (12 s), Full capture (25 s), Identify (8 s).
**Digital exploitation** sub-menu: identify device, contacts, messages, calls, photos, coordinates, full extraction, system info, users, documents, browser, mail, connected devices, credentials, collect a USB/SD device.

**On an object**: Examine, Search (15 s), Collect (8 s, bag), Read documents, Exploit radio (radios), Open SSE terminal, Deepen, Seal (bag), Lift latent prints.

**Other**: Put on / remove a head bag; Hand over my sealed items (on a teammate); with the native ATAK, Plug / Unplug the ATAK (DL-7 cable).

**Self menu** (Ctrl+Windows › COMSPEC SSE): SSE journal, Technical log, Open SSE terminal, Equip SSE kit, Link chart, Evidence register, Items I hold, Current campaign, SSE guide (manual). Shortcut: Ctrl+Shift+S opens the SEEK terminal.

---

## 12. CBA settings

Escape › Options › Addon Options › **COMSPEC SSE**:

- **Equipment**: require SSE equipment, accept items from other mods, extra equipment aliases.
- **Durations**: inspect, searches, photo, fingerprints, phone, computer.
- **Generation**: useless data probability, false lead probability.
- **Network**: mission identifier, **SSE case reference**, map identifier, prefer the COMSPEC extension.
- **Intel**: training mode, operator level, automatic triage and pivot.
- **Biometrics** and **Biometrics — population**: thresholds, quality, DNA delay, latent prints, Athena wanted notices, head bag model, enrolled and watchlist shares per side.
- **Organisations and evidence**: extra roles and natures, code names, contacts revealed per exploitation, item and seal prefixes.
- **Campaigns**: copy saves to Athena.
- **Compatibility**: ACE dog tag to SSE identity, BII Identifi bridge.

On the native ATAK side, category **COMSPEC › ATAK · Data extraction**: DL-7 cable required, cable length, transfer speed.

---

## 13. Pitfalls and troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| The Scenario director gave no role | Nobody in the radius, or fewer people than roles | Widen the radius, add people (FALCON level 1: 4). |
| A player became ABU KARIM | Players count; the closest becomes the leader | Move the module away from the players. |
| Records land in "Checks of ... (to sort)" | No case reference, or several open cases | SSE case reference module. |
| An unknown case appeared on Athena | Typo in the reference | Fix the reference; delete the extra case. |
| The campaign does not reload | Different identifier from one mission to the next, or a different server | Same identifier; for another server, go through Athena (send then fetch). |
| The identifier shown on Athena does not work | Athena shows `CODE:KEY` | Use only `KEY`. |
| DL-7 files disappeared | The *Data to extract* module and the *Extractable with DL-7 cable* box on the same object: the last one replaces the other | Only one of the two, then *Add a file* after. |
| No biometric action | Equipment required and missing | Equip the SSE kit, or uncheck *Require SSE equipment*. |
