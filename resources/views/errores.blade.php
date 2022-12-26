@if ($errors->any())
<div id="alert" class="relative px-6 py-2 mb-2 text-white bg-red-200 border-red-500 rounded border-1">
    <ul class="mt-3 text-sm text-red-600 list-disc list-inside">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button class="absolute top-0 right-0 mt-2 mr-6 text-2xl font-semibold leading-none bg-transparent outline-none focus:outline-none" onclick="document.getElementById('alert').remove();">
        <span>×</span>
    </button>
</div>
@endif
@if (session()->has('message'))
<div id="alert" class="relative px-6 py-2 mb-2 text-white bg-green-200 border-green-500 rounded border-1" >
    <span class="inline-block mx-8 align-middle" >
        {{ session('message') }}
    </span>
    <button class="absolute top-0 right-0 mt-2 mr-6 text-2xl font-semibold leading-none bg-transparent outline-none focus:outline-none" onclick="document.getElementById('alert').remove();">
        <span>×</span>
    </button>
</div>
@endif

