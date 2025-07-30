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

            Column::make('Schedule')
                ->label(fn($row) => '—'), // Placeholder for schedule link

            Column::make('Subjects')
                ->label(fn($row) => $row->subjects->pluck('name')->join(', ')),
            Column::make('Actions')
                ->label(function ($row) {
                    return view('components.table-actions', [
                        'model' => $row,
                        'actions' => [
                            [
                                'label' => 'Assign subjects',
                                'type' => 'link',
                                'href' => "/admin/students/assignSubject/user_id/{$row->id}",
                            ],
                        ],
                    ]);
                }),

        ];
    }
}

