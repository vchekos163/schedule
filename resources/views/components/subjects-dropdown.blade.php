<div x-data="{ open: false }" class="relative">
    <!-- Trigger Button -->
    <button
        @click="open = !open"
        class="text-sm text-gray-700 bg-gray-100 hover:bg-gray-200 px-3 py-1 rounded"
    >
        Subjects ▾
    </button>

    <!-- Dropdown -->
    <div
        x-show="open"
        @click.away="open = false"
        x-transition
        class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded shadow-lg z-50"
    >
        <div class="px-4 py-2 text-xs font-semibold text-gray-500 border-b">Subjects</div>

        @if($subjects->isNotEmpty())
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach($subjects as $subject)
                    <li class="flex justify-between px-4 py-2">
                        <span>{{ $subject->name }} ({{ $subject->priority }})</span>
                        <span class="text-gray-500">×{{ $subject->pivot->quantity }}</span>
                    </li>
                @endforeach
            </ul>
        @else
            <div class="px-4 py-2 text-sm text-gray-500">No subjects</div>
        @endif
    </div>
</div>
