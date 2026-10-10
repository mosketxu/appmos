@php
    // Primera pantalla de Contabilidad a la que tiene acceso (ver config/accesos.php)
    $rutaContabilidad = collect([
        'contabilidad.procesos' => 'contabilidad.procesos',
        'contabilidad.facturacionpdf' => 'contabilidad.facturacion-pdf',
        'contabilidad.durcal' => 'contabilidad.durcal',
        'contabilidad.bancos' => 'contabilidad.bancos',
        'contabilidad.facturasocr' => 'contabilidad.facturas-ocr',
        'contabilidad.neteges' => 'contabilidad.neteges',
        'contabilidad.leoybra' => 'contabilidad.leoybra',
        'contabilidad.procesosmensuales' => 'contabilidad.procesos-mensuales',
    ])->first(fn ($ruta, $permiso) => auth()->user()->can($permiso));
@endphp
@php
    // Pestaña Impuestos (9-oct-2026): primera subpestaña a la que tiene acceso
    $rutaImpuestos = auth()->user()->can('impuestos.ver') ? 'impuestos' : (auth()->user()->can('contabilidad.is') ? 'contabilidad.is' : null);
@endphp
<nav x-data="{ open: false }" class="relative bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    {{-- Pantalla mediana (640-1599 px): la barra no cabe en una línea, así que pasa a dos (enlaces arriba, campana/Claude/PCs/usuario debajo) en vez de solaparse --}}
    <style>
        .ico-act { background:#fff; border-radius:.25rem; box-shadow:0 0 0 2px #a5b4fc; width:1.5rem !important; margin-right:.5rem !important }
        @media (min-width:640px) and (max-width:1599px) {
            .barra-sup { flex-wrap:wrap; height:auto !important; min-height:3.5rem; row-gap:.25rem; padding-bottom:.35rem }
            .barra-sup > .barra-izq { min-width:0; flex-wrap:wrap }
            .barra-sup > .barra-der { margin-left:auto; flex-wrap:wrap; row-gap:.25rem; justify-content:flex-end }
            .barra-sup > .barra-claude { order:2; margin-left:auto !important }
            .barra-sup > .barra-campana { order:3 }
            .barra-sup > .barra-der { order:4 }
        }
    </style>
    <div class="max-w-full px-4 mx-auto">
        <div class="flex justify-between h-14 barra-sup">
            <div class="flex barra-izq">
                <!-- Logo -->
                <div class="flex items-center flex-shrink-0">
                    <a href="{{ route('entidades') }}">
                        <x-jet-application-mark class="block w-auto h-9" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden whitespace-nowrap sm:-my-px sm:ml-10 sm:flex" style="column-gap:1.2rem; min-width:0">
                    @can('entidades.ver')
                        <x-jet-nav-link href="{{ route('entidades') }}" :active="request()->routeIs('entidades')">
                            {{ __('Entidades') }}
                        </x-jet-nav-link>
                    @endcan
                    <x-jet-nav-link href="{{ route('todo') }}" :active="request()->routeIs('todo')">
                        TO-DO
                    </x-jet-nav-link>
                    @if ($rutaImpuestos)
                        <x-jet-nav-link href="{{ route($rutaImpuestos) }}" :active="request()->routeIs('impuestos', 'impuestos.*', 'contabilidad.is')">
                            Impuestos
                        </x-jet-nav-link>
                    @endif
                    <a href="{{ route('todo', ['nueva' => 'mejora']) }}" class="inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 text-gray-500 border-b-2 border-transparent hover:text-gray-700"
                       title="Pide una mejora o cuenta qué no te funciona: queda como tarea del TO-DO para Alex">💡 Pedir mejora</a>
                    @if ($rutaContabilidad)
                        <x-jet-nav-link href="{{ route($rutaContabilidad) }}" :active="request()->routeIs('contabilidad.*') && ! request()->routeIs('contabilidad.is')">
                            {{ __('Contabilidad') }}
                        </x-jet-nav-link>
                    @endif
                    @role('Admin')
                        <x-jet-nav-link href="{{ route('admin.usuarios') }}" :active="request()->routeIs('admin.*')">
                            {{ __('Panel de control') }}
                        </x-jet-nav-link>
                    @endrole
                    {{-- Menú Facturación oculto a petición
                    <div class="relative mt-3 ">
                        <x-jet-dropdown align="center" width="w-36" >
                            <x-slot name="trigger">
                                <span class="inline-flex rounded-md">
                                    <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-transparent rounded-md bg-blu hover:bg-gray-50 hover:text-gray-700 focus:outline-none focus:bg-gray-50 active:bg-blue-700">
                                        Facturación
                                        <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </span>
                            </x-slot>
                            <x-slot name="content">
                                <div class="w-36">
                                    <x-jet-dropdown-link href="{{ route('facturacion.index') }}" class="text-center">
                                        {{ __('Facturación') }}
                                    </x-jet-dropdown-link>
                                    <x-jet-dropdown-link href="{{ route('facturacion.prefacturas') }}"  class="text-center">
                                        {{ __('Pre-Facturación') }}
                                    </x-jet-dropdown-link>
                                </div>
                            </x-slot>
                        </x-jet-dropdown>
                    </div>
                    --}}
                </div>
            </div>

            {{-- Campana del TO-DO: centrada en la barra --}}
            <div class="hidden sm:flex sm:items-center barra-campana" style="flex-shrink:0; margin:0 .4rem">
                @livewire('todo-campana')
            </div>

            {{-- Estado de Claude (solo gestores): en pantalla mediana sube a la línea de arriba, junto a la campana --}}
            @if (\App\Support\TodoClaude::esGestor(auth()->user()))
                <div class="hidden sm:flex sm:items-center barra-claude" style="flex-shrink:0; margin:0 .4rem">@livewire('todo-claude-estado')</div>
            @endif

            <div class="hidden sm:flex sm:items-center sm:ml-6 barra-der">
                @if($entmenu->id)
                    <div class="items-center hidden p-2 space-x-3 bg-gray-100 rounded-lg sm:-my-px sm:ml-4 sm:flex">
                        {{-- Buscador: se escribe el nombre y se elige de la lista --}}
                        <div x-data="{ q: @js($entmenu->entidad), ids: @js($entidades->pluck('id','entidad')) }">
                            <input type="text" list="lista-entidades-menu" x-model="q" @focus="$event.target.select()"
                                   @change="if (ids[q]) $wire.set('filtroentidad', ids[q])"
                                   placeholder="Buscar entidad…" class="py-1 text-sm border-gray-300 rounded-md w-36 lg:w-48">
                            <datalist id="lista-entidades-menu">
                                @foreach ($entidades as $entidad)
                                <option value="{{ $entidad->entidad }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <div class="flex items-center">
                            @php $act = fn ($r) => request()->routeIs($r) ? 'ico-act' : ''; @endphp
                            <x-icon.key href="{{ route('entidad.pu',$entmenu) }}" title="Pus" class="{{ $act('entidad.pu') }}"/>
                            <x-icon.usergroup href="{{ route('entidad.contacto',$entmenu) }}" title="Contactos" class="{{ $act('entidad.contacto') }}"/>
                            <x-icon.clock-a href="{{ route('entidad.historial',$entmenu) }}" title="Historial" class="{{ $act('entidad.historial') }}"/>
                            <x-icon.impuestos href="{{ route('entidad.impuestos',$entmenu) }}" title="Impuestos" class="{{ $act('entidad.impuestos') }}"/>
                            <x-icon.edit-a href="{{ route('entidad.edit',$entmenu) }}" title="{{ auth()->user()->can('entidades.editar') ? 'Editar' : 'Ficha' }}" class="{{ $act('entidad.edit') }}"/>
                            @can('facturacion.ver')
                            <x-jet-dropdown align="center" width="w-36">
                                <x-slot name="trigger">
                                    <button type="button" title="Facturas / Prefacturas" class="mr-2 text-gray-500 transform hover:text-gray-700 hover:scale-125">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" viewBox="0 0 20 20" fill="currentColor"><path d="M4 4a2 2 0 012-2h5l5 5v9a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm7 0v3h3l-3-3zM7 11h6v1.5H7V11zm0 3h6v1.5H7V14z"/></svg>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <div class="w-36">
                                        <x-jet-dropdown-link href="{{ route('facturacion.show',$entmenu)}}" class="text-center">{{ __('Facturas') }}</x-jet-dropdown-link>
                                        <x-jet-dropdown-link href="{{ route('facturacion.prefacturasentidad',$entmenu)}}" class="text-center">{{ __('Prefacturas') }}</x-jet-dropdown-link>
                                    </div>
                                </x-slot>
                            </x-jet-dropdown>
                            <a href="{{ route('facturacionconcepto.entidad',$entmenu)}}" title="Fac.Conceptos" class="w-5 mr-2 text-green-600 transform hover:text-green-800 hover:scale-125 {{ $act('facturacionconcepto.entidad') }}"><x-icon.coins/></a>
                            @endcan
                        </div>
                    </div>
                @endif
                {{-- PCs de trabajo (cola de tareas): conectados y tareas en curso, para todos los usuarios --}}
                <div class="mr-2">@livewire('trabajadores-estado')</div>
                <!-- Settings Dropdown -->
                <div class="relative ml-3">
                    <x-jet-dropdown align="right" width="w-64">
                        <x-slot name="trigger">
                            <span class="inline-flex rounded-md">
                                <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-transparent rounded-md hover:text-gray-700 focus:outline-none">
                                    <span title="{{ Auth::user()->name }}">{{ \App\Support\NombreCorto::de(Auth::user()) }}</span>
                                    <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </span>
                        </x-slot>

                        <x-slot name="content">
                            <!-- Account Management -->
                            <div class="block px-4 py-2 text-xs text-gray-400">
                                {{ __('Manage Account') }}
                            </div>

                            <x-jet-dropdown-link href="{{ route('profile.show') }}">
                                {{ __('Profile') }}
                            </x-jet-dropdown-link>

                            {{-- Sus clientes activos según el panel de control (Responsable Suma + asignadas), aunque pueda ver todas --}}
                            @php
                                $misEmpresas = \App\Models\Entidad::withoutGlobalScopes()
                                    ->whereIn('id', \App\Support\Accesos::entidadesPropias(Auth::user()) ?: [0])
                                    ->where('estado', 1)->where('cliente', 1)
                                    ->orderBy('entidad')->get(['id', 'entidad']);
                            @endphp
                            <div class="border-t border-gray-100"></div>
                            <div class="block px-4 py-2 text-xs text-gray-400">Mis empresas ({{ $misEmpresas->count() }})</div>
                            <div class="overflow-y-auto max-h-64">
                                @forelse ($misEmpresas as $emp)
                                    @can('entidades.ver')
                                        <a href="{{ route('entidad.edit', $emp->id) }}" class="block px-4 py-1 text-xs text-gray-700 truncate hover:bg-gray-100" title="{{ $emp->entidad }}">{{ $emp->entidad }}</a>
                                    @else
                                        <div class="px-4 py-1 text-xs text-gray-700 truncate" title="{{ $emp->entidad }}">{{ $emp->entidad }}</div>
                                    @endcan
                                @empty
                                    <div class="px-4 py-1 text-xs italic text-gray-400">Ninguna asignada</div>
                                @endforelse
                            </div>

                            <div class="border-t border-gray-100"></div>

                            <!-- Authentication -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-jet-dropdown-link href="{{ route('logout') }}"
                                         onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-jet-dropdown-link>
                            </form>
                        </x-slot>
                    </x-jet-dropdown>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="flex items-center -mr-2 sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 text-gray-400 transition rounded-md hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    @cannot('entidades.editar')
        <div class="px-4 py-1 text-xs text-center text-amber-800 bg-amber-50 border-t border-amber-200">
            👁 Modo consulta: puedes ver la información de tus entidades, pero no modificarla.
        </div>
    @endcannot

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @can('entidades.ver')
                <x-jet-responsive-nav-link href="{{ route('entidades') }}" :active="request()->routeIs('entidades')">
                    {{ __('Entidades') }}
                </x-jet-responsive-nav-link>
            @endcan
            <x-jet-responsive-nav-link href="{{ route('todo') }}" :active="request()->routeIs('todo')">
                TO-DO
            </x-jet-responsive-nav-link>
            @if ($rutaImpuestos)
                <x-jet-responsive-nav-link href="{{ route($rutaImpuestos) }}" :active="request()->routeIs('impuestos', 'impuestos.*', 'contabilidad.is')">
                    Impuestos
                </x-jet-responsive-nav-link>
            @endif
            <x-jet-responsive-nav-link href="{{ route('todo', ['nueva' => 'mejora']) }}">
                💡 Pedir mejora
            </x-jet-responsive-nav-link>
            @if ($rutaContabilidad)
                <x-jet-responsive-nav-link href="{{ route($rutaContabilidad) }}" :active="request()->routeIs('contabilidad.*') && ! request()->routeIs('contabilidad.is')">
                    {{ __('Contabilidad') }}
                </x-jet-responsive-nav-link>
            @endif
            @role('Admin')
                <x-jet-responsive-nav-link href="{{ route('admin.usuarios') }}" :active="request()->routeIs('admin.*')">
                    {{ __('Panel de control') }}
                </x-jet-responsive-nav-link>
            @endrole
            {{-- Menú Facturación oculto a petición
            <x-jet-responsive-nav-link href="{{ route('facturacion.index') }}" :active="request()->routeIs('facturacion.index')">
                {{ __('Facturación') }}
            </x-jet-responsive-nav-link>
            --}}
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="flex items-center px-4">
                <div>
                    <div class="text-base font-medium text-gray-800">{{ Auth::user()->name }}</div>
                    <div class="text-sm font-medium text-gray-500">{{ Auth::user()->email }} · {{ Auth::user()->getRoleNames()->implode(', ') }}</div>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <!-- Account Management -->
                <x-jet-responsive-nav-link href="{{ route('profile.show') }}" :active="request()->routeIs('profile.show')">
                    {{ __('Profile') }}
                </x-jet-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-jet-responsive-nav-link href="{{ route('logout') }}"
                                   onclick="event.preventDefault();
                                    this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-jet-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
