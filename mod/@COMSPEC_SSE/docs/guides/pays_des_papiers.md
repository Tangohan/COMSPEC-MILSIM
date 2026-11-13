# Pays des papiers

English version: [pays_des_papiers.en.md](pays_des_papiers.en.md). Sommaire : [index.md](index.md).

Les papiers du SSE sont délivrés par un **pays** : il donne les noms et prénoms, les villes, l'adresse au format du pays, les numéros (avec leurs clés), la bande MRZ (code OACI de l'émetteur : « D » pour l'Allemagne), les dates (format du pays), la taille (cm, ou pieds-pouces aux États-Unis), les noms des papiers et des autorités dans la langue du pays, et quelques mots de cette langue pour l'IA (« Langue : locale » dans l'atelier IA). **Pays réels d'abord** (France, Allemagne, États-Unis…), puis les pays fictifs des cartes Arma (Altis, Tanoa, Livonie, Malden), gardés pour que les missions existantes se chargent comme avant.

## Où choisir le pays

- **Réglages CBA** (COMSPEC SSE › Identité) : **Pays des papiers** (Auto (selon la carte) ou un pays) et **Pays par défaut des papiers** (pays pris par Auto sur une carte inconnue ; Altis par défaut). Exemple : France : les civils portent une carte nationale d'identité française.
- **Eden** : attribut de l'unité COMSPEC SSE — Papiers › **Pays des papiers** ; attribut du groupe COMSPEC SSE · Papiers du groupe › **Pays des papiers** (chaque unité).
- **Atelier IA** (Eden et Zeus) : liste **Pays des papiers**. Choisir un pays sur une IA dont les papiers sont déjà délivrés les refait dans ce pays (papiers d'Athena gardés) ; la ligne d'état de la fenêtre le dit. Avec plusieurs IA sélectionnées, chacune suit.
- **Donner un document** (Zeus, Eden) : liste des pays, aperçu au format du pays (titre et étiquettes dans sa langue).

## « Auto (selon la carte) »

Carte reconnue → pays. Toute autre carte → réglage « Pays par défaut des papiers ».

| Carte (worldName) | Pays |
|---|---|
| `spe_mortain` | France (FRA) |
| `spe_normandy` | France (FRA) |
| `gm_weferlingen_summer` | Allemagne (DEU) |
| `gm_weferlingen_winter` | Allemagne (DEU) |
| `rosche` | Allemagne (DEU) |
| `napf` | Suisse (CHE) |
| `napfwinter` | Suisse (CHE) |
| `bornholm` | Danemark (DNK) |
| `vt7` | Finlande (FIN) |
| `fallujah` | Irak (IRQ) |
| `takistan` | Afghanistan (AFG) |
| `zargabad` | Afghanistan (AFG) |
| `lythium` | Afghanistan (AFG) |
| `tem_anizay` | Afghanistan (AFG) |
| `kunduz` | Afghanistan (AFG) |
| `tem_kujari` | Mali (MLI) |
| `altis` | République d'Altis (ALT) |
| `stratis` | République d'Altis (ALT) |
| `tanoa` | Fédération de Tanoa (TNA) |
| `enoch` | République de Livonie (LVN) |
| `malden` | République de Malden (MLD) |

## Les pays

| Code (ISO 3166) | Pays | Niveau | Papiers délivrés | Dates | Pièce courante |
|---|---|---|---|---|---|
| FRA / FR | France | complet | Carte nationale d'identité, Passeport, Permis de conduire, Titre de séjour, Carte grise (certificat d'immatriculation), Carte d'identité militaire | dd/mm/yyyy | Carte nationale d'identité |
| DEU / DE | Allemagne | complet | Carte d'identité, Passeport, Permis de conduire, Titre de séjour, Certificat d'immatriculation (partie I), Carte d'identité militaire | dd.mm.yyyy | Personalausweis |
| USA / US | États-Unis | complet | Carte d'identité d'État, Passeport, Permis de conduire, Carte de résident permanent (green card), Certificat d'immatriculation, Carte d'identité militaire (CAC) | mm/dd/yyyy | Driver License |
| GBR / GB | Royaume-Uni | complet | Passeport, Permis de conduire, Titre de séjour biométrique, Certificat d'immatriculation (V5C), Carte d'identité militaire (MOD Form 90) | dd.mm.yyyy | Driving Licence |
| BEL / BE | Belgique | complet | Carte d'identité, Passeport, Permis de conduire, Titre de séjour, Certificat d'immatriculation, Carte d'identité militaire | dd.mm.yyyy | Carte d'identité · Identiteitskaart · Personalausweis |
| AUT / AT | Autriche | complet | Carte d'identité, Passeport, Permis de conduire, Titre de séjour, Certificat d'immatriculation, Carte d'identité militaire | dd.mm.yyyy | Personalausweis |
| CHE / CH | Suisse | complet | Carte d'identité, Passeport, Permis de conduire, Titre de séjour pour étrangers, Permis de circulation, Livret de service militaire | dd.mm.yyyy | Identitätskarte · Carte d'identité · Carta d'identità |
| NLD / NL | Pays-Bas | complet | Carte d'identité, Passeport, Permis de conduire, Titre de séjour, Certificat d'immatriculation, Carte militaire (Défense) | dd mm yyyy | Identiteitskaart |
| LUX / LU | Luxembourg | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Carte d'identité |
| ITA / IT | Italie | complet | Carte d'identité électronique, Passeport, Permis de conduire, Titre de séjour, Certificat d'immatriculation, Carte d'identité militaire | dd/mm/yyyy | Carta d'identità elettronica |
| ESP / ES | Espagne | complet | Carte nationale d'identité (DNI), Passeport, Permis de conduire, Carte d'identité d'étranger (TIE), Certificat d'immatriculation, Carte d'identité militaire | dd mm yyyy | Documento Nacional de Identidad |
| PRT / PT | Portugal | générique | Carte de citoyen, Passeport, Permis de conduire | dd mm yyyy | Cartão de Cidadão |
| POL / PL | Pologne | complet | Carte d'identité, Passeport, Permis de conduire, Titre de séjour, Certificat d'immatriculation, Carte d'identité militaire | dd.mm.yyyy | Dowód osobisty |
| CZE / CZ | Tchéquie | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Občanský průkaz |
| SVK / SK | Slovaquie | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Občiansky preukaz |
| HUN / HU | Hongrie | générique | Carte d'identité, Passeport, Permis de conduire | dd mm yyyy | Személyazonosító igazolvány |
| ROU / RO | Roumanie | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Carte de identitate |
| BGR / BG | Bulgarie | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Lichna karta |
| GRC / GR | Grèce | générique | Carte d'identité, Passeport, Permis de conduire, Carte d'identité militaire | dd.mm.yyyy | Deltío Astynomikís Taftótitas |
| TUR / TR | Turquie | générique | Carte d'identité, Passeport, Permis de conduire, Carte d'identité militaire | dd.mm.yyyy | T.C. Kimlik Kartı |
| UKR / UA | Ukraine | générique | Carte d'identité, Passeport international, Permis de conduire, Livret militaire | dd.mm.yyyy | Pasport hromadianyna Ukrainy (ID-kartka) |
| RUS / RU | Russie | générique | Passeport intérieur, Passeport international, Permis de conduire, Livret militaire | dd.mm.yyyy | Pasport grazhdanina Rossiiskoi Federatsii |
| BLR / BY | Biélorussie | générique | Carte d'identité, Passeport, Permis de conduire, Livret militaire | dd.mm.yyyy | Identifikatsionnaya karta |
| DNK / DK | Danemark | générique | Carte d'identité, Passeport, Permis de conduire, Carte d'identité militaire | dd.mm.yyyy | Kørekort |
| SWE / SE | Suède | générique | Carte nationale d'identité, Passeport, Permis de conduire, Carte d'identité militaire | yyyy-mm-dd | Nationellt identitetskort |
| NOR / NO | Norvège | générique | Carte nationale d'identité, Passeport, Permis de conduire, Carte d'identité militaire | dd.mm.yyyy | Nasjonalt ID-kort |
| FIN / FI | Finlande | générique | Carte d'identité, Passeport, Permis de conduire, Livret militaire | dd.mm.yyyy | Henkilökortti |
| EST / EE | Estonie | générique | Carte d'identité, Passeport, Permis de conduire, Carte de militaire | dd.mm.yyyy | Isikutunnistus (ID-kaart) |
| LVA / LV | Lettonie | générique | Carte d'identité, Passeport, Permis de conduire, Carte de militaire | dd.mm.yyyy | Personas apliecība (eID karte) |
| LTU / LT | Lituanie | générique | Carte d'identité, Passeport, Permis de conduire, Carte de militaire | yyyy-mm-dd | Asmens tapatybės kortelė |
| HRV / HR | Croatie | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Osobna iskaznica |
| SRB / RS | Serbie | générique | Carte d'identité, Passeport, Permis de conduire | dd.mm.yyyy | Lična karta |
| CAN / CA | Canada | générique | Passeport, Permis de conduire | yyyy-mm-dd | Permis de conduire / Driver's Licence |
| AUS / AU | Australie | générique | Passeport, Permis de conduire | dd/mm/yyyy | Driver Licence |
| ISR / IL | Israël | générique | Carte d'identité, Passeport, Permis de conduire, Carte militaire | dd/mm/yyyy | תעודת זהות · Teudat Zehut |
| IRQ / IQ | Irak | générique | Carte nationale, Passeport, Permis de conduire | dd/mm/yyyy | البطاقة الوطنية · al-Bitaqa al-Wataniya |
| AFG / AF | Afghanistan | générique | Tazkira (carte d'identité), Passeport, Permis de conduire | yyyy-mm-dd | تذکره · Tazkira |
| SYR / SY | Syrie | générique | Carte d'identité, Passeport, Permis de conduire | dd/mm/yyyy | البطاقة الشخصية · al-Bitaqa al-Shakhsiya |
| MLI / ML | Mali | générique | Carte nationale d'identité, Passeport, Permis de conduire | dd/mm/yyyy | Carte nationale d'identité |
| NER / NE | Niger | générique | Carte nationale d'identité, Passeport, Permis de conduire | dd/mm/yyyy | Carte nationale d'identité |
| TCD / TD | Tchad | générique | Carte nationale d'identité, Passeport, Permis de conduire | dd/mm/yyyy | Carte nationale d'identité |
| SOM / SO | Somalie | générique | Carte d'identité, Passeport, Permis de conduire | dd-mm-yyyy | Kaarka Aqoonsiga |
| LBY / LY | Libye | générique | Carte d'identité, Passeport, Permis de conduire | dd/mm/yyyy | البطاقة الشخصية · al-Bitaqa al-Shakhsiya |
| YEM / YE | Yémen | générique | Carte d'identité, Passeport, Permis de conduire | dd/mm/yyyy | البطاقة الشخصية · al-Bitaqa al-Shakhsiya |
| COL / CO | Colombie | générique | Carte d'identité (cédula), Passeport, Permis de conduire | dd-mm-yyyy | Cédula de ciudadanía |
| ALT | République d'Altis | fictif (carte Arma) | carte, passeport, permis, carte militaire | dd.mm.yyyy |  |
| TNA | Fédération de Tanoa | fictif (carte Arma) | carte, passeport, permis, carte militaire | dd.mm.yyyy |  |
| LVN | République de Livonie | fictif (carte Arma) | carte, passeport, permis, carte militaire | dd.mm.yyyy |  |
| MLD | République de Malden | fictif (carte Arma) | carte, passeport, permis, carte militaire | dd.mm.yyyy |  |

## Niveau complet ou générique

Pays « complets » : dessin propre du pays (couleurs, drapeau en simples bandes), noms des papiers et étiquettes dans la langue du pays et en anglais, numéros au vrai format avec leur clé, numéro personnel du pays, prénoms et noms représentatifs (y compris les noms issus de l'immigration), vraies villes avec leur code postal, rues courantes, plaques, téléphones dans les plages réservées à la fiction quand elles existent. Pays « génériques » : noms, villes, formats, code MRZ, couleurs et drapeau, sur un gabarit neutre ; les clés de leurs numéros ne sont pas calculées.

## Numéros (tous fictifs)

- France : NIR à clé juste, rang de naissance **000** (jamais attribué) ; carte 2021 à 9 caractères, MRZ « IDFRA ».
- Belgique : numéro de registre national et numéro de carte à clé modulo 97 (12 chiffres, débordement OACI dans la MRZ).
- Pays-Bas : BSN (épreuve des 11). Suisse : n° AVS 756 (clé EAN-13). Autriche : n° d'assurance sociale (clé modulo 11).
- Italie : codice fiscale (caractère de contrôle). Espagne : DNI et NIE (lettre modulo 23). Pologne : PESEL et numéro du dowód (clés).
- Royaume-Uni : numéro de permis bâti sur le nom et la date de naissance. États-Unis : SSN en **9xx**, groupe 01 à 49 (jamais attribué) ; permis au format de l'État (Californie, Texas, New York, Floride, Virginie…).
- Allemagne : numéro de Personalausweis à 9 caractères (jeu de lettres allemand), MRZ « IDD<< ».
- Certificat d'immatriculation : plaque au format du pays (AB-123-CD, B-AB 123, 1-ABC-123…), numéro de châssis (VIN) à clé nord-américaine.

## Papiers de jeu, pas des faux

Les gabarits sont des dessins originaux : aucun modèle officiel, aucune armoirie ni emblème d'administration, aucun motif de sécurité réel ; drapeau en simples bandes. Chaque papier porte un bandeau **SPÉCIMEN · SIMULATION** dans la langue du pays et en anglais, en pied de document. Toutes les personnes sont fictives.

## Ajouter un pays (extension)

Le registre est **`CfgComspecCountries`** : une classe par pays, nommée par son code ISO 3166 alpha-3. Un addon tiers (qui dépend de `comspec_sse_identity`) ajoute sa classe dans son `config.cpp` avec les mêmes champs (voir `addons/identity/CfgComspecCountries.hpp`, par exemple `class FRA`) ; champs décrits dans `tools/countries/README.md`. Dans le mod, la source est `tools/countries/countries.json` (`python -I tools/countries/gen_countries.py`). Les codes sont stables et repris par le site.
