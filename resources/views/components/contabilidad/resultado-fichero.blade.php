{{-- Una línea "fichero resultado" en la pantalla de Procesos.

     Los navegadores bloquean los enlaces file:// desde una página http://, así
     que en vez de intentar abrir el fichero, al hacer clic en el enlace se
     copia su ruta Windows al portapapeles (para pegarla en el Explorador o en
     el diálogo "Abrir" de Excel).

     Prop: r = ['ruta' => 'E:\...\fichero.xlsx', ...]. --}}
@props(['r'])

<div x-data="{ copiado: false }" class="text-xs">
    <a href="#" role="button"
       class="font-mono text-blue-700 underline break-all hover:text-blue-900"
       data-ruta="{{ $r['ruta'] }}"
       title="Clic para copiar la ruta al portapapeles"
       x-on:click.prevent="navigator.clipboard && navigator.clipboard.writeText($el.dataset.ruta); copiado = true; setTimeout(() => copiado = false, 1500)">📄 {{ $r['ruta'] }}</a>
    <span x-show="copiado" style="display:none" class="ml-1 font-sans text-green-600">✓ ruta copiada</span>
</div>
