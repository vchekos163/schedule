@props([
    'model',
    'actions' => [], // Array of ['label' => '', 'type' => 'button|link', 'wire' => '', 'href' => '', 'confirm' => false]
])

<div class="relative inline-block text-left" x-data="{ open: false }">
    <button @click="open = !open" class="text-sm font-medium text-gray-700 hover:text-blue-600">
        Actions ▾
    </button>

    <div x-show="open" @click.away="open = false"
         class="z-50 absolute right-0 mt-2 w-48 origin-top-right rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 z-10">
        <div class="py-1">
            @foreach ($actions as $action)
                @if ($action['type'] === 'link')
                    <a href="{{ $action['href'] }}"
                       class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                        {{ $action['label'] }}
                    </a>
                @elseif ($action['type'] === 'button')
                    <button
                        wire:click="{{ $action['wire'] }}"
                        @if($action['confirm'] ?? false)
                            onclick="return confirm('{{ $action['confirm'] === true ? 'Are you sure?' : $action['confirm'] }}')"
                        @endif
                        class="block w-full text-left px-4 py-2 text-sm {{ $action['danger'] ?? false ? 'text-red-600 hover:bg-red-100' : 'text-gray-700 hover:bg-gray-100' }}">
                        {{ $action['label'] }}
                    </button>
                @endif
            @endforeach
        </div>
    </div>
</div>
