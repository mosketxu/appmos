#!/usr/bin/env python3
"""Modelo 349 (operaciones intracomunitarias) desde el Excel anual del cliente (16-oct-2026, hecho para NAE Comunicacions).

El Excel trae todo el año en dos pestañas:
  - «VENTAS INTR.»  (Fecha | NIF | Nº Fra | Cliente | Importe)        → clave S (prestaciones de servicios)
  - «COMPRAS INTR.» (FECHA | NIF | NOMBRE | Nº FRA. | IMPORTE | IVA)  → clave I (adquisiciones de servicios)
Se toman las filas del BLOQUE del mes pedido (decisión de Alex, 16-oct-2026: manda el bloque, no la fecha de la factura; una factura puede haberse
declarado en otro mes y no se toca). Cada bloque acaba en su fila de total, que lleva el mes (una fecha «01/MM/AAAA» o «Total sep 2026»). Si la
fecha de una factura no es del mes de su bloque, solo se avisa. Se agrupan por NIF y clave y se suman
(las facturas rectificativas del mismo mes restan). Operador con suma 0 → no se declara. Suma negativa → aviso (sería una rectificación
de un periodo anterior, que va en registros de rectificación: no hecho aún). Una celda «No declarar <nombre>» excluye a ese operador.

Uso: prepara349.py spec.json   (spec: xlsx, ejercicio, periodo '09', nif, nombre, contacto, telefono, salida)
Deja en `salida`: «<MM> <NIF>.349» (fichero para importar en la Sede de la AEAT, 500 caracteres por registro, ISO-8859-1) y resumen.json.
Formato copiado de los .349 que se presentaron en 2026 (carpeta del cliente) y del diseño de registro de la AEAT.
Solo biblioteca estándar: vale en el VPS y en cualquier PC.
"""
import datetime
import json
import re
import sys
import unicodedata
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path

NS = {'m': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
      'r': 'http://schemas.openxmlformats.org/officeDocument/2006/relationships'}
PAISES_UE = {'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT', 'NL', 'PL', 'PT',
             'RO', 'SE', 'SI', 'SK', 'XI'}


def norm(t):
    t = unicodedata.normalize('NFD', str(t or '')).encode('ascii', 'ignore').decode()
    return re.sub(r'\s+', ' ', t).strip().upper()


def col_idx(ref):
    letras = re.match(r'[A-Z]+', ref).group(0)
    n = 0
    for ch in letras:
        n = n * 26 + ord(ch) - 64
    return n - 1


def leer_xlsx(ruta):
    """{nombre_hoja: [[valores por columna], ...]} con valores ya calculados (número o texto)."""
    z = zipfile.ZipFile(ruta)
    ss = []
    if 'xl/sharedStrings.xml' in z.namelist():
        for si in ET.fromstring(z.read('xl/sharedStrings.xml')).findall('m:si', NS):
            ss.append(''.join(t.text or '' for t in si.iter('{%s}t' % NS['m'])))
    wb = ET.fromstring(z.read('xl/workbook.xml'))
    rels = {r.get('Id'): r.get('Target') for r in ET.fromstring(z.read('xl/_rels/workbook.xml.rels'))}
    hojas = {}
    for s in wb.find('m:sheets', NS):
        t = rels[s.get('{%s}id' % NS['r'])].lstrip('/')
        t = t if t.startswith('xl/') else 'xl/' + t
        filas = []
        for row in ET.fromstring(z.read(t)).iter('{%s}row' % NS['m']):
            vals = {}
            for c in row.findall('m:c', NS):
                v = c.find('m:v', NS)
                tipo = c.get('t')
                x = v.text if v is not None else None
                if tipo == 's' and x is not None:
                    x = ss[int(x)]
                elif tipo == 'inlineStr':
                    x = ''.join(t.text or '' for t in c.iter('{%s}t' % NS['m']))
                elif tipo in ('str', 'e'):
                    pass
                elif x is not None:
                    try:
                        x = float(x)
                    except ValueError:
                        pass
                if x not in (None, ''):
                    vals[col_idx(c.get('r'))] = x
            if vals:
                fila = [None] * (max(vals) + 1)
                for i, x in vals.items():
                    fila[i] = x
                filas.append((int(row.get('r')), fila))
        hojas[s.get('name')] = filas
    return hojas


def fecha_excel(v):
    if isinstance(v, float) and 20000 < v < 80000:
        return datetime.date(1899, 12, 30) + datetime.timedelta(days=int(v))
    if isinstance(v, str):
        m = re.match(r'^\s*(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})', v)
        if m:
            return datetime.date(int(m.group(3)), int(m.group(2)), int(m.group(1)))
    return None


MESES = {'ENE': 1, 'FEB': 2, 'MAR': 3, 'ABR': 4, 'MAY': 5, 'JUN': 6, 'JUL': 7, 'AGO': 8, 'SEP': 9, 'SET': 9, 'OCT': 10, 'NOV': 11, 'DIC': 12}
NOMBRE_MES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']


def mes_de_total(f, cols):
    """Si la fila es el total de un bloque (sin NIF, con importe), el (año, mes) que lleva: una fecha en una columna que no sea de importes
    (p. ej. 01/09/2026) o un texto «Total sep 2026». None si no lo es."""
    nif = f[cols['nif']] if cols['nif'] < len(f) else None
    if isinstance(nif, str) and nif.strip():
        return None
    for i, x in enumerate(f):
        if i >= cols['importe']:
            break
        if isinstance(x, float):
            d = fecha_excel(x)
            if d:
                return (d.year, d.month)
        elif isinstance(x, str):
            m = re.search(r'TOTAL\s+([A-Z]{3})[A-Z]*\.?\s+(\d{4})', norm(x))
            if m and m.group(1) in MESES:
                return (int(m.group(2)), MESES[m.group(1)])
    return None


def leer_hoja(filas, clave, ejercicio, mes, avisos):
    """Filas de operaciones del bloque del mes: [{fila, fecha, nif, nombre, factura, importe, clave}] + nombres a no declarar."""
    no_declarar = []
    cols = None
    ops = []
    bloque = []   # filas desde el último total
    for nfila, f in filas:
        textos = [norm(x) for x in f if isinstance(x, str)]
        for t in textos:
            m = re.match(r'^NO DECLARAR\s+(.+)$', t)
            if m:
                no_declarar.append(m.group(1).strip())
        cab = [norm(x) if isinstance(x, str) else '' for x in f]
        if 'NIF' in cab and any(c.startswith('IMPORTE') for c in cab):
            cols = {'fecha': next((i for i, c in enumerate(cab) if c == 'FECHA'), 0), 'nif': cab.index('NIF'),
                    'importe': next(i for i, c in enumerate(cab) if c.startswith('IMPORTE')),
                    'nombre': next((i for i, c in enumerate(cab) if c in ('CLIENTE', 'NOMBRE', 'PROVEEDOR', 'RAZON SOCIAL')), None),
                    'factura': next((i for i, c in enumerate(cab) if c.startswith('N') and 'FRA' in c), None)}
            continue
        if not cols:
            continue
        cierre = mes_de_total(f, cols)
        if cierre:
            if cierre == (ejercicio, mes):
                ops += bloque
            bloque = []
            continue
        g = lambda k: f[cols[k]] if cols.get(k) is not None and cols[k] < len(f) else None
        fe, nif, imp = fecha_excel(g('fecha')), g('nif'), g('importe')
        if not fe or not isinstance(nif, str) or not nif.strip() or not isinstance(imp, float):
            continue
        bloque.append({'fila': nfila, 'fecha': fe.strftime('%d/%m/%Y'), 'nif': re.sub(r'[\s.-]', '', nif).upper(), 'nombre': str(g('nombre') or '').strip(),
                    'factura': str(g('factura') or '').strip(), 'importe': round(imp, 2), 'clave': clave})
    if cols is None:
        avisos.append(f'No encuentro la fila de cabecera (NIF … Importe) en la pestaña de clave {clave}.')
    if bloque:   # filas después del último total: no tienen bloque
        avisos.append(f'Pestaña de clave {clave}: {len(bloque)} facturas después del último total, sin bloque de mes; no se declaran.')
    for o in ops:
        d, m_, a = (int(x) for x in o['fecha'].split('/'))
        if (a, m_) != (ejercicio, mes):
            avisos.append(f'{o["nombre"]} · factura {o["factura"]} de {o["fecha"]}: está en el bloque de {NOMBRE_MES[mes]}, se declara en {NOMBRE_MES[mes]}.')
    return ops, no_declarar


def campo_txt(t, n):
    t = unicodedata.normalize('NFC', str(t or '')).upper()
    t = re.sub(r'\s+', ' ', t).strip()
    t = t.encode('latin-1', 'replace').decode('latin-1').replace('?', ' ') if any(ord(c) > 255 for c in t) else t
    return t[:n].ljust(n)


def campo_num(v, n):
    return str(int(v)).rjust(n, '0')[:n]


def centimos(v):
    return int(round(abs(v) * 100))


def generar(spec):
    ejercicio = int(spec['ejercicio'])
    periodo = str(spec['periodo'])
    if not re.fullmatch(r'0[1-9]|1[0-2]', periodo):
        raise SystemExit(f'periodo no válido para el 349 mensual: {periodo!r}')
    mes = int(periodo)
    salida = Path(spec['salida'])
    salida.mkdir(parents=True, exist_ok=True)
    nif_decl = re.sub(r'[^A-Z0-9]', '', str(spec['nif']).upper())
    nif_decl = nif_decl[2:] if nif_decl.startswith('ES') else nif_decl
    avisos, errores = [], []

    hojas = leer_xlsx(spec['xlsx'])
    ops, excluir = [], []
    usadas = []
    for nombre, filas in hojas.items():
        n = norm(nombre)
        clave = 'S' if 'VENTA' in n or 'ENTREGA' in n or 'EMITIDA' in n else ('I' if 'COMPRA' in n or 'ADQUISIC' in n or 'RECIBIDA' in n else None)
        if not clave:
            continue
        usadas.append(f'{nombre} → clave {clave}')
        o, nd = leer_hoja(filas, clave, ejercicio, mes, avisos)
        ops += o
        excluir += nd
    if not usadas:
        errores.append('El Excel no tiene pestañas de ventas (emitidas) ni compras (recibidas) intracomunitarias.')

    # «No declarar X»
    quitadas = []
    if excluir:
        for op in list(ops):
            if any(x in norm(op['nombre']) or norm(op['nombre']) in x for x in excluir if x):
                ops.remove(op)
                quitadas.append(op)
        for x in excluir:
            n = sum(1 for q in quitadas if x in norm(q['nombre']) or norm(q['nombre']) in x)
            if n:
                avisos.append(f'El Excel dice «No declarar {x.title()}»: quitadas {n} líneas de este mes.')

    # agrupar por (clave, país, NIF)
    grupos = {}
    for op in ops:
        nif = op['nif']
        pais, resto = nif[:2], nif[2:]
        if pais == 'GR':
            pais = 'EL'
        if pais not in PAISES_UE or not resto:
            errores.append(f'Fila {op["fila"]}: «{op["nif"]}» ({op["nombre"]}) no empieza por un código de país de la UE; no se declara.')
            continue
        k = (op['clave'], pais, resto)
        g = grupos.setdefault(k, {'clave': op['clave'], 'pais': pais, 'nif': resto, 'nombre': op['nombre'], 'importe': 0.0, 'lineas': []})
        g['importe'] = round(g['importe'] + op['importe'], 2)
        g['lineas'].append(op)
        if len(op['nombre']) > len(g['nombre']):
            g['nombre'] = op['nombre']

    operadores, anulados = [], []
    for g in grupos.values():
        if abs(g['importe']) < 0.005:
            anulados.append(g)
            avisos.append(f'{g["nombre"]} ({g["pais"]}{g["nif"]}, clave {g["clave"]}): las facturas del mes se anulan entre sí (suma 0); no se declara.')
        elif g['importe'] < 0:
            errores.append(f'{g["nombre"]} ({g["pais"]}{g["nif"]}, clave {g["clave"]}): suma negativa {g["importe"]:.2f} € → es una rectificación de un '
                           'periodo anterior; va en registro de rectificación (aún no lo hace el programa). No se incluye.')
        else:
            operadores.append(g)
    operadores.sort(key=lambda g: ({'S': 0, 'E': 1, 'A': 2, 'I': 3}.get(g['clave'], 9), -g['importe']))
    for g in operadores:
        if len(norm(g['nombre'])) > 40:
            avisos.append(f'Nombre recortado a 40 caracteres: {g["nombre"]}')

    total = round(sum(g['importe'] for g in operadores), 2)
    contacto, telefono = str(spec.get('contacto') or ''), re.sub(r'\D', '', str(spec.get('telefono') or ''))
    if not contacto or len(telefono) != 9:
        avisos.append('Falta la persona de contacto o el teléfono (9 cifras) para la cabecera.')

    fichero = None
    if operadores:
        cab = ('1349' + str(ejercicio) + nif_decl.rjust(9)[:9] + campo_txt(spec.get('nombre'), 40) + ' ' + telefono.rjust(9, '0')[:9]
               + campo_txt(contacto, 40) + '349' + '0' * 10 + '  ' + '0' * 13 + periodo + campo_num(len(operadores), 9) + campo_num(centimos(total), 15)
               + campo_num(0, 9) + campo_num(0, 15))
        regs = [cab.ljust(500)]
        for g in operadores:
            r = ('2349' + str(ejercicio) + nif_decl.rjust(9)[:9] + ' ' * 58 + g['pais'] + g['nif'].ljust(15)[:15] + campo_txt(g['nombre'], 40)
                 + g['clave'] + campo_num(centimos(g['importe']), 13))
            regs.append(r.ljust(500))
        assert all(len(r) == 500 for r in regs)
        fichero = f'{periodo} {nif_decl}.349'
        (salida / fichero).write_bytes(('\n'.join(regs) + '\n').encode('latin-1'))
    elif not errores:
        avisos.append('Ninguna operación intracomunitaria este mes: no hay que presentar el 349 (o márcalo «No presenta» en Seguimiento).')

    res = {'ejercicio': ejercicio, 'periodo': periodo, 'pestanas': usadas, 'fichero': fichero, 'total': total, 'n_operadores': len(operadores),
           'operadores': [{k: g[k] for k in ('clave', 'pais', 'nif', 'nombre', 'importe')} | {'facturas': len(g['lineas'])} for g in operadores],
           'anulados': [{k: g[k] for k in ('clave', 'pais', 'nif', 'nombre')} | {'facturas': len(g['lineas'])} for g in anulados],
           'lineas': sorted(ops, key=lambda o: (o['clave'] != 'S', o['fecha'][6:] + o['fecha'][3:5] + o['fecha'][:2], o['fila'])),
           'excluidas': quitadas, 'avisos': avisos, 'errores': errores}
    (salida / 'resumen.json').write_text(json.dumps(res, ensure_ascii=False, indent=1), encoding='utf-8')
    return res


if __name__ == '__main__':
    r = generar(json.loads(Path(sys.argv[1]).read_text(encoding='utf-8')))
    print(json.dumps({k: r[k] for k in ('fichero', 'n_operadores', 'total', 'avisos', 'errores')}, ensure_ascii=False))
