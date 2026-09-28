{{-- Panel "Salida" común de las pantallas de Contabilidad (pedido 2026-09-28):
     va a la derecha del PRIMER bloque de la pantalla y acaba a la vez que él
     (si hay más texto, scroll dentro); lo que va debajo usa todo el ancho.
     Uso: <div class="fila-salida"><div class="col-principal space-y-6" style="--g:65">
     …primer bloque…</div> @include('livewire.contabilidad._salida', ['ancho' => 35]) </div>
     $cargando (por defecto true): atenuar y "Ejecutando…" durante las peticiones.
     Estilos propios porque el app.css de Tailwind 2 está compilado y purgado. --}}
@once
<style>
    .fila-salida { display:flex; flex-direction:column; gap:1.5rem; }
    .fila-salida > .col-principal, .col-salida { min-width:0; }
    .col-salida .salida-pre { max-height:50vh; }
    @media (min-width:1280px) {
        .fila-salida { flex-direction:row; align-items:stretch; }
        .fila-salida > .col-principal { flex:var(--g, 65) 1 0; }
        .col-salida { flex:var(--g, 35) 1 0; position:relative; min-height:16rem; }
        .col-salida > .caja-salida { position:absolute; inset:0; display:flex; flex-direction:column; }
        .col-salida .panel-salida { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; }
        .col-salida .salida-pre { flex:1 1 auto; min-height:0; max-height:none; }
    }
</style>
@endonce
@php $cargando = $cargando ?? true; @endphp
<div class="col-salida" style="--g:{{ $ancho ?? 35 }}">
    <div class="caja-salida">
        <div class="flex justify-start mb-2">
            <x-button.secondary wire:click="limpiarSalida">Borrar salida</x-button.secondary>
        </div>
        <div class="panel-salida p-4 rounded-lg shadow {{ $salida !== '' ? 'bg-gray-900' : 'bg-white border border-gray-200' }}">
            <h2 class="mb-2 text-sm font-semibold {{ $salida !== '' ? 'text-gray-300' : 'text-gray-400' }}">Salida</h2>
            <pre class="salida-pre overflow-auto text-xs whitespace-pre-wrap {{ $salida !== '' ? 'text-green-400' : 'text-gray-400' }}" @if ($cargando) wire:loading.class="opacity-50" @endif>{{ $salida ?: '(sin ejecuciones todavía)' }}</pre>
            @if ($cargando)
                <div wire:loading class="mt-2 text-sm text-yellow-400">Ejecutando…</div>
            @endif
        </div>
    </div>
</div>
