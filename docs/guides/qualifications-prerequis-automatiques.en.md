# Automatic prerequisites

Complete list of the conditions ATHENA **measures on its own** to award (or propose) a qualification: where the data comes from, how it is calculated and what it cannot do. All are set in the qualification’s award rule (Reference › Qualifications › the qualification › Edit, section “How to obtain it”), in **Automatic** mode. The site checks rules **every hour**.

Reminder: a rule has up to 8 blocks of 12 conditions; each block is “all” or “at least one”, and so are the blocks between them. Dated thresholds accept a **rolling window**: all time, or the last 30, 90, 180 or 365 days.

## 1. Data measured in game and on the site

| Condition | Exact source | How it is calculated | Possible scope | Window | Limits |
|---|---|---|---|---|---|
| Play hours | ATAK mod (play time recorded since installation) | Total seconds reported by the mod for the member, in hours | All play, on a server, in Zeus | No | Cumulative since the mod was installed; counts all of Arma, not only community sessions. |
| Approved session hours | Site: game sessions (attendance approved by managers) | Sum of approved time of the sessions where the member’s attendance is valid (HR exclusions left out) | All sessions or one hour category (e.g. “Instruction”) | Yes | Depends on managers entering and approving sessions. |
| Approved game sessions | Site: game sessions | Number of sessions with approved attendance | — | Yes | Same. |
| Flight hours | Mod (time at the controls, aircraft by aircraft) + logbook | Pilot seconds recorded by the Arma server, plus logbook flights entered by the pilot and countersigned | Total, helicopters, aeroplanes, a role group (transport, attack, reconnaissance, jet, cargo, drone…), a family (e.g. AH-64, UH-60, CH-47) or a single aircraft (Arma class) | No | Only the pilot seat counts; an aircraft unknown to the site is matched to a family by its class name or by hand on the Pilot syllabus page. |
| Number of flights | Logbook | One flight per mission and per aircraft recorded by the mod, plus flights entered and countersigned | As for flight hours | No | A several-hour mission on the same aircraft counts as one flight. |
| Night, instrument or formation flight hours | Logbook, countersigned by an instructor | Sum of the hours noted under the chosen condition | Night, IFR or formation, helicopter or aeroplane | No | The mod does not detect these conditions: the pilot notes them, the instructor countersigns. |
| Missions played | Mod (mission records sent by the server) | Number of record rows: one per mission (mission name and map) and per day | — | Yes | Replaying the same mission the same day counts once; a mission running past midnight counts twice; a mission without a linked server is only recorded if the player’s phone sends it. |
| Event attendance | Site: calendar (check-in on arrival) | Number of non-cancelled events where the member was checked in | All, operations, training, events, other | Yes | A sign-up without check-in does not count. |
| Parachute jumps | Mod (mission records) | A jump counts on entering free fall or a canopy, if the exit is more than 150 m above ground or straight into a parachute | — | Yes | Does not tell free fall from static line; a canopy opened close to the ground also counts. |
| Distance on foot | Mod | Kilometres on foot during missions (implausible speeds discarded) | — | Yes | Does not measure load carried or elevation gain. |
| Ground vehicle hours | Mod | Time aboard a ground vehicle, any seat | — | Yes | Does not tell driver, gunner and passenger apart. |
| Hours on water | Mod | Time aboard a boat, any seat | — | Yes | Time under water (diving) is not recorded. |
| Hours aboard aircraft (any seat) | Mod | Time aboard an aircraft, pilot or passenger | — | Yes | Useful for jumpers, crews and jumpmasters; does not say who was flying. |
| Approved after-action reports | Site: reports (AAR) | Number of AARs written by the member and approved by a manager | — | Yes | An unapproved AAR does not count. |
| Seniority in the community | Site: member file | Days since the enlistment date on file, otherwise since joining the community | — | No | A wrong enlistment date skews the result. |
| Days played with an item | Mod (equipment seen in the in-game inventory) | Number of distinct days the item or item family was seen on the member | One item (class) or a family: primary weapon, launcher, handgun, optic, muzzle device, pointer, bipod, ammunition and grenades, uniform, vest, backpack, headgear, goggles, night vision, binoculars and designators, radio, medical equipment, other | Yes | Carrying is not using: a rocket launcher on the back counts as much as one fired. |

## 2. Conditions checked by the site (outside the game)

| Condition | Source | Calculation | Limits |
|---|---|---|---|
| Academy course passed | Academy (built-in courses such as JTAC, community courses) | The member passed the course and its assessment; in Academy course mode, the certificate creates the qualification | An old pass counts, unless the qualification has a validity. |
| Qualification held | Qualifications reference | The member holds another valid qualification (not expired, not withdrawn) | An expired prior qualification blocks. |
| Minimum rank | Qualification setting | The rank on the member’s file is at least the one set | Set on the qualification, section “Prerequisites and display”, not in the rule blocks. |
| Job held | Qualification setting | The member holds the job set | Same “Prerequisites and display” section. |

## 3. Data reported but not (yet) usable as a threshold

- **Time per role** (role played in game): visible in Player metrics, not yet an award criterion.
- **9-LINEs, 5-LINEs and MEDEVAC requests** sent from the JTAC app: kept with their status and viewable by the instructor; to be attested.
- **ATAK phone screen time** and watch steps: displayed, no criterion.

## 4. Example rules

**Parachutist badge (initial stage)**
- “All” block: Academy course “Static-line jump”; Parachute jumps ≥ 5.
- “All” block: Static-line jumps (attested) ≥ 5.

**Keeping the parachutist badge current**
- Validity 12 months; Parachute jumps ≥ 2 in the last 365 days.

**Qualified UH-60 pilot**
- “All” block: Flight hours, UH-60 family, ≥ 15 h; Number of flights, UH-60 family, ≥ 10; Night helicopter flight hours ≥ 2 h.
- “At least one” block: hold “Helicopter pilot — basic”; or Flight hours, helicopters, ≥ 40 h.

**Squad leader**
- “All” block: Event attendance — Operations ≥ 10 in the last 180 days; Approved reports ≥ 3; Seniority ≥ 120 days; hold “Team leader”.

**Radio operator**
- “All” block: Days played with an item — Radios ≥ 15; Academy course “ACP 125 radio procedures”.

**Boat crew**
- “All” block: Hours on water ≥ 6 h in the last 180 days; Missions played ≥ 8.

## 5. Good to know

- The site **never withdraws** a qualification on its own: dropping below a threshold cancels nothing; validity and renewal handle that.
- A withdrawn, refused or suspended qualification is **never re-awarded** automatically.
- The qualification page shows, for each “Progressing” member, the current value and the threshold of every condition.
- Thresholds found in the catalogue text (“5 jumps”, “200 flight hours”) are offered in the “Detected prerequisites” panel; the “Manual prerequisites” guide covers the conditions that remain to be attested.
