<?php

namespace App\Livewire\Students;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class Table extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setConfigurableAreas([
                'toolbar-left-start',
            ]);
    }

    public function builder(): Builder
    {
        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'student'))
            ->with('subjects')
            ->select('users.*');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Class', 'class')
                ->sortable()
                ->searchable(),

            Column::make('Schedule')
                ->label(fn($row) => view('components.schedule-link', ['link' => 'schedule/grid/student/user_id/'.$row->id]))
                ->html(), // Ensure HTML rendering

            Column::make('Subjects')
                ->label(function ($row) {
                    $subjects = $row->subjects->map(function ($r) {
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

            Column::make('Actions')
                ->label(function ($row) {
                    return view('components.table-actions', [
                        'model' => $row,
                        'actions' => [
                            [
                                'label' => 'Edit',
                                'type' => 'link',
                                'href' => "/admin/students/assignSubject/user_id/{$row->id}",
                            ],
                        ],
                    ]);
                }),
        ];
    }
}

