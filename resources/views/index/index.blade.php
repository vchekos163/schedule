@extends('layouts.app') {{-- or your custom layout --}}

@section('content')
    <div class="container">
        <h1>Добро пожаловать!</h1>
        <p>Это главная страница — IndexController@index</p>

        @auth
            <div class="alert alert-success">
                Вы вошли как {{ auth()->user()->name }} ({{ auth()->user()->getRoleNames()->implode(', ') }})
            </div>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary">Войти</a>
        @endauth
    </div>
@endsection
