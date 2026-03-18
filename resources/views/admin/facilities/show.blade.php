@extends('layouts.admin')

@section('title', 'Facility #'.$facility->id)

@section('content')
<p class="muted"><a href="{{ route('admin.facilities.index') }}">← Facilities</a></p>
<h1 style="margin-top:0;">{{ $facility->name }}</h1>

<div class="card" style="max-width:640px;">
    <table class="data" style="border:none;">
        <tbody>
            <tr><th style="width:140px;">ID</th><td>{{ $facility->id }}</td></tr>
            <tr><th>Country</th><td>{{ $facility->country ?? '—' }}</td></tr>
            <tr><th>Address</th><td class="muted">{{ $facility->address ?? '—' }}</td></tr>
            <tr><th>Join token</th><td><code style="font-size:0.75rem;word-break:break-all;">{{ $facility->join_token }}</code></td></tr>
            <tr><th>Games</th><td>{{ $facility->games_count }}</td></tr>
            <tr><th>Presences</th><td>{{ $facility->presences_count }}</td></tr>
            <tr><th>Created</th><td class="muted">{{ $facility->created_at?->toDateTimeString() }}</td></tr>
        </tbody>
    </table>
</div>
@endsection
