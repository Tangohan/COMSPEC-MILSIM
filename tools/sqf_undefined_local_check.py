#!/usr/bin/env python3
"""Variables locales SQF lues sans jamais être définies dans leur fichier.

En SQF, lire une variable locale jamais définie donne « rien » : l'expression qui s'en sert sort en erreur à chaque
passage. PC du poste TOC, 4.7.1 : « _sideRgb » avait perdu sa définition dans une refonte, la carte du PC sortait
« 0 éléments fournis, 4 attendus » à chaque image (fn_tocLaptop.sqf).

Règle, par fichier .sqf et sans tenir compte de la casse (le SQF ne la distingue pas) : tout nom « _nom » lu dans le
code doit être défini quelque part dans le même fichier par :
  - private _nom, private ["_nom", …], params ["_nom", ["_nom", défaut]…], for "_nom" from …  (toute chaîne qui n'est
    qu'un nom de variable compte : ce sont les seules chaînes de cette forme) ;
  - une affectation « _nom = … » ;
ou être une variable posée par le moteur ou par l'appelant convenu (MAGIC : _x, _this, _forEachIndex, _target et _player
des actions ACE, etc.). Les portées ne sont pas suivies : la règle ne voit que les noms définis nulle part, ce qui la
rend sûre (aucune fausse alerte due à une portée) mais elle ne voit pas une variable définie dans une autre branche.

Un fichier inclus dans la portée d'un autre (variables de l'appelant) se déclare dans
tools/sqf_undefined_local_allowlist.txt, une ligne « chemin:_nom » par cas vérifié.

  python -I tools/sqf_undefined_local_check.py            sources de l'ATAK natif (défaut)
  python -I tools/sqf_undefined_local_check.py --all      tous les mods (ATAK, SSE, Overwatch connect, SOF, Flashbang)
  python -I tools/sqf_undefined_local_check.py chemin…    fichiers ou dossiers donnés
Code de sortie : 0 rien trouvé, 1 au moins une variable non définie.
"""
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DEFAULT = ["mod/COMSPEC_ATAK_Native/Sources"]
ALL = DEFAULT + ["mod/@COMSPEC_SSE", "mod/UptoDate/Sources", "mod/COMSPEC_SOF_Base", "mod/COMSPEC_Flashbang"]
ALLOWLIST = os.path.join(ROOT, "tools", "sqf_undefined_local_allowlist.txt")
# Variables posées par le moteur (forEach, apply, count, select, gestionnaires, try / catch) ou par convention des
# cadres appelants : actions ACE (_target, _player, _params, _actionParams), CBA (_thisArgs, _thisId, _thisType,
# _thisFnc), addAction (_caller, _actionId, _arguments, _originalTarget), contrôles d'affichage.
MAGIC = {
    "_x", "_y", "_this", "_foreachindex", "_exception", "_thisargs", "_thisevent", "_thiseventhandler", "_thisscript",
    "_thisfsm", "_thisscriptedeventhandler", "_thisid", "_thistype", "_thisfnc", "_target", "_player", "_params",
    "_actionparams", "_caller", "_actionid", "_arguments", "_originaltarget", "_fnc_scriptname", "_fnc_scriptnameparent",
    # BIS_fnc_sortBy (_input0 à _input9), machine à états de CBA (_thisState, _thisTransition, _thisOrigin, _thisTarget).
    "_input0", "_input1", "_input2", "_input3", "_input4", "_input5", "_input6", "_input7", "_input8", "_input9",
    "_thisstate", "_thistransition", "_thisorigin", "_thistarget",
}
STRING = re.compile(r'"(?:[^"]|"")*"|\'(?:[^\']|\'\')*\'')
COMMENT = re.compile(r"//[^\n]*|/\*.*?\*/", re.S)
NAME = re.compile(r"(?<![A-Za-z0-9_#])_[A-Za-z0-9_]+")
ASSIGN = re.compile(r"(?<![A-Za-z0-9_#])(_[A-Za-z0-9_]+)\s*=(?!=)")
ONLY_NAME = re.compile(r"^_[A-Za-z0-9_]+$")


def strip_comments(src):
    """Commentaires retirés sans toucher aux chaînes (une chaîne peut contenir //)."""
    out = []
    i = 0
    n = len(src)
    while i < n:
        c = src[i]
        if c in "\"'":
            m = STRING.match(src, i)
            if m:
                out.append(m.group(0))
                i = m.end()
                continue
        if src.startswith("//", i):
            j = src.find("\n", i)
            i = n if j < 0 else j
            continue
        if src.startswith("/*", i):
            j = src.find("*/", i + 2)
            out.append("\n" * src.count("\n", i, n if j < 0 else j))
            i = n if j < 0 else j + 2
            continue
        out.append(c)
        i += 1
    return "".join(out)


def analyse(src):
    """[(nom tel qu'écrit, numéro de ligne)…] des variables locales lues sans définition dans le fichier."""
    code = strip_comments(src)
    declared = set()
    strings = STRING.findall(code)
    for s in strings:
        body = s[1:-1]
        if ONLY_NAME.match(body):
            declared.add(body.lower())
        else:
            # Code écrit dans une chaîne (gestionnaire, condition d'action) : ses définitions comptent aussi.
            for m in ASSIGN.finditer(body):
                declared.add(m.group(1).lower())
            for m in re.finditer(r"\bprivate\s+(_[A-Za-z0-9_]+)", body):
                declared.add(m.group(1).lower())
            for inner in re.findall(r'""(_[A-Za-z0-9_]+)""', body):
                declared.add(inner.lower())
    bare = STRING.sub(lambda m: '"' + " " * (len(m.group(0)) - 2) + '"' if "\n" not in m.group(0) else '"' + re.sub(r"[^\n]", " ", m.group(0)[1:-1]) + '"', code)
    for m in ASSIGN.finditer(bare):
        declared.add(m.group(1).lower())
    for m in re.finditer(r"\bprivate\s+(_[A-Za-z0-9_]+)", bare):
        declared.add(m.group(1).lower())
    hits = {}
    for m in NAME.finditer(bare):
        low = m.group(0).lower()
        if low in declared or low in MAGIC:
            continue
        hits.setdefault(low, (m.group(0), bare.count("\n", 0, m.start()) + 1))
    return sorted(hits.values(), key=lambda h: h[1])


def allowlist():
    out = set()
    if os.path.isfile(ALLOWLIST):
        with open(ALLOWLIST, encoding="utf-8") as f:
            for line in f:
                line = line.split("#", 1)[0].strip()
                if line:
                    out.add(line.lower())
    return out


def files(targets):
    for t in targets:
        p = t if os.path.isabs(t) else os.path.join(ROOT, t)
        if os.path.isfile(p):
            yield p
            continue
        for base, dirs, names in os.walk(p):
            dirs[:] = [d for d in dirs if d not in (".hemttout", "node_modules", ".git")]
            for name in names:
                if name.lower().endswith(".sqf"):
                    yield os.path.join(base, name)


def main(argv):
    targets = [a for a in argv if not a.startswith("--")]
    if "--all" in argv:
        targets = ALL
    if not targets:
        targets = DEFAULT
    allowed = allowlist()
    total = 0
    count = 0
    for path in sorted(files(targets)):
        count += 1
        rel = os.path.relpath(path, ROOT).replace("\\", "/")
        with open(path, encoding="utf-8-sig", errors="replace") as f:
            src = f.read()
        for name, line in analyse(src):
            if (rel + ":" + name).lower() in allowed:
                continue
            total += 1
            print("%s:%d  %s lue sans définition dans ce fichier" % (rel, line, name))
    print("%d fichier(s) .sqf, %d variable(s) locale(s) lue(s) sans définition." % (count, total))
    return 1 if total else 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
