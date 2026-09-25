@extends('layouts.app')
@section('titel', 'Ruilen')
@section('inhoud')
<div class="d-flex flex-wrap justify-content-between align-items-center page-header mb-3 gap-2">
    <div>
        <h1><i class="bi bi-arrow-left-right me-2 text-boels"></i>Ruilen</h1>
        <p>Dienst ruilen, laten overnemen of een paar dagen overdragen. De collega bevestigt in de app of via de link in de mail; daarna wordt het rooster automatisch aangepast.</p>
    </div>
    <a href="{{ route('ruilen.nieuw') }}" class="btn btn-boels"><i class="bi bi-plus-lg me-1"></i>Nieuw ruilverzoek</a>
</div>

@if(! $eigen)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Je CORE-account is niet gekoppeld aan een roosterpersoon. Je kunt daarom geen verzoeken indienen of bevestigen. Vraag de beheerder om de koppeling (Beheer → Medewerkers).</div>
@endif

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><a class="nav-link {{ $tab === 'wacht' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-wacht">Wacht op mij @if($wachtOpMij->count())<span class="badge bg-warning text-dark ms-1">{{ $wachtOpMij->count() }}</span>@endif</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'mijn' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-mijn">Door mij aangevraagd @if($doorMij->count())<span class="badge bg-secondary ms-1">{{ $doorMij->count() }}</span>@endif</a></li>
    <li class="nav-item"><a class="nav-link {{ $tab === 'afgehandeld' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-afgehandeld">Afgehandeld</a></li>
    @if($beheer)
    <li class="nav-item"><a class="nav-link {{ $tab === 'alle' ? 'active' : '' }}" data-bs-toggle="tab" href="#tab-alle"><i class="bi bi-people me-1"></i>Alle open verzoeken @if($alleOpen->count())<span class="badge bg-boels ms-1">{{ $alleOpen->count() }}</span>@endif</a></li>
    @endif
</ul>

<div class="tab-content">
    <div class="tab-pane fade {{ $tab === 'wacht' ? 'show active' : '' }}" id="tab-wacht">
        <div class="card">
            <div class="card-header"><i class="bi bi-hourglass-split me-2 text-boels"></i>Verzoeken die op jouw bevestiging wachten</div>
            @if($wachtOpMij->isEmpty())
                <div class="card-body text-muted">Er wacht niets op jou.</div>
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Aangevraagd</th><th>Ruiling</th><th>Door</th><th>Wacht op</th><th>Status</th><th></th></tr></thead>
                    <tbody>@foreach($wachtOpMij as $r)@include('ruilen._rij', ['r' => $r])@endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'mijn' ? 'show active' : '' }}" id="tab-mijn">
        <div class="card">
            <div class="card-header"><i class="bi bi-send me-2 text-boels"></i>Open verzoeken die jij hebt ingediend</div>
            @if($doorMij->isEmpty())
                <div class="card-body text-muted">Je hebt geen open verzoeken. <a href="{{ route('ruilen.nieuw') }}">Nieuw ruilverzoek</a></div>
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Aangevraagd</th><th>Ruiling</th><th>Door</th><th>Wacht op</th><th>Status</th><th></th></tr></thead>
                    <tbody>@foreach($doorMij as $r)@include('ruilen._rij', ['r' => $r])@endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>

    <div class="tab-pane fade {{ $tab === 'afgehandeld' ? 'show active' : '' }}" id="tab-afgehandeld">
        <div class="card">
            <div class="card-header"><i class="bi bi-archive me-2 text-boels"></i>Afgehandeld (laatste 50 waar jij partij was)</div>
            @if($afgehandeld->isEmpty())
                <div class="card-body text-muted">Nog niets afgehandeld.</div>
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Aangevraagd</th><th>Ruiling</th><th>Door</th><th>Afgehandeld door</th><th>Status</th><th></th></tr></thead>
                    <tbody>@foreach($afgehandeld as $r)@include('ruilen._rij', ['r' => $r])@endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>

    @if($beheer)
    <div class="tab-pane fade {{ $tab === 'alle' ? 'show active' : '' }}" id="tab-alle">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people me-2 text-boels"></i>Alle open verzoeken ({{ rol_naam(actieve_rol()) }})</span>
                @if(actieve_rol() === 'admin')<a href="{{ route('admin.ruilingen') }}" class="btn btn-sm btn-outline-boels">Beheer ruilingen</a>@endif
            </div>
            @if($alleOpen->isEmpty())
                <div class="card-body text-muted">Er staan geen verzoeken open.</div>
            @else
                <div class="table-responsive"><table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Aangevraagd</th><th>Ruiling</th><th>Door</th><th>Wacht op</th><th>Status</th><th></th></tr></thead>
                    <tbody>@foreach($alleOpen as $r)@include('ruilen._rij', ['r' => $r])@endforeach</tbody>
                </table></div>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
