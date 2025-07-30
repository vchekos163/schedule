@extends('layouts.app')

@section('content')
    <div class="max-w-4xl mx-auto py-12 px-6 text-center">
        {{-- Заголовок --}}
        <h1 class="text-4xl font-bold text-gray-800 dark:text-white mb-4">Welcome to Forvardas: AI Schedule App!</h1>

        {{-- Авторизованный пользователь --}}
        @auth
            <div class="bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 p-4 rounded shadow">
                You logged in as
                <strong>{{ auth()->user()->name }}</strong>
                with roles:
                @php $roles = auth()->user()->getRoleNames(); @endphp
                @if ($roles->isNotEmpty())
                    ({{ $roles->implode(', ') }})
                @endif
            </div>
        @else
            {{-- Кнопка входа --}}
            <a href="{{ route('login') }}"
               class="inline-block mt-6 px-6 py-3 bg-blue-600 text-white font-semibold rounded hover:bg-blue-700 transition">
                Войти
            </a>
        @endauth
    </div>
@endsection

