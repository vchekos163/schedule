@extends('layouts.app')

@section('content')
    <div class="max-w-screen-xl mx-auto px-4 py-6">

        {{-- Page Title --}}
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Teachers</h1>

            <a href="/schedule/grid/teachers" class="grid-head-button">
                Full schedule
            </a>
        </div>

        {{-- Flash message --}}
        @if (session()->has('message'))
            <div class="mb-4 p-4 rounded bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                {{ session('message') }}
            </div>
        @endif


        {{-- Livewire Table --}}
        @livewire('teachers.table')

    </div>
@endsection
