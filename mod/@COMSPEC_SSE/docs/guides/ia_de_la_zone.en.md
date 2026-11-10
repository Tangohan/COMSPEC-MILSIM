# Area AI (AI workshop)

French version: [ia_de_la_zone.md](ia_de_la_zone.md). Guides index: [index.en.md](index.en.md).

## What it is for

The **IA DE LA ZONE** (AREA AI) page of the SSE editor sets, for nearby or selected AI, **who they are at an identity check**: their Athena profile, the papers they carry, the kind of those papers (genuine, cover, forged), their behaviour when checked, their side. It is also used to inspect and correct an AI's papers, and to **keep** a configuration for the next games on the same map.

Without it, AI receive papers "depending on their side" (CBA Identity settings: civilians who present their papers, insurgents carrying forgeries, AI without papers) and react according to the context.

## Where to find it

| Context | Path |
|---|---|
| Zeus with ZEN | **COMSPEC · SSE › Configurer une IA** (COMSPEC · SSE › Configure an AI) (on an AI or with a selection) |
| Zeus without ZEN | **Éditeur SSE** (SSE Editor) module placed on a living AI, or with AI selected |
| From the editor | **IA DE LA ZONE** button at the top of the window |
| Automatic | CBA setting **Configurer une IA à sa pose (Zeus)** (Configure an AI when placed (Zeus)) (COMSPEC SSE › Identité / Identity, off by default): the page opens as soon as a Zeus places an AI |
| Script | `[unité ou liste, position] call comspec_sse_fnc_aiConfigOpen` |

No AI page in Eden (the button is hidden): set Eden AI through their attributes **COMSPEC SSE — Papiers** (COMSPEC SSE — Papers) and **Profil au contrôle** (Profile when checked).

## The window

### Left column

- **DÉTECTER** (DETECT): radius **25 / 50 / 100 / 250 / 500 m** (50 m by default), buttons **AUTOUR** (AROUND) (AI around the module position) and **SÉLECTION ZEUS** (ZEUS SELECTION) (AI selected in Zeus), counter "N IA" (N AI). At most **60 AI** per sweep.
- **AI list**: name, side, profile (« généré » / generated, or the Athena reference), papers (« à la demande » / on demand, « aucun » / none, or their number), group. Click a row to configure it.
- **SES PAPIERS** (THEIR PAPERS): the papers of the chosen AI, in the format "Type · no. X · First name SURNAME · True/False". Buttons **OUVRIR** (OPEN) (viewer), **MARQUER VRAI** (MARK TRUE), **MARQUER FAUX** (MARK FALSE) (same name, quality 50), then a type list (Carte d'identité / Identity card, Passeport / Passport, Permis de conduire / Driving licence, Carte militaire / Military ID and the extension types) with **AJOUTER CE PAPIER** (ADD THIS PAPER).
- **GARDÉS POUR LES PROCHAINES PARTIES** (KEPT FOR NEXT GAMES): the configurations kept on Athena for this map; **RETIRER** (REMOVE), **RECHARGER** (RELOAD).

### Right column « CONFIGURATION »

| Field | Values | Example |
|---|---|---|
| **Profil Athena** (Athena profile) | **Générer une identité** (Generate an identity), then the SSE Office profiles ("ref · label (name)") | "P-014 · Passeur (Yannis Petrakis)": the AI takes this name, this cover story and these papers; an N3 check recognises them on Athena |
| **Papiers** (Papers) | Selon son camp (Depends on their side) · Aucun (None) · Carte d'identité · Passeport · Permis de conduire · Carte et permis (Card and licence) · Carte et passeport (Card and passport) · Carte militaire | "Carte et permis" for a driver |
| **Nature** (Kind) | Vrais papiers (Genuine papers) · Couverture (Cover) · Faux papiers (Forged papers) | "Faux papiers" for an insurgent posing as a civilian |
| **Comportement au contrôle** (Behaviour when checked) | Selon le contexte (Depends on context) · Coopère (Cooperates) · Nerveux (Nervous) · Ment avec aplomb (Lies confidently) · Refuse (Refuses) · S'enfuit (Flees) · Devient agressif (Turns aggressive) · Se rend (Surrenders) | "Ment avec aplomb" for a trained agent; "S'enfuit" for a lookout |
| **Camp** (Side) | Ne pas changer (Do not change) · Civils (Civilians) · BLUFOR · OPFOR · Indépendants (Independents) | A change of side creates a new group |

**CE QUE VERRA LE JOUEUR** (WHAT THE PLAYER WILL SEE): preview of the name, the papers and the "Au contrôle" (When checked) reaction. Reminder: « Il répond au-dessus de sa tête. Un contrôle N3 le reconnaît sur Athena si ses papiers viennent du profil. » (They answer above their head. An N3 check recognises them on Athena if their papers come from the profile.)

Button **GARDER POUR LES PROCHAINES PARTIES : NON/OUI** (KEEP FOR NEXT GAMES: NO/YES), then **APPLIQUER** (APPLY) (the chosen AI), **À SON GROUPE** (TO THEIR GROUP), **À TOUTE LA LISTE** (TO THE WHOLE LIST).

## Step by step: a market with a hidden insurgent

Situation: ten civilians at a market, including an insurgent lookout with forged papers; the team has to find him through checks.

1. Zeus › **Configurer une IA** on a market civilian; DÉTECTER 50 m › **AUTOUR**: "10 IA".
2. Without selecting anything in particular: Papiers **Carte d'identité**, Nature **Vrais papiers**, Comportement **Selon le contexte** › **À TOUTE LA LISTE**.
3. Click the lookout in the list: Nature **Faux papiers**, Comportement **Nerveux**, Camp **Ne pas changer** › **APPLIQUER**.
4. In **SES PAPIERS**, **OUVRIR** his card to see the printed name; **MARQUER FAUX** if the forgery needs redoing.
5. To replay the scene next week: **GARDER POUR LES PROCHAINES PARTIES : OUI** before APPLIQUER (preferably AI named in Eden).

When checked, the lookout hesitates and looks around; the visual examination (N0) or the MRZ (N1) reveals the forgery depending on its quality.

## Common pitfalls

- **SÉLECTION ZEUS**: you must close the window, select the AI in Zeus, then reopen it.
- An AI's papers are **only created on first request** (search, request, death): the "papers" column shows « à la demande » as long as nobody has checked them.
- **APPLIQUER erases the AI's old papers** and sets the new ones.
- **Keeping** requires the Athena link and the Confidential clearance; otherwise « Enregistrement sur Athena impossible … rien n'est gardé. » (Saving to Athena impossible … nothing is kept.). AI placed in Zeus without a variable name are not found again in the next game (found by Eden variable name, otherwise by the same class within 20 m): « Éléments gardés non retrouvés : … » (Kept items not found: …).
- « Aucun profil Athena reçu sur ce serveur : créez-en dans le Bureau SSE (Profils IA), ou générez. » (No Athena profile received on this server: create some in the SSE Office (AI Profiles), or generate.)
- Everything goes through the server (`fn_idAiConfig`): a client alone cannot change anything.
- There is **no slider** on this page: the percentages (AI without papers, civilians who present, insurgents carrying forgeries) are CBA settings.

## Athena

- **AI profiles** (SSE Office › AI Profiles) are chosen in "Profil Athena"; an AI linked to a profile is recognised at an N3 check with its Athena record.
- **Keep for next games** saves the configuration (community + map) on Athena; the server reapplies it at mission start.
- Identities prepared on Athena for AI (side, category, area) are handed out at their first check (see [controle_identite.en.md](controle_identite.en.md)).
