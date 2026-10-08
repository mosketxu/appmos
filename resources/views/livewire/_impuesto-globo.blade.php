@php $n = $n ?? 0; @endphp
<svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M4 3h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-8l-5 4.5V17H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/>@if ($n > 0 && $n < 10)<text x="12" y="13.2" text-anchor="middle" font-size="9" font-weight="700" font-family="Arial,sans-serif" fill="#fff">{{ $n }}</text>@endif</svg>
