# Military referential seed: audit and enrichment (October 2026)

This enrichment is the block « ENRICHISSEMENT / CORRECTIONS » at the end of `military_referential_seed()` in `bootstrap/military_referential_seed.php`.

- **Checked:** the full migration (`bootstrap/military_referential_migration.php`) was run twice on MariaDB 10.11: no warning, no orphan parent, idempotent counts (320 units in 12 countries, 309 active).

## How the fragment works (important)
- **Why `$U` is redefined:** the original `$U` closure captures `$countryId`, `$serviceId` and the other maps **by value**, so it would never see codes added afterwards. The fragment inserts its countries, services, types, specialties and sources, reloads the maps, and then redefines `$U`.
- **Fixes need deletes:** aliases and classification, function and specialty links are additive (`INSERT IGNORE`). A fix therefore needs explicit deletes. The fragment adds the helpers `$dropAlias`, `$dropClass`, `$dropSpec` and `$setStatus` (status `active|inactive|dissolved` plus `dissolved_at`, as the admin controller expects).
- **Statuses are reapplied on every run:** the original `$U` calls reset `status='active'` each time. Because the fragment runs afterwards, it applies the dissolved or inactive status again on every seed run.
- **New service types:** `SPECIAL_FORCES` (pl-ws, no-spesialstyrker) and `POLICE` (es-pn). Nothing in the code constrains `service_type`.
- **New entity type:** `DIVISION`.
- **New specialties:** `AIR_ASSAULT`, `MOUNTAIN_WARFARE`, `AMPHIBIOUS_OPERATIONS`, `ARCTIC_WARFARE`, `CBRN_DEFENSE`, `COMBAT_ENGINEERING`.
- **Alias types:** only the 9 allowed by `SystemMilitaryReferentialController` are used.

## Corrections to existing entries

### France
| Code | Fix |
|---|---|
| fr-bfst (CAST) | Confirmed. COM FST (2016) was renamed CAST on 1 Jan 2024. Its organic authority is the CFOT; it is employed by the COS. Added aliases COM FST / FST. |
| fr-cfor | Renamed **Académie des forces spéciales** (formerly centre Arès). |
| fr-ciae | Confirmed: integrated into CAST on 1 Oct 2023 (defense.gouv page). Source changed. |
| fr-712ct | Confirmed: 712e CT is part of CAST (created 2025). No change. |
| fr-cpa20 | **Wrong parent.** BFSA is CPA 10, CPA 30, ET 3/61 Poitou and EH 1/67 Pyrénées only. CPA 20 is moved under the new `fr-bafsi` (Brigade aérienne des forces de sécurité et d'intervention). Its SOF/SOF_SUPPORT classifications and the PERSONNEL_RECOVERY specialty are removed. |
| fr-cpa30 | Aliases added (RESCO). It absorbed the specialised groups of CPA 20 in 2018. |
| fr-gign | Source corrected to the Gendarmerie (it was the COS source). |
| New | fr-cpmo: Centre terre pour le partenariat militaire opérationnel, under CAST. |

### United States
| Code | Fix |
|---|---|
| us-17sts | **Re-parented 720th STG → 724th STG** (move effective 1 Oct 2020). |
| us-8pog | **Dissolved 2026-09-29.** The 8th POG(A) was inactivated at Fort Bragg (army.mil/article/295782). |
| us-mrg | The real name is **Marine Raider Support Group (MRSG)**. The seed had "Marine Raider Group" with MRSG as a former name, which is wrong. The bad aliases are removed and MRSG is added as an ACRONYM. |
| us-srec | **Set inactive.** No public source found ("Special Reconnaissance and Enabling Command" is absent from the 2023–2025 Fact Books, CRS primers and socom.mil). Remove it from the seed unless you hold a source. |
| us-delta | Official name now includes "(Airborne)". Aliases added: Army Compartmented Elements / ACE / Task Force Green / The Unit. |
| us-24sts | Kept under JSOC (operational). Note: its organic parent is the 724th STG. |
| NSWG / SDVT | Already correct. NSWG-3 and NSWG-10 were disestablished in Aug 2021 and folded into NSWG-8, which owns the SDVTs. |
| 4th POG, 528th SB, MRR/MRB, MRTC | Verified, no change. |

### Germany
| Code | Fix |
|---|---|
| de-dsk (new) | Division Schnelle Kräfte, level 0. **KSK (`de-kdo-sok`) is re-parented under DSK.** |
| de-ksk-hq | Renamed "Stab Kommando Spezialkräfte" (was a French placeholder). |
| de-ksk-kompanien | Renamed "Kommandokräfte". The official structure is Stab / Kommandokräfte / Unterstützungskräfte; `de-ksk-unterstuetzung` is added. The 2nd company was dissolved in 2020, so no per-company entries are given. |
| de-ksm | **Name corrected** to "Kommando Spezialkräfte **der Marine**" (it was "Spezialoperationen"). It now sits under the new `de-einsfltl1` (Einsatzflottille 1, Eckernförde). |
| de-ksm-kompanien | Renamed Kampfschwimmerkompanie. Two companies from the KSM 2023+ structure are added: Spezialoperationen-Bootskompanie and Unterstützungskompanie. |
| Added | Luftlandebrigade 1, Fallschirmjägerregiment 26 and 31. |

### Belgium
| Code | Fix |
|---|---|
| be-sor (new) | **Special Operations Regiment**, Heverlee. It was the Light Brigade until 3 July 2018. Its units are SF Gp, 2 Cdo, 3 Para, 6 Gp CIS and the para and commando training centres. |
| be-sf-gp | Re-parented under SOR (level 1). Its children move to level 2. |
| be-2para | **"2 Para" does not exist.** Renamed **2e Bataillon Commando (2 Cdo)**; the "2 Para" alias is removed. |
| be-3para | Renamed "3e Bataillon de Parachutistes" and placed under SOR. |
| be-para-cdo | Turned into the historical "Brigade Para-Commando", status **dissolved** (no exact date found, so `dissolved_at` is NULL). |
| be-socom (new) | Belgian SOCOM under the new `be-defence` service. It was set up in 2017; it is a coordination, advice and acquisition centre with no direct command of SOF units. |
| Uncertain | `be-sf-gp-1st/2nd/3rd` (the "compagnies opérationnelles") are not publicly documented. They are kept, but should be removed. |

### Spain
| Code | Fix |
|---|---|
| es-boe-i/ii/iii | **These never existed.** There were no "BOE Órdenes" units. All three are set **inactive**; delete the loop at seed lines 987-994. |
| Replacements | Official MOE organigram: Cuartel General, `es-moe-gcg` (Grupo de Cuartel General), **es-goe-iii** (GOE «Valencia» III), **es-goe-iv** (GOE «Tercio del Ampurdán» IV), **es-boel-xix** (Bandera de Operaciones Especiales «Caballero Legionario Maderal Oleaga» XIX; the GOE XIX name dates from 2002–2017), and `es-moe-ulog` (Unidad Logística). **es-goe-ii** «Granada» II is added as **dissolved** (Feb 2020). |
| es-mcoe (new) | Mando Conjunto de Operaciones Especiales (2014, EMAD, Retamares), under the new `es-emad` service. |
| es-uoe | **Dissolved 2009-06-10.** It merged with the UEBC into the **FGNE** (Fuerza de Guerra Naval Especial, La Algameca, Cartagena), added as `es-fgne`. |
| es-ezapac | **Name fixed** to "Escuadrón de Zapadores Paracaidistas" (it was "...Apoyo al Despliegue Inmediato", which is the EADA, a different unit). Specialties added. |
| es-grupo-oe | **Set inactive.** The Guardia Civil has no GOE. Added `es-gc-uei` (UEI, Valdemoro), `es-gc-uar` and `es-gc-gar` (UAR/GAR, Logroño), and `es-pn-geo` (GEO, Policía Nacional, new `es-pn` service). |
| Added | BRIPAC «Almogávares» VI and BRILEG (La Legión), top-level only. |
| Recommended line edits in the repo | Delete lines 987-994 (`es-boe-*`) and 1008-1012 (`es-grupo-oe`). Change line 1003 to the EZAPAC real name. Optionally replace `es-uoe` with `es-fgne`. |

## New units: 186 in total
| Country | New | Content |
|---|---|---|
| GB | 39 | UKSF: 22/21/23 SAS, SBS, SBS(R), SRR, 18 (UKSF) Sig Regt, SFSG, JSFAW with 7 Sqn RAF and 658 Sqn AAC. LSOF, Ranger Regiment and its 4 battalions; ASOB (dissolved 14/04/2026). 16 AA BCT: 2/3/4 PARA, Pathfinder Platoon, 7 PARA RHA, 23 Para Engr Regt, 13 AASR, 16 Med Regt, 216 Sig Sqn. **UK Commando Force** (3 Cdo Bde renamed in June 2026): 40/42/43/45/47/30 Cdo, 29 Cdo RA, 24 Cdo RE, CLR. |
| FR | 36 | 11e BP and its 8 regiments plus the ETAP. 27e BIM with 7e/13e/27e BCA, 4e RCh, 93e RAM, 2e REG. 9e BIMa with 2e/3e RIMa, 126e RI, RICM, 11e RAMa, 6e RG. COMLE with 1er RE, 4e RE, 3e REI, DLEM. 6e BLB with 2e REI, 1er REC, 1er REG, 13e DBLE. Also BAFSI and CPMO. |
| US | 35 | 82nd ABN (1st/2nd/3rd BCT, CAB). 101st ABN (AASLT) (1st/2nd/3rd BCT, CAB). 11th ABN (1st/2nd BCT). 173rd ABN. 10th MTN (1st/2nd/3rd BCT, CAB). 25th ID (2nd/3rd BCT, CAB). 1st MARDIV (1st/5th/7th/11th Marines, 1st Recon Bn). 2nd MARDIV (2nd/6th/10th Marines, 2nd Recon Bn, plus 8th Marines dissolved 2021-01-28). |
| IT | 18 | COFS, COMFOSE (9° Col Moschin, 4° Alpini Paracadutisti, 185° RRAO, 28° Pavia), 3° REOS «Aldebaran», COMSUBIN (GOI, GOS), 17° Stormo Incursori, 2ª Brigata Mobile CC (GIS, 1° Tuscania), Brigata Folgore (183°, 186°, 187°). |
| ES | 14 | See the Spain table above. |
| DE | 8 | DSK, KSK Unterstützungskräfte, LLBrig 1, FschJgRgt 26/31, Einsatzflottille 1, 2 KSM companies. |
| PL | 7 | DKWS, JW GROM (JW 2305), JWK (4101), JW Formoza (4026), JW Agat (3940), JW Nil (4724), 7 EDS (Air Force, Powidz). |
| NL | 7 | NLD SOCOM (2018), KCT, NLMARSOF, 11 Luchtmobiele Brigade and its 3 infantry battalions. |
| AU | 6 | SOCOMD, SASR, 1 and 2 Cdo Regt, SOER, Special Operations Logistic Squadron. |
| CA | 6 | CANSOFCOM / COMFOSCAN, JTF 2, CSOR, 427 SOAS, CJIRU, CSOTC. French official names are given as aliases. |
| BE | 5 | SOCOM, SOR, 6 Gp CIS, CE Para (Schaffen), CE Cdo (Marche-les-Dames). |
| NO | 5 | Forsvarets spesialstyrker (NORSOCOM), FSK, Jegertroppen, MJK, 339 SOAS. |

## Uncertain points to review
- **Delta / 24th STS / SFSG:** parents are operational (JSOC / UKSF), not organic.
- **Italian GOI, 17° Stormo and GIS** are placed under their organic parents, not under COFS, even though COFS employs them. 3° REOS is placed at level 0; it belongs to the Brigata Aeromobile «Friuli» and supports COFS.
- **French brigades** are placed at level 0. The divisional attachments after the 2024–2025 Army reform (1re/3e division) could not be confirmed.
- **Légion units:** 2e REI, 1er REC, 1er REG and 13e DBLE are under the 6e BLB, and 3e REI and DLEM are under COMLE. The organic chain for 3e REI and DLEM is debatable (FAG / FAZSOI).
- **7 EDS (Poland):** reports mention a reorganisation into a "PJOS" (Powietrzna Jednostka Operacji Specjalnych) built on 7 EDS. Its organic parent is 3 SLTr and its operational command is DKWS. Kept as 7 EDS with a PJOS alias.
- **Australia:** the 2021 fact sheet mentions a "Special Forces Group" HQ under SOCOMD, which is not modelled. The training centre name is unconfirmed (SFTC vs DSOTEC), so it is not added.
- **47 Sqn RAF** in the JSFAW has only one source and is not added. **1 R IRISH** in 16 AA BCT is not confirmed and is not added.
- **US BCT designations** are changing under the Army Transformation Initiative (IBCT → MBCT, 2025–2027). Generic "BCT" names are used.
- **Rangers predecessor battalions** (1 SCOTS / 2 PWRR / 2 LANCS / 4 RIFLES) are from 2021 announcements.
- **Placeholder source:** the wojsko-polskie.pl URL is the generic portal; I did not confirm a DKWS-specific URL.
- **Fetching limits:** WebFetch could not resolve wikipedia.org or bundeswehr.de from this sandbox. Facts are based on WebSearch extracts of those pages and of the official pages listed below.

## Main source URLs
- CAST: https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre
- CIAE: https://www.defense.gouv.fr/terre/unites-larmee-terre/nos-brigades/commandement-actions-speciales-terre/centre-interarmees-actions
- 14 July 2026 CAST page (712e CT, CIAE): https://www.defense.gouv.fr/ministere/dossiers-evenementiels-thematiques/editions-du-14-juillet/defile-du-14-juillet-2026/unites-du-defile-91
- COM FST → CAST decision: https://prod-site-internet-minarm-admin.cloud.defense.gouv.fr/sites/default/files/bulletin-officiel/Texte-certifie-346226.pdf
- 8th POG inactivation: https://www.army.mil/article/295782
- 17th STS move: https://soldiersystems.net/2020/10/02/17th-sts-moves-from-720th-stg-to-724th-stg/ and https://www.afsoc.af.mil/News/Tag/159929/17-sts/
- NSWG-8 creation: https://www.defensemedianetwork.com/stories/u-s-naval-special-warfare-command-establishes-group-eight-disestablishes-groups-three-ten/
- MARSOC: https://www.marsoc.marines.mil/About
- 8th Marines deactivation: https://www.2ndmardiv.marines.mil/News/Press-Releases/Article/2486224/8th-marine-regiment-deactivates-in-accordance-with-commandants-force-design-203
- KSK: https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/kommando-spezialkraefte
- KSM: https://www.bundeswehr.de/de/organisation/marine/struktur/einsatzflottille-1/kommando-spezialkraefte-marine
- KSM 2023+ press release: https://www.presseportal.de/download/document/642668d4270000fd2cc9b163-20230405-aufstellungsappell-ksm-018-23.pdf
- LLBrig 1: https://www.bundeswehr.de/de/organisation/heer/struktur/division-schnelle-kraefte/luftlandebrigade-1
- Belgian SOR: https://www.armyrecognition.com/archives/archives-land-defense/land-defense-2018/the-belgian-army-inaugurated-its-special-operations-regiment
- Belgian SOCOM: https://www.thekcis.org/publications/insights/insight-16
- MOE organigram: https://ejercito.defensa.gob.es/unidades/Alicante/cg_moe/Organizacion/index.html
- GOE II dissolution: https://www.defensa.com/opinion/malas-noticias-para-mando-operaciones-especiales-disuelve-goe-ii
- MCOE: https://emad.defensa.gob.es/Galerias/prensa/noticias/Documentos/ANUARIO_2015_MCOE.pdf
- FGNE: https://www.defensa.com/enfoque/medios-para-fuerza-guerra-naval-especial-armada
- GAR/UAR: https://web.guardiacivil.es/export/sites/guardiaCivil/documentos/pdfs/2023/CartaServicios/CS_La_Rioja-_con_NIPO.pdf
- UK Commando Force: https://www.royalnavy.mod.uk/news/2026/june/09/20260609-commando-name-change
- Ranger Regiment: https://www.gov.uk/government/news/army-announces-special-ops-army-ranger-regiment
- 23 Para Engr Regt: https://www.army.mod.uk/learn-and-explore/about-the-army/corps-regiments-and-units/engineering/corps-of-royal-engineers/23-parachute-engineer-regiment
- CANSOFCOM: https://www.canada.ca/en/department-national-defence/corporate/organizational-structure/canadian-special-operations-forces-command.html
- SOCOMD: https://www.defence.gov.au/sites/default/files/2021-07/special_operations_command.pdf
- 3° REOS: https://www.esercito.difesa.it/en/organization/The-Chief-of-General-Staff-of-the-Army/comfoter/Army-Aviation-Command/Army-Aviation-Brigade/3rd-Aldebaran-Regiment-Helicopters-for-Special-Operations/Pagine/3-reos-la-storia.aspx
- Italian special forces (Camera dei deputati): https://temi.camera.it/leg19DIL/temi/19_le-forze-speciali.html
- NLD SOCOM: https://www.defensie.nl/organisatie/bestuursstaf/cds/netherlands-special-operations-command
- Norway FSK: https://snl.no/Forsvarets_Spesialkommando
