#!/usr/bin/env python3
"""Vérification hors jeu de la MRZ du SSE : portage ligne à ligne de fn_idMrzCheck.sqf et fn_idMrz.sqf (écriture et
relecture TD1 / TD3), contrôlé sur le spécimen ICAO 9303 (passeport UTO, ERIKSSON ANNA MARIA).

  python tools/check_id_mrz.py      # 0 si tout passe
Même jeu d'essais que tools/test_id_mrz.sqf (à lancer en jeu, console de débogage).
"""
import sys
import unicodedata


def check(s):
    w = (7, 3, 1)
    total = 0
    for i, ch in enumerate(s.upper()):
        c = ord(ch)
        v = c - 48 if 48 <= c <= 57 else (c - 55 if 65 <= c <= 90 else 0)
        total += v * w[i % 3]
    return total % 10


def ck(s):
    return str(check(s))


def pad(s, n):
    return (s[:n] + "<" * n)[:n]


TL = {"Æ": "AE", "Œ": "OE", "ß": "SS", "Ł": "L", "Đ": "D"}


def clean(s):
    """Même table que fn_idMrz.sqf et SseMrzService (site) : accents retirés, tout autre caractère -> « < »."""
    out = []
    for ch in s.strip():
        c = ord(ch)
        if 97 <= c <= 122:
            c -= 32
        if 65 <= c <= 90 or 48 <= c <= 57 or c == 60:
            out.append(chr(c))
            continue
        up = ch.upper() if ch != "ß" else ch
        if up in TL:
            out.append(TL[up])
            continue
        base = unicodedata.normalize("NFD", up)[0]
        out.append(base if "A" <= base <= "Z" else "<")
    return "".join(out)


def name_field(last, first):
    norm = lambda t: "<".join(x for x in clean(t).split("<") if x)
    l, f = norm(last), norm(first)
    return l + "<<" + f if f else l


def ymd(d):
    p = d.split("-")
    return "<<<<<<" if len(p) < 3 else p[0][2:4] + p[1] + p[2]


# Codes MRZ OACI des pays réels stylisés (fn_idRealCountries / SseIdRealCountryCatalog) : seul DEU diffère du code.
REAL_MRZ = {"DEU": "D"}


def mrz(doc):
    t = doc.get("type", "id_card")
    if t not in ("id_card", "passport", "driving_license"):
        return []
    cc = doc.get("country", "ALT")
    iss = doc.get("mrzIssuer", "") or REAL_MRZ.get(cc, cc)
    issuer = pad(clean(iss), 3)
    nat_raw = doc.get("nat", iss)
    if nat_raw == cc:
        nat_raw = iss
    nat = pad(clean(nat_raw), 3)
    num = pad(clean(doc.get("mrzNumber", doc.get("number", ""))), 9)
    num_ck = ck(num)
    if doc.get("mrzFlaw") == "check":
        num_ck = str((int(num_ck) + 3) % 10)
    dob = ymd(doc.get("mrzDob", doc.get("dob", "")))
    exp = ymd(doc.get("expires", ""))
    sex = doc.get("sex", "<") if doc.get("sex") in ("M", "F") else "<"
    name = name_field(doc.get("mrzLast", doc.get("last", "")), doc.get("first", ""))
    if t in ("id_card", "driving_license"):
        l1 = pad(("D<" if t == "driving_license" else "I<") + issuer + num + num_ck, 30)
        l2 = pad(dob + ck(dob) + sex + exp + ck(exp) + nat, 29)
        comp = l1[5:30] + l2[0:7] + l2[8:15] + l2[18:29]
        return [l1, l2 + ck(comp), pad(name, 30)]
    l1 = pad("P<" + issuer + name, 44)
    pn = pad(clean(doc.get("personal", "")), 14)
    l2 = num + num_ck + nat + dob + ck(dob) + sex + exp + ck(exp) + pn + ("<" if not pn.replace("<", "") else ck(pn))
    comp = l2[0:10] + l2[13:20] + l2[21:43]
    return [l1, l2 + ck(comp)]


def parse(lines):
    lines = [x.upper() for x in lines]
    err = []
    if len(lines) == 3 and all(len(x) == 30 for x in lines):
        l1, l2, l3 = lines
        if ck(l1[5:14]) != l1[14]:
            err.append("number")
        if ck(l2[0:6]) != l2[6]:
            err.append("dob")
        if ck(l2[8:14]) != l2[14]:
            err.append("expiry")
        if ck(l1[5:30] + l2[0:7] + l2[8:15] + l2[18:29]) != l2[29]:
            err.append("composite")
        return err
    if len(lines) == 2 and all(len(x) == 44 for x in lines):
        l2 = lines[1]
        if ck(l2[0:9]) != l2[9]:
            err.append("number")
        if ck(l2[13:19]) != l2[19]:
            err.append("dob")
        if ck(l2[21:27]) != l2[27]:
            err.append("expiry")
        pn, pc = l2[28:42], l2[42]
        if not (ck(pn) == pc or (pc == "<" and pn.replace("<", "") == "")):
            err.append("personal")
        if ck(l2[0:10] + l2[13:20] + l2[21:43]) != l2[43]:
            err.append("composite")
        return err
    return ["format"]


def main():
    fails = []
    for s, want in (("L898902C3", 6), ("740812", 2), ("120415", 9), ("ZE184226B<<<<<", 1),
                    ("L898902C3674081221204159ZE184226B<<<<<1", 0)):
        if check(s) != want:
            fails.append(f"{s} -> {check(s)} (attendu {want})")
    spec = {"type": "passport", "country": "UTO", "nat": "UTO", "number": "L898902C3", "last": "Eriksson",
            "first": "Anna Maria", "sex": "F", "dob": "1974-08-12", "expires": "2012-04-15", "personal": "ZE184226B"}
    l = mrz(spec)
    if l != ["P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<", "L898902C36UTO7408122F1204159ZE184226B<<<<<10"]:
        fails.append(f"TD3 : {l}")
    if parse(l):
        fails.append(f"TD3 relu : {parse(l)}")
    card = {"type": "id_card", "country": "ALT", "nat": "ALT", "number": "AL1234567", "last": "Kostas-Andréou",
            "first": "Éléni", "sex": "F", "dob": "1990-01-31", "expires": "2031-06-30"}
    l = mrz(card)
    if parse(l):
        fails.append(f"TD1 relu : {parse(l)} {l}")
    if l[2] != pad("KOSTAS<ANDREOU<<ELENI", 30):
        fails.append(f"TD1 nom : {l[2]}")
    card["mrzFlaw"] = "check"
    if "number" not in parse(mrz(card)):
        fails.append("faux non détecté")
    lic = {**card, "type": "driving_license", "number": "LV1234567", "last": "Wiśniewski", "first": "Łukasz Paweł", "mrzFlaw": ""}
    l = mrz(lic)
    if parse(l) or l[0][:2] != "D<" or l[2] != pad("WISNIEWSKI<<LUKASZ<PAWEL", 30):
        fails.append(f"permis : {l}")
    empty = mrz({**spec, "personal": ""})
    if empty[1][42] != "<" or parse(empty):
        fails.append(f"TD3 sans numéro personnel : {empty}")
    # Pays réels stylisés : code OACI de l'émetteur et de la nationalité (Allemagne « D<< »).
    de = {"type": "passport", "country": "DEU", "nat": "DEU", "number": "DEK482913", "last": "Müller", "first": "Jürgen",
          "sex": "M", "dob": "1985-03-02", "expires": "2034-03-01"}
    l_de = mrz(de)
    if not l_de[0].startswith("P<D<<MULLER<<JURGEN") or l_de[1][10:13] != "D<<" or parse(l_de):
        fails.append(f"passeport allemand : {l_de}")
    de_card = mrz({**de, "type": "id_card"})
    if not de_card[0].startswith("I<D<<DEK482913") or de_card[1][15:18] != "D<<" or parse(de_card):
        fails.append(f"carte allemande : {de_card}")
    for code in ("GBR", "GRC", "USA", "AUT", "COL", "FRA", "IRQ", "SYR", "AFG", "RUS", "UKR", "POL", "BEL", "ESP"):
        x = mrz({**de, "country": code, "nat": code, "mrzIssuer": code, "number": code[:2] + "K482913"})
        if x[0][2:5] != code or x[1][10:13] != code or parse(x):
            fails.append(f"passeport {code} : {x}")
    for x in mrz({**card, "mrzFlaw": ""}) + l + l_de:
        print("  " + x)
    if fails:
        print("ÉCHEC\n" + "\n".join(fails))
        sys.exit(1)
    print("MRZ : tous les tests passent (spécimen ICAO compris).")


if __name__ == "__main__":
    main()
