<?php

namespace App\Livewire\Students;

use App\Models\User;
use App\Models\Subject;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class AssignSubject extends Component implements HasForms
{
    use InteractsWithForms;

    public ?User $user;

    public array $subject_ids = [];

    public function mount(int $userId): void
    {
        $this->user = User::with('subjects')->findOrFail($userId);

        // Pre-fill selected subjects if user exists
        $this->subject_ids = $this->user->subjects->pluck('id')->toArray();

        $this->form->fill([
            'subject_ids' => $this->subject_ids,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema($this->getFormSchema());
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Select::make('subject_ids')
                ->label('Subject')
                ->options(Subject::pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload(),
        ];
    }

    public function save(): void
    {
        if (!$this->user->exists) {
            session()->flash('error', 'User does not exist.');
            return;
        }

        $this->user->subjects()->sync($this->subject_ids);

        session()->flash('message', 'Subject assigned successfully.');
        redirect('/admin/students');
    }

    public function render(): View
    {
        return view('livewire.students.assign-subject');
    }
}
