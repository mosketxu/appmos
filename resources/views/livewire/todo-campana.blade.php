<div wire:poll.30s class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
@if (empty($oculta))
    {{-- Banner grande en todas las páginas mientras un PC trabajador esté caído (solo Admin y gestores del TO-DO) --}}
    @foreach (($caidos ?? []) as $c)
        <a href="{{ route('todo', ['t' => $c['tarea_id']]) }}" wire:key="caido-{{ $c['tarea_id'] }}"
           style="position:fixed; top:0; left:0; right:0; z-index:200; display:block; padding:.65rem 1rem; background:#dc2626; color:#fff; text-align:center; font-weight:700; font-size:1rem; box-shadow:0 2px 10px rgba(0,0,0,.4)">
            ⚠ El PC {{ $c['nombre'] }} no da señales: los procesos de Appmos que dependen de él no se ejecutan
            @if ($c['pendientes']) · {{ $c['pendientes'] }} tarea(s) esperando @endif
            <span style="font-weight:400; text-decoration:underline; margin-left:.5rem">Ver el aviso</span>
        </a>
    @endforeach
    <button type="button" @click="open = !open" title="{{ $total ? $total.' aviso(s) del TO-DO' : 'Sin avisos del TO-DO' }}"
        class="relative flex items-center p-2 rounded-full hover:bg-gray-100 focus:outline-none {{ $total ? 'text-red-600' : 'text-gray-400' }}">
        <svg class="w-6 h-6 {{ $total ? 'animate-pulse' : '' }}" fill="{{ $total ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.86 17.08a23.85 23.85 0 0 0 5.45-1.31A8.97 8.97 0 0 1 18 9.75v-.7V9A6 6 0 0 0 6 9v.75a8.97 8.97 0 0 1-2.31 6.02 23.85 23.85 0 0 0 5.45 1.31m5.72 0a24.3 24.3 0 0 1-5.72 0m5.72 0a3 3 0 1 1-5.72 0"/>
        </svg>
        @if ($total)
            <span class="absolute flex items-center justify-center px-1 text-xs font-bold text-white bg-red-600 border-2 border-white rounded-full"
                style="top:-2px;right:-4px;min-width:1.25rem;height:1.25rem">{{ $total > 99 ? '99+' : $total }}</span>
        @endif
    </button>

    <div x-show="open" style="display:none" class="absolute z-30 mt-1 bg-white border border-gray-300 rounded-md shadow-lg">
        <div class="w-80 max-w-[90vw]">
            <div class="flex items-center justify-between px-3 py-2 text-xs text-gray-500 border-b border-gray-200">
                <span class="font-semibold">TO-DO · avisos sin leer</span>
                @if ($total) <button type="button" wire:click="marcarTodas" class="text-indigo-600 underline">Marcar todos como leídos</button> @endif
            </div>
            @forelse ($avisos as $a)
                <button type="button" wire:key="av{{ $a->id }}" wire:click="leer({{ $a->id }})" class="block w-full px-3 py-2 text-left border-b border-gray-100 hover:bg-indigo-50">
                    <span class="block text-sm text-gray-800"><b>{{ $a->origen?->name ?? 'Alguien' }}</b> {{ $a->texto }}</span>
                    <span class="block text-xs text-gray-500 truncate">{{ $a->tarea?->titulo }} · {{ $a->created_at->format('d/m H:i') }}</span>
                </button>
            @empty
                <p class="px-3 py-4 text-sm text-center text-gray-400">No tienes avisos nuevos.</p>
            @endforelse
            <a href="{{ route('todo') }}" class="block px-3 py-2 text-xs text-center text-indigo-600 hover:bg-gray-50">Ir al TO-DO</a>
        </div>
    </div>
@endif
</div>
