<?php

namespace App\Livewire\Teachers;

use App\Models\Subject;
use App\Models\User;
use App\Models\Teacher;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public Teacher $teacher;
    public User $user;

    /** All form state lives under this bag */
    public array $data = [];

    /** For defaults only (not bound) */
    private array $days    = ['monday','tuesday','wednesday','thursday','friday'];
    private array $periods = [];

    public function mount(int $userId): void
    {
        $this->user = User::findOrFail($userId);
        $this->teacher = Teacher::firstOrNew(['user_id' => $userId]);

        $this->periods = array_keys(config('periods', []));

        // Build initial availability matrix
        $availability = [];
        foreach ($this->days as $day) {
            foreach ($this->periods as $period) {
                $existing = $this->teacher->availability[$day][$period] ?? null;
                $availability[$day][$period] = $existing ?? 'UNAVAILABLE';
            }
        }

        // Fill form
        $this->form->fill([
            'subjects'     => $this->teacher->subjects->map(fn ($s) => [
                'subject_id' => $s->id,
                'quantity'   => $s->pivot->quantity ?? 1,
            ])->toArray(),
            'max_lessons'  => $this->teacher->max_lessons,
            'max_days'  => $this->teacher->max_days,
            'max_gaps'     => $this->teacher->max_gaps,
            'availability' => $availability,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Repeater::make('subjects')
                    ->label('Subjects')
                    ->schema([
                        Forms\Components\Select::make('subject_id')
                            ->label('Subject')
                            ->options(Subject::pluck('code', 'id'))
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

                Forms\Components\Section::make('Availability')->schema([
                    Forms\Components\View::make('livewire.teachers.availability-grid')
                        ->statePath('availability')   // binds to data.availability
                        ->dehydrated(true),           // include in getState()
                ]),

                Forms\Components\TextInput::make('max_lessons')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(7)
                    ->required(),

                Forms\Components\TextInput::make('max_days')
                ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->required(),

                Forms\Components\TextInput::make('max_gaps')
                    ->numeric()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->teacher->user_id     = $this->user->id;
        $this->teacher->max_lessons = $data['max_lessons'] ?? null;
        $this->teacher->max_days = $data['max_days'] ?? null;
        $this->teacher->max_gaps    = $data['max_gaps'] ?? null;

        // Compact availability: drop UNAVAILABLE
        $compact = [];
        foreach (($data['availability'] ?? []) as $day => $hours) {
            foreach ($hours as $hour => $state) {
                if ($state !== 'UNAVAILABLE') {
                    $compact[$day][$hour] = $state;
                }
            }
        }
        $this->teacher->availability = $compact;

        $this->teacher->save();

        $subjectData = collect($data['subjects'] ?? [])
            ->filter(fn ($item) => !empty($item['subject_id']))
            ->mapWithKeys(fn ($item) => [
                $item['subject_id'] => ['quantity' => $item['quantity'] ?? 1],
            ])
            ->toArray();

        $this->teacher->subjects()->sync($subjectData);

        session()->flash('message', 'Teacher updated successfully.');
        redirect('/admin/teachers');
    }

    public function render(): View
    {
        return view('livewire.teachers.edit');
    }
}
