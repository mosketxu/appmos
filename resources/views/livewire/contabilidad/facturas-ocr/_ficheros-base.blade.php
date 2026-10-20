{{-- Ficheros base: listado de proveedores, mayor y plan --}}
            <x-contabilidad.procesando target="subidaProv,subidaMayor,subidaPlan,quitarBase" titulo="Procesando el fichero y rehaciendo los proveedores…"
                                       nota="Puede tardar un par de minutos. No cierres la página." />
            @if (! empty($basesFaltan) || empty($ficherosBase['plan']))
                <div class="focr-nocuadra" style="animation:none; border-color:#f59e0b; background:#fffbeb; color:#78350f">
                    ⚠️ Faltan ficheros base de SAGE de este cliente:
                    {{ implode(', ', array_filter([empty($ficherosBase['prov']) ? 'listado de proveedores' : '', empty($ficherosBase['mayor']) ? 'mayor' : '', empty($ficherosBase['plan']) ? 'plan de cuentas' : ''])) }}.
                    <span style="font-weight:400">Súbelos en el recuadro «Ficheros base de SAGE» de arriba (Excel exportado de SAGE){{ ! empty($basesFaltan) ? '; sin proveedores y mayor no se pueden leer las facturas' : '' }}.</span>
                </div>
            @endif
