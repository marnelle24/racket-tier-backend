@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<h1 style="margin-top:0;">Dashboard</h1>
<p class="muted">Overview of your app.</p>

<div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 1rem; margin: 1.5rem 0;">
    <div class="card">
        <div class="muted">Users</div>
        <div style="font-size:1.75rem;font-weight:600;">{{ $userCount }}</div>
    </div>
    <div class="card">
        <div class="muted">Admins</div>
        <div style="font-size:1.75rem;font-weight:600;">{{ $adminCount }}</div>
    </div>
    <div class="card">
        <div class="muted">Games</div>
        <div style="font-size:1.75rem;font-weight:600;">{{ $gameCount }}</div>
    </div>
    <div class="card">
        <div class="muted">Facilities</div>
        <div style="font-size:1.75rem;font-weight:600;">{{ $facilityCount }}</div>
    </div>
</div>

<h2 style="font-size:1rem;margin-top:2rem;">Recent games</h2>
<div class="table-wrap" style="margin-top:0.5rem;">
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Status</th>
                <th>Sport</th>
                <th>Facility</th>
                <th>Creator</th>
                <th>Updated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($recentGames as $game)
                <tr>
                    <td>{{ $game->id }}</td>
                    <td>{{ $game->status }}</td>
                    <td>{{ $game->sport ?? '—' }}</td>
                    <td>{{ $game->facility?->name ?? '—' }}</td>
                    <td>{{ $game->creator?->email ?? '—' }}</td>
                    <td class="muted">{{ $game->updated_at?->diffForHumans() }}</td>
                    <td><a href="{{ route('admin.games.show', $game) }}">View</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No games yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
