<div x-data="{ open: false }" class="relative">
    <!-- Trigger Button -->
    <button
        @click="open = !open"
        class="text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded"
    >
        {{ $label ?? 'Items' }} ▾
    </button>

    <!-- Dropdown -->
    <div
        x-show="open"
        @click.away="open = false"
        x-transition
        class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded shadow-lg z-50"
    >
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 border-b">{{ $label ?? 'Items' }}</div>

        @if($items->isNotEmpty())
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach($items as $item)
                    <li class="flex justify-between px-4 py-2">
                        <span>{{ $item->name }}</span>
                        @if(!empty($item->value))
                            <span class="text-gray-500">: {{ $item->value }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-4 py-2 text-sm text-gray-500">No {{ strtolower($label ?? 'items') }}</div>
        @endif
    </div>
</div>
