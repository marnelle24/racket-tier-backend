@extends('layouts.admin')

@section('title', 'Game #'.$game->id)

@section('content')
<p class="muted"><a href="{{ route('admin.games.index') }}">← Games</a></p>
<h1 style="margin-top:0;">Game #{{ $game->id }}</h1>

<div class="card" style="max-width:720px;">
    <table class="data" style="border:none;">
        <tbody>
            <tr><th style="width:160px;">Status</th><td>{{ $game->status }}</td></tr>
            <tr><th>Sport</th><td>{{ $game->sport ?? '—' }}</td></tr>
            <tr><th>Match type</th><td>{{ $game->match_type ?? '—' }}</td></tr>
            <tr><th>Score</th><td>{{ $game->score ?? '—' }}</td></tr>
            <tr><th>Facility</th><td>{{ $game->facility?->name ?? '—' }} @if($game->facility) (ID {{ $game->facility_id }}) @endif</td></tr>
            <tr><th>Creator</th><td>{{ $game->creator?->email ?? '—' }}</td></tr>
            <tr><th>Start / End</th><td class="muted">{{ $game->start_time?->toDateTimeString() ?? '—' }} → {{ $game->end_time?->toDateTimeString() ?? '—' }}</td></tr>
            <tr><th>Stats applied</th><td class="muted">{{ $game->stats_applied_at?->toDateTimeString() ?? '—' }}</td></tr>
        </tbody>
    </table>
</div>

<h2 style="font-size:1rem;margin-top:2rem;">Participants</h2>
<div class="table-wrap" style="margin-top:0.5rem;">
    <table class="data">
        <thead>
            <tr>
                <th>User</th>
                <th>Result</th>
                <th>Invitation</th>
                <th>Confirmed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($game->participants as $p)
                <tr>
                    <td>{{ $p->user?->email ?? 'User #'.$p->user_id }}</td>
                    <td class="muted">{{ $p->result ?? '—' }}</td>
                    <td class="muted">{{ $p->invitation_responded_at?->toDateTimeString() ?? '—' }}</td>
                    <td class="muted">{{ $p->confirmed_at?->toDateTimeString() ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">No participants.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
