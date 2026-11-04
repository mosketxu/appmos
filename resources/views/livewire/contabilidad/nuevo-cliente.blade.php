<div class="p-3 text-sm">
@if (! $puede)
    <p class="text-gray-600">No tienes permiso para dar de alta clientes desde un correo.</p>
@else
    @if ($creada)
        <p class="text-green-700">✓ Entidad creada.
            <a href="{{ route('entidad.edit', $creada) }}" target="_blank" class="underline text-indigo-600">Abrir la ficha ↗</a> ·
            <a href="{{ route('entidad.historial', $creada) }}" target="_blank" class="underline text-indigo-600">Ver su historial ↗</a></p>
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
            <div class="grid gap-2 mt-2" style="grid-template-columns:repeat(auto-fill,minmax(15rem,1fr))">
                @php
                    $caja = [['entidad', 'Nombre'], ['nif', 'NIF'], ['direccion', 'Dirección'], ['codpostal', 'CP'], ['localidad', 'Población'], ['tfno', 'Teléfono'], ['emailadm', 'Email Adm'], ['iban1', 'IBAN'], ['web', 'Web']];
                @endphp
                @foreach ($caja as [$k, $etq])
                    <label class="block"><span class="text-xs text-gray-500">{{ $etq }}</span>
                        <input type="text" wire:model="campos.{{ $k }}" class="w-full border-gray-300 rounded-md"></label>
                @endforeach
                <label class="block"><span class="text-xs text-gray-500">Provincia</span>
                    <select wire:model="campos.provincia_id" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($provincias as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Responsable Suma (Equipo)</span>
                    <select wire:model="campos.suma_id" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ($sumas as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Cobro</span>
                    <select wire:model="campos.metodopago_id" class="w-full border-gray-300 rounded-md"><option value="1">Transferencia</option><option value="2">Recibo domiciliado</option></select></label>
                <label class="block"><span class="text-xs text-gray-500">Facturación</span>
                    <select wire:model="campos.ciclo" class="w-full border-gray-300 rounded-md"><option value="">—</option>
                        @foreach ([1 => 'Mensual', 3 => 'Trimestral', 12 => 'Anual', 20 => 'Puntual'] as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select></label>
                <label class="block"><span class="text-xs text-gray-500">Importe (€)</span>
                    <input type="text" wire:model="campos.importe" class="w-full border-gray-300 rounded-md"></label>
            </div>
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
