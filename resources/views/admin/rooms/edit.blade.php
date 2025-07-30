@extends('layouts.app')

@section('content')
    <div class="max-w-screen-xl mx-auto px-4 py-6">

        {{-- Livewire Table --}}
        @livewire('rooms.edit', ['roomId' => $room->id ?? 0])

    </div>
@endsection
