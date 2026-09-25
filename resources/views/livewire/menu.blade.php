@php
    // Primera pantalla de Contabilidad a la que tiene acceso (ver config/accesos.php)
    $rutaContabilidad = collect([
        'contabilidad.procesos' => 'contabilidad.procesos',
        'contabilidad.facturacionpdf' => 'contabilidad.facturacion-pdf',
        'contabilidad.durcal' => 'contabilidad.durcal',
        'contabilidad.bancos' => 'contabilidad.bancos',
        'contabilidad.is' => 'contabilidad.is',
    ])->first(fn ($ruta, $permiso) => auth()->user()->can($permiso));
@endphp
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-full px-4 mx-auto">
        <div class="flex justify-between h-14">
            <div class="flex">
                <!-- Logo -->
                <div class="flex items-center flex-shrink-0">
                    <a href="{{ route('entidades') }}">
                        <x-jet-application-mark class="block w-auto h-9" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                    @can('entidades.ver')
                        <x-jet-nav-link href="{{ route('entidades') }}" :active="request()->routeIs('entidades')">
                            {{ __('Entidades') }}
                        </x-jet-nav-link>
                    @endcan
                    @if ($rutaContabilidad)
                        <x-jet-nav-link href="{{ route($rutaContabilidad) }}" :active="request()->routeIs('contabilidad.*')">
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

            <div class="hidden sm:flex sm:items-center sm:ml-6">
                @if($entmenu->id)
                    <div class="hidden p-2 space-x-8 bg-gray-100 rounded-lg sm:-my-px sm:ml-10 sm:flex">
                        <div class="">
                            <x-select wire:model.lazy="filtroentidad"  selectname="entidad_id" class="w-full">
                                <option value="">-- Elige una entidad --</option>
                                @foreach ($entidades as $entidad)
                                <option value="{{ $entidad->id }}">{{ $entidad->entidad }}</option>
                                @endforeach
                            </x-select>
                        </div>
                        <x-jet-nav-link href="{{ route('entidad.pu',$entmenu) }}" :active="request()->routeIs('entidad.pu')">
                            {{ __('Pus') }}
                        </x-jet-nav-link>
                        <x-jet-nav-link href="{{ route('entidad.contacto',$entmenu) }}" :active="request()->routeIs('entidad.contacto')">
                            {{ __('Contactos') }}
                        </x-jet-nav-link>
                        <x-jet-nav-link href="{{ route('entidad.edit',$entmenu) }}" :active="request()->routeIs('entidad.edit')">
                            @can('entidades.editar') {{ __('Editar') }} @else {{ __('Ficha') }} @endcan
                        </x-jet-nav-link>
                        @can('facturacion.ver')
                        <div class="relative mt-1 ">
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
                                        <x-jet-dropdown-link href="{{ route('facturacion.show',$entmenu)}}" class="text-center">
                                            {{ __('Facturas') }}
                                        </x-jet-dropdown-link>
                                        <x-jet-dropdown-link href="{{ route('facturacion.prefacturasentidad',$entmenu)}}"  class="text-center">
                                            {{ __('Prefacturas') }}
                                        </x-jet-dropdown-link>
                                    </div>
                                </x-slot>
                            </x-jet-dropdown>
                        </div>
                        <x-jet-nav-link href="{{ route('facturacionconcepto.entidad',$entmenu)}}" :active="request()->routeIs('facturacionconcepto.entidad')">
                            {{ __('Fac.Conceptos') }}
                        </x-jet-nav-link>
                        @endcan
                    </div>
                @endif
                <!-- Settings Dropdown -->
                <div class="relative ml-3">
                    <x-jet-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <span class="inline-flex rounded-md">
                                <button type="button" class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition bg-white border border-transparent rounded-md hover:text-gray-700 focus:outline-none">
                                    {{ Auth::user()->name }}
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
            @if ($rutaContabilidad)
                <x-jet-responsive-nav-link href="{{ route($rutaContabilidad) }}" :active="request()->routeIs('contabilidad.*')">
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
