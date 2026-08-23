@props(['id' => null, 'maxWidth' => null])

<x-modal :id="$id" :maxWidth="$maxWidth" {{ $attributes }}>
    <div class="px-4 py-3 sm:px-6 sm:py-4">
        <div class="text-base sm:text-lg">
            {{ $title }}
        </div>

        <div class="mt-2 sm:mt-4">
            {{ $content }}
        </div>
    </div>

    <div class="px-4 py-2 text-right bg-gray-100 sm:px-6 sm:py-4">
        {{ $footer }}
    </div>
</x-modal>
