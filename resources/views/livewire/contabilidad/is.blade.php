<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="flex items-start gap-2 p-3 bg-white border border-gray-300 rounded-lg shadow-lg">
                <pre class="flex-1 font-sans text-sm text-gray-800 whitespace-pre-wrap" x-text="aviso.mensaje"></pre>
                <button type="button" class="text-lg leading-none text-gray-400 shrink-0 hover:text-gray-700"
                        x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)">&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.is'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.is'])

    @php
        $nombrePagina = function ($p) {
            $p = (string) $p;   // '12000' llega como clave entera
            $sufijos = ['B' => ' bis', 'C' => ' ter', 'D' => ' quater', 'E' => ' quinquies', 'F' => ' sexies', 'G' => ' septies'];
            if ($p === 'DID00') return 'Documento de ingreso / devolución';
            return 'Página '.ltrim(substr($p, 0, 2), '0').($sufijos[$p[2]] ?? '');
        };
        $euros = fn ($v) => is_float($v) || is_int($v) ? number_format($v, 2, ',', '.') : $v;
        $res = $revision['resumen'] ?? null;
    @endphp

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
    <div class="space-y-6" style="flex:65 1 0;min-width:0">

    <h1 class="flex flex-wrap items-center text-2xl font-semibold text-gray-900 gap-x-3 gap-y-2">
        <span>Impuesto sobre Sociedades (modelo 200) —</span>
        <select wire:model.live="entidadId" class="text-base font-normal border-gray-300 rounded-md shadow-sm">
            @forelse ($entidades as $e)
                <option value="{{ $e->id }}">{{ $e->entidad }} · {{ $e->nif }}</option>
            @empty
                <option value="">(no hay entidades con NIF)</option>
            @endforelse
        </select>
        <span class="text-base font-normal text-gray-600">ejercicio</span>
        <input type="number" wire:model.live.debounce.600ms="ejercicio" min="2024" max="2099" class="w-24 text-base font-normal border-gray-300 rounded-md shadow-sm">
    </h1>

    <p class="text-sm text-gray-600">
        Se genera el fichero para <b>importar en Sociedades WEB</b>. Aquí no se presenta nada: lo importas, lo revisas
        allí y lo presentas tú con el certificado desde tu navegador.
        Sólo balance y PyG en modelo PYMES y período = año natural.
        @if ($carpeta)<span class="text-gray-400">Carpeta: IS/{{ $carpeta }}</span>@endif
    </p>

    @if ($carpeta)
    {{-- 1. Ficheros --}}
    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">1. Ficheros del ejercicio {{ $ejercicio }}</h2>
            <p class="text-xs text-gray-500">Uno por casilla. Si subes otra vez uno, el anterior se guarda aparte.</p>
        </div>
        <div class="divide-y divide-gray-100">
            @foreach ($fuentes as $clave => [$titulo, $exts, $obligatorio, $ayuda])
                <div class="flex flex-wrap items-center gap-3 px-4 py-3">
                    <div class="flex-1 min-w-[16rem]">
                        <div class="text-sm font-medium text-gray-800">
                            {{ $titulo }}
                            @if ($obligatorio)<span class="text-red-600">*</span>@else<span class="text-xs font-normal text-gray-400">(opcional)</span>@endif
                        </div>
                        <div class="text-xs text-gray-500">{{ $ayuda }}</div>
                        @if ($estado[$clave])
                            <div class="mt-1 text-xs text-green-700">✔ {{ $estado[$clave]['nombre'] }} · {{ $estado[$clave]['fecha'] }}</div>
                        @elseif ($obligatorio)
                            <div class="mt-1 text-xs text-amber-700">Falta</div>
                        @endif
                        @error('subida.'.$clave)<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                    </div>
                    <label class="inline-flex items-center px-3 py-1.5 text-sm text-indigo-700 border border-indigo-300 rounded-md cursor-pointer bg-indigo-50 hover:bg-indigo-100">
                        <input type="file" class="hidden" wire:model="subida.{{ $clave }}" accept="{{ collect($exts)->map(fn ($x) => '.'.$x)->implode(',') }}">
                        {{ $estado[$clave] ? 'Cambiar' : 'Subir' }} ({{ implode(' / ', $exts) }})
                    </label>
                    <div wire:loading wire:target="subida.{{ $clave }}" class="text-xs text-yellow-600">⏳ Subiendo…</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 2. Criterios y ajustes --}}
    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="text-sm font-semibold text-gray-700">2. Criterios y ajustes</h2>
        </div>
        <div class="grid gap-6 p-4 lg:grid-cols-2">
            <div>
                <h3 class="text-xs font-semibold text-gray-700">Criterios del cliente (todos los años): cuenta → casilla</h3>
                <p class="mb-2 text-xs text-gray-500">Para llevar una cuenta (o todas las que empiezan así) a otra casilla del balance o la PyG. P. ej. 649 → 00263 (formación como trabajos de otras empresas).</p>
                @foreach ($criterios as $i => $c)
                    <div class="flex items-center gap-2 mb-1" wire:key="crit-{{ $i }}">
                        <input type="text" wire:model="criterios.{{ $i }}.prefijo" placeholder="cuenta" class="w-24 py-1 text-sm font-mono border-gray-300 rounded">
                        <span class="text-gray-400">→</span>
                        <input type="text" wire:model="criterios.{{ $i }}.casilla" placeholder="casilla" class="w-24 py-1 text-sm font-mono border-gray-300 rounded">
                        <button type="button" wire:click="quitarCriterio({{ $i }})" class="text-xs text-red-600 hover:underline">quitar</button>
                    </div>
                @endforeach
                <button type="button" wire:click="anadirCriterio" class="text-xs text-blue-700 hover:underline">+ añadir criterio</button>
                @error('criterios')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror

                <h3 class="mt-5 text-xs font-semibold text-gray-700">Correcciones al resultado contable (páginas 12-13)</h3>
                <p class="mb-2 text-xs text-gray-500">Casilla de aumento o disminución e importe. P. ej. multas: 01815; donativos: 00339. El detalle de las páginas 26 y los totales se rellenan solos.</p>
                @foreach ($correcciones as $i => $c)
                    <div class="flex items-center gap-2 mb-1" wire:key="corr-{{ $i }}">
                        <input type="text" wire:model="correcciones.{{ $i }}.casilla" placeholder="casilla" class="w-20 py-1 text-sm font-mono border-gray-300 rounded">
                        <input type="text" wire:model="correcciones.{{ $i }}.importe" placeholder="importe" class="py-1 text-sm text-right border-gray-300 rounded w-28">
                        <input type="text" wire:model="correcciones.{{ $i }}.texto" placeholder="qué es" class="flex-1 py-1 text-sm border-gray-300 rounded">
                        <button type="button" wire:click="quitarCorreccion({{ $i }})" class="text-xs text-red-600 hover:underline">quitar</button>
                    </div>
                @endforeach
                <button type="button" wire:click="anadirCorreccion" class="text-xs text-blue-700 hover:underline">+ añadir corrección</button>
                @error('correcciones')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
            </div>

            <div class="space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-xs text-gray-600">Forma de pago si sale a ingresar
                        <select wire:model="formaPago" class="block w-full py-1 mt-1 text-sm border-gray-300 rounded">
                            <option value="">Automática (domiciliación si hay IBAN)</option>
                            <option value="U">Domiciliación</option>
                            <option value="I">Ingreso (NRC / cargo en cuenta)</option>
                        </select>
                    </label>
                    <label class="text-xs text-gray-600">Teléfono
                        <input type="text" wire:model="telefono" class="block w-full py-1 mt-1 text-sm border-gray-300 rounded">
                    </label>
                </div>
                <label class="block text-xs text-gray-600">IBAN (domiciliación o devolución)
                    <input type="text" wire:model="iban" class="block w-full py-1 mt-1 font-mono text-sm border-gray-300 rounded">
                </label>
                <div>
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                        <input type="checkbox" wire:model.live="rectificativa" class="border-gray-300 rounded">
                        Autoliquidación rectificativa (ya hay un 200 presentado de este ejercicio)
                    </label>
                    @if ($rectificativa)
                        <div class="grid grid-cols-2 gap-3 mt-1">
                            <label class="text-xs text-gray-600">Justificante de la anterior
                                <input type="text" wire:model="justificanteAnterior" class="block w-full py-1 mt-1 font-mono text-sm border-gray-300 rounded">
                            </label>
                            <label class="text-xs text-gray-600">Importe ingresado con ella
                                <input type="text" wire:model="ingresadoAnterior" class="block w-full py-1 mt-1 text-sm text-right border-gray-300 rounded">
                            </label>
                        </div>
                    @endif
                    @error('rectificativa')<div class="mt-1 text-xs text-red-600">{{ $message }}</div>@enderror
                </div>
                <label class="block text-xs text-gray-600">Valores fijados a mano (uno por línea: página.casilla = valor; <code>null</code> lo borra)
                    <textarea wire:model="fijos" rows="3" placeholder="14000.00558 = 2122&#10;01000.#20 = 600000000" class="block w-full mt-1 font-mono text-xs border-gray-300 rounded"></textarea>
                </label>
                @error('fijos')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-3 px-4 py-3 border-t border-gray-200 bg-gray-50">
            <x-button.primary wire:click="calcular" wire:loading.attr="disabled" wire:target="calcular">Guardar y calcular</x-button.primary>
            <span wire:loading wire:target="calcular" class="text-xs text-yellow-600">⏳ Calculando…</span>
            @if ($hay200)
                <button type="button" wire:click="descargar('200')" class="text-sm font-semibold text-blue-700 underline hover:text-blue-900">⬇ Descargar {{ basename($carpeta) }} .200</button>
                <button type="button" wire:click="descargar('datos')" class="text-xs text-gray-500 underline hover:text-gray-700">datos.json</button>
            @endif
            @error('calcular')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        </div>
    </div>

    {{-- 3. Resultado --}}
    @if ($revision)
        <div class="overflow-hidden bg-white border rounded-lg shadow">
            <div class="p-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-sm font-semibold text-gray-700">3. Resultado y revisión</h2>
            </div>
            <div class="p-4 space-y-4">
                @if ($res)
                    <div class="grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
                        @foreach (['resultado_contable' => 'Resultado contable', 'base_imponible' => 'Base imponible', 'cuota' => 'Cuota íntegra', 'a_ingresar' => 'Resultado autoliquidación'] as $k => $t)
                            <div class="p-2 border rounded"><div class="text-xs text-gray-500">{{ $t }}</div><div class="font-semibold text-right">{{ $euros($res[$k]) }}</div></div>
                        @endforeach
                        <div class="p-2 border rounded"><div class="text-xs text-gray-500">Tipo de declaración</div>
                            <div class="font-semibold text-right">{{ ['U' => 'Domiciliación', 'I' => 'Ingreso', 'D' => 'Devolución', 'N' => 'Resultado cero'][$res['tipo_declaracion']] ?? $res['tipo_declaracion'] }}</div></div>
                    </div>
                @endif

                @foreach ($revision['errores'] ?? [] as $e)
                    <div class="px-3 py-2 text-sm text-red-800 border border-red-200 rounded bg-red-50">❌ {{ $e }}</div>
                @endforeach
                @foreach ($revision['avisos'] ?? [] as $a)
                    <div class="px-3 py-2 text-sm border rounded text-amber-800 border-amber-200 bg-amber-50">⚠️ {{ $a }}</div>
                @endforeach

                @if (! empty($revision['revisar']))
                    <div>
                        <h3 class="mb-1 text-xs font-semibold text-gray-700">Cuentas que suelen llevar ajustes (del mayor)</h3>
                        @foreach ($revision['revisar'] as $cuenta => $movs)
                            <details class="mb-1 text-xs">
                                <summary class="cursor-pointer">{{ $cuenta }} — {{ count($movs) }} apuntes, {{ $euros(round(array_sum(array_column($movs, 'debe')) - array_sum(array_column($movs, 'haber')), 2)) }}
                                    @if (str_starts_with($cuenta, '678'))
                                        <button type="button" class="ml-2 text-blue-700 hover:underline"
                                                wire:click="proponerCorreccion('01815', @js(number_format(array_sum(array_column($movs, 'debe')) - array_sum(array_column($movs, 'haber')), 2, ',', '.')), @js('multas y sanciones ('.$cuenta.')'))">→ añadir como multas (01815)</button>
                                    @endif
                                </summary>
                                <table class="w-full mt-1 ml-4">
                                    @foreach ($movs as $mv)
                                        <tr><td class="pr-2">{{ $mv['fecha'] }}</td><td class="pr-2">{{ $mv['comentario'] }}</td>
                                            <td class="text-right">{{ $euros($mv['debe'] - $mv['haber']) }}</td></tr>
                                    @endforeach
                                </table>
                            </details>
                        @endforeach
                    </div>
                @endif

                @if (! empty($revision['comparacion']))
                    @php $cmp = $revision['comparacion']; @endphp
                    <div>
                        <h3 class="mb-1 text-xs font-semibold text-gray-700">Comparación con el 200 presentado: {{ $cmp['iguales'] }} casillas iguales, {{ count($cmp['diferencias']) }} distintas</h3>
                        <p class="mb-1 text-xs text-gray-400">{{ $cmp['nota'] }}</p>
                        <table class="w-full text-xs">
                            <tr class="text-left text-gray-500"><th>Casilla</th><th>Descripción</th><th class="text-right">Generado</th><th class="text-right">Presentado</th></tr>
                            @foreach ($cmp['diferencias'] as $d)
                                <tr class="border-t"><td class="font-mono">{{ $d['casilla'] }}</td><td>{{ \Illuminate\Support\Str::limit($d['descripcion'], 90) }}</td>
                                    <td class="text-right">{{ is_null($d['generado']) ? 'no está' : $euros($d['generado']) }}</td>
                                    <td class="text-right">{{ is_null($d['presentado']) ? 'no está' : $euros($d['presentado']) }}</td></tr>
                            @endforeach
                        </table>
                    </div>
                @endif

                <div>
                    <h3 class="mb-1 text-xs font-semibold text-gray-700">Casillas del fichero</h3>
                    @foreach ($paginas as $pg => $filas)
                        <details class="mb-1" @if (in_array($pg, ['14000', '14B00'])) open @endif>
                            <summary class="text-sm cursor-pointer">{{ $nombrePagina($pg) }} <span class="text-xs text-gray-400">({{ count($filas) }})</span></summary>
                            <table class="w-full mt-1 text-xs">
                                @foreach ($filas as $f)
                                    <tr class="align-top border-t">
                                        <td class="pr-2 font-mono whitespace-nowrap">{{ $f['casilla'] }}</td>
                                        <td class="pr-2">{{ \Illuminate\Support\Str::limit($f['descripcion'], 110) }}</td>
                                        <td class="pr-2 font-semibold text-right whitespace-nowrap">{{ $euros($f['valor']) }}</td>
                                        <td class="text-gray-500">{{ $f['origen'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </details>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
    @endif

    </div>{{-- /columna izquierda --}}

    <div class="w-full" style="flex:35 1 0;min-width:0">
        <div class="sticky top-4">
            <div class="flex justify-start mb-2">
                <x-button.secondary wire:click="limpiarSalida">Borrar salida</x-button.secondary>
            </div>
            <div class="p-4 rounded-lg shadow {{ $salida !== '' ? 'bg-gray-900' : 'bg-white border border-gray-200' }}">
                <h2 class="mb-2 text-sm font-semibold {{ $salida !== '' ? 'text-gray-300' : 'text-gray-400' }}">Salida</h2>
                <pre class="overflow-auto text-xs whitespace-pre-wrap {{ $salida !== '' ? 'text-green-400' : 'text-gray-400' }}" style="max-height:calc(100vh - 9rem)">{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
            </div>
        </div>
    </div>

    </div>
    </div>
</div>
