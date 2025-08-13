@php
    use Illuminate\Support\Str;
    // Local constants for rendering
    $days    = ['monday','tuesday','wednesday','thursday','friday'];
    $periods = config('periods');
@endphp

<div
    x-data="availabilityGrid($wire.entangle('{{ $getStatePath() }}').live)"
    x-init="seed()"
    class="space-y-2"
>
    <!-- Legend -->
    <div class="legend mt-2 text-xs flex items-center gap-3">
        <span class="legend-item inline-flex items-center gap-1"><span class="swatch class"></span> CLASS</span>
        <span class="legend-item inline-flex items-center gap-1"><span class="swatch online"></span> ONLINE</span>
        <span class="legend-item inline-flex items-center gap-1"><span class="swatch hybrid"></span> HYBRID</span>
        <span class="legend-item inline-flex items-center gap-1"><span class="swatch unavailable"></span> UNAVAILABLE</span>
    </div>

    <table class="border-collapse w-full select-none">
        <thead>
        <tr>
            <th class="w-auto"></th>
            @foreach($days as $day)
                <th class="px-2 py-1 text-xs font-medium cursor-pointer"
                    @click.stop.prevent="toggleColumn('{{ $day }}')">
                    {{ ucfirst($day) }}
                </th>
            @endforeach
        </tr>
        </thead>
        <tbody>
        @foreach($periods as $num => $time)
            <tr>
                <th class="px-2 py-1 text-xs cursor-pointer"
                    @click.stop.prevent="toggleRow({{ $num }})">
                    {{ $num }} - {{ $time['start'] }} | {{ $time['end'] }}
                </th>

                @foreach($days as $day)
                    <td
                        class="cell border w-16 h-8 text-center cursor-pointer"
                        :class="stateClass(state('{{ $day }}', {{ $num }}))"
                        :title="state('{{ $day }}', {{ $num }})"
                        @click.stop.prevent="cycle('{{ $day }}', {{ $num }})"
                    ></td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@once
    <style>
        .state-class        { background-color: #90EE90; } /* lightgreen */
        .state-online       { background-color: #ADD8E6; } /* lightblue  */
        .state-hybrid       { background-color: #F0E68C; } /* khaki      */
        .state-unavailable  { background-color: #DCDCDC; } /* gainsboro  */
        td.cell { min-width: 2.5rem; min-height: 2rem; }
        .swatch { width: 12px; height: 12px; display: inline-block; border: 1px solid rgba(0,0,0,0.2); border-radius: 2px; }
        .swatch.class { background-color: #90EE90; }
        .swatch.online { background-color: #ADD8E6; }
        .swatch.hybrid { background-color: #F0E68C; }
        .swatch.unavailable { background-color: #DCDCDC; }
    </style>

    <script>
        function availabilityGrid(model) {
            const DAYS    = ['monday','tuesday','wednesday','thursday','friday'];
            const PERIODS = [1,2,3,4,5,6,7];

            return {
                // Keep the entangled proxy (do NOT replace it with {})
                matrix: model,
                states: ['CLASS','ONLINE','HYBRID','UNAVAILABLE'],

                // Ensure matrix exists & prefill empty slots once the component mounts
                seed() {
                    if (!this.matrix || typeof this.matrix !== 'object') {
                        this.$wire.set('{{ $getStatePath() }}', {});
                        this.matrix = this.$wire.entangle('{{ $getStatePath() }}').live;
                    }
                    for (const d of DAYS) {
                        if (!this.matrix[d]) this.matrix[d] = {};
                        for (const p of PERIODS) {
                            if (!this.matrix[d][p]) this.matrix[d][p] = 'UNAVAILABLE';
                        }
                    }
                },

                state(day, period) {
                    const row = this.matrix?.[day];
                    return (row && row[period]) ? row[period] : 'UNAVAILABLE';
                },

                set(day, period, val) {
                    if (!this.matrix[day]) this.matrix[day] = {};
                    this.matrix[day][period] = val;
                    // no reassignment — keep Livewire proxy alive
                },

                cycle(day, period) {
                    const cur = this.state(day, period);
                    const i   = this.states.indexOf(cur);
                    const next = this.states[(i + 1) % this.states.length];
                    this.set(day, period, next);
                },

                toggleRow(period) {
                    for (const d of Object.keys(this.matrix)) this.cycle(d, period);
                },

                toggleColumn(day) {
                    for (const p of Object.keys(this.matrix[day] || {})) this.cycle(day, Number(p));
                },

                stateClass(s) {
                    return {
                        'CLASS': 'state-class',
                        'ONLINE': 'state-online',
                        'HYBRID': 'state-hybrid',
                        'UNAVAILABLE': 'state-unavailable',
                    }[s] || '';
                },
            };
        }
    </script>
@endonce
