#!/usr/bin/env python3
"""
Génère public/assets/css/back-office-dark.generated.css : le mode nuit du back-office.

Principe : on relit les feuilles du back-office (et les utilitaires Tailwind de couleur)
et, pour chaque règle qui pose une couleur, on réécrit cette couleur pour un fond sombre :
  - fonds blancs et clairs → surfaces sombres (c’est le seul vrai changement) ;
  - textes gris / noirs    → gris clairs ; textes colorés très foncés → même teinte éclaircie ;
  - bordures claires       → filets sombres ;
  - tout le reste (boutons, badges, barre latérale, accents, textes colorés) → inchangé.
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
        "account-hub.css",
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
    """kind ∈ bg | text | border.

    Principe volontairement sobre : seuls les fonds clairs deviennent sombres. Les couleurs
    franches (boutons, badges, barre latérale, textes colorés lisibles) ne bougent pas ; les
    textes sombres sont éclaircis juste ce qu’il faut pour rester lisibles sur le fond nuit.
    """
    try:
        r, g, b, a = parse_color(tok)
    except (ValueError, IndexError):
        return tok
    h, l, s = colorsys.rgb_to_hls(r / 255, g / 255, b / 255)
    chroma = (max(r, g, b) - min(r, g, b)) / 255
    # Blancs, gris, ardoises (y compris les quasi-noirs bleutés type slate-950) — pas les pastels d’alerte.
    neutral = chroma < 0.12 and (l < 0.5 or s < 0.6)
    # Reflets et filets blancs très transparents : déjà adaptés à un fond sombre.
    if a is not None and a < 0.5 and l > 0.85:
        return tok
    if kind == "bg":
        if l < 0.8:
            return tok  # couleurs franches (boutons, bandeaux) ou déjà sombres : inchangées
        if neutral:
            nl = 0.075 + (1 - l) * 0.5
            out = colorsys.hls_to_rgb(NEUTRAL_HUE, nl, NEUTRAL_SAT)
        else:
            # Fond pastel (alerte, encart) : même teinte, version sombre discrète.
            nl = 0.12 + (1 - l) * 0.35
            out = colorsys.hls_to_rgb(h, nl, min(s, 0.6) * 0.45)
        alpha = a if a is None or a >= 0.999 else (max(a, 0.35) if l > 0.9 else a)
        return fmt(*(v * 255 for v in out), alpha)
    if kind == "text":
        if neutral:
            if l >= 0.6:
                return tok
            nl = 0.92 - l * 0.45  # noir → blanc cassé, gris moyen → gris clair
            return fmt(*(v * 255 for v in colorsys.hls_to_rgb(NEUTRAL_HUE, nl, NEUTRAL_SAT)), a)
        if l >= 0.55:
            return tok  # texte coloré déjà lisible : même couleur qu’en mode jour
        # Texte coloré foncé (marine, vert sapin, bleu soutenu) : même teinte, éclairci juste assez.
        return fmt(*(v * 255 for v in colorsys.hls_to_rgb(h, max(0.66, l + 0.2), s)), a)
    # border
    if l < 0.75:
        return tok
    nl = 0.16 + (1 - l) * 0.3
    return fmt(*(v * 255 for v in colorsys.hls_to_rgb(NEUTRAL_HUE if neutral else h, nl, NEUTRAL_SAT if neutral else min(s, 0.3) * 0.5)), a)


# Variables « de marque » : jamais converties (barre latérale, fonds noirs, accents).
BRAND_VAR = re.compile(r"sidebar|void|accent|brand|primary|mint|emerald|focus|rail")


def var_kind(name: str, value: str) -> str | None:
    """Rôle d'une variable CSS d'après son nom (et sa valeur)."""
    n = name.lower()
    if BRAND_VAR.search(n):
        return None
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
    # Autres variables : seules les teintes très claires (fonds) sont converties.
    if l > 0.85:
        return "bg"
    return None


def transform_decl(prop: str, value: str) -> str | None:
    p = prop.strip().lower()
    if p.startswith("--"):
        if p in ("--tw-ring-color", "--tw-ring-offset-color"):
            kind = "border"
            out = COLOR_RE.sub(lambda m: remap(m.group(0), kind), value)
            return out if out != value else None
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


def process(css: str, *, tailwind: bool = False, view: bool = False) -> list[str]:
    rules = []
    for prelude, body in parse_blocks(css):
        if prelude.startswith("@"):
            low = prelude.lower()
            if low.startswith("@media") and "print" not in low:
                inner = process(body, tailwind=tailwind, view=view)
                if inner:
                    rules.append(prelude + " {\n" + "\n".join(inner) + "\n}")
            elif low.startswith("@supports"):
                inner = process(body, tailwind=tailwind, view=view)
                if inner:
                    rules.append(prelude + " {\n" + "\n".join(inner) + "\n}")
            continue  # @keyframes, @font-face, @page…
        # La barre latérale est déjà sombre en mode jour : elle reste identique.
        if "ath-sidebar" in prelude:
            continue
        # Règles déjà écrites pour le mode nuit dans la feuille source : laissées telles quelles.
        if "data-bo-theme" in prelude:
            continue
        if view:
            # Styles de vue : seuls les sélecteurs ciblant une classe ou un id sont repris
            # (un « p » ou « h1 » nu déborderait sur toutes les pages en mode nuit).
            kept = [x.strip() for x in prelude.split(",") if re.search(r"[.#]", x)]
            if not kept:
                continue
            prelude = ", ".join(kept)
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
    # Styles écrits directement dans les vues (<style> des pages du back-office) : sans eux,
    # des tableaux ou encarts restaient blancs en mode nuit.
    style_re = re.compile(r"<style[^>]*>(.*?)</style>", re.S | re.I)
    for view in sorted((ROOT / "views").rglob("*.php")):
        rel = view.relative_to(ROOT).as_posix()
        if rel.startswith(("views/emails/", "views/email/", "views/errors/")):
            continue
        text = view.read_text(encoding="utf-8", errors="replace")
        blocks = [b for b in style_re.findall(text) if "<?" not in b]
        rules = []
        for b in blocks:
            rules.extend(process(b, view=True))
        if rules:
            chunks.append(f"\n/* ——— {rel} (<style> de la vue) ——— */")
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
