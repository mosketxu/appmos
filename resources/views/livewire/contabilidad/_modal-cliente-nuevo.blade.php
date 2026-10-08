{{-- Modal: crear la carpeta de cliente de una entidad que aún no la tiene (Facturas OCR y Bancos) --}}
@if ($modalNuevo)
    @php($ex = $explorando ? $this->datosExplorador() : null)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(17,24,39,.5)">
        <div class="w-full max-w-2xl p-5 space-y-3 bg-white rounded-lg shadow-xl" wire:keydown.escape="cancelarNuevo">
            <h2 class="text-lg font-semibold text-gray-900">Nuevo cliente: {{ $nuevoEntidad }}</h2>
            <p class="text-sm text-gray-600">Esta entidad aún no tiene carpeta en Facturas OCR / Bancos. Se crea ahora; revisa lo que propongo.</p>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">Nombre del cliente</span>
                <input type="text" wire:model="nuevoNombre" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm">
            </label>
            <div class="text-sm">
                <span class="font-medium text-gray-700">Carpeta de OneDrive con las facturas recibidas</span>
                <div class="flex items-stretch gap-2 mt-1">
                    <div class="flex-1 px-3 py-2 font-mono text-xs break-all bg-gray-50 border border-gray-300 rounded-md">{{ \App\Support\ClientesEntidad::rutaWindows($nuevoCarpeta) }}</div>
                    <button type="button" wire:click="elegirConWindows" wire:loading.attr="disabled" @disabled($ventanaToken !== '') class="px-3 py-1.5 text-sm font-semibold text-indigo-700 border border-indigo-300 rounded-md hover:bg-indigo-50" title="Se abre el selector de carpetas de Windows en el PC donde trabajas">📂 Examinar…</button>
                </div>
                @if ($ventanaToken !== '')
                    <div class="flex items-center gap-2 mt-1 text-xs font-semibold text-indigo-700" wire:poll.2s="revisarVentana">
                        ⏳ Se está abriendo la ventana de Windows en tu PC: elige la carpeta de las facturas recibidas (si no la ves, mira en la barra de tareas).
                        <button type="button" wire:click="cancelarVentana" class="font-normal text-gray-600 underline">Cancelar</button>
                    </div>
                @endif
                <span class="text-xs {{ $nuevoDetectada ? 'text-green-700' : 'text-amber-700' }}">
                    {{ $nuevoDetectada ? '✔ He encontrado la carpeta del cliente en OneDrive.' : '⚠ No he encontrado la carpeta del cliente en OneDrive: es una suposición; usa «Examinar…» (abre el selector de carpetas de Windows).' }}
                    Dentro tiene que haber una subcarpeta por mes (01, 02…); las facturas validadas van a la del mes de registro.
                </span>
            </div>

            @if ($explorando)
                <div class="border border-gray-300 rounded-md" @if ($ex['enCurso']) wire:poll.3s @endif>
                    <div class="flex flex-wrap items-center gap-1 px-3 py-2 text-sm bg-gray-100 border-b border-gray-300">
                        <button type="button" wire:click="irACarpeta('_Clientes')" class="text-indigo-700 hover:underline">OneDrive</button>
                        @foreach ($ex['migas'] as $m)
                            <span class="text-gray-400">›</span>
                            <button type="button" wire:click="irACarpeta(@js($m['ruta']))" class="text-indigo-700 hover:underline">{{ $m['t'] }}</button>
                        @endforeach
                        <span class="flex-1"></span>
                        <button type="button" wire:click="actualizarArbol" class="text-xs text-gray-600 hover:underline" title="Vuelve a leer las carpetas de OneDrive en un PC">↻ Actualizar</button>
                    </div>
                    <div class="overflow-auto" style="max-height:16rem">
                        @if (! $ex['hay'])
                            <p class="px-3 py-4 text-sm text-gray-600">⏳ Un PC está leyendo las carpetas de OneDrive… (unos segundos). Si tarda, comprueba que haya un PC trabajador encendido.</p>
                        @else
                            @if ($ex['padre'] !== null)
                                <div class="px-3 py-1.5 text-sm cursor-pointer hover:bg-gray-100" wire:click="irACarpeta(@js($ex['padre']))">⬆ ..</div>
                            @endif
                            @forelse ($ex['hijas'] as $h)
                                <div class="px-3 py-1.5 text-sm cursor-pointer hover:bg-indigo-50" wire:click="irACarpeta(@js(trim($expRuta.'/'.$h, '/')))">📁 {{ $h }}</div>
                            @empty
                                <p class="px-3 py-3 text-sm text-gray-500">(sin subcarpetas)</p>
                            @endforelse
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 border-t border-gray-300">
                        <span class="text-xs text-gray-500">@if ($ex['hay']) Carpetas leídas {{ $ex['fecha'] }} en {{ $ex['pc'] }}. @endif @if ($expError)<b class="text-red-700">{{ $expError }}</b>@endif</span>
                        <span class="flex gap-2">
                            <button type="button" wire:click="cerrarExplorador" class="px-2 py-1 text-sm text-gray-700 border border-gray-300 rounded-md">Cancelar</button>
                            <button type="button" wire:click="elegirCarpetaExplorada" class="px-2 py-1 text-sm font-semibold text-white bg-indigo-600 rounded-md">Elegir esta carpeta</button>
                        </span>
                    </div>
                </div>
            @endif

            @if ($nuevoError)
                <p class="text-sm font-semibold text-red-700">⚠️ {{ $nuevoError }}</p>
            @endif
            <div class="flex justify-end gap-2 pt-2">
                <button type="button" wire:click="cancelarNuevo" class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded-md">Cancelar</button>
                <button type="button" wire:click="crearClienteNuevo" wire:loading.attr="disabled" class="px-3 py-1.5 text-sm font-semibold text-white bg-indigo-600 rounded-md">Crear y abrir</button>
            </div>
        </div>
    </div>
@endif
