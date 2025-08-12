@php
    $days = ['monday','tuesday','wednesday','thursday','friday'];
    $hours = [9,10,11,12,13,14,15,16];
@endphp
<div x-data="availabilityGrid(@entangle('availability').defer)">
    <table class="table-fixed border-collapse">
        <thead>
            <tr>
                <th class="w-20"></th>
                @foreach($days as $day)
                    <th class="px-2 py-1 text-xs font-medium cursor-pointer" @click="toggleColumn('{{ $day }}')">{{ ucfirst($day) }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($hours as $hour)
                <tr>
                    <th class="px-2 py-1 text-xs cursor-pointer" @click="toggleRow({{ $hour }})">{{ sprintf('%02d:00', $hour) }}</th>
                    @foreach($days as $day)
                        <td class="border w-16 h-8 text-[10px] text-center cursor-pointer" :class="stateClass(matrix['{{ $day }}'][{{ $hour }}])" @click="cycleState('{{ $day }}', {{ $hour }})" x-text="matrix['{{ $day }}'][{{ $hour }}]"></td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@once
    <script>
        function availabilityGrid(model) {
            return {
                matrix: model || {},
                states: ['CLASS','ONLINE','HYBRID','UNAVAILABLE'],
                init() {
                    const days = ['monday','tuesday','wednesday','thursday','friday'];
                    const hours = [9,10,11,12,13,14,15,16];
                    days.forEach(day => {
                        if(!this.matrix[day]) this.matrix[day] = {};
                        hours.forEach(hour => {
                            if(!this.matrix[day][hour]) this.matrix[day][hour] = 'UNAVAILABLE';
                        });
                    });
                },
                cycleState(day, hour) {
                    const current = this.matrix[day][hour];
                    const index = this.states.indexOf(current);
                    const next = this.states[(index + 1) % this.states.length];
                    this.matrix[day][hour] = next;
                },
                toggleRow(hour) {
                    const days = Object.keys(this.matrix);
                    days.forEach(day => this.cycleState(day, hour));
                },
                toggleColumn(day) {
                    const hours = Object.keys(this.matrix[day] || {});
                    hours.forEach(hour => this.cycleState(day, hour));
                },
                stateClass(state) {
                    return {
                        'CLASS': 'bg-green-300',
                        'ONLINE': 'bg-blue-300',
                        'HYBRID': 'bg-yellow-300',
                        'UNAVAILABLE': 'bg-gray-200'
                    }[state] || '';
                }
            }
        }
    </script>
@endonce
