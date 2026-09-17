<div class=""
    x-data="{ avisos: [] }"
    x-on:proceso-terminado.window="avisos.push({ id: Date.now() + '-' + Math.random(), mensaje: $event.detail.mensaje })"
>
    <div class="fixed top-4 right-4 z-50 flex w-96 max-w-[calc(100vw-2rem)] flex-col gap-2">
        <template x-for="aviso in avisos" :key="aviso.id">
            <div class="flex items-start gap-2 rounded-lg border border-gray-300 bg-white p-3 shadow-lg">
                <pre class="flex-1 whitespace-pre-wrap font-sans text-sm text-gray-800" x-text="aviso.mensaje"></pre>
                <button
                    type="button"
                    class="shrink-0 text-lg leading-none text-gray-400 hover:text-gray-700"
                    x-on:click="avisos = avisos.filter(a => a.id !== aviso.id)"
                >&times;</button>
            </div>
        </template>
    </div>

    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.facturacion-pdf'])
    @include('livewire.contabilidad._subnav')

    <div class="p-4">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-start">
    <div class="space-y-6" style="flex:65 1 0;min-width:0">

    <h1 class="text-2xl font-semibold text-gray-900">Facturación PDF</h1>
    <p class="text-sm text-gray-500">
        Suma y Balerga son procesos independientes: cada uno se procesa y se envía por separado.
        «Procesar (vista previa)» parte los PDF y muestra el plan de correos sin mandar nada.
        «Procesar y enviar» hace lo mismo y además manda los correos reales.
    </p>

    <div class="grid gap-6 sm:grid-cols-2">
        @foreach ($this->clientes as $id => $c)
            <div wire:key="cliente-{{ $id }}" class="p-4 bg-white border rounded-lg shadow">
                <h2 class="text-lg font-semibold text-gray-900">{{ $c['label'] }}</h2>
                <p class="mt-1 mb-3 text-xs text-gray-500">{{ $c['ayuda'] }}</p>
                <div class="flex flex-wrap gap-2">
                    <x-button.secondary
                        wire:click="procesar('{{ $id }}')"
                        wire:loading.attr="disabled"
                        wire:target="procesar('{{ $id }}')"
                    >
                        <span wire:loading.remove wire:target="procesar('{{ $id }}')">Procesar (vista previa)</span>
                        <span wire:loading wire:target="procesar('{{ $id }}')">⏳ Procesando…</span>
                    </x-button.secondary>
                    <x-button.primary
                        wire:click="procesarYEnviar('{{ $id }}')"
                        wire:loading.attr="disabled"
                        wire:target="procesarYEnviar('{{ $id }}')"
                        onclick="return confirm('Esto manda los correos de {{ $c['label'] }} de verdad a los destinatarios reales. ¿Seguro?')"
                    >
                        <span wire:loading.remove wire:target="procesarYEnviar('{{ $id }}')">Procesar y enviar (REAL)</span>
                        <span wire:loading wire:target="procesarYEnviar('{{ $id }}')">⏳ Enviando…</span>
                    </x-button.primary>
                </div>
            </div>
        @endforeach
    </div>

    </div>{{-- /columna izquierda --}}

    <div class="w-full" style="flex:35 1 0;min-width:0">
        <div class="sticky top-4">
            <div class="flex justify-start mb-2">
                <x-button.secondary wire:click="limpiarSalida">Borrar salida</x-button.secondary>
            </div>
            <div class="p-4 rounded-lg shadow {{ $salida !== '' ? 'bg-gray-900' : 'bg-white border border-gray-200' }}">
                <h2 class="mb-2 text-sm font-semibold {{ $salida !== '' ? 'text-gray-300' : 'text-gray-400' }}">Salida</h2>
                <pre class="overflow-auto text-xs whitespace-pre-wrap {{ $salida !== '' ? 'text-green-400' : 'text-gray-400' }}" style="max-height:calc(100vh - 9rem)" wire:loading.class="opacity-50">{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
                <div wire:loading class="mt-2 text-sm text-yellow-400">Ejecutando…</div>
            </div>
        </div>
    </div>

    </div>{{-- /flex 2 columnas --}}
    </div>
</div>
