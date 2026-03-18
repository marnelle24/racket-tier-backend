@extends('layouts.admin')

@section('title', 'Users')

@section('content')
<h1 style="margin-top:0;">Users</h1>

<form method="get" action="{{ route('admin.users.index') }}" style="margin-bottom:1rem;display:flex;gap:0.5rem;max-width:420px;">
    <input type="search" name="q" value="{{ $q }}" placeholder="Search name or email…"
        style="flex:1;padding:0.5rem 0.75rem;border:1px solid var(--admin-border);border-radius:6px;background:#0f1419;color:var(--admin-text);">
    <button type="submit" class="btn btn-primary">Search</button>
</form>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Rating / Tier</th>
                <th>Joined</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($users as $user)
                <tr>
                    <td>{{ $user->id }}</td>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td><span class="muted">{{ $user->role }}</span></td>
                    <td class="muted">{{ $user->global_rating ?? 0 }} / {{ $user->tier ?? 0 }}</td>
                    <td class="muted">{{ $user->created_at?->format('Y-m-d') }}</td>
                    <td><a href="{{ route('admin.users.show', $user) }}">View</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">{{ $users->links() }}</div>
@endsection
