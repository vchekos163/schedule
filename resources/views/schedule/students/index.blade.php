@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 overflow-x-auto max-w-full">
    <div class="flex items-center justify-center mb-4 gap-2">
        <select id="version-select" class="border rounded px-2 py-1" style="padding-right:2rem;">
            @foreach($versions as $v)
                <option value="{{ $v->id }}" {{ $v->id == $versionId ? 'selected' : '' }}>{{ $v->name }}</option>
            @endforeach
        </select>
        <button id="assign" class="grid-head-button">Assign</button>
        <button id="unassign" class="grid-head-button">Unassign Excess</button>
    </div>
    <table class="min-w-full border text-sm">
        <thead>
            <tr>
                <th rowspan="2" class="border px-2 py-1 bg-gray-100">Student</th>
                @foreach($days as $dayLabel)
                    <th colspan="{{ count($periods) }}" class="border px-2 py-1 text-center bg-gray-100">{{ $dayLabel }}</th>
                @endforeach
            </tr>
            <tr>
                @foreach($days as $dayNumber => $dayLabel)
                    @for($i = 1; $i <= count($periods); $i++)
                        <th class="border px-1 py-1 text-center">{{ $i }}</th>
                    @endfor
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($students as $student)
                <tr>
                    <td class="border px-2 py-1 {{ isset($studentsWithConflict[$student->id]) ? 'text-red-500' : '' }}">
                        <a href="{{ url('schedule/grid/student/user_id/' . $student->id) }}">
                            {{ $student->name . ($student->class ? ' (' . $student->class . ')' : '') }}
                        </a>
                    </td>
                    @foreach($days as $dayNumber => $dayLabel)
                        @for($period = 1; $period <= count($periods); $period++)
                            @php
                                $lessons = $studentLessons[$student->id][$dayNumber][$period] ?? [];
                            @endphp
                            <td class="border px-1 py-1 text-center">
                                @foreach($lessons as $lesson)
                                    <div class="mb-0.5 text-white text-xs leading-tight" style="background-color: {{ $lesson->subject->color }}">
                                        {{ $lesson->subject->code }}
                                    </div>
                                @endforeach
                            </td>
                        @endfor
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const versionSelect = document.getElementById('version-select');
    let currentVersion = versionSelect.value;

    versionSelect.addEventListener('change', () => {
        currentVersion = versionSelect.value;
        window.location.href = `/schedule/students/index/version_id/${currentVersion}`;
    });

    const assignBtn = document.getElementById('assign');
    if (assignBtn) {
        assignBtn.addEventListener('click', () => {
            fetch(`/schedule/lesson/assignStudentLessons/version_id/${currentVersion}`)
                .then(() => window.location.reload());
        });
    }

    const unassignBtn = document.getElementById('unassign');
    if (unassignBtn) {
        unassignBtn.addEventListener('click', () => {
            fetch(`/schedule/lesson/unassignStudentLessons/version_id/${currentVersion}`)
                .then(() => window.location.reload());
        });
    }
});
</script>
@endpush

