<x-app-layout>
    <div class="p-4">
        <div class="max-w-4xl mx-auto">
            <h1 class="mb-4 text-2xl font-semibold text-gray-900">Menú</h1>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($apartados as $a)
                    <a href="{{ $a['href'] }}" class="block p-5 transition bg-white border rounded-lg shadow hover:shadow-md hover:border-indigo-300">
                        <div class="text-lg font-semibold text-gray-900">{{ $a['label'] }}</div>
                        @if (!empty($a['descripcion']))
                            <div class="mt-1 text-sm text-gray-500">{{ $a['descripcion'] }}</div>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
