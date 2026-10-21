#!/usr/bin/env python3
"""Lee la tabla «M216 · no residentes» del libro de IVA 303 (hoja RECIBIDAS, columnas O:R) y saca los datos de la declaración.
Uso: lee216.py <libro.xlsx> | lee216.py --pdf <216.pdf>  →  JSON por stdout: {proveedores:[{nombre,pais,total}], n, total, control}.
Solo biblioteca estándar. Declaración NEGATIVA (sin retenciones): casilla 17 = n proveedores, 18/19 = total (Total factura)."""
import json, re, sys, zipfile
from xml.etree import ElementTree as ET

NS = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main'}
T = '{%s}' % NS['m']


def leer(path):
    z = zipfile.ZipFile(path)
    ss = []
    if 'xl/sharedStrings.xml' in z.namelist():
        for si in ET.fromstring(z.read('xl/sharedStrings.xml')).findall('m:si', NS):
            ss.append(''.join(t.text or '' for t in si.iter(T + 't')))
    wb = ET.fromstring(z.read('xl/workbook.xml'))
    rels = {r.get('Id'): r.get('Target') for r in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
    rid = '{http://schemas.openxmlformats.org/officeDocument/2006/relationships}id'
    for s in wb.find('m:sheets', NS):
        if s.get('name').upper() != 'RECIBIDAS':
            continue
        tgt = rels[s.get(rid)].lstrip('/')
        sh = ET.fromstring(z.read(tgt if tgt.startswith('xl/') else 'xl/' + tgt))
        cel = {}
        for c in sh.iter(T + 'c'):
            v = c.find('m:v', NS)
            t = c.get('t')
            if t == 's' and v is not None:
                val = ss[int(v.text)]
            elif t == 'inlineStr':
                val = ''.join(x.text or '' for x in c.iter(T + 't'))
            elif v is not None:
                val = v.text
            else:
                continue
            cel[c.get('r')] = val
        return cel
    raise SystemExit(json.dumps({'error': 'el libro no tiene hoja RECIBIDAS'}))


def num(v):
    try:
        return float(v)
    except (TypeError, ValueError):
        return 0.0


def leer_pdf(path):
    """Casillas de un 216 presentado (PDF de la AEAT): {n: casilla 17, base: casilla 18}. Necesita pymupdf (venv de Facturas OCR del servidor)."""
    import pymupdf
    pal = []
    for pg in pymupdf.open(path):
        pal += [(round((w[1] + w[3]) / 2), w[0], w[4]) for w in pg.get_text('words')]
    for y in sorted({a[0] for a in pal}):
        fila = sorted((x, t) for yy, x, t in pal if abs(yy - y) <= 3)
        txt = [t for _, t in fila]
        if '15' in txt and txt.index('15') + 2 < len(txt) and txt[txt.index('15') + 2] == '18' and re.match(r'^[\d.]+,\d{2}$', txt[-1]):
            return {'n': int(txt[txt.index('15') + 1]), 'base': float(txt[-1].replace('.', '').replace(',', '.'))}
    return None


def main():
    if len(sys.argv) > 2 and sys.argv[1] == '--pdf':
        print(json.dumps(leer_pdf(sys.argv[2])))
        return
    cel = leer(sys.argv[1])
    ini = next((int(r[1:]) for r, v in cel.items() if r.startswith('O') and re.match(r'O\d+$', r) and str(v).startswith('M216')), None)
    if ini is None:
        print(json.dumps({'error': 'el libro no lleva la tabla M216 (¿la empresa tiene el 216 activo en Entidades → Impuestos?)'}))
        return
    prov, fila, control = [], ini + 3, None
    while True:
        nom = cel.get('O%d' % fila)
        if nom is None:
            break
        if nom.startswith('Total general'):
            ctl = cel.get('Q%d' % (fila + 1))
            control = None if ctl is None else num(ctl)
            break
        prov.append({'nombre': nom, 'pais': cel.get('P%d' % fila, ''), 'total': round(num(cel.get('Q%d' % fila)), 2)})
        fila += 1
    print(json.dumps({'proveedores': prov, 'n': len(prov), 'total': round(sum(p['total'] for p in prov), 2), 'control': control}, ensure_ascii=False))


main()
