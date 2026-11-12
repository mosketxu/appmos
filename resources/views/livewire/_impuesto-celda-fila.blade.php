@if ($ob->etiqueta !== '')
    <span class="text-xs font-semibold text-indigo-700" title="{{ $ob->etiqueta }}">{{ mb_substr($ob->etiqueta, 0, 3) }}</span>
@endif
@include('livewire._impuesto-celda', ['ob' => $ob, 'per' => $per, 'e' => $e, 'docs' => $docs, 'coment' => $coment, 'color' => $color, 'letra' => $letra, 'etq' => $etq])
