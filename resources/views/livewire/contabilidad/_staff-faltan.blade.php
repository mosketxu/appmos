{{-- 17-oct-2026: empleados de la nómina del mes sin pareja en Anaplan (los avisa Laboral; «🔎 Preparar propuesta» lo recalcula). --}}
@php $sf = $this->staffFaltanMes; @endphp
@if (! empty($sf['faltan']))
    <div style="max-width:28rem;padding:.4rem .6rem;border:1px solid #fca5a5;border-left:5px solid #dc2626;border-radius:.375rem;background:#fef2f2;font-size:.75rem;line-height:1.25;color:#7f1d1d">
        <div style="font-weight:700">🚨 {{ count($sf['faltan']) }} empleado(s) del mes {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }} sin pareja en Anaplan <span style="font-weight:400;color:#991b1b">({{ $sf['cuando'] ?? '' }})</span></div>
        @foreach ($sf['faltan'] as $f)
            <div>{{ $f['tienda'] }}: {{ $f['nomina'] }} → crear como <b>«{{ $f['propuesta'] }}»</b>@if (! empty($f['quizas'])) <span style="color:#991b1b">(¿o es «{{ $f['quizas'] }}»?)</span>@endif @if (! empty($f['nota'])) <span style="color:#991b1b">· {{ $f['nota'] }}</span>@endif</div>
        @endforeach
        <div style="margin-top:.2rem;color:#991b1b">👉 Créalos en Anaplan («Input Staff Data for AC») y pulsa «🔎 Preparar propuesta» en ANAPLAN · personal: este aviso se actualiza solo.</div>
    </div>
@endif
