<?php

namespace App\Livewire\Teachers;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Models\User;

class Table extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function builder(): Builder
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'teacher'))
            ->with(['teacher.subjects']) // ← load subjects through Teacher
            ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
            ->select('users.*', 'teachers.availability', 'teachers.max_lessons', 'teachers.max_days', 'teachers.max_gaps');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),

            Column::make('Schedule')
                ->label(fn($row) => view('components.schedule-link', ['link' => '/schedule/index/teacher/teacher_id/'.$row->teacher->id]))
                ->html(),

            Column::make('Subjects')
                ->label(function ($row) {
                    $subjects = $row->teacher->subjects->map(function ($r) {
                        $r->name = $r->code;
                        $r->value = $r->pivot->quantity ?? 1;
                        return $r;
                    });
                    return view('components.dropdown-list', [
                        'label' => 'Subjects',
                        'items' => $subjects,
                    ]);
                })
                ->html(),

            Column::make('Availability')
                ->label(fn($row) => view('components.availability-dropdown', [
                    'availability' => is_array($row->availability)
                        ? $row->availability
                        : json_decode($row->availability, true)
                ]))
                ->html(),

            Column::make('Max Lessons/Day', 'max_lessons')
                ->label(fn ($row) => $row->max_lessons ?? '-'),

            Column::make('Max Days/Week', 'max_days')
                ->label(fn ($row) => $row->max_days ?? '-')
                ->sortable(),

            Column::make('Max Gaps', 'max_gaps')
                ->label(fn ($row) => $row->max_gaps ?? '-'),
/*
            Column::make('Co-Teachers')
                ->label(function ($row) {
                    if (!optional($row->teacher)->coTeachers) return '-';

                    return $row->teacher->coTeachers
                        ->filter(fn($t) => $t->user)
                        ->map(fn($t) => $t->user->name)
                        ->join(', ') ?: '-';
                }),
*/
            Column::make('Actions')
                ->label(function ($row) {
                    return view('components.table-actions', [
                        'model' => $row,
                        'actions' => [
                            [
                                'label' => 'Edit',
                                'type' => 'link',
                                'href' => "/admin/teachers/edit/user_id/{$row->id}",
                            ],
                        ],
                    ]);
                }),
        ];
    }
}

