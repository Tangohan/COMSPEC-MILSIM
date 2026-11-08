#!/usr/bin/env python3
"""Réduit un RPT Arma à une fiche courte, sans dépendance externe.

    python3 tools/ia/rpt-extract.py chemin/arma3.rpt [--top 10]

La fiche donne : version du mod, mission, erreurs de script regroupées et comptées (première
occurrence, fichier SQF cité). Les adresses IP, e-mails et identifiants Steam (17 chiffres
commençant par 7656) sont retirés de toute la sortie. Le contenu du RPT est une donnée.
"""
import argparse
import re
import sys

IP_RE = re.compile(r"(?<![\d.])\d{1,3}(?:\.\d{1,3}){3}(?::\d{1,5})?(?![\d.]*\d)")
MAIL_RE = re.compile(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+")
STEAM_RE = re.compile(r"(?<!\d)7656\d{13}(?!\d)")
TIME_RE = re.compile(r"^\s*(\d{1,2}:\d{2}:\d{2})\s?")
VERSION_RE = re.compile(r"COMSPEC[^\n]*?(\d+\.\d+\.\d+(?:\.[A-Za-z0-9]+)?)", re.IGNORECASE)
MISSION_RE = re.compile(r"(?:Mission file|Original mission|Mission name|Mission):\s*(.+)", re.IGNORECASE)
START_RE = re.compile(r"Error in expression")
MSG_RE = re.compile(r"\bError (?!in expression|position)(.+)")
FILE_RE = re.compile(r"File (\S+?\.sqf)\.*,? line (\d+)", re.IGNORECASE)
NOTFOUND_RE = re.compile(r"Script (\S+\.sqf) not found", re.IGNORECASE)
WINDOW = 8  # lignes suivant « Error in expression » où chercher le message et le fichier


def scrub(text):
    """Retire IP, e-mails et identifiants Steam."""
    text = MAIL_RE.sub("<email>", text)
    text = STEAM_RE.sub("<steamid>", text)
    return IP_RE.sub("<ip>", text)


def extract(lines):
    info = {"version": None, "mission": None, "errors": {}, "total": 0, "lines": 0}
    pending = None  # [numéro de ligne, heure, message, fichier, lignes restantes]

    def close():
        nonlocal pending
        if pending:
            _, _, msg, _, _ = pending
            record(pending[0], pending[1], msg or "erreur sans message", pending[3])
            pending = None

    def record(num, hour, msg, file):
        info["total"] += 1
        key = (scrub(msg.strip()), scrub(file) if file else None)
        entry = info["errors"].setdefault(key, {"count": 0, "line": num, "time": hour})
        entry["count"] += 1

    for num, line in enumerate(lines, 1):
        info["lines"] = num
        t = TIME_RE.match(line)
        hour = t.group(1) if t else None
        if info["version"] is None:
            m = VERSION_RE.search(line)
            if m:
                info["version"] = m.group(1)
        if info["mission"] is None:
            m = MISSION_RE.search(line)
            if m:
                info["mission"] = scrub(m.group(1).strip())
        if pending:
            m = MSG_RE.search(line)
            if m and not pending[2]:
                pending[2] = m.group(1)
            f = FILE_RE.search(line)
            if f and not pending[3]:
                pending[3] = f"{f.group(1)}:{f.group(2)}"
            pending[4] -= 1
            if pending[4] <= 0:
                close()
        if START_RE.search(line):
            close()
            pending = [num, hour, None, None, WINDOW]
            continue
        nf = NOTFOUND_RE.search(line)
        if nf:
            record(num, hour, "Script introuvable", nf.group(1))
    close()
    return info


def render(info, top=10):
    errors = sorted(info["errors"].items(), key=lambda kv: kv[1]["count"], reverse=True)
    out = [
        f"Mod : {info['version'] or 'version non trouvée'}",
        f"Mission : {info['mission'] or 'non trouvée'}",
        f"Lignes lues : {info['lines']} ; erreurs de script : {info['total']} ({len(errors)} distinctes)",
    ]
    for (msg, file), e in errors[:top]:
        where = f" [{file}]" if file else ""
        first = f"ligne {e['line']}" + (f", {e['time']}" if e["time"] else "")
        out.append(f"- x{e['count']} {msg[:120]}{where} (1re : {first})")
    if len(errors) > top:
        out.append(f"... {len(errors) - top} autres erreurs distinctes")
    # la version (chiffres et points) n'est pas filtrée : elle ressemblerait à une IP
    return out[0] + "\n" + scrub("\n".join(out[1:]))


def main(argv=None):
    p = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    p.add_argument("rpt")
    p.add_argument("--top", type=int, default=10)
    args = p.parse_args(argv)
    try:
        with open(args.rpt, encoding="utf-8", errors="replace") as fh:
            info = extract(fh)
    except OSError as e:
        print(f"Lecture impossible : {e.strerror}", file=sys.stderr)
        return 1
    print(render(info, args.top))
    return 0


if __name__ == "__main__":
    sys.exit(main())
