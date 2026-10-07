{{-- Una casilla de Impuestos: marca de estado + icono PDF. Variables: $ob, $per, $e (estado), $docs, $color, $letra, $etq. --}}
@php
    $clave = $ob->entidad_id.'|'.$ob->codigo.'|'.$per;
    $lista = $docs[$clave] ?? [];
    $tipoPdf = $e === 'presentado' ? 'presentado' : 'borrador';
    $principal = collect($lista)->where('tipo', $tipoPdf)->last();
    $otros = collect($lista)->reject(fn ($d) => $principal && $d->id === $principal->id)->values();
    $conPdf = in_array($e, ['revision', 'revisado', 'presentado'], true);
    $titulo = \App\Support\Impuestos::etiquetaPeriodo($per, $this->ejercicio, $ob->desfase).' · '.$etq[$e].' (clic: cambiar)';
    $subir = "\$wire.subirA = '".$ob->id.'|'.$per."'; document.getElementById('imp-fichero').click()";
@endphp
<span class="imp-cel">
    <button type="button" wire:click="clic({{ $ob->id }}, '{{ $per }}')" title="{{ $titulo }}"
        class="imp-m {{ $e === 'no' ? 'no' : '' }}" @if ($e !== 'no') style="background:{{ $color[$e] }}" @endif>{{ $e === 'no' ? '' : $letra[$e] }}</button>
    @if ($conPdf)
        @if ($principal && $otros->isEmpty())
            <a href="{{ route('impuestos.documento', $principal->id) }}" target="_blank" class="imp-pdf" style="color:{{ $color[$e] }}" title="{{ $principal->nombre }}">@include('livewire._impuesto-pdf')</a>
        @elseif ($lista)
            <span class="imp-pdfw" x-data="{ o: false }" x-on:click.outside="o = false">
                <button type="button" class="imp-pdf {{ $principal ? '' : 'gris' }}" @if ($principal) style="color:{{ $color[$e] }}" @endif x-on:click="o = !o" title="PDF ({{ count($lista) }})">@include('livewire._impuesto-pdf')</button>
                <div class="imp-pop" x-show="o" x-cloak>
                    @foreach ($lista as $d)
                        <a href="{{ route('impuestos.documento', $d->id) }}" target="_blank">{{ ['presentado' => 'Presentado', 'borrador' => 'Borrador', 'otro' => 'Otro'][$d->tipo] ?? $d->tipo }} · {{ $d->nombre }}</a>
                    @endforeach
                    <button type="button" x-on:click="o = false; {!! $subir !!}" style="border-top:1px solid #e5e7eb">＋ Subir {{ $tipoPdf === 'presentado' ? 'el presentado' : 'un borrador' }}…</button>
                </div>
            </span>
        @else
            <button type="button" class="imp-pdf gris" x-on:click="{!! $subir !!}" title="Sin PDF: pulsa para subir {{ $tipoPdf === 'presentado' ? 'el presentado' : 'el borrador' }}">@include('livewire._impuesto-pdf')</button>
        @endif
    @endif
</span>
