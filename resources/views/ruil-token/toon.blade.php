@extends('layouts.publiek')
@section('titel', $actie === 'bevestig' ? 'Ruilverzoek bevestigen' : 'Ruilverzoek afwijzen')
@section('inhoud')
<div class="card">
    <div class="card-header">
        <i class="bi bi-arrow-left-right me-2 text-boels"></i>{{ $actie === 'bevestig' ? 'Ruilverzoek bevestigen' : 'Ruilverzoek afwijzen' }}
    </div>
    <div class="card-body">
        <p class="mb-3">Beste {{ $ontvanger?->naam ?? 'collega' }}, <strong>{{ $r->aangevraagdDoor?->naam ?? 'een collega' }}</strong> heeft je een ruilverzoek gestuurd. Controleer de gegevens en {{ $actie === 'bevestig' ? 'bevestig met de knop hieronder' : 'wijs het af met de knop hieronder' }}.</p>
        <div class="alert alert-light border mb-3"><strong>{{ $omschrijving }}</strong></div>
        <table class="table table-sm samenvatting mb-4">
            @foreach($regels as $regel)
                <tr><th>{{ $regel[0] }}</th><td>{!! nl2br(e($regel[1])) !!}</td></tr>
            @endforeach
        </table>

        <form method="post" action="{{ route('ruil.token.verwerk', ['token' => $r->token, 'actie' => $actie]) }}">
            @csrf
            @if($actie === 'bevestig')
                <div class="d-grid">
                    <button class="btn btn-success btn-lg py-3"><i class="bi bi-check-lg me-2"></i>Ja, ik bevestig deze ruiling</button>
                </div>
                <p class="small text-muted mt-3 mb-0">Na bevestiging wordt het rooster direct aangepast en krijgen jullie beiden een mail. Wil je toch niet? <a href="{{ route('ruil.token', ['token' => $r->token, 'actie' => 'afwijs']) }}">Verzoek afwijzen</a>.</p>
            @else
                <div class="mb-3">
                    <label class="form-label">Reden (optioneel, gaat mee in de mail naar {{ $r->aangevraagdDoor?->naam ?? 'de aanvrager' }})</label>
                    <textarea name="reden" class="form-control" rows="3" maxlength="500"></textarea>
                </div>
                <div class="d-grid">
                    <button class="btn btn-danger btn-lg py-3"><i class="bi bi-x-lg me-2"></i>Nee, ik wijs dit verzoek af</button>
                </div>
                <p class="small text-muted mt-3 mb-0">Toch akkoord? <a href="{{ route('ruil.token', ['token' => $r->token, 'actie' => 'bevestig']) }}">Verzoek bevestigen</a>.</p>
            @endif
        </form>
    </div>
</div>
@endsection
