@php
    // $availability is passed in from Table::columns() via view()
    // If this file is called with $row, adjust to extract it before this block.

    use Illuminate\Support\Str;
    $availability = $availability ?? [];

    // Days/periods you want to render
    $days    = ['monday','tuesday','wednesday','thursday','friday'];
    $periods = config('periods');

    // Fill missing keys but keep existing states
    foreach ($days as $day) {
        if (!isset($availability[$day])) {
            $availability[$day] = [];
        }
        foreach ($periods as $num => $time) {
            if (!isset($availability[$day][$num])) {
                $availability[$day][$num] = 'UNAVAILABLE';
            }
        }
    }
@endphp

<div x-data="{ open: false }" class="relative inline-block text-left">
    <button
        type="button"
        @click="open = !open"
        class="text-xs text-gray-700 bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded"
        title="Show availability"
    >
        Availability ▾
    </button>

    <div x-cloak x-show="open" @click.outside="open = false" x-transition
         class="absolute right-0 mt-2 bg-white border border-gray-200 rounded shadow-lg z-50 w-max max-w-[95vw]">
        <!-- Legend -->
        <div class="px-3 pt-3 pb-2 text-[11px] text-gray-700 flex items-center gap-3">
            <span class="inline-flex items-center gap-1"><span class="swatch class"></span> CLASS</span>
            <span class="inline-flex items-center gap-1"><span class="swatch online"></span> ONLINE</span>
            <span class="inline-flex items-center gap-1"><span class="swatch hybrid"></span> HYBRID</span>
            <span class="inline-flex items-center gap-1"><span class="swatch unavailable"></span> UNAVAILABLE</span>
        </div>

        <!-- Read-only grid -->
        <div class="p-3 max-h-80 overflow-auto">
            <table class="border-collapse">
                <thead>
                <tr>
                    <th class="w-auto"></th>
                    @foreach($days as $day)
                        <th class="px-2 py-1 text-[11px] font-medium text-gray-700 whitespace-nowrap">
                            {{ ucfirst($day) }}
                        </th>
                    @endforeach
                </tr>
                </thead>
                <tbody>
                @foreach($periods as $num => $time)
                    <tr>
                        <th class="px-2 py-1 text-[11px] text-gray-600 whitespace-nowrap">
                            {{ Str::ordinal($num) }} - {{ $time['start'] }} | {{ $time['end'] }}
                        </th>
                        @foreach($days as $day)
                            @php
                                $state = strtoupper(trim($availability[$day][$num] ?? 'UNAVAILABLE'));
                                $map = [
                                    'CLASS'       => 'state-class',
                                    'ONLINE'      => 'state-online',
                                    'HYBRID'      => 'state-hybrid',
                                    'UNAVAILABLE' => 'state-unavailable',
                                ];
                            @endphp
                            <td class="cell border w-12 h-6 {{ $map[$state] ?? '' }}" title="{{ $state }}"></td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@once
    <style>
        .state-class        { background-color: #90EE90; }
        .state-online       { background-color: #ADD8E6; }
        .state-hybrid       { background-color: #F0E68C; }
        .state-unavailable  { background-color: #DCDCDC; }
        td.cell { min-width: 2rem; min-height: 1.5rem; }
        .swatch { width: 10px; height: 10px; display: inline-block; border: 1px solid rgba(0,0,0,0.2); border-radius: 2px; }
        .swatch.class { background-color: #90EE90; }
        .swatch.online { background-color: #ADD8E6; }
        .swatch.hybrid { background-color: #F0E68C; }
        .swatch.unavailable { background-color: #DCDCDC; }
        [x-cloak] { display: none !important; }
    </style>
@endonce
