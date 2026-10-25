{{-- 17-oct-2026: empleados de la nómina del mes sin nombre de Anaplan (los deja Laboral; se resuelven en «✎ Nombres»). --}}
@php $sf = $this->staffSinNombreMes; $sd = $this->staffDatosMes; @endphp
@if ($sf)
    <div style="max-width:28rem;padding:.4rem .6rem;border:1px solid #fca5a5;border-left:5px solid #dc2626;border-radius:.375rem;background:#fef2f2;font-size:.75rem;line-height:1.25;color:#7f1d1d">
        <div style="font-weight:700">🚨 {{ count($sf) }} empleado(s) del mes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }} sin nombre de Anaplan <span style="font-weight:400;color:#991b1b">(Laboral del {{ $sd['cuando'] ?? '' }})</span></div>
        @foreach ($sf as $f)
            <div>{{ $f['tienda'] }}: {{ $f['excel'] }} → créalo como <b>«{{ $f['propuesta'] }}»</b>@if (! empty($f['quizas'])) <span style="color:#991b1b">(¿o es «{{ $f['quizas'] }}»?)</span>@endif</div>
        @endforeach
        <div style="margin-top:.2rem;color:#991b1b">👉 Créalos en Anaplan («Input Staff Data for AC») y escribe su nombre en ANAPLAN · personal → «✎ Nombres».</div>
    </div>
@elseif (! empty($sd['tiendas']))
    <span class="text-xs text-green-700 whitespace-nowrap" title="{{ collect($sd['tiendas'])->flatten(1)->count() }} empleados del mes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }} con nombre de Anaplan (Laboral del {{ $sd['cuando'] ?? '' }})">✓ {{ collect($sd['tiendas'])->flatten(1)->count() }} empleados con nombre de Anaplan <span class="text-indigo-500 cursor-help">ⓘ</span></span>
@endif
