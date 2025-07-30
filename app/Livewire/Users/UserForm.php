<?php

namespace App\Livewire\Users;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Spatie\Permission\Models\Role;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class UserForm extends Component implements HasForms
{
    use InteractsWithForms;

    public ?User $user;
    public array $data = [];

    public function mount(int $userId = 0): void
    {
        $this->user = $userId ? User::findOrFail($userId) : new User();

        $this->data = [
            'name' => $this->user->name ?? '',
            'email' => $this->user->email ?? '',
            'password' => '',
            'roles' => $this->user->roles()->pluck('id')->toArray(),
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form
            ->statePath('data') // ключевой момент!
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->minLength(6)
                    ->maxLength(255)
                    ->dehydrateStateUsing(fn ($state) => $state ? bcrypt($state) : null)
                    ->required(fn () => !$this->user->exists),

                Forms\Components\Select::make('roles')
                    ->label('Roles')
                    ->multiple()
                    ->options(fn () => Role::pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Проверка уникальности email
        $existing = User::where('email', $data['email'])->first();
        if (!$this->user->exists && $existing) {
            session()->flash('error', 'User with this email already exists.');
            return;
        }

        // Создание или обновление
        if (!$this->user->exists) {
            $this->user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        } else {
            $update = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (!empty($data['password'])) {
                $update['password'] = $data['password'];
            }

            $this->user->update($update);
        }

        // Привязка ролей
        $roleNames = Role::whereIn('id', $data['roles'] ?? [])->pluck('name')->toArray();
        $this->user->syncRoles($roleNames);

        session()->flash('message', 'User successfully created.');
        redirect('/admin/users');
    }

    public function render(): View
    {
        return view('livewire.users.user-form');
    }
}
