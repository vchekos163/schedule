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
            ->with(['subjects', 'teacher']) // если нужно всё вместе teacher.coTeachers.user
            ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
            ->select('users.*', 'teachers.availability', 'teachers.max_lessons', 'teachers.max_gaps');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')->sortable()->searchable(),

            Column::make('Subjects')
                ->label(fn($row) => view('components.subjects-dropdown', ['subjects' => $row->subjects]))
                ->html(),

            Column::make('Availability')
                ->label(fn($row) => view('components.availability-dropdown', [
                    'availability' => is_array($row->availability)
                        ? $row->availability
                        : json_decode($row->availability, true) // for JSON storage
                ]))
                ->html(),

            Column::make('Max Lessons/Day', 'max_lessons')
                ->label(fn ($row) => $row->max_lessons ?? '-'),

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

