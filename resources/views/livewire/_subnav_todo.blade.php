{{-- Pestañas del TO-DO (7-oct-2026): Tareas (el TO-DO de siempre) e Impuestos. Mismo aspecto de hoja de Excel que la sub-navegación de Contabilidad.
     $activa: 'todo' | 'impuestos' | 'libros-iva'. Estilos propios (el app.css de Tailwind está compilado y purgado). --}}
<style>
    .hojas { display:flex; align-items:flex-end; gap:2px; padding:10px 16px 0; background:#e5e7eb; border-bottom:1px solid #9ca3af; flex-wrap:wrap; }
    .hojas a { display:block; white-space:nowrap; padding:7px 18px 6px; font-size:.875rem; font-weight:500;
               color:#4b5563; background:#f3f4f6; border:1px solid #c4c8cf; border-bottom:0; border-radius:6px 6px 0 0; }
    .hojas a:hover { background:#fff; color:#111827; }
    .hojas a.activa { margin-bottom:-1px; padding-bottom:7px; background:#f9fafb; color:#047857; font-weight:700;
                      border-color:#9ca3af; box-shadow:inset 0 3px 0 #059669; }
</style>
<nav class="hojas">
    <a href="{{ route('todo') }}" class="{{ ($activa ?? '') === 'todo' ? 'activa' : '' }}">Tareas</a>
    @can('impuestos.ver')
        <a href="{{ route('impuestos') }}" class="{{ ($activa ?? '') === 'impuestos' ? 'activa' : '' }}">Impuestos</a>
        <a href="{{ route('impuestos.libro-iva') }}" class="{{ ($activa ?? '') === 'libros-iva' ? 'activa' : '' }}">Libros IVA</a>
    @endcan
</nav>
