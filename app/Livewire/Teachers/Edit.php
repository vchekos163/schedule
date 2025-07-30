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

    public function mount(int $userId): void
    {
        $this->user = User::findOrFail($userId);

        // Найти или создать запись teacher по user_id
        $this->teacher = Teacher::firstOrNew(['user_id' => $userId]);

        // Заполнить форму начальными значениями
        $this->form->fill([
            'subject_ids' => $this->user->subjects()->pluck('subjects.id')->toArray(),
            'availability' => $this->teacher->availability ?? [],
            'max_lessons' => $this->teacher->max_lessons,
            'max_gaps' => $this->teacher->max_gaps,
            'co_teachers' => $this->teacher->coTeachers()->pluck('co_teacher_teacher.co_teacher_id')->toArray(),
        ]);
    }

    public function form(Form $form): Form
    {

        return $form->schema([
            Forms\Components\Select::make('subject_ids')
                ->label('Subject')
                ->options(Subject::pluck('name', 'id'))
                ->multiple()
                ->searchable()
                ->preload(),
            Forms\Components\Repeater::make('availability')
                ->label('Working Days and Time Slots')
                ->schema([
                    Forms\Components\Select::make('day')
                        ->label('Day')
                        ->options([
                            'monday' => 'Monday',
                            'tuesday' => 'Tuesday',
                            'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday',
                            'friday' => 'Friday',
                            'saturday' => 'Saturday',
                            'sunday' => 'Sunday',
                        ])
                        ->required(),
                    Forms\Components\TextInput::make('slots')
                        ->label('Time Slots (e.g. 09:00-12:00, 14:00-16:00)')
                        ->required(),
                ])
                ->default([])
                ->reorderable(),

            Forms\Components\TextInput::make('max_lessons')
                ->numeric()
                ->required(),

            Forms\Components\TextInput::make('max_gaps')
                ->numeric()
                ->required(),

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
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $this->teacher->user_id = $this->user->id;
        $this->teacher->availability = $data['availability'];
        $this->teacher->max_lessons = $data['max_lessons'];
        $this->teacher->max_gaps = $data['max_gaps'];
        $this->teacher->save();

        $this->user->subjects()->sync($data['subject_ids'] ?? []);

        // Создаём записи в teachers, если co-teachers ещё не существуют
        $coTeacherIds = collect($data['co_teachers'] ?? [])
            ->map(function ($userId) {
                return \App\Models\Teacher::firstOrCreate(['user_id' => $userId])->id;
            })
            ->toArray();

        // Сохраняем связи co-teachers
        $this->teacher->coTeachers()->sync($coTeacherIds);

        session()->flash('message', 'Teacher updated successfully.');
        redirect('/admin/teachers');
    }

    public function render(): View
    {
        return view('livewire.teachers.edit');
    }
}
