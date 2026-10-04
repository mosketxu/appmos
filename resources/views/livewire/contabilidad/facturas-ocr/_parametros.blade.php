{{-- IVA, periodo, cierre, analítica, botones de lectura y salida (parte izquierda de la tarjeta de arriba en la web) --}}
<div class="focr-grid">
                    <div>
                        <label class="focr-lbl">IVA del cliente
                            <x-neteges-info>
                                @if ($entidad && (int) $entidad->cicloimpuesto_id === 0)
                                    La entidad no lo tiene: se grabará en Entidades (Ciclo Impuesto) al elegirlo.
                                @else
                                    Sale de Entidades (Ciclo Impuesto).
                                @endif
                            </x-neteges-info>
                        </label>
                        <select wire:model.live="ciclo" class="focr-in {{ $ciclo === '' ? 'falta' : '' }}">
                            <option value="">— elegir —</option>
                            <option value="M">Mensual</option>
                            <option value="T">Trimestral</option>
                        </select>
                        @error('ciclo') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                    </div>
                    <div>
                        <label class="focr-lbl">Periodo fiscal en el que entran</label>
                        <select wire:model.live="periodo" class="focr-in" @disabled($ciclo === '')>
                            @foreach ($periodos as $k => $v)
                                <option value="{{ $k }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($ciclo === 'T')
                        <div>
                            <label class="focr-lbl">Cierre mensual</label>
                            <select wire:model.live="cierre" class="focr-in">
                                <option value="">Sin cierre mensual</option>
                                @foreach ($mesesCierre as $k => $v)
                                    <option value="{{ $k }}">Registrar desde {{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="focr-lbl">Analítica
                            <x-neteges-info>Contabilidad analítica: si la marcas, cada factura lleva el código de canal que el proveedor tenga por defecto en su ficha. Se guarda en la entidad.</x-neteges-info>
                        </label>
                        <label class="flex items-center gap-2 mt-1 text-sm">
                            <input type="checkbox" wire:model.live="analitica" class="rounded">
                            Canal del proveedor por defecto
                        </label>
                        @unless ($hayAnalitica)
                            <div class="mt-1 text-xs" style="color:#b45309">Falta la migración en la base de datos: de momento no se guarda en la entidad.</div>
                        @endunless
                    </div>
</div>

                <div class="flex flex-wrap items-center gap-3 mt-4">
                    @if (! $web)
                    <button type="button" wire:click="analizar" wire:loading.attr="disabled" class="focr-btn b-indigo" @disabled($ciclo === '')>
                        <span wire:loading.remove wire:target="analizar">📄 Leer las facturas de la carpeta</span>
                        <span wire:loading wire:target="analizar">Leyendo… (las que son imagen pasan por OCR)</span>
                    </button>
                    @elseif ($sinLeer > 0 && ! $leyendo)
                        {{-- Web: al subirlas se leen solas; este botón solo sale si quedan facturas sin leer (p. ej. se subieron antes de elegir el IVA) --}}
                        <button type="button" wire:click="analizar" wire:loading.attr="disabled" class="focr-btn b-indigo" @disabled($ciclo === '')>
                            📄 Leer ahora las {{ $sinLeer }} facturas sin leer
                        </button>
                        @if ($ciclo === '') <span class="text-xs text-red-600">Elige antes el IVA del cliente.</span> @endif
                    @endif
                    @if ($primeraAbierta)
                        <span class="text-sm">Fecha de registro = fecha de la factura; si es anterior, el <b>{{ $primeraAbierta->format('d/m/Y') }}</b></span>
                    @endif
                </div>
                @if (trim($salida) !== '')
                    <pre class="p-2 mt-3 text-xs text-gray-700 whitespace-pre-wrap border border-gray-200 rounded bg-gray-50">{{ trim($salida) }}</pre>
                @endif
