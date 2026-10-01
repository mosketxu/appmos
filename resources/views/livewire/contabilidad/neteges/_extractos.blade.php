{{-- Extractos del banco (dentro del bloque Bancos de neteges.blade.php) --}}
    <div class="border-t-2 border-gray-200"
     x-data="{
         encima: false, subiendo: false, progreso: 0,
         subir(files) {
             if (! files || ! files.length) return;
             this.subiendo = true; this.progreso = 0;
             $wire.uploadMultiple('extractos', files,
                 () => { this.subiendo = false; $wire.procesarExtractos(); },
                 () => { this.subiendo = false; },
                 (e) => { this.progreso = e.detail.progress; });
         },
     }">
    <div class="px-4 py-2">
        <h3 class="mb-1 text-sm font-semibold text-gray-700">📄 Extractos a conciliar</h3>
        <p class="mb-2 text-xs text-gray-500">
            Suelta aquí todos los que quieras a la vez (Excel, XML o TXT). Se guardan en {{ $carpeta }}\Input y se
            reconoce la cuenta de cada uno: por el IBAN de dentro del fichero o, si no, por el nombre del banco en el
            nombre del fichero. Si no se reconoce, elígela en su fila.
        </p>
        @if (empty($cuentas))
            <p class="text-sm text-amber-700">Primero sube el mayor.</p>
        @else
            <label x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false"
                   x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                   :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
                   class="flex items-center gap-2 px-3 py-3 text-sm border-2 border-dashed rounded-md cursor-pointer">
                <input type="file" multiple accept=".xlsx,.xls,.xml,.txt,.n43,.csv" class="hidden"
                       x-on:change="subir($event.target.files); $event.target.value = ''">
                <span>📄</span>
                <span class="text-gray-700">Arrastra aquí los extractos (varios a la vez) o haz clic para elegirlos</span>
                <span x-show="subiendo" x-cloak class="ml-auto text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
            </label>
            <div wire:loading.flex wire:target="procesarExtractos" class="items-center gap-2 mt-1 text-xs font-semibold text-amber-800">⏳ Reconociendo las cuentas…</div>
            @error('extractos')
                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
        @endif
    </div>

    @if ($extractosInput)
        <table class="min-w-full text-xs">
            <thead class="text-left text-gray-500 bg-gray-50">
                <tr><th class="px-4 py-1">Extracto (en Input)</th><th class="px-2 py-1">Tipo</th><th class="px-2 py-1">Cuenta</th><th class="px-2 py-1">Cómo</th>
                    <th class="px-2 py-1 text-right">
                        <button type="button" wire:click="borrarExtracto('')"
                                wire:confirm="¿Quitar TODOS los extractos de Input? (Quedan en Input/Borrados.)"
                                class="font-normal text-red-600 underline hover:text-red-800">🗑 Borrar todos</button>
                    </th></tr>
            </thead>
            <tbody>
                @foreach ($extractosInput as $ex)
                    <tr class="border-t" wire:key="ex-{{ md5($ex['fichero']) }}">
                        <td class="px-4 py-1">
                            <button type="button" wire:click="descargar(@js('Input/'.$ex['fichero']))" class="underline hover:text-gray-900">{{ $ex['fichero'] }}</button>
                        </td>
                        <td class="px-2 py-1">{{ $ex['tipo'] }}</td>
                        <td class="px-2 py-1">
                            <select x-on:change="$wire.asignarCuenta(@js($ex['fichero']), $event.target.value)"
                                    class="py-0.5 text-xs rounded-md shadow-sm {{ $ex['cuenta'] === '' ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                <option value="">— elige —</option>
                                @foreach ($cuentas as $codigo)
                                    <option value="{{ $codigo }}" @selected($ex['cuenta'] === $codigo)>{{ $codigo }}{{ ($nombresCuentas[$codigo] ?? '') !== '' ? ' · '.$nombresCuentas[$codigo] : '' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-2 py-1 {{ $ex['cuenta'] === '' ? 'text-red-700' : 'text-gray-500' }}">{{ $ex['como'] }}</td>
                        <td class="px-2 py-1 text-right">
                            <button type="button" wire:click="borrarExtracto(@js($ex['fichero']))"
                                    wire:confirm="¿Quitar {{ $ex['fichero'] }} de Input?"
                                    class="text-gray-400 hover:text-red-600" title="Borrar">&times;</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
