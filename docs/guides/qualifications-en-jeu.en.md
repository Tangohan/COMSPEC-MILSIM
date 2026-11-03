# Using qualifications in game (Arma 3)

This guide explains how a community organises training and awards qualifications with ATHENA and the ATAK mod: which data the game reports, how to combine it with an Academy course and an instructor’s sign-off, and how to keep a qualification alive over time.

Qualifications from the catalogues (United States, France, Germany, United Kingdom, NATO…) are **in-game qualifications inspired by real badges, with no real-world value**. They structure player progression; they do not reproduce a military pipeline one for one.

## 1. Three layers

A well-built qualification combines, as needed:

1. **What the game measures on its own**: the ATAK mod and the site count play hours, flight hours per aircraft, missions played, parachute jumps, attendance, approved reports and equipment seen in game. See the “Automatic prerequisites” guide.
2. **What the Academy checks**: a course (theory, practical cases, timed assessment) passed by the member. The Academy JTAC course is the typical example.
3. **What only an instructor can judge**: a range qualification, a fitness test, a free-fall rather than static-line jump, a dive. The instructor attests it member by member. See the “Manual prerequisites (attestation)” guide.

The site puts these conditions together in the qualification’s **award rule**: up to 8 blocks of 12 conditions, each block “all” or “at least one”. The rule is set in Reference › Qualifications › the qualification › Edit, section “How to obtain it”.

## 2. The four award modes

| Mode | When to use it | What happens |
|---|---|---|
| Awarded by a manager | One-off qualification, leadership decision | An instructor or manager awards it by hand, with the date and a comment or proof. |
| Academy course | The qualification certifies a course | Passing the course creates the qualification (Academy certificate). |
| Automatic | The thresholds are measurable | The site checks the rule every hour and awards as soon as everything is met, or **proposes** the award (the “To approve” list on the qualification page) if you prefer to keep control. |
| Pilot syllabus | Pilot stages | Stages based on flight hours, set on the Pilot syllabus page. |

Tip: start in “Automatic” with **proposed** awards. Instructors see members who meet the rule and approve in one click; switch to direct awards once the rule has proved itself.

## 3. Importing and preparing the qualification

1. Academy › Catalogue › Qualifications tab: open the **import wizard** (a whole country, a category, a level or an issuer) or import a single entry. The prior qualifications named as prerequisites can be imported with it.
2. Open the imported qualification: the **“Detected prerequisites”** panel offers, pre-ticked, the prerequisites read from the catalogue text (“5 jumps”, “50 free-fall jumps”, “Basic Airborne Course”, minimum rank). Review and confirm: nothing is applied without you.
3. Complete the rule: measured thresholds, Academy course, prior qualification, attested conditions.
4. Set the **validity** (in months) if the qualification must be renewed.

## 4. Worked examples

### Parachutist badge (Basic Airborne Course style)

- **Block 1 (all)**: pass an Academy course “Static-line jump” (in-aircraft procedures, exit, landing, malfunctions); **5 parachute jumps** measured by the mod.
- **Block 2 (all)**: attested condition “Static-line jumps”, 5 jumps, signed off by a jumpmaster (the mod counts jumps without telling free fall from static line).
- Mode: automatic, proposed award.

In game: a jump session from a C-130 or a helicopter over a drop zone, five passes, a jumpmaster noting clean exits.

### JTAC

- **Block 1 (all)**: pass the Academy course **“JTAC — ATH-ACADEMY Licence”** (six chapters, timed assessments, instructor validation); hold “Joint Fires Observer (JFO)” if you use it as a stage.
- **Block 2 (all)**: attested condition “Terminal controls in game” with a label such as “6 complete 9-LINE / 5-LINE controls sent from the JTAC app”, signed off by a JTAC instructor who reviewed the messages.
- Suggested validity: 6 to 12 months, renewed on a new attestation of controls.

The 9-LINEs, 5-LINEs and MEDEVAC requests sent from the ATAK JTAC app are kept by the site (with their status, up to the BDA), but **they are not yet counted as an automatic criterion**: the instructor reviews and attests them.

### AH-64 pilot (or any other aircraft family)

- **Block 1 (all)**: “Flight hours”, scope **AH-64 family**, 20 h; “Night flight hours, helicopter”, 3 h (countersigned logbook); hold your syllabus’s basic helicopter pilot qualification.
- **Block 2 (at least one)**: pass the Academy aircraft employment course, or attestation “Check ride passed” by an instructor pilot.
- Hours come from the mod (time at the controls, aircraft by aircraft) **and** the logbook: the pilot enters missed flights (server not linked, private mission) and an instructor countersigns them.

### Sniper

- **Block 1 (all)**: attested condition **“Marksmanship qualification”**: “Range: 8 hits out of 10 at 600 m, daytime, with the issued precision rifle”; “Days played with an item”, scope the precision rifle (class seen in game) or the “Optics” family, 10 days.
- **Block 2 (all)**: pass an Academy “Sniper” course (ballistics, range estimation, reporting); 2 approved after-action reports as a sniper-spotter pair.

The mod does not yet record shots and hits: the range session stays an attestation.

### Combat medic (TCCC / MEDEVAC)

- Academy TCCC course; “Days played with an item”, “Medical equipment” family, 15 days; attested condition “Practical test: MARCH-PAWS on two casualties and a 9-LINE MEDEVAC request in under 5 minutes”.

## 5. Training mission scenarios

| Scenario | Goal | Reported automatically | Attested by the instructor |
|---|---|---|---|
| Firing range | Marksmanship, sniper | Attendance (event check-in), approved session, equipment carried | Score, distances, position, time |
| Jump | Parachutist, free fall, jumpmaster | Jumps (exit ≥ 150 m), hours aboard an aircraft, mission played | Free fall or static line, exit, grouping, landing |
| CQB / shoot house | Assault team, breacher | Attendance, approved session, server play time | Entry drills, angle safety, communication |
| MEDEVAC | Medic, helicopter crew, JTAC | Flight hours (pilots), attendance, medical equipment seen | Quality of the 9-LINE request, care, evacuation time |
| Navigation / march | Reconnaissance, mountain | Kilometres on foot, mission played | Route followed, checkpoints, load carried |

Create these sessions as **“Training” events** in the calendar: check-in on arrival feeds “Event attendance — Training”, and a **game session** approved by managers feeds “Approved session hours” (which can be filtered by hour category, for example “Instruction”).

## 6. The instructor’s role

- **Design the rule** with the leadership: few conditions, all verifiable.
- **Run the session** in game and collect proof: screenshot, recording, after-action report (AAR), JTAC app log.
- **Attest** on the qualification page (“Requirements attested by an instructor” panel): the value (number of jumps, hours) and a note pointing to the proof.
- **Approve proposals** (“To approve”) when the award is not direct.
- **Withdraw or suspend** a qualification if needed: a withdrawn, refused or suspended qualification is never re-awarded automatically.

## 7. Renewal

A qualification with a validity expires on its due date. When it comes due (expiring soon, expired within the grace period or expired) **and the rule is still met**, the member shows as “to renew” on the qualification page. For a rule that is really demanding at renewal, use **rolling windows** (last 30, 90, 180 or 365 days): for example “2 jumps in the last 365 days” to keep the parachutist badge current, or “5 flight hours in the last 90 days” for a pilot.

## 8. Good practice

- One qualification = one readable skill. Prefer three stages (initial, qualified, instructor) to one overloaded qualification.
- Measure what can be measured, attest the rest, and say so in the qualification description.
- Keep proof in one place (session AAR, shared folder) and cite it in the attestation note.
- Check the “Progressing” panel now and then: it shows who is close to the qualification and what they are missing.
