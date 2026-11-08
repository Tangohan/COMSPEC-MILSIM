#!/usr/bin/env python3
"""Statistiques des sessions Claude Code depuis `git log`, sans dépendance externe.

Une session = un lien claude.ai/code/session_... dans le message d'un commit.

    python3 tools/ia/session-stats.py [--days 30] [--top 5]

Sortie texte courte : nombre de sessions, commits par session, durée (premier au dernier commit),
parts par modèle d'après Co-Authored-By. Aucune adresse e-mail n'est lue ni affichée.
"""
import argparse
import re
import subprocess
import sys
from collections import Counter
from datetime import datetime

SESSION_RE = re.compile(r"claude\.ai/code/session_([A-Za-z0-9]+)")
MODEL_RE = re.compile(r"^Co-Authored-By:\s*(Claude[^<\n]*?)\s*<", re.IGNORECASE | re.MULTILINE)
SEP_C, SEP_F = "\x1e", "\x1f"


def parse_log(raw):
    """Texte `git log --format=%aI<US>%B<RS>` -> liste de (date, session|None, modèle|None)."""
    commits = []
    for block in raw.split(SEP_C):
        if SEP_F not in block:
            continue
        date_s, body = block.strip().split(SEP_F, 1)
        try:
            date = datetime.fromisoformat(date_s.strip())
        except ValueError:
            continue
        s = SESSION_RE.search(body)
        m = MODEL_RE.search(body)
        commits.append((date, s.group(1) if s else None, m.group(1).strip() if m else None))
    return commits


def summarize(commits):
    sessions = {}
    models = Counter()
    for date, sid, model in commits:
        models[model or "sans signature Claude"] += 1
        if sid:
            sessions.setdefault(sid, []).append(date)
    rows = []
    for sid, dates in sessions.items():
        rows.append({"id": sid[:8], "commits": len(dates), "hours": (max(dates) - min(dates)).total_seconds() / 3600})
    rows.sort(key=lambda r: r["commits"], reverse=True)
    return {"total": len(commits), "sessions": rows, "models": models}


def render(stats, days, top):
    rows, total = stats["sessions"], stats["total"]
    out = [f"Sessions sur {days} jours : {len(rows)} ({total} commits au total)"]
    if rows:
        n = len(rows)
        in_sessions = sum(r["commits"] for r in rows)
        out.append(f"Commits dans une session : {in_sessions}, moyenne {in_sessions / n:.1f}, max {rows[0]['commits']}")
        long_ = sum(1 for r in rows if r["hours"] > 24)
        out.append(f"Durée moyenne {sum(r['hours'] for r in rows) / n:.1f} h, plus de 24 h : {long_}")
        out.append(f"Plus longues (par commits), {min(top, n)} :")
        for r in rows[:top]:
            out.append(f"  {r['id']}  {r['commits']:>4} commits  {r['hours']:>6.1f} h")
    out.append("Modèles (commits) :")
    for name, count in stats["models"].most_common():
        out.append(f"  {name}: {count} ({100 * count / total:.0f} %)" if total else f"  {name}: {count}")
    return "\n".join(out)


def main(argv=None):
    p = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    p.add_argument("--days", type=int, default=30)
    p.add_argument("--top", type=int, default=5)
    args = p.parse_args(argv)
    fmt = f"--format=%aI{SEP_F}%B{SEP_C}"
    res = subprocess.run(["git", "log", f"--since={args.days} days ago", fmt],
                         capture_output=True, text=True, encoding="utf-8", errors="replace")
    if res.returncode != 0:
        print("git log a échoué : lancer depuis le dépôt.", file=sys.stderr)
        return 1
    print(render(summarize(parse_log(res.stdout)), args.days, args.top))
    return 0


if __name__ == "__main__":
    sys.exit(main())
