"""Guide « Pays des papiers » (docs/guides/pays_des_papiers.md et .en.md), écrit depuis countries.json par gen_countries.py :
le tableau des pays reste toujours celui du registre."""

DOC_TXT = {
    "fr": {
        "title": "# Pays des papiers",
        "other": "English version: [pays_des_papiers.en.md](pays_des_papiers.en.md). Sommaire : [index.md](index.md).",
        "intro": ("Les papiers du SSE sont délivrés par un **pays** : il donne les noms et prénoms, les villes, l'adresse au format du pays, "
                  "les numéros (avec leurs clés), la bande MRZ (code OACI de l'émetteur : « D » pour l'Allemagne), les dates (format du pays), "
                  "la taille (cm, ou pieds-pouces aux États-Unis), les noms des papiers et des autorités dans la langue du pays, et quelques mots "
                  "de cette langue pour l'IA (« Langue : locale » dans l'atelier IA). **Pays réels d'abord** (France, Allemagne, États-Unis…), "
                  "puis les pays fictifs des cartes Arma (Altis, Tanoa, Livonie, Malden), gardés pour que les missions existantes se chargent comme avant."),
        "where": "## Où choisir le pays",
        "whereRows": [
            "**Réglages CBA** (COMSPEC SSE › Identité) : **Pays des papiers** (Auto (selon la carte) ou un pays) et **Pays par défaut des papiers** "
            "(pays pris par Auto sur une carte inconnue ; Altis par défaut). Exemple : France : les civils portent une carte nationale d'identité française.",
            "**Eden** : attribut de l'unité COMSPEC SSE — Papiers › **Pays des papiers** ; attribut du groupe COMSPEC SSE · Papiers du groupe › "
            "**Pays des papiers** (chaque unité).",
            "**Atelier IA** (Eden et Zeus) : liste **Pays des papiers**. Choisir un pays sur une IA dont les papiers sont déjà délivrés les refait dans ce "
            "pays (papiers d'Athena gardés) ; la ligne d'état de la fenêtre le dit. Avec plusieurs IA sélectionnées, chacune suit.",
            "**Donner un document** (Zeus, Eden) : liste des pays, aperçu au format du pays (titre et étiquettes dans sa langue).",
        ],
        "auto": "## « Auto (selon la carte) »",
        "autoIntro": "Carte reconnue → pays. Toute autre carte → réglage « Pays par défaut des papiers ».",
        "mapHead": "| Carte (worldName) | Pays |",
        "tableTitle": "## Les pays",
        "tableHead": "| Code (ISO 3166) | Pays | Niveau | Papiers délivrés | Dates | Pièce courante |",
        "tiers": {"full": "complet", "generic": "générique", "fictional": "fictif (carte Arma)"},
        "fictDocs": "carte, passeport, permis, carte militaire",
        "fullTitle": "## Niveau complet ou générique",
        "fullText": ("Pays « complets » : dessin propre du pays (couleurs, drapeau en simples bandes), noms des papiers et étiquettes dans la langue du "
                     "pays et en anglais, numéros au vrai format avec leur clé, numéro personnel du pays, prénoms et noms représentatifs (y compris "
                     "les noms issus de l'immigration), vraies villes avec leur code postal, rues courantes, plaques, téléphones dans les plages "
                     "réservées à la fiction quand elles existent. Pays « génériques » : noms, villes, formats, code MRZ, couleurs et drapeau, sur un "
                     "gabarit neutre ; les clés de leurs numéros ne sont pas calculées."),
        "fmtTitle": "## Numéros (tous fictifs)",
        "fmtRows": [
            "France : NIR à clé juste, rang de naissance **000** (jamais attribué) ; carte 2021 à 9 caractères, MRZ « IDFRA ».",
            "Belgique : numéro de registre national et numéro de carte à clé modulo 97 (12 chiffres, débordement OACI dans la MRZ).",
            "Pays-Bas : BSN (épreuve des 11). Suisse : n° AVS 756 (clé EAN-13). Autriche : n° d'assurance sociale (clé modulo 11).",
            "Italie : codice fiscale (caractère de contrôle). Espagne : DNI et NIE (lettre modulo 23). Pologne : PESEL et numéro du dowód (clés).",
            "Royaume-Uni : numéro de permis bâti sur le nom et la date de naissance. États-Unis : SSN en **9xx**, groupe 01 à 49 (jamais "
            "attribué) ; permis au format de l'État (Californie, Texas, New York, Floride, Virginie…).",
            "Allemagne : numéro de Personalausweis à 9 caractères (jeu de lettres allemand), MRZ « IDD<< ».",
            "Certificat d'immatriculation : plaque au format du pays (AB-123-CD, B-AB 123, 1-ABC-123…), numéro de châssis (VIN) à clé nord-américaine.",
        ],
        "legalTitle": "## Papiers de jeu, pas des faux",
        "legal": ("Les gabarits sont des dessins originaux : aucun modèle officiel, aucune armoirie ni emblème d'administration, aucun motif de "
                  "sécurité réel ; drapeau en simples bandes. Chaque papier porte un bandeau **SPÉCIMEN · SIMULATION** dans la langue du pays et en "
                  "anglais, en pied de document. Toutes les personnes sont fictives."),
        "extTitle": "## Ajouter un pays (extension)",
        "ext": ("Le registre est **`CfgComspecCountries`** : une classe par pays, nommée par son code ISO 3166 alpha-3. Un addon tiers (qui dépend "
                "de `comspec_sse_identity`) ajoute sa classe dans son `config.cpp` avec les mêmes champs (voir `addons/identity/CfgComspecCountries.hpp`, "
                "par exemple `class FRA`) ; champs décrits dans `tools/countries/README.md`. Dans le mod, la source est `tools/countries/countries.json` "
                "(`python -I tools/countries/gen_countries.py`). Les codes sont stables et repris par le site."),
    },
    "en": {
        "title": "# Country of the papers",
        "other": "Version française : [pays_des_papiers.md](pays_des_papiers.md). Index: [index.en.md](index.en.md).",
        "intro": ("SSE papers are issued by a **country**: it gives the first and last names, the towns, the address in the country's format, the "
                  "numbers (with their check keys), the MRZ (ICAO issuer code: « D » for Germany), dates (country format), height (cm, or feet-inches "
                  "in the United States), names of papers and authorities in the country's language, and a few words of that language for the AI "
                  "(« Language: local » in the AI workshop). **Real countries first** (France, Germany, United States…), then the fictional countries "
                  "of the Arma maps (Altis, Tanoa, Livonia, Malden), kept so existing missions load as before."),
        "where": "## Where to choose the country",
        "whereRows": [
            "**CBA settings** (COMSPEC SSE › Identité): **Country of the papers** (Auto (by map) or a country) and **Default country of the papers** "
            "(used by Auto on an unknown map; Altis by default). Example: France: civilians carry a French national identity card.",
            "**Eden**: unit attribute COMSPEC SSE — Papiers › **Country of the papers**; group attribute COMSPEC SSE · Papiers du groupe › "
            "**Country of the papers** (every unit).",
            "**AI workshop** (Eden and Zeus): **Country of the papers** list. Choosing a country on an AI whose papers were already issued redoes them "
            "in that country (Athena papers kept); the window's status line says so. With several AIs selected, each one follows.",
            "**Give a document** (Zeus, Eden): list of countries, preview in the country's format (title and labels in its language).",
        ],
        "auto": "## « Auto (by map) »",
        "autoIntro": "Known map → country. Any other map → « Default country of the papers » setting.",
        "mapHead": "| Map (worldName) | Country |",
        "tableTitle": "## The countries",
        "tableHead": "| Code (ISO 3166) | Country | Level | Papers issued | Dates | Everyday paper |",
        "tiers": {"full": "full", "generic": "generic", "fictional": "fictional (Arma map)"},
        "fictDocs": "card, passport, licence, military card",
        "fullTitle": "## Full or generic level",
        "fullText": ("« Full » countries: the country's own design (colours, flag as simple bands), paper names and labels in the country's language and "
                     "in English, numbers in the real format with their check key, the country's personal number, representative first and last names "
                     "(including names from immigration), real towns with their postal code, common streets, plates, phone numbers in ranges reserved "
                     "for fiction when they exist. « Generic » countries: names, towns, formats, MRZ code, colours and flag on a neutral template; their "
                     "number keys are not computed."),
        "fmtTitle": "## Numbers (all fictional)",
        "fmtRows": [
            "France: NIR with a correct key, birth order **000** (never issued); 2021 card with 9 characters, MRZ « IDFRA ».",
            "Belgium: national register number and card number with modulo 97 keys (12 digits, ICAO overflow in the MRZ).",
            "Netherlands: BSN (11-test). Switzerland: AHV number 756 (EAN-13 key). Austria: social insurance number (modulo 11 key).",
            "Italy: codice fiscale (control character). Spain: DNI and NIE (modulo 23 letter). Poland: PESEL and dowód number (keys).",
            "United Kingdom: driving licence number built from the name and date of birth. United States: SSN in **9xx**, group 01 to 49 (never "
            "issued); licence in the state's format (California, Texas, New York, Florida, Virginia…).",
            "Germany: 9-character Personalausweis number (German letter set), MRZ « IDD<< ».",
            "Vehicle registration: plate in the country's format (AB-123-CD, B-AB 123, 1-ABC-123…), VIN with a North-American check digit.",
        ],
        "legalTitle": "## Game papers, not forgeries",
        "legal": ("Templates are original drawings: no official layout, no coat of arms or agency emblem, no real security pattern; flags as simple "
                  "bands. Every paper carries a **SPECIMEN · SIMULATION** band in the country's language and in English, at the bottom. All persons "
                  "are fictional."),
        "extTitle": "## Adding a country (extension)",
        "ext": ("The registry is **`CfgComspecCountries`**: one class per country, named after its ISO 3166 alpha-3 code. A third-party addon "
                "(depending on `comspec_sse_identity`) adds its class in its `config.cpp` with the same fields (see "
                "`addons/identity/CfgComspecCountries.hpp`, e.g. `class FRA`); fields described in `tools/countries/README.md`. In the mod the source "
                "is `tools/countries/countries.json` (`python -I tools/countries/gen_countries.py`). Codes are stable and reused by the website."),
    },
}


def build_doc(reg, lang):
    T = DOC_TXT[lang]
    order = sorted(reg, key=lambda k: (reg[k].get("order", 999), k))
    L = [T["title"], "", T["other"], "", T["intro"], "", T["where"], ""]
    L += ["- " + r for r in T["whereRows"]] + ["", T["auto"], "", T["autoIntro"], "", T["mapHead"], "|---|---|"]
    for code in order:
        for m in reg[code].get("maps", []):
            L.append(f"| `{m}` | {reg[code]['name'][lang]} ({code}) |")
    L += ["", T["tableTitle"], "", T["tableHead"], "|---|---|---|---|---|---|"]
    ix = 1 if lang == "fr" else 2
    for code in order:
        c = reg[code]
        if c["tier"] == "fictional":
            names, prim = T["fictDocs"], ""
        else:
            names = ", ".join(c["docNames"][t][ix] for t in c["docTypes"] if t in c.get("docNames", {}))
            prim = c["docNames"].get(c["primary"], ["", "", ""])[0]
        iso = f"{code} / {c['iso2']}" if c.get("iso2") else code
        L.append(f"| {iso} | {c['name'][lang]} | {T['tiers'][c['tier']]} | {names} | {c.get('dateFormat', '')} | {prim} |")
    L += ["", T["fullTitle"], "", T["fullText"], "", T["fmtTitle"], ""] + ["- " + r for r in T["fmtRows"]]
    L += ["", T["legalTitle"], "", T["legal"], "", T["extTitle"], "", T["ext"], ""]
    return "\n".join(L)
