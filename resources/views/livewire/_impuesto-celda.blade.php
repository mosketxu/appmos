{{-- Una casilla de Impuestos: marca de estado + icono PDF. Variables: $ob, $per, $e (estado), $docs, $color, $letra, $etq. --}}
@php
    $clave = $ob->entidad_id.'|'.$ob->codigo.'|'.$ob->etiqueta.'|'.$per;
    $lista = $docs[$clave] ?? [];
    $tipoPdf = in_array($e, ['presentado', 'visto'], true) ? 'presentado' : 'borrador';
    $principal = collect($lista)->where('tipo', $tipoPdf)->last();
    $otros = collect($lista)->reject(fn ($d) => $principal && $d->id === $principal->id)->values();
    $conPdf = in_array($e, ['revision', 'revisado', 'presentado', 'visto'], true);
    $titulo = ($ob->etiqueta !== '' ? $ob->etiqueta.' · ' : '').(ctype_digit($ob->codigo) ? 'M'.$ob->codigo : $ob->codigo).' · '.\App\Support\Impuestos::etiquetaPeriodo($per, $this->ejercicio, $ob->desfase).' · '.$etq[$e].' (clic: siguiente estado · Mayús+clic: no se presenta)';
    $cm = ($coment ?? [])[$ob->id.'|'.$per] ?? [];
    $cmTitulo = collect($cm)->map(fn ($c) => ($c[1] ? $c[1].': ' : '').$c[0])->implode("\n");
    $subir = "\$wire.subirA = '".$ob->id.'|'.$per."'; document.getElementById('imp-fichero').click()";
@endphp
<span class="imp-cel">
    @php $bloqueada = $e === 'visto' && ! $this->puedeVisto(); @endphp
    @if ($bloqueada)
        <span class="imp-m" title="{{ $titulo }} · lo ha validado Marta: solo ella lo cambia" style="background:{{ $color[$e] }}; cursor:default">{{ $letra[$e] }}</span>
    @else
        <button type="button" wire:click="clic({{ $ob->id }}, '{{ $per }}', $event.shiftKey)" title="{{ $titulo }}"
            class="imp-m {{ $e === 'no' ? 'no' : '' }}" @if ($e !== 'no') style="background:{{ $color[$e] }}" @endif>{{ $e === 'no' ? '' : $letra[$e] }}</button>
    @endif
    @if ($conPdf)
        @if (count($lista) === 1 && $principal)
            <a href="{{ route('impuestos.documento', $principal->id) }}" target="_blank" class="imp-pdf" style="color:{{ $color[$e] }}" title="{{ $principal->nombre }}">@include('livewire._impuesto-pdf')</a>
        @elseif ($lista)
            {{-- Varios documentos (dos declaraciones, justificantes, aplazamientos...): hojas apiladas; el menú deja marcar los que se quieren abrir --}}
            <span class="imp-pdfw" x-data="{ o: false, sel: [] }" x-on:click.outside="o = false">
                <button type="button" class="imp-pdf {{ $principal ? '' : 'gris' }}" @if ($principal) style="color:{{ $color[$e] }}" @endif x-on:click="o = !o" title="{{ count($lista) }} documentos">
                    @if (count($lista) > 1) @include('livewire._impuesto-pdf-varios') @else @include('livewire._impuesto-pdf') @endif
                </button>
                <div class="imp-pop" x-show="o" x-cloak>
                    @foreach ($lista as $d)
                        <label style="display:flex; gap:6px; align-items:center; padding:3px 6px; font-size:12px; cursor:pointer">
                            <input type="checkbox" value="{{ route('impuestos.documento', $d->id) }}" x-model="sel" class="border-gray-300 rounded">
                            <a href="{{ route('impuestos.documento', $d->id) }}" target="_blank" style="padding:0; width:auto">{{ ['presentado' => 'Presentado', 'borrador' => 'Borrador', 'otro' => 'Otro'][$d->tipo] ?? $d->tipo }} · {{ $d->nombre }}</a>
                        </label>
                    @endforeach
                    <button type="button" x-show="sel.length" x-on:click="sel.forEach(u => window.open(u, '_blank')); sel = []; o = false" style="border-top:1px solid #e5e7eb; font-weight:600">Abrir los seleccionados (<span x-text="sel.length"></span>)</button>
                    <button type="button" x-on:click="o = false; {!! $subir !!}" style="border-top:1px solid #e5e7eb">＋ Subir {{ $tipoPdf === 'presentado' ? 'el presentado' : 'un borrador' }}…</button>
                </div>
            </span>
        @else
            <button type="button" class="imp-pdf gris" x-on:click="{!! $subir !!}" title="Sin PDF: pulsa para subir {{ $tipoPdf === 'presentado' ? 'el presentado' : 'el borrador' }}">@include('livewire._impuesto-pdf')</button>
        @endif
    @endif
    <button type="button" class="imp-com {{ $cm ? 'tiene' : '' }}" wire:click="abrirComentarios({{ $ob->id }}, '{{ $per }}')"
        title="{{ $cm ? $cmTitulo : 'Añadir un comentario' }}">{{ $cm ? '💬' : '' }}@if (count($cm) > 1)<sup>{{ count($cm) }}</sup>@endif</button>
</span>
