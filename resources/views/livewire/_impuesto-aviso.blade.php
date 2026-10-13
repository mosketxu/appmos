{{-- Aviso al cliente de un periodo (sobre ✉): estado + comentarios. Variables: $entId, $bl, $avisos, $avisoCom. --}}
@php
    [$av, $avAt] = $avisos[$entId.'|'.$bl['k']] ?? ['sin_comunicar', null];
    $avFecha = $avAt ? \Illuminate\Support\Carbon::parse($avAt)->format('d/m/Y') : '';
    $coms = $avisoCom[$entId.'|'.$bl['k']] ?? [];
    $estados = ['sin_comunicar' => ['Sin comunicar', '#6b7280', '✉'], 'comunicado' => ['Comunicado', '#2563eb', '✉'], 'parcial' => ['Falta comunicar algún impuesto', '#dc2626', '✉❗'], 'respondido' => ['Respondido', '#16a34a', '✉✔']];
    $tit = $estados[$av][0].($avFecha && $av !== 'sin_comunicar' ? ' · comunicado el '.$avFecha : '').($coms ? ' · '.count($coms).' comentario(s)' : '').' (clic: cambiar / comentar)';
@endphp
<span class="imp-pdfw" x-data="{ o: false, t: '' }" x-on:click.outside="o = false">
    <button type="button" class="imp-av {{ $av }}" x-on:click="o = !o" title="{{ $tit }}">{{ $estados[$av][2] }}@if ($coms)<sup>{{ count($coms) }}</sup>@endif</button>
    <div class="imp-pop imp-avp" x-show="o" x-cloak>
        <div style="font-weight:600; margin-bottom:4px">Aviso al cliente · {{ $bl['titulo'] }}</div>
        @foreach ($estados as $k => [$nom, $col])
            <button type="button" class="e {{ $av === $k ? 'on' : '' }}" style="color:{{ $col }}" wire:click="fijarAviso({{ $entId }}, '{{ $bl['k'] }}', '{{ $k }}')">{{ $estados[$k][2] }} {{ $nom }}</button>
        @endforeach
        @if ($avFecha && $av !== 'sin_comunicar') <div style="color:#6b7280; margin:2px 0 6px">Comunicado el {{ $avFecha }}</div> @endif
        @foreach ($coms as [$texto, $autor, $fecha, $cid, $cuid])
            <div style="border-top:1px solid #e5e7eb; padding:4px 0">
                <div style="color:#6b7280">{{ $autor ?: '—' }} · {{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y H:i') }}
                    @if ($cuid === auth()->id() || $this->puedeTodos())
                        <button type="button" style="display:inline; width:auto; padding:0 4px; color:#b91c1c" wire:click="borrarComentarioAviso({{ $cid }})" wire:confirm="¿Borrar este comentario?">🗑</button>
                    @endif
                </div>
                <div style="white-space:pre-wrap">{{ $texto }}</div>
            </div>
        @endforeach
        <div style="border-top:1px solid #e5e7eb; padding-top:6px; display:flex; gap:4px">
            <textarea x-model="t" rows="2" class="w-full text-xs border-gray-300 rounded" placeholder="Añadir comentario…"></textarea>
            <button type="button" style="width:auto; background:#4f46e5; color:#fff; padding:2px 8px" x-on:click="if (t.trim()) { $wire.comentarAviso({{ $entId }}, '{{ $bl['k'] }}', t); t = '' }">＋</button>
        </div>
    </div>
</span>
