@extends('layouts.admin')

@section('title', 'Passwort ändern – PflegeIndex Verwaltung')

@section('content')
    <main class="container admin-main">
        <div class="admin-title"><div><h1>Passwort ändern</h1><p>Verwenden Sie mindestens 12 Zeichen sowie Groß- und Kleinbuchstaben und Zahlen.</p></div></div>

        @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <form class="admin-form" method="post" action="{{ route('admin.password.update') }}">
            @csrf
            @method('put')
            <div class="admin-form__grid">
                <div class="admin-field admin-field--wide"><label for="current_password">Aktuelles Passwort</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required></div>
                <div class="admin-field"><label for="password">Neues Passwort</label><input id="password" name="password" type="password" autocomplete="new-password" required></div>
                <div class="admin-field"><label for="password_confirmation">Neues Passwort wiederholen</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div>
            </div>
            <div class="admin-actions"><button class="primary-button" type="submit">Passwort speichern</button></div>
        </form>
    </main>
@endsection
