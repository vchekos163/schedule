<?php

namespace App\Livewire\Lessons;

use App\Models\Lesson;
use App\Models\Room;
use App\Models\Subject;
use App\Models\User;
use App\Models\Teacher;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Carbon\Carbon;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public ?User $user = null;
    public array $data = [];
    public ?int $editingLessonId = null;

    public function mount(int $userId): void
    {
        $this->user = User::findOrFail($userId);

        $this->form->fill([
            'subject_id' => null,
            'room_id' => null,
            'teacher_ids' => [],
            'date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '10:00', // optional, not used in loop
            'quantity' => 1,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('subject_id')
                ->label('Subject')
                ->options(Subject::pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload()
                ->live(), // 👈 so updatedDataSubjectId() triggers

            Forms\Components\Select::make('room_id')
                ->label('Room')
                ->options(Room::pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\Select::make('teacher_ids')
                ->label('Teachers')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(User::role('teacher')->pluck('name', 'id'))
                ->required(),

            Forms\Components\TextInput::make('quantity')
                ->label('Quantity')
                ->numeric()
                ->minValue(1)
                ->required()
                ->live(), // in case you want to react to this

            Forms\Components\DatePicker::make('date')
                ->label('Date')
                ->required(),

            Forms\Components\TimePicker::make('start_time')
                ->label('Start Time')
                ->required()
                ->seconds(false)
                ->step(900)
                ->timezone('Europe/Vilnius')
        ])->statePath('data');
    }

    public function updatedDataSubjectId($value): void
    {
        if (!$value || !$this->user) return;

        $pivot = $this->user
            ->subjects()
            ->where('subject_id', $value)
            ->first();

        $this->data['quantity'] = $pivot?->pivot->quantity ?? 1;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $startTime = Carbon::parse($data['start_time']);
        $endTime = $startTime->copy()->addMinutes(45);
        $quantity = (int) $data['quantity'];

        for ($i = 0; $i < $quantity; $i++) {
            $lesson = Lesson::create([
                'subject_id' => $data['subject_id'],
                'room_id' => $data['room_id'],
                'date' => $data['date'],
                'start_time' => $startTime->format('H:i'),
                'end_time' => $endTime->format('H:i'),
            ]);

            $lesson->users()->attach($this->user->id);

            $teacherIds = collect($data['teacher_ids'] ?? [])
                ->map(fn($userId) => Teacher::firstOrCreate(['user_id' => $userId])->id)
                ->toArray();

            $lesson->teachers()->sync($teacherIds);

            $startTime->addMinutes(45);
            $endTime->addMinutes(45);
        }

        session()->flash('message', "{$quantity} lesson(s) created successfully.");
        $this->redirect(request()->header('Referer', '/'), navigate: true);
    }

    public function render()
    {
        return view('livewire.lessons.edit');
    }
}
