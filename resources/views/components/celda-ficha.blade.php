{{-- Celda del listado que abre la ficha con un clic, sin impedir copiar (16-oct-2026): arrastrar para seleccionar texto NO abre la ficha,
     y el doble clic copia el campo entero (✔ copiado) sin abrirla. Ctrl/Cmd+clic, en otra pestaña. Uso: <x-celda-ficha :href="..." :texto="..." class="..." /> --}}
@props(['href', 'texto' => ''])
<span x-data="{ t: null, ok: false }" title="Clic: abrir la ficha · doble clic: copiar"
    x-on:click="if (window.getSelection().toString() !== '') return; const nueva = $event.ctrlKey || $event.metaKey; clearTimeout(t);
                t = setTimeout(() => { if (window.getSelection().toString() !== '') return; nueva ? window.open(@js($href), '_blank') : (window.location.href = @js($href)) }, 280)"
    x-on:dblclick="clearTimeout(t); navigator.clipboard && navigator.clipboard.writeText(@js((string) $texto)); window.getSelection().selectAllChildren($refs.txt); ok = true; setTimeout(() => ok = false, 1500)"
    {{ $attributes->merge(['style' => 'cursor:pointer; user-select:text']) }}><span x-ref="txt">{{ $texto }}</span><span x-show="ok" x-cloak class="ml-1 text-xs text-green-700">✔ copiado</span></span>
