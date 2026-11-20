<?php

/**
 * Ayuda de cada pantalla de Appmos (19-nov-2026, a petición de Alex): el icono del libro con «?» junto al título abre un modal con estas instrucciones.
 * Clave = nombre de la ruta; 'titulo' y 'html' (HTML propio, no viene de usuarios). Una pantalla nueva: añadir aquí su entrada (hay un test que lo exige para las del menú).
 * Mantenerlo corto: qué es, cómo se usa y qué hay que vigilar. El detalle vive en el PLAN.md / LEEME.md de cada proceso (carpeta Claude).
 */


return [
    'entidades' => ['titulo' => 'Entidades', 'html' => <<<'HTML'
<p>La lista de clientes y entidades del despacho. Cada uno ve solo las suyas (los permisos de «ver todas» las abren).</p>
<h4>Cómo se usa</h4>
<ul>
<li>Escribe en «Búsqueda» y filtra por <b>Clientes</b>, <b>Estado</b>, <b>Facturar</b> y <b>Responsable</b>; normalmente trabajas con las <b>activas</b>.</li>
<li>Abre una entidad para ver sus <b>contactos</b>, su <b>historial</b>, qué <b>impuestos</b> presenta (esa zona alimenta el seguimiento de Impuestos) y su plan de facturación.</li>
<li>Crear o editar entidades requiere el permiso de edición; sin él los botones no salen.</li>
<li>El botón <b>📂</b> de cada fila (y el de la cabecera de la ficha) sube los <b>ficheros base de SAGE</b> de esa entidad.</li>
<li><b>Responsable</b> se ve con iniciales; el nombre completo sale al pasar el ratón.</li>
<li>En la ficha, <b>cuenta contable y código</b> de cliente salen si es Cliente y los de proveedor si es Proveedor (los cuatro si es ambas cosas).</li>
</ul>
<h4>Ojo</h4>
<ul><li>Lo que pongas aquí (impuestos de cada cliente, cifras de cuenta de SAGE, estado) lo usan otras pantallas: Impuestos, Bancos, Facturas OCR…</li></ul>
HTML],

    'todo' => ['titulo' => 'Tareas (TO-DO)', 'html' => <<<'HTML'
<p>Tu lista de tareas. También es el canal para pedirle cosas a <b>Claude</b>.</p>
<h4>Cómo se usa</h4>
<ul>
<li>Crea una tarea, ponle prioridad y fecha y <b>asígnala</b> a quien toque (puede ser a varias personas).</li>
<li>Si la asignas a <b>Claude</b>, la hace sola cada hora (o con «Ejecutar ya»). Si la crea otra persona que no sea Alex, Alex tiene que autorizarla.</li>
<li>Las respuestas y dudas de Claude salen en la tarea y en la <b>campana</b> de arriba.</li>
<li>«Pedir prioridad» avisa al responsable sin cambiarle el orden.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Cada uno solo ve sus tareas. Los errores automáticos se cierran solos si no se repiten en 24 horas.</li></ul>
HTML],

    'todo.mejoras' => ['titulo' => 'Pedir mejora', 'html' => <<<'HTML'
<p>Aquí cualquiera puede pedir una mejora o contar un fallo de Appmos.</p>
<ul>
<li>Escribe qué quieres y para qué pantalla; cuanto más concreto, mejor.</li>
<li>Alex ve todas las peticiones y las va atendiendo; las verás cambiar de estado.</li>
</ul>
HTML],

    'formacion' => ['titulo' => 'Formación', 'html' => <<<'HTML'
<p>Pantalla personal de Alex con los temas que quiere explicar a los compañeros.</p>
<ul>
<li>Añade un tema, adjunta lo que haga falta y ordénalos arrastrando.</li>
<li>Marca los temas ya explicados; puedes descargar los adjuntos de un tema en un zip.</li>
</ul>
HTML],

    'correo' => ['titulo' => 'Correo', 'html' => <<<'HTML'
<p>Tu Outlook dentro de Appmos, con Claude a mano. <b>Cada uno ve solo su buzón.</b></p>
<h4>Las columnas</h4>
<ul>
<li><b>Carpetas</b> (izquierda): con los no leídos y las subcarpetas (▸). Arrastra un correo a una carpeta para moverlo.</li>
<li><b>Lista</b>: buscador, «Cargar más» y el filtro <b>💬 Solo con conversación con Claude</b>: enseña esos correos estén donde estén y, a la izquierda, <b>solo las carpetas que tienen alguno</b> (púlsalas para acotar).</li>
<li><b>El correo</b>: se ve en claro solo aquí. «🖼 Mostrar imágenes» es voluntario (las remotas pueden avisar al remitente). Pulsa una imagen (🖼) o un PDF (📄) adjunto para verlo aquí mismo. Botones <b>📁 Mover a…</b> y <b>🗑 Eliminar</b> (va a Elementos eliminados; queda leído).</li>
<li><b>Qué hacer con este correo</b> (derecha): escribe o <b>dicta 🎤</b> lo que quieres y elige cómo.</li>
</ul>
<h4>Pedirle cosas a Claude</h4>
<ul>
<li><b>🔒 Anonimizar y enviar a Claude</b>: el correo, sus adjuntos y tu texto pasan a etiquetas (Persona1, Empresa1…) y se envía solo; no ves la revisión. Un nombre que solo esté en tu texto puede quedar a la vista: añádelo en «Ocultar además estos nombres».</li>
<li><b>Solo anonimizar para revisarlo</b>: te deja revisar y dar por bueno antes de enviar.</li>
<li><b>Enviar SIN anonimizar</b>: sale todo en claro hacia Anthropic. Es decisión tuya y pide confirmación.</li>
<li>Claude suele tardar menos de un minuto. La conversación se queda con el correo aunque lo muevas.</li>
</ul>
<h4>Borradores en Outlook</h4>
<ul>
<li>Si pides redactar un correo, <b>se crea solo en tu carpeta Borradores</b> (con tu firma, logo y pie legal); nunca se envía. Si no lo ves, pulsa F9 en Outlook.</li>
<li>Puedes <b>adjuntar</b> (opcional) adjuntos del correo original o ficheros de tu equipo, antes o después de crearlo. Si lo que pides lo requiere, <b>Claude también los adjunta solo</b> al borrador (te lo dice debajo); revísalo en Outlook antes de enviar.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Es seudonimización de mejor esfuerzo: revisa lo que mandas. Si ves «sin permiso» en rojo, tu buzón aún no está habilitado: manda el texto a Alex.</li></ul>
HTML],

    'confidencialidad' => ['titulo' => 'Confidencialidad', 'html' => <<<'HTML'
<p>Para trabajar con Claude un documento <b>sin que salgan datos personales</b>: se anonimiza antes y a Claude solo le llega la versión con etiquetas.</p>
<h4>Cómo se usa</h4>
<ul>
<li><b>Sube</b> el documento (PDF, imagen, Excel, Word, texto, correo .eml…) o <b>trae un correo</b> por asunto. Se anonimiza solo.</li>
<li>Mira el <b>original</b> y el <b>anonimizado</b> lado a lado y oculta lo que falte (sugerencias, recuadros manuales, «Revisión de Claude» sobre lo ya anonimizado).</li>
<li><b>Dar por bueno</b> borra el original. Después escribe qué quieres en el chat; la respuesta sale con los datos reales.</li>
</ul>
<h4>Ojo</h4>
<ul>
<li>Es seudonimización, no anonimato legal: <b>revisa siempre</b>. Si el almacén cifrado está cerrado la pantalla avisa y no guarda nada.</li>
<li>Para el correo del día a día usa la pantalla <b>Correo</b>.</li>
</ul>
HTML],

    'impuestos' => ['titulo' => 'Impuestos: seguimiento', 'html' => <<<'HTML'
<p>El checklist de presentación de impuestos por <b>cliente, impuesto y periodo</b>.</p>
<h4>Cómo se usa</h4>
<ul>
<li>Cada casilla cambia de color: <b>rojo</b> pendiente → <b>naranja</b> en revisión → <b>azul</b> revisado → <b>verde</b> presentado (con su PDF).</li>
<li>Cada uno ve lo suyo; Admin y Suma tienen «ver todos».</li>
<li>El icono del documento de cada periodo es la <b>petición de documentación</b> al cliente.</li>
<li>Qué impuestos presenta cada cliente se define en <b>Entidades</b> (zona Impuestos): si falta una casilla, mira allí.</li>
<li>Los PDF de presentación los sube solo un PC desde OneDrive.</li>
</ul>
<p>Las subpestañas (IVA M303 / Libros IVA, IRPF M111, IS M200, Pago a cuenta M202, M349, M216, Intrastat) son los procesos de cada modelo.</p>
HTML],

    'impuestos.libro-iva' => ['titulo' => 'Libros IVA (303)', 'html' => <<<'HTML'
<p>Monta el <b>libro de IVA del 303</b> de un cliente a partir de sus Excel de la carpeta IVA de OneDrive.</p>
<ul>
<li>Elige la carpeta del cliente en el explorador (se recuerda).</li>
<li>Se genera el Excel «IVA T3 …» con expedidas y recibidas por bloques, subtotales por tipo, el resultado, una pestaña <b>CONTROL</b> (roja si no cuadra) y <b>AVISOS</b>.</li>
<li>Queda registrado en la casilla 303 del seguimiento y se sincroniza solo al editarlo en OneDrive.</li>
</ul>
<h4>Ojo</h4>
<ul><li>La carpeta tiene que estar en OneDrive. Mira siempre CONTROL y AVISOS antes de dar nada por bueno.</li></ul>
HTML],

    'impuestos.m111' => ['titulo' => 'IRPF Modelo 111', 'html' => <<<'HTML'
<p>Genera el fichero del <b>modelo 111</b> para la AEAT a partir del txt/PDF/mayor de nóminas y los profesionales de SAGE.</p>
<ul>
<li>Arriba está el recuadro «📂 Ficheros base de SAGE de esta empresa» (mayor, plan, proveedores…): se suben ahí y los usan todos los procesos.</li>
<li>Elige cliente y periodo, sube lo que pida la pantalla y lanza el proceso; lo ejecuta un PC trabajador.</li>
<li>Revisa el resultado antes de presentar.</li>
</ul>
HTML],

    'impuestos.m349' => ['titulo' => 'Modelo 349', 'html' => <<<'HTML'
<p>Declaración recapitulativa de <b>operaciones intracomunitarias</b>. De momento solo está operativo para <b>NAE Comunicacions</b>; otro cliente sale «en construcción».</p>
<ul>
<li>Elige cliente y mes, la carpeta de trabajo de OneDrive y el Excel anual (ventas → S, compras → I): se genera el fichero <b>.349</b> para la AEAT, que puedes descargar o guardar en OneDrive.</li>
<li><b>Marcar como revisado</b> (Alex o Marta) y, en el último paso, <b>Enviar justificante a Marta…</b>.</li>
<li>El borrador en la AEAT se abre con Edge <b>con ventana</b> en el PC de «Ejecutar en»; quien lo lanza elige el certificado.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Faltan las rectificaciones de periodos anteriores. Contrasta el resultado con lo presentado otros meses.</li></ul>
HTML],

    'impuestos.m216' => ['titulo' => 'Modelo 216', 'html' => <<<'HTML'
<p>Retenciones a <b>no residentes</b>.</p>
<ul>
<li>Elige cliente y periodo; la pantalla prepara lo necesario (incluida la declaración negativa cuando no hay retenciones).</li>
<li>Revisa el resultado antes de presentar.</li>
</ul>
HTML],

    'impuestos.intrastat' => ['titulo' => 'Intrastat', 'html' => <<<'HTML'
<p>Prepara la declaración <b>Intrastat</b> (Fashion IQ) del Excel «Outbound Fashion IQ PPI &amp; PS - MM.2026».</p>
<ul>
<li>Elige cliente y periodo, sube el Excel y pulsa «▶ Procesar»: se hace en el servidor y sustituye lo que hubiera de ese mes.</li>
<li>Sale el <b>CSV para la Sede</b> (⬇ descargar), el número de partidas y los avisos. Desde Procesos FIQ se puede lanzar igual y se marca el check del mes.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Lee los avisos antes de presentarlo: unidades y códigos mal puestos dieron un requerimiento de Aduana.</li></ul>
HTML],

    'impuestos.pago-cuenta' => ['titulo' => 'Pago a cuenta (M202)', 'html' => <<<'HTML'
<p>Pagos fraccionados del Impuesto sobre Sociedades (<b>modelo 202</b>).</p>
<ul>
<li>Arriba, los ficheros base de SAGE de la empresa; después elige la empresa y el periodo.</li>
<li>La pantalla calcula el pago y genera el fichero .202; revisa la modalidad (40.2 o 40.3) antes de presentarlo.</li>
</ul>
HTML],

    'contabilidad.is' => ['titulo' => 'Impuesto sobre Sociedades (M200)', 'html' => <<<'HTML'
<p>Prepara el fichero del <b>modelo 200</b> a partir de los datos contables.</p>
<ul>
<li>Sube los ficheros base de SAGE de la empresa (recuadro de arriba) y sigue los pasos de la pantalla.</li>
<li>Los datos buenos están en el servidor (no en tu PC). Revisa los avisos antes de presentar.</li>
</ul>
HTML],

    'contabilidad.procesos' => ['titulo' => 'Procesos FIQ', 'html' => <<<'HTML'
<p>Los procesos mensuales de <b>Fashion IQ</b>, lanzados desde la web. Es también el <b>checklist de cierre</b>: una fila por proceso y un check por mes.</p>
<h4>Cómo se usa</h4>
<ul>
<li>Elige el <b>año</b> y el <b>mes</b> (arriba); ese mes lo usan todos los procesos. Lanza cada proceso con su botón: lo ejecuta un PC trabajador (puedes elegirlo en «Ejecutar en») y siempre es «real».</li>
<li>A la derecha, el panel <b>Salida</b> enseña lo que va haciendo (con ⚠️ y «👉 Qué hacer» si algo falla); «Borrar salida» lo limpia. Al terminar bien, el check del mes se marca solo.</li>
<li>Los checks manuales se cambian con clic: ✓ → «no toca» → vacío. Los procesos con envío tienen 3 estados: vacío, <b>P</b> (preparado, sin enviar) y ✓ (enviado). Arrastrando ⠿ cambias el orden.</li>
<li>Los ficheros que pide cada proceso (settlement de Adyen, sumas y saldos, Excel de Intrastat…) se suben en su fila; el recuadro de arriba es el de los ficheros base de SAGE.</li>
<li>Con ⓘ ves el detalle de cada proceso. Los paneles de <b>Rentas variables</b>, <b>Pagos de fin de mes</b>, <b>Cash in store</b> y <b>Comunicar impuestos</b> se abren debajo de su fila («abrir ↓»).</li>
<li>Algunos van solos y solo los vigilas aquí: Fiscal Report diario («📅 Ver días») y UBS semanal.</li>
</ul>
<h4>Ojo</h4>
<ul>
<li>Si un proceso tarda, espera: no lo lances dos veces. En Anaplan, «🔄 Reiniciar» cancela y deja el navegador limpio.</li>
<li>Los botones «Split SyS Canal» y «Subir Web» de Anaplan empiezan <b>comprobando las nóminas</b>: compara, tienda a tienda, lo contabilizado en el mayor de SAGE (sueldos, ticket, pagas extras, finiquito, bonus, SS empresa) con la Imputación de costes del mes. Solo avisa en la Salida (⚠️ si algo no cuadra) y el proceso sigue.</li>
<li>El proceso <b>Open Sold Items</b> termina comprobando que el importe del PDF de cada factura/abono (carpetas de facturas recibidas y emitidas) coincide con el mayor, y lo repite al subir Anaplan. Solo avisa (⚠️ falta el PDF, sin contabilizar o importe distinto).</li>
<li>Los envíos a terceros (pagos de fin de mes, Plein, arrendatarios) piden confirmación en dos pasos y tienen «enviar a prueba»: úsalo primero.</li>
<li>Los avisos rojos (alarma de ventas, gastos sin canal, devoluciones de Adyen) no son errores del programa: son datos que hay que mirar.</li>
</ul>
HTML],

    'contabilidad.facturacion-pdf' => ['titulo' => 'Facturación PDF (Balerga / Suma)', 'html' => <<<'HTML'
<p>Separa los PDF de facturas, los renombra y los <b>envía por correo</b> a cada cliente.</p>
<ul>
<li>Sube el PDF maestro; revisa las páginas y los destinatarios (se pueden editar y se guardan).</li>
<li>El envío real va en <b>dos pasos</b> y hay un botón de <b>prueba</b> para mandártelo solo a ti primero.</li>
<li>Al terminar se guardan copias en las carpetas de cada cliente.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Es correo real a terceros: usa siempre el fichero oficial y confirma antes del envío.</li></ul>
HTML],

    'contabilidad.durcal' => ['titulo' => 'Durcal', 'html' => <<<'HTML'
<p>El proceso de <b>Durcal Software</b>: capitaliza (activa) sueldos y Seguridad Social por proyecto desde las nóminas y da de alta la amortización (3 años). Se ejecuta en un PC fijo.</p>
<ul>
<li>Sube el fichero de nóminas del mes y lanza el proceso. Primero va en <b>modo prueba</b> (fichero aparte); revisa y solo entonces se hace sobre el Excel de amortización real.</li>
<li>El estado y los resultados salen en la pantalla.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Los empleados a activar y su porcentaje por proyecto salen de la hoja de datos de Durcal: si hay cambios de plantilla, se actualizan allí.</li></ul>
HTML],

    'contabilidad.bancos' => ['titulo' => 'Bancos → SAGE', 'html' => <<<'HTML'
<p><b>Conciliación bancaria</b>: de los extractos al plugin de SAGE.</p>
<ul>
<li>Elige la empresa (los ficheros base de SAGE están en el recuadro de arriba) y sube el extracto o los extractos.</li>
<li>Revisa los movimientos, el proveedor y la cuenta propuestos; puedes cambiarlos (la pantalla te avisa si algo no cuadra, pero decides tú).</li>
<li>Genera el plugin y lo importas en SAGE.</li>
</ul>
<h4>Ojo</h4>
<ul><li>Los datos buenos están en el servidor. Si SAGE da error al importar, avisa: hay casos conocidos con el formato del Excel.</li></ul>
HTML],

    'contabilidad.facturas-ocr' => ['titulo' => 'Facturas OCR', 'html' => <<<'HTML'
<p>Facturas recibidas en PDF → <b>plugin de SAGE</b>, revisadas aquí.</p>
<ul>
<li>Las facturas se <b>suben siempre al servidor</b> (solo las que no conoce); las escaneadas pasan por OCR.</li>
<li>Revisa cada factura (proveedor, NIF, importes, periodo), corrige lo que haga falta y <b>valídala</b>.</li>
<li>Lo contabilizado se lleva a la carpeta de OneDrive con comprobación.</li>
</ul>
<h4>Ojo</h4>
<ul><li>El estado bueno es el del servidor: no trabajes Facturas OCR en tu PC.</li></ul>
HTML],

    'contabilidad.neteges' => ['titulo' => 'Neteges', 'html' => <<<'HTML'
<p><b>Conciliación bancaria de Neteges</b>, como Bancos pero con más ficheros (Ventas…).</p>
<ul>
<li>Sube los ficheros que pide la pantalla (arriba, los ficheros base de SAGE) y lanza el proceso; se ejecuta en un PC fijo.</li>
<li>Revisa el resultado y descarga lo generado.</li>
</ul>
HTML],

    'contabilidad.leoybra' => ['titulo' => 'LeoyBra', 'html' => <<<'HTML'
<p>Del <b>Excel trimestral de Grupo Leoybra</b> genera PluginFacturas, PluginBancos e IVA para validar.</p>
<ul>
<li>Sube el Excel del trimestre y los ficheros base; se ejecuta en el servidor.</li>
<li>Descarga los ficheros generados y revísalos antes de importarlos en SAGE.</li>
</ul>
HTML],

    'contabilidad.whatsapp' => ['titulo' => 'WhatsApp', 'html' => <<<'HTML'
<p>Los <b>chats exportados de WhatsApp</b> de clientes.</p>
<ul>
<li>Exporta el chat (.zip) y déjalo en la carpeta del cliente de OneDrive que indica la pantalla.</li>
<li>Pulsa <b>«Importar ahora»</b>: sube solo lo nuevo.</li>
</ul>
HTML],

    'contabilidad.procesos-mensuales' => ['titulo' => 'Procesos mensuales', 'html' => <<<'HTML'
<p>Agrupa los <b>procesos que se repiten cada mes</b> y su seguimiento.</p>
<ul>
<li><b>Seguimiento mensual</b>: checklist de todos los procesos por mes (y por empresa en los que lo son); pulsa un proceso para ver su detalle y abrir su pantalla.</li>
<li><b>Revisión del mayor</b>, <b>Certificados</b>, <b>Vencimientos</b>, <b>Prefacturación mensual</b>: cada uno con su pestaña.</li>
<li>Los datos son los del servidor (en tu PC redirige a la web).</li>
</ul>
HTML],

    'contabilidad.certificados' => ['titulo' => 'Certificados', 'html' => <<<'HTML'
<p>Vigilancia de los <b>certificados digitales por caducar</b>.</p>
<ul>
<li><b>Escanear</b> lee los certificados instalados y avisa de los que caducan pronto; cada usuario escanea el suyo.</li>
<li>Puedes instalar el escáner en tu PC, descargar tu lista y enviarla; renueva con tiempo.</li>
</ul>
HTML],

    'admin.usuarios' => ['titulo' => 'Usuarios', 'html' => <<<'HTML'
<p>Alta y gestión de los usuarios (solo Admin).</p>
<ul>
<li>«Nuevo» crea el usuario (entra con <b>nombre.apellido@sumaempresa.com</b>); asígnale rol y las entidades que verá (busca la entidad por nombre) y guarda.</li>
<li>Al poner una contraseña, el usuario tendrá que cambiarla al entrar.</li>
<li>Su correo es también su buzón en la pantalla <b>Correo</b>: si lo cambias, el buzón nuevo hay que habilitarlo en Exchange.</li>
</ul>
HTML],

    'admin.roles' => ['titulo' => 'Roles y permisos', 'html' => <<<'HTML'
<p>Qué puede hacer cada rol (solo Admin).</p>
<ul>
<li>Marca o quita permisos por rol: ver entidades, editar, cada proceso de Contabilidad, Impuestos, Correo…</li>
<li>Los cambios se aplican en cuanto el usuario recarga. Admin lo puede todo.</li>
</ul>
HTML],

    'password.cambiar.form' => ['titulo' => 'Cambiar contraseña', 'html' => <<<'HTML'
<p>Elige una contraseña nueva. Si te lo pide Appmos al entrar, es obligatorio antes de seguir.</p>
HTML],
];
