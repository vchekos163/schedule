@extends('layouts.app')

@section('content')
    <div class="container mx-auto p-4">
        @livewire('lessons.edit', ['userId' => $user->id])
        <div id="calendar"></div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <script>
        let calendar;
        document.addEventListener('DOMContentLoaded', function () {
            console.log('Calendar JS loaded');

            calendar = new FullCalendar.Calendar(document.getElementById('calendar'), {
                initialView: 'timeGridWeek',
                slotDuration: '00:15:00',     // ⏱ 15-minute slots
                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false              // ⌚ 24-hour format
                },
                allDaySlot: false,            // ⛔ hide "All Day" slot
                scrollTime: '08:00:00',       // ⬇️ scroll to morning by default
                slotMinTime: '08:00:00',      // 🕖 earliest slot
                slotMaxTime: '16:00:00',      // 🕓 latest visible time
                nowIndicator: true,           // 🔴 show current time line

                firstDay: 1, // Monday
                locale: 'en', // or 'de', 'es', 'ru' — depending on region (see below)

                editable: true, // ⬅️ enables drag/drop & resize
                eventDurationEditable: true,
                eventStartEditable: true,

                // optional: for resizing
                selectable: true,
                selectMirror: true,

                eventDrop: function (info) {
                    updateLesson(info.event);
                },

                eventResize: function (info) {
                    updateLesson(info.event);
                },

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'timeGridWeek,timeGridDay'
                },

                eventTimeFormat: { // 24h format
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },

                dayHeaderFormat: { // Format for "Mon 30 Jul"
                    weekday: 'short',
                    day: '2-digit',
                    month: 'short'
                },

                events: @json($events),
                eventContent: function(arg) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'flex justify-between items-start gap-1 w-full';

                    const infoBox = document.createElement('div');
                    infoBox.innerHTML = `
        <div class="font-semibold text-white truncate">${arg.event.title}</div>
        <div class="text-sm text-gray-200">${arg.event.extendedProps.room || ''}</div>
        <div class="text-xs text-gray-300">${arg.event.extendedProps.teachers || ''}</div>
    `;

                    const dropdownWrapper = document.createElement('div');
                    dropdownWrapper.className = 'relative ml-auto';

                    const button = document.createElement('button');
                    button.className = 'text-white hover:text-red-400 text-sm px-1';
                    button.textContent = '⋮';

                    const menu = document.createElement('div');
                    menu.className = 'absolute right-0 mt-1 w-28 bg-white text-black border rounded shadow z-50 hidden';
                    menu.innerHTML = `
        <button
            data-id="${arg.event.id}"
            class="edit-btn block w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-blue-100"
        >
            Edit
        </button>
        <button
            data-id="${arg.event.id}"
            class="delete-btn block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-100"
        >
            Delete
        </button>
    `;

                    // Toggle menu visibility
                    button.addEventListener('click', (e) => {
                        e.stopPropagation();
                        menu.classList.toggle('hidden');
                    });

                    // Close dropdown when clicking outside
                    document.addEventListener('click', () => {
                        menu.classList.add('hidden');
                    });

                    dropdownWrapper.appendChild(button);
                    dropdownWrapper.appendChild(menu);

                    wrapper.appendChild(infoBox);
                    wrapper.appendChild(dropdownWrapper);

                    return { domNodes: [wrapper] };
                }
            });

            calendar.render();
        });

        document.addEventListener('click', function (e) {
            if (e.target.matches('.delete-btn')) {
                const id = e.target.getAttribute('data-id');

                if (confirm('Are you sure you want to delete this lesson?')) {
                    fetch(`/admin/schedule/delete/lesson_id/${id}`, {
                        method: 'GET',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    }).then(res => {
                        if (res.ok) {
                            // ✅ Remove event from calendar
                            if (event) event.remove();
                        } else {
                            alert('Failed to delete lesson.');
                        }
                    });
                }
                if (e.target.matches('.edit-btn')) {
                    const id = e.target.getAttribute('data-id');

                    Livewire.dispatch('open-lesson-modal', {
                        lessonId: id,
                    });
                }
            }
        });

        function updateLesson(event) {
            const lessonId = event.id;
            const date = event.start.toISOString().slice(0, 10);
            const startTime = formatTime(event.start);
            const endTime = event.end ? event.end.toISOString().slice(11, 16) : startTime;

            const url = `/admin/schedule/update/lesson_id/${lessonId}/date/${date}/start_time/${startTime}/end_time/${endTime}`;

            fetch(url, {
                method: 'GET', // You can use POST if needed, but GET is easiest with your current routing
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to update lesson.');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Lesson updated:', data);
                })
                .catch(error => {
                    alert(error.message);
                    event.revert(); // rollback the calendar change
                });
        }
        function formatTime(dateObj) {
            return dateObj.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });
        }
    </script>
@endpush


