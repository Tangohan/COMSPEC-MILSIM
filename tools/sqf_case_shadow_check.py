#!/usr/bin/env python3
"""Détecteur de collisions de casse entre variables locales SQF.

En SQF, les noms de variables locales sont insensibles à la casse : `_L` et `_l`,
`_T` et `_t` désignent la même variable. Un helper `private _L = { localize ... }`
écrasé plus loin par `private _l = [...]` ou `params ["_l"]` fait échouer le
`call _L` suivant et le script s'arrête au milieu (fenêtre à moitié construite).

Usage :  python -I tools/sqf_case_shadow_check.py [--all] [--extras] [racines...]
  sans racine : les sources des mods COMSPEC (ATAK natif, SSE, Overwatch connect...).
  --all     affiche aussi les RISK et les simples variantes d'orthographe (OK).
  --extras  autres classes d'échec silencieux (clés localize manquantes,
            variable déclarée dans un bloc interne et lue dehors, call sur une valeur).
Code retour 1 si au moins un BREAKS hors liste d'exceptions
(tools/sqf_case_shadow_allowlist.txt, une ligne `chemin/relatif.sqf:_nom`).

Classes :
  BREAKS  le helper (ou la valeur) est remplacé par l'autre orthographe puis encore
          utilisé avec son type d'origine dans la même portée (erreur à l'exécution).
  RISK    la collision existe mais ne change pas le type (comportement modifié en silence)
          ou n'est pas suivie d'un usage certain.
  OK      même variable écrite avec deux casses différentes, sans conflit.
"""
from __future__ import annotations

import os
import re
import sys
from dataclasses import dataclass, field

REPO = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT_ROOTS = [
    "mod/COMSPEC_ATAK_Native/Sources",
    "mod/@COMSPEC_SSE",
    "mod/UptoDate/Sources",
    "mod/COMSPEC_SOF_Base",
    "mod/COMSPEC_Flashbang",
]
ALLOWLIST = os.path.join(REPO, "tools", "sqf_case_shadow_allowlist.txt")

MAGIC = {
    "_x", "_y", "_foreachindex", "_this", "_thisargs", "_thisevent", "_thiseventhandler",
    "_thisscript", "_thisfsm", "_exception", "_fnc_scriptname", "_fnc_scriptnameparent",
    "_thistype", "_thisid",
}
# Blocs exécutés hors de la portée courante (aucune variable locale héritée).
ISOLATING_CMDS = {
    "addeventhandler", "addmpeventhandler", "addmissioneventhandler", "ctrladdeventhandler",
    "displayaddeventhandler", "addaction", "adduseractioneventhandler", "addplayereventhandler",
    "onEachFrame".lower(), "addmusiceventhandler", "addpublicvariableeventhandler",
}
ISOLATING_FUNC_RE = re.compile(r"^(cba_fnc_(addperframehandler|waitandexecute|waituntilandexecute|execnextframe|addeventhandler|addclasseventhandler|addplayereventhandler|addkeybind|addbiseventhandler)|bis_fnc_holdactionadd|ace_interact_menu_fnc_createaction|ace_common_fnc_addactioneventhandler)$")
LITERAL_START = {"[", "true", "false", "objnull", "grpnull", "controlnull", "displaynull",
                 "locationnull", "createhashmap", "createhashmapfromarray", "nil", "-"}

TOKEN_RE = re.compile(
    r"""(?P<ws>[ \t\r\f\v]+)|(?P<nl>\n)|(?P<lc>//[^\n]*)|(?P<bc>/\*.*?\*/)|"""
    r"""(?P<str>"(?:[^"]|"")*"|'(?:[^']|'')*')|"""
    r"""(?P<num>\$[0-9A-Fa-f]+|0[xX][0-9A-Fa-f]+|(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?)|"""
    r"""(?P<id>[A-Za-z_][A-Za-z0-9_]*)|"""
    r"""(?P<op>==|!=|>=|<=|>>|&&|\|\||[{}\[\]();,=<>!+\-*/%^#:?&|.@])|(?P<other>.)""",
    re.S,
)


@dataclass
class Tok:
    kind: str
    text: str
    line: int
    low: str = ""


@dataclass
class Block:
    idx: int
    parent: "Block | None"
    start: int
    end: int = 10**9
    mode: str = "immediate"  # immediate | deferred | isolated
    children: list = field(default_factory=list)


@dataclass
class Var:
    name: str
    block: Block
    pos: int
    line: int
    kind: str  # code | value | unknown
    body: Block | None = None
    raw: str = "other"
    rebinds: list = field(default_factory=list)
    how: str = "private"


@dataclass
class Occ:
    name: str
    block: Block
    pos: int
    line: int
    code_use: bool


def strip_preprocessor(src: str) -> str:
    out, cont = [], False
    for ln in src.split("\n"):
        if cont or ln.lstrip().startswith("#"):
            cont = ln.rstrip().endswith("\\")
            out.append("")
        else:
            out.append(ln)
    return "\n".join(out)


def tokenize(src: str) -> list[Tok]:
    toks, line = [], 1
    for m in TOKEN_RE.finditer(strip_preprocessor(src)):
        k = m.lastgroup
        t = m.group()
        if k in ("ws", "lc"):
            continue
        if k == "nl":
            line += 1
            continue
        if k in ("bc", "str"):
            if k == "str":
                toks.append(Tok("str", t, line, t.lower()))
            line += t.count("\n")
            continue
        toks.append(Tok(k, t, line, t.lower()))
    return toks


def unquote(s: str) -> str:
    q = s[0]
    return s[1:-1].replace(q + q, q)


class FileModel:
    def __init__(self, path: str, src: str):
        self.path = path
        self.toks = tokenize(src)
        self.blocks: list[Block] = []
        self.block_at: list[Block] = []
        self.match: dict[int, int] = {}
        self.vars: list[Var] = []
        self.occs: list[Occ] = []
        self.code_used_names: set[str] = set()
        self.index: dict = {}
        self._build_blocks()
        self._collect()

    # --- structure -------------------------------------------------------------
    def _build_blocks(self):
        root = Block(0, None, -1, len(self.toks) + 1, "isolated")
        self.blocks.append(root)
        stack: list[tuple[str, int, Block | None]] = []
        cur = root
        self.block_at = [root] * len(self.toks)
        for i, t in enumerate(self.toks):
            if t.text in ("{", "[", "("):
                if t.text == "{":
                    b = Block(len(self.blocks), cur, i)
                    self.blocks.append(b)
                    cur.children.append(b)
                    stack.append(("{", i, b))
                    cur = b
                else:
                    stack.append((t.text, i, None))
            elif t.text in ("}", "]", ")"):
                if stack:
                    _, j, b = stack.pop()
                    self.match[j] = i
                    self.match[i] = j
                    if b is not None:
                        b.end = i
                        cur = b.parent or root
            self.block_at[i] = cur
        self.block_by_start = {b.start: b for b in self.blocks}
        for b in self.blocks[1:]:
            b.mode = self._mode(b)

    def _prev(self, i):
        return self.toks[i - 1] if i > 0 else Tok("none", "", 0)

    def _mode(self, b: Block) -> str:
        p = self._prev(b.start)
        if p.low == "spawn" or p.low in ISOLATING_CMDS:
            return "isolated"
        # bloc élément direct d'un tableau : [ ... , { } , ... ]
        depth, j = 0, b.start - 1
        while j >= 0:
            tx = self.toks[j].text
            if tx in ("}", "]", ")"):
                j = self.match.get(j, j) - 1
                continue
            if tx in ("{", "("):
                break
            if tx == "[":
                before = self._prev(j)
                if before.low in ISOLATING_CMDS or before.low in ("remoteexec", "remoteexeccall"):
                    return "isolated"
                k = self.match.get(j)
                if k is not None and k + 1 < len(self.toks):
                    nxt = self.toks[k + 1]
                    if nxt.low in ("spawn", "remoteexec", "remoteexeccall", "execvm"):
                        return "isolated"
                    if nxt.low == "call" and k + 2 < len(self.toks) and ISOLATING_FUNC_RE.match(self.toks[k + 2].low):
                        return "isolated"
                return "deferred"
            if tx == ";":
                break
            j -= 1
        if p.text == "=":
            return "deferred"
        return "immediate"

    def _stmt_end(self, i: int) -> int:
        j = i
        while j < len(self.toks):
            tx = self.toks[j].text
            if tx in ("{", "[", "("):
                j = self.match.get(j, j) + 1
                continue
            if tx in (";", "}", "]", ")", ","):
                return j
            j += 1
        return j

    def _expr_kind(self, i: int):
        """Nature de l'expression qui commence au jeton i."""
        if i >= len(self.toks):
            return "value", None, "other"
        t = self.toks[i]
        if t.text == "{":
            end = self.match.get(i, i)
            nxt = self.toks[end + 1].text if end + 1 < len(self.toks) else ";"
            if nxt in (";", "}", "]", ")", ","):
                return "code", self.block_by_start.get(i), "code"
            return "value", None, "other"  # { } forEach ..., { } count ... etc.
        if t.low in ("compile", "compilefinal", "compilescript"):
            return "code", None, "code"
        # valeur littérale complète (nombre, chaîne, tableau, booléen...) suivie de la fin d'instruction
        end = self.match.get(i, i) if t.text == "[" else i
        nxt = self.toks[end + 1].text if end + 1 < len(self.toks) else ";"
        whole = nxt in (";", "}", "]", ")", ",")
        raw = "literal" if whole and (t.kind in ("num", "str") or t.low in LITERAL_START) else "other"
        return "value", None, raw

    # --- collecte --------------------------------------------------------------
    def _add_var(self, name, i, pos, kind, body=None, raw="other", how="private", block=None):
        self._push(Var(name, block or self.block_at[i], pos, self.toks[i].line, kind, body, raw, how=how))

    def _push(self, v: Var):
        self.vars.append(v)
        self.index.setdefault((v.block.idx, v.name.lower()), []).append(v)

    def _collect(self):
        T = self.toks
        n = len(T)
        decl_tok: set[int] = set()
        pending_assign = []
        i = 0
        while i < n:
            t = T[i]
            if t.low == "private" and i + 1 < n:
                a = T[i + 1]
                if a.kind == "id" and a.text.startswith("_") and i + 2 < n and T[i + 2].text == "=":
                    kind, body, raw = self._expr_kind(i + 3)
                    self._add_var(a.text, i + 1, self._stmt_end(i + 3), kind, body, raw)
                    decl_tok.add(i + 1)
                elif a.kind == "str":
                    self._add_var(unquote(a.text), i + 1, i + 1, "unknown")
                elif a.text == "[":
                    for k in range(i + 2, self.match.get(i + 1, i + 1)):
                        if T[k].kind == "str" and unquote(T[k].text).startswith("_"):
                            self._add_var(unquote(T[k].text), k, k, "unknown")
            elif t.low == "params" and i + 1 < n and T[i + 1].text == "[":
                end = self.match.get(i + 1, i + 1)
                pos = self._stmt_end(i)
                depth, k = 0, i + 2
                while k < end:
                    tk = T[k]
                    if tk.text in ("[", "{", "("):
                        if tk.text == "[" and depth == 0 and k + 1 < end and T[k + 1].kind == "str":
                            inner = self.match.get(k, k)
                            is_code = any(T[q].text == "{" and T[q + 1].text == "}" for q in range(k + 2, inner))
                            self._add_var(unquote(T[k + 1].text), k + 1, pos, "code" if is_code else "value", how="params", block=self.block_at[i])
                            k = inner + 1
                            continue
                        depth += 1
                    elif tk.text in ("]", "}", ")"):
                        depth -= 1
                    elif tk.kind == "str" and depth == 0:
                        self._add_var(unquote(tk.text), k, pos, "value", how="params", block=self.block_at[i])
                    k += 1
            elif t.low == "for" and i + 1 < n and T[i + 1].kind == "str":
                k = i + 2
                while k < n and T[k].low != "do" and T[k].text != ";":
                    k += 1
                if k + 1 < n and T[k + 1].text == "{":
                    body = self.block_by_start.get(k + 1)
                    if body:
                        self._add_var(unquote(T[i + 1].text), i + 1, k + 1, "value", how="for", block=body)
            elif t.kind == "id" and t.text.startswith("_") and i + 1 < n and T[i + 1].text == "=" and self._prev(i).low != "private":
                kind, body, raw = self._expr_kind(i + 2)
                pending_assign.append((t.text, i, self._stmt_end(i + 2), kind, body, raw))
                decl_tok.add(i)
            i += 1
        for i, t in enumerate(T):
            if t.kind == "id" and t.text.startswith("_") and i not in decl_tok and t.low not in MAGIC:
                cu = self._prev(i).low in ("call", "spawn")
                self.occs.append(Occ(t.text, self.block_at[i], i, t.line, cu))
                if cu:
                    self.code_used_names.add(t.text)
        self.vars = [v for v in self.vars if v.name.lower() not in MAGIC]
        self.index = {}
        for v in list(self.vars):
            self.index.setdefault((v.block.idx, v.name.lower()), []).append(v)
        # Affectations sans private : réécriture d'une variable visible, sinon création locale.
        for name, i, pos, kind, body, raw in sorted(pending_assign, key=lambda x: x[2]):
            if name.lower() in MAGIC:
                continue
            v = Var(name, self.block_at[i], pos, T[i].line, kind, body, raw, how="assign")
            found = self.resolve_var(name.lower(), self.block_at[i], i)
            if found:
                found.rebinds.append(v)
            else:
                self._push(v)
        for v in self._all_bindings():
            if v.kind == "value" and v.name in self.code_used_names and v.raw != "literal":
                v.kind = "unknown"

    def _all_bindings(self):
        for v in self.vars:
            yield v
            yield from v.rebinds

    # --- résolution ------------------------------------------------------------
    def chain(self, block: Block, stop: Block | None = None):
        b = block
        while b is not None:
            yield b
            if b is stop or b.mode == "isolated":
                return
            b = b.parent

    def resolve_var(self, low: str, block: Block, pos: int, stop: Block | None = None):
        for s in self.chain(block, stop):
            cands = [v for v in self.index.get((s.idx, low), ()) if v.pos < pos]
            if cands:
                return max(cands, key=lambda v: v.pos)
        return None

    def current(self, var: Var, pos: int) -> Var:
        cur = var
        for r in var.rebinds:
            if r.pos < pos and r.pos > cur.pos:
                cur = r
        return cur

    def resolve_exact(self, name: str, block: Block, pos: int):
        for s in self.chain(block):
            cands = []
            for v in self.index.get((s.idx, name.lower()), ()):
                if v.pos < pos:
                    cands += [b for b in [v] + v.rebinds if b.name == name and b.pos < pos]
            if cands:
                return max(cands, key=lambda v: v.pos)
        return None

    def free_occs(self, body: Block):
        res = []
        for o in self.occs:
            if not (body.start < o.pos < body.end):
                continue
            # un bloc isolé interne coupe l'accès à la portée appelante
            iso = any(b.mode == "isolated" for b in self.chain(o.block, body) if b is not body)
            if iso:
                continue
            if self.resolve_var(o.name.lower(), o.block, o.pos, stop=body) is None:
                res.append(o)
        return res


@dataclass
class Finding:
    cls: str
    path: str
    line: int
    used: str
    other: str
    other_line: int
    note: str = ""


def check_site(fm: FileModel, name: str, block: Block, pos: int, line: int, code_use: bool, via: str = ""):
    var = fm.resolve_var(name.lower(), block, pos)
    if var is None:
        return None
    cur = fm.current(var, pos)
    if cur.name == name:
        return None
    intended = fm.resolve_exact(name, block, pos)
    if intended is None:
        return None  # même variable écrite autrement : simple variante d'orthographe
    if code_use and cur.kind == "value":
        cls = "BREAKS"
    elif (not code_use) and intended.kind == "value" and cur.kind == "code":
        cls = "BREAKS"
    else:
        cls = "RISK"
    note = f"{cur.how} {cur.name} ({cur.kind}) remplace {intended.name} ({intended.kind})"
    if via:
        note += f" via {via}"
    return Finding(cls, fm.path, line, name, cur.name, cur.line, note)


def analyse(fm: FileModel):
    findings: list[Finding] = []
    lows: dict[str, set[str]] = {}
    for v in fm._all_bindings():
        lows.setdefault(v.name.lower(), set()).add(v.name)
    for o in fm.occs:
        lows.setdefault(o.name.lower(), set()).add(o.name)
    multi = {k: s for k, s in lows.items() if len(s) > 1}
    if not multi:
        return findings, {}
    free_cache: dict[int, list[Occ]] = {}

    def propagate(helper: str, block, pos, line, depth, seen, via):
        if depth > 6:
            return
        var = fm.resolve_var(helper.lower(), block, pos)
        if var is None:
            return
        cur = fm.current(var, pos)
        if cur.name != helper or cur.body is None or cur.body.idx in seen:
            return
        body = cur.body
        if body.idx not in free_cache:
            free_cache[body.idx] = fm.free_occs(body)
        for o in free_cache[body.idx]:
            if o.name.lower() in multi:
                f = check_site(fm, o.name, block, pos, line, o.code_use, via=f"{via}{helper}:{o.line}")
                if f:
                    findings.append(f)
            if o.code_use:
                propagate(o.name, block, pos, line, depth + 1, seen | {body.idx}, f"{via}{helper}>")

    for o in fm.occs:
        if o.name.lower() in multi:
            f = check_site(fm, o.name, o.block, o.pos, o.line, o.code_use)
            if f:
                findings.append(f)
        if o.code_use:
            propagate(o.name, o.block, o.pos, o.line, 0, set(), "")
    # dédoublonnage
    uniq, seen = [], set()
    for f in findings:
        key = (f.line, f.used.lower(), f.cls, f.note)
        if key not in seen:
            seen.add(key)
            uniq.append(f)
    return uniq, multi


# --- autres classes d'échec silencieux -------------------------------------------
def extras(fm: FileModel):
    out = []
    # 1) variable créée dans un bloc interne immédiat (if/then, forEach...) et lue après, dehors
    for o in fm.occs:
        if fm.resolve_var(o.name.lower(), o.block, o.pos) is not None:
            continue
        chain = list(fm.chain(o.block))
        for v in fm._all_bindings():
            if v.name.lower() != o.name.lower() or v.pos >= o.pos or v.block in chain:
                continue
            b = v.block
            while b is not None and b.parent not in chain:
                b = b.parent
            occ_ok = all(x.mode == "immediate" for x in chain if x is not b.parent and b is not None and b.parent in chain and chain.index(x) < chain.index(b.parent)) if b is not None else False
            if b is not None and occ_ok and b.mode == "immediate" and b.end < o.pos and all(x.mode == "immediate" for x in fm.chain(v.block, b)):
                out.append(("bloc-interne", fm.path, o.line, f"{o.name} lu hors du bloc où il est créé (ligne {v.line})"))
                break
    # 2) helper appelé dans un bloc isolé (spawn, gestionnaire d'événement) qui ne voit pas la portée du fichier
    helpers = {v.name.lower() for v in fm._all_bindings() if v.kind == "code"}
    for o in fm.occs:
        if o.code_use and o.name.lower() in helpers and fm.resolve_var(o.name.lower(), o.block, o.pos) is None:
            recursive = any(v.body is not None and v.name.lower() == o.name.lower() and v.body.start < o.pos < v.body.end for v in fm._all_bindings())
            if not recursive and any(b.mode == "isolated" and b.idx != 0 for b in fm.chain(o.block)):
                out.append(("hors-portee", fm.path, o.line, f"call {o.name} dans un bloc spawn/événement où {o.name} n'existe pas"))
    # 3) call sur une valeur littérale
    for o in fm.occs:
        if o.code_use:
            var = fm.resolve_var(o.name.lower(), o.block, o.pos)
            if var:
                cur = fm.current(var, o.pos)
                if cur.raw == "literal" and cur.name == o.name:
                    out.append(("call-valeur", fm.path, o.line, f"call {o.name} alors que {o.name} vaut une valeur (ligne {cur.line})"))
    return out


def localize_keys(fm: FileModel):
    T = fm.toks
    keys = []
    prefixes: dict[str, str] = {}
    for v in fm._all_bindings():
        if v.body is not None:
            seg = T[v.body.start + 1:v.body.end]
            lows = [t.low for t in seg]
            if "islocalized" in lows or "localize" not in lows:
                continue
            for k in range(len(seg) - 4):
                if seg[k].low == "localize" and seg[k + 1].text == "(" and seg[k + 2].kind == "str" and seg[k + 3].text == "+" and seg[k + 4].low == "_this":
                    prefixes[v.name] = unquote(seg[k + 2].text)
    for i, t in enumerate(T):
        if t.low == "localize" and i + 1 < len(T) and T[i + 1].kind == "str":
            keys.append((unquote(T[i + 1].text), t.line))
        if t.low == "call" and i + 1 < len(T) and T[i + 1].text in prefixes and i > 0 and T[i - 1].kind == "str":
            if i < 2 or T[i - 2].text not in ("+",):
                keys.append((prefixes[T[i + 1].text] + unquote(T[i - 1].text), t.line))
    return [(k, l) for k, l in keys if k.upper().startswith("STR_")]


def iter_sqf(roots):
    for r in roots:
        base = os.path.join(REPO, r)
        if not os.path.isdir(base):
            continue
        for d, dirs, files in os.walk(base):
            dirs[:] = [x for x in dirs if not x.startswith(".") and x not in ("node_modules", ".hemttout")]
            for f in files:
                if f.lower().endswith(".sqf"):
                    yield os.path.join(d, f)


def main(argv):
    try:
        sys.stdout.reconfigure(encoding="utf-8")
    except (AttributeError, ValueError):
        pass
    show_all = "--all" in argv
    want_extras = "--extras" in argv
    roots = [a for a in argv if not a.startswith("--")] or DEFAULT_ROOTS
    allow = set()
    if os.path.exists(ALLOWLIST):
        with open(ALLOWLIST, encoding="utf-8") as fh:
            allow = {ln.split("#", 1)[0].strip() for ln in fh if ln.split("#", 1)[0].strip()}
    n_files = 0
    counts = {"BREAKS": 0, "RISK": 0, "OK": 0}
    pairs = {"BREAKS": set(), "RISK": set()}
    blocking = 0
    extra_rows = []
    all_keys = []
    for path in iter_sqf(roots):
        n_files += 1
        try:
            rel = os.path.relpath(path, REPO).replace("\\", "/")
        except ValueError:
            rel = path.replace("\\", "/")
        with open(path, encoding="utf-8", errors="replace") as fh:
            fm = FileModel(rel, fh.read())
        findings, multi = analyse(fm)
        flagged = set()
        for f in sorted(findings, key=lambda f: (f.cls != "BREAKS", f.line)):
            flagged.add(f.used.lower())
            counts[f.cls] += 1
            pairs[f.cls].add((rel, f.used.lower()))
            allowed = f"{rel}:{f.used}" in allow or f"{rel}:{f.other}" in allow
            if f.cls == "BREAKS" and not allowed:
                blocking += 1
            if f.cls == "BREAKS" or show_all:
                tag = f.cls + (" (exception)" if allowed else "")
                print(f"{tag:8} {rel}:{f.line}  {f.used} / {f.other} (l.{f.other_line})  {f.note}")
        for low, sp in multi.items():
            if low not in flagged:
                counts["OK"] += 1
                if show_all:
                    print(f"OK       {rel}  {' / '.join(sorted(sp))}")
        if want_extras:
            extra_rows += extras(fm)
            all_keys += [(k, rel, l) for k, l in localize_keys(fm)]
    print(f"\n{n_files} fichiers .sqf analysés : {len(pairs['BREAKS'])} BREAKS ({counts['BREAKS']} lignes), "
          f"{len(pairs['RISK'])} RISK ({counts['RISK']} lignes), {counts['OK']} OK (variantes sans conflit), "
          f"{blocking} lignes BREAKS hors exceptions.")
    if want_extras:
        for kind, p, l, msg in extra_rows:
            print(f"EXTRA {kind:12} {p}:{l}  {msg}")
        known = set()
        for d, _, files in os.walk(os.path.join(REPO, "mod")):
            if "stringtable.xml" in files:
                with open(os.path.join(d, "stringtable.xml"), encoding="utf-8", errors="replace") as fh:
                    known |= {m.upper() for m in re.findall(r'<Key\s+ID="([^"]+)"', fh.read())}
        missing = sorted({k for k, _, _ in all_keys if k.upper() not in known})
        print(f"EXTRA localize : {len(missing)} clés STR_ littérales absentes des stringtable.xml")
        if show_all:
            for k in missing:
                print(f"      {k}")
    return 1 if blocking else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
