# ToDO Alex.xlsx → Appmos (análisis, 1-oct-2026)

Excel de seguimiento de obligaciones fiscales por cliente que acabará entrando en Appmos.
Ruta: `OneDrive/_RUR_Marta_Alex/__AlexArregui info/ToDO Alex.xlsx`. Aquí solo estructura y reglas:
los nombres de clientes y el cruce con Appmos están en el CSV de al lado del Excel
(`ToDO Alex - responsables para Appmos.csv`), no en el repo.

## Hojas
| Hoja | Qué es |
|---|---|
| `2026`, `2025`, `2024` | Año en curso y anteriores: una fila por cliente (cabecera en la fila 7, datos desde la 8; filas 1-6 = notas/estados por columna y el trimestre de cada bloque). |
| `2023`, `Fact 2019-2020` | Formato antiguo (cabecera en la fila 3; columnas `Cliente`, `Ownner`, ...). |
| `Hoja3` | Tabla dinámica Cliente → Responsable (recuento por responsable). |

## Columnas de `2026` (A..S = datos del cliente)
| Col | Campo | En Appmos |
|---|---|---|
| A | Cod Cli (430xxx) | `entidades.cuentacontable` (ojo: 5 cuentas de Appmos son de otra entidad) |
| B | Cliente | `entidades.entidad` (nombres abreviados, cruce por cuenta y por nombre) |
| C | Obs | — |
| D/E/F | Mensual / Trimestral / Otros (importe cuota) | — (¿plan de facturación?) |
| G | Modo pago (Transf / Dom) | `metodopago_id` |
| H | Fecha (día) | `diafactura`? |
| I | Periodo (mes / trim / anual / puntual) | `cicloimpuesto_id` |
| J | Facturar (No) | `facturar` |
| K | Estado (inactivo / baja / liquidada) | `estado` |
| L | **Ownner** | **Responsable Suma** (`suma_id`) + asignadas (`entidad_user`) |
| M | Enviar (0/1) | `enviar` / `mail_peticion_check`? |
| N | Idioma (ES/EN) | `idioma` |
| O | Sexo | — (¿tratamiento en los correos?) |
| P/Q/R/S | Contacto / Mail a / CC / BCC (12 filas) | `emailadm`, `mail_peticion_cc` |

Desde la T: bloques por trimestre (T1..T4 + Anual) con una columna por modelo y mes
(303, 111, 115, 123, 130, 349, 202, 210, 216, Vtos, Bancos, Cierres, D2, IS, 390, 190, 193,
180, 296, 347…). Valores: `x`, `OK`, `OK J`, `no`, `no hace`, `Listo rev`, `No tiene`…
→ cuando se traiga: tabla de obligaciones (cliente, ejercicio, periodo, modelo, estado).

## Códigos de Owner → Responsable Suma
AA Alex Arregui · MR Marta Ruiz · SM Susana Martinez · MC Marta Carmona · DC Dolors Celdran ·
JO Julia Ortiz · MM Miriam Marin · NN (ya no está) → Miriam Marin · MoC Montse Casas (nueva) ·
OT Olga Tarrega (nueva) · SS Sara Salom. Combinaciones (`AA MR`, `AA-OT`, `MC MR`, `MR-MoC`,
`SS/DC`): el primero es el Responsable Suma y los demás se asignan a mano (panel de control,
`entidad_user`), así la empresa sale a todos ellos en Proc.Mensuales.

## Cruce con Appmos (VPS, 1-oct-2026)
212 filas: 100 por cuenta contable, 53 por nombre exacto, 27 por nombre aproximado,
12 revisadas a mano, 3 dudosas, 17 sin entidad en Appmos.
