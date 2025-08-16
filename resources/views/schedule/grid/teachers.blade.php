@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 flex gap-4 max-w-full">
    {{-- Left: Subject palette --}}
    <div class="w-1/8">
        <h1 class="text-lg font-semibold mb-3 flex items-center gap-2">
            Teacher schedule{{ $teacher ? ': ' . ($teacher->user->name ?? 'Teacher') : '' }}
            <span id="spinner" class="hidden">
                <svg class="animate-spin h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
            </span>
        </h1>
        <h2 class="text-sm font-semibold mb-2">Subjects</h2>
        <div id="subject-palette"></div>
    </div>

    {{-- Right: Grid --}}
    <div class="flex-1">
        <div class="flex items-center justify-center mb-2 gap-2">
            <button id="prev-week" class="px-2 py-1 bg-gray-200 rounded">Prev</button>
            <div id="week-label" class="font-semibold"></div>
            <button id="next-week" class="px-2 py-1 bg-gray-200 rounded">Next</button>
            <button id="fill-week"
                    class="grid-head-button">
                Fill week
            </button>
            <button id="optimize" class="grid-head-button">Optimize</button>
            <button id="save" class="grid-head-button hidden">Save</button>
            <button id="undo" class="grid-head-button hidden">Undo</button>
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
                      @for($day = 1; $day <= 5; $day++)
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
    const teacherId = {{ $teacher->id ?? 0 }};
    const table = document.getElementById('schedule-table');
    const subjectPalette = document.getElementById('subject-palette');
    let currentMonday = startOfWeek(new Date());
    let dragType = null;
    const periods = @json($periods);
    const timeToPeriod = {};
    Object.entries(periods).forEach(([p, t]) => { timeToPeriod[t.start] = Number(p); });
    let originalEvents = null;
    let jobId = null;

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
        spinner.classList.remove('hidden'); // show spinner
        originalEvents = null;
        document.getElementById('save').classList.add('hidden');
        document.getElementById('undo').classList.add('hidden');

        const start = formatYMD(currentMonday);
        document.getElementById('week-label').textContent = start;

        fetch(`/schedule/grid/teachersData/teacher_id/${teacherId}/start/${start}`)
            .then(r => r.json())
            .then(data => {
                renderSubjects(data.subjects || []);
                clearLessons();
                (data.events || []).forEach(addLessonToTable);
                // Update weekday headers with date
                for (let day = 1; day <= 5; day++) {
                    const header = document.querySelector(`[data-day-header="${day}"]`);
                    if (header) {
                        const date = new Date(currentMonday);
                        date.setDate(date.getDate() + (day - 1));
                        const options = { month: 'short', day: 'numeric' };
                        const dateStr = date.toLocaleDateString(undefined, options);
                        const weekday = header.textContent.split(' ')[0]; // original name
                        header.textContent = `${weekday} ${dateStr}`;
                    }
                }
            })
            .catch(err => {
                console.error('Error loading week:', err);
            })
            .finally(() => {
                spinner.classList.add('hidden'); // hide spinner
            });
    }

    function clearLessons(){
        table.querySelectorAll('td').forEach(td=>td.innerHTML='');
    }
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
        tooltip.style.width='10rem';
        reasonBtn.appendChild(tooltip);
        reasonBtn.addEventListener('click', () => {
            tooltip.classList.toggle('hidden');
        });

        const details = document.createElement('div');
        details.innerHTML = `
            <div class="font-semibold">${ev.title || ''}</div>
            <div class="text-sm text-gray-200">${ev.room || ''}</div>
            <div class="text-xs text-gray-300 teachers-text">${ev.teachers || ''}</div>
        `;

        const iconWrap = document.createElement('div');
        iconWrap.className = 'flex flex-col gap-1';
        iconWrap.appendChild(reasonBtn);

        if (ev.students && ev.students.length) {
            const studentsBtn = document.createElement('span');
            studentsBtn.className = 'students-btn cursor-pointer text-white text-xs relative';
            studentsBtn.textContent = '👥';

            const list = document.createElement('div');
            list.className = 'students-list absolute z-50 left-4 top-6 text-blue-600 bg-white border border-blue-300 text-xs rounded shadow hidden';
            list.style.width = '12rem';

            // Header with count
            const header = document.createElement('div');
            header.className = 'px-3 py-2 border-b bg-blue-50 text-gray-700 font-semibold';
            header.textContent = `Students (${ev.students.length})`;
            list.appendChild(header);
            // Scrollable body
            const body = document.createElement('div');
            body.className = 'max-h-48 overflow-auto px-3 py-2';
            ev.students.forEach(s => {
                const item = document.createElement('div');
                item.className = 'py-1';
                item.dataset.id = s.id;
                item.textContent = s.name;
                body.appendChild(item);
            });
            list.appendChild(body);
            studentsBtn.appendChild(list);
            studentsBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                list.classList.toggle('hidden');
            });
            document.addEventListener('click', () => list.classList.add('hidden'));
            iconWrap.appendChild(studentsBtn);
        }

        wrap.appendChild(iconWrap);
        wrap.appendChild(details);
        lesson.appendChild(wrap);

        cell.appendChild(lesson);
    }

    function getCurrentEvents(){
        const events = [];
        table.querySelectorAll('td[data-day][data-period]').forEach(cell => {
            const day = parseInt(cell.dataset.day,10);
            const period = cell.dataset.period;
            const date = new Date(currentMonday);
            date.setDate(date.getDate() + (day - 1));
            const ymd = formatYMD(date);
            cell.querySelectorAll('.lesson').forEach(lesson => {
                events.push({
                    id: lesson.dataset.id,
                    title: lesson.querySelector('.font-semibold')?.textContent || '',
                    color: lesson.style.backgroundColor || '#64748b',
                    date: ymd,
                    period: period,
                    reason: lesson.querySelector('.reason-tooltip')?.textContent || '',
                    room: lesson.querySelector('.text-sm')?.textContent || '',
                    teachers: lesson.querySelector('.teachers-text')?.textContent || '',
                    students: Array.from(lesson.querySelectorAll('.students-list div')).map(div => ({
                        id: div.dataset.id,
                        name: div.textContent,
                    })),
                });
            });
        });
        return events;
    }

    subjectPalette.addEventListener('dragstart', e=>{
        if(e.target.classList.contains('subject-item')){
            dragType = 'subject';
            e.dataTransfer.setData('subjectId', e.target.dataset.subjectId);
        }
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
              fetch(`/schedule/lesson/createFromSubjectPeriod/subject_id/${subjectId}/date/${ymd}/period/${period}`)
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
                          teachers:data.teachers,
                          students:data.students || []
                      });
                      decreaseSubjectQuantity(subjectId);
                  });
          } else if (dragType === 'lesson') {
        const lessonId = e.dataTransfer.getData('lessonId');
        const lessonEl = document.querySelector(`.lesson[data-id="${lessonId}"]`);
        if (!lessonEl) return;

        // Remember origin (for revert on failure)
        const fromCell   = lessonEl.closest('td[data-day][data-period]');
        const fromDay    = fromCell ? fromCell.dataset.day : null;
        const fromPeriod = fromCell ? fromCell.dataset.period : null;

        // Optimistically move lesson in the UI
        cell.appendChild(lessonEl);

        // Compute YYYY-MM-DD for target cell
        const date = new Date(currentMonday);
        date.setDate(date.getDate() + (day - 1));
        const ymd = formatYMD(date);

        // Fire update request (no full reload)
        fetch(`/schedule/lesson/update/lesson_id/${lessonId}/date/${ymd}/period/${period}`)
            .then(res => {
                if (!res.ok) throw new Error('Update failed');
                // Optionally update any local data attributes if you use them later
                lessonEl.dataset.date = ymd;
                lessonEl.dataset.period = period;
                lessonEl.dataset.day = String(day);
            })
            .catch(err => {
                console.error(err);
                // Revert UI on failure
                if (fromCell) fromCell.appendChild(lessonEl);
                // Optional: toast/alert
                // alert('Could not move lesson. Please try again.');
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
    document.getElementById('fill-week').addEventListener('click',()=>{
        const monday = formatYMD(currentMonday);
        window.location.href = `/schedule/lesson/autoFillTeacher/teacher_id/${teacherId}/start/${monday}`;
    });

    document.getElementById('optimize').addEventListener('click', () => {
        const monday = formatYMD(currentMonday);
        const spinner = document.getElementById('spinner');
        spinner.classList.remove('hidden');
        originalEvents = getCurrentEvents();
        fetch(`/schedule/index/optimizeTeachers/start/${monday}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) throw new Error(data.error);
                jobId = data.jobId;
                if (!jobId) throw new Error('No job id');
                const poll = setInterval(() => {
                    fetch(`/schedule/index/getOptimizedTeachers/jobId/${jobId}`)
                        .then(r => r.json())
                        .then(result => {
                            if (result.status !== 'pending') {
                                clearInterval(poll);
                                if (result.status === 'failed') {
                                    spinner.classList.add('hidden');
                                    alert(result.error || 'Optimization failed');
                                    return;
                                }
                                const events = (result.events || []).map(ev => {
                                    return {
                                        id: ev.id,
                                        title: ev.title,
                                        color: ev.color,
                                        date: ev.date,
                                        period: ev.period,
                                        reason: ev.reason,
                                        room: ev.room ,
                                        teachers: ev.teachers,
                                        students: ev.students || [],
                                    };
                                }).filter(e => e.period);
                                console.log(events);
                                clearLessons();
                                events.forEach(addLessonToTable);
                                document.getElementById('save').classList.remove('hidden');
                                document.getElementById('undo').classList.remove('hidden');
                                spinner.classList.add('hidden');
                            }
                        })
                        .catch(err => {
                            clearInterval(poll);
                            spinner.classList.add('hidden');
                            alert(err.message || 'Optimization failed');
                        });
                }, 10000);
            })
            .catch(err => {
                spinner.classList.add('hidden');
                alert(err.message || 'Optimization failed');
            });
    });

    document.getElementById('save').addEventListener('click', () => {
        if (!jobId) return;
        fetch(`/schedule/index/saveOptimizedTeachers/jobId/${jobId}`)
            .then(() => window.location.reload())
            .catch(() => alert('Failed to save schedule'));
    });

    document.getElementById('undo').addEventListener('click', () => {
        if (!originalEvents) return;
        clearLessons();
        originalEvents.forEach(addLessonToTable);
        jobId = null;
        originalEvents = null;
        document.getElementById('save').classList.add('hidden');
        document.getElementById('undo').classList.add('hidden');
    });

    loadWeek();
});
</script>
@endpush
