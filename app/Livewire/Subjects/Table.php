<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class Table extends DataTableComponent
{
    public function configure(): void
    {
        $this->setPrimaryKey('id')
            ->setBulkActions([
                'bulkDelete' => 'Delete Selected',
            ])
            ->setConfigurableAreas([
                'toolbar-left-start',
            ]);
    }

    public function builder(): Builder
    {
        return Subject::query()
            ->with('teachers.user', 'rooms')
            ->select('subjects.*');
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')
                ->label(function ($row) {
                    $color = $row->color ?? '#999';

                    return <<<HTML
            <div class="flex items-center gap-2">
                <span class="w-4 h-4 rounded-full inline-block" style="background-color: {$color};"></span>
                <span>{$row->name}</span>
            </div>
        HTML;
                })
                ->html()
                ->sortable()
                ->searchable(),

            Column::make('Code', 'code')
                ->sortable()
                ->searchable(),

            Column::make('Priority', 'priority')
                ->label(function ($row) {
                    return Subject::getPriority()[$row->priority] ?? ucfirst($row->priority);
                })
                ->sortable()
                ->searchable(),

            Column::make('Rooms')
                ->label(function ($row) {
                    $rooms = $row->rooms->map(function ($r) {
                        $r->name = $r->code;
                        $r->value = $r->pivot->priority ?? 1;
                        return $r;
                    });

                    return view('components.dropdown-list', [
                        'items' => $rooms,
                        'label' => 'Rooms',
                    ]);
                })
                ->html(),

            Column::make('Assigned Teachers')
                ->label(function ($row) {
                    $teachers = $row->teachers->filter(fn ($t) => $t->user)
                        ->map(function ($t) {
                            $t->name = $t->user->name;
                            $t->value = $t->pivot->quantity ?? 1;
                            return $t;
                        });

                    return view('components.dropdown-list', [
                        'items' => $teachers,
                        'label' => 'Teachers',
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
                                'href' => "/admin/subjects/edit/subject_id/{$row->id}",
                            ],
                            [
                                'label' => 'Delete',
                                'type' => 'button',
                                'wire' => "delete({$row->id})",
                                'danger' => true,
                                'confirm' => 'Delete this user?',
                            ],
                        ],
                    ]);
                }),

        ];
    }

    public function bulkDelete(): void
    {
        Subject::whereIn('id', $this->getSelected())->delete();
        $this->clearSelected();
        session()->flash('message', 'Selected subjects have been deleted.');
    }

    public function delete($id): void
    {
        Subject::findOrFail($id)->delete();
        session()->flash('message', 'Subject deleted successfully.');
    }
}
