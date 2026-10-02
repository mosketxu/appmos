{{-- ⓘ con el detalle de un dato, como en otras pantallas (2-oct-2026). Se abre con un clic y se cierra
     al pinchar fuera. Estilos en línea: el app.css de Tailwind 2 está purgado. --}}
<span x-data="{ o: false }" style="position:relative; display:inline-block">
    <button type="button" x-on:click="o = !o" x-on:click.outside="o = false"
            class="text-indigo-500 hover:text-indigo-700" title="Información">ⓘ</button>
    <div x-show="o" x-cloak
         style="position:absolute; left:0; top:1.5rem; z-index:40; width:24rem; max-width:80vw; line-height:1.35"
         class="p-3 text-xs font-normal text-left text-gray-700 bg-white border border-gray-300 rounded-md shadow-lg">
        {{ $slot }}
    </div>
</span>
