#!/usr/bin/env python3
"""
Génère public/assets/css/back-office-dark.generated.css : le mode nuit du back-office.

Principe : on relit les feuilles du back-office (et les utilitaires Tailwind de couleur)
et, pour chaque règle qui pose une couleur, on réécrit cette couleur pour un fond sombre :
  - fonds clairs   → surfaces sombres (même teinte, saturation réduite) ;
  - textes sombres → textes clairs (même teinte : un texte vert reste vert) ;
  - bordures claires → filets sombres ;
  - couleurs franches et déjà sombres (boutons, barre latérale) → inchangées.
Chaque sélecteur est préfixé par html[data-bo-theme="dark"] : sans ce réglage, rien ne change.

Relancer après toute modification d'une feuille du back-office :
    python3 tools/build-bo-dark-css.py
Les ajustements manuels vont dans public/assets/css/back-office-dark.css (chargé après).
"""
from __future__ import annotations

import colorsys
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CSS = ROOT / "public" / "assets" / "css"
OUT = CSS / "back-office-dark.generated.css"
PREFIX = 'html[data-bo-theme="dark"]'

# Feuilles propres au back-office (le site public n'est pas concerné).
SOURCES = sorted(
    {p.name for p in CSS.glob("back-office-*.css")}
    | {
        "announce-tiles.css", "operational-board.css", "invitations-sheet.css", "jnet_bo_embed.css",
        "personnel-dossier.css", "personnel-file.css", "personnel-file-refresh.css",
        "personnel-edit-refresh.css", "personnel-directory.css", "member-integration.css",
        "document-fm.css", "effectifs_lms.css", "decorations-kit.css", "dashboard-impact.css",
    }
    - {"back-office-dark.css", "back-office-dark.generated.css"}
)

# Utilitaires Tailwind de couleur, limités au contenu des pages du back-office.
TW_COLOR_UTIL = re.compile(
    r"^\.(?:[a-z0-9-]+\\:)*(?:bg|text|border|divide|from|via|to|ring|placeholder|fill|stroke|outline|decoration)-"
)

NAMED = {"white": (255, 255, 255), "black": (0, 0, 0)}
COLOR_RE = re.compile(
    r"#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})\b"
    r"|rgba?\(\s*\d+(?:\.\d+)?%?\s*[ ,]\s*\d+(?:\.\d+)?%?\s*[ ,]\s*\d+(?:\.\d+)?%?\s*(?:[,/]\s*(?:[\d.]+%?|var\([^)]*\))\s*)?\)"
    r"|\b(?:white|black)\b"
)

BG_PROPS = {"background", "background-color", "background-image", "fill"}
TEXT_PROPS = {"color", "-webkit-text-fill-color", "caret-color", "text-decoration-color", "stroke"}
BORDER_PROPS = {
    "border", "border-color", "border-top", "border-bottom", "border-left", "border-right",
    "border-top-color", "border-bottom-color", "border-left-color", "border-right-color",
    "outline", "outline-color", "column-rule", "column-rule-color",
}


def parse_color(tok: str):
    t = tok.strip().lower()
    if t in NAMED:
        r, g, b = NAMED[t]
        return r, g, b, None
    if t.startswith("#"):
        h = t[1:]
        if len(h) in (3, 4):
            h = "".join(c * 2 for c in h)
        r, g, b = int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16)
        a = int(h[6:8], 16) / 255 if len(h) == 8 else None
        return r, g, b, a
    t = re.sub(r"var\([^)]*\)", "", t)  # opacité Tailwind : rgb(255 255 255/var(--tw-bg-opacity))
    nums = re.findall(r"[\d.]+%?", t)
    vals = []
    for i, n in enumerate(nums[:3]):
        vals.append(float(n[:-1]) * 2.55 if n.endswith("%") else float(n))
    a = None
    if len(nums) >= 4:
        a = float(nums[3][:-1]) / 100 if nums[3].endswith("%") else float(nums[3])
    return int(vals[0]), int(vals[1]), int(vals[2]), a


def fmt(r, g, b, a):
    r, g, b = (max(0, min(255, int(round(v)))) for v in (r, g, b))
    if a is None or a >= 0.999:
        return f"#{r:02x}{g:02x}{b:02x}"
    return f"rgba({r}, {g}, {b}, {round(a, 3)})"


# Teinte des gris en mode nuit : le gris-vert ATHENA plutôt qu'un gris neutre.
NEUTRAL_HUE, NEUTRAL_SAT = 0.42, 0.10


def remap(tok: str, kind: str) -> str:
    """kind ∈ bg | text | border."""
    try:
        r, g, b, a = parse_color(tok)
    except (ValueError, IndexError):
        return tok
    h, l, s = colorsys.rgb_to_hls(r / 255, g / 255, b / 255)
    chroma = (max(r, g, b) - min(r, g, b)) / 255
    neutral = chroma < 0.16 and s < 0.6  # gris, ardoises (slate), blancs cassés — pas les teintes d’alerte
    if neutral:
        h, s = NEUTRAL_HUE, NEUTRAL_SAT
    # Reflets et filets blancs très transparents : déjà adaptés à un fond sombre.
    if a is not None and a < 0.5 and l > 0.85:
        return tok
    if kind == "bg":
        if l < 0.62:
            return tok  # couleurs franches (boutons) ou déjà sombres
        if a is not None and a < 0.999:
            nl = 0.10 + (1 - l) * 0.5
            return fmt(*(v * 255 for v in colorsys.hls_to_rgb(h, nl, s * (1 if neutral else 0.45))), max(a, 0.35) if l > 0.9 else a)
        nl = 0.085 + (1 - l) * 0.55
        ns = s if neutral else min(s, 0.55) * 0.5
        return fmt(*(v * 255 for v in colorsys.hls_to_rgb(h, nl, ns)), a)
    if kind == "text":
        if l > 0.55:
            return tok  # déjà clair (texte sur bouton coloré)
        if neutral:
            nl = 0.93 - l * 0.55
            return fmt(*(v * 255 for v in colorsys.hls_to_rgb(h, nl, 0.08)), a)
        nl = max(0.68, 0.86 - l * 0.3)
        return fmt(*(v * 255 for v in colorsys.hls_to_rgb(h, nl, min(1, s * 0.85))), a)
    # border
    if l < 0.6:
        return tok
    nl = 0.17 + (1 - l) * 0.35
    return fmt(*(v * 255 for v in colorsys.hls_to_rgb(h, nl, s if neutral else min(s, 0.3) * 0.5)), a)


def var_kind(name: str, value: str) -> str | None:
    """Rôle d'une variable CSS d'après son nom (et sa valeur)."""
    n = name.lower()
    m = COLOR_RE.search(value)
    if not m:
        return None
    try:
        r, g, b, _ = parse_color(m.group(0))
    except (ValueError, IndexError):
        return None
    _, l, s = colorsys.rgb_to_hls(r / 255, g / 255, b / 255)
    if any(k in n for k in ("line", "border", "rule", "divider")):
        return "border"
    if any(k in n for k in ("ink", "text", "muted", "subtle", "-fg", "fg-", "heading")):
        return "text"
    if any(k in n for k in ("bg", "surface", "soft", "panel", "card", "paper", "head", "hover", "row", "tint", "light")):
        return "bg"
    # Variables de couleur « de marque » : on ne touche qu'aux teintes très claires (fonds).
    if l > 0.85:
        return "bg"
    if s < 0.18 and l < 0.5:
        return "text"
    return None


def transform_decl(prop: str, value: str) -> str | None:
    p = prop.strip().lower()
    if p.startswith("--"):
        if p.startswith("--tw-") and p not in ("--tw-gradient-from", "--tw-gradient-to", "--tw-gradient-stops"):
            # Variables d'opacité Tailwind, ombres… : seules les couleurs de dégradé comptent.
            if not any(k in p for k in ("bg-opacity", "text-opacity", "border-opacity")):
                return None
            return None
        kind = "bg" if p.startswith("--tw-gradient") else var_kind(p, value)
    elif p in BG_PROPS:
        kind = "bg"
    elif p in TEXT_PROPS:
        kind = "text"
    elif p in BORDER_PROPS:
        kind = "border"
    elif p == "box-shadow" and "inset" in value:
        kind = "border"
    else:
        return None
    if kind is None or not COLOR_RE.search(value):
        return None
    out = COLOR_RE.sub(lambda m: remap(m.group(0), kind), value)
    return out if out != value else None


def split_decls(block: str):
    decls, buf, depth, quote = [], "", 0, None
    for ch in block:
        if quote:
            buf += ch
            if ch == quote:
                quote = None
            continue
        if ch in "\"'":
            quote = ch
        elif ch == "(":
            depth += 1
        elif ch == ")":
            depth -= 1
        elif ch == ";" and depth == 0:
            decls.append(buf)
            buf = ""
            continue
        buf += ch
    if buf.strip():
        decls.append(buf)
    return decls


def parse_blocks(css: str):
    """Renvoie une liste de (prélude, corps) au premier niveau ; récursif pour @media."""
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.S)
    i, n, out = 0, len(css), []
    while i < n:
        j = css.find("{", i)
        if j < 0:
            break
        prelude = css[i:j].strip()
        depth, k = 1, j + 1
        while k < n and depth:
            if css[k] == "{":
                depth += 1
            elif css[k] == "}":
                depth -= 1
            k += 1
        body = css[j + 1:k - 1]
        semi = prelude.rfind(";")
        if semi >= 0:  # @import / @charset en tête
            prelude = prelude[semi + 1:].strip()
        out.append((prelude, body))
        i = k
    return out


def prefix_selector(sel: str) -> str | None:
    parts = []
    for s in sel.split(","):
        s = s.strip()
        if not s:
            continue
        if s.startswith(":root") or s in ("html",):
            parts.append(PREFIX + s.replace(":root", "", 1).replace("html", "", 1))
        elif s.startswith("html"):
            parts.append(PREFIX + s[4:])
        elif s.startswith("body"):
            parts.append(f"{PREFIX} {s}")
        else:
            parts.append(f"{PREFIX} {s}")
    return ", ".join(parts) if parts else None


def process(css: str, *, tailwind: bool = False) -> list[str]:
    rules = []
    for prelude, body in parse_blocks(css):
        if prelude.startswith("@"):
            low = prelude.lower()
            if low.startswith("@media") and "print" not in low:
                inner = process(body, tailwind=tailwind)
                if inner:
                    rules.append(prelude + " {\n" + "\n".join(inner) + "\n}")
            elif low.startswith("@supports"):
                inner = process(body, tailwind=tailwind)
                if inner:
                    rules.append(prelude + " {\n" + "\n".join(inner) + "\n}")
            continue  # @keyframes, @font-face, @page…
        if tailwind:
            sels = [s.strip() for s in prelude.split(",")]
            if not all(TW_COLOR_UTIL.match(s) for s in sels):
                continue
            # Tailwind n'est réécrit que dans le contenu des pages, pas dans les écrans hors back-office.
            prelude = ", ".join(f".ath-main__body {s}" for s in sels)
        decls = []
        for d in split_decls(body):
            if ":" not in d:
                continue
            prop, value = d.split(":", 1)
            new = transform_decl(prop, value)
            if new is not None:
                decls.append(f"  {prop.strip()}:{new.rstrip()}")
        if decls:
            sel = prefix_selector(prelude)
            if sel:
                rules.append(sel + " {\n" + ";\n".join(decls) + ";\n}")
    return rules


def main() -> int:
    chunks = [
        "/* Fichier généré par tools/build-bo-dark-css.py — ne pas modifier à la main. */",
        "/* Mode nuit du back-office : actif seulement avec html[data-bo-theme=\"dark\"]. */",
    ]
    for name in SOURCES:
        path = CSS / name
        if not path.is_file():
            continue
        rules = process(path.read_text(encoding="utf-8", errors="replace"))
        if rules:
            chunks.append(f"\n/* ——— {name} ——— */")
            chunks.extend(rules)
    tw = CSS / "tailwind.css"
    if tw.is_file():
        rules = process(tw.read_text(encoding="utf-8", errors="replace"), tailwind=True)
        if rules:
            chunks.append("\n/* ——— utilitaires Tailwind (contenu des pages) ——— */")
            chunks.extend(rules)
    OUT.write_text("\n".join(chunks) + "\n", encoding="utf-8")
    print(f"{OUT.relative_to(ROOT)} : {OUT.stat().st_size // 1024} Ko, {len(SOURCES)} feuilles")
    return 0


if __name__ == "__main__":
    sys.exit(main())
