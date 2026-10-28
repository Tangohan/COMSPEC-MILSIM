# COMSPEC SSE — Zeus quick start (English)

What to place so players can run a site exploitation (SSE) out of the box. Everything below is available in Zeus and in Eden. No ZEN required.

## 1. Equip the players (2 minutes)

The easiest way is to drop **Place a kit crate** (category *SSE · Sites and kits*) next to the team. Pick one of these kits:

- **Team kit** (`COMSPEC_SSE_Box_Team`): 20 evidence bags, 8 pairs of gloves, camera, 2 sets of cones, 6 document bags, 4 Faraday bags, 4 head bags.
- **Biometrics kit** (`COMSPEC_SSE_Box_Biometrics`): SEEK II, 2 fingerprint kits, 6 swabs, DNA kit, 6 pairs of gloves, 6 evidence bags.
- **Digital exploitation kit** (`COMSPEC_SSE_Box_Digital`): 8 Faraday bags, terminal, camera, 4 pairs of gloves, 8 evidence bags.
- **DOMEX case**: digital intelligence on site.

Other ways to equip the players:

- To give one player the scanner, use **Issue SEEK terminal** on that unit.
- Items can also go straight into an inventory or arsenal:

| Item | Class |
|---|---|
| SEEK II terminal (biometrics) | `COMSPEC_SSE_SEEKII` |
| Fingerprint kit / DNA kit / swab kit | `COMSPEC_SSE_FingerprintKit`, `COMSPEC_SSE_DNKit`, `COMSPEC_SSE_SwabKit` |
| Evidence bag, gloves, camera, markers | `COMSPEC_SSE_EvidenceBag`, `COMSPEC_SSE_Gloves`, `COMSPEC_SSE_Camera`, `COMSPEC_SSE_EvidenceMarkers` |
| Faraday bag, head bag | `COMSPEC_SSE_FaradayBag`, `COMSPEC_SSE_HeadBag` |

## 2. Create something to exploit

Pick one of these modules:

- **Place an SSE site**: a prefab site (safehouse, IED lab, forgery, weapons cache, cell HQ, dead drop, hawala) with occupants, furniture and linked evidence.
- **Populate a building**: turns the building under the module into a site.
- **Seed evidence**: scatters 1 to 20 evidence items in the buildings around, linked to the AI in the radius.
- **Multi-site network**: 3 to 6 sites that point to each other, with a cell HQ at the end.
- **SSE training site**: a ready-made training site.

Existing objects and units can be made exploitable too:

- **Make exploitable (SSE)** on any unit or object turns it into an SSE subject or piece of evidence.
- **Subject identity (SEEK query)** on a unit sets what the SEEK query returns for that unit (no match, possible, confirmed).

## 3. During the mission

- **SSE Zeus panel** and **SSE site manager** show what players have found and let you reset or adjust a site.
- **Known to players / truth** compares what players know with the real data.
- **SSE after-action report (AAR) + graph** builds the debrief.

## Player side

- **SEEK II**: look at a person within 6 m and use the ACE interaction, or open the SSE app in the ATAK phone.
- **Evidence**: use the ACE interaction on the item (examine, photograph, bag).
- **Results**: they go to the SSE app in the ATAK phone and, with the Athena link, to the SSE office on the website.

More detail on sites and kits is in `SITES_ET_KITS.md` (French).
