#!/usr/bin/env python3

from __future__ import annotations

import json
import sys
from pathlib import Path

from reportlab.lib import colors
from reportlab.lib.pagesizes import A4, landscape
from reportlab.lib.styles import ParagraphStyle, getSampleStyleSheet
from reportlab.lib.units import mm
from reportlab.platypus import Paragraph, SimpleDocTemplate, Spacer, Table, TableStyle


def format_money(value: object) -> str:
    amount = float(value or 0)
    return f"Gs. {amount:,.2f}".replace(",", "X").replace(".", ",").replace("X", ".")


def build_pdf(payload_path: Path, output_path: Path) -> None:
    payload = json.loads(payload_path.read_text(encoding="utf-8"))
    styles = getSampleStyleSheet()
    styles.add(
        ParagraphStyle(
            name="SmallMuted",
            parent=styles["Normal"],
            fontName="Helvetica",
            fontSize=8.5,
            leading=11,
            textColor=colors.HexColor("#66768a"),
        )
    )
    styles.add(
        ParagraphStyle(
            name="Cell",
            parent=styles["Normal"],
            fontName="Helvetica",
            fontSize=8,
            leading=10,
        )
    )

    doc = SimpleDocTemplate(
        str(output_path),
        pagesize=landscape(A4),
        leftMargin=14 * mm,
        rightMargin=14 * mm,
        topMargin=14 * mm,
        bottomMargin=14 * mm,
    )

    filters = payload.get("filters", {})
    filter_chunks = []
    if filters.get("client_label"):
        filter_chunks.append(f"Cliente: {filters['client_label']}")
    if filters.get("contract_number"):
        filter_chunks.append(f"Contrato: {filters['contract_number']}")
    if filters.get("identifier_number"):
        filter_chunks.append(f"ID: {filters['identifier_number']}")
    if not filter_chunks:
        filter_chunks.append("Sin filtros")

    story = [
        Paragraph("Industria Feris", styles["Title"]),
        Paragraph(str(payload.get("title", "Reporte operativo")), styles["Heading2"]),
        Paragraph(" | ".join(filter_chunks), styles["SmallMuted"]),
        Spacer(1, 6 * mm),
    ]

    columns = payload.get("columns", [])
    rows = payload.get("rows", [])
    table_data = [[Paragraph(str(column.get("label", "")), styles["Cell"]) for column in columns]]

    for row in rows:
        rendered = []
        for column in columns:
            value = row.get(column["key"], "")
            if column.get("money"):
                value = format_money(value)
            rendered.append(Paragraph(str(value or "-"), styles["Cell"]))
        table_data.append(rendered)

    if len(table_data) == 1:
        table_data.append([Paragraph("Sin resultados para los filtros indicados.", styles["Cell"])] + [""] * (len(columns) - 1))

    available_width = landscape(A4)[0] - doc.leftMargin - doc.rightMargin
    col_width = available_width / max(len(columns), 1)
    table = Table(table_data, colWidths=[col_width] * max(len(columns), 1), repeatRows=1)
    table.setStyle(
        TableStyle(
            [
                ("BACKGROUND", (0, 0), (-1, 0), colors.HexColor("#123b63")),
                ("TEXTCOLOR", (0, 0), (-1, 0), colors.white),
                ("FONTNAME", (0, 0), (-1, -1), "Helvetica"),
                ("GRID", (0, 0), (-1, -1), 0.5, colors.HexColor("#d8e1ea")),
                ("ROWBACKGROUNDS", (0, 1), (-1, -1), [colors.white, colors.HexColor("#f6f8fb")]),
                ("VALIGN", (0, 0), (-1, -1), "TOP"),
                ("LEFTPADDING", (0, 0), (-1, -1), 5),
                ("RIGHTPADDING", (0, 0), (-1, -1), 5),
                ("TOPPADDING", (0, 0), (-1, -1), 5),
                ("BOTTOMPADDING", (0, 0), (-1, -1), 5),
            ]
        )
    )

    story.append(table)
    story.append(Spacer(1, 4 * mm))
    story.append(Paragraph(f"Registros: {len(rows)}", styles["SmallMuted"]))
    doc.build(story)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("usage: render_report_pdf.py INPUT_JSON OUTPUT_PDF")

    build_pdf(Path(sys.argv[1]), Path(sys.argv[2]))
