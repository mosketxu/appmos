{{-- Petición de documentación al cliente (icono de documento, 15-oct-2026: gris no solicitado, azul solicitado, verde ✔ recibido; «1/3» = solo parte de los meses): el correo de Proc.Mensuales → Pet. Documentación Impuestos y su estado,
     por mes (ciclo mensual) o por trimestre. Mismo dato que allí (procesos_estado / mails_enviados). Variables: $entId, $bl, $pet. --}}
@php
    $peticiones = $this->peticionesBloque($entId, $bl['k'], $pet);
    $nomPet = [null => ['No solicitado', '#9ca3af'], 'solicitado' => ['Solicitado', '#2563eb'], 'recibido' => ['Recibido', '#16a34a']];
@endphp
@if ($peticiones)
    @php
        $est = collect($peticiones)->pluck(2);
        $peor = $est->contains(null) ? null : ($est->contains('solicitado') ? 'solicitado' : 'recibido');
        $tit = 'Petición de documentación: '.collect($peticiones)->map(fn ($p) => $p[1].' '.mb_strtolower($nomPet[$p[2]][0]).($p[4] ? ' (enviado el '.\Illuminate\Support\Carbon::parse($p[4]->enviado_at)->format('d/m').')' : ''))->implode(' · ').' (clic: ver / cambiar)';
    @endphp
    <span class="imp-pdfw" wire:key="pet-{{ $entId }}-{{ $bl['k'] }}-{{ $est->implode(',') }}" x-data="{ o: false, ver: '' }" x-on:click.outside="o = false">
        @php $hechas = $est->filter()->count(); $color = $nomPet[$peor === null && $hechas ? 'solicitado' : $peor][1]; @endphp
        <button type="button" class="imp-pet" style="color:{{ $color }}; {{ $peor === null ? 'opacity:.75' : '' }}" x-on:click="o = !o" title="{{ $tit }}"><svg viewBox="0 0 24 24" aria-hidden="true" width="17" height="17" style="vertical-align:middle"><path fill="currentColor" d="M6 2h8l6 6v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm7 1.5V9h5.5L13 3.5zM7 12v1.6h10V12H7zm0 3.4V17h10v-1.6H7z"/></svg>@if ($peor === 'recibido')<b class="ok">✔</b>@elseif ($peor === null && $hechas)<sup style="font-size:9px">{{ $hechas }}/{{ $est->count() }}</sup>@endif</button>
        <div class="imp-pop imp-avp" x-show="o" x-cloak>
            <div style="font-weight:600; margin-bottom:4px">Petición de documentación · {{ $bl['titulo'] }}</div>
            @foreach ($peticiones as [$per, $etq, $e, $fila, $mail])
                <div style="border-top:1px solid #e5e7eb; padding:4px 0">
                    <div style="font-weight:600">{{ $etq }}
                        <span style="font-weight:400; color:{{ $nomPet[$e][1] }}">· {{ $nomPet[$e][0] }}{{ $e === 'recibido' && $fila?->recibido_at ? ' el '.\Illuminate\Support\Carbon::parse($fila->recibido_at)->format('d/m/Y') : '' }}</span>
                    </div>
                    @if ($mail)
                        <div style="color:#374151">✉ Enviado el {{ \Illuminate\Support\Carbon::parse($mail->enviado_at)->format('d/m/Y H:i') }}{{ $mail->error ? ' · ⚠ '.$mail->error : '' }}</div>
                        <div style="color:#6b7280">Para: {{ $mail->destinatarios }}@if ($mail->cc) · CC: {{ $mail->cc }}@endif</div>
                        <button type="button" style="color:#4f46e5; padding:0" x-on:click="ver = ver === '{{ $per }}' ? '' : '{{ $per }}'" x-text="ver === '{{ $per }}' ? '▲ Ocultar el correo' : '▼ Ver el correo'">▼ Ver el correo</button>
                        <div x-show="ver === '{{ $per }}'" x-cloak style="background:#f9fafb; border:1px solid #e5e7eb; border-radius:4px; padding:4px; margin-top:2px">
                            <div style="font-weight:600">{{ $mail->asunto }}</div>
                            <div style="white-space:pre-wrap; max-height:220px; overflow:auto">{{ $mail->texto }}</div>
                        </div>
                    @elseif ($e === 'solicitado')
                        <div style="color:#6b7280">Marcado a mano{{ $fila?->solicitado_at ? ' el '.\Illuminate\Support\Carbon::parse($fila->solicitado_at)->format('d/m/Y') : '' }} (sin correo enviado desde Appmos)</div>
                    @endif
                    <div style="display:flex; gap:2px; margin-top:3px">
                        @foreach ($nomPet as $k => [$nom, $col])
                            <button type="button" class="e {{ $e === ($k ?: null) ? 'on' : '' }}" style="color:{{ $col }}; width:auto; flex:1; text-align:center; white-space:nowrap; padding:3px 2px"
                                    x-on:click="o = false" wire:click="fijarPeticion({{ $entId }}, '{{ $per }}', {{ $k ? "'".$k."'" : 'null' }})">{{ $nom }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach
            <div style="border-top:1px solid #e5e7eb; padding-top:4px; color:#6b7280">
                @can('contabilidad.procesosmensuales')
                    <a href="{{ route('contabilidad.procesos-mensuales') }}" style="color:#4f46e5">📨 Enviar o preparar el correo; texto, asunto, CC y «Mail petición» (Proc.Mensuales)</a>
                @endcan
            </div>
        </div>
    </span>
@endif
