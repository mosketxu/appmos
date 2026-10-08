{{-- Barra de avance de la lectura de la carpeta: leídas de las subidas. Se actualiza sola con el poll de revisarLectura. --}}
@php
    $progTotal = max(1, count($entrada));
    $progLeidas = collect($entrada)->filter(fn ($e) => ! in_array($e[1], ['en el servidor', 'leyendo'], true))->count();
    $progPct = (int) round($progLeidas / $progTotal * 100);
@endphp
<div style="display:flex; align-items:center; gap:.5rem; {{ $estilo ?? '' }}">
    <div style="flex:1; height:.55rem; background:#e5e7eb; border-radius:9999px; overflow:hidden; min-width:6rem">
        <div style="height:100%; width:{{ $progPct }}%; background:#4f46e5; transition:width .6s"></div>
    </div>
    <b style="font-size:.75rem; color:#3730a3; white-space:nowrap">{{ $progLeidas }} de {{ count($entrada) }} · {{ $progPct }} %</b>
</div>
