@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 flex gap-4 max-w-full">
    <div class="w-1/8">
        <h1 class="text-lg font-semibold mb-3 flex items-center gap-2">
            Student schedule: {{ $user->name }}
            <span id="spinner" class="hidden">
                <svg class="animate-spin h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </span>
        </h1>
        <h2 class="text-sm font-semibold mb-2">Subjects</h2>
        <div id="subject-palette"></div>

        <h2 class="text-sm font-semibold mt-4 mb-2">Unassigned Lessons</h2>
        <div id="unassigned-lessons"></div>
    </div>

    <div class="flex-1">
        <div class="flex items-center justify-center mb-2 gap-2">
            <button id="prev-week" class="px-2 py-1 bg-gray-200 rounded">Prev</button>
            <div id="week-label" class="font-semibold"></div>
            <button id="next-week" class="px-2 py-1 bg-gray-200 rounded">Next</button>
            <button id="assign" class="grid-head-button">Assign</button>
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
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const userId = {{ $user->id }};
    const table = document.getElementById('schedule-table');
    const subjectPalette = document.getElementById('subject-palette');
    const unassignedList = document.getElementById('unassigned-lessons');
    let currentMonday = startOfWeek(new Date());
    let dragType = null;
    const periods = @json($periods);
    const timeToPeriod = {};
    Object.entries(periods).forEach(([p, t]) => { timeToPeriod[t.start] = Number(p); });

    function startOfWeek(d){
        const date = new Date(d);
        const day = date.getDay();
        const diff = date.getDate() - day + (day === 0 ? -6 : 1);
        return new Date(date.setDate(diff));
    }
    function formatYMD(d){
        const y=d.getFullYear();
        const m=('0'+(d.getMonth()+1)).slice(-2);
        const da=('0'+d.getDate()).slice(-2);
        return `${y}-${m}-${da}`;
    }
    function loadWeek(){
        const spinner = document.getElementById('spinner');
        spinner.classList.remove('hidden');
        const start = formatYMD(currentMonday);
        document.getElementById('week-label').textContent = start;
        fetch(`/schedule/grid/studentData/user_id/${userId}/start/${start}`)
            .then(r=>r.json())
            .then(data => {
                renderSubjects(data.subjects || []);
                renderUnassigned(data.unassigned || []);
                clearLessons();
                (data.events || []).forEach(addLessonToTable);
                for (let day=1; day<=5; day++) {
                    const header = document.querySelector(`[data-day-header="${day}"]`);
                    if (header) {
                        const date = new Date(currentMonday);
                        date.setDate(date.getDate() + (day - 1));
                        const options = { month: 'short', day: 'numeric' };
                        const dateStr = date.toLocaleDateString(undefined, options);
                        const weekday = header.textContent.split(' ')[0];
                        header.textContent = `${weekday} ${dateStr}`;
                    }
                }
            })
            .finally(()=> spinner.classList.add('hidden'));
    }
    function clearLessons(){ table.querySelectorAll('td').forEach(td=>td.innerHTML=''); }
    function renderSubjects(subjects){
        subjectPalette.innerHTML='';
        subjects.forEach(sub=>{
            const div=document.createElement('div');
            div.className='subject-item cursor-pointer text-white px-2 py-1 rounded mb-2';
            div.draggable=true;
            div.dataset.subjectId=sub.id;
            div.dataset.label=sub.code || sub.name;
            div.dataset.quantity=sub.quantity;
            div.style.backgroundColor=sub.color || '#64748b';
            div.textContent=`${div.dataset.label} (${sub.quantity})`;
            subjectPalette.appendChild(div);
        });
    }
    function renderUnassigned(lessons){
        unassignedList.innerHTML='';
        lessons.forEach(l=>{
            const div=document.createElement('div');
            div.className='unassigned-item cursor-pointer text-white px-2 py-1 rounded mb-2';
            div.dataset.lessonId=l.id;
            div.dataset.subjectId=l.subject_id;
            div.style.backgroundColor=l.color || '#64748b';
            const date=new Date(l.date);
            const options={weekday:'short', month:'short', day:'numeric'};
            const dateStr=date.toLocaleDateString(undefined, options);
            div.textContent=`${l.title} ${dateStr} P${l.period}`;
            unassignedList.appendChild(div);
        });
    }
    function addLessonToTable(ev){
        const date = new Date(ev.date);
        const day = date.getDay();
        const period = ev.period;
        if(!period) return;
        const cell = table.querySelector(`td[data-day="${day}"][data-period="${period}"]`);
        if(!cell) return;
        const lesson=document.createElement('div');
        lesson.className='lesson relative text-xs text-white px-1 py-1 rounded mb-1';
        lesson.draggable=true;
        lesson.style.backgroundColor=ev.color || '#64748b';
        lesson.dataset.id=ev.id;

        const delBtn = document.createElement('button');
        delBtn.className = 'delete-btn absolute top-0 right-0 text-xl text-white hover:text-red-300';
        delBtn.dataset.id = ev.id;
        delBtn.textContent = '×';
        lesson.appendChild(delBtn);

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
    subjectPalette.addEventListener('dragstart', e=>{
        if(e.target.classList.contains('subject-item')){
            dragType = 'subject';
            e.dataTransfer.setData('subjectId', e.target.dataset.subjectId);
        }
    });
    unassignedList.addEventListener('click', e=>{
        const item = e.target.closest('.unassigned-item');
        if(!item) return;
        const lessonId = item.dataset.lessonId;
        const subjectId = item.dataset.subjectId;
        if(!confirm('Assign this lesson to the student?')) return;
        fetch(`/schedule/lesson/assignToStudent/lesson_id/${lessonId}/user_id/${userId}`)
            .then(r=>r.json())
            .then(data=>{
                addLessonToTable(data);
                item.remove();
                decreaseSubjectQuantity(subjectId);
            });
    });
    table.addEventListener('dragstart', e=>{
        if(e.target.classList.contains('lesson')){
            dragType = 'lesson';
            e.dataTransfer.setData('lessonId', e.target.dataset.id);
        }
    });
    table.addEventListener('dragover', e=>{ e.preventDefault(); });
    table.addEventListener('drop', e=>{
        const cell = e.target.closest('td[data-day]');
        if(!cell) return;
        const day = parseInt(cell.dataset.day,10);
        const period = cell.dataset.period;
        const date = new Date(currentMonday);
        date.setDate(date.getDate()+day-1);
        const ymd = formatYMD(date);
        if(dragType==='subject'){
            const subjectId = e.dataTransfer.getData('subjectId');
            fetch(`/schedule/lesson/createFromSubjectPeriodStudent/subject_id/${subjectId}/date/${ymd}/period/${period}/user_id/${userId}`)
                .then(r=>r.json())
                .then(data=>{
                    addLessonToTable({
                        id:data.id,
                        title:data.title,
                        color:data.color,
                        date:ymd,
                        period:period,
                        reason:data.reason,
                        room:data.room,
                        teachers:data.teachers
                    });
                    decreaseSubjectQuantity(subjectId);
                });
        } else if (dragType === 'lesson') {
            const lessonId = e.dataTransfer.getData('lessonId');
            const lessonEl = document.querySelector(`.lesson[data-id="${lessonId}"]`);
            if (!lessonEl) return;
            const fromCell   = lessonEl.closest('td[data-day][data-period]');
            const fromDay    = fromCell ? fromCell.dataset.day : null;
            const fromPeriod = fromCell ? fromCell.dataset.period : null;
            cell.appendChild(lessonEl);
            const date = new Date(currentMonday);
            date.setDate(date.getDate() + (day - 1));
            const ymd = formatYMD(date);
            fetch(`/schedule/lesson/update/lesson_id/${lessonId}/date/${ymd}/period/${period}`)
                .then(res => { if (!res.ok) throw new Error('Update failed'); })
                .catch(err => {
                    console.error(err);
                    if (fromCell) fromCell.appendChild(lessonEl);
                });
        }
    });
    table.addEventListener('click', e=>{
        if(e.target.classList.contains('delete-btn')){
            const id = e.target.dataset.id;
            if(!confirm('Delete this lesson?')) return;
            fetch(`/schedule/lesson/delete/lesson_id/${id}`).then(()=> loadWeek());
        }
    });
    function decreaseSubjectQuantity(id){
        const el = subjectPalette.querySelector(`.subject-item[data-subject-id="${id}"]`);
        if(!el) return;
        let qty = parseInt(el.dataset.quantity,10)-1;
        if(qty<=0) el.remove();
        else { el.dataset.quantity=qty; el.textContent=`${el.dataset.label} (${qty})`; }
    }
    document.getElementById('prev-week').addEventListener('click',()=>{ currentMonday.setDate(currentMonday.getDate()-7); loadWeek(); });
    document.getElementById('next-week').addEventListener('click',()=>{ currentMonday.setDate(currentMonday.getDate()+7); loadWeek(); });
    document.getElementById('assign').addEventListener('click',()=>{
        const monday = formatYMD(currentMonday);
        window.location.href = `/schedule/lesson/assignStudentLessons/user_id/${userId}/start/${monday}`;
    });
    loadWeek();
});
</script>
@endpush

