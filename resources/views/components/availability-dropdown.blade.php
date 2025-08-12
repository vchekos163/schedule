<div x-data="{ open: false }" class="relative inline-block text-left">
    <button
        @click="open = !open"
        class="text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded"
    >
        Availability ▾
    </button>

    <div
        x-show="open"
        @click.away="open = false"
        x-transition
        class="absolute right-0 mt-2 w-64 bg-white border border-gray-200 rounded shadow-lg z-50"
    >
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 border-b">
            Working Days & Time Slots
        </div>

        @if(!empty($availability) && is_array($availability))
            <ul class="divide-y divide-gray-100 text-sm max-h-64 overflow-y-auto">
                @foreach($availability as $day => $slots)
                    @php
                        $display = collect($slots ?? [])
                            ->filter(fn($state) => strtoupper($state) !== 'UNAVAILABLE')
                            ->map(fn($state, $hour) => sprintf('%02d:00', (int)$hour) . ' ' . $state)
                            ->implode(', ');
                    @endphp
                    @if($display !== '')
                        <li class="flex justify-between px-4 py-2">
                            <span class="capitalize">{{ $day }}</span>
                            <span class="text-gray-500">{{ $display }}</span>
                        </li>
                    @endif
                @endforeach
            </ul>
        @else
            <div class="px-4 py-2 text-sm text-gray-500">No availability</div>
        @endif
    </div>
</div>
