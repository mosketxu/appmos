{{-- Texto con botón 📋 que lo copia al portapapeles (para pegarlo en la Sede de la AEAT, etc.). Uso: <x-copiar :texto="$nif" /> --}}
@props(['texto' => '', 'titulo' => 'Copiar'])
<span x-data="{ ok: false }" style="display:inline-flex;align-items:center;gap:4px;white-space:nowrap">
    <span style="user-select:all">{{ $texto }}</span>
    <button type="button" title="{{ $titulo }}" style="border:0;background:none;cursor:pointer;padding:0 2px;font-size:12px;color:#6b7280"
        x-on:click="navigator.clipboard && navigator.clipboard.writeText(@js((string) $texto)); ok = true; setTimeout(() => ok = false, 1500)">
        <span x-show="!ok">📋</span><span x-show="ok" x-cloak style="color:#15803d">✔</span></button>
</span>
