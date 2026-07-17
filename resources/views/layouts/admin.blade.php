<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'PflegeIndex Verwaltung')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/styles.css') }}?v=20260715-2">
    <link rel="stylesheet" href="{{ asset('assets/admin.css') }}?v=20260715-3">
</head>
<body class="admin-body">
    @auth
        <header class="admin-header">
            <div class="container admin-header__inner">
                <a class="admin-brand" href="{{ route('admin.dashboard') }}"><img src="{{ asset('logo-light.svg') }}" alt="PflegeIndex"><span>Verwaltung</span></a>
                <nav class="admin-nav" aria-label="Verwaltung">
                    <a href="{{ route('admin.facilities.index') }}">Einrichtungen</a>
                    <a href="{{ route('admin.suggestions.index') }}">Kontaktprüfung</a>
                    <a href="{{ route('admin.password.edit') }}">Passwort</a>
                    <a href="{{ route('home') }}" target="_blank" rel="noopener">Website öffnen</a>
                    <form method="post" action="{{ route('admin.logout') }}">@csrf<button type="submit">Abmelden</button></form>
                </nav>
            </div>
        </header>
    @endauth

    @yield('content')
</body>
</html>
