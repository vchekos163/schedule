@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 flex gap-4">
    {{-- Left: Subject palette --}}
    <div class="w-1/4">
        <h1 class="text-lg font-semibold mb-3">
            Teacher schedule{{ $teacher ? ': ' . ($teacher->user->name ?? 'Teacher') : '' }}
            <span id="spinner" class="hidden">
                <svg class="animate-spin h-4 w-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
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
        <div class="flex items-center mb-2 gap-2">
            <button id="prev-week" class="px-2 py-1 bg-gray-200 rounded">Prev</button>
            <div id="week-label" class="font-semibold"></div>
            <button id="next-week" class="px-2 py-1 bg-gray-200 rounded">Next</button>
            <button id="fill-week" class="ml-auto px-2 py-1 bg-blue-500 text-white rounded">Fill week</button>
        </div>
        <table id="schedule-table" class="w-full table-fixed border">
            <thead>
                <tr>
                    <th class="w-24"></th>
                    <th class="text-center">Mon</th>
                    <th class="text-center">Tue</th>
                    <th class="text-center">Wed</th>
                    <th class="text-center">Thu</th>
                    <th class="text-center">Fri</th>
                </tr>
            </thead>
            <tbody>
                  @foreach($periods as $num => $period)
                  <tr>
                      <th class="border px-2 py-1 text-sm">Period {{ $num }}</th>
                      @for($day = 1; $day <= 5; $day++)
                          <td class="border h-16 align-top" data-period="{{ $num }}" data-day="{{ $day }}"></td>
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
        const start = formatYMD(currentMonday);
        document.getElementById('week-label').textContent = start;
        fetch(`/schedule/index/teachersData/teacher_id/${teacherId}/start/${start}`)
            .then(r=>r.json())
            .then(data=>{
                renderSubjects(data.subjects || []);
                clearLessons();
                (data.events||[]).forEach(addLessonToTable);
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
        lesson.className='lesson text-xs text-white px-1 py-1 rounded mb-1';
        lesson.draggable=true;
        lesson.style.backgroundColor=ev.color || '#64748b';
        lesson.dataset.id=ev.id;
        lesson.innerHTML = `<span>${ev.title}</span> <button class="delete-btn ml-1" data-id="${ev.id}">x</button>`;
        cell.appendChild(lesson);
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
              fetch(`/schedule/lesson/createFromSubject/subject_id/${subjectId}/date/${ymd}/period/${period}`)
                  .then(r=>r.json())
                  .then(data=>{
                      addLessonToTable({id:data.id,title:data.title,color:data.color,date:ymd,period:period});
                      decreaseSubjectQuantity(subjectId);
                  });
          } else if(dragType==='lesson'){
              const lessonId = e.dataTransfer.getData('lessonId');
              fetch(`/schedule/lesson/update/lesson_id/${lessonId}/date/${ymd}/period/${period}`)
                  .then(()=> loadWeek());
          }
      });
    table.addEventListener('click', e=>{
        if(e.target.classList.contains('delete-btn')){
            const id = e.target.dataset.id;
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

    loadWeek();
});
</script>
@endpush
