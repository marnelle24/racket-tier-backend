<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        :root { --admin-bg: #0f1419; --admin-surface: #1a2332; --admin-border: #2d3a4d; --admin-text: #e6edf3; --admin-muted: #8b9cb3; --admin-accent: #3b82f6; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: ui-sans-serif, system-ui, sans-serif; background: var(--admin-bg); color: var(--admin-text); min-height: 100vh; }
        a { color: var(--admin-accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-nav { width: 220px; flex-shrink: 0; background: var(--admin-surface); border-right: 1px solid var(--admin-border); padding: 1rem 0; }
        .admin-nav a { display: block; padding: 0.5rem 1.25rem; color: var(--admin-muted); text-decoration: none; }
        .admin-nav a:hover, .admin-nav a.active { color: var(--admin-text); background: rgba(59,130,246,0.1); text-decoration: none; }
        .admin-nav .brand { padding: 0.5rem 1.25rem 1rem; font-weight: 600; color: var(--admin-text); border-bottom: 1px solid var(--admin-border); margin-bottom: 0.5rem; }
        .admin-main { flex: 1; padding: 1.5rem 2rem; overflow-x: auto; }
        .table-wrap { overflow-x: auto; border: 1px solid var(--admin-border); border-radius: 8px; }
        table.data { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        table.data th, table.data td { padding: 0.65rem 1rem; text-align: left; border-bottom: 1px solid var(--admin-border); }
        table.data th { background: var(--admin-surface); color: var(--admin-muted); font-weight: 500; }
        table.data tr:last-child td { border-bottom: none; }
        table.data tbody tr:hover { background: rgba(255,255,255,0.03); }
        .btn { display: inline-block; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.875rem; cursor: pointer; border: none; }
        .btn-primary { background: var(--admin-accent); color: #fff; }
        .btn-ghost { background: transparent; color: var(--admin-muted); border: 1px solid var(--admin-border); }
        .card { background: var(--admin-surface); border: 1px solid var(--admin-border); border-radius: 8px; padding: 1.25rem; }
        .muted { color: var(--admin-muted); font-size: 0.875rem; }
        .flash { padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1rem; background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.4); }
        .error { color: #f87171; font-size: 0.875rem; margin-top: 0.25rem; }
    </style>
</head>
<body>
    <div class="admin-layout">
        <nav class="admin-nav">
            <div class="brand">Admin</div>
            <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
            <a href="{{ route('admin.games.index') }}" class="{{ request()->routeIs('admin.games.*') ? 'active' : '' }}">Games</a>
            <a href="{{ route('admin.facilities.index') }}" class="{{ request()->routeIs('admin.facilities.*') ? 'active' : '' }}">Facilities</a>
            <form method="post" action="{{ route('admin.logout') }}" style="margin-top: 1.5rem; padding: 0 1.25rem;">
                @csrf
                <button type="submit" class="btn btn-ghost" style="width: 100%;">Log out</button>
            </form>
        </nav>
        <main class="admin-main">
            @if (session('status'))
                <div class="flash">{{ session('status') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
