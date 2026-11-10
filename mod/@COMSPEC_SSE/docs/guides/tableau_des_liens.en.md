# Link chart

French version: [tableau_des_liens.md](tableau_des_liens.md). Guides index: [index.en.md](index.en.md).

## What it is for

The **Tableau des liens** (Link chart) shows what the team knows about people, organisations, exhibits, devices, documents and places, and how all of it connects. It fills up as the mission unfolds: checking or enrolling a person adds them to it, a biometric identification confirms their membership, exploiting a phone or a notebook raises suspicion about members of their cell, exploiting an item attached to an organisation reveals it. Zeus has a **Vue Zeus** (Zeus view) that shows everything, with what the players do not know in grey, and can **reveal** a node.

Do not confuse it with the **intelligence graph** of the legacy terminal (GRAPHE screen, « Nœuds et relations établies sur le terrain » (Nodes and relations established in the field), PIVOT button): that is the view of a single linked record; the link chart is the complete view.

## Where to find it

| Context | Path |
|---|---|
| Player | ATAK phone › Renseignement (Intelligence) › **SSE terrain** (Field SSE) app › **SITE** tab › **Tableau des liens** (Link chart) button (the phone is put away, the chart opens) |
| Zeus | ZEN › **COMSPEC · Renseignement (SSE)** (COMSPEC · Intelligence (SSE)) › **Ouvrir le tableau des liens** (Open the link chart) (Zeus view) |
| Script | `[false] call comspec_sse_fnc_chartOpen` (player) · `[true]` (Zeus view) |

There is no longer an ACE self-interaction menu « COMSPEC SSE » nor a keybind for the chart.

## The window

Title **TABLEAU DES LIENS** (LINK CHART) (in Zeus view: **TABLEAU DES LIENS — VUE ZEUS** (LINK CHART — ZEUS VIEW) on an orange background), branding « COMSPEC SSE · ANALYSE » (COMSPEC SSE · ANALYSIS), side panel **DÉTAIL** (DETAIL).

| Button | Tooltip / effect |
|---|---|
| **GRAPHE** (GRAPH) | « Personnes, organisations, objets et données reliés » (People, organisations, items and linked data): each organisation at the centre of its cells and members, each item around its holder, the places mentioned |
| **ORBAT** | « Organigramme : organisations, cellules, membres par rôle » (Org chart: organisations, cells, members by role): organisation at the top, cells below, members from leader to sympathiser |
| **TOUT · PERSONNES · ORGANISATIONS · OBJETS ET DONNÉES** (ALL · PEOPLE · ORGANISATIONS · ITEMS AND DATA) | Filters (« Personnes et organisations » (People and organisations), « Pièces, appareils, documents, lieux et qui y touche » (Exhibits, devices, documents, places and who handles them)) |
| **CONFIRMÉ SEUL** (CONFIRMED ONLY) | « Masque ce qui n'est que soupçonné » (Hides what is only suspected) |
| **RECADRER** (REFRAME) | Recentres the canvas |
| **RÉVÉLER** (REVEAL) (Zeus view) | « Zeus : rend le nœud choisi connu des joueurs (confirmé) » (Zeus: makes the selected node known to the players (confirmed)); recorded reason: « renseignement transmis par le PC » (intelligence passed on by the CP). Only people and organisations can be revealed |
| **FERMER** (CLOSE) | Closes |

Canvas: **click** = node sheet in DÉTAIL, **double-click** = zoom, **mouse wheel** = scale. Legend: organisation (colour = threat: low green, medium yellow, high orange, critical red) · person (bluish white) · document · device (light blue) · place (purple); **solid = confirmed, « ? » = suspected**, in Zeus **grey = unknown to the players**.

**DÉTAIL** sheet: Alias, Appartenance (Membership), Capture (bag over the head, alive / deceased), Effets saisis (Seized effects), Membres identifiés (Identified members), Menace (Threat), Nature, Organisation, Position, Rattachée à (Attached to), Repérée par (Spotted by), Rôle (Role), Source (and time of discovery), LIENS (LINKS) (owner, associate, contact, place, member, family, finance, supplies, uses, leads, communications, owns). Empty: « Choisissez un nœud sur le tableau. Le tableau se remplit au fil de la mission… » (Pick a node on the chart. The chart fills up as the mission unfolds…)

The chart updates by itself (`comspec_sse_anaChanged` event, batched over 0.5 s).

## Step by step: rebuilding a cell

Situation: after the assault, the team has a prisoner, a body, a phone and a notebook.

1. SEEK II on the prisoner › IDENTIFIER (IDENTIFY): **MATCH**; he enters the chart with his membership **confirmed** (solid).
2. ACE on the phone › SSE › **Fouiller** (Search), then SSE terrain app › CIBLE (TARGET) › **Approfondir** (Dig deeper): two « contact de… » (contact of…) appear as **suspected** (« ? »).
3. Open **SSE terrain › SITE › Tableau des liens**: **ORBAT** shows the organisation, its cell, the prisoner (role) and the two contacts.
4. Click on a contact: DÉTAIL › Source: « exploité, téléphone de … » (exploited, phone of …). He still has to be checked or enrolled to confirm.
5. At the debrief, Zeus opens the **Zeus view**: what the players missed appears in grey; **RÉVÉLER** on the leader simulates intelligence from the CP.

## Common pitfalls

- **Empty at the start is normal**: check, identify, exploit.
- A **suspected** node is not evidence: **CONFIRMÉ SEUL** to keep only what is established.
- **Overwatch Connect** loaded: the terminal screens open in the Overwatch host, not the chart (which stays the same).
- The terminal **graph** (GRAPHE / PIVOT: « Aucun record pour pivot. » (No record to pivot on.), « Pivot : n entité(s) liée(s) » (Pivot: n linked entity(ies))) shows only the linked record.
- Attached items are cached for 60 s: a very recent Zeus attachment may require closing and reopening.
- Nothing is sent to Athena from the chart: sending is done **per sheet** (TRANSMETTRE (TRANSMIT) / Transmettre à Athena (Transmit to Athena)); Zeus can **EXPORTER LE GRAPHE** (EXPORT THE GRAPH) (Contrôle Zeus SSE (SSE Zeus Control) screen) and open the **AAR** report.
- Settings (COMSPEC SSE › Organisations et scellés (Organisations and seals)): Contacts révélés par exploitation (Contacts revealed by exploitation) (2), La biométrie prouve l'appartenance (Biometrics prove membership) (yes), Rôles en plus (Extra roles), Types d'organisation en plus (Extra organisation types), Noms de code des cellules (Cell code names).

## Athena

- Transmitted sheets (person, biometrics, exhibits) fill the case of the SSE Desk; the ORBAT of organisations with what the players know can be read in **Bureau SSE › Campagnes** (SSE Desk › Campaigns) when the campaign is copied into Athena.
- The Zeus module **Ouvrir le compte rendu (AAR)** (Open the after-action report (AAR)) with « Exporter le graphe » (Export the graph) produces the mission graph (`comspec_sse_mission_graph`) attached to the report.
