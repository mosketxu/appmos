{{-- Panel "Salida" común de las pantallas de Contabilidad. 2026-09-29 (pedido de Alex):
     va al FINAL de la pantalla, a todo el ancho, para dejar arriba el espacio útil;
     un botón fijo arriba a la derecha baja hasta ella y dice cómo fue la última
     ejecución: verde = OK, naranja = avisos, rojo = errores (gris = sin salida).
     Uso: @include('livewire.contabilidad._salida') justo antes del </div> raíz.
     $cargando (por defecto true): atenuar y "Ejecutando…" durante las peticiones.
     Estilos en línea porque el app.css de Tailwind 2 está compilado y purgado. --}}
@php
    $cargando = $cargando ?? true;
    ['estado' => $estadoSalida, 'color' => $colorSalida, 'icono' => $iconoSalida] = \App\Support\EstadoSalida::de($salida);
@endphp
<a href="#salida" title="Ir a la salida"
   x-on:click.prevent="document.getElementById('salida').scrollIntoView({ behavior: 'smooth' })"
   style="position:fixed; right:1rem; top:4.5rem; z-index:40; display:inline-flex; align-items:center; justify-content:center; gap:.5rem; min-width:9rem; padding:.6rem 1.25rem; border-radius:.5rem; color:#fff; font-size:1rem; font-weight:700; text-decoration:none; box-shadow:0 4px 14px rgba(0,0,0,.3); background:{{ $colorSalida }}">
    @if ($cargando)
        <span wire:loading>⏳</span><span wire:loading.remove>{{ $iconoSalida }}</span>
    @else
        <span>{{ $iconoSalida }}</span>
    @endif
    Salida ↓
</a>

<div id="salida" style="scroll-margin-top:1rem">
    <div class="p-4 rounded-lg shadow {{ $salida !== '' ? 'bg-gray-900' : 'bg-white border border-gray-200' }}"
         @if ($estadoSalida !== 'vacio') style="border-left:6px solid {{ $colorSalida }}" @endif>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold {{ $salida !== '' ? 'text-gray-300' : 'text-gray-400' }}">Salida</h2>
            <div class="flex items-center gap-2">
                <a href="#" x-on:click.prevent="window.scrollTo({ top: 0, behavior: 'smooth' })" class="text-xs text-gray-400 hover:underline">↑ Arriba</a>
                <x-button.secondary wire:click="limpiarSalida">Borrar salida</x-button.secondary>
            </div>
        </div>
        <pre class="overflow-auto text-xs whitespace-pre-wrap {{ $salida !== '' ? 'text-green-400' : 'text-gray-400' }}" style="max-height:70vh" @if ($cargando) wire:loading.class="opacity-50" @endif>{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
        @if ($cargando)
            <div wire:loading class="mt-2 text-sm text-yellow-400">Ejecutando…</div>
        @endif
    </div>
</div>
