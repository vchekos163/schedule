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
    public array $data = [];
    public array $availability = [];
    private array $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
    private array $hours = [9, 10, 11, 12, 13, 14, 15, 16];

    public function mount(int $userId): void
    {
        $this->user = User::findOrFail($userId);

        // Найти или создать запись teacher по user_id
        $this->teacher = Teacher::firstOrNew(['user_id' => $userId]);

        foreach ($this->days as $day) {
            foreach ($this->hours as $hour) {
                $existing = $this->teacher->availability[$day][$hour] ?? null;
                $this->availability[$day][$hour] = $existing ?? 'UNAVAILABLE';
            }
        }

        // Заполнить форму начальными значениями
        $this->form->fill([
            'subjects' => $this->teacher->subjects->map(fn($subject) => [
                'subject_id' => $subject->id,
                'quantity' => $subject->pivot->quantity ?? 1,
            ])->toArray(),
            'max_lessons' => $this->teacher->max_lessons,
            'max_gaps' => $this->teacher->max_gaps,
//            'co_teachers' => $this->teacher->coTeachers()->pluck('co_teacher_teacher.co_teacher_id')->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {

        return $form->schema([
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

            Forms\Components\TextInput::make('max_lessons')
                ->numeric()
                ->required(),

            Forms\Components\TextInput::make('max_gaps')
                ->numeric()
                ->required(),
/*
            Forms\Components\Select::make('co_teachers')
                ->label('Co-Teachers')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(
                    User::role('teacher')
                        ->where('id', '!=', $this->user->id)
                        ->get()
                        ->mapWithKeys(fn($user) => [$user->id => $user->name])
                ),
*/
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->teacher->user_id = $this->user->id;
        $this->teacher->max_lessons = $data['max_lessons'];
        $this->teacher->max_gaps = $data['max_gaps'];

        $availability = [];
        foreach ($this->availability as $day => $hours) {
            foreach ($hours as $hour => $state) {
                if ($state !== 'UNAVAILABLE') {
                    $availability[$day][$hour] = $state;
                }
            }
        }
        $this->teacher->availability = $availability;

        $this->teacher->save();

        $subjectData = collect($data['subjects'] ?? [])
            ->filter(fn ($item) => !empty($item['subject_id']))
            ->mapWithKeys(fn ($item) => [
                $item['subject_id'] => ['quantity' => $item['quantity'] ?? 1],
            ])
            ->toArray();

        $this->teacher->subjects()->sync($subjectData);
/*
        // Создаём записи в teachers, если co-teachers ещё не существуют
        $coTeacherIds = collect($data['co_teachers'] ?? [])
            ->map(function ($userId) {
                return \App\Models\Teacher::firstOrCreate(['user_id' => $userId])->id;
            })
            ->toArray();

        // Сохраняем связи co-teachers
        $this->teacher->coTeachers()->sync($coTeacherIds);
*/
        session()->flash('message', 'Teacher updated successfully.');
        redirect('/admin/teachers');
    }

    public function render(): View
    {
        return view('livewire.teachers.edit');
    }
}
