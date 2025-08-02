<?php
namespace App\Livewire\Students;

use App\Models\Subject;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class AssignSubject extends Component implements HasForms
{
    use InteractsWithForms;

    public ?User $user = null;

    public array $data = [];

    public function mount(int $userId): void
    {
        $this->user = User::with('subjects')->findOrFail($userId);

        // Prefill the form from existing pivot data
        $this->data = [
            'subjects' => $this->user->subjects->map(function ($subject) {
                return [
                    'subject_id' => $subject->id,
                    'quantity' => $subject->pivot->quantity ?? 1,
                ];
            })->toArray(),
        ];

        $this->form->fill($this->data);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Repeater::make('subjects')
                ->label('Subjects')
                ->schema([
                    Forms\Components\Select::make('subject_id')
                        ->label('Subject')
                        ->options(Subject::pluck('name', 'id'))
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('quantity')
                        ->label('Quantity')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->required(),
                ])
                ->columns(2)
                ->default([])
                ->reorderable(),
        ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $syncData = collect($data['subjects'] ?? [])
            ->filter(fn ($row) => !empty($row['subject_id']))
            ->mapWithKeys(fn ($row) => [
                $row['subject_id'] => ['quantity' => $row['quantity'] ?? 1],
            ])
            ->toArray();

        $this->user->subjects()->sync($syncData);

        session()->flash('message', 'Subjects and quantities assigned successfully.');
        redirect('/admin/students');
    }

    public function render(): View
    {
        return view('livewire.students.assign-subject');
    }
}
