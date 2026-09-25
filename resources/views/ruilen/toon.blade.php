@extends('layouts.app')
@section('titel', 'Ruilverzoek #'.$r->id)
@section('inhoud')
@php($w = $r->dienst?->week)
@php($tw = $r->tegenDienst?->week)
<div class="d-flex flex-wrap justify-content-between align-items-center page-header mb-3 gap-2">
    <div>
        <h1><i class="bi bi-arrow-left-right me-2 text-boels"></i>Ruilverzoek #{{ $r->id }} @include('ruilen._badge', ['r' => $r])</h1>
        <p>{{ $omschrijving }}</p>
    </div>
    <a href="{{ route('ruilen') }}" class="btn btn-light"><i class="bi bi-arrow-left me-1"></i>Terug naar overzicht</a>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-people me-2 text-boels"></i>Partijen en diensten</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted text-uppercase">Geeft af</div>
                            <div class="fs-5 fw-semibold">{{ $r->van?->naam ?? '—' }}</div>
                            @if($r->van?->emailEffectief())<div class="small text-muted">{{ $r->van->emailEffectief() }}</div>@endif
                            <hr>
                            @if($r->dienst)
                                <div><strong>Week {{ $w->weeknummer }}</strong> · {{ $r->dienst->soort?->naam }}</div>
                                <div class="small text-muted">{{ $w->van->format('d-m-Y') }} t/m {{ $w->tm->format('d-m-Y') }}</div>
                                @if(! $r->dienst->heleWeek())<div class="small">Deeldienst: {{ \App\Services\Weekindeling::dagNaam($r->dienst->dagVan()) }} t/m {{ \App\Services\Weekindeling::dagNaam($r->dienst->dagTm()) }}</div>@endif
                                @if($r->type === 'deel')
                                    <div class="mt-2 badge bg-info text-dark">Dagen die overgaan: {{ \App\Services\Weekindeling::dagNaam((int) $r->dagen_van) }} {{ $w->datumVanDag((int) $r->dagen_van)->format('d-m') }} t/m {{ \App\Services\Weekindeling::dagNaam((int) $r->dagen_tm) }} {{ $w->datumVanDag((int) $r->dagen_tm)->format('d-m') }}</div>
                                @endif
                            @else
                                <div class="text-muted">Dienst bestaat niet meer.</div>
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted text-uppercase">Neemt over</div>
                            <div class="fs-5 fw-semibold">{{ $r->naar?->naam ?? '—' }}</div>
                            @if($r->naar?->emailEffectief())<div class="small text-muted">{{ $r->naar->emailEffectief() }}</div>@endif
                            <hr>
                            @if($r->type === 'ruil')
                                @if($r->tegenDienst)
                                    <div><strong>Week {{ $tw->weeknummer }}</strong> · {{ $r->tegenDienst->soort?->naam }} <span class="small text-muted">(gaat naar {{ $r->van?->naam }})</span></div>
                                    <div class="small text-muted">{{ $tw->van->format('d-m-Y') }} t/m {{ $tw->tm->format('d-m-Y') }}</div>
                                    @if(! $r->tegenDienst->heleWeek())<div class="small">Deeldienst: {{ \App\Services\Weekindeling::dagNaam($r->tegenDienst->dagVan()) }} t/m {{ \App\Services\Weekindeling::dagNaam($r->tegenDienst->dagTm()) }}</div>@endif
                                @else
                                    <div class="text-muted">Tegendienst bestaat niet meer.</div>
                                @endif
                            @elseif($r->type === 'deel')
                                <div class="text-muted">Neemt de genoemde dagen over; geeft niets terug.</div>
                            @else
                                <div class="text-muted">Neemt de hele dienst over; geeft niets terug.</div>
                            @endif
                        </div>
                    </div>
                </div>
                <table class="table table-sm mt-3 mb-0">
                    <tr><th class="text-muted fw-normal" style="width:35%">Type</th><td>{{ $r->typeLabel() }}</td></tr>
                    <tr><th class="text-muted fw-normal">Aangevraagd door</th><td>{{ $r->aangevraagdDoor?->naam ?? '—' }} op {{ $r->created_at?->format('d-m-Y H:i') }}</td></tr>
                    @if($r->isOpen())<tr><th class="text-muted fw-normal">Wacht op</th><td>{{ $r->moetBevestigen()?->naam ?? '—' }} (tot {{ $r->token_verloopt_op?->format('d-m-Y H:i') }})</td></tr>@endif
                    @if($r->afgehandeld_door)<tr><th class="text-muted fw-normal">Afgehandeld door</th><td>{{ $r->afgehandeld_door }}</td></tr>@endif
                    <tr><th class="text-muted fw-normal">Opmerking</th><td>{!! $r->opmerking ? nl2br(e($r->opmerking)) : '<span class="text-muted">—</span>' !!}</td></tr>
                </table>
            </div>
        </div>

        @if($magBevestigen || $magIntrekken || $magAfwijzenAlsBeheer)
        <div class="card">
            <div class="card-header"><i class="bi bi-hand-index me-2 text-boels"></i>Wat wil je doen?</div>
            <div class="card-body">
                @if($magBevestigen)
                    <p>Dit verzoek wacht op <strong>jouw</strong> bevestiging. Na bevestiging wordt het rooster direct aangepast.</p>
                    <div class="d-flex flex-wrap gap-2">
                        <form method="post" action="{{ route('ruilen.bevestig', $r) }}">@csrf<button class="btn btn-success btn-lg"><i class="bi bi-check-lg me-1"></i>Ja, ik bevestig</button></form>
                        <button type="button" class="btn btn-outline-danger btn-lg" data-bs-toggle="collapse" data-bs-target="#afwijs-vak"><i class="bi bi-x-lg me-1"></i>Afwijzen…</button>
                    </div>
                @elseif($magAfwijzenAlsBeheer)
                    <p class="text-muted">Als {{ rol_naam(actieve_rol()) }} kun je dit verzoek namens de organisatie afwijzen.</p>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#afwijs-vak"><i class="bi bi-x-lg me-1"></i>Afwijzen…</button>
                @endif
                @if($magBevestigen || $magAfwijzenAlsBeheer)
                <div class="collapse mt-3 {{ $errors->has('reden') ? 'show' : '' }}" id="afwijs-vak">
                    <form method="post" action="{{ route('ruilen.afwijs', $r) }}" class="border rounded p-3 bg-light">@csrf
                        <label class="form-label">Reden (optioneel, gaat mee in de mail naar de aanvrager)</label>
                        <textarea name="reden" class="form-control mb-2" rows="2" maxlength="500"></textarea>
                        <button class="btn btn-danger">Verzoek afwijzen</button>
                    </form>
                </div>
                @endif
                @if($magIntrekken)
                    <div class="{{ $magBevestigen || $magAfwijzenAlsBeheer ? 'mt-3 pt-3 border-top' : '' }}">
                        <form method="post" action="{{ route('ruilen.intrek', $r) }}" class="d-inline">@csrf<button class="btn btn-outline-secondary" onclick="return confirm('Verzoek intrekken? De collega krijgt daarvan bericht.')"><i class="bi bi-arrow-counterclockwise me-1"></i>Verzoek intrekken</button></form>
                    </div>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2 text-boels"></i>Tijdlijn</div>
            <ul class="list-group list-group-flush">
                @foreach($tijdlijn as $stap)
                    <li class="list-group-item d-flex gap-3 {{ ! empty($stap['toekomst']) ? 'text-muted' : '' }}">
                        <span class="badge rounded-pill bg-{{ $stap['kleur'] }} {{ in_array($stap['kleur'], ['warning', 'light']) ? 'text-dark' : '' }} d-flex align-items-center" style="width:32px;height:32px;justify-content:center"><i class="bi bi-{{ $stap['icoon'] }}"></i></span>
                        <span>
                            <div>{{ $stap['tekst'] }}</div>
                            <small class="text-muted">{{ $stap['op']?->format('d-m-Y H:i') ?? '—' }}</small>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
        @if(in_array(actieve_rol(), ['manager', 'admin']) && $r->status === 'bevestigd' && actieve_rol() === 'admin')
            <div class="mt-3 small text-muted">Terugdraaien kan onder <a href="{{ route('admin.ruilingen', ['status' => 'bevestigd']) }}">Beheer → Ruilingen beheren</a>.</div>
        @endif
    </div>
</div>
@endsection
