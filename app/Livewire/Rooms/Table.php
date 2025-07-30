<?php

namespace App\Livewire\Rooms;

use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class Table extends DataTableComponent
{
    public function builder(): Builder
    {
        return Room::query()
            ->with('subjects')
            ->select('rooms.*');
    }

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        return [
            Column::make('Room Name', 'name')->sortable()->searchable(),
            Column::make('Capacity', 'capacity')->sortable(),
            Column::make('Purpose', 'purpose')->sortable()->searchable(),

            Column::make('Subjects')
                ->label(fn($row) => $row->subjects->pluck('name')->join(', ') ?: '-'),

            Column::make('Actions')
                ->label(function ($row) {
                    return view('components.table-actions', [
                        'model' => $row,
                        'actions' => [
                            [
                                'label' => 'Edit',
                                'type' => 'link',
                                'href' => "/admin/rooms/edit/room_id/{$row->id}",
                            ],
                        ],
                    ]);
                }),
        ];
    }
}
