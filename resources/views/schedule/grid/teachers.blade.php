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
            <select id="version-select" class="border rounded px-2 py-1" style="padding-right:2rem;">
                @foreach($versions as $v)
                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                @endforeach
            </select>
            <button id="add-version" class="grid-head-button">Add version</button>
            <button id="fill-week"
                    class="grid-head-button">
                Fill
            </button>
            <button id="optimize" class="grid-head-button">Optimize</button>
            <button id="save" class="grid-head-button hidden">Save</button>
            <button id="undo" class="grid-head-button hidden">Undo</button>
            <button id="export" class="grid-head-button">Export</button>

            <div id="optimize-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                <div class="bg-white rounded-lg shadow-lg w-full max-w-lg p-4">
                    <h2 class="text-lg font-semibold mb-3">Enter prompt for optimization</h2>
                    <textarea id="optimize-textarea"
                              class="w-full border rounded p-2 text-sm"
                              rows="6"
                              placeholder="Write your optimization rules here..."></textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button id="cancel-optimize" class="px-3 py-1 rounded bg-gray-200">Cancel</button>
                        <button id="confirm-optimize" class="px-3 py-1 rounded bg-blue-600 text-white">Optimize</button>
                    </div>
                </div>
            </div>
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
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
document.addEventListener('DOMContentLoaded', () => {
    const teacherId = {{ $teacher->id ?? 0 }};
    const table = document.getElementById('schedule-table');
    const subjectPalette = document.getElementById('subject-palette');
    const versionSelect = document.getElementById('version-select');
    const addVersionBtn = document.getElementById('add-version');
    const params = new URLSearchParams(window.location.search);
    const urlVersionId = params.get('version_id');
    if (urlVersionId) {
        versionSelect.value = urlVersionId;
    }
    let currentVersion = versionSelect.value;
    let dragType = null;
    const periods = @json($periods);
    const timeToPeriod = {};
    Object.entries(periods).forEach(([p, t]) => { timeToPeriod[t.start] = Number(p); });
    let originalEvents = null;
    let jobId = null;

    function csrfToken() {
        const el = document.querySelector('meta[name="csrf-token"]');
        return el ? el.getAttribute('content') : '';
    }

    versionSelect.addEventListener('change', () => {
        currentVersion = versionSelect.value;
        loadVersion();
    });

    addVersionBtn.addEventListener('click', () => {
        const name = prompt('Enter version name');
        if (!name) return;
        fetch(`/schedule/version/create`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ name })
        })
            .then(r => r.json())
            .then(data => {
                let base = teacherId ? `/schedule/grid/teachers/teacher_id/${teacherId}` : '/schedule/grid/teachers';
                window.location.href = `${base}?version_id=${data.id}`;
            });
    });

    function loadVersion(){
        const spinner = document.getElementById('spinner');
        spinner.classList.remove('hidden');
        originalEvents = null;
        document.getElementById('save').classList.add('hidden');
        document.getElementById('undo').classList.add('hidden');

        fetch(`/schedule/grid/teachersData/teacher_id/${teacherId}/version_id/${currentVersion}`)
            .then(r => r.json())
            .then(data => {
                renderSubjects(data.subjects || []);
                clearLessons();
                if (data.free) {
                    Object.entries(data.free).forEach(([day, periods]) => {
                        Object.entries(periods).forEach(([period, students]) => {
                            addLessonToTable({
                                day: day,
                                period: period,
                                title: 'FREE',
                                color: '#d1d5db',
                                students: students,
                                isFree: true
                            });
                        });
                    });
                }
                (data.events || []).forEach(addLessonToTable);
            })
            .catch(err => {
                console.error('Error loading version:', err);
            })
            .finally(() => {
                spinner.classList.add('hidden');
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
        const day = parseInt(ev.day,10);
        const period = ev.period;
        if(!period) return;
        const cell = table.querySelector(`td[data-day="${day}"][data-period="${period}"]`);
        if(!cell) return;

        const lesson=document.createElement('div');
        let cls = 'lesson relative text-xs px-1 py-1 rounded mb-1';
        if(ev.isFree){
            cls += ' text-gray-800';
            lesson.style.backgroundColor=ev.color || '#d1d5db';
        } else {
            cls += ' text-white';
            lesson.style.backgroundColor=ev.color || '#64748b';
            lesson.draggable=!ev.fixed;
            lesson.dataset.id=ev.id;
        }
        lesson.className = cls;

        if(!ev.isFree){
            const actionContainer = document.createElement('div');
            actionContainer.className = 'flex items-center gap-1 absolute top-0 right-0 m-0.5';

            const fixedInput = document.createElement('input');
            fixedInput.type = 'checkbox';
            fixedInput.className = 'fixed-checkbox w-4 h-4 rounded border-gray-400 text-blue-600 focus:ring-blue-500';
            fixedInput.dataset.id = ev.id;
            fixedInput.checked = !!ev.fixed;
            actionContainer.appendChild(fixedInput);

            const delBtn = document.createElement('button');
            delBtn.className = 'delete-btn text-sm text-white hover:text-red-300';
            delBtn.dataset.id = ev.id;
            delBtn.textContent = '❌';
            actionContainer.appendChild(delBtn);

            lesson.appendChild(actionContainer);
        }

        const wrap = document.createElement('div');
        wrap.className = 'flex items-start gap-1';

        const iconWrap = document.createElement('div');
        iconWrap.className = 'flex flex-col gap-1';

        if(!ev.isFree){
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
            iconWrap.appendChild(reasonBtn);
        }

        if (ev.students && ev.students.length) {
            const studentsBtn = document.createElement('span');
            studentsBtn.className = `students-btn cursor-pointer text-xs relative ${ev.isFree ? 'text-gray-800' : 'text-white'}`;
            studentsBtn.textContent = '👥';

            const list = document.createElement('div');
            list.className = 'students-list absolute z-50 left-4 top-6 text-blue-600 bg-white border border-blue-300 text-xs rounded shadow hidden';
            list.style.width = '12rem';

            const header = document.createElement('div');
            header.className = 'px-3 py-2 border-b bg-blue-50 text-gray-700 font-semibold';
            header.textContent = `Students (${ev.students.length})`;
            list.appendChild(header);

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

        const details = document.createElement('div');
        const studentCount = Array.isArray(ev.students) ? ev.students.length : 0;

        if (ev.isFree) {
            details.innerHTML = `
                <div class="font-semibold">
                    FREE${studentCount ? ` (${studentCount})` : ''}
                </div>
            `;
        } else {
            details.innerHTML = `
                <div class="font-semibold">
                    ${ev.title || ''}${studentCount ? ` (${studentCount})` : ''}
                </div>
                <div class="text-sm text-gray-100">${ev.room || ''}</div>
                <div class="text-xs text-gray-100 teachers-text">${ev.teachers || ''}</div>
            `;
        }

        wrap.appendChild(iconWrap);
        wrap.appendChild(details);
        lesson.appendChild(wrap);

        if(ev.isFree){
            cell.prepend(lesson);
        } else {
            cell.appendChild(lesson);
        }
    }

    function getCurrentEvents(){
        const events = [];
        table.querySelectorAll('td[data-day][data-period]').forEach(cell => {
            const day = parseInt(cell.dataset.day,10);
            const period = cell.dataset.period;
            cell.querySelectorAll('.lesson').forEach(lesson => {
                events.push({
                    id: lesson.dataset.id,
                    title: lesson.querySelector('.font-semibold')?.textContent || '',
                    color: lesson.style.backgroundColor || '#64748b',
                    day: day,
                    period: period,
                    reason: lesson.querySelector('.reason-tooltip')?.textContent || '',
                    room: lesson.querySelector('.text-sm')?.textContent || '',
                    teachers: lesson.querySelector('.teachers-text')?.textContent || '',
                    fixed: lesson.querySelector('.fixed-checkbox')?.checked || false,
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
        if(dragType==='subject'){
            const subjectId = e.dataTransfer.getData('subjectId');
            fetch(`/schedule/lesson/createFromSubjectPeriod/subject_id/${subjectId}/day/${day}/period/${period}/version_id/${currentVersion}`)
                .then(r=>r.json())
                .then(data=>{
                    addLessonToTable({
                        id:data.id,
                        title:data.title,
                        color:data.color,
                        day:day,
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

            const fromCell = lessonEl.closest('td[data-day][data-period]');
            cell.appendChild(lessonEl);

            fetch(`/schedule/lesson/update/lesson_id/${lessonId}/day/${day}/period/${period}/version_id/${currentVersion}`)
                .then(res => {
                    if (!res.ok) throw new Error('Update failed');
                })
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
            fetch(`/schedule/lesson/delete/lesson_id/${id}`).then(()=> loadVersion());
        }
    });
    table.addEventListener('change', e=>{
        if(e.target.classList.contains('fixed-checkbox')){
            const id = e.target.dataset.id;
            const fixed = e.target.checked ? 1 : 0;
            fetch(`/schedule/lesson/setFixed/lesson_id/${id}/fixed/${fixed}`);
        }
    });
    function decreaseSubjectQuantity(id){
        const el = subjectPalette.querySelector(`.subject-item[data-subject-id="${id}"]`);
        if(!el) return;
        let qty = parseInt(el.dataset.quantity,10)-1;
        if(qty<=0) el.remove();
        else { el.dataset.quantity=qty; el.textContent=`${el.dataset.label} (${qty})`; }
    }
    document.getElementById('fill-week').addEventListener('click',()=>{
        window.location.href = `/schedule/lesson/autoFillTeacher/teacher_id/${teacherId}/version_id/${currentVersion}`;
    });

    document.getElementById('optimize').addEventListener('click', () => {
        document.getElementById('optimize-modal').classList.remove('hidden');
    });

    document.getElementById('confirm-optimize').addEventListener('click', () => {
        const promptText = document.getElementById('optimize-textarea').value.trim();
        if (!promptText) return;
        document.getElementById('optimize-modal').classList.add('hidden');
        runOptimize(promptText);
    });

    document.getElementById('cancel-optimize').addEventListener('click', () => {
        document.getElementById('optimize-modal').classList.add('hidden');
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

    document.getElementById('export').addEventListener('click', () => {
        window.location.href = `/schedule/index/teachersExport/version_id/${currentVersion}/teacher_id/${teacherId}`;
    });

    loadVersion();

    function runOptimize(promptText) {
        const spinner = document.getElementById('spinner');
        spinner.classList.remove('hidden');
        originalEvents = getCurrentEvents();

        fetch(`/schedule/index/optimizeTeachers`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ version_id: currentVersion, prompt: promptText })
        })
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
                                const events = (result.events || []).map(ev => ({
                                    id: ev.id,
                                    title: ev.title,
                                    color: ev.color,
                                    day: ev.day,
                                    period: ev.period,
                                    reason: ev.reason,
                                    room: ev.room,
                                    teachers: ev.teachers,
                                    students: ev.students || [],
                                })).filter(e => e.period);

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
    }
});
</script>
@endpush
