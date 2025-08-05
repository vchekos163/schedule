<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
use App\Models\User;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public Subject $subject;
    public array $data = [];
    public array $teacher_ids = [];

    public function mount(int $subjectId): void
    {
        $this->subject = $subjectId
            ? Subject::with('users')->findOrFail($subjectId)
            : new Subject();

        $this->teacher_ids = $this->subject->users?->pluck('id')->toArray() ?? [];

        $this->form->fill([
            'name' => $this->subject->name ?? '',
            'description' => $this->subject->description ?? '',
            'teacher_ids' => $this->teacher_ids,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Subject Name')
                ->required()
                ->maxLength(255),

            Forms\Components\Select::make('teacher_ids')
                ->label('Assigned Teachers')
                ->multiple()
                ->preload()
                ->searchable()
                ->options(
                    User::whereHas('roles', fn($q) => $q->where('name', 'teacher'))
                        ->pluck('name', 'id')
                )
                ->required(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $subjectData = [
            'name' => $data['name'],
        ];

        if (!$this->subject->exists) {
            $this->subject = Subject::create($subjectData);
        } else {
            $this->subject->update($subjectData);
        }

        $this->subject->users()->sync($data['teacher_ids'] ?? []);

        session()->flash('message', 'Subject updated successfully.');

        redirect('/admin/subjects');
    }

    public function render(): View
    {
        return view('livewire.subjects.edit');
    }
}
