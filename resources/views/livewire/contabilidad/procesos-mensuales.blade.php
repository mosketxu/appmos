<div>
    @livewire('menu', ['entidad' => new \App\Models\Entidad, 'ruta' => 'contabilidad.procesos-mensuales'])
    @include('livewire.contabilidad._subnav', ['activa' => 'contabilidad.procesos-mensuales'])

    <div class="p-4 space-y-4">
        <h1 class="text-2xl font-semibold text-gray-900">Procesos mensuales</h1>
        <p class="text-sm text-gray-600">Los procesos van por empresa: cada usuario los ejecuta para sus empresas (las del panel de control).</p>

        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm text-gray-700">Empresas que gestiona <b>{{ $usuario->name }}</b></span>
            <input type="text" wire:model.live.debounce.300ms="buscar" placeholder="Buscar empresa..." class="py-1 text-sm border-gray-300 rounded-md">
            <span class="text-sm text-gray-500">{{ $empresas->count() }} empresas</span>
        </div>

        <div class="overflow-x-auto bg-white border border-gray-200 rounded-lg shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="text-left text-gray-700 bg-gray-100">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Empresa</th>
                        @foreach ($procesos as $p)
                            <th class="px-3 py-2 font-semibold text-center" title="{{ $p['descripcion'] }}">{{ $p['icono'] }} {{ $p['titulo'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($empresas as $e)
                        <tr wire:key="emp-{{ $e->id }}" class="hover:bg-gray-50">
                            <td class="px-3 py-1">{{ $e->entidad }}@if ($e->alias && $e->alias !== $e->entidad) <span class="text-xs text-gray-400">({{ $e->alias }})</span>@endif</td>
                            @foreach ($procesos as $clave => $p)
                                <td class="px-3 py-1 text-center whitespace-nowrap">
                                    @if ($clave === 'petdocimpuestos')
                                        <span class="mr-1 text-xs {{ $e->mail_peticion_check ? 'text-green-700' : 'text-gray-400' }}"
                                              title="{{ $e->mail_peticion_check ? 'Se le pide por correo' : 'No marcado en la entidad' }}{{ $e->mail_peticion_check && blank($e->mail_peticion) ? ' (sin mensaje escrito)' : '' }}">
                                            {{ $e->mail_peticion_check ? (blank($e->mail_peticion) ? '✉ sin mensaje' : '✉ sí') : '—' }}
                                        </span>
                                    @endif
                                    <button type="button" disabled title="{{ $p['listo'] ? 'Ejecutar' : 'En preparación' }}"
                                            class="px-2 py-1 text-xs text-gray-400 bg-gray-100 border border-gray-200 rounded cursor-not-allowed">▶ Ejecutar</button>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($procesos) + 1 }}" class="px-3 py-6 italic text-center text-gray-400">
                            Sin empresas: el Admin las asigna en el panel de control (Responsable Suma o marcadas a mano).
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
