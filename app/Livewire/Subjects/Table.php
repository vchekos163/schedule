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
            ->with('users')
            ->select('subjects.*');;
    }

    public function columns(): array
    {
        return [
            Column::make('Name', 'name')
                ->sortable()
                ->searchable(),

            Column::make('Assigned Teachers')
                ->label(function ($row) {
                    $teachers = $row->users->filter(fn ($user) => $user->hasRole('teacher'));

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
