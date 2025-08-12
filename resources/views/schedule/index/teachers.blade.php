@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-4 flex gap-4">
        {{-- Left: Subject palette --}}
        <div class="w-1/4" id="external-events">
            <h1 class="text-lg font-semibold mb-3">
                Teacher schedule{{ $teacher ? ': ' . ($teacher->user->name ?? 'Teacher') : '' }}
                <span id="spinner" class="hidden">
                    <svg class="animate-spin h-4 w-4 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z">
                        </path>
                    </svg>
                </span>
            </h1>

            <h2 class="text-sm font-semibold mb-2">Subjects</h2>
            @foreach ($subjects as $subject)
                <div
                    class="fc-event cursor-pointer text-white px-2 py-1 rounded mb-2"
                    data-subject-id="{{ $subject->id }}"
                    style="background-color: {{ $subject->color ?? '#64748b' }}"
                    title="{{ $subject->name }}"
                >
                    {{ $subject->code ?? $subject->name }}
                </div>
            @endforeach
        </div>

        {{-- Right: Calendar --}}
        <div class="flex-1">
            <div id="calendar" data-teacher-id="{{ $teacher->id ?? 0 }}"></div>
        </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Make the subject list draggable
            const Draggable = FullCalendar.Draggable;
            new Draggable(document.getElementById('external-events'), {
                itemSelector: '.fc-event',
                eventData: function (el) {
                    return {
                        title: el.innerText.trim(),
                        color: el.style.backgroundColor,
                        extendedProps: { subjectId: el.getAttribute('data-subject-id') }
                    };
                }
            });

            const calendarEl = document.getElementById('calendar');
            const teacherId = calendarEl.getAttribute('data-teacher-id');

            let originalEvents = null;
            let generatedLessons = null;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                slotDuration: '00:15:00',
                slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                nowIndicator: true,
                firstDay: 1,         // Monday
                weekends: false,     // Mon-Fri
                allDaySlot: false,
                scrollTime: '09:00:00',
                slotMinTime: '09:00:00',
                slotMaxTime: '15:00:00',
                defaultTimedEventDuration: '00:45:00',
                timeZone: 'local',   // avoid UTC shifts
                editable: true,
                droppable: true,
                selectable: true,
                selectMirror: true,
                locale: 'en',

                dayHeaderFormat: {
                    weekday: 'short',
                    day: '2-digit',
                },

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay fillWeek optimize save undo'
                },

                // Custom buttons for calendar
                customButtons: {
                    fillWeek: {
                        text: 'Fill week',
                        click: () => {
                            const monday = toLocalYMD(calendar.view.activeStart); // local YYYY-MM-DD
                            window.location.href =
                                `/schedule/lesson/autoFillTeacher/teacher_id/${teacherId}/start/${monday}`;
                        }
                    },
                    optimize: {
                        text: 'Optimize',
                        click: () => {
                            const monday = toLocalYMD(calendar.view.activeStart);
                            const spinner = document.getElementById('spinner');
                            spinner.classList.remove('hidden');

                            originalEvents = calendar.getEvents().map(ev => ({
                                id: ev.id,
                                title: ev.title,
                                start: ev.startStr,
                                end: ev.endStr,
                                color: ev.backgroundColor,
                                extendedProps: ev.extendedProps,
                            }));

                            fetch(`/schedule/index/optimizeTeachers/start/${monday}`)
                                .then(res => res.json())
                                .then(data => {
                                    const jobId = data.jobId;
                                    if (!jobId) throw new Error('No job id');

                                    const poll = setInterval(() => {
                                        fetch(`/schedule/index/getOptimizedTeachers/jobId/${jobId}`)
                                            .then(r => r.json())
                                            .then(result => {
                                                if (result.status !== 'pending') {
                                                    clearInterval(poll);
                                                    generatedLessons = result.lessons || [];
                                                    calendar.removeAllEvents();
                                                    calendar.addEventSource(result.events || []);
                                                    document.querySelector('.fc-save-button').style.display = '';
                                                    document.querySelector('.fc-undo-button').style.display = '';
                                                    spinner.classList.add('hidden');
                                                }
                                            })
                                            .catch(() => {
                                                clearInterval(poll);
                                                spinner.classList.add('hidden');
                                                alert('Optimization failed');
                                            });
                                    }, 1000);
                                })
                                .catch(() => {
                                    spinner.classList.add('hidden');
                                    alert('Optimization failed');
                                });
                        }
                    },
                    save: {
                        text: 'Save',
                        click: () => {
                            if (!generatedLessons) return;
                            fetch('/schedule/index/saveOptimizedTeachers', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                },
                                body: JSON.stringify({ lessons: generatedLessons }),
                            })
                                .then(() => window.location.reload())
                                .catch(() => alert('Failed to save schedule'));
                        }
                    },
                    undo: {
                        text: 'Undo',
                        click: () => {
                            if (!originalEvents) return;
                            calendar.removeAllEvents();
                            calendar.addEventSource(originalEvents);
                            generatedLessons = null;
                            originalEvents = null;
                            document.querySelector('.fc-save-button').style.display = 'none';
                            document.querySelector('.fc-undo-button').style.display = 'none';
                        }
                    }
                },

                // Initial events
                events: @json($events),

                // When a subject pill is dropped onto the calendar: create lesson for THIS teacher
                eventReceive(info) {
                    const subjectId = info.event.extendedProps.subjectId;
                    const start = info.event.start;
                    if (!info.event.end) {
                        info.event.setEnd(new Date(start.getTime() + 45 * 60 * 1000));
                    }

                    const date = toLocalYMD(start);
                    const startTime = formatTime(start);

                    fetch(`/schedule/lesson/createForTeacher/teacher_id/${teacherId}/subject_id/${subjectId}/date/${date}/start_time/${startTime}`)
                        .then(res => res.json())
                        .then(data => {
                            if (!data?.id) throw new Error('Creation failed');
                            info.event.setProp('id', data.id);
                            if (data.title)    info.event.setProp('title', data.title);
                            if (data.reason)   info.event.setExtendedProp('reason', data.reason);
                            if (data.room)     info.event.setExtendedProp('room', data.room);
                            if (data.teachers) info.event.setExtendedProp('teachers', data.teachers);
                        })
                        .catch(() => info.event.remove());
                },

                // Drag/resize updates
                eventDrop:   ({ event }) => updateLesson(event),
                eventResize: ({ event }) => updateLesson(event),

                // Custom event rendering: reason tooltip + actions menu
                eventContent(arg) {
                    const wrap = document.createElement('div');
                    wrap.className = 'flex justify-between items-start gap-1 w-full';

                    const left = document.createElement('div');
                    left.className = 'flex items-start';

                    const reasonBtn = document.createElement('span');
                    reasonBtn.textContent = '❓';
                    reasonBtn.className = 'cursor-pointer text-white text-sm hover:text-yellow-300 relative';

                    const tooltip = document.createElement('div');
                    tooltip.className = 'absolute left-4 top-6 text-red-600 bg-white border border-red-300 px-3 py-2 text-sm rounded shadow max-w-xs w-64 hidden';
                    tooltip.style.zIndex = '9999';
                    tooltip.textContent = arg.event.extendedProps.reason || 'No reason provided.';
                    reasonBtn.addEventListener('click', () => tooltip.classList.toggle('hidden'));
                    reasonBtn.appendChild(tooltip);

                    const details = document.createElement('div');
                    details.innerHTML = `
                        <div class="font-semibold text-white truncate">${arg.event.title || ''}</div>
                        <div class="text-sm text-gray-200">${arg.event.extendedProps.room || ''}</div>
                        <div class="text-xs text-gray-300">${arg.event.extendedProps.teachers || ''}</div>
                    `;

                    left.appendChild(reasonBtn);
                    left.appendChild(details);

                    const dropdown = document.createElement('div');
                    dropdown.className = 'relative ml-auto';

                    const btn = document.createElement('button');
                    btn.className = 'text-white hover:text-red-300 text-sm px-1';
                    btn.textContent = '⋮';

                    const menu = document.createElement('div');
                    menu.className = 'absolute right-0 mt-1 w-28 bg-white text-black border rounded shadow z-50 hidden';
                    menu.innerHTML = `
                        <button data-id="${arg.event.id}" class="delete-btn block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-100">Delete</button>
                    `;
                    btn.addEventListener('click', (e) => { e.stopPropagation(); menu.classList.toggle('hidden'); });
                    document.addEventListener('click', () => menu.classList.add('hidden'));

                    dropdown.appendChild(btn);
                    dropdown.appendChild(menu);

                    wrap.appendChild(left);
                    wrap.appendChild(dropdown);

                    return { domNodes: [wrap] };
                }
            });

            calendar.render();

            document.querySelector('.fc-save-button').style.display = 'none';
            document.querySelector('.fc-undo-button').style.display = 'none';

            // Delegated actions for delete/edit
            document.addEventListener('click', function (e) {
                if (e.target.matches('.delete-btn')) {
                    const id = e.target.getAttribute('data-id');
                    if (!id) return;
                    if (!confirm('Delete this lesson?')) return;

                    fetch(`/schedule/lesson/delete/lesson_id/${id}`, { method: 'GET' })
                        .then(res => {
                            if (!res.ok) throw new Error();
                            const ev = calendar.getEventById(id);
                            if (ev) ev.remove();
                        })
                        .catch(() => alert('Failed to delete lesson.'));
                }
            });

            // Helpers
            function updateLesson(event) {
                const id = event.id;
                const date = toLocalYMD(event.start);
                if (!event.end) event.setEnd(new Date(event.start.getTime() + 45 * 60 * 1000));
                const startTime = formatTime(event.start);
                const endTime   = formatTime(event.end);

                fetch(`/schedule/lesson/update/lesson_id/${id}/date/${date}/start_time/${startTime}/end_time/${endTime}`, {
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                })
                    .then(res => { if (!res.ok) throw new Error('Failed to update'); return res.json(); })
                    .then(data => {
                        if (data.title)    event.setProp('title', data.title);
                        if (data.reason)   event.setExtendedProp('reason', data.reason);
                        if (data.room)     event.setExtendedProp('room', data.room);
                        if (data.teachers) event.setExtendedProp('teachers', data.teachers);
                    })
                    .catch(err => {
                        alert(err.message || 'Update failed');
                        event.revert();
                    });
            }

            function toLocalYMD(d) {
                const y = d.getFullYear();
                const m = String(d.getMonth() + 1).padStart(2, '0');
                const day = String(d.getDate()).padStart(2, '0');
                return `${y}-${m}-${day}`;
            }

            function formatTime(dateObj) {
                return dateObj.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false,
                });
            }
        });
    </script>
@endpush
