@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 max-w-full">
    <h1 class="text-lg font-semibold mb-3 flex items-center gap-2">
        Room schedule{{ $room ? ': ' . ($room->name ?? 'Room') : '' }}
        <span id="spinner" class="hidden">
                <svg class="animate-spin h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
        </span>
    </h1>
    <div class="flex items-center justify-center mb-2 gap-2">
        <select id="version-select" class="border rounded px-2 py-1" style="padding-right:2rem;">
            @foreach($versions as $v)
                <option value="{{ $v->id }}">{{ $v->name }}</option>
            @endforeach
        </select>
    </div>
    <table id="schedule-table" class="w-full table-fixed border">
        <thead>
        <tr>
            <th class="w-1/6 border"></th>
            <th class="w-1/6 text-center border" data-day-header="1">Mon</th>
            <th class="w-1/6 text-center border" data-day-header="2">Tue</th>
            <th class="w-1/6 text-center border" data-day-header="3">Wed</th>
            <th class="w-1/6 text-center border" data-day-header="4">Thu</th>
            <th class="w-1/6 text-center border" data-day-header="5">Fri</th>
        </tr>
        </thead>
        <tbody>
        @foreach($periods as $num => $time)
        <tr>
            <th class="border px-2 py-1 text-sm whitespace-normal leading-tight" style="width: 7rem;">
                Period {{ $num }}<br>{{ $time['start'] }} | {{ $time['end'] }}
            </th>
            @for($day=1; $day<=5; $day++)
                <td class="border h-16" data-period="{{ $num }}" data-day="{{ $day }}" style="vertical-align: top;"></td>
            @endfor
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const roomId = {{ $room->id ?? 0 }};
    const table = document.getElementById('schedule-table');
    const versionSelect = document.getElementById('version-select');
    let currentVersion = versionSelect.value;

    versionSelect.addEventListener('change', () => {
        currentVersion = versionSelect.value;
        loadVersion();
    });

    function loadVersion(){
        const spinner = document.getElementById('spinner');
        spinner.classList.remove('hidden');
        fetch(`/schedule/grid/roomsData/room_id/${roomId}/version_id/${currentVersion}`)
            .then(r=>r.json())
            .then(data => {
                clearLessons();
                (data.events || []).forEach(addLessonToTable);
            })
            .finally(() => {
                spinner.classList.add('hidden');
            });
    }
    function clearLessons(){ table.querySelectorAll('td').forEach(td=>td.innerHTML=''); }
    function addLessonToTable(ev){
        const day = parseInt(ev.day,10);
        const period = ev.period;
        if(!period) return;
        const cell = table.querySelector(`td[data-day="${day}"][data-period="${period}"]`);
        if(!cell) return;
        const lesson=document.createElement('div');
        lesson.className='lesson relative text-xs text-white px-1 py-1 rounded mb-1';
        lesson.style.backgroundColor=ev.color || '#64748b';
        const wrap = document.createElement('div');
        wrap.className = 'flex items-start gap-1';
        const reasonBtn = document.createElement('span');
        reasonBtn.className = 'reason-btn cursor-pointer text-white text-xs relative';
        reasonBtn.textContent = '❓';
        const tooltip = document.createElement('div');
        tooltip.className = 'reason-tooltip absolute z-50 left-4 top-4 text-red-600 bg-white border border-red-300 px-3 py-2 text-xs rounded shadow hidden';
        tooltip.textContent = ev.reason || 'No reason provided.';
        tooltip.style.width='14rem';
        reasonBtn.appendChild(tooltip);
        reasonBtn.addEventListener('click', () => {
            tooltip.classList.toggle('hidden');
        });
        const details = document.createElement('div');
        details.innerHTML = `
            <div class="font-semibold">${ev.title || ''}</div>
            <div class="text-sm text-gray-200">${ev.room || ''}</div>
            <div class="text-xs text-gray-300">${ev.teachers || ''}</div>
        `;
        wrap.appendChild(reasonBtn);
        wrap.appendChild(details);
        lesson.appendChild(wrap);
        cell.appendChild(lesson);
    }

    loadVersion();
});
</script>
@endpush
