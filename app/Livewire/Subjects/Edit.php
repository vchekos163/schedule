<?php

namespace App\Livewire\Subjects;

use App\Models\Subject;
use App\Models\User;
use App\Models\Teacher;
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
            ? Subject::with('teachers.user')->findOrFail($subjectId)
            : new Subject();

        $this->form->fill([
            'name'     => $this->subject->name ?? '',
            'code'     => $this->subject->code ?? '',
            'priority' => $this->subject->priority ?? 'must',
            'color'    => $this->subject->color ?? '',
            'teachers' => $this->subject->exists
                ? $this->subject->teachers->map(fn ($t) => [
                    'teacher_id' => $t->id,
                    'quantity'   => $t->pivot->quantity ?? 1,
                ])->toArray()
                : [],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Subject Name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->nullable(),

            Forms\Components\Select::make('priority')
                ->label('Priority')
                ->options(Subject::getPriority())
                ->searchable()
                ->required(),

            Forms\Components\ColorPicker::make('color')
                ->label('Color'),

            Forms\Components\Repeater::make('teachers')
                ->label('Assigned Teachers')
                ->schema([
                    Forms\Components\Select::make('teacher_id')
                        ->label('Teacher')
                        ->options(
                            Teacher::with('user')
                                ->get()
                                ->mapWithKeys(fn ($t) => [$t->id => $t->user?->name ?? "Teacher #{$t->id}"])
                        )
                        ->required()
                        ->searchable()
                        ->preload(),

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

        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $subjectData = [
            'name' => $data['name'],
            'code' => $data['code'],
            'priority' => $data['priority'],
            'color' => $data['color'],
        ];

        if (!$this->subject->exists) {
            $this->subject = Subject::create($subjectData);
        } else {
            $this->subject->update($subjectData);
        }

        $sync = collect($data['teachers'] ?? [])
            ->filter(fn ($row) => !empty($row['teacher_id']))
            ->mapWithKeys(fn ($row) => [
                (int) $row['teacher_id'] => ['quantity' => max(1, (int) ($row['quantity'] ?? 1))],
            ])
            ->toArray();

        $this->subject->teachers()->sync($sync);

        session()->flash('message', 'Subject updated successfully.');

        redirect('/admin/subjects');
    }

    public function render(): View
    {
        return view('livewire.subjects.edit');
    }
}
