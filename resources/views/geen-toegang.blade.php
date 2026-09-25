@extends('layouts.app')
@section('titel', 'Geen toegang')
@section('inhoud')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card text-center">
            <div class="card-body py-5">
                <i class="bi bi-shield-lock display-4 text-boels"></i>
                <h2 class="mt-3">Geen toegang tot de Spoedverhuur</h2>
                <p class="text-muted">Je bent ingelogd in Boels CORE als <strong>{{ $gebruiker['name'] ?? '?' }}</strong>, maar je hebt (nog) geen rol in deze app.<br>
                Vraag je beheerder om je in Boels CORE (Beheer → Gebruikers) een rol te geven onder <em>Spoedverhuur</em>.</p>
                <a href="{{ config('core.url') }}" class="btn btn-boels"><i class="bi bi-grid-3x3-gap me-1"></i>Terug naar Boels CORE</a>
                <a href="{{ route('uitloggen') }}" class="btn btn-outline-secondary ms-2">Uitloggen</a>
            </div>
        </div>
    </div>
</div>
@endsection
