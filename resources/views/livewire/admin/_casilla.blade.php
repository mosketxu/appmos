@php
    $usuarios = $c['tipo'] === 'usuario';
    $accion = $usuarios
        ? 'alternarUsuario('.$c['id'].', '.json_encode($clave).')'
        : 'alternar('.json_encode($c['nombre']).', '.json_encode($clave).')';
@endphp
<td class="px-1 py-1.5 text-center" wire:key="c-{{ $c['tipo'] }}-{{ $c['id'] }}-{{ $clave }}-{{ $est }}" @if (! empty($c['inicio'])) style="border-left:2px solid #9ca3af" @endif @if (! ($c['activo'] ?? true)) class="bg-gray-100" @endif>
    @php $inactivo = $usuarios && ! ($c['activo'] ?? true); @endphp
    <input type="checkbox" @checked($est > 0) @disabled($est === 2 || $inactivo) @if ($est !== 2 && ! $inactivo) wire:click="{{ $accion }}" @endif
           class="border-gray-300 rounded {{ $est === 2 || $inactivo ? 'opacity-50' : 'cursor-pointer' }}"
           @if ($inactivo) style="accent-color:#9ca3af; opacity:.4; background-color:#e5e7eb" @endif
           title="{{ $inactivo ? ($est === 3 ? 'Usuario inactivo: tenía este acceso (no puede entrar)' : 'Usuario inactivo') : ($est === 2 ? ($c['admin'] ? 'Admin: todo' : ($usuarios ? 'Lo da su rol' : '')) : '') }}">
</td>
