@extends('layouts.app')

@section('content')
<div class="container mx-auto p-4 overflow-x-auto max-w-full">
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
                        {{ $student->name }}
                    </td>
                    @foreach($days as $dayNumber => $dayLabel)
                        @for($period = 1; $period <= count($periods); $period++)
                            @php
                                $date    = $startDate->copy()->addDays($dayNumber - 1)->toDateString();
                                $lessons = $studentLessons[$student->id][$date][$period] ?? [];
                            @endphp
                            <td class="border px-1 py-1 text-center">
                                @foreach($lessons as $lesson)
                                    <div class="mb-0.5 text-white" style="background-color: {{ $lesson->subject->color }}">
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

