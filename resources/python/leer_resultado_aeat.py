"""Importe a ingresar de un PDF de autoliquidación de la AEAT (borrador o justificante de 111, 115, 202, 216, 123, 130…).
Uso: python leer_resultado_aeat.py fichero.pdf -> JSON {"resultado": 965.71, "veces": 2} o {"error": "..."}.
En estos formularios el resultado sale repetido (casilla de resultado e «Importe del ingreso» / «Importe (casilla [34] ó [03])»),
mientras que bases y percepciones salen una vez: se toma el importe que más se repite (si empatan, el mayor).
Lo usa Appmos (Impuestos → correo al cliente) para rellenar los importes que no son del 303 (15-oct-2026)."""
import collections
import json
import re
import sys

import pymupdf

IMPORTE = re.compile(r'(?<![\d.,])(-?\d{1,3}(?:\.\d{3})*,\d{2})(?![\d,])')


def resultado(ruta):
    doc = pymupdf.open(ruta)
    texto = ''.join(p.get_text() for p in doc)
    vals = [float(m.replace('.', '').replace(',', '.')) for m in IMPORTE.findall(texto)]
    vals = [v for v in vals if abs(v) >= 0.01]
    if not vals:
        raise ValueError('no hay importes (¿declaración negativa o PDF escaneado?)')
    cuenta = collections.Counter(vals)
    v, n = max(cuenta.items(), key=lambda kv: (kv[1], abs(kv[0])))
    if n < 2 and len(cuenta) > 1:
        raise ValueError('no sé cuál es el resultado: ningún importe se repite')
    return v, n


if __name__ == '__main__':
    try:
        v, n = resultado(sys.argv[1])
        print(json.dumps({'resultado': v, 'veces': n}))
    except Exception as e:
        print(json.dumps({'error': str(e)}))
