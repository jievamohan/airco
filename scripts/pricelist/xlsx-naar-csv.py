#!/usr/bin/env python3
"""Zet een leveranciersprijslijst (.xlsx) om in het CSV-formaat dat de
catalogus-importeur leest.

    python3 scripts/pricelist/xlsx-naar-csv.py "Prijslijst MHI 2026.xlsx" \
        apps/api/database/data/pricelists/mhi-2026.csv

Vereist openpyxl (pip install openpyxl). Er wordt niets omgerekend: de bruto-
en nettobedragen komen ongewijzigd uit het werkblad. De vertaalslag naar onze
catalogus staat in app/Services/Pricing/PriceListRegistry.php, zodat de
brondata een letterlijke kopie blijft.

De werkbladen zijn opgebouwd als: een sectiekop in kolom A, daaronder de
artikelen. Een artikel herken je aan een bruto- én nettobedrag; een kop aan een
lege kolom B of een lijnmarkering ("M LINE") daarin.
"""

import csv
import sys

import openpyxl

LIJNMARKERINGEN = {'M LINE', 'T LINE', 'A LINE'}


def is_getal(waarde):
    return isinstance(waarde, (int, float)) and not isinstance(waarde, bool)


def schoon(waarde):
    if waarde is None:
        return None
    return waarde if is_getal(waarde) else str(waarde).strip()


def converteer(bron: str, doel: str) -> None:
    werkboek = openpyxl.load_workbook(bron, read_only=True, data_only=True)
    regels = []

    for blad in werkboek.worksheets:
        sectie = ''

        for rij in blad.iter_rows(min_col=1, max_col=5, values_only=True):
            a, b, c, d, e = (schoon(v) for v in rij)

            if a in (None, '') and b in (None, ''):
                continue
            if a == 'ARTIKELNUMMER:':
                continue

            if is_getal(c) and c > 0 and is_getal(d) and d > 0:
                regels.append({
                    'blad': blad.title,
                    'sectie': sectie,
                    'artikelnummer': '' if a is None else str(a),
                    'product': '' if b is None else str(b),
                    'bruto_eur': round(float(c), 2),
                    'netto_eur': round(float(d), 4),
                    'korting_pct': round(float(e) * 100, 2) if is_getal(e) else '',
                })
                continue

            # Een sectiekop staat in kolom A, met kolom B leeg of alleen een
            # lijnmarkering. Staat er een productnaam naast, dan is het een
            # artikel zonder prijs ("op aanvraag") en blijft de sectie staan.
            if a in (None, '') or is_getal(a) or '©' in str(a):
                continue
            if b in (None, '') or str(b).upper() in LIJNMARKERINGEN:
                sectie = str(a)

    with open(doel, 'w', newline='', encoding='utf-8') as bestand:
        # lineterminator expliciet: csv schrijft standaard \r\n, en dan komt er
        # bij elke nieuwe lijst een diff van het hele bestand doorheen.
        schrijver = csv.DictWriter(bestand, lineterminator='\n', fieldnames=[
            'blad', 'sectie', 'artikelnummer', 'product',
            'bruto_eur', 'netto_eur', 'korting_pct',
        ])
        schrijver.writeheader()
        schrijver.writerows(regels)

    print(f'{doel}: {len(regels)} regels')


if __name__ == '__main__':
    if len(sys.argv) != 3:
        raise SystemExit('gebruik: xlsx-naar-csv.py <bron.xlsx> <doel.csv>')
    converteer(sys.argv[1], sys.argv[2])
