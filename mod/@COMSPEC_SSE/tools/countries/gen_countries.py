#!/usr/bin/env python3
"""Registre des pays des papiers : countries.json -> addons/identity/CfgComspecCountries.hpp (+ examples.json).

countries.json est la SOURCE du registre (mod et site) : un objet par pays, clé = code ISO 3166 alpha-3 (codes hors ISO
pour les pays fictifs d'Arma : ALT, TNA, LVN, MLD). Champs : voir README.md de ce dossier.

  python -I tools/countries/gen_countries.py           # régénère CfgComspecCountries.hpp et examples.json (versionnés)
  python -I tools/countries/gen_countries.py --check   # échoue si un fichier versionné diffère ou si le registre est invalide

docs/guides/pays_des_papiers.md (.en.md) : guide, tableau des pays tiré du registre.
examples.json : numéros, plaques, téléphones et MRZ tirés par sse_numbers.py (graine fixe), relus par PHPUnit avec des
validateurs PHP indépendants (tests/Unit/SseCountryRegistryTest.php).
"""
import json
import os
import random
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)
import sse_numbers as N  # noqa: E402
from country_docs import build_doc  # noqa: E402

sys.stdout.reconfigure(encoding="utf-8")

ROOT = os.path.dirname(os.path.dirname(HERE))
SRC = os.path.join(HERE, "countries.json")
OUT_HPP = os.path.join(ROOT, "addons", "identity", "CfgComspecCountries.hpp")
OUT_EX = os.path.join(HERE, "examples.json")
OUT_DOC_FR = os.path.join(ROOT, "docs", "guides", "pays_des_papiers.md")
OUT_DOC_EN = os.path.join(ROOT, "docs", "guides", "pays_des_papiers.en.md")

TIERS = ("full", "generic", "fictional")
DOC_TYPES = ("id_card", "passport", "driving_license", "residence_permit", "vehicle_registration", "military_id")
DATE_FORMATS = ("dd.mm.yyyy", "dd/mm/yyyy", "mm/dd/yyyy", "dd-mm-yyyy", "yyyy-mm-dd", "dd mm yyyy")
LABEL_KEYS = ("last", "first", "sex", "nat", "dob", "pob", "number", "expires", "issued", "authority", "address")
# Numéro personnel imprimé sur la pièce (sinon gardé pour les fichiers et la légende) : seulement là où la vraie pièce le porte.
PERSONAL_HIDDEN = {"FRA", "USA", "GBR", "AUT", "CHE", "ESP", "DEU", "CAN", "AUS"}
# Nationalité telle qu'imprimée au recto (sinon le code MRZ).
NAT_TEXT = {"FRA": "FRANÇAISE", "DEU": "DEUTSCH", "USA": "USA", "GBR": "BRITISH CITIZEN", "BEL": "BELGE · BELG", "AUT": "ÖSTERREICH",
            "CHE": "SCHWEIZ · SUISSE", "NLD": "NEDERLANDSE", "ITA": "ITALIANA", "ESP": "ESPAÑOLA", "POL": "POLSKIE", "LUX": "LUXEMBOURGEOISE",
            "PRT": "PORTUGUESA"}


def latin(s):
    """Texte affichable par les polices du jeu (latin étendu, cyrillique, ponctuation courante)."""
    for ch in s:
        o = ord(ch)
        if o < 0x250 or 0x400 <= o < 0x530 or 0x1E00 <= o < 0x1F00 or ch in "·—–’‘“”…«»€":
            continue
        return False
    return True


# Bandeau des papiers : forme courte quand la forme officielle ne tient pas sur une ligne.
NAME_DOC = {"BEL": "BELGIQUE · BELGIË · BELGIEN", "CHE": "SCHWEIZ · SUISSE · SVIZZERA", "GBR": "UNITED KINGDOM",
            "LUX": "GRAND-DUCHÉ DE LUXEMBOURG"}


def doc_name(c):
    """Nom du pays au bandeau des papiers : forme officielle si elle s'affiche, sinon sa translittération, sinon l'anglais."""
    if c["code"] in NAME_DOC:
        return NAME_DOC[c["code"]]
    native = c["name"].get("native", "") or c["name"]["en"]
    if len(native) > 34:
        native = c["name"]["en"]
    if latin(native):
        return native
    parts = [p.strip() for p in native.split("·")]
    lat = [p for p in parts if p and latin(p)]
    return lat[0] if lat else c["name"]["en"]


def lat_or(s, fallback):
    return s if latin(s) else fallback


def validate(reg):
    err = []
    seen2, seen3, seen_maps = {}, set(), {}
    for code, c in reg.items():
        where = code + " : "
        if c.get("code") != code:
            err.append(where + "code différent de la clé")
        if c.get("tier") not in TIERS:
            err.append(where + "tier inconnu")
        fict = c.get("tier") == "fictional"
        if not fict:
            if len(code) != 3 or not code.isalpha() or not code.isupper():
                err.append(where + "code alpha-3 invalide")
            iso2 = c.get("iso2", "")
            if len(iso2) != 2 or not iso2.isalpha() or not iso2.isupper():
                err.append(where + "iso2 invalide")
            if iso2 in seen2:
                err.append(where + "iso2 en double avec " + seen2[iso2])
            seen2[iso2] = code
        seen3.add(code)
        for k in ("fr", "en"):
            if not c.get("name", {}).get(k):
                err.append(where + "nom " + k + " manquant")
        dts = c.get("docTypes", [])
        if not dts or any(t not in DOC_TYPES for t in dts):
            err.append(where + "docTypes invalides")
        if c.get("primary") not in dts:
            err.append(where + "primary hors docTypes")
        if c.get("dateFormat") not in DATE_FORMATS:
            err.append(where + "dateFormat inconnu")
        if len(c.get("first", [])) < 10 or len(c.get("firstF", [])) < 10 or len(c.get("last", [])) < 12:
            err.append(where + "réservoirs de noms trop courts")
        if c.get("lastF") and len(c["lastF"]) != len(c["last"]):
            err.append(where + "lastF pas aligné sur last")
        if not c.get("cities"):
            err.append(where + "villes manquantes")
        if not fict:
            for t in dts:
                if t not in c.get("numbers", {}):
                    err.append(where + "numéro manquant pour " + t)
                if t not in c.get("docNames", {}) or len(c["docNames"][t]) != 3:
                    err.append(where + "docNames[" + t + "] manquant")
            for spec in c.get("numbers", {}).values():
                if spec.startswith("scheme:") and spec[7:] not in N.SCHEMES:
                    err.append(where + "schéma inconnu " + spec)
            for m in c.get("maps", []):
                if m in seen_maps:
                    err.append(where + "carte " + m + " déjà prise par " + seen_maps[m])
                seen_maps[m] = code
            for k in LABEL_KEYS:
                if not c.get("labels", {}).get(k):
                    err.append(where + "étiquette manquante " + k)
            if len(c.get("idCheck", [])) != 2:
                err.append(where + "idCheck [langue, anglais] manquant")
            if "state" in c.get("numbers", {}).values():
                for r, rv in c.get("regions", {}).items():
                    if "dl" not in rv:
                        err.append(where + "région " + r + " sans format de permis")
    return err


# ------------------------------------------------------------------------------------------------- écriture config
def q(s):
    return '"' + str(s).replace('"', '""') + '"'


def arr(lst):
    return "{" + ", ".join(arr(x) if isinstance(x, list) else (str(x) if isinstance(x, (int, float)) and not isinstance(x, bool) else q(x)) for x in lst) + "}"


def cfg_country(code, c):
    fict = c["tier"] == "fictional"
    L = []
    w = L.append
    nm = c["name"]
    short = c.get("short", {"fr": nm["fr"], "en": nm["en"]})
    flag = c.get("flag", {"kind": "h", "colors": [c["colors"]["main"]]})
    lang = c.get("language", {})
    phone = c.get("phone", {})
    w(f"    class {code} {{")
    w(f"        code = {q(code)}; iso2 = {q(c.get('iso2', ''))}; mrz = {q(c.get('mrz', code))}; tier = {q(c['tier'])}; order = {c.get('order', 999)};")
    w(f"        name = {q(nm['fr'])}; nameEn = {q(nm['en'])}; nameNative = {q(lat_or(nm.get('native', ''), nm['en']))}; nameDoc = {q(doc_name(c))};")
    w(f"        shortFr = {q(short['fr'])}; shortEn = {q(short['en'])};")
    w(f"        zone = {q(c.get('zone', ''))}; colorMain = {q(c['colors']['main'])}; colorAccent = {q(c['colors']['accent'])};")
    w(f"        flagKind = {q(flag['kind'])}; flagColors[] = {arr(flag['colors'])};")
    w(f"        languages[] = {arr(c.get('languages', []))}; languageName = {q(lang.get('fr', ''))}; languageNameEn = {q(lang.get('en', ''))};")
    w(f"        speech[] = {arr([s for s in c.get('speech', []) if latin(s)])};")
    w(f"        dateFormat = {q(c.get('dateFormat', 'dd.mm.yyyy'))}; height = {q(c.get('height', 'cm'))}; currency = {q(c.get('currency', ''))};")
    w(f"        phone = {q(phone.get('pattern', ''))}; phoneNote = {q(phone.get('note', ''))}; postal = {q(c.get('postal', ''))};")
    w(f"        address = {q(c.get('address', '{no} {street}, {city}'))}; plate = {q(c.get('plate', ''))};")
    w(f"        streets[] = {arr(c.get('streets', []))};")
    w(f"        cities[] = {arr([[r[0], r[1] if len(r) > 1 else '', r[2] if len(r) > 2 else ''] for r in c['cities']])};")
    w(f"        docTypes[] = {arr(c['docTypes'])}; primary = {q(c['primary'])};")
    pers = "personal" in c.get("numbers", {}) and code not in PERSONAL_HIDDEN
    w(f"        printPersonal = {int(pers)}; natText = {q(NAT_TEXT.get(code, ''))}; maps[] = {arr([m.lower() for m in c.get('maps', [])])};")
    w(f"        first[] = {arr(c['first'])};")
    w(f"        firstF[] = {arr(c['firstF'])};")
    w(f"        last[] = {arr(c['last'])};")
    w(f"        lastF[] = {arr(c.get('lastF', []))}; lastFSuffix = {q(c.get('lastFSuffix', ''))};")
    idc = c.get("idCheck", ["", ""])
    w(f"        idCheck[] = {arr([lat_or(idc[0], idc[1]), idc[1]])};")
    if fict:
        w(f"        numberFormat = {q(c['numbers']['*'])}; authority = {q(c['authorities']['*'])}; validityYears = {c['validity']['*']};")
    else:
        w(f"        numberFormat = \"\"; authority = {q(c.get('authorities', {}).get(c['primary'], ''))}; validityYears = 10;")
        w("        class numbers { " + " ".join(f"{k} = {q(v)};" for k, v in c["numbers"].items()) + " };")
        w("        class mrzCodes { " + " ".join(f"{k} = {q(v)};" for k, v in c.get("mrzCodes", {}).items()) + " };")
        w("        class docNames {")
        for k, v in c["docNames"].items():
            w(f"            {k}[] = {arr([lat_or(v[0], v[2]), v[1], v[2]])};")
        w("        };")
        w("        class authorities { " + " ".join(f"{k} = {q(lat_or(v, ''))};" for k, v in c.get("authorities", {}).items()) + " };")
        w("        class police {")
        for k, v in c.get("police", {}).items():
            w(f"            {k}[] = {arr([lat_or(v[0], v[2]), v[1], v[2]])};")
        w("        };")
        w("        class labels { " + " ".join(f"{k} = {q(lat_or(v, ''))};" for k, v in c.get("labels", {}).items()) + " };")
        w("        class validity { " + " ".join(f"{k} = {int(v)};" for k, v in c.get("validity", {}).items()) + " };")
        regs = c.get("regions", {})
        if regs:
            w("        class regions {")
            for r, rv in regs.items():
                inner = " ".join(f"{k} = {q(v)};" for k, v in rv.items())
                w(f"            class r_{''.join(ch for ch in r if ch.isalnum())} {{ code = {q(r)}; {inner} }};")
            w("        };")
    w("    };")
    return "\n".join(L)


HEADER = """// GÉNÉRÉ par tools/countries/gen_countries.py depuis tools/countries/countries.json : ne pas modifier à la main.
/*
    Registre des PAYS DES PAPIERS (lu par comspec_sse_fnc_idCountries). Une classe par pays : code ISO 3166 alpha-3
    (codes stables, repris par le site : FRA, DEU, USA…), alpha-2 (iso2), code MRZ OACI (Allemagne « D »).
      tier       full (pays réel complet) | generic (pays réel, contenu générique) | fictional (pays d'Arma : ALT, TNA, LVN, MLD)
      order      ordre des listes (pays réels d'abord)     maps[]  cartes (worldName) qui choisissent ce pays en « Auto »
      numbers    motifs des numéros par type (# chiffre, @ lettre, * alphanumérique, & jeu allemand, {C} clé OACI, {R} région,
                 {A} indicatif, {Y} année de naissance) ou « scheme:<nom> » (fn_idNumbers) ; « state » : format de la région
      docNames   [langue du pays, français, anglais]     labels  étiquettes des champs dans la langue du pays
      cities[]   [ville, code postal, code de région]    regions  classes r_<code> : name (et dl, id, plate, authority, area)
    Extension : un addon tiers peut ajouter un pays (classe de même forme) dans son propre config.cpp ; voir le README de
    l'extension d'exemple. Papiers de JEU : mention SPÉCIMEN · SIMULATION, aucun modèle officiel ni emblème reproduit.
*/
class CfgComspecCountries {
"""


def build_hpp(reg):
    codes = sorted(reg, key=lambda k: (reg[k].get("order", 999), k))
    return HEADER + "\n".join(cfg_country(k, reg[k]) for k in codes) + "\n};\n"


# ------------------------------------------------------------------------------------------------- exemples
def examples(reg):
    rnd = random.Random(20261111)
    out = {}
    for code in sorted(reg):
        c = reg[code]
        if c["tier"] == "fictional":
            continue
        ex = {"numbers": {}, "plates": [], "phones": [], "mrz": {}}
        for i in range(4):
            sex = "F" if i % 2 else "M"
            city = rnd.choice(c["cities"])
            region = city[2] if len(city) > 2 else ""
            ctx = {"sex": sex, "dob": "%04d-%02d-%02d" % (rnd.randrange(1960, 2008), rnd.randrange(1, 13), rnd.randrange(1, 29)),
                   "last": rnd.choice(c["last"]), "first": rnd.choice(c["firstF" if sex == "F" else "first"]), "pob": city[0], "region": region,
                   "area": c.get("regions", {}).get(region, {}).get("area", "555")}
            for t, spec in c.get("numbers", {}).items():
                if spec == "state":
                    rv = c.get("regions", {}).get(region) or next(iter(c["regions"].values()))
                    spec = rv.get("dl" if t == "driving_license" else "id", rv.get("dl"))
                row = {"spec": c["numbers"][t], "value": N.make(spec, rnd, ctx)}
                if spec.startswith("scheme:"):
                    row["ctx"] = {k: ctx[k] for k in ("sex", "dob", "last", "first", "pob")}
                ex["numbers"].setdefault(t, []).append(row)
            if c.get("plate"):
                ex["plates"].append(N.pattern(c["plate"], rnd, ctx))
            if c.get("phone", {}).get("pattern"):
                ex["phones"].append(N.pattern(c["phone"]["pattern"], rnd, ctx))
            if i == 0:
                num = ex["numbers"].get(c["primary"], [{"value": "X"}])[-1]["value"]
                doc = {"number": num, "last": ctx["last"], "first": ctx["first"], "sex": sex, "dob": ctx["dob"], "expires": "2031-06-30",
                       "issuer": c.get("mrz", code), "nat": c.get("mrz", code)}
                ex["mrz"]["td1"] = N.mrz_td1(dict(doc, code=c.get("mrzCodes", {}).get("id_card", "I<")))
                ex["mrz"]["td3"] = N.mrz_td3(dict(doc, number=N.pattern("#########", rnd)))
        out[code] = ex
    return json.dumps(out, ensure_ascii=False, indent=1, sort_keys=True) + "\n"


def main():
    reg = json.load(open(SRC, encoding="utf-8"))
    err = validate(reg)
    if err:
        print("\n".join(err))
        sys.exit(1)
    hpp = build_hpp(reg)
    ex = examples(reg)
    outs = ((OUT_HPP, hpp), (OUT_EX, ex), (OUT_DOC_FR, build_doc(reg, "fr")), (OUT_DOC_EN, build_doc(reg, "en")))
    if "--check" in sys.argv:
        bad = []
        for path, want in outs:
            have = open(path, encoding="utf-8").read() if os.path.exists(path) else ""
            if have.replace("\r\n", "\n") != want:
                bad.append(path)
        if bad:
            print("Périmé : " + ", ".join(bad) + " — lancez python -I tools/countries/gen_countries.py")
            sys.exit(1)
        print(f"Registre à jour ({len(reg)} pays).")
        return
    for path, txt in outs:
        with open(path, "w", encoding="utf-8", newline="\n") as fh:
            fh.write(txt)
    full = sum(1 for c in reg.values() if c["tier"] == "full")
    gen = sum(1 for c in reg.values() if c["tier"] == "generic")
    print(f"{len(reg)} pays : {full} complets, {gen} génériques, {len(reg) - full - gen} fictifs.")


if __name__ == "__main__":
    main()
