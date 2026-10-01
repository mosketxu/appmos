<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <p class="mb-4 text-sm text-gray-600">
            Hola, {{ auth()->user()->name }}. Antes de seguir tienes que poner una contraseña nueva (mínimo 8 caracteres).
        </p>

        <x-jet-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.cambiar') }}">
            @csrf
            <div>
                <x-jet-label for="password" value="Contraseña nueva" />
                <x-jet-input id="password" class="block w-full mt-1" type="password" name="password" required autofocus autocomplete="new-password" />
            </div>
            <div class="mt-4">
                <x-jet-label for="password_confirmation" value="Repite la contraseña" />
                <x-jet-input id="password_confirmation" class="block w-full mt-1" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>
            <div class="flex items-center justify-between mt-4">
                <button type="submit" form="salir" class="text-sm text-gray-600 underline hover:text-gray-900">Salir</button>
                <x-jet-button>Guardar</x-jet-button>
            </div>
        </form>
        <form id="salir" method="POST" action="{{ route('logout') }}">@csrf</form>
    </x-jet-authentication-card>
</x-guest-layout>
