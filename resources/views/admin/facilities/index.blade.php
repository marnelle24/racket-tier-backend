@extends('layouts.admin')

@section('title', 'Facilities')

@section('content')
<h1 style="margin-top:0;">Facilities</h1>

<div class="table-wrap">
    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Country</th>
                <th>Games</th>
                <th>Presences</th>
                <th>Created</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($facilities as $facility)
                <tr>
                    <td>{{ $facility->id }}</td>
                    <td>{{ $facility->name }}</td>
                    <td class="muted">{{ $facility->country ?? '—' }}</td>
                    <td>{{ $facility->games_count }}</td>
                    <td>{{ $facility->presences_count }}</td>
                    <td class="muted">{{ $facility->created_at?->format('Y-m-d') }}</td>
                    <td><a href="{{ route('admin.facilities.show', $facility) }}">View</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">{{ $facilities->links() }}</div>
@endsection
