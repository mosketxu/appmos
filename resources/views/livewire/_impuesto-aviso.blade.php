{{-- Aviso al cliente de un periodo (sobre ✉): estado + comentarios. Variables: $entId, $bl, $avisos, $avisoCom. --}}
@php
    [$av, $avAt, $falta] = $avisos[$entId.'|'.$bl['k']] ?? ['sin_comunicar', null, false];
    if (! in_array($av, ['sin_comunicar', 'comunicado', 'respondido'], true)) { $av = 'sin_comunicar'; } // valor truncado/antiguo en BD
    $avFecha = $avAt ? \Illuminate\Support\Carbon::parse($avAt)->format('d/m/Y') : '';
    $coms = $avisoCom[$entId.'|'.$bl['k']] ?? [];
    $estados = ['sin_comunicar' => ['Sin comunicar', '#6b7280', '✉'], 'comunicado' => ['Comunicado', '#2563eb', '✉'], 'respondido' => ['Respondido', '#16a34a', '✉✔']];
    $tit = $estados[$av][0].($avFecha && $av !== 'sin_comunicar' ? ' · comunicado el '.$avFecha : '').($falta ? ' · ❗ falta comunicar algún impuesto' : '').($coms ? ' · '.count($coms).' comentario(s)' : '').' (clic: cambiar / comentar)';
@endphp
@php $clk = $entId.'|'.$bl['k']; @endphp
<span class="imp-pdfw" wire:key="av-{{ $entId }}-{{ $bl['k'] }}-{{ $av }}-{{ (int) $falta }}-{{ count($coms) }}" x-data="{ o: {{ $avisoAbierto === $clk ? 'true' : 'false' }}, t: '' }" x-on:click.outside="o = false">
    <button type="button" class="imp-av {{ $av }}" x-on:click="o = !o" title="{{ $tit }}"><svg viewBox="0 0 24 24" aria-hidden="true" width="19" height="19" style="vertical-align:middle"><path fill="currentColor" d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm1.6 2 7.4 5.6L19.4 7H4.6zM4 17h16V9.4l-8 6-8-6V17z"/></svg>@if ($av === 'respondido')<b class="ok">✔</b>@endif @if ($coms)<sup>{{ count($coms) }}</sup>@endif</button>@if ($falta)<span class="imp-falta" style="color:#dc2626; font-weight:700" title="Falta comunicar algún impuesto (clic en el sobre para quitarlo)">❗</span>@endif
    <div class="imp-pop imp-avp" x-show="o" x-cloak>
        <div style="font-weight:600; margin-bottom:4px">Aviso al cliente · {{ $bl['titulo'] }}</div>
        <button type="button" class="e" style="color:#4f46e5; font-weight:600" x-on:click="o = false" wire:click="ciAbrir({{ $entId }}, '{{ $bl['k'] }}')" title="Propone el correo con los importes y días de cargo, en el idioma del cliente, y lo deja como borrador en tu Outlook">📝 Preparar correo (borrador)…</button>
        @foreach ($estados as $k => [$nom, $col])
            <button type="button" class="e {{ $av === $k ? 'on' : '' }}" style="color:{{ $col }}" x-on:click="o = false" wire:click="fijarAviso({{ $entId }}, '{{ $bl['k'] }}', '{{ $k }}')">{{ $estados[$k][2] }} {{ $nom }}</button>
        @endforeach
        <button type="button" class="e {{ $falta ? 'on' : '' }}" style="color:#dc2626" x-on:click="o = false" wire:click="alternarFalta({{ $entId }}, '{{ $bl['k'] }}')">❗ Falta comunicar algún impuesto {{ $falta ? '(quitar)' : '' }}</button>
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
