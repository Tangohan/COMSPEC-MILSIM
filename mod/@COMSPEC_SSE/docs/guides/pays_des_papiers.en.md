# Country of the papers

Version française : [pays_des_papiers.md](pays_des_papiers.md). Index: [index.en.md](index.en.md).

SSE papers are issued by a **country**: it gives the first and last names, the towns, the address in the country's format, the numbers (with their check keys), the MRZ (ICAO issuer code: « D » for Germany), dates (country format), height (cm, or feet-inches in the United States), names of papers and authorities in the country's language, and a few words of that language for the AI (« Language: local » in the AI workshop). **Real countries first** (France, Germany, United States…), then the fictional countries of the Arma maps (Altis, Tanoa, Livonia, Malden), kept so existing missions load as before.

## Where to choose the country

- **CBA settings** (COMSPEC SSE › Identité): **Country of the papers** (Auto (by map) or a country) and **Default country of the papers** (used by Auto on an unknown map; Altis by default). Example: France: civilians carry a French national identity card.
- **Eden**: unit attribute COMSPEC SSE — Papiers › **Country of the papers**; group attribute COMSPEC SSE · Papiers du groupe › **Country of the papers** (every unit).
- **AI workshop** (Eden and Zeus): **Country of the papers** list. Choosing a country on an AI whose papers were already issued redoes them in that country (Athena papers kept); the window's status line says so. With several AIs selected, each one follows.
- **Give a document** (Zeus, Eden): list of countries, preview in the country's format (title and labels in its language).

## « Auto (by map) »

Known map → country. Any other map → « Default country of the papers » setting.

| Map (worldName) | Country |
|---|---|
| `spe_mortain` | France (FRA) |
| `spe_normandy` | France (FRA) |
| `gm_weferlingen_summer` | Germany (DEU) |
| `gm_weferlingen_winter` | Germany (DEU) |
| `rosche` | Germany (DEU) |
| `napf` | Switzerland (CHE) |
| `napfwinter` | Switzerland (CHE) |
| `bornholm` | Denmark (DNK) |
| `vt7` | Finland (FIN) |
| `fallujah` | Iraq (IRQ) |
| `takistan` | Afghanistan (AFG) |
| `zargabad` | Afghanistan (AFG) |
| `lythium` | Afghanistan (AFG) |
| `tem_anizay` | Afghanistan (AFG) |
| `kunduz` | Afghanistan (AFG) |
| `tem_kujari` | Mali (MLI) |
| `altis` | Republic of Altis (ALT) |
| `stratis` | Republic of Altis (ALT) |
| `tanoa` | Federation of Tanoa (TNA) |
| `enoch` | Republic of Livonia (LVN) |
| `malden` | Republic of Malden (MLD) |

## The countries

| Code (ISO 3166) | Country | Level | Papers issued | Dates | Everyday paper |
|---|---|---|---|---|---|
| FRA / FR | France | full | National identity card, Passport, Driving licence, Residence permit, Vehicle registration certificate, Military identity card | dd/mm/yyyy | Carte nationale d'identité |
| DEU / DE | Germany | full | Identity card, Passport, Driving licence, Residence permit, Registration certificate Part I, Military identity card | dd.mm.yyyy | Personalausweis |
| USA / US | United States | full | State ID card, Passport, Driver license, Permanent resident card (green card), Vehicle registration, Military ID (Common Access Card) | mm/dd/yyyy | Driver License |
| GBR / GB | United Kingdom | full | Passport, Driving licence, Biometric residence permit, Vehicle registration certificate (V5C), Defence identity card (MOD Form 90) | dd.mm.yyyy | Driving Licence |
| BEL / BE | Belgium | full | Identity card, Passport, Driving licence, Residence permit, Vehicle registration certificate, Military identity card | dd.mm.yyyy | Carte d'identité · Identiteitskaart · Personalausweis |
| AUT / AT | Austria | full | Identity card, Passport, Driving licence, Residence permit, Vehicle registration certificate, Military identity card | dd.mm.yyyy | Personalausweis |
| CHE / CH | Switzerland | full | Identity card, Passport, Driving licence, Foreign national permit, Vehicle registration certificate, Military service book | dd.mm.yyyy | Identitätskarte · Carte d'identité · Carta d'identità |
| NLD / NL | Netherlands | full | Identity card, Passport, Driving licence, Residence permit, Vehicle registration card, Defence pass | dd mm yyyy | Identiteitskaart |
| LUX / LU | Luxembourg | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Carte d'identité |
| ITA / IT | Italy | full | Electronic identity card, Passport, Driving licence, Residence permit, Vehicle registration document, Military identity card | dd/mm/yyyy | Carta d'identità elettronica |
| ESP / ES | Spain | full | National identity document (DNI), Passport, Driving licence, Foreigner identity card (TIE), Vehicle registration certificate, Military identity card | dd mm yyyy | Documento Nacional de Identidad |
| PRT / PT | Portugal | generic | Citizen card, Passport, Driving licence | dd mm yyyy | Cartão de Cidadão |
| POL / PL | Poland | full | Identity card, Passport, Driving licence, Residence card, Vehicle registration certificate, Military identity card | dd.mm.yyyy | Dowód osobisty |
| CZE / CZ | Czechia | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Občanský průkaz |
| SVK / SK | Slovakia | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Občiansky preukaz |
| HUN / HU | Hungary | generic | Identity card, Passport, Driving licence | dd mm yyyy | Személyazonosító igazolvány |
| ROU / RO | Romania | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Carte de identitate |
| BGR / BG | Bulgaria | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Lichna karta |
| GRC / GR | Greece | generic | Police identity card, Passport, Driving licence, Military identity card | dd.mm.yyyy | Deltío Astynomikís Taftótitas |
| TUR / TR | Türkiye | generic | Identity card, Passport, Driving licence, Military identity card | dd.mm.yyyy | T.C. Kimlik Kartı |
| UKR / UA | Ukraine | generic | Identity card, Passport for travelling abroad, Driving licence, Military ID | dd.mm.yyyy | Pasport hromadianyna Ukrainy (ID-kartka) |
| RUS / RU | Russia | generic | Internal passport, International passport, Driving licence, Military ID | dd.mm.yyyy | Pasport grazhdanina Rossiiskoi Federatsii |
| BLR / BY | Belarus | generic | Identity card, Passport, Driving licence, Military ID | dd.mm.yyyy | Identifikatsionnaya karta |
| DNK / DK | Denmark | generic | Identity card, Passport, Driving licence, Military ID card | dd.mm.yyyy | Kørekort |
| SWE / SE | Sweden | generic | National identity card, Passport, Driving licence, Armed Forces ID card | yyyy-mm-dd | Nationellt identitetskort |
| NOR / NO | Norway | generic | National identity card, Passport, Driving licence, Armed Forces ID card | dd.mm.yyyy | Nasjonalt ID-kort |
| FIN / FI | Finland | generic | Identity card, Passport, Driving licence, Military passport | dd.mm.yyyy | Henkilökortti |
| EST / EE | Estonia | generic | Identity card, Passport, Driving licence, Military service card | dd.mm.yyyy | Isikutunnistus (ID-kaart) |
| LVA / LV | Latvia | generic | Identity card, Passport, Driving licence, Military ID card | dd.mm.yyyy | Personas apliecība (eID karte) |
| LTU / LT | Lithuania | generic | Identity card, Passport, Driving licence, Military ID card | yyyy-mm-dd | Asmens tapatybės kortelė |
| HRV / HR | Croatia | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Osobna iskaznica |
| SRB / RS | Serbia | generic | Identity card, Passport, Driving licence | dd.mm.yyyy | Lična karta |
| CAN / CA | Canada | generic | Passport, Driver's licence | yyyy-mm-dd | Permis de conduire / Driver's Licence |
| AUS / AU | Australia | generic | Passport, Driver licence | dd/mm/yyyy | Driver Licence |
| ISR / IL | Israel | generic | Identity card, Passport, Driving licence, Military ID card | dd/mm/yyyy | תעודת זהות · Teudat Zehut |
| IRQ / IQ | Iraq | generic | National card, Passport, Driving licence | dd/mm/yyyy | البطاقة الوطنية · al-Bitaqa al-Wataniya |
| AFG / AF | Afghanistan | generic | Tazkira (identity card), Passport, Driving licence | yyyy-mm-dd | تذکره · Tazkira |
| SYR / SY | Syria | generic | Identity card, Passport, Driving licence | dd/mm/yyyy | البطاقة الشخصية · al-Bitaqa al-Shakhsiya |
| MLI / ML | Mali | generic | National identity card, Passport, Driving licence | dd/mm/yyyy | Carte nationale d'identité |
| NER / NE | Niger | generic | National identity card, Passport, Driving licence | dd/mm/yyyy | Carte nationale d'identité |
| TCD / TD | Chad | generic | National identity card, Passport, Driving licence | dd/mm/yyyy | Carte nationale d'identité |
| SOM / SO | Somalia | generic | Identity card, Passport, Driving licence | dd-mm-yyyy | Kaarka Aqoonsiga |
| LBY / LY | Libya | generic | Identity card, Passport, Driving licence | dd/mm/yyyy | البطاقة الشخصية · al-Bitaqa al-Shakhsiya |
| YEM / YE | Yemen | generic | Identity card, Passport, Driving licence | dd/mm/yyyy | البطاقة الشخصية · al-Bitaqa al-Shakhsiya |
| COL / CO | Colombia | generic | Citizenship card (cédula), Passport, Driving licence | dd-mm-yyyy | Cédula de ciudadanía |
| ALT | Republic of Altis | fictional (Arma map) | card, passport, licence, military card | dd.mm.yyyy |  |
| TNA | Federation of Tanoa | fictional (Arma map) | card, passport, licence, military card | dd.mm.yyyy |  |
| LVN | Republic of Livonia | fictional (Arma map) | card, passport, licence, military card | dd.mm.yyyy |  |
| MLD | Republic of Malden | fictional (Arma map) | card, passport, licence, military card | dd.mm.yyyy |  |

## Full or generic level

« Full » countries: the country's own design (colours, flag as simple bands), paper names and labels in the country's language and in English, numbers in the real format with their check key, the country's personal number, representative first and last names (including names from immigration), real towns with their postal code, common streets, plates, phone numbers in ranges reserved for fiction when they exist. « Generic » countries: names, towns, formats, MRZ code, colours and flag on a neutral template; their number keys are not computed.

## Numbers (all fictional)

- France: NIR with a correct key, birth order **000** (never issued); 2021 card with 9 characters, MRZ « IDFRA ».
- Belgium: national register number and card number with modulo 97 keys (12 digits, ICAO overflow in the MRZ).
- Netherlands: BSN (11-test). Switzerland: AHV number 756 (EAN-13 key). Austria: social insurance number (modulo 11 key).
- Italy: codice fiscale (control character). Spain: DNI and NIE (modulo 23 letter). Poland: PESEL and dowód number (keys).
- United Kingdom: driving licence number built from the name and date of birth. United States: SSN in **9xx**, group 01 to 49 (never issued); licence in the state's format (California, Texas, New York, Florida, Virginia…).
- Germany: 9-character Personalausweis number (German letter set), MRZ « IDD<< ».
- Vehicle registration: plate in the country's format (AB-123-CD, B-AB 123, 1-ABC-123…), VIN with a North-American check digit.

## Game papers, not forgeries

Templates are original drawings: no official layout, no coat of arms or agency emblem, no real security pattern; flags as simple bands. Every paper carries a **SPECIMEN · SIMULATION** band in the country's language and in English, at the bottom. All persons are fictional.

## Adding a country (extension)

The registry is **`CfgComspecCountries`**: one class per country, named after its ISO 3166 alpha-3 code. A third-party addon (depending on `comspec_sse_identity`) adds its class in its `config.cpp` with the same fields (see `addons/identity/CfgComspecCountries.hpp`, e.g. `class FRA`); fields described in `tools/countries/README.md`. In the mod the source is `tools/countries/countries.json` (`python -I tools/countries/gen_countries.py`). Codes are stable and reused by the website.
