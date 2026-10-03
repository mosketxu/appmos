@props(['id' => 'password', 'name' => 'password', 'autocomplete' => 'current-password', 'autofocus' => false])
<div class="relative mt-1">
    <input id="{{ $id }}" name="{{ $name }}" type="password" required autocomplete="{{ $autocomplete }}" @if($autofocus) autofocus @endif
        {{ $attributes->merge(['class' => 'block w-full pr-10 border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 rounded-md shadow-sm']) }}>
    <button type="button" tabindex="-1" title="Mostrar/ocultar contraseña" aria-label="Mostrar u ocultar contraseña"
        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700"
        onclick="var i=document.getElementById('{{ $id }}');var v=i.type==='password';i.type=v?'text':'password';this.querySelector('.ojo').style.display=v?'none':'';this.querySelector('.ojo-tachado').style.display=v?'':'none';">
        <svg class="ojo w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 8.2 7.36 5 12 5s8.58 3.2 9.96 6.68a1 1 0 0 1 0 .64C20.58 15.8 16.64 19 12 19s-8.58-3.2-9.96-6.68Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
        <svg class="ojo-tachado w-5 h-5" style="display:none" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.5 10.5 0 0 0 2.04 12c1.38 3.5 5.32 6.7 9.96 6.7 1.9 0 3.7-.54 5.25-1.46M6.23 6.23A10.45 10.45 0 0 1 12 5.3c4.64 0 8.58 3.2 9.96 6.7a10.5 10.5 0 0 1-4.3 5.1M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.78 7.78L21 21m-3.34-3.34-3.54-3.54m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
    </button>
</div>
