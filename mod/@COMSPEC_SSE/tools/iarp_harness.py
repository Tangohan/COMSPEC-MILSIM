# -*- coding: utf-8 -*-
"""
Banc d'essai hors jeu de l'IA RP (addon ia_rp) : lit les données SQF du mod (lexique, banque, hors-sujet) et rejoue en
Python l'interprète (fn_iarpNorm, fn_iarpIntent) et la couche hors sujet (fn_iarpOffTopic). Aucun jeu lancé.

    python tools/iarp_harness.py            batterie complète (hors sujet, identité tu / vous / court, guide, ordres,
                                            relances, bulles), code de sortie 1 au premier échec
    python tools/iarp_harness.py "texte"    classement d'un message : intention, score, confiance, catégorie hors sujet

Le portage suit le SQF ligne à ligne ; une règle changée dans fn_iarpIntent, fn_iarpNorm ou fn_iarpOffTopic doit l'être ici.
"""
import os
import random
import re
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
DATA = os.path.join(HERE, "..", "addons", "ia_rp", "data")


# ---------------------------------------------------------------- lecture des tableaux SQF
def _strip_comments(src):
    out, i, n = [], 0, len(src)
    while i < n:
        c = src[i]
        if c == '"' or c == "'":
            q = c
            j = i + 1
            while j < n:
                if src[j] == q:
                    if j + 1 < n and src[j + 1] == q:
                        j += 2
                        continue
                    break
                j += 1
            out.append(src[i:j + 1])
            i = j + 1
        elif src.startswith("//", i):
            j = src.find("\n", i)
            i = n if j < 0 else j
        elif src.startswith("/*", i):
            j = src.find("*/", i + 2)
            i = n if j < 0 else j + 2
        else:
            out.append(c)
            i += 1
    return "".join(out)


def parse_array(src):
    """Premier tableau littéral SQF du texte (commentaires retirés) -> listes Python."""
    s = _strip_comments(src)
    i = s.find("[")
    pos = [i]

    def val():
        while s[pos[0]] in " \t\r\n":
            pos[0] += 1
        c = s[pos[0]]
        if c == "[":
            pos[0] += 1
            arr = []
            while True:
                while s[pos[0]] in " \t\r\n,":
                    pos[0] += 1
                if s[pos[0]] == "]":
                    pos[0] += 1
                    return arr
                arr.append(val())
        if c in "\"'":
            q = c
            j = pos[0] + 1
            buf = []
            while True:
                if s[j] == q:
                    if j + 1 < len(s) and s[j + 1] == q:
                        buf.append(q)
                        j += 2
                        continue
                    break
                buf.append(s[j])
                j += 1
            pos[0] = j + 1
            return "".join(buf)
        m = re.compile(r"-?[0-9.]+|true|false|[A-Za-z_][A-Za-z0-9_]*").match(s, pos[0])
        tok = m.group(0)
        pos[0] = m.end()
        if tok == "true":
            return True
        if tok == "false":
            return False
        try:
            return float(tok) if "." in tok else int(tok)
        except ValueError:
            return tok

    return val()


def read(name):
    with open(os.path.join(DATA, name), encoding="utf-8") as f:
        return f.read()


def load_bank(text, start="private _rows = ["):
    i = text.find(start)
    rows = parse_array(text[i + len(start) - 1:])
    return {r[0]: {t[0]: t[1] for t in r[1]} for r in rows}


LEX = parse_array(read("iarp_lexicon.sqf"))
BANK = load_bank(read("iarp_bank.sqf"))
_OT_SRC = read("iarp_offtopic.sqf") if os.path.exists(os.path.join(DATA, "iarp_offtopic.sqf")) else ""
OT = parse_array(_OT_SRC) if _OT_SRC else []


def ot_section(key, default=None):
    for e in OT:
        if e and e[0] == key:
            return e[1]
    return default


# Lexique hors sujet (section « lexicon ») : motifs et exemples ajoutés à l'intention connue, nouvelle intention en fin de liste
# (fn_iarpData « lexicon »).
for _e in ot_section("lexicon", []) or []:
    _e = list(_e) + [[], "", ""][len(_e) - 3:] if len(_e) < 6 else list(_e)
    _at = next((k for k, x in enumerate(LEX) if x[0] == _e[0]), -1)
    if _at < 0:
        LEX.append([_e[0], _e[1], list(_e[2]), list(_e[3]) if len(_e) > 3 else [], _e[4] if len(_e) > 4 else "", ""])
    else:
        _x = LEX[_at]
        _x[2] = list(_x[2]) + list(_e[2])
        while len(_x) < 5:
            _x.append([] if len(_x) == 3 else "")
        _x[3] = list(_x[3]) + (list(_e[3]) if len(_e) > 3 else [])

# Banque hors sujet (data\iarp_offtopic.sqf, section « bank ») : ajoutée à la banque comme fn_iarpData le fait.
for _row in ot_section("bank", []) or []:
    _tones = BANK.setdefault(_row[0], {})
    for _t in _row[1]:
        _tones.setdefault(_t[0], [])
        _tones[_t[0]] = _tones[_t[0]] + _t[1]


# ---------------------------------------------------------------- fn_iarpNorm
_ACC = {}
for to, frm in [("a", "àáâãäåāÀÁÂÃÄÅĀ"), ("c", "çćčÇĆČ"), ("e", "èéêëēėęÈÉÊËĒĖĘ"), ("i", "ìíîïīÌÍÎÏĪ"), ("n", "ñńÑŃ"),
                ("o", "òóôõöøōÒÓÔÕÖØŌ"), ("u", "ùúûüūÙÚÛÜŪ"), ("y", "ýÿÝŸ"), ("z", "žźżŽŹŻ"), ("s", "šśŠŚ"), ("l", "łŁ"),
                ("oe", "œŒ"), ("ae", "æÆ"), ("ss", "ß")]:
    for ch in frm:
        _ACC[ch] = to


def _sms_map():
    m = {}
    for row in ot_section("sms", []) or []:
        m[row[0]] = row[1]
    return m


SMS = _sms_map()


def norm(s):
    out = []
    sp = True
    for ch in s:
        rep = _ACC.get(ch)
        if rep:
            out.append(rep)
            sp = False
            continue
        o = ord(ch)
        if 65 <= o <= 90:
            out.append(chr(o + 32))
            sp = False
            continue
        if 97 <= o <= 122 or 48 <= o <= 57:
            out.append(ch)
            sp = False
            continue
        if not sp:
            out.append(" ")
            sp = True
    t = "".join(out)
    if t.endswith(" "):
        t = t[:-1]
    # Écriture SMS et abréviations (data\iarp_offtopic.sqf « sms ») : mot entier remplacé (« kel » -> « quel »).
    if SMS and t:
        t = " ".join(SMS.get(w, w) for w in t.split(" "))
    return " " + t + " "


# ---------------------------------------------------------------- fn_iarpData « dict »
def _skel(w):
    o = []
    for c in w:
        if not o or o[-1] != c:
            o.append(c)
    return "".join(o)


def build_dict():
    words, skel, idx, keep = {}, {}, {}, []
    for e in LEX:
        i = e[0]
        pats = e[2] if len(e) > 2 else []
        if i == "_keep":
            keep += pats
            continue
        if i == "_kw":
            pats = [p.replace("+", " ").replace("*", "") for row in pats for p in row[1]]
        if not i.startswith("_") or i == "_kw":
            for p in pats:
                for w in p.split(" "):
                    if len(w) >= 5 and all(97 <= ord(c) <= 122 for c in w) and w not in words:
                        words[w] = True
                        s = _skel(w)
                        skel.setdefault(s, w)
                        idx.setdefault("%s%d" % (w[:2], len(w)), []).append(w)
    for k in keep:
        words[k.strip()] = True
    return words, skel, idx


WORDS, SKEL, IDX = build_dict()
LEXI = [e for e in LEX if not e[0].startswith("_")]
WOF = {e[0]: e[1] for e in LEXI}
KW = {}
for e in LEX:
    if e[0] == "_kw":
        for i, items in e[2]:
            for it in items:
                parts = [p for p in it.split("+") if p]
                if parts:
                    KW.setdefault(i, []).append((parts, sum(len(p.replace("*", "")) for p in parts) * 0.8))
POLITE = ["greet", "thanks", "sorry", "bye", "yes", "no"]
NEGTO = {"bribe": "", "search": "", "threat": "calm"}
NEGS = ["ne", "n", "pas", "jamais", "sans", "aucun", "aucune", "not", "never", "no", "without", "don", "didn", "doesn", "won", "isn",
        "aren", "wouldn", "nobody", "personne"]


def _osa(a, b, mx):
    la, lb = len(a), len(b)
    if abs(la - lb) > mx:
        return mx + 1
    prev2, prev = [], list(range(lb + 1))
    for i in range(1, la + 1):
        cur = [i]
        rmin = i
        ai = a[i - 1]
        for j in range(1, lb + 1):
            bj = b[j - 1]
            v = min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (ai != bj))
            if i > 1 and j > 1 and ai == b[j - 2] and a[i - 2] == bj:
                v = min(v, prev2[j - 2] + 1)
            cur.append(v)
            rmin = min(rmin, v)
        if rmin > mx:
            return mx + 1
        prev2, prev = prev, cur
    return prev[lb]


def _fix(s, budget):
    toks = []
    for tok in s.split(" "):
        if tok == "":
            continue
        r, run = [], 0
        for c in tok:
            if r and r[-1] == c:
                run += 1
                if run == 3:
                    r.pop()
                elif run < 3:
                    r.append(c)
            else:
                run = 1
                r.append(c)
        w = "".join(r)
        if len(r) < 5 or w in WORDS or any(not (97 <= ord(c) <= 122) for c in r):
            toks.append(w)
            continue
        hit = SKEL.get(_skel(w), "")
        if hit:
            toks.append(hit)
            continue
        if budget[0] <= 0:
            toks.append(w)
            continue
        budget[0] -= 1
        ln = len(r)
        d = 2 if ln >= 8 else 1
        best, bk, ties = "", 1e6, 0
        for l in range(ln - d, ln + d + 1):
            for x in IDX.get("%s%d" % (w[:2], l), []) + IDX.get("%s%s%d" % (w[1:2], w[:1], l), []):
                dd = _osa(w, x, d)
                if dd <= d:
                    k = dd * 10 + abs(len(x) - ln)
                    if k < bk:
                        bk, best, ties = k, x, 0
                    elif k == bk:
                        ties += 1
        toks.append(best if best and ties == 0 else w)
    return " " + " ".join(toks) + " "


def _negated(s, at):
    before = s[:at].split()
    win = before[max(len(before) - 4, 0):]
    return any(x in NEGS for x in win) or ("t" in win and any(x in ["can", "couldn", "shouldn", "wasn"] for x in win))


def _score_of(s):
    acc, shift = {}, {}
    toks = s.split()
    for e in LEXI:
        intent = e[0]
        for p in e[2]:
            at = s.find(p)
            if at >= 0:
                if intent in NEGTO and _negated(s, at):
                    to = NEGTO[intent]
                    if to:
                        shift[to] = shift.get(to, 0) + len(p)
                else:
                    a = acc.setdefault(intent, [0, 1e6, []])
                    a[0] += len(p)
                    a[1] = min(a[1], at)
                    a[2].append(p)
    for intent, rows in KW.items():
        for parts, ln in rows:
            ok = True
            for p in parts:
                if p.endswith("*"):
                    pre = p[:-1]
                    if not any(t.startswith(pre) for t in toks):
                        ok = False
                        break
                elif p not in toks:
                    ok = False
                    break
            if ok:
                at = s.find(" " + parts[0].replace("*", ""))
                if intent in NEGTO and _negated(s, at):
                    to = NEGTO[intent]
                    if to:
                        shift[to] = shift.get(to, 0) + ln
                else:
                    a = acc.setdefault(intent, [0, 1e6, []])
                    a[0] += ln
                    a[1] = min(a[1], at)
                    a[2].append("+".join(parts))
    res = []
    for e in LEXI:
        i = e[0]
        a = acc.get(i, [0, 1e6, []])
        add = shift.get(i, 0)
        if a[0] > 0 or add > 0:
            res.append([(a[0] + add) * e[1], i, a[1], a[2]])
    for k, y in shift.items():
        if not any(r[1] == k for r in res):
            res.append([y * WOF.get(k, 1), k, 1e6, []])
    return res


def _best(res):
    b = None
    for r in res:
        if b is None or r[0] > b[0]:
            b = r
    return b


def sqf_round(x):
    return int(x * 10 + (0.5 if x >= 0 else -0.5)) / 10.0


def intent(text):
    """fn_iarpIntent -> [intention, score, motifs, liste, mots du message]."""
    if norm(text).strip() == "":
        return ["unknown", 0, [], [], 0, []]
    budget = [12]
    segs = [_fix(norm(x), budget) for x in re.split(r"[.,;!?:]", text)]
    segs = [x for x in segs if x.strip()]
    scores = [_score_of(x) for x in segs]
    tot = {}
    for res in scores:
        for sc, i, p, m in res:
            e = tot.get(i, [0, i, 1e6, []])
            tot[i] = [e[0] + sc, i, min(e[2], p), e[3] + m]
    top = None
    for e in LEXI:
        t = tot.get(e[0])
        if t and (top is None or t[0] > top[0]):
            top = t
    toks = " ".join(segs).split()
    if top is None:
        return ["unknown", 0, [], [], 0, toks]
    cands = []
    for k, res in enumerate(scores[:6]):
        b = _best(res)
        if b:
            cands.append([k * 10000 + b[2], b[1], b[0]])
            if b[1] not in POLITE:
                p = _best([r for r in res if r[1] in POLITE])
                if p:
                    cands.append([k * 10000 + p[2], p[1], p[0]])
    cands.sort()
    lst, npol, nreq = [], 0, 0
    for _, i, sc in cands:
        if not any(x[0] == i for x in lst):
            if i in POLITE:
                if npol < 1:
                    npol += 1
                    lst.append([i, sqf_round(sc)])
            elif nreq < 2:
                nreq += 1
                lst.append([i, sqf_round(sc)])
    if not any(x[0] == top[1] for x in lst):
        lst = [[top[1], sqf_round(top[0])]]
    # Confiance (fn_iarpIntent, fin) : score / 8 borné à 1, × (0,4 + 0,6 × part des mots couverts par les motifs).
    cov = set()
    for p in top[3]:
        if "+" in p or not p.startswith(" "):
            for q in p.split("+"):
                if not q:
                    continue
                qq = q.replace("*", "")
                for t in toks:
                    if (q.endswith("*") and t.startswith(qq)) or t == qq:
                        cov.add(t)
        else:
            for w in p.split():
                cov.add(w)
    content = [t for t in toks if t not in STOP and len(t) >= 2]
    part = 1.0 if not content else sum(1 for t in content if t in cov) / len(content)
    conf = int(min(top[0] / 8, 1) * (0.4 + 0.6 * part) * 100 + 0.5) / 100.0
    return [top[1], sqf_round(top[0]), top[3], lst, conf, toks]


STOP = set("""le la les l de du des d un une et ou a au aux vous tu te t toi ton ta tes votre vos moi me m mon ma mes je j il elle on nous ils
elles est es suis c ce cet cette ca qui que qu quoi ne n pas plus s svp stp plait si y en sur dans pour par avec the an you your u i is are to of
do my it in and or be please sir monsieur madame hein oui non bien tres""".split())


# ---------------------------------------------------------------- fn_iarpOffTopic
OFF_I = ["insult", "mock", "joke", "laugh", "bot", "absurd", "hobbies", "flirt", "compliment", "time", "politics", "followup",
         "otSexual", "otPersonal", "otWorld", "otGibberish", "unknown", "yes", "no"]


def classify(text):
    n = norm(text)
    for x in ot_section("not", []):
        n = n.replace(x, " ")
    toks = n.split()
    best = ["", 0, ""]
    for cat, w, pats, words in ot_section("cats", []):
        sc, ev = 0, ""
        for p in pats:
            if p in n:
                sc += len(p)
                ev = ev or p.strip()
        for p in words:
            hit = any(t.startswith(p[:-1]) for t in toks) if p.endswith("*") else p in toks
            if hit:
                sc += 2 + len(p)
                ev = ev or p
        sc *= w
        if sc > best[1]:
            best = [cat, sc, ev]
    return best


ROWS = ["azertyuiop", "qsdfghjklm", "wxcvbn", "qwertyuiop", "asdfghjkl", "zxcvbnm"]


def gibberish(text):
    toks = norm(text).split()
    if not toks:
        return True
    junk = ot_section("junk", [])

    def bad(w):
        return (w in junk
                or (len(w) >= 3 and not any(c in "aeiouy" for c in w) and all("a" <= c <= "z" for c in w))
                or (len(w) >= 3 and len(set(w)) == 1)
                or (len(w) >= 3 and any(w in r for r in ROWS))
                or all("0" <= c <= "9" for c in w))
    return sum(1 for w in toks if bad(w)) * 2 >= len(toks)


def filt(text, ir=None):
    ir = ir or intent(text)
    main, score, pats, lst = ir[0], ir[1], ir[2], ir[3]
    conf = ir[4] if len(ir) > 5 else 0
    names = [x[0] for x in lst] or [main]
    cat = classify(text)[0]
    act = ot_section("actions", [])
    mn = 0.4
    legit = [x for x in names if x not in OFF_I and x not in ("greet", "thanks", "sorry", "bye") and (x not in act or conf >= mn)]
    sure = bool(legit) and conf >= 0.6 and main not in OFF_I
    if cat == "sexual":
        m, l = "otSexual", ["otSexual"]
    elif cat == "insult" or main == "insult":
        if legit and conf >= mn:
            m, l = legit[0], ["insult", legit[0]]
        else:
            m, l = "insult", ["insult"]
    elif cat == "advance":
        m, l = (legit[0], ["flirt", legit[0]]) if sure else ("flirt", ["flirt"])
    elif cat == "personal" and not sure:
        m, l = "otPersonal", ["otPersonal"]
    elif cat == "world" and not (main in act and conf >= mn):
        m, l = "otWorld", ["otWorld"]
    elif cat == "absurd" and not sure:
        m, l = "absurd", ["absurd"]
    elif main in act and conf < mn:
        m, l = "unknown", ["unknown"]
    else:
        m, l = main, [x for x in names if x not in act or conf >= mn]
    if not l:
        l = [m]
    if m == "unknown" and gibberish(text):
        m, l = "otGibberish", ["otGibberish"]
    return [m, l, cat, conf]


def react(intent_, pm, role="civil", st="", ctl=False, now=0.0):
    """fn_iarpOffTopic « react »."""
    style = "Dur" if role in ("insurge", "hvt") else ("Pro" if role in ("policier", "agent") else "")
    if intent_ in ("unknown", "otGibberish"):
        n = pm.get("miss", 0) + 1
        pm["miss"] = n
        if n >= 3:
            g = "otLost"
        elif n == 2:
            g = "otMiss2"
        elif intent_ == "otGibberish":
            g = "otGibberish"
        elif ctl:
            g = "unknown"
        else:
            g = "otMiss1"
        return [g, 0, 0, "incompris", False]
    pm["miss"] = 0
    if pm.get("coldUntil", -1) > now:
        if intent_ == "sorry":
            pm["coldUntil"] = -1
            pm["provoke"] = 1
            pm["provokeAt"] = now
            return ["otApology", 3, -4, "excuses", False]
        if intent_ in ("threat", "bribe", "confront", "search", "cuff", "badge", "givePapers", "noPapers", "watchMe", "calm", "photo", "cooperate"):
            return []
        return ["otCold", -5 if intent_ in ("insult", "otInsult", "otSexual", "mock") else 0, 0, "froid", False]
    sev = {"otSexual": 1.6, "insult": 1.2, "mock": 0.6, "flirt": 0.6, "otPersonal": 0.3}.get(intent_, -1)
    if sev >= 0:
        pv = max(pm.get("provoke", 0) - (now - pm.get("provokeAt", now)) / 60.0, 0) + sev
        pm["provoke"] = pv
        pm["provokeAt"] = now
        if pv >= 3:
            pm["coldUntil"] = now + 90
            pm["provoke"] = 1
            return ["otEndPro" if style == "Pro" else "otEnd", -20, 6, "fin", True]
        l2 = pv >= 1.8
        if intent_ == "otSexual":
            return ["otSexual" + style, -15, 8, "", False]
        if intent_ == "mock":
            return ["otInsult2" if l2 else "mock", -5, 3, "", False]
        if intent_ == "flirt":
            return ["flirt", -3, 2, "", False]
        if intent_ == "otPersonal":
            return ["otPersonal", -2, 0, "", False]
        g = (["otInsult2", "otInsultDur", "otInsultPro"][["", "Dur", "Pro"].index(style)]) if l2 else "otInsult" + style
        return [g, -15 if l2 else -10, 10 if l2 else 6, "", False]
    if intent_ == "otWorld":
        return ["otWorld", 0, 0, "", False]
    if intent_ == "followup":
        lg, li, lt = pm.get("lastGi", ["", "", -1e6])
        grp = "none"
        if now - lt < 180:
            for g, l in ot_section("more", []):
                if "%s.%s" % (lg, li) in l or lg in l:
                    grp = g
                    break
        return ["otMore." + grp, 0, 0, "", False]
    return []


SMALL = ["howareyou", "smalltalk", "hobbies", "compliment", "joke", "laugh", "time", "bot", "greet", "greetAgain", "introduce", "otWorld", "absurd",
         "politics", "thanks"]
ORDERS = ["handsup", "halt", "kneel", "follow", "leave", "dismount", "trunk", "dropWeapon", "stepBack", "comeHere", "turnAround", "sit", "quiet",
          "hurry", "handsDown", "bag"]
INTEL = ["intel", "seenArmed", "seenVehicles", "strangers", "explosives", "mines", "whereIs", "leader", "howMany", "whenPassed", "happened",
         "heard", "whereWent", "describe", "cache", "base", "checkpoints", "safety", "problems", "roadSafe", "knowPerson", "threatened"]


def after(pm, parts, now):
    """fn_iarpOffTopic « after »."""
    ask = bool(parts) and parts[-1][3] == "ask"
    m = parts[-2] if ask and len(parts) > 1 else parts[-1]
    pm["lastGi"] = [m[0], m[3], now]
    if all(x[0] in SMALL for x in parts):
        pm["small"] = pm.get("small", 0) + 1
        if pm["small"] >= 2 and not ask:
            parts.append(["otSteer", "", "", "ask", ""])
            pm["small"] = 0
    else:
        pm["small"] = 0
    return parts


def turn(pm, text, role="civil", st="", ctl=False, now=0.0):
    """Tour d'échange simplifié (fn_iarpServer « say ») : intention filtrée, réplique de chaque demande (civil sans savoir)."""
    m, lst, cat, conf = filt(text)
    parts = []
    for i in lst:
        gi = i
        if i in ORDERS:
            gi = "noComply" if (i == "leave" and st in ("agressif", "renforts", "fuit")) else "comply"
        else:
            r = react(i, pm, role, st, ctl, now)
            if r:
                gi = r[0]
            elif i in INTEL:
                gi = "intelNo"
            elif i == "name" and "prenom" in norm(text):
                gi = "firstName"
        parts.append([gi, "", "", i, ""])
    parts = after(pm, parts, now)
    return m, [x[0] for x in parts], cat, conf


def pool(key, tone="calme"):
    """fn_iarpGen : phrases du ton comptées deux fois, plus celles de tous les tons."""
    sets = BANK.get(key, {})
    return (sets.get(tone, []) * 2) + sets.get("*", [])


def run_tests():
    T = parse_array(read("iarp_ottests.sqf"))
    sec = {e[0]: e[1] for e in T}
    act = ot_section("actions", [])
    ko, rows = [], []
    small_ok = [x for x in SMALL if x not in ("otWorld", "absurd")]
    # Hors sujet : jamais une demande à effet, la bonne catégorie.
    for text, want in sec["offtopic"]:
        m, lst, cat, conf = filt(text)
        got = "small" if m in small_ok else m
        bad = [x for x in lst if x in act]
        rows.append(("hors sujet", text, m, cat, conf))
        if bad:
            ko.append("hors sujet « %s » : demande à effet %s" % (text, bad))
        if got != want:
            ko.append("hors sujet « %s » : %s au lieu de %s" % (text, m, want))
    # Légitime : la bonne intention, jamais dans la couche hors sujet.
    for text, want in sec["legit"]:
        m, lst, cat, conf = filt(text)
        rows.append(("légitime", text, m, cat, conf))
        if m != want:
            ko.append("légitime « %s » : %s au lieu de %s (conf %.2f, catégorie %s)" % (text, m, want, conf, cat))
    # Guide : chaque exemple compris comme son intention, après la couche hors sujet.
    nguide = 0
    for e in LEXI:
        if len(e) > 3 and isinstance(e[3], list):
            for ex in e[3]:
                for t in ex:
                    nguide += 1
                    m = filt(t)[0]
                    if m != e[0]:
                        ko.append("guide « %s » : %s au lieu de %s" % (t, m, e[0]))
    # Banc d'essai du lexique (corpus et phrases jamais vues) : mêmes seuils que l'autotest, aucun faux positif dangereux.
    rates = {}
    for f, mn in (("iarp_corpus.sqf", 95), ("iarp_unseen.sqf", 85)):
        c = parse_array(read(f))
        ok = {}
        for lg, t, w in c:
            g = intent(t)[0]
            o = ok.setdefault(lg, [0, 0])
            o[1] += 1
            o[0] += g == w
            if g in ("bribe", "threat", "confront") and g != w:
                ko.append("%s : faux positif dangereux « %s » -> %s" % (f, t, g))
        rates[f] = {k: round(v[0] * 100.0 / v[1], 1) for k, v in ok.items()}
        for k, v in rates[f].items():
            if v < mn:
                ko.append("%s : %s %% en %s (%d attendus)" % (f, v, k, mn))
    # Dialogues : relance, incompris, provocation, conversation coupée.
    for d in sec["dialog"]:
        pm = {}
        now = 0.0
        for text, want in d:
            m, gis, cat, conf = turn(pm, text, now=now)
            now += 8
            ok = (want == "*" and all(not g.startswith("ot") and g != "unknown" for g in gis)) or \
                 (want == "*+otSteer" and gis[-1] == "otSteer") or (want in gis)
            rows.append(("dialogue", text, "+".join(gis), cat, conf))
            if not ok:
                ko.append("dialogue « %s » : %s au lieu de %s" % (text, gis, want))
    # Phrases : 6 au moins par ton principal, jamais « D'accord, voilà. », aucun mot grossier repris, parenthèses fermées.
    keys = [k for k in BANK if k.startswith("ot") or k.startswith("comply.") or k.startswith("noComply.") or k == "leaveCuffed"]
    banned = set()
    for cat, w, pats, words in ot_section("cats", []):
        if cat in ("sexual", "insult"):
            banned |= set(x.rstrip("*") for x in words)
    nphr = 0
    for k in keys:
        for tone, lines in BANK[k].items():
            nphr += len(lines)
            need = 6 if tone in ("*", "nerveux", "mefiant", "agressif", "effraye") and not k.startswith("comply.") and k != "otGibberish" else 3
            if tone == "*" and k.startswith("comply."):
                need = 6
            if len(lines) < need:
                ko.append("banque %s.%s : %d phrases (%d attendues)" % (k, tone, len(lines), need))
            for ln in lines:
                fr, en = ln[0], ln[1]
                if "accord, voil" in fr:
                    ko.append("banque %s : « %s »" % (k, fr))
                hit = (set(norm(fr).split()) | set(norm(en).split())) & banned
                if hit:
                    ko.append("banque %s : mot grossier repris %s dans « %s »" % (k, hit, fr))
                if fr.count("(") != fr.count(")") or en.count("(") != en.count(")"):
                    ko.append("banque %s : parenthèses « %s »" % (k, fr))
    # Aucune redite sur les 5 dernières répliques (tirage comme fn_iarpGen, mémoire courte du serveur).
    random.seed(4)
    for k in ["otMiss1", "otMiss2", "otLost", "otInsult", "otSexual", "otPersonal", "otWorld", "otCold", "otEnd", "otSteer"]:
        for tone in ["calme", "nerveux", "mefiant", "agressif", "effraye"]:
            pl = pool(k, tone)
            recent = []
            for _ in range(12):
                fresh = [x for x in pl if x[0] not in recent[-14:]] or [x for x in pl if x[0] not in recent[-5:]] or pl
                c = random.choice(fresh)
                if c[0] in recent[-5:]:
                    ko.append("redite %s/%s : « %s »" % (k, tone, c[0]))
                recent.append(c[0])
    # Bulles du fil (fn_iarpUi « refresh ») : texte aligné à gauche dans sa bulle, marge ≥ 0,9 case, bulle dans le fil.
    for name, aspect in (("4:3, interface très grande", 4 / 3.0), ("16:9, interface normale", 16 / 9.0), ("21:9, interface petite", 21 / 9.0)):
        wabs = min(aspect, 1.2)
        gw = wabs / 40.0
        W = 27 * gw - 0.012
        maxW = W * 0.74
        px = 0.45 * gw
        for wtext in (0.02, 0.15, 0.30, 0.9):
            inner = max(min(wtext * 1.06 + 0.9 * gw, maxW - 2 * px), 4 * gw)
            bx = W - inner - 2 * px
            tx = bx + px
            fits = inner >= min(wtext + 0.9 * gw, maxW - 2 * px) - 1e-9
            if not (bx >= 0 and tx > bx and tx + inner <= W + 1e-9 and fits):
                ko.append("bulle %s, texte %.2f : x=%.4f largeur %.4f" % (name, wtext, bx, inner))
        rows.append(("bulle", name, "case %.4f fil %.4f max %.4f" % (gw, W, maxW - 2 * px), "", 0))
    return ko, rows, rates, nguide, nphr


if __name__ == "__main__" and (len(sys.argv) == 1 or sys.argv[1] in ("--test", "-v")):
    ko, rows, rates, nguide, nphr = run_tests()
    cnt = {}
    for r in rows:
        cnt[r[0]] = cnt.get(r[0], 0) + 1
    if "-v" in sys.argv:
        for r in rows:
            print("%-11s %-45s -> %-34s %-9s %.2f" % (r[0], r[1][:45], r[2][:34], r[3], r[4]))
    print("lignes :", cnt, "| exemples du guide :", nguide, "| phrases hors sujet :", nphr, "| taux :", rates)
    for k in ko:
        print("ÉCHEC", k)
    print("OK" if not ko else "%d échec(s)" % len(ko))
    sys.exit(1 if ko else 0)

if __name__ == "__main__" and len(sys.argv) > 1 and sys.argv[1] not in ("--test", "-v"):
    for a in sys.argv[1:]:
        r = intent(a)
        print(a, "->", r[:5], "| filtre :", filt(a, r))
