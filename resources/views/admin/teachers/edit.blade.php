@extends('layouts.app')

@section('content')
    <div class="max-w-screen-xl mx-auto px-4 py-6">

        {{-- Livewire Table --}}
        @livewire('teachers.edit', ['userId' => $user->id])

    </div>
@endsection
