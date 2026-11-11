# Area AI

French version: [ia_de_la_zone.md](ia_de_la_zone.md). Guides index: [index.en.md](index.en.md).

## What it is for

The SSE editor's **Area AI** window sets, for nearby or selected AIs, **who they are at an identity check**: Athena profile, papers they carry and their nature (genuine, cover, forged), behaviour when checked, side. It also lets you view and fix an AI's papers, write **what it answers** when spoken to (**Answers…** button) and **keep** an AI for the next games on the same map.

Without it, AIs get papers "by side" (Identity CBA settings) and answer on their own, from their character and cover.

## Where to find it

| Context | Path |
|---|---|
| Zeus (ATAK Zeus centre) | **Area AI** on an AI or a selection |
| Zeus with ZEN | Right-click one or more AIs › **SSE · AI roleplay › Identity and papers › SSE editor sheet (AIs of the area)…** |
| Zeus without ZEN | **SSE Editor** module placed on an AI (its whole group is listed) |
| Script | `[unit or list, position] call comspec_sse_fnc_aiConfigOpen` |

In Eden, set AIs through their attributes (**COMSPEC SSE › Papers**, **Profile at a check**, **AI RP profile** and its **AI workshop… › All questions and answers…** button; one question only: **Question** and **AI's answer** in the same attributes): everything is saved in the mission.

## The window

### Top left: finding the AIs

- **Radius** (25, 50, 100, 250, 500 m), **Detect around** (around the module, the Zeus camera or you) and **Zeus selection** (the AIs selected before opening). "N AI" counter, 60 at most.
- **Search** (given name, true name, unit, group, profile; e.g. "Vlachos", "Alpha") and three filters: side, role, state (Athena profile, generated identity, papers on first request, kept).

### The list

One row per AI: check box, **Given name** (what its papers say), **True name** (only you see it), **Truth** (true identity, false identity, forged papers, cover, decided on first request), side, role, profile, number of papers, group.

A click checks or unchecks an AI and shows its sheet. **Check all**, **Uncheck**, "N AI checked". Settings and buttons apply to **the checked AIs**.

**Answers…** (right of the counter) opens the **AI answers** window on the checked AIs: see [reponses_ia.en.md](reponses_ia.en.md).

### Kept for next games

The AIs saved for this map (server, Athena or both), with **Reload the saved AI** and **Delete the record**.

### The sheet

| Setting | Values | Example |
|---|---|---|
| **Athena profile** | **Generate an identity**, then the SSE Office profiles (search, **Refresh**) | "PRF-0012 · Vendor (Yannis Petrakis)": the AI takes that name, legend and papers; an N3 check recognises it on Athena |
| **Papers carried** | By side, None, ID card, Passport, Driving licence, Card and licence, Card and passport, Military ID, extension types | "Card and licence" for a driver |
| **Nature** | Genuine papers, Cover, Forged papers | "Forged papers" for an insurgent posing as a civilian |
| **Behaviour at a check** | By context, Complies, Nervous, Lies confidently, Refuses, Flees, Turns aggressive, Surrenders | "Nervous": hesitates, answers off the point |
| **Side** | Do not change, Civilians, BLUFOR, OPFOR, Independent | a civilian moved to OPFOR turns hostile |

Each setting is explained under its box.

### Its papers

Type, country, number, nature. **View the paper** (as a player will see it), **Make it genuine**, **Make it forged**, and a type to **Give this paper now**.

### What the player will see

At a check: the name it gives, its papers, its reaction. For you only: its true name and truth, and what will change after **Apply**.

### Bottom

**Keep for next games** (applies, then saves), **Apply to the group** (the whole group of each checked AI), **Apply** (checked AIs; their papers are remade).

## Step by step: a market with a lookout

Ten civilians on a market, one of them a lookout with forged papers, and a vendor who knows the network's password.

1. Zeus › **Area AI** on a market civilian; radius 50 m › **Detect around**: "10 AI".
2. **Check all**; Papers **ID card**, Nature **Genuine papers**, Behaviour **By context** › **Apply**.
3. **Uncheck**, check the lookout: Nature **Forged papers**, Behaviour **Nervous** › **Apply**.
4. Check the vendor alone › **Answers…**: for "Who are you?", **Custom** "I'm {first}, I've sold fruit here for twenty years."; **Add a custom question**: triggers "password", answer "The falcon flies low." › **Apply**.
5. To replay the scene: check both and **Keep for next games** (AIs named in Eden preferably). The answers written in step 4 are kept with the vendor.

## Common pitfalls

- **Zeus selection** reads the selection made before opening the window.
- An AI's papers are **only made on first request**: "on demand" until someone checks it.
- **Apply remakes the papers** of the checked AIs.
- **Answers…** applies at once, without Apply; **Keep** then takes these answers along with the AI.
- **Keep** saves on this server, and on Athena when the link and the Confidentiel clearance allow. An AI without a variable name is found again by its class within 20 m, otherwise: "Saved AI not found".
- Everything goes through the server (Zeus or administrator only).

## Athena

- **AI profiles** (SSE Office › AI profiles) are picked in "Athena profile"; an AI linked to a profile is recognised at an N3 check.
- **Keep** copies the configuration to Athena (community + map), game master answers included; the server puts it back at start.
- Written answers are not part of the site's AI profiles: they live in the mission (Eden) or in the kept AI.
