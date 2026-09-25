@extends('layouts.publiek')
@section('titel', $titel)
@section('inhoud')
<div class="card text-center">
    <div class="card-body py-5">
        <div class="display-3 {{ $bevestigd ? 'text-success' : 'text-danger' }} mb-2"><i class="bi bi-{{ $bevestigd ? 'check-circle-fill' : 'x-circle-fill' }}"></i></div>
        <h1 class="h3 mb-3">{{ $titel }}</h1>
        <p class="text-muted mb-3">{{ $omschrijving }}</p>
        <p class="mb-4">{{ $tekst }}</p>
        <a href="{{ rtrim(config('app.url'), '/') }}/ruilen" class="btn btn-boels">Naar de app</a>
        <p class="small text-muted mt-4 mb-0">Je kunt dit venster sluiten.</p>
    </div>
</div>
@endsection
