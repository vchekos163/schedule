<?php

namespace App\Livewire\Rooms;

use App\Models\Room;
use App\Models\Subject;
use Livewire\Component;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Contracts\View\View;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class Edit extends Component implements HasForms
{
    use InteractsWithForms;

    public Room $room;
    public array $data = [];
    public array $subject_ids = [];

    public function mount(int $roomId): void
    {
        $this->room = $roomId
            ? Room::with('subjects')->findOrFail($roomId)
            : new Room();

//        $this->subject_ids = $this->room->subjects?->pluck('id')->toArray() ?? [];

        $this->form->fill([
            'name' => $this->room->name ?? '',
            'code' => $this->room->code ?? '',
            'capacity' => $this->room->capacity ?? '',
            'purpose' => $this->room->purpose ?? '',
//            'subject_ids' => $this->subject_ids,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Room Name')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('code')
                ->label('Code')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('capacity')
                ->label('Capacity')
                ->numeric()
                ->minValue(1)
                ->required(),

            Forms\Components\TextInput::make('purpose')
                ->label('Purpose')
                ->required()
                ->maxLength(255),
/*
            Forms\Components\Select::make('subject_ids')
                ->label('Allowed Subjects')
                ->multiple()
                ->preload()
                ->searchable()
                ->options(Subject::pluck('name', 'id'))
                ->required(),
*/
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $roomFata = [
            'name' => $data['name'],
            'capacity' => $data['capacity'],
            'purpose' => $data['purpose'],
        ];

        if (!$this->room->exists) {
            $this->room = Room::create($roomFata);
        } else {
            $this->room->update($roomFata);
        }

//        $this->room->subjects()->sync($data['subject_ids'] ?? []);

        session()->flash('message', 'Room updated successfully.');

        redirect('/admin/rooms');
    }

    public function render(): View
    {
        return view('livewire.rooms.edit');
    }
}
