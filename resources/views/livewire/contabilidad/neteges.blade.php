<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)" title="Clic para cerrar" class="cursor-pointer flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
                <button type="button" class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                        x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)">&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.neteges'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.neteges'])

    <div class="p-4 space-y-6">

    <h1 class="text-2xl font-semibold text-gray-900">Neteges</h1>

    @php
        $filasBase = [['clave' => 'plan', 'icono' => '📘', 'titulo' => 'Plan de cuentas', 'boton' => 'Subir plan', 'accept' => '.xlsx,.xls']];
        foreach ($cuentas as $codigo) {
            $filasBase[] = ['clave' => $codigo, 'icono' => '🏦', 'titulo' => $codigo, 'boton' => 'Subir mayor', 'accept' => '.xlsx,.xls'];
        }
        $filasBase[] = ['clave' => 'mayor', 'icono' => '＋', 'titulo' => 'Mayor de otra cuenta', 'boton' => 'Subir mayor', 'accept' => '.xlsx,.xls'];
        $filasBase[] = ['clave' => 'ventas', 'icono' => '🧾', 'titulo' => 'Fichero Ventas', 'boton' => 'Subir ventas', 'accept' => '.xlsx,.xls,.csv'];
    @endphp
    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="mb-1 text-sm font-semibold text-gray-700">Ficheros base</h2>
            <p class="text-xs text-gray-500">
                El plan de cuentas y el mayor de cada cuenta de banco, exportados de SAGE, y el fichero de Ventas.
                Cada uno va en su fila (arrástralo encima o pulsa ⬆); se guardan en {{ $carpeta }}\Base\Recibidos.
                <b>Los extractos del banco no van aquí</b>, van abajo en «Extracto a procesar».
            </p>
        </div>

        <div class="divide-y">
            @foreach ($filasBase as $fb)
                @php $datos = $estado[$fb['clave']] ?? null; @endphp
                <div wire:key="fila-base-{{ $fb['clave'] }}"
                     x-data="{
                         encima: false, subiendo: false, progreso: 0,
                         subir(files) {
                             if (! files || ! files.length) return;
                             this.subiendo = true; this.progreso = 0;
                             $wire.uploadMultiple('subidas', files,
                                 () => { this.subiendo = false; $wire.procesarSubidas(@js($fb['clave'])); },
                                 () => { this.subiendo = false; },
                                 (e) => { this.progreso = e.detail.progress; });
                         },
                     }"
                     x-on:dragover.prevent="encima = true"
                     x-on:dragleave.prevent="encima = false"
                     x-on:drop.prevent="encima = false; subir($event.dataTransfer.files)"
                     :class="encima ? 'bg-indigo-50 ring-2 ring-inset ring-indigo-400' : ''"
                     class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-2">
                    <input type="file" multiple accept="{{ $fb['accept'] }}" class="hidden" x-ref="input"
                           x-on:change="subir($event.target.files); $event.target.value = ''">
                    <div class="w-64 text-sm font-medium text-gray-800">{{ $fb['icono'] }} {{ $fb['titulo'] }}</div>
                    <div class="flex-1 min-w-[14rem] text-xs text-gray-600">
                        @if ($fb['clave'] === 'mayor')
                            <span class="text-gray-400">Una cuenta de banco nueva (572…) o otra que haga de banco (p.ej. 551002).
                                Si el nombre del fichero no empieza por la cuenta, escríbela:</span>
                            <input type="text" wire:model="otraCuenta" placeholder="cuenta" class="w-28 py-0.5 ml-1 text-xs border-gray-300 rounded-md shadow-sm">
                        @elseif (! $datos)
                            <span class="text-gray-400">(todavía nada)</span>
                        @else
                            <b>{{ $datos['n'] }}</b> {{ $datos['n'] === 1 ? 'fichero' : 'ficheros' }}
                            <span class="text-gray-400">· último:
                                <button type="button" wire:click="descargar(@js($datos['ultimo']['guardado']))" class="underline hover:text-gray-700">{{ $datos['ultimo']['fichero'] }}</button>
                                ({{ \Illuminate\Support\Carbon::parse($datos['ultimo']['fecha'])->format('d/m/Y H:i') }})</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <span x-show="subiendo" x-cloak class="text-xs text-gray-500">Subiendo… <span x-text="progreso"></span>%</span>
                        <x-button.secondary x-on:click="$refs.input.click()">⬆ {{ $fb['boton'] }}</x-button.secondary>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="px-4 py-2 border-t bg-gray-50">
            <div wire:loading.flex wire:target="procesarSubidas" class="items-center gap-2 mb-1 text-xs font-semibold text-amber-800">⏳ Guardando…</div>
            @error('subidas')
                <p class="mb-1 text-xs text-red-600">{{ $message }}</p>
            @enderror
            @if ($recibidos)
                <details class="text-xs">
                    <summary class="text-gray-500 cursor-pointer">Historial de ficheros subidos ({{ count($recibidos) }} últimos)</summary>
                    @foreach ($recibidos as $f)
                        <div class="text-gray-700">{{ $f }}</div>
                    @endforeach
                </details>
            @endif
        </div>
    </div>

    <div class="overflow-hidden bg-white border rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 bg-gray-50">
            <h2 class="mb-1 text-sm font-semibold text-gray-700">Extracto a procesar</h2>
            <p class="mb-3 text-xs text-gray-500">
                Elige la cuenta del banco y sube su extracto. De momento solo se guarda en {{ $carpeta }}\Input
                (con la cuenta delante del nombre): el proceso se hará más adelante.
            </p>

            @if (empty($cuentas))
                <p class="text-sm text-amber-700">Primero sube arriba el mayor de cada cuenta de banco.</p>
            @else
                <div class="flex flex-wrap items-start gap-4">
                    <div>
                        <label class="block mb-1 text-xs font-medium text-gray-600">Cuenta del banco</label>
                        <select wire:model.live="cuenta" class="text-sm border-gray-300 rounded-md shadow-sm">
                            <option value="">— elige —</option>
                            @foreach ($cuentas as $codigo)
                                <option value="{{ $codigo }}">{{ $codigo }}</option>
                            @endforeach
                        </select>
                        @error('cuenta')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex-1 min-w-[16rem]">
                        <label class="block mb-1 text-xs font-medium text-gray-600">Extracto del banco</label>
                        <x-contabilidad.soltar-fichero model="extracto" accept=".xlsx,.xls" :fichero="$extracto"
                            texto="Arrastra aquí el extracto o haz clic para elegirlo" />
                        <div wire:loading wire:target="extracto" class="mt-1 text-xs text-gray-400">Subiendo…</div>
                        @error('extracto')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-5">
                        <x-button.primary wire:click="guardarExtracto" wire:loading.attr="disabled" wire:target="guardarExtracto, extracto"
                            :disabled="$cuenta === '' || ! $extracto">
                            <span wire:loading.remove wire:target="guardarExtracto">⬆ Guardar extracto</span>
                            <span wire:loading wire:target="guardarExtracto">⏳ Guardando…</span>
                        </x-button.primary>
                    </div>
                </div>
            @endif

            @if ($pendientes)
                <div class="mt-3 text-xs text-gray-600">
                    <span class="font-medium">En Input:</span>
                    @foreach ($pendientes as $p)
                        <button type="button" wire:click="descargar(@js('Input/'.$p))" class="ml-2 underline hover:text-gray-900">{{ $p }}</button>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    </div>
</div>
