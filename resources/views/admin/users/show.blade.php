@extends('layouts.admin')

@section('title', 'User #'.$user->id)

@section('content')
<p class="muted"><a href="{{ route('admin.users.index') }}">← Users</a></p>
<h1 style="margin-top:0;">{{ $user->name }}</h1>

<div class="card" style="max-width:640px;">
    <table class="data" style="border:none;">
        <tbody>
            <tr><th style="width:140px;">Email</th><td>{{ $user->email }}</td></tr>
            <tr><th>Role</th><td>{{ $user->role }}</td></tr>
            <tr><th>Global rating</th><td>{{ $user->global_rating ?? 0 }}</td></tr>
            <tr><th>Tier</th><td>{{ $user->tier ?? 0 }}</td></tr>
            <tr><th>Primary sport</th><td>{{ $user->primary_sport ?? '—' }}</td></tr>
            <tr><th>Joined</th><td class="muted">{{ $user->created_at?->toDateTimeString() }}</td></tr>
            <tr><th>Games created</th><td>{{ $user->games_created_count }}</td></tr>
            <tr><th>Game participations</th><td>{{ $user->game_participants_count }}</td></tr>
            <tr><th>Facility presences</th><td>{{ $user->facility_presences_count }}</td></tr>
        </tbody>
    </table>
</div>

<h2 style="font-size:1rem;margin-top:2rem;">Change role</h2>
<form method="post" action="{{ route('admin.users.role', $user) }}" class="card" style="max-width:640px;margin-top:0.5rem;">
    @csrf
    @method('PATCH')
    <select name="role" style="padding:0.5rem;margin-right:0.5rem;background:#0f1419;color:var(--admin-text);border:1px solid var(--admin-border);border-radius:6px;">
        <option value="{{ \App\Models\User::ROLE_USER }}" @selected($user->role === \App\Models\User::ROLE_USER)>User</option>
        <option value="{{ \App\Models\User::ROLE_ADMIN }}" @selected($user->role === \App\Models\User::ROLE_ADMIN)>Admin</option>
    </select>
    <button type="submit" class="btn btn-primary">Update role</button>
    @error('role')
        <div class="error">{{ $message }}</div>
    @enderror
</form>
@endsection
