@extends('layouts.admin')

@section('title', 'Games')

@section('content')
<h1 style="margin-top:0;">Games</h1>

<div class="table-wrap">
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
            @foreach ($games as $game)
                <tr>
                    <td>{{ $game->id }}</td>
                    <td>{{ $game->status }}</td>
                    <td>{{ $game->sport ?? '—' }}</td>
                    <td>{{ $game->facility?->name ?? '—' }}</td>
                    <td class="muted">{{ $game->creator?->email ?? '—' }}</td>
                    <td class="muted">{{ $game->updated_at?->format('Y-m-d H:i') }}</td>
                    <td><a href="{{ route('admin.games.show', $game) }}">View</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">{{ $games->links() }}</div>
@endsection
