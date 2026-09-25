@extends('layouts.publiek')
@section('titel', $titel)
@section('inhoud')
<div class="card text-center">
    <div class="card-body py-5">
        <div class="display-3 text-warning mb-2"><i class="bi bi-link-45deg"></i></div>
        <h1 class="h3 mb-3">{{ $titel }}</h1>
        <p class="mb-4">{{ $tekst }}</p>
        <a href="{{ rtrim(config('app.url'), '/') }}/ruilen" class="btn btn-boels">Naar de app (inloggen via Boels CORE)</a>
    </div>
</div>
@endsection
