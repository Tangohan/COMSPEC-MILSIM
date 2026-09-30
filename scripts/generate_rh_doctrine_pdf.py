#!/usr/bin/env python3
"""Génère storage/documents/doctrine/drh-pers-2026-001.pdf depuis le Markdown."""

from __future__ import annotations

import re
from pathlib import Path

from fpdf import FPDF

ROOT = Path(__file__).resolve().parents[1]
MD = ROOT / "storage/documents/doctrine/drh-pers-2026-001.md"
PDF = ROOT / "storage/documents/doctrine/drh-pers-2026-001.pdf"
FONT = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"
FONT_B = "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf"
FONT_I = "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf"  # pas d'italique DejaVu séparée ici


class DoctrinePDF(FPDF):
    def header(self) -> None:
        self.set_font("DejaVu", "B", 9)
        self.set_text_color(40, 55, 80)
        self.cell(0, 6, "ATHENA — Référentiel doctrinal", align="L")
        self.cell(0, 6, "DRH/PERS/2026-001", align="R", new_x="LMARGIN", new_y="NEXT")
        self.set_draw_color(37, 99, 235)
        self.set_line_width(0.4)
        self.line(self.l_margin, self.get_y(), self.w - self.r_margin, self.get_y())
        self.ln(4)

    def footer(self) -> None:
        self.set_y(-15)
        self.set_font("DejaVu", "I", 8)
        self.set_text_color(100, 100, 110)
        self.cell(
            0,
            5,
            "Bureau DRH — Doctrine d'emploi RH Recrutement et Avancement — v1.0",
            align="L",
        )
        self.cell(0, 5, f"Page {self.page_no()}/{{nb}}", align="R")


def strip_md(s: str) -> str:
    return s.replace("**", "").replace("`", "")


def main() -> None:
    text = MD.read_text(encoding="utf-8")
    pdf = DoctrinePDF(format="A4")
    pdf.alias_nb_pages()
    pdf.set_auto_page_break(auto=True, margin=18)
    pdf.set_margins(18, 18, 18)
    pdf.add_font("DejaVu", "", FONT)
    pdf.add_font("DejaVu", "B", FONT_B)
    pdf.add_font("DejaVu", "I", FONT_I)
    pdf.add_page()
    pdf.set_title("Doctrine d'emploi RH — Recrutement et Avancement")
    pdf.set_author("Bureau DRH — ATHENA")
    pdf.set_subject("DRH/PERS/2026-001")
    pdf.set_creator("ATHENA doctrine seed")

    y = pdf.get_y()
    pdf.set_fill_color(37, 99, 235)
    pdf.rect(18, y, pdf.epw, 30, style="F")
    pdf.set_xy(22, y + 4)
    pdf.set_text_color(255, 255, 255)
    pdf.set_font("DejaVu", "B", 11)
    pdf.cell(0, 6, "DRH / PERS / 2026-001", new_x="LMARGIN", new_y="NEXT")
    pdf.set_x(22)
    pdf.set_font("DejaVu", "B", 15)
    pdf.multi_cell(pdf.epw - 8, 7, "Doctrine d'emploi RH\nRecrutement et Avancement")
    pdf.set_y(y + 34)
    pdf.set_text_color(30, 30, 35)

    meta = [
        ("Version", "v1.0"),
        ("Statut", "Publié"),
        ("Niveau d'exigence", "Obligatoire"),
        ("Diffusion", "Tous les membres de l'organisation"),
        ("Autorité émettrice", "Bureau DRH — Ressources humaines"),
    ]
    for key, value in meta:
        pdf.set_font("DejaVu", "B", 10)
        pdf.cell(52, 6, key)
        pdf.set_font("DejaVu", "", 10)
        pdf.cell(0, 6, value, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(3)
    pdf.set_draw_color(200, 205, 215)
    pdf.line(pdf.l_margin, pdf.get_y(), pdf.w - pdf.r_margin, pdf.get_y())
    pdf.ln(5)

    started = False
    for raw in text.splitlines():
        line = raw.rstrip()
        if not started:
            if line.startswith("## 1."):
                started = True
            else:
                continue
        if line.strip() == "---":
            pdf.ln(2)
            continue
        if line.startswith("|") and "|" in line[1:]:
            body = line.replace("|", "").replace("-", "").replace(":", "").strip()
            if body == "":
                continue
            cells = [strip_md(c.strip()) for c in line.strip("|").split("|")]
            col_w = pdf.epw / max(len(cells), 1)
            pdf.set_font("DejaVu", "", 8)
            pdf.set_fill_color(245, 247, 250)
            x0 = pdf.get_x()
            y0 = pdf.get_y()
            if y0 > pdf.h - 25:
                pdf.add_page()
                x0 = pdf.get_x()
                y0 = pdf.get_y()
            row_h = 6
            for i, cell in enumerate(cells):
                while pdf.get_string_width(cell) > col_w - 2 and len(cell) > 4:
                    cell = cell[:-4] + "…"
                pdf.set_xy(x0 + i * col_w, y0)
                pdf.cell(col_w, row_h, cell, border=1, fill=True)
            pdf.set_xy(pdf.l_margin, y0 + row_h)
            continue
        pdf.set_x(pdf.l_margin)
        if line.startswith("### "):
            pdf.ln(2)
            pdf.set_font("DejaVu", "B", 11)
            pdf.set_text_color(37, 99, 235)
            pdf.multi_cell(0, 6, strip_md(line[4:]))
            pdf.set_text_color(30, 30, 35)
            continue
        if line.startswith("## "):
            pdf.ln(3)
            pdf.set_font("DejaVu", "B", 13)
            pdf.set_text_color(20, 40, 70)
            pdf.multi_cell(0, 7, strip_md(line[3:]))
            pdf.set_text_color(30, 30, 35)
            continue
        if line.startswith("- "):
            pdf.set_font("DejaVu", "", 10)
            pdf.multi_cell(0, 5, "- " + strip_md(line[2:]))
            continue
        if re.match(r"^\d+\.\s", line):
            pdf.set_font("DejaVu", "", 10)
            pdf.multi_cell(0, 5, strip_md(line))
            continue
        if line.startswith("*") and line.endswith("*") and not line.startswith("**"):
            pdf.set_font("DejaVu", "I", 9)
            pdf.set_text_color(90, 90, 100)
            pdf.multi_cell(0, 5, strip_md(line.strip("*")))
            pdf.set_text_color(30, 30, 35)
            continue
        if not line.strip():
            pdf.ln(2)
            continue
        pdf.set_font("DejaVu", "", 10)
        pdf.multi_cell(0, 5, strip_md(line))

    PDF.parent.mkdir(parents=True, exist_ok=True)
    pdf.output(str(PDF))
    print(f"Wrote {PDF} ({PDF.stat().st_size} bytes)")


if __name__ == "__main__":
    main()
