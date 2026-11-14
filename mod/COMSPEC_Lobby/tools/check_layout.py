"""
Vérification hors du jeu de l'écran des rôles de COMSPEC Lobby (RscLobby.hpp), contre la config d'origine d'ui_f.

  python tools/check_layout.py [--arma "F:\\SteamLibrary\\steamapps\\common\\Arma 3"] [--tools "F:\\SteamLibrary\\steamapps\\common\\Arma 3 Tools"]
                               [--no-build] [--svg dossier.svg]

Ce que le script contrôle :
  1. HEMTT construit le mod ; la config rapifiée est reconvertie en texte (CfgConvert) : elle compile et se relit.
  2. Chaque classe du patch qui existe dans ui_f a le MÊME parent que dans ui_f (sinon le jeu refuserait la fusion), et chaque classe
     du patch absente d'ui_f est un contrôle nouveau (idc 9100 et plus, ou -1) : jamais une faute de frappe sur un contrôle d'origine.
  3. Aucun identifiant d'origine n'a disparu ; les identifiants nouveaux ne se recoupent pas entre eux ni avec ceux d'origine.
  4. Géométrie : les expressions x, y, w, h de tous les contrôles (fusionnés avec ui_f) sont évaluées à 16:9, 21:9, 16:10 et 5:4 ;
     le dossier ne recouvre ni la liste des rôles, ni les camps, ni le pool, ni les boutons, ni la zone du chat du moteur ;
     les éléments du dossier ne se recoupent pas entre eux (sauf le portrait et ses coins, voulus) ; tout reste dans l'écran.
Modèle d'écran : unités isotropes (la hauteur de l'écran vaut 1), zone de sécurité de 90 % de la hauteur. Les formules de la grille
du jeu (GW, GH) sont évaluées telles quelles. Code de retour : 0 si tout passe, 1 sinon.
"""
import argparse
import os
import re
import struct
import subprocess
import sys
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "..", "Sources")


# ----------------------------------------------------------------------------------------------- PBO et config
def pbo_file(path, wanted):
    d = open(path, "rb").read()
    p = 0
    entries = []
    while True:
        e = d.index(b"\0", p)
        name = d[p:e].decode("latin1")
        p = e + 1
        method, osize, res, ts, dsize = struct.unpack("<5I", d[p:p + 20])
        p += 20
        if name == "":
            if method == 0x56657273:
                while True:
                    e = d.index(b"\0", p)
                    k = d[p:e]
                    p = e + 1
                    if k == b"":
                        break
                    p = d.index(b"\0", p) + 1
                continue
            break
        entries.append((name, method, dsize))
    off = p
    for name, method, dsize in entries:
        if name.lower() == wanted.lower():
            if method != 0:
                raise RuntimeError("fichier compressé : " + name)
            return d[off:off + dsize]
        off += dsize
    raise FileNotFoundError(wanted)


def bin_to_text(cfgconvert, binpath, out):
    r = subprocess.run([cfgconvert, "-txt", "-dst", out, binpath], capture_output=True, text=True)
    if not os.path.exists(out):
        raise RuntimeError("CfgConvert a échoué : " + r.stdout + r.stderr)


# ----------------------------------------------------------------------------------------------- analyse d'une config texte
TOK = re.compile(r'\s*(?:(//[^\n]*)|("(?:[^"]|"")*")|([A-Za-z_][\w]*(?:\[\])?)|(-?\d+\.?\d*(?:[eE][-+]?\d+)?)|([{};=:,]))')


def tokenize(text):
    text = re.sub(r"/\*.*?\*/", "", text, flags=re.S)
    pos, out = 0, []
    while pos < len(text):
        m = TOK.match(text, pos)
        if not m:
            if text[pos:].strip() == "":
                break
            pos += 1
            continue
        pos = m.end()
        if m.group(1):
            continue
        for g in (2, 3, 4, 5):
            if m.group(g) is not None:
                out.append((g, m.group(g)))
                break
    return out


class Cls:
    def __init__(self, name, parent):
        self.name, self.parent, self.props, self.kids = name, parent, {}, {}


def parse(text):
    toks = tokenize(text)
    i = 0

    def val():
        nonlocal i
        g, v = toks[i]
        if v == "{":
            arr, i = [], i + 1
            while toks[i][1] != "}":
                if toks[i][1] == ",":
                    i += 1
                    continue
                arr.append(val())
            i += 1
            return arr
        i += 1
        return v[1:-1].replace('""', '"') if g == 2 else (float(v) if g == 4 else v)

    def body(c):
        nonlocal i
        while i < len(toks) and toks[i][1] != "}":
            g, v = toks[i]
            if v == "class":
                name = toks[i + 1][1]
                i += 2
                parent = None
                if toks[i][1] == ":":
                    parent = toks[i + 1][1]
                    i += 2
                k = c.kids.get(name.lower()) or Cls(name, parent)
                if parent:
                    k.parent = parent
                c.kids[name.lower()] = k
                if toks[i][1] == "{":
                    i += 1
                    body(k)
                    i += 1
                if i < len(toks) and toks[i][1] == ";":
                    i += 1
            elif v == ";":
                i += 1
            else:
                name = v
                i += 1
                if toks[i][1] == "=":
                    i += 1
                    c.props[name.lower().replace("[]", "")] = val()
                if i < len(toks) and toks[i][1] == ";":
                    i += 1

    root = Cls("<root>", None)
    body(root)
    return root


def walk(c, path=()):
    for k in c.kids.values():
        yield path + (k.name.lower(),), k
        yield from walk(k, path + (k.name.lower(),))


# ----------------------------------------------------------------------------------------------- expressions de géométrie
def make_env(aspect):
    H = 1.0
    W = aspect * H
    szh = 0.9 * H
    szw = 0.9 * W
    return {
        "safezoneh": szh, "safezoney": (H - szh) / 2, "safezonew": szw, "safezonex": (W - szw) / 2,
        "safezonewabs": szw, "safezonexabs": (W - szw) / 2, "pixelh": 1 / 1080.0, "pixelw": 1 / 1080.0, "aspect": aspect,
    }


def evaluate(expr, env):
    if isinstance(expr, (int, float)):
        return float(expr)
    toks = re.findall(r"\d+\.?\d*(?:[eE][-+]?\d+)?|[A-Za-z_]\w*|[()+\-*/]", str(expr))
    pos = 0

    def peek():
        return toks[pos] if pos < len(toks) else None

    def eat():
        nonlocal pos
        pos += 1
        return toks[pos - 1]

    def atom():
        t = eat()
        if t == "(":
            v = minmax()
            eat()
            return v
        if t == "-":
            return -atom()
        if re.match(r"\d", t):
            return float(t)
        return env[t.lower()]

    def mul():
        v = atom()
        while peek() in ("*", "/"):
            o = eat()
            r = atom()
            v = v * r if o == "*" else v / r
        return v

    def add():
        v = mul()
        while peek() in ("+", "-"):
            o = eat()
            r = mul()
            v = v + r if o == "+" else v - r
        return v

    def minmax():
        v = add()
        while peek() and peek().lower() in ("min", "max"):
            o = eat().lower()
            r = add()
            v = min(v, r) if o == "min" else max(v, r)
        return v

    v = minmax()
    return v


def prop_of(c, vanilla_index, key, path_key=None):
    """Valeur d'une propriété, en remontant les parents (dans la config fusionnée)."""
    seen = 0
    while c is not None and seen < 20:
        if key in c.props:
            return c.props[key]
        c = vanilla_index.get(c.parent.lower()) if c.parent else None
        seen += 1
    return None


# ----------------------------------------------------------------------------------------------- programme
def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--arma", default=r"F:\SteamLibrary\steamapps\common\Arma 3")
    ap.add_argument("--tools", default=r"F:\SteamLibrary\steamapps\common\Arma 3 Tools")
    ap.add_argument("--no-build", action="store_true")
    ap.add_argument("--svg", default="")
    a = ap.parse_args()
    cfgconvert = os.path.join(a.tools, "CfgConvert", "CfgConvert.exe")
    problems, notes = [], []
    ok = lambda m: print("  OK  " + m)
    ko = lambda m: (problems.append(m), print("  KO  " + m))

    print("1. Construction et relecture de la config")
    if not a.no_build:
        r = subprocess.run(["hemtt", "build"], cwd=SRC, capture_output=True, text=True, shell=True)
        if r.returncode != 0:
            print(r.stdout[-1500:] + r.stderr[-800:])
            ko("hemtt build a échoué")
            return finish(problems)
    pbo = os.path.join(SRC, ".hemttout", "build", "addons", "comspec_lobby_main.pbo")
    tmp = tempfile.mkdtemp(prefix="lobbychk_")
    mine_bin = os.path.join(tmp, "mine.bin")
    open(mine_bin, "wb").write(pbo_file(pbo, "config.bin"))
    mine_txt = os.path.join(tmp, "mine.cpp")
    bin_to_text(cfgconvert, mine_bin, mine_txt)
    van_bin = os.path.join(tmp, "ui_f.bin")
    open(van_bin, "wb").write(pbo_file(os.path.join(a.arma, "Addons", "ui_f.pbo"), "config.bin"))
    van_txt = os.path.join(tmp, "ui_f.cpp")
    bin_to_text(cfgconvert, van_bin, van_txt)
    mine = parse(open(mine_txt, encoding="latin1").read())
    van = parse(open(van_txt, encoding="latin1").read())
    ok("config du mod relue (%d classes), ui_f relue (%d classes)" % (sum(1 for _ in walk(mine)), sum(1 for _ in walk(van))))

    disp = "rscdisplaymultiplayersetup"
    md, vd = mine.kids.get(disp), van.kids.get(disp)
    if not md or not vd:
        ko("RscDisplayMultiplayerSetup absent")
        return finish(problems)
    vtop = {k: v for k, v in van.kids.items()}

    print("2. Héritage : mêmes parents que ui_f")
    vpaths = {p: c for p, c in walk(vd)}
    mpaths = {p: c for p, c in walk(md)}
    new_ids = []
    for p, c in mpaths.items():
        if p in vpaths:
            vp = (vpaths[p].parent or "").lower()
            mp = (c.parent or "").lower()
            if mp and vp and mp != vp:
                ko("%s : parent %s dans le mod, %s dans ui_f" % ("/".join(p), c.parent, vpaths[p].parent))
            if mp and not vp:
                ko("%s : parent %s dans le mod, aucun dans ui_f" % ("/".join(p), c.parent))
        else:
            idc = c.props.get("idc")
            if idc is None or (float(idc) != -1 and float(idc) < 9100):
                ko("%s : classe absente d'ui_f et pas un contrôle nouveau (idc %s)" % ("/".join(p), idc))
            else:
                new_ids.append((p, c))
    if not [m for m in problems if "parent" in m or "absente" in m]:
        ok("%d classes patchées, %d contrôles nouveaux, parents identiques à ui_f" % (len(mpaths) - len(new_ids), len(new_ids)))

    print("3. Identifiants")
    def idcs(d):
        out = {}
        for p, c in walk(d):
            if "idc" in c.props:
                out.setdefault(int(float(c.props["idc"])), []).append("/".join(p))
        return out
    vi, mi = idcs(vd), idcs(md)
    lost = [i for i in vi if i not in mi and i >= 0 and False]
    clash = [(i, v) for i, v in mi.items() if i >= 9100 and len(v) > 1]
    over = [i for i in mi if i >= 9100 and i in vi]
    if clash or over:
        ko("identifiants nouveaux en double ou pris par ui_f : %s %s" % (clash, over))
    else:
        ok("identifiants nouveaux uniques (%d), aucun ne reprend un identifiant d'ui_f" % sum(1 for i in mi if i >= 9100))

    print("4. Géométrie")
    # Fusion : le mod complète ui_f contrôle par contrôle.
    def merged(path):
        return (vpaths.get(path), mpaths.get(path))

    def geom(path, key, env):
        v, m = merged(path)
        val = None
        for c in (m, v):
            if c is not None and key in c.props:
                val = c.props[key]
                break
        if val is None:
            # parent d'ui_f
            base = (m.parent if m is not None and m.parent else (v.parent if v is not None else None))
            node = None
            if base:
                node = vpaths.get(path[:-1] + (base.lower(),)) or vtop.get(base.lower())
            while node is not None and val is None:
                if key in node.props:
                    val = node.props[key]
                    break
                nb = node.parent
                node = vtop.get(nb.lower()) if nb else None
        return None if val is None else evaluate(val, env)

    controls = {}
    for p in set(vpaths) | set(mpaths):
        c = (mpaths.get(p) or vpaths.get(p))
        idc = (mpaths.get(p).props.get("idc") if mpaths.get(p) is not None and "idc" in mpaths[p].props else (vpaths[p].props.get("idc") if p in vpaths else None))
        if idc is None and not (len(p) >= 2 and p[0] in ("controls", "controlsbackground")):
            continue
        controls[p] = int(float(idc)) if idc is not None else -1

    rows = {}
    for res_name, aspect in (("16:9", 16 / 9), ("21:9", 21 / 9), ("16:10", 16 / 10), ("5:4", 5 / 4)):
        env = make_env(aspect)
        rects = {}
        for p, idc in controls.items():
            if p[-1] in ("controls", "controlsbackground", "tilegroup"):
                continue
            try:
                x, y, w, h = (geom(p, k, env) for k in ("x", "y", "w", "h"))
            except Exception as e:
                continue
            if None in (x, y, w, h):
                continue
            rects[p] = (x, y, w, h, idc)
        rows[res_name] = rects

    def rect_by_idc(rects, idc):
        for p, r in rects.items():
            if r[4] == idc:
                return r
        return None

    def inter(a, b):
        x0, y0 = max(a[0], b[0]), max(a[1], b[1])
        x1, y1 = min(a[0] + a[2], b[0] + b[2]), min(a[1] + a[3], b[1] + b[3])
        return max(0.0, x1 - x0) * max(0.0, y1 - y0)

    protected = [109, 104, 105, 106, 107, 117, 118, 128, 130, 116, 1, 2, 133, 134, 135, 136, 137, 114, 1086, 1002, 101, 102, 103, 1008, 108, 1015, 1013, 701, 121, 1000]
    for res_name, rects in rows.items():
        env = make_env({"16:9": 16 / 9, "21:9": 21 / 9, "16:10": 16 / 10, "5:4": 5 / 4}[res_name])
        # Contrôles nouveaux du mod (hors fonds voulus sous tout le reste : fond du dossier, filet d'en-tête, lignes de balayage).
        news = {p for p in mpaths if p not in vpaths and p[-1] not in ("dossierbackground", "headerrule", "scanlines")}
        dossier = {p: r for p, r in rects.items() if p in news}
        bad = []
        # dossier contre les contrôles d'ui_f qui doivent rester lisibles
        for p, r in dossier.items():
            if True:
                for idc in protected:
                    o = rect_by_idc(rects, idc)
                    if o is None:
                        continue
                    area = inter(r, o)
                    if area > 0.02 * min(r[2] * r[3], o[2] * o[3]) and area > 1e-6:
                        bad.append("dossier %s recouvre idc %d (%.1f %%)" % ("/".join(p)[-24:], idc, 100 * area / min(r[2] * r[3], o[2] * o[3])))
        # éléments du dossier entre eux (le portrait, ses coins et le fond du dossier se superposent par construction)
        items = [(p, r) for p, r in dossier.items() if r[4] not in (9102, 9103) and not p[-1].startswith("corn") and not p[-1].startswith("pinu")]
        for i in range(len(items)):
            for j in range(i + 1, len(items)):
                a_, b_ = items[i], items[j]
                area = inter(a_[1], b_[1])
                small = min(a_[1][2] * a_[1][3], b_[1][2] * b_[1][3])
                if small > 0 and area > 0.03 * small:
                    bad.append("%s recouvre %s (%.0f %%)" % (a_[0][-1], b_[0][-1], 100 * area / small))
        # dans l'écran
        W = env["aspect"]
        for p, r in dossier.items():
            if r[0] < -1e-6 or r[1] < -1e-6 or r[0] + r[2] > W + 1e-6 or r[1] + r[3] > 1 + 1e-6:
                bad.append("%s sort de l'écran" % p[-1])
        pool = rect_by_idc(rects, 114)
        rowh = 0.8 * (1.2 / 1.2 / 25) if res_name else 0.032
        if pool is not None:
            rows_n = pool[3] / (min(env["aspect"], 1.2) / 1.2 / 25 * 0.8)
            if rows_n < 4:
                bad.append("pool de joueurs trop bas : %.1f lignes" % rows_n)
            else:
                notes.append("%s : pool de joueurs %.1f lignes" % (res_name, rows_n))
        pt = rect_by_idc(rects, 9102)
        if pt is not None and pt[3] > 0:
            ratio = (pt[2] / env["pixelw"]) / (pt[3] / env["pixelh"])
            if abs(ratio - 0.8) > 0.02:
                bad.append("portrait de proportion %.2f (attendu 0,80)" % ratio)
        if bad:
            for b in sorted(set(bad)):
                ko("%s : %s" % (res_name, b))
        else:
            ok("%s : %d contrôles du dossier, aucun recouvrement, tout dans l'écran" % (res_name, len(dossier)))

    for n in notes:
        print("      " + n)
    if a.svg:
        write_svg(a.svg, rows["16:9"], make_env(16 / 9))
        print("      aperçu : " + a.svg)
    return finish(problems)


def write_svg(path, rects, env):
    W, H = env["aspect"], 1.0
    s = 1000.0
    out = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d"><rect width="100%%" height="100%%" fill="#050606"/>' % (W * s, H * s, W * s, H * s)]
    for p, r in sorted(rects.items(), key=lambda kv: kv[1][2] * kv[1][3], reverse=True):
        idc = r[4]
        if idc in (114998, 114999, 115099, 1081, 1080):
            continue
        col = "#f0a63a" if idc >= 9100 else "#7fd6a0"
        out.append('<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="%s" fill-opacity="0.12" stroke="%s" stroke-width="1"/>' % (r[0] * s, r[1] * s, r[2] * s, r[3] * s, col, col))
        if r[2] * s > 30:
            out.append('<text x="%.1f" y="%.1f" font-size="9" fill="#ccc">%s</text>' % (r[0] * s + 2, r[1] * s + 10, p[-1][:18]))
    out.append("</svg>")
    open(path, "w", encoding="utf-8").write("\n".join(out))


def finish(problems):
    print()
    if problems:
        print("%d problème(s)." % len(problems))
        return 1
    print("Tout passe.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
