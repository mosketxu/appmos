{{-- Control del listado de Entidades que se cambia con un clic y se guarda al momento.
     $e = entidad, $c = 'relacion' | 'facturar' | 'ciclo' | 'estado' | 'responsable'. Sin permiso de editar, solo se ve. --}}
@php $puede = auth()->user()->can('entidades.editar'); $tag = $puede ? 'button' : 'span'; @endphp
@if ($c === 'relacion')
    <span class="inline-flex gap-1">
        @foreach (['cliente' => ['Cli', 'Cliente', 'bg-green-100 text-green-800 border-green-300'], 'proveedor' => ['Pro', 'Proveedor', 'bg-blue-100 text-blue-800 border-blue-300'], 'contacto' => ['Con', 'Contacto', 'bg-purple-100 text-purple-800 border-purple-300']] as $campo => [$corto, $largo, $color])
            <{{ $tag }} @if ($puede) type="button" wire:click="alternar({{ $e->id }}, '{{ $campo }}')" @endif
                title="{{ $largo }}: {{ $e->{$campo} ? 'sí' : 'no' }}{{ $puede ? ' (clic para cambiar)' : '' }}"
                class="px-1 text-xs border rounded {{ $e->{$campo} ? $color.' font-semibold' : 'text-gray-300 border-gray-200' }}">{{ $corto }}</{{ $tag }}>
        @endforeach
    </span>
@elseif ($c === 'facturar')
    <{{ $tag }} @if ($puede) type="button" wire:click="alternar({{ $e->id }}, 'facturar')" @endif
        title="Facturar: {{ $e->facturar ? 'sí' : 'no' }}{{ $puede ? ' (clic para cambiar)' : '' }}"
        class="px-2 text-sm font-bold rounded {{ $e->facturar ? 'text-green-600 hover:bg-green-50' : 'text-red-400 hover:bg-red-50' }}">{!! $e->facturar ? '&#10003;' : '&#10007;' !!}</{{ $tag }}>
@elseif ($c === 'ciclo')
    @php $ok = in_array((int) $e->cicloimpuesto_id, [1, 3], true); @endphp
    <{{ $tag }} @if ($puede) type="button" wire:click="siguienteCiclo({{ $e->id }})" @endif
        title="Ciclo de impuestos{{ $puede ? ' (clic: pasa al siguiente)' : '' }}"
        class="px-2 py-0.5 text-xs border rounded-md whitespace-nowrap {{ $ok ? 'text-gray-600 border-gray-300 hover:bg-gray-100' : 'text-yellow-800 border-yellow-400 bg-yellow-50' }}">{{ $nombresCiclo[(int) $e->cicloimpuesto_id] ?? 'Sin definir' }}</{{ $tag }}>
@elseif ($c === 'responsable')
    @if ($puede)
        <select wire:change="cambiarResponsable({{ $e->id }}, $event.target.value)" title="Responsable Suma (se guarda al momento)"
                class="w-full py-0.5 pl-1 pr-6 text-xs border-gray-300 rounded-md {{ $e->suma_id ? 'text-gray-600' : 'text-purple-700' }}">
            <option value="" @selected(! $e->suma_id)>— sin resp. —</option>
            @foreach ($sumas as $s) <option value="{{ $s->id }}" @selected($e->suma_id == $s->id)>{{ $s->nombre }}</option> @endforeach
        </select>
    @else
        <span class="text-xs {{ $e->suma_id ? 'text-gray-600' : 'text-purple-700' }}">{{ $sumas->firstWhere('id', $e->suma_id)->nombre ?? '— sin resp. —' }}</span>
    @endif
@elseif ($c === 'estado')
    <{{ $tag }} @if ($puede) type="button" wire:click="alternar({{ $e->id }}, 'estado')" @endif
        title="Estado{{ $puede ? ' (clic para cambiar)' : '' }}"
        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs leading-4 {{ $e->estado == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $e->estado == 1 ? 'Activo' : 'Baja' }}</{{ $tag }}>
@endif
