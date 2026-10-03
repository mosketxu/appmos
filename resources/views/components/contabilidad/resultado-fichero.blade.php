{{-- Una línea "fichero resultado" en la pantalla de Procesos.

     Los navegadores bloquean los enlaces file:// desde una página http://, así
     que en vez de intentar abrir el fichero, al hacer clic en el enlace se
     copia su ruta Windows al portapapeles (para pegarla en el Explorador o en
     el diálogo "Abrir" de Excel). Al lado, «⬇ Descargar» lo baja por el navegador.

     Prop: r = ['ruta' => 'E:\...\fichero.xlsx', ...]. --}}
@props(['r'])

<div x-data="{ copiado: false }" class="text-xs">
    <a href="#" role="button"
       class="font-mono text-blue-700 underline break-all hover:text-blue-900"
       data-ruta="{{ $r['ruta'] }}"
       title="Clic para copiar la ruta al portapapeles"
       x-on:click.prevent="navigator.clipboard && navigator.clipboard.writeText($el.dataset.ruta); copiado = true; setTimeout(() => copiado = false, 1500)">📄 {{ $r['ruta'] }}</a>
    <span x-show="copiado" style="display:none" class="ml-1 font-sans text-green-600">✓ ruta copiada</span>
    @if (! empty($r['tarea']))
        {{-- fichero que subió un PC trabajador al terminar la tarea (web, 2026-10-03) --}}
        <button type="button" wire:click='descargarDeTarea({{ (int) $r['tarea'] }}, @js($r['nombre'] ?? ''))'
                class="ml-1 px-1.5 py-0 font-sans text-xs text-indigo-700 border border-indigo-300 rounded hover:bg-indigo-50">⬇ Descargar</button>
    @endif
    @if (! empty($r['local']))
        {{-- descarga el fichero por el navegador (2026-10-02) --}}
        <button type="button" wire:click="descargarResultado('{{ base64_encode($r['local']) }}')"
                class="ml-1 px-1.5 py-0 font-sans text-xs text-indigo-700 border border-indigo-300 rounded hover:bg-indigo-50">⬇ Descargar</button>
    @endif
</div>
