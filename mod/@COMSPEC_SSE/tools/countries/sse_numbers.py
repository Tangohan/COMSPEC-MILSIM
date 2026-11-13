"""Numéros des papiers de pays réels (registre CfgComspecCountries) : portage hors jeu de fn_idNumbers.sqf.

Mêmes motifs et mêmes algorithmes de clé que le mod ; sert aux essais (test_countries.py) et à écrire les exemples
contrôlés par PHPUnit (tests/Unit/SseCountryRegistryTest.php, validateurs PHP indépendants).

Motif : « # » chiffre, « @ » lettre (ABCDEFGHJKLMNPRSTUVWXYZ), « * » A-Z0-9, « & » jeu allemand
(CFGHJKLMNPRTVWXYZ0123456789), « {C} » chiffre de contrôle ICAO 7-3-1 des caractères alphanumériques qui précèdent,
« {R} » code de région de la ville, « {A} » indicatif de la région (États-Unis), « {Y} » année de naissance sur deux
chiffres ; tout autre caractère est recopié. Valeur « scheme:<nom> » : numéro à clé (voir SCHEMES).

Tout est FICTIF : rang de naissance 000 du NIR (jamais attribué), SSN en 9xx groupe 01-49 (ni SSN ni ITIN), etc.
"""
import random
import unicodedata

LETTERS = "ABCDEFGHJKLMNPRSTUVWXYZ"
ALNUM = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
DE_SET = "CFGHJKLMNPRTVWXYZ0123456789"
IT_MONTHS = "ABCDEHLMPRST"
ES_LETTERS = "TRWAGMYFPDXBNJZSQVHLCKE"
# Codes cadastraux (Belfiore) de quelques grandes communes italiennes, pour le codice fiscale.
BELFIORE = {"ROMA": "H501", "MILANO": "F205", "NAPOLI": "F839", "TORINO": "L219", "PALERMO": "G273", "GENOVA": "D969",
            "BOLOGNA": "A944", "FIRENZE": "D612", "BARI": "A662", "CATANIA": "C351", "VENEZIA": "L736", "VERONA": "L781"}


def icao(s):
    """Chiffre de contrôle ICAO 9303 : 0-9, A-Z = 10-35, autre = 0 ; poids 7, 3, 1."""
    w = (7, 3, 1)
    total = 0
    for i, ch in enumerate(s.upper()):
        c = ord(ch)
        v = c - 48 if 48 <= c <= 57 else (c - 55 if 65 <= c <= 90 else 0)
        total += v * w[i % 3]
    return total % 10


def mod_digits(digits, m):
    """Reste d'un grand nombre écrit en chiffres (le mod calcule chiffre par chiffre : SQF n'a que 24 bits exacts)."""
    r = 0
    for ch in digits:
        r = (r * 10 + int(ch)) % m
    return r


def plain(s):
    """Capitales sans accents, lettres seulement."""
    s = unicodedata.normalize("NFKD", s.upper())
    return "".join(c for c in s if "A" <= c <= "Z")


# ------------------------------------------------------------------------------------------------- motifs
def pattern(p, rnd, ctx=None):
    ctx = ctx or {}
    out = []
    i = 0
    while i < len(p):
        c = p[i]
        if c == "{":
            j = p.index("}", i)
            tok = p[i + 1:j]
            if tok == "C":
                out.append(str(icao("".join(ch for ch in "".join(out) if ch.isalnum()))))
            elif tok == "R":
                out.append(ctx.get("region", ""))
            elif tok == "A":
                out.append(ctx.get("area", "555"))
            elif tok == "Y":
                out.append(ctx.get("dob", "1990-01-01")[2:4])
            i = j + 1
            continue
        if c == "#":
            out.append(str(rnd.randrange(10)))
        elif c == "@":
            out.append(rnd.choice(LETTERS))
        elif c == "*":
            out.append(rnd.choice(ALNUM))
        elif c == "&":
            out.append(rnd.choice(DE_SET))
        else:
            out.append(c)
        i += 1
    return "".join(out)


# ------------------------------------------------------------------------------------------------- schémas
def fr_nir(rnd, ctx):
    sex = "2" if ctx.get("sex") == "F" else "1"
    dob = ctx.get("dob", "1990-01-01")
    dept = ctx.get("region", "")
    if not (len(dept) == 2 and dept.isdigit()):
        dept = "99"
    body = sex + dob[2:4] + dob[5:7] + dept + "%03d" % (1 + rnd.randrange(999)) + "000"
    key = 97 - mod_digits(body, 97)
    return "%s %s %s %s %s %s %02d" % (body[0], body[1:3], body[3:5], body[5:7], body[7:10], body[10:13], key)


def fr_nir_ok(v):
    d = v.replace(" ", "")
    return len(d) == 15 and d.isdigit() and int(d[13:]) == 97 - mod_digits(d[:13], 97) and d[10:13] == "000"


def be_rrn(rnd, ctx):
    dob = ctx.get("dob", "1990-01-01")
    ctr = 2 * rnd.randrange(1, 498) + (1 if ctx.get("sex") != "F" else 0)
    ctr = min(ctr, 997)
    body = dob[2:4] + dob[5:7] + dob[8:10] + "%03d" % ctr
    base = ("2" + body) if dob[:2] == "20" else body
    key = 97 - mod_digits(base, 97)
    return "%s.%s.%s-%s.%02d" % (body[0:2], body[2:4], body[4:6], body[6:9], key)


def be_rrn_ok(v):
    d = "".join(c for c in v if c.isdigit())
    if len(d) != 11:
        return False
    k = int(d[9:])
    return k == 97 - mod_digits(d[:9], 97) or k == 97 - mod_digits("2" + d[:9], 97)


def be_idcard(rnd, ctx):
    body = "".join(str(rnd.randrange(10)) for _ in range(10))
    key = mod_digits(body, 97) or 97
    return "%s-%s-%02d" % (body[:3], body[3:], key)


def be_idcard_ok(v):
    d = "".join(c for c in v if c.isdigit())
    return len(d) == 12 and int(d[10:]) == (mod_digits(d[:10], 97) or 97)


def us_ssn(rnd, ctx):
    return "9%02d-%02d-%04d" % (rnd.randrange(100), rnd.randrange(1, 50), rnd.randrange(1, 10000))


def us_ssn_ok(v):
    p = v.split("-")
    return len(p) == 3 and p[0][0] == "9" and 1 <= int(p[1]) <= 49 and int(p[2]) > 0


def nl_bsn(rnd, ctx):
    while True:
        d = [rnd.randrange(1, 10)] + [rnd.randrange(10) for _ in range(7)]
        s = sum(x * w for x, w in zip(d, range(9, 1, -1))) % 11
        if s < 10:
            return "".join(map(str, d + [s]))


def nl_bsn_ok(v):
    d = [int(c) for c in v if c.isdigit()]
    return len(d) == 9 and (sum(x * w for x, w in zip(d[:8], range(9, 1, -1))) - d[8]) % 11 == 0


def ean13(d12):
    s = sum(int(c) * (1 if i % 2 == 0 else 3) for i, c in enumerate(d12))
    return (10 - s % 10) % 10


def ch_ahv(rnd, ctx):
    d = "756" + "".join(str(rnd.randrange(10)) for _ in range(9))
    d += str(ean13(d))
    return "%s.%s.%s.%s" % (d[:3], d[3:7], d[7:11], d[11:])


def ch_ahv_ok(v):
    d = v.replace(".", "")
    return len(d) == 13 and d.startswith("756") and int(d[12]) == ean13(d[:12])


AT_W = (3, 7, 9, 0, 5, 8, 4, 2, 1, 6)


def at_svnr(rnd, ctx):
    dob = ctx.get("dob", "1990-01-01")
    tail = dob[8:10] + dob[5:7] + dob[2:4]
    while True:
        n = "%d%02d" % (rnd.randrange(1, 10), rnd.randrange(100))
        digits = n + "0" + tail
        c = sum(int(x) * w for x, w in zip(digits, AT_W)) % 11
        if c < 10:
            return "%s%d %s" % (n, c, tail)


def at_svnr_ok(v):
    d = v.replace(" ", "")
    if len(d) != 10 or not d.isdigit():
        return False
    return sum(int(x) * w for x, w in zip(d, AT_W)) % 11 == int(d[3])


def _cf_part(s, name=False):
    s = plain(s)
    cons = [c for c in s if c not in "AEIOU"]
    vow = [c for c in s if c in "AEIOU"]
    if name and len(cons) >= 4:
        cons = [cons[0], cons[2], cons[3]]
    return ("".join(cons + vow) + "XXX")[:3]


CF_ODD = dict(zip("0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ",
                  [1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 1, 0, 5, 7, 9, 13, 15, 17, 19, 21, 2, 4, 18, 20, 11, 3, 6, 8, 12, 14, 16, 10, 22, 25, 24, 23]))


def cf_check(s15):
    t = 0
    for i, c in enumerate(s15):
        if i % 2 == 0:
            t += CF_ODD[c]
        else:
            t += int(c) if c.isdigit() else ord(c) - 65
    return chr(65 + t % 26)


def it_cf(rnd, ctx):
    dob = ctx.get("dob", "1990-01-01")
    day = int(dob[8:10]) + (40 if ctx.get("sex") == "F" else 0)
    town = BELFIORE.get(plain(ctx.get("pob", "")), rnd.choice(sorted(BELFIORE.values())))
    s = _cf_part(ctx.get("last", "ROSSI")) + _cf_part(ctx.get("first", "MARIO"), True) + dob[2:4] + IT_MONTHS[int(dob[5:7]) - 1] + "%02d" % day + town
    return s + cf_check(s)


def it_cf_ok(v):
    return len(v) == 16 and v[15] == cf_check(v[:15])


def it_cie(rnd, ctx):
    return "C" + rnd.choice(LETTERS) + "".join(str(rnd.randrange(10)) for _ in range(5)) + rnd.choice(LETTERS) + rnd.choice(LETTERS)


def es_dni(rnd, ctx):
    d = "%08d" % rnd.randrange(10 ** 7, 10 ** 8)
    return d + ES_LETTERS[mod_digits(d, 23)]


def es_dni_ok(v):
    return len(v) == 9 and v[:8].isdigit() and v[8] == ES_LETTERS[mod_digits(v[:8], 23)]


def es_nie(rnd, ctx):
    p = rnd.choice("XYZ")
    d = "%07d" % rnd.randrange(10 ** 7)
    return p + d + ES_LETTERS[mod_digits(str("XYZ".index(p)) + d, 23)]


def es_nie_ok(v):
    return len(v) == 9 and v[0] in "XYZ" and v[8] == ES_LETTERS[mod_digits(str("XYZ".index(v[0])) + v[1:8], 23)]


PESEL_W = (1, 3, 7, 9, 1, 3, 7, 9, 1, 3)


def pl_pesel(rnd, ctx):
    dob = ctx.get("dob", "1990-01-01")
    m = int(dob[5:7]) + (20 if dob[:2] == "20" else 0)
    sexd = rnd.choice("02468" if ctx.get("sex") == "F" else "13579")
    d = dob[2:4] + "%02d" % m + dob[8:10] + "%03d" % rnd.randrange(1000) + sexd
    c = (10 - sum(int(x) * w for x, w in zip(d, PESEL_W)) % 10) % 10
    return d + str(c)


def pl_pesel_ok(v):
    return len(v) == 11 and v.isdigit() and int(v[10]) == (10 - sum(int(x) * w for x, w in zip(v, PESEL_W)) % 10) % 10


def _plv(c):
    return ord(c) - 55 if c.isalpha() else int(c)


PL_W = (7, 3, 1, 9, 7, 3, 1, 7, 3)


def pl_idcard(rnd, ctx):
    letters = "".join(rnd.choice(LETTERS) for _ in range(3))
    digits = "".join(str(rnd.randrange(10)) for _ in range(5))
    s = sum(_plv(c) * w for c, w in zip(letters + "0" + digits, PL_W))
    return letters + str(s % 10) + digits


def pl_idcard_ok(v):
    return len(v) == 9 and sum(_plv(c) * w for c, w in zip(v, PL_W)) % 10 == 0


def gb_dln(rnd, ctx):
    sur = plain(ctx.get("last", "SMITH"))
    if sur.startswith("MAC"):
        sur = "MC" + sur[3:]
    dob = ctx.get("dob", "1990-01-01")
    month = int(dob[5:7]) + (50 if ctx.get("sex") == "F" else 0)
    ini = (plain(ctx.get("first", "J"))[:1] + plain(ctx.get("middle", ""))[:1] + "9")[:2]
    return (sur[:5] + "99999")[:5] + dob[2] + "%02d" % month + dob[8:10] + dob[3] + ini + "9" + rnd.choice(LETTERS) + rnd.choice(LETTERS)


def gb_dln_ok(v):
    return len(v) == 16 and v[5:11].isdigit() and int(v[6:8]) % 50 in range(1, 13)


SCHEMES = {
    "fr_nir": (fr_nir, fr_nir_ok), "be_rrn": (be_rrn, be_rrn_ok), "be_idcard": (be_idcard, be_idcard_ok),
    "us_ssn": (us_ssn, us_ssn_ok), "nl_bsn": (nl_bsn, nl_bsn_ok), "ch_ahv": (ch_ahv, ch_ahv_ok),
    "at_svnr": (at_svnr, at_svnr_ok), "it_cf": (it_cf, it_cf_ok), "it_cie": (it_cie, lambda v: len(v) == 9),
    "es_dni": (es_dni, es_dni_ok), "es_nie": (es_nie, es_nie_ok), "pl_pesel": (pl_pesel, pl_pesel_ok),
    "pl_idcard": (pl_idcard, pl_idcard_ok), "gb_dln": (gb_dln, gb_dln_ok),
}

VIN_TL = dict(zip("ABCDEFGHJKLMNPRSTUVWXYZ", [1, 2, 3, 4, 5, 6, 7, 8, 1, 2, 3, 4, 5, 7, 9, 2, 3, 4, 5, 6, 7, 8, 9]))
VIN_W = (8, 7, 6, 5, 4, 3, 2, 10, 0, 9, 8, 7, 6, 5, 4, 3, 2)
VIN_SET = "ABCDEFGHJKLMNPRSTUVWXYZ0123456789"


def vin_check(v):
    s = sum((int(c) if c.isdigit() else VIN_TL[c]) * w for c, w in zip(v, VIN_W)) % 11
    return "X" if s == 10 else str(s)


def vin(rnd, wmi="SSE"):
    v = list((wmi + "".join(rnd.choice(VIN_SET) for _ in range(14)))[:17])
    v[8] = "0"
    v[8] = vin_check("".join(v))
    return "".join(v)


# ------------------------------------------------------------------------------------------------- MRZ (fn_idMrz.sqf)
def mrz_clean(s):
    s = s.strip().upper().replace("ß", "SS").replace("Æ", "AE").replace("Œ", "OE")
    s = unicodedata.normalize("NFKD", s)
    out = []
    for ch in s:
        if unicodedata.combining(ch):
            continue
        out.append(ch if ("A" <= ch <= "Z" or "0" <= ch <= "9" or ch == "<") else "<")
    return "".join(out)


def _pad(s, n):
    return (s + "<" * n)[:n]


def _ymd(d):
    p = d.split("-")
    return "<<<<<<" if len(p) < 3 else p[0][2:] + p[1] + p[2]


def _name(last, first):
    norm = lambda s: "<".join(x for x in mrz_clean(s).split("<") if x)  # noqa: E731
    lst, fst = norm(last), norm(first)
    return lst + "<<" + fst if fst else lst


def mrz_td1(doc):
    """Carte (TD1, 3 x 30) : code du document (« I< », « ID », « IR », « AR »…), numéro long en débordement OACI, données
    facultatives (numéro personnel) en ligne 1."""
    code = (doc.get("code", "I<") + "<")[:2]
    num = mrz_clean(doc["number"]).replace("<", "")
    opt = mrz_clean(doc.get("optional", "")).replace("<", "")
    if len(num) > 9:
        head = num[:9] + "<" + num[9:] + str(icao(num)) + "<" + opt
    else:
        head = _pad(num, 9) + str(icao(_pad(num, 9))) + opt
    l1 = _pad(code + _pad(mrz_clean(doc["issuer"]), 3) + head, 30)
    dob, exp = _ymd(doc["dob"]), _ymd(doc.get("expires", ""))
    sex = doc.get("sex", "<") if doc.get("sex") in ("M", "F") else "<"
    l2 = _pad(dob + str(icao(dob)) + sex + exp + str(icao(exp)) + _pad(mrz_clean(doc["nat"]), 3), 29)
    comp = l1[5:30] + l2[0:7] + l2[8:15] + l2[18:29]
    return [l1, l2 + str(icao(comp)), _pad(_name(doc["last"], doc["first"]), 30)]


def mrz_td3(doc):
    l1 = _pad("P<" + _pad(mrz_clean(doc["issuer"]), 3) + _name(doc["last"], doc["first"]), 44)
    num = _pad(mrz_clean(doc["number"]), 9)
    dob, exp = _ymd(doc["dob"]), _ymd(doc.get("expires", ""))
    sex = doc.get("sex", "<") if doc.get("sex") in ("M", "F") else "<"
    pn = _pad(mrz_clean(doc.get("personal", "")), 14)
    pnck = "<" if pn.strip("<") == "" else str(icao(pn))
    l2 = num + str(icao(num)) + _pad(mrz_clean(doc["nat"]), 3) + dob + str(icao(dob)) + sex + exp + str(icao(exp)) + pn + pnck
    comp = l2[0:10] + l2[13:20] + l2[21:43]
    return [l1, l2 + str(icao(comp))]


def mrz_ok(lines):
    """Relecture : chiffres de contrôle justes (mêmes positions que fn_idMrz « parse »)."""
    if len(lines) == 3:
        l1, l2, _ = lines
        if l1[14] == "<" and l1[15] != "<":
            seg = l1[15:30].split("<")[0]
            if str(icao(l1[5:14] + seg[:-1])) != seg[-1]:
                return False
        elif str(icao(l1[5:14])) != l1[14]:
            return False
        comp = l1[5:30] + l2[0:7] + l2[8:15] + l2[18:29]
        return str(icao(l2[0:6])) == l2[6] and str(icao(l2[8:14])) == l2[14] and str(icao(comp)) == l2[29]
    l1, l2 = lines
    comp = l2[0:10] + l2[13:20] + l2[21:43]
    return (str(icao(l2[0:9])) == l2[9] and str(icao(l2[13:19])) == l2[19] and str(icao(l2[21:27])) == l2[27]
            and str(icao(comp)) == l2[43])


def make(spec, rnd, ctx=None):
    """Numéro selon le motif ou le schéma du registre."""
    ctx = ctx or {}
    if spec.startswith("scheme:"):
        return SCHEMES[spec[7:]][0](rnd, ctx)
    return pattern(spec, rnd, ctx)


def check(spec, value):
    if spec.startswith("scheme:"):
        return SCHEMES[spec[7:]][1](value)
    return True


if __name__ == "__main__":
    r = random.Random(1)
    for k in SCHEMES:
        print(k, make("scheme:" + k, r, {"sex": "F", "dob": "1985-05-17", "last": "Rossi", "first": "Giulia", "pob": "Roma", "region": "75"}))
    print("vin", vin(r))
