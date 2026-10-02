{{-- Extractos del banco a conciliar (dentro del proceso «Conciliar bancos y remesas»; 2-oct-2026) --}}
<div x-data="{
         encima: false, subiendo: false, progreso: 0,
         subir(files) {
             if (! files || ! files.length) return;
             this.subiendo = true; this.progreso = 0;
             $wire.uploadMultiple('extractos', files,
                 () => { this.subiendo = false; $wire.procesarExtractos(); },
                 () => { this.subiendo = false; },
                 (e) => { this.progreso = e.detail.progress; });
         },
     }" style="padding:.25rem .75rem .5rem">
    <div class="mb-1 text-sm text-gray-800">
        📄 Extractos del banco
        <x-neteges-info>
            <b>Extractos a conciliar</b>: el Excel del BBVA y el del Sabadell (o N43/XML/TXT), varios a la vez.
            Se guardan en {{ $carpeta }}\Input y se reconoce la cuenta de cada uno por el IBAN de dentro o, si no,
            por el nombre del banco en el nombre del fichero. Si no se reconoce, se elige en su fila.
        </x-neteges-info>
    </div>
    @if (empty($cuentas))
        <p class="text-xs text-amber-700">Primero sube el mayor.</p>
    @else
        <label x-on:dragover.prevent="encima = true" x-on:dragleave.prevent="encima = false"
               x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
               :class="encima ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white hover:border-indigo-400'"
               class="flex items-center gap-2 px-3 py-2 text-xs border-2 border-dashed rounded-md cursor-pointer">
            <input type="file" multiple accept=".xlsx,.xls,.xml,.txt,.n43,.csv" class="hidden"
                   x-on:change="subir($event.target.files); $event.target.value = ''">
            <span class="text-gray-600">Arrastra aquí los extractos o haz clic para elegirlos</span>
            <span x-show="subiendo" x-cloak class="ml-auto text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
        </label>
        <div wire:loading.flex wire:target="procesarExtractos" class="items-center gap-2 mt-1 text-xs font-semibold text-amber-800">⏳ Reconociendo las cuentas…</div>
        @error('extractos')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @if ($extractosInput)
            <table class="mt-1 text-xs" style="width:100%">
                @foreach ($extractosInput as $ex)
                    <tr wire:key="ex-{{ md5($ex['fichero']) }}">
                        <td style="padding:.1rem .25rem .1rem 0; max-width:16rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">
                            <button type="button" wire:click="descargar(@js('Input/'.$ex['fichero']))" class="underline hover:text-gray-900"
                                    title="{{ $ex['fichero'] }} · {{ $ex['tipo'] }} · cuenta reconocida: {{ $ex['como'] }}">{{ $ex['fichero'] }}</button>
                        </td>
                        <td style="padding:.1rem .25rem">
                            <select x-on:change="$wire.asignarCuenta(@js($ex['fichero']), $event.target.value)"
                                    class="py-0 text-xs rounded-md shadow-sm {{ $ex['cuenta'] === '' ? 'border-red-400 bg-red-50' : 'border-gray-300' }}">
                                <option value="">— elige cuenta —</option>
                                @foreach ($cuentas as $codigo)
                                    <option value="{{ $codigo }}" @selected($ex['cuenta'] === $codigo)>{{ $codigo }}{{ ($nombresCuentas[$codigo] ?? '') !== '' ? ' · '.$nombresCuentas[$codigo] : '' }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="padding:.1rem 0; text-align:right; white-space:nowrap">
                            <button type="button" wire:click="borrarExtracto(@js($ex['fichero']))"
                                    wire:confirm="¿Quitar {{ $ex['fichero'] }} de Input?"
                                    class="text-gray-400 hover:text-red-600" title="Quitar este extracto">&times;</button>
                        </td>
                    </tr>
                @endforeach
            </table>
            <button type="button" wire:click="borrarExtracto('')" wire:confirm="¿Quitar TODOS los extractos de Input? (Quedan en Input/Borrados.)"
                    class="mt-1 text-xs text-red-600 underline hover:text-red-800">🗑 Quitar todos</button>
        @endif
    @endif
</div>
