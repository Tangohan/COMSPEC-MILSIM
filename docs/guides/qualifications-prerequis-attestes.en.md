# Manual prerequisites (instructor attestation)

Not everything can be measured in Arma 3. For what the game cannot count, a qualification’s award rule accepts **attested conditions**: an instructor signs them off member by member, with a note pointing to the proof. The rest of the rule (hours, jumps, Academy courses…) keeps being checked automatically.

## 1. Attestation types

| Type | Nature | Example label | Why it is not measured |
|---|---|---|---|
| Free-fall jumps | Number, held by the member | “50 free-fall jumps” | The mod counts jumps without knowing whether the canopy was opened by hand or by static line. |
| Static-line jumps | Number, held by the member | “5 static-line jumps, including 1 at night” | Same reason. |
| Dive hours | Number, held by the member | “10 dive hours, including 2 at night” | The mod does not record time under water. |
| Marksmanship qualification | Test specific to the qualification | “Range: 8 hits out of 10 at 600 m” | The mod records neither shots nor hits. |
| Fitness tests | Test specific to the qualification | “12-mile march in under 3 h”, “500 m swim” | No measure of load, test timing or swimming. |
| Other condition (“Condition attested by an instructor”) | Test specific to the qualification | “6 complete 9-LINE controls”, “Check ride passed”, “Mission plan briefed” | An instructor’s judgement. |

**Number held by the member**: an attestation of jumps or hours counts for **every** qualification that asks for it. A member attested with “60 free-fall jumps” meets at once the jumpmaster’s “50 jumps” and the free-faller’s “20 jumps”. A **test** (marksmanship, fitness, other) is specific to the qualification and its label.

## 2. How to attest

1. Add the condition to the qualification rule (Reference › Qualifications › the qualification › Edit, section “How to obtain it”), choosing the type and, for a test, a precise label.
2. On the qualification page, the **“Requirements attested by an instructor”** panel lists each condition, the members already attested and those who can be.
3. Pick the member, enter the value (number of jumps, hours) if the condition carries one, and a **note**: session date, place (map, server), instructor’s name, link to the proof.
4. The attestation can be corrected or removed from the same panel.

As soon as the attestation is saved, the member progresses in the rule; if everything is met, the qualification is awarded (or proposed) at the next hourly check.

## 3. Good practice

- **One label = one standard**: “8/10 at 600 m, daytime, prone, issued rifle” rather than “shooting passed”.
- **Two people** for high-level qualifications: the instructor who runs the session and a second one who checks the proof.
- **Attest right after the session**, while the proof is fresh.
- **Do not replace a measure with an attestation**: if the mod counts the data (jumps, flight hours), set the measured threshold and keep the attestation for what it cannot tell apart.
- **Explain the rule** in the qualification description, so members know what will be attested and how to prepare.
- **Set a validity** on tests that decay (marksmanship, fitness): the qualification shows “to renew” when it comes due.

## 4. Acceptable proof

| Proof | For what | Where to keep it |
|---|---|---|
| Screenshots or video | Range grouping, altimeter at opening, score table | Community shared folder; link in the note |
| After-action report (AAR) | How the session went, participants, results | Site reports (approved by a manager) |
| JTAC app messages | 9-LINE, 5-LINE, MEDEVAC sent, status up to the BDA | ATAK data, reviewed by the instructor |
| Countersigned logbook | Night, instrument, formation flights | The pilot’s logbook (pilot syllabus) |
| Session sheet (FRM, assessment sheet) | Criteria graded one by one, “GO / NO GO” | Community document; link in the note |

## 5. What the mod could measure in the future

This data is not recorded today; it would let some attestations become automatic thresholds:

- **Free fall / static line**: tell a manual opening from a static-line opening, and the opening altitude.
- **Time under water**: dive duration, depth, rebreather or open circuit.
- **Shots and hits**: rounds fired, hits, target distance, per weapon.
- **Loaded march**: distance on foot with weight carried and elevation gain.
- **Counted JTAC messages**: number of complete 9-LINE / 5-LINE controls carried through to the BDA, per member.

Until then, attest these conditions with a precise label: the day a measure exists, the rule can switch over without changing the qualification.
