# Covers, infiltrators and « Ma couverture »

French version: [ma_couverture.md](ma_couverture.md). Guides index: [index.en.md](index.en.md).

## What it is for

An **infiltrated** player (or AI) lives under a cover identity: papers of the "cover" kind and a **cover story** (address, employer, job, family, stock phrases). As long as the cover holds, enemies do not target them and AI checkpoints check them like a civilian. It is **blown** ("burned") when they give themselves away. The phone app **Ma couverture** (My cover) shows the player their record, their consistency and what gives them away; the Zeus sees all infiltrators there.

## Where to find it

| Need | Path |
|---|---|
| Infiltrate a player (Zeus) | ZEN › **COMSPEC · Identité › Donner une couverture** (COMSPEC · Identity › Give a cover) on the person |
| Infiltrate in Eden | Unit attributes › COMSPEC SSE — Papiers (Papers) › **Infiltré (couverture)** (Infiltrator (cover)) (+ Nature des papiers (Kind of papers): Couverture préparée / Prepared cover) |
| Through Athena | Cover brief (Mission preparation › briefs) or "cover" paper in the kit: the infiltration opens on arrival |
| Through the SSE editor | **Infiltration** kit (identity + cover papers + cover story), "Sur quelqu'un" (On someone) |
| Their record | ATAK phone › Intelligence › **Ma couverture** |
| Answer a guard | Ma couverture app, or personal ACE menu › **Mes papiers › Répondre** (My papers › Answer) |
| Restricted area | ZEN › **COMSPEC · Identité › Poser une zone interdite** (Place a restricted area) (Rayon / Radius 0-500 m, Nom / Name; 0 removes the nearest) or markers named `comspec_id_interdit…` |

## The « Donner une couverture » module

| Field | Values | Example |
|---|---|---|
| **Action** | Infiltrer (Infiltrate) · Brûler la couverture (Blow the cover) · Lever l'infiltration (End the infiltration) | Infiltrer |
| **Captif tant que la couverture tient** (Captive while the cover holds) | Selon le réglage de mission (According to the mission setting) · Oui (Yes) · Non (No) | Oui: enemy AI do not shoot as long as they are not blown |
| **Lui donner une carte de couverture** (Give them a cover card) | checkbox (no) | Checked: cover identity card (quality 90) in the name entered or drawn |
| **Nom de la couverture** (Cover surname), **Prénom de la couverture** (Cover first name) | text | "STAVROU", "Nikos" |

## The « Ma couverture » app

Visible only to you (and the Zeus): **status** (holds / blown), **consistency** (0 to 100) and **what gives you away**, identity, address, employer, occupation, phone, contacts, supplementary documents, history, biography, family, languages, stock phrases, sensitive topics, secrets, Athena brief and instructions. **ZEUS** tab (Zeus only): all infiltrators, their consistency, their record with the real name.

Consistency = papers (one cover document, a single name, its preparation) + cover story (correct answers) + equipment (nothing military in sight) + behaviour (suspicion). It weighs on a checker's visual examination and on the suspicion of checkpoints.

## Cover-story questions

After reading the papers, a guard asks 2 questions (3 at a strict post): « D'où venez-vous ? » (Where are you coming from?), « Pour qui travaillez-vous ? » (Who do you work for?), « Raison du déplacement ? » (Reason for travelling?), « Quel est votre métier ? » (What is your job?), « Où êtes-vous né ? » (Where were you born?), « Qui peut confirmer ce que vous dites ? » (Who can confirm what you say?). Three answers to choose from (the true cover story and two decoys), in Ma couverture or **Mes papiers › Répondre**. Time limit: 20 s (setting), +10 s per question. Wrong answer or silence: suspicion.

## Getting your cover from Athena

A cover generated on Athena (My cover › Generate) can be fetched without handling any file:

1. ATAK phone › **Ma couverture** › **Import my cover** section › **SYNC WITH ATHENA**.
2. The **My covers on Athena** window lists your covers: name, job and city, country, state (New, Already imported, Update available, Active, Expired), size, date.
3. Tap **CHOOSE** on one or more covers (or **SELECT ALL**), then **IMPORT SELECTION**. The status line gives the result, for example "2 imported, 1 already up to date".
4. The most recent one in the selection becomes your **active cover** (the one your legend uses) and replaces the previous one. The others stay on this computer: **ACTIVATE** plays one of them instead.

Requirements: licence accepted, Athena link authorised on this computer, Athena account linked to the phone (otherwise the window offers **LINK MY ACCOUNT**), COMSPEC Link 2.0.118 or later. Nothing is asked of Athena until these are met. An expired cover (90 days) can no longer be imported. The photo stays on Athena.

Without a connection: on Athena › My cover, **Download**, put the file in `Documents\Arma 3 - COMSPEC`, then **IMPORT A FILE**. **Forget the import** goes back to the previous legend without touching your papers.

## Step by step: crossing an enemy checkpoint

1. Before the mission, the Zeus: **Donner une couverture › Infiltrer**, card checked, "STAVROU Nikos". You receive the card and your cover story in **Ma couverture**: read it (job, employer, place of birth).
2. Drop everything military (long weapon on your back, vest, helmet, NVG): the app lists what gives you away; aim for a consistency above 70.
3. At the checkpoint: « Papiers, s'il vous plaît » (Papers, please) on the ATAK. Within 3 m of the guard, **Mes papiers › Présenter mes papiers › Carte d'identité › Présenter** (My papers › Present my papers › Identity card › Present) (within 45 s).
4. Two questions: answer in **Ma couverture** (the answer that fits your cover story) before the time runs out.
5. Let through: the cover holds. Detained: suspicion; alarm: blown, enemies within 400 m go to combat.

## Common pitfalls

- **What blows the cover immediately**: a gunshot near an enemy, handcuffs, a checkpoint alarm, a weapon found during a search, a weapon in hand seen by an enemy (witness distance: 50 m, setting).
- **What builds up suspicion** (limit 100, setting): long weapon on the back, military clothing, vest, helmet, NVG seen up close; restricted area; gunshot heard; failed check; wrong answers; being detained.
- Blown: you are notified **discreetly** (vibration), as are the Zeus and the AAR report; enemies know your position.
- The cover passes N0 to N3 but **not biometrics**: an N4 or a SEEK identifies you if you are on file.
- Several papers in **different names** on you drop the consistency (a single name).
- A **player without an Athena paper** sees in Ma couverture an identity drawn by the mod, with the reason (package not loaded, no paper for this Steam account…).
- Settings: Infiltré captif tant que sa couverture tient (Infiltrator captive while their cover holds) (yes) · Soupçons avant que la couverture saute (Suspicion before the cover is blown) (100) · Distance des témoins (Witness distance) (50 m) · Questions de légende (Cover-story questions) (yes) · Délai pour répondre (Time to answer) (20 s).

## Athena

- The **cover brief** prepared on Athena (Mission preparation › briefs) arrives in Ma couverture (brief and instructions) and opens the infiltration on arrival.
- The cover card of the Athena kit is **the member's own**: at a check, it is the Athena cover identity that is shown.
- Blown cover and checks undergone are reported to Athena (report, Contrôles subis / Checks undergone).
