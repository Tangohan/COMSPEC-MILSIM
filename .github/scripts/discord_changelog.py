#!/usr/bin/env python3
"""
Poste sur Discord un résumé de chaque mise à jour livrée (PR fusionnée sur main
ou release publiée), sans aucun lien GitHub.

Lit l'événement GitHub (GITHUB_EVENT_NAME / GITHUB_EVENT_PATH) et poste sur le
webhook DISCORD_RELEASES_WEBHOOK (secret du dépôt). Sans webhook, n'envoie rien.

Une PR est ignorée si elle porte le label « discord:skip » ou si son titre
contient « [skip discord] ».

Test local : python3 discord_changelog.py --dry-run --event pull_request --payload event.json
"""

from __future__ import annotations

import argparse
import datetime
import json
import os
import re
import sys
import urllib.error
import urllib.request

SKIP_LABEL = "discord:skip"
SKIP_MARKER = "[skip discord]"
MAX_DESC = 3500
COLORS = {"fix": 0xE74C3C, "patch": 0xE67E22, "update": 0x6B7F3A}
KINDS = {
    "fix": "🐛 Correctif",
    "patch": "🩹 Patch",
    "update": "🚀 Mise à jour",
}
PRODUCTS = [
    ("ATAK", r"\batak\b|t[ée]l[ée]phone|drone"),
    ("Athena", r"\bathena\b|back-office|\bsite\b|portail|admin"),
    ("Overwatch", r"\boverwatch\b"),
    ("Link", r"\blink\b|\bdll\b"),
    ("SSE", r"\bsse\b|\bseek\b|biom[ée]tri"),
    ("Modules", r"\bmodules?\b"),
]


# --------------------------------------------------------------------------
# Nettoyage du texte : aucun lien, aucun détail technique
# --------------------------------------------------------------------------
URL_RE = re.compile(r"https?://\S+|www\.\S+", re.I)
MD_LINK_RE = re.compile(r"!?\[([^\]]*)\]\([^)]*\)")
GITHUB_REF_RE = re.compile(r"(?<![\w/])(?:[\w.-]+/[\w.-]+)?#\d+\b")


def clean(text: str) -> str:
    text = re.sub(r"<!--.*?-->", "", text, flags=re.S)
    text = MD_LINK_RE.sub(lambda m: m.group(1), text)
    text = URL_RE.sub("", text)
    text = GITHUB_REF_RE.sub("", text)
    text = re.sub(r"\(\s*[,;]?\s*\)", "", text)
    out = []
    for line in text.splitlines():
        indent = re.match(r"^\s*", line).group(0)
        rest = re.sub(r"[ \t]{2,}", " ", line[len(indent):]).rstrip()
        rest = re.sub(r"\s+([.,;:!?])", r"\1", rest)
        rest = re.sub(r"(?:\s+(?:and|et|or|ou|see|voir|in|dans))+\s*[.:]?$", "", rest, flags=re.I)
        rest = re.sub(r"^(?:see|voir)\s*[.:]?$", "", rest, flags=re.I)
        rest = re.sub(r"[.,;:]*\s*(?:see|voir)\s*[.:]?$", ".", rest, flags=re.I) if rest.lower().endswith(("see", "voir")) else rest
        if re.fullmatch(r"[-*]\s*", rest):
            continue
        out.append(indent + rest)
    return "\n".join(out).strip()


def section(body: str, *names: str) -> str | None:
    """Renvoie le contenu d'une section « After: » / « ## Après » etc."""
    lines = body.splitlines()
    heads = [n.lower() for n in names]
    start = None
    for i, line in enumerate(lines):
        bare = line.strip().lstrip("#").strip().strip("*").rstrip(":").strip().lower()
        if start is None and bare in heads:
            start = i + 1
            continue
        if start is not None and re.match(r"^\s*(#{1,6}\s+\S|\*\*[^*]+\*\*\s*:?\s*$|[A-Z][\w ]{1,30}:\s*$)", line):
            if not line.lstrip().startswith(("-", "*")):
                return "\n".join(lines[start:i]).strip()
    if start is not None:
        return "\n".join(lines[start:]).strip()
    return None


def summarize(body: str) -> str:
    body = body or ""
    picked = section(body, "after", "après", "apres", "changements", "changes", "what's new", "nouveautés")
    if not picked:
        # Premier paragraphe utile
        paras = [p for p in re.split(r"\n\s*\n", clean(body)) if p.strip()]
        paras = [p for p in paras if not re.match(r"^_?requested by|^🤖|^before\s*:", p.strip(), re.I)]
        picked = paras[0] if paras else ""
    text = clean(picked)
    text = "\n".join(l for l in text.splitlines()
                     if not re.match(r"^\s*(🤖|_?requested by|generated with)", l, re.I))
    if len(text) > MAX_DESC:
        cut = text[:MAX_DESC].rsplit("\n", 1)[0]
        text = cut + "\n…"
    return text.strip()


def kind_of(title: str, labels: list[str]) -> str:
    t = f"{title} {' '.join(labels)}".lower()
    if re.search(r"hotfix|\bpatch\b|correctif urgent", t):
        return "patch"
    if re.search(r"\bfix|\bbug|correct|r[ée]pare|debug|crash", t):
        return "fix"
    return "update"


def products_of(text: str) -> list[str]:
    t = text.lower()
    return [name for name, pat in PRODUCTS if re.search(pat, t)]


def version_of(title: str) -> str | None:
    m = re.search(r"\b\d+\.\d+\.\d+\b", title)
    return m.group(0) if m else None


# --------------------------------------------------------------------------
# Construction du message
# --------------------------------------------------------------------------
def build(event_name: str, payload: dict) -> dict | None:
    if event_name == "pull_request":
        pr = payload.get("pull_request") or {}
        if not pr.get("merged"):
            return None
        labels = [l.get("name", "") for l in pr.get("labels", [])]
        title = (pr.get("title") or "").strip()
        if SKIP_LABEL in labels or SKIP_MARKER in title.lower():
            return None
        body = pr.get("body") or ""
        when = pr.get("merged_at")
    elif event_name == "release":
        rel = payload.get("release") or {}
        if rel.get("draft") or rel.get("prerelease"):
            return None
        labels = []
        title = (rel.get("name") or rel.get("tag_name") or "").strip()
        body = rel.get("body") or ""
        when = rel.get("published_at")
    else:
        return None

    title = clean(title) or "Mise à jour"
    kind = "update" if event_name == "release" else kind_of(title, labels)
    desc = summarize(body)
    fields = []
    prods = products_of(f"{title}\n{desc}")
    if prods:
        fields.append({"name": "Produits", "value": " · ".join(prods), "inline": True})
    ver = version_of(title)
    if ver:
        fields.append({"name": "Version", "value": ver, "inline": True})

    embed = {
        "title": f"{KINDS[kind]} · {title}"[:256],
        "description": desc or "Mise à jour déployée.",
        "color": COLORS[kind],
        "footer": {"text": "COMSPEC · journal des mises à jour"},
    }
    if fields:
        embed["fields"] = fields
    if when:
        embed["timestamp"] = when
    else:
        embed["timestamp"] = datetime.datetime.now(datetime.timezone.utc).isoformat()
    return {"username": "COMSPEC Releases", "embeds": [embed], "allowed_mentions": {"parse": []}}


def post(webhook: str, message: dict) -> None:
    req = urllib.request.Request(
        webhook, data=json.dumps(message).encode("utf-8"),
        headers={"Content-Type": "application/json", "User-Agent": "COMSPEC-changelog/1.0"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            print(f"Discord : message envoyé ({resp.status}).")
    except urllib.error.HTTPError as e:
        # Ne jamais afficher l'URL du webhook
        print(f"Discord a refusé le message : HTTP {e.code}.", file=sys.stderr)
        sys.exit(1)


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true", help="affiche le message sans l'envoyer")
    ap.add_argument("--event", default=os.environ.get("GITHUB_EVENT_NAME", ""))
    ap.add_argument("--payload", default=os.environ.get("GITHUB_EVENT_PATH", ""))
    args = ap.parse_args()

    if not args.payload or not os.path.exists(args.payload):
        print("Aucun événement GitHub à lire.")
        return 0
    with open(args.payload, encoding="utf-8") as fh:
        payload = json.load(fh)

    message = build(args.event, payload)
    if message is None:
        print("Rien à annoncer pour cet événement.")
        return 0
    if args.dry_run:
        print(json.dumps(message, ensure_ascii=False, indent=2))
        return 0
    # Un copier-coller laisse parfois un BOM ou un caractère invisible devant « https » : on les retire.
    webhook = os.environ.get("DISCORD_RELEASES_WEBHOOK", "").strip().strip("\ufeff\u200b\u200c\u200d\u2060\u200e\u200f").strip()
    if not webhook:
        print("Secret DISCORD_RELEASES_WEBHOOK absent : aucun message envoyé.")
        return 0
    post(webhook, message)
    return 0


if __name__ == "__main__":
    sys.exit(main())
