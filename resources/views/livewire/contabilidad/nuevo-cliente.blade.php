<div class="p-3 text-sm">
@if (! $puede)
    <p class="text-gray-600">No tienes permiso para dar de alta clientes desde un correo.</p>
@else
    @if ((int) $sumaId > 0)
        @livewire('ficheros-base-subida', ['entidadId' => (int) $sumaId, 'tipos' => ['plan', 'clientes', 'gestion']], key('fb-nuevo-cliente-'.(int) $sumaId))
    @endif
    @if (! $creada)
        <div class="p-2 mb-2 border border-gray-200 rounded-md bg-gray-50">
            <p class="text-gray-700">📥 Excel de importación para SAGE (plantilla «Importación Gestión»):
                @if ($pendientes > 0) hay <b>{{ $pendientes }}</b> cliente(s) dados de alta aquí que aún no están en el listado de SAGE. @else no hay clientes pendientes. @endif</p>
            <div class="flex flex-wrap items-end gap-3 mt-1">
                <label class="text-xs text-gray-500">Es un<br>
                    <select wire:model="clienteOProveedor" class="border-gray-300 rounded-md"><option value="C">Cliente (C)</option><option value="P">Proveedor (P)</option></select></label>
                <label class="text-xs text-gray-500">Añadir además códigos ya creados (p. ej. 430277)<br>
                    <input type="text" wire:model="codigosExtra" placeholder="430277, 430278" class="border-gray-300 rounded-md"></label>
                <button type="button" wire:click="generarExcel" class="px-3 py-1 border border-gray-300 rounded-md hover:bg-white">Preparar el Excel</button>
                @if ($excel)<button type="button" wire:click="descargar" class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Descargar «{{ $excel }}»</button>@endif
            </div>
            @if ($error)<p class="mt-1 text-red-700">⚠️ {{ $error }}</p>@endif
        </div>
    @endif
    @if ($creada)
        <p class="text-green-700">✓ Entidad creada.
            <a href="{{ route('entidad.edit', $creada) }}" target="_blank" class="underline text-indigo-600">Abrir la ficha ↗</a> ·
            <a href="{{ route('entidad.historial', $creada) }}" target="_blank" class="underline text-indigo-600">Ver su historial ↗</a></p>
        @if ($error)<p class="mt-2 text-red-700">⚠️ {{ $error }}</p>@endif
        @if ($excel)
            <p class="mt-2">📥 Excel de importación para SAGE (una fila por cada cliente nuevo pendiente):
                <button type="button" wire:click="descargar" class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Descargar «{{ $excel }}»</button></p>
        @else
            <button type="button" wire:click="generarExcel" class="px-3 py-1 mt-2 border border-gray-300 rounded-md hover:bg-gray-50">Preparar el Excel para SAGE</button>
        @endif
        <button type="button" wire:click="otro" class="px-3 py-1 mt-3 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">Otro cliente</button>
    @else
        <p class="text-gray-700">Indica el correo «Alta cliente» de Marta: <b>arrástralo desde Outlook</b> a este botón, o escribe parte del asunto / el nombre del cliente y pulsa «Buscar». No se mira el correo hasta que lo pides.</p>
        <div class="flex flex-wrap items-center gap-3 mt-3">
            <x-subir-fichero wire:model="fichero" accept=".msg,.eml" texto="Arrastra aquí el correo" title="Arrastra el correo desde Outlook (.msg o .eml)" />
            <span class="text-gray-400">o</span>
            <input type="text" wire:model="consulta" wire:keydown.enter="buscar" placeholder="Asunto o cliente (p. ej. Forza Andina)" class="border-gray-300 rounded-md" style="width:20rem">
            <button type="button" wire:click="buscar" wire:loading.attr="disabled" class="px-3 py-1 text-white bg-indigo-600 rounded-md hover:bg-indigo-700">🔍 Buscar</button>
            <span wire:loading class="text-gray-500">⏳ Un momento…</span>
        </div>
        @if ($error)<p class="mt-2 text-red-700">⚠️ {{ $error }}</p>@endif

        @if ($resultados)
            <p class="mt-3 text-gray-700">Elige el correo:</p>
            <ul class="mt-1 border border-gray-200 rounded-md divide-y">
                @foreach ($resultados as $r)
                    <li wire:key="r-{{ md5($r['id']) }}">
                        <button type="button" wire:click="elegir('{{ $r['id'] }}')" class="block w-full px-3 py-1 text-left hover:bg-gray-50">
                            <b>{{ $r['asunto'] }}</b> <span class="text-xs text-gray-500">· {{ $r['de'] }} · {{ \Illuminate\Support\Carbon::parse($r['fecha'])->timezone('Europe/Madrid')->format('d/m/Y H:i') }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($campos)
            <h3 class="mt-4 font-semibold text-gray-900">Datos del correo «{{ $asunto }}»</h3>
            @foreach ($avisos as $a)
                @php [$tipo, $txt, $idDup] = array_pad(explode('|', $a, 3), 3, null); @endphp
                @if ($txt)
                    <p class="mt-1 text-yellow-800">⚠️ {{ $txt }} <a href="{{ route('entidad.edit', $idDup) }}" target="_blank" class="underline text-indigo-600">Abrirla ↗</a></p>
                @else
                    <p class="mt-1 text-yellow-800">⚠️ {{ $a }}</p>
                @endif
            @endforeach
            @php
                $texto = [['entidad', 'Entidad'], ['alias', 'Alias'], ['nif', 'NIF'], ['emailgral', 'Email Gral'], ['emailadm', 'Email Adm'], ['web', 'Web'], ['tfno', 'Tfno.'],
                    ['direccion', 'Dirección'], ['codpostal', 'C.P.'], ['localidad', 'Localidad'],
                    ['banco1', 'Banco 1'], ['iban1', 'IBAN 1'], ['banco2', 'Banco 2'], ['iban2', 'IBAN 2'], ['banco3', 'Banco 3'], ['iban3', 'IBAN 3'],
                    ['cuentacontable', 'Cta. contable'], ['digitos_cuenta', 'Dígitos de cuenta (6-12)'], ['codigo_cliente', 'Cód. cliente'], ['referenciacliente', 'Ref. cliente'],
                    ['cnae', 'CNAE'], ['epigrafe_iae', 'Epígrafe IAE'], ['diafactura', 'Día factura'], ['diavencimiento', 'Día vencimiento'], ['retencion', '% Retención (7 o 15)'],
                    ['porcentajemarta', '% Marta'], ['porcentajesusana', '% Susana'], ['importe', 'Importe (€) → historial']];
                $ciclos = \App\Models\Entidad::CICLOS;
                $checks = [['cliente', 'Cliente'], ['proveedor', 'Proveedor'], ['contacto', 'Contacto'], ['facturar', 'Facturar'], ['enviar', 'Enviar'], ['favorito', 'Favorito']];
            @endphp
            <div class="grid gap-2 mt-2" style="grid-template-columns:repeat(auto-fill,minmax(14rem,1fr))">
                @foreach ($texto as [$k, $etq])
                    <label class="block"><span class="text-xs text-gray-500">{{ $etq }}</span>
                        <input type="{{ in_array($k, ['porcentajemarta', 'porcentajesusana', 'retencion']) ? 'number' : 'text' }}" @if (in_array($k, ['porcentajemarta', 'porcentajesusana', 'retencion'])) step="any" @endif wire:model="campos.{{ $k }}" class="w-full border-gray-300 rounded-md"></label>
                @endforeach
                <label class="block"><span class="text-xs text-gray-500">Provincia</span>
                    <select wire:model="campos.provincia_id" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($provincias as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">País</span>
                    <select wire:model="campos.pais_id" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($paises as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Persona</span>
                    <select wire:model="campos.persona_fj" class="w-full border-gray-300 rounded-md"><option value="J">Jurídica</option><option value="F">Física</option></select></label>
                <label class="block"><span class="text-xs text-gray-500">Idioma</span>
                    <select wire:model="campos.idioma" class="w-full border-gray-300 rounded-md"><option value="ES">Español</option><option value="CA">Catalán</option><option value="EN">Inglés</option></select></label>
                <label class="block"><span class="text-xs text-gray-500">IVA</span>
                    <select wire:model="campos.tipoiva" class="w-full border-gray-300 rounded-md"><option value="0.21">21%</option><option value="0">0%</option></select></label>
                <label class="block"><span class="text-xs text-gray-500">Rpble. Suma (Equipo)</span>
                    <select wire:model="campos.suma_id" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($sumas as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Grupo</span>
                    <select wire:model="campos.grupo_id" class="w-full border-gray-300 rounded-md"><option value="">— sin grupo —</option>
                        @foreach ($grupos as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Método de pago</span>
                    <select wire:model="campos.metodopago_id" class="w-full border-gray-300 rounded-md"><option value="1">Transferencia</option><option value="2">Recibo domiciliado</option><option value="3">No definida</option><option value="4">No aplica</option></select></label>
                <label class="block"><span class="text-xs text-gray-500">Ciclo impuesto</span>
                    <select wire:model="campos.cicloimpuesto_id" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($ciclos as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Ciclo facturación</span>
                    <select wire:model="campos.ciclo" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($ciclos as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Estado</span>
                    <select wire:model="campos.estado" class="w-full border-gray-300 rounded-md">@foreach (\App\Models\Entidad::ESTADOS as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
            </div>
            @if ($codigoInfo)
                <p class="mt-2 text-xs text-gray-600">Código de cliente = cuenta contable: el mayor 430xxx de 6 dígitos +1
                    ({{ collect($codigoInfo['fuentes'] ?? [])->map(fn ($v, $k) => $k.' '.$v)->implode(' · ') ?: 'sin ficheros' }}; los de 7 dígitos y los 430900 en adelante no cuentan).
                    @foreach ($codigoInfo['avisos'] ?? [] as $av)<span class="text-yellow-800">⚠️ {{ $av }}</span> @endforeach</p>
            @endif
            <div class="flex flex-wrap items-center mt-2 gap-x-5">
                @foreach ($checks as [$k, $etq])
                    <label class="inline-flex items-center gap-1"><input type="checkbox" wire:model="campos.{{ $k }}" class="w-4 h-4 border-gray-300 rounded"> {{ $etq }}</label>
                @endforeach
            </div>
            <label class="block mt-2"><span class="text-xs text-gray-500">Observaciones (máx. 255)</span>
                <input type="text" maxlength="255" wire:model="campos.observaciones" class="w-full border-gray-300 rounded-md"></label>
            <div class="mt-3 text-gray-700">
                <p class="text-xs text-gray-500">Irá al historial de la entidad:</p>
                <p>@foreach ($nota as $etq => $val)<span class="mr-3"><b>{{ $etq }}:</b> {{ $val }}</span>@endforeach</p>
                <textarea wire:model="extra" rows="3" class="w-full mt-1 border-gray-300 rounded-md" placeholder="Texto libre del correo"></textarea>
            </div>
            <div class="mt-3">
                <button type="button" wire:click="crear" wire:loading.attr="disabled" wire:target="crear"
                    wire:confirm="¿Crear la entidad con estos datos?" class="px-3 py-1 text-white bg-green-600 rounded-md hover:bg-green-700">✓ Crear la entidad</button>
                <span class="ml-2 text-xs text-gray-500">Se crea como cliente activo. Lo que falte lo completas luego en su ficha.</span>
            </div>
        @endif
    @endif
@endif
</div>
