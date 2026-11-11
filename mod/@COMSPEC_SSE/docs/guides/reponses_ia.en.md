# Talking to AIs: understood questions, game master answers, what an AI may bring up

French version: [reponses_ia.md](reponses_ia.md). Guides index: [index.en.md](index.en.md).

## What it is for

Players talk to AIs in free text (ACE interaction › **Talk to…**, or Ctrl+T). The AI understands about **120 kinds of questions**, in French and English, typos and slang included, and answers from its character, cover and knowledge. The game master can, question by question, keep this **automatic answer** or write **their own** ("My answer"), and set **what the AI may bring up**.

Golden rule: **an AI only talks about what it knows**. A passer-by with no knowledge never talks about weapons, armed groups, pickups, mines or caches: "No, I haven't seen anything like that.", "I don't know, I just got here."

## To make an AI talk: write a question, write its answer

1. Eden: unit attributes › **COMSPEC SSE · AI roleplay** › **Question**: what a player may ask it, in their own words. Zeus: right-click › **SSE · AI roleplay › AI profile › Question and answer…** (the same two boxes are also at the top of the **AI workshop**).
2. **AI's answer**: what it answers (one line per possible answer; **+ Variable** or `{first}`, `{city}`, `{player}`… for words replaced in game).
3. That's it: close wordings work too. Example: Question "Where is the village chief?", Answer "The chief is at the mosque, he'll be back tonight."; in game, "The village chief, where is he?" gets this answer.

These two boxes are the first of "your own questions": more questions, an answer per situation or revealed intel are set in **All questions and answers…** (below). **Advanced options** (in the workshop) keeps **What it knows** (a secret, useful intel; formerly "Knowledge") and **When it tells** (once trusted, bribe, with the keyword, never; formerly "Unlock"): missions already made keep their values. "If ordered" is now **If given an order** (what it does at "Hands up!", "Follow me").

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
| Eden, one question | Unit attributes › **COMSPEC SSE · AI roleplay › Question** and **AI's answer**: saved in the mission |
| Eden, all | Unit attributes › **COMSPEC SSE · AI roleplay › AI RP profile › AI workshop… › All questions and answers…**, then **Confirm** and **Apply** in the workshop: saved in the mission |
| Zeus, ZEN | Right-click one or more AIs › **SSE · AI roleplay › AI profile › Question and answer…** (one question) or **All questions and answers…** (or **What she may bring up…** for the frame alone) |
| Zeus, Area AI | Check the AIs › **Answers…** (see [ia_de_la_zone.en.md](ia_de_la_zone.en.md)) |
| Zeus, AI workshop | **AI workshop** module or menu › **Question**, **AI's answer**, or **All questions and answers…** |

## The "AI answers" window

It opens in **simple mode**: only what you need to write an answer. Everything else is under **Advanced options**, bottom right, folded by default.

### Simple mode

1. **At the top**, one line: **What this AI may bring up**: **Nothing sensitive** (never weapons, mines or hints), **What she knows** (only her knowledge, her secrets and your answers) or **Free** (anything, except forbidden themes). Until you pick one, the window shows the level that follows from her profile ("Nothing sensitive" for a civilian with no knowledge or secret, otherwise "What she knows") and the profile keeps it automatic.
2. **On the left**, the questions by theme, the same as the players' GUIDE tab, with **Search** ("name", "armed", "password"). Right of a question: **My answer** if you wrote one, **Forbidden** if its theme is forbidden.
3. **On the right**, the chosen question (and other ways to ask it), then **Automatic answer** or **My answer**.
4. With **My answer**: write **what she says, one answer per line** (several lines: she says one). **Insert a variable** opens the list of words replaced in game (her first name, her town, the player's name…) with a sample value; a click inserts it at the cursor.
5. **The AI will say** shows the answer with variables replaced; an unknown variable, a custom question with no trigger word or a forbidden theme is flagged there.
6. **Save** (Zeus: at once; Eden: into each unit's attribute; workshop: taken by its Apply button). **Whole group** (Zeus, Zone AIs) also saves on every AI of her group. The status line next to it says whether it is saved.

### Advanced options

The button tells how many advanced settings are in use ("Advanced options (2 settings in use)"). Open, it shows one help sentence for the setting under the mouse.

| Setting | Role | Example |
|---|---|---|
| **Forbidden themes** | Weapons (and armed groups), Explosives (and mines), Military sites, Wanted, Drugs, Politics: on these themes she says she knows nothing, **even with your answer** | Explosives |
| **Situation** | Always, If calm, If afraid, If threatened (or weapon aimed), If accused of lying, The first time, If asked again, If he agrees, If he refuses; the most specific one with an answer wins | "If afraid": "I… I'm {first}! Don't shoot!" |
| **Order** | At random (never the same twice in a row) or In order (per player, the last one repeats) | a witness who tells more each time |
| **Then reveals** | Nothing more, A vague hint, Precise intel or All she knows, for this player | Precise intel |
| **Map marker** | Like a "Lieu:" line of the knowledge: grid, coordinates or marker, then the name; put once on this player's map | 045 078 Abandoned farm |
| **Add my own question** | A question the game does not know: **Question name** (for you), **Words that trigger it** separated by ";" ("a+b": both words in the message), then the answer; **Delete** removes it | "password; mot de passe" → "The falcon flies low." |

Folding the advanced options does not erase them: they stay saved, and the button counts them.

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
2. **All questions and answers…** › "Who are you?" › **My answer**: "I'm {first}, the baker. Everyone knows me here." (the first name through **Insert a variable**).
3. **Advanced options** › Situation **If afraid**: "I… I'm {first}! I bake bread, that's all!"
4. "Have you seen armed men?" › **My answer**, Situation **If he agrees**: "Last night, three pickups heading north."; Then reveals **Precise intel**; Map marker "045 078 Abandoned farm"; Situation **If he refuses**: "I don't want trouble."
5. **Add my own question**: name "Password", words that trigger it "password; mot de passe", answer "The falcon flies low."
6. **Save**, then **Apply** in the workshop. In game: "Who are you?" → "I'm Nikos, the baker. Everyone knows me here."

The other market civilians, with no knowledge, stay on **Automatic answer**: to "Any insurgents here?", "No. I'm just going to the market."

## AI emotions (fear of weapons included)

Six emotions, each one a box to tick: **fear** (trembles, drops to the ground, surrenders or runs away), **fear of weapons** (raised weapon aimed at her, soldiers with raised weapons, nearby shots, "/me aims his rifle"), **nervousness** (hesitates, gets nervous, refuses her papers), **anger** (aggressive tone, denies and gets angry, fights back), **distrust** (wary tone) and **relief** (relaxes when reassured). Global setting: Addon options › COMSPEC SSE › AI roleplay (can be saved in the mission). Per AI: Eden attributes **AI emotions** (preset, one box per emotion, **Whole group**); Zeus: SSE · AI roleplay menu › AI profile › **Emotions…** (Mission setting, Custom, Tick all, Stoic, Very emotional, Untick all). The AI's own setting wins over the global one. An unticked emotion no longer rises, no longer changes the tone and no longer triggers anything; with everything unticked the AI stays neutral but still talks normally. Example: untick **Fear of weapons** for a police officer or a veteran: he sees your rifle ("He stares at your gun barrel.") but stays calm; a spoken threat, forged papers found or violence seen still get to him.

## Common pitfalls

- Your answer **only replaces the line**: a refused order stays refused, a liar can still be caught, papers still go through the ACE check. For an order, a search or a bribe, "Always" applies when he obeys; if he refuses only "If he refuses" is said.
- Your own question replaces the conversation of the message; an order or a threat in the same message is still handled ("Stop! The password.").
- A forbidden theme beats your texts.
- Answers stay on the server (never sent to players), are saved in the mission (Eden) and go along with the AI through **Keep for next games**.
- `<` `>` `&` are shown as they are; a line break separates two answers.

## Athena

The site's AI profiles do not carry these answers (no field for them): they live in the mission or in the kept AI (configuration copied to Athena by "Keep").
