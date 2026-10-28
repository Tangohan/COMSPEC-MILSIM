#!/usr/bin/env python3
"""
Poste sur Discord une carte lisible par les joueurs pour chaque version livrée
(PR fusionnée sur main ou release publiée), sans aucun lien GitHub.

La carte ne reprend JAMAIS le détail technique de la PR : elle publie seulement
le bloc « Résumé joueur » (français puis anglais) écrit pour cette version.
Ordre de recherche du bloc :
  1. l'entrée de CHANGELOG-ATAK.md dont le titre contient la version du titre de la PR ;
  2. une section « Résumé joueur » dans la description de la PR (ou de la release).
Sans bloc « Résumé joueur », rien n'est posté (avertissement dans le journal de l'action).

Format du bloc (voir l'en-tête de CHANGELOG-ATAK.md) :

    ### Résumé joueur
    Type : correctif            (nouveauté, correctif, performance ou patch)
    FR : L'adresse du portail ne se règle plus
    - Phrase complète qui dit ce qui change pour le joueur.
    EN : The portal address is no longer a setting
    - Same sentence in English.

Webhook : secret DISCORD_RELEASES_WEBHOOK. Sans webhook, n'envoie rien.
Une PR est ignorée si elle porte le label « discord:skip » ou si son titre
contient « [skip discord] ».

Une seule annonce par version (voir already_announced) :
  - une PR que GitHub marque « fusionnée » parce que ses commits sont arrivés
    dans main par un regroupement n'annonce rien : le regroupement s'en charge ;
  - une entrée de CHANGELOG-ATAK.md qui existait déjà avant la fusion n'est pas
    reprise (une PR de suivi qui cite la même version ne la reposte pas).

Aperçu local d'une carte :
  python3 .github/scripts/discord_changelog.py --preview 1.72.0
Test d'un événement :
  python3 .github/scripts/discord_changelog.py --dry-run --event pull_request --payload event.json
"""

from __future__ import annotations

import argparse
import datetime
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request

SKIP_LABEL = "discord:skip"
SKIP_MARKER = "[skip discord]"
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", ".."))
CHANGELOG = os.path.join(ROOT, "CHANGELOG-ATAK.md")
MAX_BULLETS = 4

# Type de carte : emoji, libellé FR · EN, couleur
KINDS = {
    "new": ("🚀", "Nouveauté · New", 0x6B7F3A),
    "fix": ("🐛", "Correctif · Fix", 0xE74C3C),
    "perf": ("⚡", "Performance", 0x3498DB),
    "patch": ("🩹", "Patch", 0xE67E22),
}
KIND_WORDS = {
    "new": r"nouveaut[ée]|new|feature|ajout|mise à jour|update",
    "fix": r"correctif|fix|bug",
    "perf": r"perf|performance",
    "patch": r"patch|hotfix",
}
# Noms de produits lisibles, repérés dans le titre de l'entrée
PRODUCTS = [
    (r"ATAK natif\s+(\d+\.\d+\.\d+(?:\.[A-Za-z]+)?(?:\s+-\s+[A-Z][\w-]*)?)", "Mod ATAK {}"),
    (r"COMSPEC Link\s+(\d+\.\d+\.\d+)", "COMSPEC Link {}"),
]

VERSION_RE = re.compile(r"\b\d+\.\d+\.\d+\b")
SUMMARY_HEAD_RE = re.compile(r"^\s*(?:#{2,5}\s*|\*\*)\s*(?:r[ée]sum[ée] joueur|player summary)\b", re.I)
STOP_RE = re.compile(r"^\s*(#{1,5}\s+\S|---\s*$)")


# --------------------------------------------------------------------------
# Texte : aucun lien, aucune mise en forme technique
# --------------------------------------------------------------------------
URL_RE = re.compile(r"https?://\S+|www\.\S+", re.I)
MD_LINK_RE = re.compile(r"!?\[([^\]]*)\]\([^)]*\)")
GITHUB_REF_RE = re.compile(r"(?<![\w/])(?:[\w.-]+/[\w.-]+)?#\d+\b")


def clean(text: str) -> str:
    text = MD_LINK_RE.sub(lambda m: m.group(1), text)
    text = URL_RE.sub("", text)
    text = GITHUB_REF_RE.sub("", text)
    text = text.replace("`", "")
    text = re.sub(r"\(\s*[,;]?\s*\)", "", text)
    text = re.sub(r"[ \t]{2,}", " ", text)
    return re.sub(r"[ \t]+([.,])(?=\s|$)", r"\1", text).strip()


def jargon(text: str) -> list[str]:
    """Repère ce qui n'a rien à faire dans une annonce joueur."""
    hits = []
    if "`" in text:
        hits.append("code entre accents graves")
    if URL_RE.search(text):
        hits.append("adresse web")
    for word in (w.strip(".") for w in re.findall(r"[\w.]+", text)):
        if "_" in word.strip("_"):
            hits.append(word)
        elif re.search(r"[a-z][A-Z]", word):
            hits.append(word)
        elif re.search(r"\.(?:pbo|php|cpp|hpp|sqf|bat|dll|cs|csproj|py|json)$", word, re.I):
            hits.append(word)
    return hits


# --------------------------------------------------------------------------
# Lecture du bloc « Résumé joueur »
# --------------------------------------------------------------------------
def summary_block(text: str) -> list[str] | None:
    lines = (text or "").replace("\r\n", "\n").splitlines()
    for i, line in enumerate(lines):
        if SUMMARY_HEAD_RE.match(line):
            out = []
            for nxt in lines[i + 1:]:
                if STOP_RE.match(nxt):
                    break
                out.append(nxt.lstrip("> ").rstrip() if nxt.lstrip().startswith(">") else nxt.rstrip())
            return out
    return None


def parse_summary(lines: list[str]) -> dict | None:
    data = {"kind": None, "product": None, "fr": None, "en": None}
    lang = None
    for raw in lines:
        line = raw.strip()
        if not line:
            continue
        m = re.match(r"^(type|produit|product|fr|en)\s*:\s*(.*)$", line, re.I)
        if m:
            key, val = m.group(1).lower(), clean(m.group(2))
            if key == "type":
                data["kind"] = val.lower()
            elif key in ("produit", "product"):
                data["product"] = val
            else:
                lang = key
                data[lang] = {"title": val, "bullets": []}
            continue
        b = re.match(r"^[-*•]\s+(.*)$", line)
        if b and lang:
            data[lang]["bullets"].append(clean(b.group(1)))
    if not data["fr"] or not data["fr"]["title"]:
        return None
    return data


def changelog_entry(versions: list[str], path: str = CHANGELOG) -> tuple[str, str] | None:
    """Renvoie (titre, corps) de la première entrée dont le titre contient une des versions."""
    if not versions or not os.path.exists(path):
        return None
    with open(path, encoding="utf-8") as fh:
        text = fh.read()
    parts = re.split(r"^## (?!#)", text, flags=re.M)
    for part in parts[1:]:
        head, _, body = part.partition("\n")
        if any(re.search(rf"(?<![\d.]){re.escape(v)}(?!\d|\.\d)", head) for v in versions):
            return head.strip(), body
    return None


def products_of(heading: str) -> str | None:
    found = []
    for pat, label in PRODUCTS:
        for m in re.finditer(pat, heading, re.I):
            found.append(label.format(m.group(1)))
    if re.search(r"\bsite\b", heading, re.I):
        found.append("Site Athena")
    return "\n".join(found) or None


def kind_of(declared: str | None, title: str) -> str:
    for source in (declared or "", title):
        for kind in ("patch", "perf", "fix", "new"):
            if re.search(rf"\b(?:{KIND_WORDS[kind]})", source, re.I):
                return kind
    return "new"


# --------------------------------------------------------------------------
# Construction de la carte
# --------------------------------------------------------------------------
def card(summary: dict, heading: str, when: str | None) -> dict:
    kind = kind_of(summary["kind"], heading)
    emoji, label, color = KINDS[kind]

    def bullets(lang: str) -> str:
        items = summary[lang]["bullets"][:MAX_BULLETS] if summary[lang] else []
        return "\n".join(f"• {b}" for b in items)

    desc = bullets("fr")
    if summary["en"]:
        desc += f"\n\n**🇬🇧 {summary['en']['title']}**\n" + bullets("en")
    desc = desc.strip()

    fields = [{"name": "Type", "value": label, "inline": True}]
    product = summary["product"] or products_of(heading)
    if product:
        fields.append({"name": "Version", "value": product, "inline": True})

    embed = {
        "title": f"{emoji} {summary['fr']['title']}"[:256],
        "description": desc[:4000] or "Mise à jour déployée. · Update deployed.",
        "color": color,
        "fields": fields,
        "footer": {"text": "COMSPEC · journal des mises à jour"},
        "timestamp": when or datetime.datetime.now(datetime.timezone.utc).isoformat(),
    }
    return {"username": "COMSPEC Releases", "embeds": [embed], "allowed_mentions": {"parse": []}}


def find_summary(title: str, body: str) -> tuple[dict, str, bool] | None:
    """(résumé, titre, vient du journal) ; le dernier est faux pour la description de la PR."""
    entry = changelog_entry(VERSION_RE.findall(title))
    if entry:
        block = summary_block(entry[1])
        parsed = parse_summary(block) if block else None
        if parsed:
            return parsed, entry[0], True
    block = summary_block(body)
    parsed = parse_summary(block) if block else None
    if parsed:
        return parsed, title, False
    return None


def warn_jargon(summary: dict) -> None:
    for lang in ("fr", "en"):
        if not summary[lang]:
            continue
        text = " ".join([summary[lang]["title"]] + summary[lang]["bullets"])
        hits = jargon(text)
        if hits:
            print(f"::warning::Résumé joueur ({lang}) : termes techniques à reformuler : {', '.join(sorted(set(hits)))}")


# --------------------------------------------------------------------------
# Une seule annonce par version
# --------------------------------------------------------------------------
def git(*args: str) -> str | None:
    try:
        out = subprocess.run(["git", "-C", ROOT, *args], capture_output=True, text=True, timeout=60)
    except (OSError, subprocess.SubprocessError):
        return None
    return out.stdout if out.returncode == 0 else None


def merged_into_main(sha: str, branch: str = "main") -> bool | None:
    """Vrai si sha est un commit de main lui-même (fusion ou squash), faux s'il n'y est
    arrivé qu'à l'intérieur d'un regroupement. None si l'historique est illisible."""
    chain = git("rev-list", "--first-parent", "-n", "200", f"origin/{branch}") or git(
        "rev-list", "--first-parent", "-n", "200", "HEAD")
    if not chain:
        return None
    return sha in chain.split()


def file_at(sha: str) -> str | None:
    return git("show", f"{sha}:CHANGELOG-ATAK.md")


def entry_heading_in(text: str | None, heading: str) -> bool:
    return bool(text) and re.search(rf"^## {re.escape(heading)}\s*$", text, re.M) is not None


def already_announced(pr: dict) -> str | None:
    """Raison de ne pas annoncer cette PR, ou None."""
    sha = pr.get("merge_commit_sha") or ""
    if not sha:
        return None
    on_main = merged_into_main(sha, (pr.get("base") or {}).get("ref") or "main")
    if on_main is False:
        return "PR arrivée dans main par un regroupement : l'annonce part avec le regroupement."
    return None


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
        reason = already_announced(pr)
        if reason:
            print(f"::notice::{reason}")
            return None
        merge_sha = pr.get("merge_commit_sha") or ""
    elif event_name == "release":
        rel = payload.get("release") or {}
        if rel.get("draft") or rel.get("prerelease"):
            return None
        title = (rel.get("name") or rel.get("tag_name") or "").strip()
        body = rel.get("body") or ""
        when = rel.get("published_at")
        merge_sha = ""
    else:
        return None

    found = find_summary(title, body)
    if not found:
        print("::notice::Pas de « Résumé joueur » pour cette version (CHANGELOG-ATAK.md ou description) : rien n'est posté sur Discord.")
        return None
    summary, heading, from_changelog = found
    if merge_sha and from_changelog and entry_heading_in(file_at(f"{merge_sha}^1"), heading):
        print(f"::notice::L'entrée « {heading} » existait déjà avant cette fusion : déjà annoncée, rien n'est reposté.")
        return None
    warn_jargon(summary)
    return card(summary, heading, when)


def post(webhook: str, message: dict) -> None:
    req = urllib.request.Request(
        webhook, data=json.dumps(message).encode("utf-8"),
        headers={"Content-Type": "application/json", "User-Agent": "COMSPEC-changelog/2.0"},
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
    ap.add_argument("--preview", metavar="VERSION", help="affiche la carte de cette version de CHANGELOG-ATAK.md")
    ap.add_argument("--event", default=os.environ.get("GITHUB_EVENT_NAME", ""))
    ap.add_argument("--payload", default=os.environ.get("GITHUB_EVENT_PATH", ""))
    args = ap.parse_args()

    if args.preview:
        found = find_summary(args.preview, "")
        if not found:
            print(f"Aucun « Résumé joueur » pour {args.preview} dans CHANGELOG-ATAK.md.")
            return 1
        warn_jargon(found[0])
        print(json.dumps(card(found[0], found[1], None), ensure_ascii=False, indent=2))
        return 0

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
    webhook = os.environ.get("DISCORD_RELEASES_WEBHOOK", "").strip().strip("﻿​‌‍⁠‎‏").strip()
    if not webhook:
        print("Secret DISCORD_RELEASES_WEBHOOK absent : aucun message envoyé.")
        return 0
    post(webhook, message)
    return 0


if __name__ == "__main__":
    sys.exit(main())
