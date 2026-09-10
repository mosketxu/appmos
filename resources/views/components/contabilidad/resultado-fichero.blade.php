{{-- Una línea "fichero resultado" en la pantalla de Procesos.

     Los navegadores BLOQUEAN los enlaces file:// desde una página http://, así
     que un <a href="file://..."> no abre nada al hacer clic. Aquí:
       - "abrir"  -> lo abre el servidor (misma máquina) con cmd.exe /c start,
                     por índice (nada de rutas en el wire:click, sin líos de
                     comillas/barras).
       - "copiar" -> copia la ruta Windows al portapapeles para pegarla en el
                     explorador o en el diálogo Abrir de Excel.

     Props: r = ['ruta'=>'E:\...', 'url'=>'file:///...', 'cruda'=>'/mnt/e/...'],
            resKey = clave en $resultados, idx = posición dentro de esa clave. --}}
@props(['r', 'resKey', 'idx'])

<div class="flex flex-wrap items-start text-xs gap-x-2" x-data="{ copiado: false }">
    <span class="font-mono text-gray-700 break-all">📄 {{ $r['ruta'] }}</span>
    <button type="button"
            class="text-blue-700 underline shrink-0 hover:text-blue-900"
            wire:click="abrirFichero('{{ $resKey }}', {{ (int) $idx }})"
            wire:loading.attr="disabled"
            wire:target="abrirFichero('{{ $resKey }}', {{ (int) $idx }})">abrir</button>
    <button type="button"
            class="text-blue-700 underline shrink-0 hover:text-blue-900"
            data-ruta="{{ $r['ruta'] }}"
            x-on:click="navigator.clipboard && navigator.clipboard.writeText($el.dataset.ruta); copiado = true; setTimeout(() => copiado = false, 1500)">
        <span x-show="!copiado">copiar</span><span x-show="copiado">✓ copiada</span>
    </button>
</div>
