@extends('layouts.app')
@section('titel', 'Import: voorbeeld en koppelen')
@section('inhoud')
@php($dagen = [1, 2, 3, 4, 5, 6, 7])
<div class="page-header mb-3">
    <h1><i class="bi bi-link-45deg me-2 text-boels"></i>Rooster importeren — stap 2 van 3</h1>
    <p>Controleer het voorbeeld, koppel onbekende namen en verdeel gedeelde weken. Er is nog niets opgeslagen.</p>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-calendar3"></i></div><div><div class="kpi-value">{{ $preview['jaar'] ?: '?' }}</div><div class="kpi-label">jaar · blad "{{ $preview['blad'] ?: '—' }}"</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-calendar-week"></i></div><div><div class="kpi-value">{{ count($preview['weken']) }}</div><div class="kpi-label">weken (wk {{ $eerste['week'] ?? '?' }} t/m {{ $laatste['week'] ?? '?' }}, {{ $verleden }} in het verleden)</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-list-check"></i></div><div><div class="kpi-value">{{ count($preview['kolommen']) }}</div><div class="kpi-label">dienstsoorten · {{ $nDiensten }} diensten</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ count($preview['onbekend']) ? '#dc3545' : '#198754' }}"><i class="bi bi-person-question"></i></div><div><div class="kpi-value">{{ count($preview['onbekend']) }}</div><div class="kpi-label">onbekende namen · {{ count($vermoedelijk) }} vermoedelijk · {{ count($gedeeld) }} gedeelde cellen</div></div></div></div></div>
</div>

@if(! empty($preview['meldingen']))
    <div class="alert alert-warning small"><strong><i class="bi bi-exclamation-triangle me-1"></i>Meldingen bij het inlezen:</strong><ul class="mb-0">@foreach($preview['meldingen'] as $m)<li>{{ $m }}</li>@endforeach</ul></div>
@endif

<form method="post" action="{{ route('admin.import.verwerk') }}">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-columns me-2 text-boels"></i>Herkende kolommen</div>
        <div class="card-body small">
            @foreach($preview['kolommen'] as $kol => $s)<span class="badge bg-secondary me-1 mb-1">{{ $kol }}: {{ $s['naam'] }}</span>@endforeach
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-person-question me-2 text-boels"></i>Onbekende namen koppelen <span class="text-muted small fw-normal">({{ count($preview['onbekend']) }})</span></div>
        <div class="card-body">
            @if(empty($preview['onbekend']))
                <p class="text-success mb-0"><i class="bi bi-check-circle me-1"></i>Alle namen zijn herkend.</p>
            @else
                <p class="small text-muted">Deze namen uit het rooster zijn niet herkend. Kies de juiste medewerker; de schrijfwijze wordt onthouden voor volgende imports. "Overslaan" zet de naam wél in het rooster, maar zonder koppeling (geen mails/vergoeding).</p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Naam in rooster</th><th class="text-end">Aantal</th><th>Medewerker</th></tr></thead>
                        <tbody>
                            @foreach($preview['onbekend'] as $naam => $aantal)
                                <tr>
                                    <td class="fw-semibold text-boels">{{ $naam }}<input type="hidden" name="koppel[{{ $loop->index }}][naam]" value="{{ $naam }}"></td>
                                    <td class="text-end">{{ $aantal }}×</td>
                                    <td>
                                        <select name="koppel[{{ $loop->index }}][medewerker_id]" class="form-select form-select-sm" style="max-width: 340px">
                                            <option value="">— overslaan (niet koppelen) —</option>
                                            @foreach($medewerkers as $m)<option value="{{ $m->id }}">{{ $m->naam }}</option>@endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">Staat iemand er niet tussen? Voeg de persoon eerst toe bij <a href="{{ route('admin.medewerkers') }}" target="_blank">Medewerkers &amp; koppelingen</a> en lees het bestand daarna opnieuw in.</p>
            @endif
        </div>
    </div>

    @if(count($vermoedelijk))
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-question-circle me-2 text-boels"></i>Vermoedelijke koppelingen controleren <span class="text-muted small fw-normal">({{ count($vermoedelijk) }})</span></div>
        <div class="card-body">
            <p class="small text-muted">Deze namen zijn op achternaam of voornaam herkend. Klopt het? Laat het staan of kies een andere medewerker.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Naam in rooster</th><th class="text-end">Aantal</th><th>Vermoedelijk</th><th>Medewerker</th></tr></thead>
                    <tbody>
                        @foreach($vermoedelijk as $naam => $v)
                            <tr>
                                <td class="fw-semibold">{{ $naam }}<input type="hidden" name="vermoed[{{ $loop->index }}][naam]" value="{{ $naam }}"></td>
                                <td class="text-end">{{ $v['aantal'] }}×</td>
                                <td><span class="badge bg-info">{{ $v['zekerheid'] }}</span> {{ $v['medewerker_naam'] }}</td>
                                <td>
                                    <select name="vermoed[{{ $loop->index }}][medewerker_id]" class="form-select form-select-sm" style="max-width: 340px">
                                        <option value="">— niet koppelen —</option>
                                        @foreach($medewerkers as $m)<option value="{{ $m->id }}" {{ $m->id === $v['medewerker_id'] ? 'selected' : '' }}>{{ $m->naam }}</option>@endforeach
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    @if(count($gedeeld))
    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-scissors me-2 text-boels"></i>Gedeelde weken verdelen <span class="text-muted small fw-normal">({{ count($gedeeld) }} cellen)</span></div>
        <div class="card-body">
            <p class="small text-muted">In deze cellen staan meerdere personen. Geef per persoon de dagen (1 = maandag … 7 = zondag). Standaard: eerste ma–wo, tweede do–zo.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Week</th><th>Dienstsoort</th><th>Cel</th><th>Persoon</th><th>Van</th><th>T/m</th></tr></thead>
                    <tbody>
                        @foreach($gedeeld as $sleutel => $cel)
                            @foreach($cel['personen'] as $i => $p)
                                <tr>
                                    <td>@if($i === 0)<strong>Week {{ $cel['week'] }}</strong> <span class="text-muted small">{{ dmy($cel['van']) }}</span>@endif</td>
                                    <td>@if($i === 0){{ $cel['soort'] }}@endif</td>
                                    <td>@if($i === 0)<span class="text-muted small">{{ $cel['tekst'] }}</span>@endif</td>
                                    <td>
                                        <input type="hidden" name="verdeling[{{ $sleutel }}][{{ $i }}][naam]" value="{{ $p['naam'] }}">
                                        <input type="hidden" name="verdeling[{{ $sleutel }}][{{ $i }}][medewerker_id]" value="{{ $p['medewerker_id'] }}">
                                        {{ $p['naam'] }}
                                        @if($p['medewerker_id'])<span class="text-muted small">→ {{ $p['medewerker_naam'] }}</span>@else <span class="badge bg-warning text-dark">koppelen hierboven</span>@endif
                                    </td>
                                    <td><select name="verdeling[{{ $sleutel }}][{{ $i }}][dag_van]" class="form-select form-select-sm">@foreach($dagen as $d)<option value="{{ $d }}" {{ $d === $p['dag_van'] ? 'selected' : '' }}>{{ $d }} {{ \App\Services\Weekindeling::dagNaam($d, true) }}</option>@endforeach</select></td>
                                    <td><select name="verdeling[{{ $sleutel }}][{{ $i }}][dag_tm]" class="form-select form-select-sm">@foreach($dagen as $d)<option value="{{ $d }}" {{ $d === $p['dag_tm'] ? 'selected' : '' }}>{{ $d }} {{ \App\Services\Weekindeling::dagNaam($d, true) }}</option>@endforeach</select></td>
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><i class="bi bi-eye me-2 text-boels"></i>Voorbeeld van het rooster</div>
        <div class="table-responsive" style="max-height: 420px">
            <table class="table table-sm table-hover mb-0 small" style="white-space: nowrap">
                <thead class="sticky-top bg-white"><tr><th>Week</th><th>Van</th>@foreach($preview['kolommen'] as $s)<th>{{ $s['naam'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach($preview['weken'] as $w)
                        <tr>
                            <td><strong>{{ $w['week'] }}</strong> <span class="text-muted">{{ $w['jaar'] }}</span></td>
                            <td>{{ dmy($w['van']) }}</td>
                            @foreach($preview['kolommen'] as $s)
                                <td>
                                    @foreach($w['cellen'][$s['id']] ?? [] as $p)
                                        <div>
                                            @if($p['medewerker_id'])
                                                {{ $p['medewerker_naam'] }}@if($p['zekerheid'] !== 'exact' && $p['zekerheid'] !== 'alias') <span class="badge bg-info" title="{{ $p['zekerheid'] }}">?</span>@endif
                                            @else
                                                <span class="text-boels fw-semibold" title="onbekend">{{ $p['naam'] }}</span>
                                            @endif
                                            @if($p['gedeeld'])<i class="bi bi-scissors text-muted" title="gedeelde cel"></i>@endif
                                        </div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-body d-flex flex-wrap align-items-center gap-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ook_verleden" name="ook_verleden" value="1">
                <label class="form-check-label" for="ook_verleden">Ook weken in het verleden overschrijven <span class="text-muted small">({{ $verleden }} weken; standaard blijven die zoals ze zijn)</span></label>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('admin.import') }}" class="btn btn-outline-secondary">Annuleren</a>
                <button class="btn btn-boels" type="submit"><i class="bi bi-check2-circle me-1"></i>Verwerken (stap 3)</button>
            </div>
        </div>
    </div>
</form>
@endsection
