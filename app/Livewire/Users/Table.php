<?php

namespace App\Livewire\Users;

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
                'toolbar-left-start'
            ]);
    }

    public function builder(): Builder
    {
        // Eager-load roles for performance
        return User::with('roles')->select('users.*');
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')->sortable(),
            Column::make('Name', 'name')->searchable()->sortable(),
            Column::make('Email', 'email')->searchable(),
            Column::make('Role')
                ->label(fn($row) => $row->roles->pluck('name')->join(', '))
                ->sortable(function (Builder $query, $direction) {
                    // Optional: sort by role name using join
                    return $query->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                        ->orderBy('roles.name', $direction)
                        ->select('users.*');
                }),
            Column::make('Created At', 'created_at')->sortable(),
            Column::make('Actions')
                ->label(function ($row) {
                    return view('components.table-actions', [
                        'model' => $row,
                        'actions' => [
                            [
                                'label' => 'Edit',
                                'type' => 'link',
                                'href' => "/admin/users/edit/user_id/{$row->id}",
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
        User::whereIn('id', $this->getSelected())->delete();
        $this->clearSelected();
        session()->flash('message', 'Selected users have been deleted.');
    }

    public function delete($id): void
    {
        User::findOrFail($id)->delete();
        session()->flash('message', 'User deleted successfully.');
    }
}
