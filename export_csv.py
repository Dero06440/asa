"""
export_csv.py - Exporte la liste des arrosants Excel vers CSV pour import web.
"""

import csv
import os
from datetime import datetime

import openpyxl

EXCEL_FILE = os.path.join(os.path.dirname(__file__), "..", "ANNEE 2026", "DOSSIERS FICHIERS  ARROSANTS", "Liste des Arrosants  2025  VE 5 25.xlsx")
OUTPUT_CSV = os.path.join(os.path.dirname(__file__), "arrosants_import.csv")

COL_CIVILITE = 1
COL_NOM = 2
COL_ANNEE = 4
COL_RUE = 5
COL_QUARTIER = 6
COL_CP = 7
COL_VILLE = 8
COL_PARCELLES = 9
COL_SURFACE = 10
COL_TAXE = 11
COL_DATE_MAJ = 14


def clean(val):
    if val is None:
        return ""
    if isinstance(val, float) and val != val:
        return ""
    return str(val).strip()


def format_date(val):
    if val is None:
        return ""
    if isinstance(val, datetime):
        return val.strftime("%d/%m/%Y")
    return clean(val)


def main():
    wb = openpyxl.load_workbook(EXCEL_FILE, data_only=True)
    ws = wb["Arrosants"]

    rows_written = 0
    rows_skipped = 0

    with open(OUTPUT_CSV, "w", newline="", encoding="utf-8-sig") as csvfile:
        writer = csv.writer(csvfile, delimiter=";", quoting=csv.QUOTE_MINIMAL)
        writer.writerow([
            "civilite",
            "nom",
            "annee",
            "rue",
            "quartier",
            "code_postal",
            "ville",
            "parcelles",
            "surface_m2",
            "taxe_annuelle",
            "date_maj",
        ])

        for row in ws.iter_rows(min_row=2, values_only=True):
            nom = clean(row[COL_NOM])
            if not nom:
                rows_skipped += 1
                continue

            if "MONTANT TOTAL" in nom.upper() or "TOTAL" in nom.upper():
                rows_skipped += 1
                continue

            surface = ""
            if row[COL_SURFACE] not in (None, ""):
                try:
                    surface = f"{float(row[COL_SURFACE]):.2f}"
                except (ValueError, TypeError):
                    surface = ""

            taxe = ""
            if row[COL_TAXE] not in (None, ""):
                try:
                    taxe = f"{float(row[COL_TAXE]):.2f}"
                except (ValueError, TypeError):
                    taxe = ""

            writer.writerow([
                clean(row[COL_CIVILITE]),
                nom,
                clean(row[COL_ANNEE]) or "2025",
                clean(row[COL_RUE]),
                clean(row[COL_QUARTIER]),
                clean(row[COL_CP]),
                clean(row[COL_VILLE]),
                clean(row[COL_PARCELLES]),
                surface,
                taxe,
                format_date(row[COL_DATE_MAJ]),
            ])
            rows_written += 1

    print(f"OK Export termine : {rows_written} arrosants exportes, {rows_skipped} lignes ignorees.")
    print(f"Fichier genere : {os.path.abspath(OUTPUT_CSV)}")


if __name__ == "__main__":
    main()
