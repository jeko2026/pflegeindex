@extends('layouts.admin')

@section('title', 'Anmelden – PflegeIndex Verwaltung')

@section('content')
    <main class="admin-login">
        <img src="{{ asset('logo.svg') }}" alt="PflegeIndex">
        <h1>Verwaltung</h1>
        <p>Mit Ihrem Administratorkonto anmelden.</p>

        @if($errors->any())
            <div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <form method="post" action="{{ route('admin.login') }}">
            @csrf
            <div class="admin-field"><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus></div>
            <div class="admin-field"><label for="password">Passwort</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
            <button class="primary-button" type="submit">Anmelden</button>
        </form>
    </main>
@endsection
