# Identity check

French version: [controle_identite.md](controle_identite.md). Guides index: [index.en.md](index.en.md). Detailed reference (realistic AI, checkpoints, HVT, API): [../guide/Controle_identite.md](../guide/Controle_identite.md).

## What it is for

Checking the papers of a person (AI or player): asking for them, examining them by eye, scanning them with the ATAK phone level by level (MRZ, chip, database, face), logging them in your check logbook, seizing them or handing them back. A check detects a forgery, a stolen or revoked document, a flagged, wanted or HVT person, and feeds the reports (FRM, SALUTE, SPOTREP) and Athena.

## Where to find it

| Need | Path |
|---|---|
| Check a person | ACE menu on the person › **Contrôle d'identité** (Identity check) |
| Check the occupants of a vehicle | ACE menu of the stopped vehicle › **Contrôle d'identité › Contrôler les occupants** (Identity check › Check the occupants) or **Faire descendre** (Order out) |
| My own papers | Personal ACE menu › **Mes papiers** (My papers) |
| Scanner, logs, BOLO | ATAK phone › Intelligence › **Contrôle ID** (ID Check) app (tabs **CONTRÔLE** (CHECK), **HISTORIQUE** (HISTORY), **BOLO**) |
| Settings | Esc › Options › Addon settings › **COMSPEC SSE › Identité** (COMSPEC SSE › Identity) |

## The ACE menu « Contrôle d'identité »

| Action | Duration | Condition | Effect |
|---|---|---|---|
| **Demander les papiers** (Ask for papers) | 2 s (« Papiers, s'il vous plaît… » / "Papers, please…") | Living person, on foot, within 3 m | An AI responds (hands over their papers, lies, refuses, flees…). A player receives the request on their ATAK and answers through **Mes papiers** |
| **Contrôler l'identité › <document>** (Check identity › <document>) | | While the presentation is valid (120 s, setting) | **Examiner (contrôle visuel)** (Examine (visual check)) (2 s) opens the viewer; **Saisir** (Seize) (2 s) puts the document in your inventory with a chain of custody |
| **Fouiller les papiers** (Search for papers) | 6 s (4 s on a body) | Person dead, handcuffed, surrendered or unconscious | The documents found are listed under **Documents trouvés** (Documents found) (180 s): Examine / Seize; a searched player is notified on their ATAK |
| **Rendre un document › Rendre : <nom>** (Return a document › Return: <name>) | 1 s | You hold one of their documents | Gives it back to them |
| **Poser une question** (Ask a question) | 1 s | Conscious AI, within 3 m | « D'où venez-vous ? » (Where are you coming from?), « Pour qui travaillez-vous ? » (Who do you work for?), « Raison du déplacement ? » (Reason for travelling?), « Quel est votre métier ? » (What is your job?), « Où êtes-vous né ? » (Where were you born?), « Qui peut confirmer ce que vous dites ? » (Who can confirm what you say?): the answer is shown in the Contrôle ID app (compare it with the papers) |

With the setting **Ouvrir les papiers tendus à l'écran** (Open handed-over papers on screen) (yes by default), a presented document opens immediately in the viewer.

## The « Mes papiers » menu (checked person's side)

- **Présenter mes papiers › <document> › Présenter** (Present my papers › <document> › Present) (the checker can examine it for 120 s) or **Remettre** (Hand over) (the item goes into their inventory).
- **Refuser** (Refuse) (if a request is pending).
- **Répondre** (Answer) (cover-story questions from an AI checkpoint: "question → answer").
- **Ouvrir mes papiers** (Open my papers): the viewer on your own documents.

## The document viewer

Window opened by Examine, Open my papers, or the Area AI page (OUVRIR / OPEN).

| Button | Effect |
|---|---|
| **RECTO / VERSO** (FRONT / BACK) | Flips the side (no effect on a passport, a single page) |
| **ZOOM** | Enlarges the document (from 46 % to 70 % of the screen height) |
| **SCANNER (ATAK)** (SCAN (ATAK)) | Closes the viewer, opens the ATAK on the Contrôle ID app with this document. Without a phone: « Pas de téléphone ATAK sur vous : impossible de scanner le document. » (No ATAK phone on you: the document cannot be scanned.) |
| **RELEVER** (LOG) | Records the document (type, country, number, surname, first name, birth, expiry, bearer, place and time, visual examination) in your **relevé de contrôle** (check log): phone › Contrôles / Fichiers (Checks / Files), then Athena « Contrôles subis » (Checks undergone). Status line: « Relevé : ALF814703 noté dans votre téléphone (Contrôles) » (Logged: ALF814703 recorded in your phone (Checks)). If not possible, the button says so (« Pas de téléphone » / No phone, « Déjà relevé à 20:37 » / Already logged at 20:37) |
| **FERMER** (CLOSE) | Closes |

Feedback is shown in the window's **status line**, never as an Arma hint. The visual examination (N0) happens here: « Hologramme de sécurité absent. » (Security hologram missing.), « La photo semble rapportée : bord décollé, plastification reprise. » (The photo looks replaced: lifted edge, re-laminated.), « Caractères du nom irréguliers : police différente du reste du document. » (Irregular characters in the name: font differs from the rest of the document.), « Document expiré depuis le … » (Document expired since …), « Carte militaire, mais la personne est en civil. » (Military ID, but the person is in civilian clothes.), or « Examen visuel : rien d'anormal. » (Visual examination: nothing abnormal.). At night: « Trop sombre pour bien examiner le document : éclairez-le. » (Too dark to examine the document properly: light it up.)

## The check levels (Contrôle ID app › CONTRÔLE tab)

| Level | Button | Duration | Requires | What it tells you |
|---|---|---|---|---|
| **N0 · visual** | (viewer) | 2 s | Light, a trained eye (`COMSPEC_ID_Expert`: better examination) | Physical flaws of the forgery, depending on its quality and the difficulty |
| **N1 · MRZ** | **Lire la MRZ (N1)** (Read the MRZ (N1)) | 3 s | ATAK phone | ICAO check digits, consistency with the front: « MRZ conforme ICAO, chiffres de contrôle justes, identique au recto. » (MRZ ICAO-compliant, check digits correct, matches the front.) or « MRZ : chiffre de contrôle du numéro faux. » (MRZ: number check digit wrong.), « MRZ différente du recto : le nom ne correspond pas. » (MRZ differs from the front: the name does not match.), « Pas de bande MRZ sur ce document. » (No MRZ strip on this document.) |
| **N2 · chip** | **Lire la puce (N2)** (Read the chip (N2)) | 4 s, within 1.5 m | Phone (NFC) | « Puce lue : signature valide, données identiques au document. » (Chip read: valid signature, data identical to the document.), « Puce lue : la photo enregistrée n'est pas celle imprimée. » (Chip read: the stored photo is not the printed one.), « Aucune puce détectée sur un document qui doit en avoir une. » (No chip detected on a document that should have one.), « Ce document n'a pas de puce. » (This document has no chip.) (driving licence) |
| **N3 · database** | **Interroger la base (N3)** (Query the database (N3)) | 3 s | Phone; Athena online completes the local database | « Base du pays : document délivré, valide, aucun signalement. » (Country database: document issued, valid, no alert.), « Numéro inconnu de la base du pays : jamais délivré. » (Number unknown to the country database: never issued.), « Document signalé VOLÉ. » (Document reported STOLEN.), « Document RÉVOQUÉ par l'autorité. » (Document REVOKED by the authority.), « BOLO : document à surveiller (réf. …) » (BOLO: document to watch (ref. …)), « AVIS : personne RECHERCHÉE / HVT (réf. …) » (NOTICE: WANTED person / HVT (ref. …)), « Athena injoignable : base locale seule. » (Athena unreachable: local database only.) |
| **N4 · face** | **Comparer le visage (N4)** (Compare the face (N4)) | 8 s, within 3 m | Phone or SEEK; setting « Comparaison du visage (N4) » (Face comparison (N4)) | Face compared with the biometric database and the document photo, with a confidence rate; an HVT is recognised even under a false name |

Verdict banner: **RAS : DOCUMENT CONFORME** (ALL CLEAR: DOCUMENT VALID) · **DOUTE : À APPROFONDIR** (DOUBT: INVESTIGATE FURTHER) · **DOCUMENT EXPIRÉ** (DOCUMENT EXPIRED) · **NUMÉRO INCONNU : FAUX PROBABLE** (NUMBER UNKNOWN: PROBABLE FORGERY) · **FAUX DOCUMENT** (FORGED DOCUMENT) · **DOCUMENT VOLÉ** (STOLEN DOCUMENT) · **DOCUMENT RÉVOQUÉ** (REVOKED DOCUMENT) · **PERSONNE SOUS SURVEILLANCE** (PERSON UNDER SURVEILLANCE) · **PERSONNE RECHERCHÉE** (WANTED PERSON) · **HVT : CIBLE PRIORITAIRE** (HVT: PRIORITY TARGET). A flagged person makes the phone vibrate.

Other buttons of the app: **Transmettre à Athena** (Send to Athena) (becomes « Transmis » / Sent), **Joindre à une FRM** (Attach to an FRM), **Confirmer le PID** (Confirm PID) (HVT or wanted verdict), **Relever le document** (Log the document) (becomes « Document relevé » / Document logged), **SALUTE**, **SPOTREP**, **Partager** (Share), **NFC**, **Sur mon téléphone** (On my phone), **Voir** (View). **HISTORIQUE** tab: your last 50 checks. **BOLO** tab: current notices.

## Step by step: a check at a roadblock

Situation: your buddy team holds a roadblock; a car stops, two civilians on board.

1. ACE menu of the vehicle › **Contrôle d'identité › Faire descendre**, then on the driver › **Demander les papiers**. He hands over an identity card: it opens in the viewer.
2. **RECTO / VERSO**, **ZOOM**: the font of the name looks different. Status line: « Caractères du nom irréguliers… ».
3. **SCANNER (ATAK)** › **Lire la MRZ (N1)**: « MRZ différente du recto : le nom ne correspond pas. » Verdict **FAUX DOCUMENT**.
4. **Interroger la base (N3)**: « AVIS : personne RECHERCHÉE (réf. …) ». The phone vibrates.
5. **Relever le document** (or RELEVER in the viewer): the log goes into your logbook and to Athena. **Joindre à une FRM** then **SPOTREP** to report it.
6. Handcuff (ACE): ACE menu › **Contrôle d'identité › Saisir** the document (chain of custody); **Fouiller les papiers** for the rest.

## AI checkpoints

Eden module **Poser un checkpoint IA** (Place an AI checkpoint) (category COMSPEC · Identité / COMSPEC · Identity) or Zeus ZEN **COMSPEC · Identité › Poser un checkpoint IA**: **Rayon (m)** (Radius (m)) (0 = setting « Distance des checkpoints » / Checkpoint distance, 25 m), **Camp du poste** (Post side) (Automatique / BLUFOR / OPFOR / Indépendant / Civil (police) — Automatic / BLUFOR / OPFOR / Independent / Civilian (police)), **Équipement du poste** (Post equipment) (Visuel seul N0 / Scanner MRZ N1 / Lecteur NFC N2 / Liaison base N3 — Visual only N0 / MRZ scanner N1 / NFC reader N2 / Database link N3), **Sévérité** (Severity) (Laxiste / Normale / Stricte — Lax / Normal / Strict), **Nom du poste** (Post name). Guards: synchronised units, otherwise AI of that side in the area.

A player who enters: « Papiers, s'il vous plaît » on the ATAK, 45 s (setting) to **Mes papiers › Présenter** within 3 m of the guard; examination according to the equipment; 2 cover-story questions (3 at a strict post); outcome: let through · search (8 s) · detain (3 min, Zeus notified) · raise the alarm · open fire. **Retirer un checkpoint IA** (Remove an AI checkpoint), **Déclencher un contrôle** (Trigger a check) (on a person, by the post covering them or the nearest armed AI), **Forcer un état IA** (Force an AI state) (Interpellé, Coopère, Ment, Nerveux, Refuse, Fuit, Agressif, Se rend, Appelle des renforts, Calme — Stopped, Cooperates, Lies, Nervous, Refuses, Flees, Aggressive, Surrenders, Calls reinforcements, Calm) and **Imposer un couvre-feu** (Impose a curfew) complete the Zeus toolkit.

## Settings (COMSPEC SSE › Identité)

Contrôle d'identité (Identity check) (yes) · Difficulté des contrôles (Check difficulty) (Arcade / **Standard** / Réaliste — Arcade / Standard / Realistic) · Univers réaliste-simulation (pays réels stylisés) (Realistic-simulation universe (stylised real countries)) (no) · Configurer une IA à sa pose (Zeus) (Configure an AI when placed (Zeus)) (no) · IA sans papiers (AI without papers) (0 %) · Photo d'identité en direct (Live ID photo) (yes) · Pays des papiers (Country of the papers) (Selon la carte / According to the map) · Papiers des IA (AI papers) (yes) · Rendre les papiers du kit au respawn (Return kit papers on respawn) (yes) · Civils qui présentent leurs papiers (Civilians who present their papers) (90 %) · Insurgés porteurs de faux papiers (Insurgents carrying forged papers) (60 %) · Durée d'une présentation (Presentation duration) (120 s) · Transmission automatique à Athena (Automatic transmission to Athena) (yes; the community setting prevails) · Documents et avis d'Athena (Athena documents and notices) (yes) · Opération Athena (clé) (Athena operation (key)) · IA réalistes au contrôle (Realistic AI when checked) (yes) · Agressivité des IA (AI aggressiveness) (50 %) · Distance des checkpoints (Checkpoint distance) (25 m) · Ouvrir les papiers tendus à l'écran (yes) · Délai pour présenter ses papiers (Time to present papers) (45 s) · Les checkpoints contrôlent aussi les IA (Checkpoints also check AI) (yes) · Couvre-feu (Curfew) (no) · Infiltré captif tant que sa couverture tient (Infiltrator captive while their cover holds) (yes) · Soupçons avant que la couverture saute (Suspicion before the cover is blown) (100) · Distance des témoins (Witness distance) (50 m) · Questions de légende (Cover-story questions) (yes) · Délai pour répondre (Time to answer) (20 s) · Comparaison du visage (N4) (yes) · Durée de la comparaison du visage (Face comparison duration) (8 s) · Notifier les BOLO (Notify BOLOs) (yes).

## Common pitfalls

- **Without an ATAK phone**, only N0 is possible: SCANNER answers « Pas de téléphone ATAK sur vous ».
- **At night**, light the document (weapon flashlight): otherwise « Trop sombre pour bien examiner ».
- The visual examination roll is **reproducible**: redoing the same check gives the same result; change level.
- The **presentation expires** (120 s): the « Contrôler l'identité » submenu disappears; ask again.
- **A good forgery passes N0 and sometimes N1** (high quality): go up to the database or the face.
- A **prepared cover** passes N0 to N3; only biometrics (N4, SEEK) give it away.
- **RELEVER is not a photo**: the log is text (Arma's screenshot does not capture the interface); the physical flaws are not logged, only your visual examination is.
- **Already logged**: the button says so with the time; a document is logged only once per check.
- **False positive at N4**: balaclava, scarf, night and distance degrade the shot; confirm with the SEEK (fingerprints, iris).
- A searched player **is notified** on their ATAK; a checked player answers themselves (Present, Hand over, Refuse).

## Athena

- Every check transmitted (**Transmettre à Athena**, or automatically) creates an entry in SSE Office › **Contrôles** (Checks) (reference CTRL-…): document, printed identity, N0 to N4 results, verdict, grid reference, checker; without a link, queued (« Pas de liaison Athena : contrôle mis en file d'attente, envoyé au retour de la liaison. » / No Athena link: check queued, sent when the link returns.).
- **RELEVER** / **Relever le document** feeds your logbook (phone › Contrôles) and Athena « **Contrôles subis** » on the record of the checked member.
- **Documents et avis d'Athena** (setting): stolen, lost, revoked documents, BOLOs and wanted persons of the operation come back into the game at N3.
- The FRM sent and its document photo are attached to the check on Athena.
