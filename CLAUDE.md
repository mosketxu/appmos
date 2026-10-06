# Appmos — guía para Claude (carpeta de la app)

App web de Suma Apoyo Empresarial: entidades/facturación, procesos de Contabilidad, TO-DO y panel de control.
Laravel 12 (PHP 8.5), Livewire 3, Jetstream 4, spatie/laravel-permission, Tailwind 2 compilado con Laravel Mix.
Repo: `github.com/mosketxu/appmos`, rama `master`. Producción: https://appmos.sumaempresa.com.
**Responder siempre en español.** Lo común a todos los PCs (sincronización, correo, Contabilidad) está en el
`CLAUDE.md` de la carpeta Claude (`/mnt/e/Claude` en AlexMiniPC, `/mnt/f/Claude` en PortalExomen): si algo de aquí lo
contradice, manda aquel. Mapa y memoria de Appmos: `.claude/memoria/appmos/appmos-resumen.md` de la carpeta Claude.

## Dónde está cada cosa

| Qué | Dónde |
|---|---|
| Rutas | `routes/web.php` (todo detrás de `auth:sanctum`, `verified`, `activo`), `routes/api.php` (API de los PCs trabajadores, cabecera `X-Token`) |
| Menú | `resources/views/livewire/menu.blade.php` (componente Livewire que cada página incluye con `@livewire('menu', …)`); NO es `navigation-menu` |
| Entidades: CNAE / Epígrafe IAE | columnas `entidades.cnae` y `entidades.epigrafe_iae` (texto libre, nulos, sin límite de 4 caracteres: puede haber varios; 6-oct-2026); `Ent.php` + `ent.blade.php` |
| Permisos y roles | `config/accesos.php` (lista única de permisos; Admin / Suma / Usuario). Seeder: `php artisan db:seed --class=AccesosSeeder` (no pisa lo cambiado a mano) |
| Entidades visibles por usuario | `App\Support\Accesos` (Responsable Suma + `entidad_user`); scope global en los modelos |
| Componentes | `app/Http/Livewire/` (`Contabilidad/*` = pestañas de Contabilidad; `Admin/*` = panel de control; `Todo*` = TO-DO) |
| Correo | `App\Support\GraphMail` (Microsoft Graph; credenciales `GRAPH_*` en el `.env`, no en git) |
| Cola de trabajadores | `App\Support\ColaTareas`, `TrabajadorApiController`, procesos permitidos en `config/contabilidad.php` → `tareas_procesos` |
| TO-DO y Claude automático | `Todo.php`, `TodoCampana.php`, `TodoClaudeEstado.php`, `App\Support\TodoClaude`, modelos `TodoTarea/TodoComentario/TodoAviso`. Operación: `Contabilidad/TrabajadorWeb/CLAUDE_TODO.md` de la carpeta Claude |
| Guías de cada pestaña de Contabilidad | `Contabilidad/<proceso>/PLAN.md` de la carpeta Claude (Bancos, IS, Neteges, FacturasOcr, ProcesosMensuales, Verifactu…) — **actualizarlas con cada cambio** |

Pestañas: Entidades · TO-DO · Contabilidad (Procesos FIQ, Facturación PDF, Durcal, Bancos, Facturas OCR, IS, Neteges,
Proc.Mensuales, Seguimiento, Certificados) · Panel de control (solo Admin). La Facturación del propio Appmos está oculta
(base del proyecto Verifactu).

## Entornos y despliegue

Cada sitio tiene su **propia BD** (la buena es la del VPS; para copiarla a un PC: `sudo mysqldump appmos` en el VPS →
`mysql appmos <` aquí, guardando antes copia en `~/backups_appmos/`).
- **AlexMiniPC:** se edita en `/mnt/e/Claude/appmos`; Apache sirve `/var/www/appmos` (`localhost:8000`). Tras un cambio: commit + push → `git pull` en `/var/www/appmos` → `php artisan view:clear`.
- **PortalExomen:** un único clon `~/appmos` (editar = desplegar; `localhost`).
- **VPS:** `ssh mosketxu@100.110.4.69`, `/var/www/appmos` (git de root): `sudo git pull` → `chown mosketxu` **solo de los ficheros tocados** (nunca `-R`: rompe `storage/` y `bootstrap/cache/`, que son de `www-data`) → `sudo -u www-data php artisan migrate --force` (si hay migraciones) → `sudo -u www-data php artisan view:clear` (+ `config:clear` si cambió config). Para `tinker`: `sudo -u www-data env HOME=/tmp php artisan tinker`.
- Push a GitHub por SSH (`git@github.com:…`); HTTPS no tiene credenciales en WSL.
- **Si el modo automático deniega el `ssh` al VPS («Production Deploy»):** no es un fallo del servidor; no buscar rodeos. Terminar lo demás y pedir a Alex `! ssh …`, una regla `Bash(ssh mosketxu@100.110.4.69:*)` o la orden explícita «ejecútalo tú». Antes de `pull`, mirar el VPS en solo lectura (otra sesión puede haber desplegado ya) y avisar si suben commits ajenos. Detalle: memoria `appmos-deploy`.
- Los commits llevan la línea `Co-Authored-By` que indique la sesión. Desplegar al VPS cuando Alex lo pida o lo haya pedido para ese trabajo; no subir cambios a producción por iniciativa propia.

## Tests y convenciones

- `php artisan test` usa **sqlite en memoria** (`phpunit.xml`): nunca contra la MySQL real. Hay **10 tests antiguos que fallan** (Entidades, Facturación, Registro): no son de cambios recientes; no dar un fallo nuevo por bueno comparando con esa cifra. Todo cambio con lógica lleva test (ver `tests/Feature/Todo*Test.php` de ejemplo).
- **Los tests nunca envían correo real ni hacen peticiones externas** (`tests/TestCase.php` anula las credenciales de Graph y activa `Http::preventStrayRequests()`); si un test prueba Graph, pone la config y `Http::fake()`. (Un test que mandó 9 correos reales desde el buzón de Alex ocurrió el 3-oct-2026.)
- **CSS:** `public/css/app.css` está compilado y purgado (Tailwind 2): una clase nueva puede no existir (p. ej. `amber-*` no, `yellow-*` sí). Para estilos nuevos usar `style=""`, o recompilar (`npm run production`) y desplegar el CSS.
- **Livewire 3:** `wire:model.live` para tiempo real; no `wire:model` anidado directo sobre un modelo Eloquent (usar arrays). En `@script` la **primera línea debe ser código** (un comentario delante rompe el script: Alpine lo trata como expresión). El arrastrar-y-soltar del TO-DO está probado con Chromium sin pantalla (Playwright en `Contabilidad/monthlyFIQ/anaplanWeb/node_modules` + `~/.cache/ms-playwright`): los tests PHP no ejecutan JavaScript, así que para UI con JS probar en navegador.
- Textos de la interfaz en español. Migraciones nuevas con fecha de hoy; las que tocan datos de producción, idempotentes.
- Antes de borrar o sobrescribir datos reales, mirar qué hay y guardar copia. Envíos a terceros: con confirmación de Alex y fichero oficial.

## Cosas que ya se decidieron (no reabrir sin hablar con Alex)

- Registro público cerrado; usuarios y roles los crea el Admin (entran con nombre.apellido@sumaempresa.com; `debe_cambiar_password` obliga a cambiarla la primera vez).
- Contraseña olvidada: el enlace sale por Graph desde `GRAPH_SENDER` (no por SMTP).
- Solo **Alex** (`CLAUDE_TODO_GESTORES`) pausa, reanuda y autoriza a Claude; la prioridad de cada usuario en el TO-DO es suya (un Admin la ve en «Ver tareas de», solo lectura; Alex además ordena la de Claude).
- **Entrada única (decisión de Alex, 3-oct-2026):** a Appmos solo se entra por la web. En un PC `ENTRADA_WEB_URL` (en el `.env`; **nunca en el VPS**) redirige TODAS las páginas a la misma ruta de la web (middleware `EntradaUnicaWeb`); para desarrollar en local se quita esa línea. Lista de excepciones (`entrada_web_excepciones`): **vacía** desde el 3-oct-2026 (Facturas OCR y Durcal ya están en la web); si algún día hay que poner una, es temporal. Todo proceso nuevo: pantalla en la web + ejecución por el trabajador. Los datos buenos están en la BD del VPS; no crear tareas ni nada en `localhost`.
- Permisos de Claude por tarea (scripts, correo, desplegar, ssh, borrar): solo los concede Alex; sin ellos no hay python/ssh/rm en las ejecuciones desatendidas.
- Claude automático: PC principal AlexMiniPC, secundario PortalExomen; pasadas cada hora o «Ejecutar ya»; freno con el uso real del plan ≥ 80 %.
- Al asignar entidades a un colaborador solo salen las activas.
- TO-DO: «⚑ Pedir prioridad» (creador/Admin) avisa por la campana sin tocar el orden de nadie; al asignar una tarea a otra persona sale un **correo por Graph** desde `GRAPH_SENDER` (OK de Alex 3-oct-2026; se apaga con `TODO_CORREO_ASIGNACION=false`).
