{{-- Recuadro de ficheros base de SAGE (plan, clientes, proveedores, mayor, sumas y saldos), siempre en el mismo sitio: justo debajo del título. Uso:
     @include('livewire._ficheros_base', ['eid' => <id de entidad>])  (19-oct-2026) --}}
@if ((int) ($eid ?? 0) > 0)
    @livewire('ficheros-base-subida', ['entidadId' => (int) $eid], key('fb-'.(int) $eid.'-'.(($clave ?? '') ?: 'x')))
@endif
