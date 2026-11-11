# Area AI

French version: [ia_de_la_zone.md](ia_de_la_zone.md). Guides index: [index.en.md](index.en.md).

## What it is for

The SSE editor's **Area AI** window sets, for nearby or selected AIs, **who they are at an identity check**: Athena profile, papers they carry and their nature (genuine, cover, forged), behaviour when checked, side. It also lets you see its papers as the player will, fix them, write **what it answers** when spoken to (**Questions and answers…**) and keep it in the **map memory** for the next games.

Without it, AIs get papers "by side" (Identity CBA settings) and answer on their own, from their character and cover.

## Where to find it

| Context | Path |
|---|---|
| Zeus (ATAK Zeus centre) | **Area AI** on an AI or a selection |
| Zeus with ZEN | Right-click one or more AIs › **SSE · AI roleplay › Identity and papers › SSE editor sheet (AIs of the area)…** |
| Zeus without ZEN | **SSE Editor** module placed on an AI (its whole group is listed) |
| Script | `[unit or list, position] call comspec_sse_fnc_aiConfigOpen` |

In Eden, set AIs through their attributes (**COMSPEC SSE › Papers**, **Profile at a check**, **AI RP profile** and its **AI workshop… › Answers and limits…** button): everything is saved in the mission.

## The window, in three steps

The window reads from left to right: **1. choose the AIs**, **2. set them up**, **3. apply or keep**. On first opening, a three-bubble guided tour points at each area (**?** button to replay it; **Skip** closes it for this time, **Never again** for good). Nothing changes in game before **Apply**: the status line at the bottom says so ("Changed, not applied yet") and tells what happened ("3 AI updated."). No Arma alert.

### 1. Choose the AIs (left)

- **Detect around** and its **radius** (25 to 500 m): the AIs around the module, the Zeus camera or you. **Zeus selection**: the AIs selected before opening. 60 AIs at most.
- **Search a name…** (given name, true name, unit, group, profile; e.g. "Vlachos", "Alpha") and **Filters** (side, role, state: Athena profile, random name, papers to come, in map memory; **Show all** clears them).
- The list: check box, **given name** (what its papers say), role, **papers** icon and count, **Athena profile** icon. Papers icon colour: green genuine, red forged, orange it lies or cover, grey not given yet (legend under the list). Hover a name: its **true name** (you only). Clicking a header sorts the list.
- A click checks or unchecks an AI. **Check all**, **Uncheck**, "N AI checked". Empty list: a panel explains what to do, with both buttons.

### 2. Set them up (middle, one tab per topic)

At the top: the AI's name, then for you only its true name, its truth ("False identity (lies)") and its group. Several AIs checked: "Settings apply to the N checked AI".

| Tab | Settings | Example |
|---|---|---|
| **Identity** | **Where its identity comes from** (random, or an Athena profile), **Authenticity of its papers** (genuine, cover, forged; formerly "Nature"), **Country of its papers** (as the mission, or a country). **Import from Athena** panel: profile count and pack date, search, **Refresh** | "Athena profile: PRF-0012 · Vendor": the AI takes that name, legend and papers; an N3 check recognises it |
| **Papers** | **Papers it will carry** (by side, none, card, passport, licence, military ID, extension types); **Papers it has on it** (paper and country, number, **Real / fake** badge; formerly "Truth"); **View full screen**, **Make forged** / **Make genuine**, a type and **Give now** | a stolen card: **Make forged** |
| **Behaviour** | **At a check, it…** (by context, cooperates, nervous, lies with confidence, refuses, runs away, turns aggressive, surrenders); **Questions and answers…** ([AI answers](reponses_ia.en.md) window) | "Nervous": hesitates, answers off the point |
| **Side** | **Its side after "Apply"** (do not change, civilians, BLUFOR, OPFOR, independents) | a civilian turned OPFOR becomes hostile |

Each choice is explained under its box, in one sentence.

### What the player sees (right)

The paper chosen in the Papers tab, **drawn as in the player's viewer** (template, fields, MRZ, hologram; **Front / back**), with its caption ("ID card · Republic of Altis · front"). Without a paper: a sentence says why ("No paper yet…"). Below, **At a check**: the name it gives, its papers, its reaction; for you only, the truth; and what will change after **Apply**.

### 3. Apply or keep (bottom)

- **Apply** (checked AIs; a "●" dot when settings are waiting) and **Apply to the whole group** (the whole group of each checked AI). The selection stays afterwards.
- **Map memory…** (formerly "Kept for next games") opens a panel: **Keep the checked AI** (applies, then saves on this server for this map, and on Athena when the link is open: put back at the start of each game), the **Already in memory** list, **Reload**, **Delete**.

## Step by step: a market with a lookout

Ten civilians on a market, one of them a lookout with forged papers, and a vendor who knows the network's password.

1. Zeus › **Area AI** on a market civilian; radius 50 m › **Detect around**: "10 AI".
2. **Check all**; Papers tab **ID card**, Identity tab **Genuine papers**, Behaviour tab **By context** › **Apply**.
3. **Uncheck**, check the lookout: Identity tab **Forged papers**, Behaviour tab **Nervous** › **Apply** (on the right, its paper and "At a check" show the result).
4. Check the vendor alone › Behaviour tab › **Questions and answers…**: for "Who are you?", **Custom** "I'm {first}, I've sold fruit here for twenty years."; **Add a custom question**: triggers "password", answer "The falcon flies low." › **Apply**.
5. To replay the scene: check both › **Map memory…** › **Keep the checked AI** (AIs named in Eden preferably). The answers written in step 4 are kept with the vendor.

## Common pitfalls

- **Zeus selection** reads the selection made before opening the window.
- An AI's papers are **only made on first request**: "on demand" until someone checks it.
- **Apply remakes the papers** of the checked AIs.
- **Questions and answers…**, **Make genuine / forged** and **Give now** act at once, without Apply; **Keep** then takes the answers along with the AI.
- **Map memory** saves on this server, and on Athena when the link and the Confidentiel clearance allow. An AI without a variable name is found again by its class within 20 m, otherwise: "AI in memory not found".
- Everything goes through the server (Zeus or administrator only).

## Athena

- **AI profiles** (SSE Office › AI profiles) are picked in "Athena profile"; an AI linked to a profile is recognised at an N3 check.
- **Keep** copies the configuration to Athena (community + map), game master answers included; the server puts it back at start.
- Written answers are not part of the site's AI profiles: they live in the mission (Eden) or in the kept AI.
