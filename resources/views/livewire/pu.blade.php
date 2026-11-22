<div class="">
<style>
    /* 26-oct-2026 (Alex, «cegatos»): en móvil los valores y los campos de edición, mucho más grandes; un campo por línea */
    .pu-valor { font-size:1.2rem; line-height:1.4; word-break:break-word }
    @media (max-width: 767px) {
        #destino, #url, #us, #us2, #ps, #observaciones { font-size:1.2rem; padding-top:.55rem; padding-bottom:.55rem }
    }
</style>
    @livewire('menu',['entidad'=>$ent,'ruta'=>$ruta,'iconos'=>false],key($ent->id))

    <div class="p-1 mx-2">

        <div class="flex flex-wrap items-center" style="column-gap:1.5rem; row-gap:.25rem">
            <h1 class="text-2xl font-semibold text-gray-900">Pus de {{ $ent->entidad }} <span class="text-lg text-gray-500 "> ({{ $ent->nif }})</span></h1>
            <x-buscador-entidad :entidad="$ent" :ruta="$ruta"/>
        </div>

        <div class="py-1 space-y-4">
            @if (session()->has('message'))
                <div id="alert" class="relative px-6 py-2 mb-2 text-white bg-red-200 border-red-500 rounded border-1">
                    <span class="inline-block mx-8 align-middle">
                        {{ session('message') }}
                    </span>
                    <button class="absolute top-0 right-0 mt-2 mr-6 text-2xl font-semibold leading-none bg-transparent outline-none focus:outline-none" onclick="document.getElementById('alert').remove();">
                        <span>×</span>
                    </button>
                </div>
            @endif
            <div class="flex justify-between">
                <div class="flex w-2/4 space-x-2">
                    <input type="text" wire:model.live.debounce.1000ms="search" class="py-1 border border-blue-100 rounded-lg" placeholder="Búsqueda..." autofocus/>
                </div>
                <x-button.primary wire:click="create"><x-icon.plus/> Nueva Pu</x-button.primary>

                {{-- <x-button.primary href="{{ route('pu.create') }}" class="py-0 my-0"><x-icon.plus/> Nueva</x-button.primary> --}}
            </div>

            {{-- tabla pus (movil: tarjeta de 3 lineas por registro) --}}
            <div class="space-y-2 md:hidden">
                @forelse ($pus as $pu)
                    <div class="p-3 space-y-2 border border-gray-200 rounded-lg shadow-sm pu-tarjeta" style="background:{{ $loop->odd ? '#eef2ff' : '#ffffff' }}" wire:loading.class.delay="opacity-50">
                        <div class="flex items-start justify-between">
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Destino') }}</span>
                                <span class="block font-medium text-gray-700 pu-valor">{{ $pu->destino }}</span>
                            </div>
                            <div class="flex items-center flex-shrink-0 pt-3 ml-2 space-x-3">
                                <x-icon.edit-a wire:click="edit({{ $pu->id }})" href="#"/>
                                <x-icon.delete-a wire:click.prevent="delete({{ $pu->id }})" wire:confirm="¿Estás seguro?"/>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-y-3">
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Url') }}</span>
                                <span class="block text-gray-700 pu-valor">{{ $pu->url ?: '—' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('us') }}</span>
                                <span class="block text-gray-700 pu-valor">{{ $pu->us ?: '—' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('us2') }}</span>
                                <span class="block text-gray-700 pu-valor">{{ $pu->us2 ?: '—' }}</span>
                            </div>
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('ps') }}</span>
                                <span class="block text-gray-700 pu-valor">{{ $pu->ps ?: '—' }}</span>
                            </div>
                        </div>
                        @if($pu->observaciones)
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Observaciones') }}</span>
                                <span class="block text-gray-700 pu-valor" style="white-space:pre-line">{{ $pu->observaciones }}</span>
                            </div>
                        @endif
                        @if($pu->adjuntos->isNotEmpty())
                            <div class="min-w-0">
                                <span class="block text-xs font-semibold tracking-wide text-gray-400 uppercase">{{ __('Adjuntos') }}</span>
                                @include('livewire._pu_adjuntos', ['adjuntos' => $pu->adjuntos, 'grande' => true])
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="flex items-center justify-center">
                        <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                        <span class="py-5 text-xl font-medium text-gray-500">
                            No se han encontrado registros...
                        </span>
                    </div>
                @endforelse
            </div>

            {{-- tabla pus (escritorio) --}}
            <div class="hidden md:block md:space-y-4">
                <x-table>
                    <x-slot name="head">
                        <x-table.heading class="pl-4 text-left">{{ __('Destino') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Url') }} </x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('us') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('us2') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('ps') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Observaciones') }}</x-table.heading>
                        <x-table.heading class="pl-4 text-left">{{ __('Adjuntos') }}</x-table.heading>
                        <x-table.heading colspan="2"/>
                    </x-slot>
                    <x-slot name="body">
                        @forelse ($pus as $pu)
                            <x-table.row wire:loading.class.delay="opacity-50">
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->destino }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->url }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->us }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->us2 }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->ps }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    <input type="text" value="{{ $pu->observaciones }}" class="w-full py-1 text-sm font-thin text-gray-500 truncate border-0 rounded-md"  readonly/>
                                </x-table.cell>
                                <x-table.cell>
                                    @include('livewire._pu_adjuntos', ['adjuntos' => $pu->adjuntos, 'grande' => false])
                                </x-table.cell>
                                <x-table.cell>
                                    <div class="flex items-center justify-center space-x-3">
                                        <x-icon.edit-a wire:click="edit({{ $pu->id }})" href="#"/>
                                        <x-icon.delete-a wire:click.prevent="delete({{ $pu->id }})" wire:confirm="¿Estás seguro?"/>
                                    </div>
                                </x-table.cell>


                            </x-table.row>
                        @empty
                            <x-table.row>
                                <x-table.cell colspan="8">
                                    <div class="flex items-center justify-center">
                                        <x-icon.inbox class="w-8 h-8 text-gray-300"/>
                                        <span class="py-5 text-xl font-medium text-gray-500">
                                            No se han encontrado registros...
                                        </span>
                                    </div>
                                </x-table.cell>
                            </x-table.row>
                        @endforelse
                    </x-slot>
                </x-table>
            </div>
            <div class="flex mt-0 ml-2 space-x-4">
                <div class="space-x-3">
                    <x-jet-secondary-button  onclick="location.href = '{{url()->previous()}}'">{{ __('Volver') }}</x-jet-secondary-button>
                </div>
            </div>
            <x-notification/>
        </div>
        <!-- Save Transaction Modal -->
        <form wire:submit.prevent="save">
        {{-- Sin permiso de escritura: todo el formulario en solo lectura --}}
        <fieldset class="contents" @cannot('entidades.editar') disabled @endcannot>
            @if (session()->has('message'))
                <div id="alert" class="relative px-6 py-2 mb-2 text-white bg-red-200 border-red-500 rounded border-1">
                    <span class="inline-block mx-8 align-middle">
                        {{ session('message') }}
                    </span>
                    <button class="absolute top-0 right-0 mt-2 mr-6 text-2xl font-semibold leading-none bg-transparent outline-none focus:outline-none" onclick="document.getElementById('alert').remove();">
                        <span>×</span>
                    </button>
                </div>
            @endif
            <x-jet-validation-errors/>
            <x-modal.dialog wire:model.defer="showEditModal">
                <x-slot name="title">Editar Pu</x-slot>

                <x-slot name="content">
                    <x-input.group for="destino" label="destino" :error="$errors->first('editing.destino')">
                        <x-input.text wire:model="editing.destino" id="destino"  />
                    </x-input.group>

                    <x-input.group for="url" label="url" :error="$errors->first('editing.url')">
                        <x-input.text wire:model="editing.url" id="url" />
                    </x-input.group>

                    <x-input.group for="us" label="us" :error="$errors->first('editing.us')">
                        <x-input.text wire:model="editing.us" id="us" />
                    </x-input.group>

                    <x-input.group for="us2" label="us2" :error="$errors->first('editing.us2')">
                        <x-input.text wire:model="editing.us2" id="us2" />
                    </x-input.group>

                    <x-input.group for="ps" label="ps" :error="$errors->first('editing.ps')">
                        <x-input.text wire:model="editing.ps" id="ps" />
                    </x-input.group>

                    <x-input.group for="observaciones" label="observaciones" :error="$errors->first('editing.observaciones')">
                        <textarea wire:model="editing.observaciones" id="observaciones" rows="3" class="block w-full border border-gray-300 rounded-md form-textarea sm:text-sm sm:leading-5" style="resize:vertical; min-height:4.5rem"></textarea>
                    </x-input.group>

                    {{-- Imágenes y ficheros de la PU (22-nov-2026): elegir, arrastrar o pegar una captura con Ctrl+V; se guardan con Save --}}
                    <x-input.group for="pu-ficheros" label="Imágenes y ficheros" :error="$errors->first('nuevas.*') ?: $errors->first('subidas.*')">
                        @if($adjuntosEdicion->isNotEmpty())
                            <ul class="mb-2 space-y-1">
                                @foreach($adjuntosEdicion as $a)
                                    <li class="flex items-center space-x-2 text-sm" wire:key="pa-{{ $a->id }}">
                                        <a href="{{ route('entidad.pu.adjunto', $a->id) }}" target="_blank" class="flex items-center min-w-0 space-x-2 text-indigo-700 hover:underline">
                                            @if($a->esImagen())
                                                <img src="{{ route('entidad.pu.adjunto', $a->id) }}" alt="" class="object-cover w-10 h-10 border rounded">
                                            @else
                                                <span>📎</span>
                                            @endif
                                            <span class="truncate">{{ $a->nombre }}</span>
                                        </a>
                                        @can('entidades.editar')
                                            <button type="button" wire:click="quitarAdjunto({{ $a->id }})" wire:confirm="¿Borrar «{{ $a->nombre }}»?" class="text-red-600 hover:text-red-800" title="Borrar">✕</button>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if(count($subidas))
                            <ul class="mb-2 space-y-1">
                                @foreach($subidas as $i => $f)
                                    <li class="flex items-center space-x-2 text-sm" wire:key="ps-{{ $i }}-{{ $f->getFilename() }}">
                                        @if($f->isPreviewable())
                                            <img src="{{ $f->temporaryUrl() }}" alt="" class="object-cover w-10 h-10 border rounded">
                                        @else
                                            <span>📎</span>
                                        @endif
                                        <span class="truncate">{{ $f->getClientOriginalName() }}</span>
                                        <span class="text-xs text-amber-700">(se guarda al pulsar Save)</span>
                                        <button type="button" wire:click="quitarSubida({{ $i }})" class="text-red-600 hover:text-red-800" title="Quitar">✕</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @can('entidades.editar')
                            <div x-data="{ sobre: false,
                                    subir(lista) {
                                        const fs = Array.from(lista || []).map((f, n) => {
                                            if (f.name && f.name !== 'image.png') return f;
                                            const d = new Date(), p = v => String(v).padStart(2, '0');
                                            const ext = (f.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
                                            return new File([f], `captura-${d.getFullYear()}${p(d.getMonth()+1)}${p(d.getDate())}-${p(d.getHours())}${p(d.getMinutes())}${p(d.getSeconds())}${n ? '-' + n : ''}.${ext}`, { type: f.type });
                                        });
                                        if (fs.length) $wire.uploadMultiple('nuevas', fs);
                                    } }"
                                 tabindex="0" x-on:click="$el.focus()"
                                 x-on:paste="if ($event.clipboardData && $event.clipboardData.files.length) { $event.preventDefault(); subir($event.clipboardData.files) }"
                                 x-on:dragover.prevent="sobre = true" x-on:dragleave.prevent="sobre = false"
                                 x-on:drop.prevent="sobre = false; subir($event.dataTransfer.files)"
                                 :class="sobre ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300'"
                                 class="px-3 py-3 text-sm text-center text-gray-600 border-2 border-dashed rounded-md cursor-pointer focus:outline-none focus:border-indigo-500 focus:bg-indigo-50">
                                <input type="file" multiple id="pu-ficheros" x-ref="fi" class="hidden" x-on:change="subir($event.target.files); $event.target.value = ''">
                                <button type="button" x-on:click.stop="$refs.fi.click()" class="px-2 py-1 text-indigo-700 border border-indigo-300 rounded hover:bg-indigo-50">📎 Añadir imágenes o ficheros</button>
                                <span class="block mt-1 text-xs text-gray-500">o arrástralos aquí, o haz clic en este recuadro y pega una captura con Ctrl+V (máx. 20 MB cada uno)</span>
                                <span wire:loading wire:target="nuevas" class="block mt-1 text-xs text-indigo-700">⏳ Subiendo…</span>
                            </div>
                        @endcan
                    </x-input.group>

                </x-slot>

                <x-slot name="footer">
                    <x-button.secondary wire:click="$set('showEditModal', false)">Cancel</x-button.secondary>

                    <x-button.primary type="submit">Save</x-button.primary>
                </x-slot>
            </x-modal.dialog>
                </fieldset>
        </form>

    </div>

</div>


