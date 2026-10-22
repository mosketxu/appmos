<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'todo'])
    @include('livewire._subnav_todo', ['activa' => 'mejoras'])

    <div class="p-4 space-y-3">
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('todo', ['nueva' => 'mejora']) }}" class="px-4 py-2 text-base font-medium text-white bg-indigo-600 rounded-md hover:bg-indigo-700">💡 Pedir una mejora</a>
            <select wire:model.live="estado" class="py-2 text-base border-gray-300 rounded-md">
                <option value="abiertas">Abiertas</option>
                <option value="todas">Todas</option>
            </select>
            <span class="text-sm text-gray-600">{{ $veTodas ? 'Aquí ves todas las mejoras pedidas.' : 'Aquí ves las mejoras que has pedido.' }} Pide una mejora o cuenta qué no te funciona: queda como tarea para Alex.</span>
        </div>

        <div class="bg-white border rounded-lg shadow">
            <table class="w-full text-base">
                <thead class="bg-gray-50">
                    <tr class="text-sm text-left text-gray-500">
                        <th class="px-3 py-2">Mejora</th>
                        <th class="px-3 py-2">Pedida por</th>
                        <th class="px-3 py-2">Fecha</th>
                        <th class="px-3 py-2">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($mejoras as $m)
                        <tr wire:key="mj-{{ $m->id }}" class="hover:bg-gray-50">
                            <td class="px-3 py-2"><a href="{{ route('todo', ['t' => $m->id]) }}" class="text-indigo-700 hover:underline">{{ \Illuminate\Support\Str::after($m->titulo, 'Mejora:') }}</a></td>
                            <td class="px-3 py-2">{{ $m->creador?->name ?? '—' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ $m->created_at->format('d/m/Y') }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ \App\Models\TodoTarea::ESTADOS[$m->estado] ?? $m->estado }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-3 py-4 text-center text-gray-400">No hay mejoras {{ $estado === 'abiertas' ? 'abiertas' : 'pedidas' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
