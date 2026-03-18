<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin login — {{ config('app.name') }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            font-family: ui-sans-serif, system-ui, sans-serif; background: #0f1419; color: #e6edf3; }
        .box { width: 100%; max-width: 400px; padding: 2rem; background: #1a2332; border: 1px solid #2d3a4d; border-radius: 12px; }
        h1 { margin: 0 0 1.5rem; font-size: 1.25rem; font-weight: 600; }
        label { display: block; margin-bottom: 0.35rem; font-size: 0.875rem; color: #8b9cb3; }
        input[type="email"], input[type="password"] { width: 100%; padding: 0.65rem 0.75rem; margin-bottom: 1rem;
            border: 1px solid #2d3a4d; border-radius: 6px; background: #0f1419; color: #e6edf3; }
        .err { color: #f87171; font-size: 0.875rem; margin-bottom: 1rem; }
        button { width: 100%; padding: 0.75rem; background: #3b82f6; color: #fff; border: none; border-radius: 6px;
            font-weight: 500; cursor: pointer; }
        button:hover { background: #2563eb; }
        .remember { display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; font-size: 0.875rem; color: #8b9cb3; }
    </style>
</head>
<body>
    <div class="box">
        <h1>Admin sign in</h1>
        @if ($errors->any())
            <div class="err">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('admin.login.post') }}">
            @csrf
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">

            <label for="password">Password</label>
            <input id="password" type="password" name="password" required autocomplete="current-password">

            <label class="remember">
                <input type="checkbox" name="remember" value="1"> Remember me
            </label>

            <button type="submit">Sign in</button>
        </form>
    </div>
</body>
</html>
