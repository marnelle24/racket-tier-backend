<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin registration — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: ui-sans-serif, system-ui, sans-serif; background: #0f1419; color: #e6edf3; }
        .box { width: 100%; max-width: 420px; padding: 2rem; background: #1a2332; border: 1px solid #2d3a4d; border-radius: 12px; }
        h1 { margin: 0 0 0.5rem; font-size: 1.25rem; font-weight: 600; }
        p.muted { margin: 0 0 1.25rem; font-size: 0.875rem; color: #8b9cb3; }
        label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; color: #8b9cb3; }
        input[type="text"], input[type="email"], input[type="password"] {
            width: 100%; padding: 0.65rem 0.75rem; margin-bottom: 0.75rem;
            border: 1px solid #2d3a4d; border-radius: 6px; background: #0f1419; color: #e6edf3;
        }
        .err { color: #f87171; font-size: 0.875rem; margin-bottom: 1rem; }
        button { width: 100%; padding: 0.75rem; background: #3b82f6; color: #fff; border: none; border-radius: 6px;
            font-weight: 500; cursor: pointer; }
        button:hover { background: #2563eb; }
        a { color: #93c5fd; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Initial admin setup</h1>
        <p class="muted">Create the first administrator for this installation.</p>

        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('admin.register.post') }}">
            @csrf
            <label for="name">Name</label>
            <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">

            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username">

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">

            <label for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

            <button type="submit">Create admin</button>
        </form>

        <p class="muted" style="margin-top:1rem;">
            Already have an admin? <a href="{{ route('admin.login') }}">Sign in</a>.
        </p>
    </div>
</body>
</html>

