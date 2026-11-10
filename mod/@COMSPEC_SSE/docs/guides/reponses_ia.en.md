# Talking to AIs: understood questions, game master answers, what an AI may bring up

French version: [reponses_ia.md](reponses_ia.md). Guides index: [index.en.md](index.en.md).

## What it is for

Players talk to AIs in free text (ACE interaction › **Talk to…**, or Ctrl+T). The AI understands about **120 kinds of questions**, in French and English, typos and slang included, and answers from its character, cover and knowledge. The game master can, question by question, keep this **automatic answer** or write a **custom answer**, and set **what the AI may bring up**.

Golden rule: **an AI only talks about what it knows**. A passer-by with no knowledge never talks about weapons, armed groups, pickups, mines or caches: "No, I haven't seen anything like that.", "I don't know, I just got here."

## What players can ask

The **GUIDE** tab of the "Talk to" window lists everything, by theme, with clickable examples:

| Theme | Examples |
|---|---|
| Greeting, repeating | Hello · Say again? · Do you understand? |
| Identity and papers | Who are you? · How old are you? · Your phone number? · Do you speak English? · Papers, please |
| Cover | Where are you from? · Who do you work for? · Where were you born? |
| Presence | How long have you been here? · Are you alone? · Where were you last night? · What are you carrying? |
| Search, orders | I'm going to search you · Open your bag · Drop your weapon! · Step back · Sit down · Be quiet |
| Intelligence | Have you seen armed men? · Vehicles? · Strangers? · Mines on the road? · Who's in charge here? · How many were there? · Which way did they go? |
| Local situation | Is it safe here? · Drinking water? · A doctor? · What do you need? · Where's the market? · Where are we? |
| Relations | Do you know this man? · Your neighbours? · The village elder? · What do you think of the army? |
| Personal | Are you scared? · Are you hurt? · Are you hungry? · Want some water? |
| Roleplay rules | Do you want a lawyer? · An interpreter? · Will you cooperate? · Hold on · I promise |
| Off topic | insults, jokes, "Are you a robot?", the time, politics: it stays in character |

An AI remembers: asked "Do you have a phone?" twice, it says the same thing; "Say again" repeats its last line. With a fact in its story ("Phone: 06 12 34", "Since: this morning", "With: my brother", "Last night: at the café", "Cargo: oranges"), it gives it.

## Where to set answers

| Context | Path |
|---|---|
| Eden | Unit attributes › **COMSPEC SSE · AI roleplay › AI RP profile › AI workshop… › Answers and limits…**, then **Confirm** and **Apply** in the workshop: saved in the mission |
| Zeus, ZEN | Right-click one or more AIs › **SSE · AI roleplay › AI profile › AI answers…** (or **What she may bring up…** for the frame alone) |
| Zeus, Area AI | Check the AIs › **Answers…** (see [ia_de_la_zone.en.md](ia_de_la_zone.en.md)) |
| Zeus, AI workshop | **AI workshop** module or menu › **Answers and limits…** |

## The "AI answers" window

### Top: the frame

- **What she may bring up**: **Auto** ("Nothing sensitive" for a civilian with no knowledge or secret, otherwise "What she knows"), **Nothing sensitive** (neutral answers, never a hint), **What she knows** (her knowledge, secrets and your texts; automatic answers mention no scenario theme), **Free** (automatic answers may bring up themes, except forbidden ones).
- **Forbidden themes**: Weapons (and armed groups), Explosives (and mines), Military sites (bases, checkpoints), Wanted (where is he, photo, description), Drugs, Politics (and police, army). On a question of a ticked theme: neutral answer ("I don't know anything about that."), **even with a custom answer**.

### Left: the questions

The same as the players' GUIDE tab, by theme, with **Search** ("name", "armed", "password"). Right of each: **Auto**, **Custom (2)** (number of texts) or **Forbidden**. On top: your **custom questions**, and **Add a custom question**.

### Right: the answer

| Setting | Role | Example |
|---|---|---|
| **Answer** | Automatic or Custom | Custom for "Who are you?" |
| **Order** | At random (never the same twice in a row) or In order (per player, the last text repeats) | a witness who tells more each time |
| **Question**, **Triggers** | Custom questions only: its name (for you) and the words that trigger it, separated by ";"; "a+b": both words in the message | "password; mot de passe" |
| **Situation** | Always, If calm, If afraid, If threatened (or weapon aimed), If accused of lying, The first time, If asked again, If he agrees, If he refuses; the most specific one with a text wins | "If afraid": "I… I'm Nikos! Don't shoot!" |
| **Answers** | One per line, 300 characters at most, 8 per situation | see variables |
| **Unlocks** | Nothing, vague, precise or complete level of its knowledge, for this player | precise |
| **Place** | Like a "Lieu:" line of the knowledge: grid, coordinates or marker, then the name; put once on this player's map | 045 078 Abandoned farm |

**Preview**: the answer with variables replaced by sample values; an unknown variable is flagged. **Back to automatic** clears the custom answer (or deletes the custom question). **Whole group** (Zeus) also applies to each AI's group. **Apply** (Zeus: at once; Eden: into each unit's attribute) or **Confirm** (workshop).

### Variables

| French | English | Replaced by |
|---|---|---|
| {nom} | {name} | the full name (papers, or workshop name) |
| {prenom} | {first} | the first name |
| {ville} | {city} | its town (cover) |
| {metier} · {employeur} | {job} · {employer} | its job, its employer |
| {age} · {famille} · {adresse} · {motif} · {contact} | {age} · {family} · {address} · {travel} · {contact} | the facts of its cover |
| {telephone} · {nationalite} | {phone} · {nation} | "Phone:", "Nationality:" lines of its story |
| {joueur} | {player} | the name of the player talking |
| {heure} | {time} | the game time (18:05) |
| {lieu} | {here} | the nearest village or town on the map |
| {monsieur} | {sir} | "monsieur" / "sir" |

Missing fact: "…" (the preview flags it).

## Step by step: the baker and the lookout

1. Eden, the baker's attributes › **AI workshop…**: Name "Nikos KARRAS", story "Job: baker".
2. **Answers and limits…** › "Who are you?" › **Custom**: "I'm {first}, the baker. Everyone knows me here."; Situation **If afraid**: "I… I'm {first}! I bake bread, that's all!"
3. "Have you seen armed men?" › Custom, Situation **If he agrees**: "Last night, three pickups heading north."; Unlocks **Precise level**; Place "045 078 Abandoned farm"; Situation **If he refuses**: "I don't want trouble."
4. **Add a custom question**: Question "Password", Triggers "password; mot de passe", Answer "The falcon flies low."
5. **Confirm**, then **Apply** in the workshop. In game: "Who are you?" → "I'm Nikos, the baker. Everyone knows me here."

The other market civilians, with no knowledge, stay on **Auto**: to "Any insurgents here?", "No. I'm just going to the market."

## Common pitfalls

- A custom answer **only replaces the line**: a refused order stays refused, a liar can still be caught, papers still go through the ACE check. For an order, a search or a bribe, "Always" applies when he obeys; if he refuses only "If he refuses" is said.
- A custom question replaces the conversation of the message; an order or a threat in the same message is still handled ("Stop! The password.").
- A forbidden theme beats your texts.
- Answers stay on the server (never sent to players), are saved in the mission (Eden) and go along with the AI through **Keep for next games**.
- `<` `>` `&` are shown as they are; a line break separates two answers.

## Athena

The site's AI profiles do not carry these answers (no field for them): they live in the mission or in the kept AI (configuration copied to Athena by "Keep").
