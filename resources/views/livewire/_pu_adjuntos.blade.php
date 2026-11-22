{{-- Adjuntos de una PU (22-nov-2026): miniatura si es imagen, 📎 y nombre si no; abren en otra pestaña. --}}
<div class="flex flex-wrap items-center gap-1">
    @foreach($adjuntos as $a)
        <a href="{{ route('entidad.pu.adjunto', $a->id) }}" target="_blank" title="{{ $a->nombre }}" class="inline-flex items-center text-xs text-indigo-700 hover:underline">
            @if($a->esImagen())
                <img src="{{ route('entidad.pu.adjunto', $a->id) }}" alt="{{ $a->nombre }}" loading="lazy" class="object-cover border rounded {{ $grande ? 'w-16 h-16' : 'w-8 h-8' }}">
            @else
                <span class="px-1 border border-indigo-200 rounded bg-indigo-50 {{ $grande ? 'text-sm' : '' }}" style="max-width:9rem; overflow:hidden; text-overflow:ellipsis; white-space:nowrap">📎 {{ $a->nombre }}</span>
            @endif
        </a>
    @endforeach
</div>
