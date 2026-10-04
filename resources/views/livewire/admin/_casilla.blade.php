@php
    $usuarios = $c['tipo'] === 'usuario';
    $accion = $usuarios
        ? 'alternarUsuario('.$c['id'].', '.json_encode($clave).')'
        : 'alternar('.json_encode($c['nombre']).', '.json_encode($clave).')';
@endphp
<td class="px-1 py-1.5 text-center" wire:key="c-{{ $c['tipo'] }}-{{ $c['id'] }}-{{ $clave }}-{{ $est }}" @if (! empty($c['inicio'])) style="border-left:2px solid #9ca3af" @endif>
    <input type="checkbox" @checked($est > 0) @disabled($est === 2) @if ($est !== 2) wire:click="{{ $accion }}" @endif
           class="border-gray-300 rounded {{ $est === 2 ? 'opacity-50' : 'cursor-pointer' }}"
           title="{{ $est === 2 ? ($c['admin'] ? 'Admin: todo' : ($usuarios ? 'Lo da su rol' : '')) : '' }}">
</td>
