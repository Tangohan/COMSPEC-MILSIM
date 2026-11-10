# Biometrics and the SEEK II terminal

French version: [biometrie.md](biometrie.md). Guides index: [index.en.md](index.en.md).

## What it is for

Every character has their fingerprints (ten fingers, minutiae, Henry classification), irises, face and DNA; no two characters ever share a biometric. The **Terminal SEEK II** (SEEK II terminal) captures these traits on a person (alive, handcuffed, unconscious or dead), compares them with the database (local when offline, full ABIS when linked) and returns a verdict: MATCH, possible match, unknown. A positive identification **confirms membership** on the link chart and exposes false papers.

## Where to find it

| Need | Path |
|---|---|
| Open the terminal | **Ctrl+Shift+S** (CBA keybind « Ouvrir l'outil SSE (ATAK / SEEK II) » (Open the SSE tool (ATAK / SEEK II))): ATAK phone › SSE terrain (Field SSE) app if carried, otherwise BII-10, otherwise SEEK on the person aimed at within 6 m (otherwise on yourself) |
| From the ATAK | **SSE terrain** app › **CIBLE** (TARGET) tab (a person aimed at) › **SEEK II** |
| Manual captures (kits) | ACE menu on the person › **SSE** › **Relever les empreintes (kit)** (Take fingerprints (kit)) (8 s, 10 uses; hidden if you carry a SEEK), **Prélèvement ADN (écouvillon buccal)** (DNA sample (buccal swab)) (6 s), **Test de résidus de tir** (Gunshot residue test) (6 s), **Enrôlement biométrique (SEEK II)** (Biometric enrolment (SEEK II)) |
| Latents | ACE menu on a weapon on the ground › **Relever des empreintes latentes** (Lift latent prints) (fingerprint kit) |
| Head bag | ACE menu on a prisoner › **Mettre un sac sur la tête** (Put a bag over the head) (4 s) / **Retirer le sac de tête** (Remove the head bag) (2 s) |
| Equipment | Items « SEEK II », « Kit empreintes » (Fingerprint kit), « Kit ADN » (DNA kit), « Écouvillons ADN / résidus de tir » (DNA / gunshot residue swabs); crate **Caisse kit biométrie** (Biometrics kit crate) (Eden / Zeus **Poser une caisse de kit** (Place a kit crate)); module **Doter d'un terminal SEEK** (Issue a SEEK terminal) |

Without a terminal: « Terminal SEEK II (ou tablette cTab / Android ATAK compatible) requis. » (SEEK II terminal (or compatible cTab / Android ATAK tablet) required.); no tool at all: « Aucun terminal SSE n'a pu s'ouvrir. Emportez un téléphone ATAK, un SEEK ou un BII-10. » (No SSE terminal could be opened. Carry an ATAK phone, a SEEK or a BII-10.)

## The SEEK II window

Title « SEEK II — BIOMETRIC TERMINAL », subtitle « Photo · empreintes · iris · identité · score · qualité » (Photo · fingerprints · iris · identity · score · quality).

| Button | Effect |
|---|---|
| **EMPREINTES** (FINGERPRINTS), **IRIS**, **VISAGE** (FACE), **ADN** (DNA) | Capture of the trait (progress bar and beeps; the operator kneels over a body, a cooperative subject holds out their hand or looks at the device) |
| **CAPTURE ALL** | Capture everything |
| **IDENTIFIER** (IDENTIFY) | Database search (5 s offline, 12 s linked; DNA: lab result after 15 min) |
| **TRANSMETTRE** (TRANSMIT) | Sends the sheet to Athena |
| **TERMINAL SSE** (SSE TERMINAL) | Opens the field SSE terminal (« TERMINAL SSE — TERRAIN » (SSE TERMINAL — FIELD) screen) |
| **FERMER** (CLOSE) | Closes |

**ACQUISITION BIOMÉTRIQUE** (BIOMETRIC ACQUISITION) panel: Record SSE (SSE record), Identité supposée (Assumed identity), Alias; Prélèvements (Captures) Empreintes / Iris / Visage / ADN (NONE at the start); Qualité moyenne du prélèvement (Average capture quality) (%); Résultat de recherche (Search result) (« Aucune recherche effectuée » (No search performed)), Score de correspondance (Match score), Référence (Reference), Statut (Status) (READY). It refreshes 15 s after a capture.

## Reading the result

| Line | Meaning |
|---|---|
| **MATCH · TIER n · score** | Identity found (at least two matching fingers or one matching iris, score above the 60 threshold). Tier = watchlist level: 1 DETAIN, 2 QUESTION / DETAIN, 3 QUESTION / DENY ACCESS, 4 for information |
| **POSSIBLE MATCH · score** | Intermediate score (threshold 25): to be confirmed by another capture |
| **QUALITÉ INSUFFISANTE · RECAPTURER** (INSUFFICIENT QUALITY · RECAPTURE) | Capture too degraded |
| **INCONNU DES BASES · EMPREINTES SUR OBJETS SAISIS** (UNKNOWN TO DATABASES · PRINTS ON SEIZED ITEMS) | Not on file, but their prints are on a seized exhibit |
| **NO MATCH** | Unknown to the database consulted |
| **ALERTE : identité déclarée « … » différente du dossier (faux papiers ?)** (ALERT: declared identity « … » differs from the file (false papers?)) | The papers do not match the file |

Then: Dossier (File) (ref., name, alias), Motif (Reason), Conduite (Handling), Source, « Déjà rencontré n fois » (Already encountered n times), « Avis de recherche Athena » (Athena wanted notice), finger / iris / face match, minutiae (threshold 12), iris Hamming distance (threshold 0.32), face similarity, Henry classification, « Autre candidat » (Other candidate) (possible false alarm), latents, and the scope: « Base locale du terminal (hors liaison) » (Terminal local database (offline)) or « ABIS complet (en liaison) » (Full ABIS (linked)).

## Step by step: enrolling a prisoner

1. The subject is handcuffed (ACE). Get within 6 m, **Ctrl+Shift+S** (or SSE terrain › CIBLE › SEEK II).
2. **EMPREINTES** then **IRIS** then **VISAGE**: each trait goes from NONE to its quality score. Hand wounded? Treat it first (ACE), otherwise the capture is refused.
3. **IDENTIFIER**: « MATCH · TIER 2 · 78 », Dossier HVT-ABOU-SAMIR, Motif, Conduite: QUESTION / DETAIN. His membership becomes confirmed on the link chart.
4. **ADN** (DNA kit or swabs): the result arrives 15 min later, with familial search.
5. **TRANSMETTRE** to Athena. Only then, **Mettre un sac sur la tête** (the bag prevents face and iris).

## Setting a character's biometrics

- Eden, unit attributes › **COMSPEC SSE — Biométrie** (COMSPEC SSE — Biometrics): Fichier biométrique (Biometric file) (Automatique (Automatic) / Inconnu de toute base (Unknown to any database) / Enrôlé, non signalé (Enrolled, not flagged) / Watchlist tier 1 à 4 (Watchlist tier 1 to 4)), Motif de signalement (Flag reason), Faux papiers (False papers) (Automatique / Oui (Yes) / Non (No)), Vraie identité (True identity) (if false papers), Identifiant biométrique (campagne) (Biometric identifier (campaign)) (e.g. `HVT-ABOU-SAMIR`: same character from one mission to the next), Parent de (Relative of) + Lien de parenté (Family relationship) (Frère / sœur (Sibling), Parent, Enfant (Child): DNA familial search), Doigts manquants (Missing fingers) (1-10), Iris (Cataracte (Cataract), Prothèse (Prosthesis), Lésion (Lesion)). On items: **Empreintes latentes de** (Latent prints of).
- Zeus: ZEN › **COMSPEC · Renseignement (SSE)** (COMSPEC · Intelligence (SSE)) › **Régler la biométrie** (Set biometrics) (same fields) and **Définir l'identité** (Define identity) (« Ce que la base doit répondre » (What the database should answer), Alias connu (Known alias), Nom (Last name), Prénom (First name), Nationalité déclarée (Declared nationality), Langue parlée (Spoken language), Référence de dossier antérieur (Prior file reference)).

## Common pitfalls

- **Quality matters**: night, rain, agitated subject, old body, wounded hands or face (ACE) degrade the capture; beyond the threshold (setting « Blessure qui empêche le relevé » (Injury that prevents capture), 1.2), it is impossible. An amputated finger or an artificial eye are missing.
- **Offline**, the local database only knows wanted persons up to the level of the setting « Base hors ligne du terminal » (Terminal offline database) (2) and the encounters of the mission.
- **False alarms** (4 %, setting): a look-alike candidate with few minutiae; confirm with another trait.
- **Head bag on**: no more face or iris. Capture first.
- **Examination gloves**: without them you leave your own prints and DNA on items.
- The SEEK **without a valid target within 6 m opens on yourself**.
- The ACE button « Relever les empreintes (kit) » disappears when you **carry a SEEK** (the terminal does better).
- Settings (COMSPEC SSE › Biométrie): Biométrie réaliste (Realistic biometrics), Score pour une correspondance (Score for a match) (60) et possible (and possible) (25), Fausses alertes (False alarms) (4 %), Effet des conditions (Effect of conditions) (1), Blessure qui empêche le relevé (1.2), Base hors ligne du terminal (2), Durée de recherche hors ligne (Offline search duration) (5 s) / en ligne (online) (12 s), Délai du résultat ADN (DNA result delay) (900 s), Empreintes laissées sur les objets (Prints left on items), Avis de recherche Athena, Photo du visage en studio (Studio face photo), Modèle de la cagoule de prisonnier (Prisoner hood model), Garder les rencontres d'une mission à l'autre (Keep encounters from one mission to the next); **Biométrie : population** (Biometrics: population) (Déjà fichés (Already on file) and Recherchés (Wanted) per side, Civils / Combattants sous faux papiers (Civilians / Combatants under false papers)).

## Athena

- **TRANSMETTRE** sends the person sheet then the biometrics to the SSE Desk case (queued when offline).
- **Avis de recherche Athena** (setting): the community's and the operation's wanted persons are imported into the terminal database.
- When linked, IDENTIFIER queries the full ABIS (12 s); the campaign copied into Athena keeps the biometric encounters.
