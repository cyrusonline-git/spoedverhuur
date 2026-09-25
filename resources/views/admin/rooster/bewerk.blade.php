@extends('layouts.app')
@section('titel', 'Rooster bewerken week '.$week)
@push('head')
<style>
    .regel-tabel td { vertical-align: middle; }
    .regel-tabel .form-select-sm, .regel-tabel .form-control-sm { min-width: 90px; }
</style>
@endpush
@section('inhoud')
@php($dagen = [1, 2, 3, 4, 5, 6, 7])
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 page-header">
    <div>
        <h1><i class="bi bi-pencil-square me-2 text-boels"></i>Rooster bewerken — week {{ $week }} ({{ $jaar }})</h1>
        <p>{{ $van->format('d-m-Y') }} t/m {{ $tm->format('d-m-Y') }} · geplande diensten; na opslaan wordt het effectieve rooster (met ruilingen) opnieuw berekend.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.rooster.bewerk', [$vorige[0], $vorige[1]]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> Week {{ $vorige[1] }}</a>
        <a href="{{ route('rooster.week', [$jaar, $week]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye me-1"></i>Bekijken</a>
        <a href="{{ route('admin.rooster.bewerk', [$volgende[0], $volgende[1]]) }}" class="btn btn-sm btn-outline-secondary">Week {{ $volgende[1] }} <i class="bi bi-chevron-right"></i></a>
        <form method="post" action="{{ route('admin.rooster.herbereken') }}" onsubmit="return confirm('Alle weken opnieuw berekenen (diensten + bevestigde ruilingen → effectief rooster)?')">
            @csrf
            <input type="hidden" name="terug" value="{{ route('admin.rooster.bewerk', [$jaar, $week]) }}">
            <button class="btn btn-sm btn-outline-boels" type="submit"><i class="bi bi-arrow-repeat me-1"></i>Alles herberekenen</button>
        </form>
    </div>
</div>

@if(! $model)
    <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Voor deze week bestaat nog geen rooster; bij opslaan wordt de week aangemaakt.</div>
@endif
@if($ruilingen->isNotEmpty())
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-2"></i><strong>Let op: deze week heeft {{ $ruilingen->where('status', 'bevestigd')->count() }} bevestigde en {{ $ruilingen->where('status', 'aangevraagd')->count() }} open ruiling(en).</strong>
        Regels met een <span class="badge bg-info"><i class="bi bi-arrow-left-right"></i></span>-markering hangen aan een ruiling. Als je zo'n regel wijzigt of verwijdert, wordt de ruiling meegenomen in de herberekening (verwijderen = ruiling weg). Het effectieve rooster wordt na opslaan opnieuw berekend.
        <ul class="mb-0 small mt-1">
            @foreach($ruilingen as $r)
                <li>#{{ $r->id }} {{ $r->typeLabel() }} · {{ $r->van?->naam }} → {{ $r->naar?->naam }} · {{ $r->dienst?->soort?->naam }}@if($r->tegenDienst) ⇄ {{ $r->tegenDienst->soort?->naam }}@endif · <span class="badge bg-{{ $r->status === 'bevestigd' ? 'success' : 'secondary' }}">{{ $r->statusLabel() }}</span></li>
            @endforeach
        </ul>
    </div>
@endif

<form method="post" action="{{ route('admin.rooster.bewerk.opslaan', [$jaar, $week]) }}" id="bewerk-form">
    @csrf
    @foreach($soorten as $s)
        <div class="card mb-3" data-soort="{{ $s->id }}">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-list-check me-2 text-boels"></i>{{ $s->naam }}@if(! $s->betaald) <span class="badge bg-secondary">onbetaald</span>@endif</span>
                <span class="d-flex gap-1">
                    <button type="button" class="btn btn-sm btn-outline-boels btn-toevoegen"><i class="bi bi-plus-lg me-1"></i>Regel toevoegen</button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-leegmaken" title="Alle regels van deze dienstsoort verwijderen"><i class="bi bi-trash me-1"></i>Leegmaken</button>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 regel-tabel">
                    <thead><tr><th style="width:36%">Medewerker</th><th>Dagen</th><th style="width:26%">Naam in rooster <span class="fw-normal text-muted">(als niet gekoppeld)</span></th><th>Bron</th><th></th></tr></thead>
                    <tbody class="regels">
                        @foreach($perSoort->get($s->id, collect()) as $i => $d)
                            <tr>
                                <td>
                                    <input type="hidden" name="regels[{{ $s->id }}][{{ $i }}][id]" value="{{ $d->id }}">
                                    <select name="regels[{{ $s->id }}][{{ $i }}][medewerker_id]" class="form-select form-select-sm">
                                        <option value="">— niet gekoppeld —</option>
                                        @foreach($medewerkers as $m)<option value="{{ $m->id }}" {{ $m->id === $d->medewerker_id ? 'selected' : '' }}>{{ $m->naam }}</option>@endforeach
                                        @if($d->medewerker_id && ! $medewerkers->contains('id', $d->medewerker_id))<option value="{{ $d->medewerker_id }}" selected>{{ $d->medewerker?->naam }} (inactief)</option>@endif
                                    </select>
                                </td>
                                <td class="text-nowrap">
                                    <select name="regels[{{ $s->id }}][{{ $i }}][dag_van]" class="form-select form-select-sm d-inline-block w-auto">
                                        <option value="">hele week</option>
                                        @foreach($dagen as $dg)<option value="{{ $dg }}" {{ ! $d->heleWeek() && $dg === $d->dagVan() ? 'selected' : '' }}>van {{ \App\Services\Weekindeling::dagNaam($dg, true) }}</option>@endforeach
                                    </select>
                                    <select name="regels[{{ $s->id }}][{{ $i }}][dag_tm]" class="form-select form-select-sm d-inline-block w-auto">
                                        <option value="">hele week</option>
                                        @foreach($dagen as $dg)<option value="{{ $dg }}" {{ ! $d->heleWeek() && $dg === $d->dagTm() ? 'selected' : '' }}>t/m {{ \App\Services\Weekindeling::dagNaam($dg, true) }}</option>@endforeach
                                    </select>
                                </td>
                                <td><input type="text" name="regels[{{ $s->id }}][{{ $i }}][rooster_naam]" value="{{ $d->rooster_naam }}" class="form-control form-control-sm" placeholder="naam uit Excel"></td>
                                <td class="small text-muted">{{ $d->bron }}@if(isset($metRuiling[$d->id])) <span class="badge bg-info" title="{{ count($metRuiling[$d->id]) }} ruiling(en)"><i class="bi bi-arrow-left-right"></i> {{ count($metRuiling[$d->id]) }}</span>@endif</td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-verwijder" title="Regel verwijderen"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <template class="regel-sjabloon">
                <tr>
                    <td>
                        <select name="regels[{{ $s->id }}][__I__][medewerker_id]" class="form-select form-select-sm">
                            <option value="">— niet gekoppeld —</option>
                            @foreach($medewerkers as $m)<option value="{{ $m->id }}">{{ $m->naam }}</option>@endforeach
                        </select>
                    </td>
                    <td class="text-nowrap">
                        <select name="regels[{{ $s->id }}][__I__][dag_van]" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="">hele week</option>
                            @foreach($dagen as $dg)<option value="{{ $dg }}">van {{ \App\Services\Weekindeling::dagNaam($dg, true) }}</option>@endforeach
                        </select>
                        <select name="regels[{{ $s->id }}][__I__][dag_tm]" class="form-select form-select-sm d-inline-block w-auto">
                            <option value="">hele week</option>
                            @foreach($dagen as $dg)<option value="{{ $dg }}">t/m {{ \App\Services\Weekindeling::dagNaam($dg, true) }}</option>@endforeach
                        </select>
                    </td>
                    <td><input type="text" name="regels[{{ $s->id }}][__I__][rooster_naam]" class="form-control form-control-sm" placeholder="naam (optioneel)"></td>
                    <td class="small text-muted">nieuw</td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger btn-verwijder" title="Regel verwijderen"><i class="bi bi-x-lg"></i></button></td>
                </tr>
            </template>
        </div>
    @endforeach

    <div class="card">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <span class="small text-muted">Lege regels (geen medewerker én geen naam) worden genegeerd. Regels die je weghaalt worden verwijderd; overige worden bewaard als "handmatig".</span>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('rooster.week', [$jaar, $week]) }}" class="btn btn-outline-secondary">Annuleren</a>
                <button class="btn btn-boels" type="submit"><i class="bi bi-save me-1"></i>Opslaan en herberekenen</button>
            </div>
        </div>
    </div>
</form>
@endsection
@push('scripts')
<script>
(function () {
    var teller = 1000;
    document.querySelectorAll('[data-soort]').forEach(function (kaart) {
        var body = kaart.querySelector('.regels');
        var sjabloon = kaart.querySelector('.regel-sjabloon');
        kaart.querySelector('.btn-toevoegen').addEventListener('click', function () {
            var html = sjabloon.innerHTML.replace(/__I__/g, String(teller++));
            body.insertAdjacentHTML('beforeend', html);
        });
        kaart.querySelector('.btn-leegmaken').addEventListener('click', function () {
            if (body.children.length === 0 || confirm('Alle regels van deze dienstsoort verwijderen?')) { body.innerHTML = ''; }
        });
        kaart.addEventListener('click', function (e) {
            var knop = e.target.closest('.btn-verwijder');
            if (knop) { knop.closest('tr').remove(); }
        });
    });
})();
</script>
@endpush
