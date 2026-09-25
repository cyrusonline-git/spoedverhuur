@extends('layouts.app')
@section('titel', 'Kies je rol')
@section('inhoud')
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-badge me-2 text-boels"></i>Als welke rol wil je werken?</div>
            <div class="card-body">
                <p class="text-muted small">Je hebt meerdere rollen in de Spoedverhuur. Kies er één; wisselen kan later via het menu rechtsboven.</p>
                <form method="post" action="{{ route('kies-rol.opslaan') }}" class="d-grid gap-2">
                    @csrf
                    @foreach($rollen as $slug)
                        <button type="submit" name="rol" value="{{ $slug }}" class="btn btn-lg {{ $actief === $slug ? 'btn-boels' : 'btn-outline-boels' }} text-start">
                            <i class="bi bi-{{ ['binnendienst' => 'headset', 'werkplaats' => 'wrench-adjustable', 'manager' => 'bar-chart-line', 'fleet' => 'truck', 'admin' => 'gear'][$slug] ?? 'person' }} me-2"></i>{{ rol_naam($slug) }}
                            @if($actief === $slug)<span class="float-end small">huidig</span>@endif
                        </button>
                    @endforeach
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
