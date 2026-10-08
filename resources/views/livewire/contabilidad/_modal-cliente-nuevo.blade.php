{{-- Modal: crear la carpeta de cliente de una entidad que aún no la tiene (Facturas OCR y Bancos) --}}
@if ($modalNuevo)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background:rgba(17,24,39,.5)">
        <div class="w-full max-w-lg p-5 space-y-3 bg-white rounded-lg shadow-xl" wire:keydown.escape="cancelarNuevo">
            <h2 class="text-lg font-semibold text-gray-900">Nuevo cliente: {{ $nuevoEntidad }}</h2>
            <p class="text-sm text-gray-600">Esta entidad aún no tiene carpeta en Facturas OCR / Bancos. Se crea ahora; revisa lo que propongo.</p>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">Nombre del cliente (carpeta)</span>
                <input type="text" wire:model="nuevoNombre" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm">
            </label>
            <label class="block text-sm">
                <span class="font-medium text-gray-700">Carpeta anual en OneDrive <span class="font-normal text-gray-500">(_Clientes/{AAAA}/…)</span></span>
                <input type="text" wire:model="nuevoAnual" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                <span class="text-xs {{ $nuevoDetectada ? 'text-green-700' : 'text-amber-700' }}">
                    {{ $nuevoDetectada ? '✔ He encontrado esta carpeta en OneDrive.' : '⚠ No la he encontrado en OneDrive: es una suposición. Cámbiala si la carpeta se llama de otra forma; las facturas validadas irán a «…/_Facturas/{MM}».' }}
                    {AAAA} = el año.
                </span>
            </label>
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
