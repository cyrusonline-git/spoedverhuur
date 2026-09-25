@extends('layouts.app')
@section('titel', 'Maandoverzicht '.$maandnaam.' '.$jaar)
@section('inhoud')
@php($oorsprongKleur = ['rooster' => 'secondary', 'ruil' => 'info text-dark', 'overname' => 'primary', 'deel' => 'warning text-dark', 'handmatig' => 'dark'])
@php($oorsprongLabel = ['rooster' => 'rooster', 'ruil' => 'ruil', 'overname' => 'overname', 'deel' => 'deel', 'handmatig' => 'handmatig'])

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 page-header">
    <div>
        <h1><i class="bi bi-file-earmark-spreadsheet text-boels me-2"></i>Maandoverzicht vergoedingen — {{ $maandnaam }} {{ $jaar }}</h1>
        <p>
            @if ($weken)
                Week {{ $week1 }} t/m {{ $week2 }}
                <span class="text-muted">({{ count($weken) }} weken; een week telt mee in de maand waarin de donderdag valt)</span>
            @else
                Geen weken gevonden voor deze maand.
            @endif
        </p>
    </div>
    <div class="d-flex flex-wrap align-items-center gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('admin.maand', ['jaar' => $vorige['jaar'], 'maand' => $vorige['maand']]) }}"><i class="bi bi-chevron-left me-1"></i>{{ $vorige['naam'] }} {{ $vorige['jaar'] }}</a>
        <form method="get" action="{{ route('admin.maand') }}" class="d-flex gap-2">
            <select name="maand" class="form-select" style="width: auto" onchange="this.form.submit()">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $m === $maand ? 'selected' : '' }}>{{ ucfirst(\App\Services\Weekindeling::maandNaam($m)) }}</option>
                @endfor
            </select>
            <select name="jaar" class="form-select" style="width: auto" onchange="this.form.submit()">
                @for ($j = (int) now()->format('Y') - 2; $j <= (int) now()->format('Y') + 1; $j++)
                    <option value="{{ $j }}" {{ $j === $jaar ? 'selected' : '' }}>{{ $j }}</option>
                @endfor
                @if ($jaar < (int) now()->format('Y') - 2 || $jaar > (int) now()->format('Y') + 1)
                    <option value="{{ $jaar }}" selected>{{ $jaar }}</option>
                @endif
            </select>
        </form>
        <a class="btn btn-outline-secondary" href="{{ route('admin.maand', ['jaar' => $volgende['jaar'], 'maand' => $volgende['maand']]) }}">{{ $volgende['naam'] }} {{ $volgende['jaar'] }}<i class="bi bi-chevron-right ms-1"></i></a>
        <a class="btn btn-outline-boels" href="{{ route('admin.maand.archief') }}"><i class="bi bi-archive me-1"></i>Archief &amp; jaaroverzicht</a>
    </div>
</div>

{{-- KPI's --}}
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body">
            <div class="kpi-icon"><i class="bi bi-list-ol"></i></div>
            <div><div class="kpi-value">{{ count($regels) }}</div><div class="kpi-label">Regels in het overzicht</div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body">
            <div class="kpi-icon"><i class="bi bi-people"></i></div>
            <div><div class="kpi-value">{{ $aantalPersonen }}</div><div class="kpi-label">Medewerkers met vergoeding</div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body">
            <div class="kpi-icon"><i class="bi bi-currency-euro"></i></div>
            <div><div class="kpi-value">{{ euro($totaal) }}</div><div class="kpi-label">Totaal te vergoeden</div></div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body">
            <div class="kpi-icon" style="background: {{ count($ontbrekend) ? '#dc3545' : '#198754' }}"><i class="bi {{ count($ontbrekend) ? 'bi-exclamation-triangle' : 'bi-check-lg' }}"></i></div>
            <div><div class="kpi-value">{{ count($ontbrekend) }}</div><div class="kpi-label">Regels met ontbrekende gegevens</div></div>
        </div></div>
    </div>
</div>

{{-- Waarschuwing ontbrekende gegevens --}}
@if ($ontbrekend)
<div class="alert alert-danger">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div class="flex-grow-1">
            <strong>Bij {{ count($ontbrekend) }} regel(s) ontbreken gegevens.</strong> In het Excel-bestand blijft het personeelsnummer dan leeg; HR/payroll kan die regels niet verwerken.
            Vul de gegevens aan bij <a href="{{ route('admin.medewerkers') }}" class="alert-link">Medewerkers &amp; koppelingen</a> (personeelsnummer komt uit CORE; anders het handmatige veld) en ververs deze pagina.
            <ul class="mb-0 mt-2">
                @foreach ($ontbrekendPerNaam as $naam => $wat)
                    <li>
                        <strong>{{ $naam }}</strong> — ontbreekt: {{ implode(', ', $wat) }}
                        <a href="{{ route('admin.medewerkers', ['zoek' => $naam]) }}" class="alert-link ms-1"><i class="bi bi-pencil-square"></i> aanvullen</a>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endif

{{-- Bestand aanmaken --}}
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-file-earmark-plus me-2 text-boels"></i>Excel-bestand aanmaken</div>
    <div class="card-body">
        @if ($laatsteVergrendeld)
            <div class="alert alert-warning mb-3">
                <i class="bi bi-lock-fill me-2"></i><strong>Deze maand is al vergrendeld</strong> (overzicht #{{ $laatsteVergrendeld->id }}, {{ $laatsteVergrendeld->bestandsnaam }},
                {{ $laatsteVergrendeld->verzonden_op ? 'verzonden op '.$laatsteVergrendeld->verzonden_op->format('d-m-Y H:i') : 'handmatig vergrendeld' }}).
                Een nieuw bestand wordt daarom een <strong>correctie</strong>: het krijgt "- correctie" in de bestandsnaam en verwijst naar het vergrendelde overzicht.
                Verstuur de correctie apart naar HR/payroll en vermeld wat er is veranderd. Wil je geen correctie maar een vervanging, ontgrendel dan eerst het bestaande overzicht.
            </div>
        @endif
        <p class="text-muted small mb-3">
            Het bestand volgt exact het sjabloon "24 uurs week vergoedingen" (kop met afdeling, weekbereik en maand; daaronder Naam Medewerker, Personeelsnummer, Weeknummer, Dagen, Betaling).
            De regels hieronder zijn een momentopname van het effectieve rooster (na bevestigde ruilingen); het bestand wordt als versie bewaard en kan daarna worden gedownload en verstuurd.
        </p>
        <form method="post" action="{{ route('admin.maand.maak') }}" class="d-flex flex-wrap align-items-center gap-3"
              @if ($ontbrekend) onsubmit="return confirm('Er ontbreken personeelsnummers bij {{ count($ontbrekend) }} regel(s); die blijven leeg in het bestand. Toch aanmaken?')" @endif>
            @csrf
            <input type="hidden" name="jaar" value="{{ $jaar }}">
            <input type="hidden" name="maand" value="{{ $maand }}">
            @if ($ontbrekend)
                <input type="hidden" name="toch" value="1">
            @endif
            <button type="submit" class="btn btn-boels" {{ count($regels) ? '' : 'disabled' }}>
                <i class="bi {{ $laatsteVergrendeld ? 'bi-file-earmark-diff' : 'bi-file-earmark-excel' }} me-1"></i>{{ $laatsteVergrendeld ? 'Correctiebestand aanmaken' : 'Bestand aanmaken' }}
            </button>
            @if (! count($regels))
                <span class="text-muted small">Geen betaalde diensten in deze maand; er is niets aan te maken.</span>
            @elseif ($ontbrekend)
                <span class="text-danger small"><i class="bi bi-exclamation-circle me-1"></i>Er ontbreken personeelsnummers — je krijgt een bevestigingsvraag.</span>
            @endif
        </form>
    </div>
</div>

{{-- Preview --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-table me-2 text-boels"></i>Voorbeeld van de regels (zoals ze in het Excel komen)</span>
        <span class="text-muted small fw-normal">Weekbedrag {{ euro(\App\Services\Vergoeding::weekbedrag()) }} · bedrag = weekbedrag / 7 × dagen</span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th>Naam Medewerker</th>
                    <th>Personeelsnummer</th>
                    <th class="text-center">Weeknummer</th>
                    <th class="text-center">Dagen</th>
                    <th class="text-end">Betaling</th>
                    <th class="border-start"><span class="text-muted" title="Niet in het Excel-bestand">Dienstsoort</span></th>
                    <th><span class="text-muted" title="Niet in het Excel-bestand">Oorsprong</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($regels as $r)
                    @php($sleutel = $r['jaar'].'-'.$r['week'])
                    @if ($loop->first || $sleutel !== $regels[$loop->index - 1]['jaar'].'-'.$regels[$loop->index - 1]['week'])
                        <tr class="table-light">
                            <td colspan="7" class="fw-semibold small text-uppercase text-secondary">
                                <i class="bi bi-calendar-week me-1"></i>Week {{ $r['week'] }}
                                <span class="fw-normal text-lowercase ms-1">{{ $weekDatums[$sleutel] ?? '' }}</span>
                                <a href="{{ route('rooster.week', ['jaar' => $r['jaar'], 'week' => $r['week']]) }}" class="ms-2 fw-normal text-lowercase">rooster</a>
                            </td>
                        </tr>
                    @endif
                    <tr class="{{ $r['ontbreekt'] ? 'table-danger' : '' }}">
                        <td>
                            {{ $r['naam'] }}
                            @if ($r['ontbreekt'])
                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="{{ implode(', ', $r['ontbreekt']) }}"></i>
                            @endif
                        </td>
                        <td>
                            @if ($r['personeelsnummer'])
                                {{ $r['personeelsnummer'] }}
                            @else
                                <span class="text-danger small">ontbreekt</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $r['week'] }}</td>
                        <td class="text-center">{{ $r['dagen'] }}</td>
                        <td class="text-end">{{ euro($r['bedrag']) }}</td>
                        <td class="border-start text-muted small">{{ $r['dienst_soort'] ?? '—' }}</td>
                        <td><span class="badge bg-{{ $oorsprongKleur[$r['oorsprong']] ?? 'secondary' }}">{{ $oorsprongLabel[$r['oorsprong']] ?? $r['oorsprong'] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">Geen betaalde diensten gevonden in week {{ $week1 }} t/m {{ $week2 }}. Is het rooster voor deze weken al geïmporteerd?</td></tr>
                @endforelse
            </tbody>
            @if ($regels)
                <tfoot>
                    <tr class="fw-bold">
                        <td colspan="2">Totaal</td>
                        <td class="text-center">{{ count($regels) }} regels</td>
                        <td class="text-center">{{ array_sum(array_column($regels, 'dagen')) }}</td>
                        <td class="text-end">{{ euro($totaal) }}</td>
                        <td colspan="2" class="border-start"></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>

{{-- Bestaande overzichten --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder2-open me-2 text-boels"></i>Aangemaakte bestanden voor {{ $maandnaam }} {{ $jaar }}</span>
        <span class="text-muted small fw-normal">
            Versturen gaat naar: {{ implode(', ', $adressen) ?: 'geen adressen ingesteld' }}
            @if ($testModus)
                · <span class="badge bg-warning text-dark">testmodus: naar het testadres</span>
            @endif
        </span>
    </div>
    @if ($overzichten->isEmpty())
        <div class="card-body text-muted">Nog geen bestand aangemaakt voor deze maand.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Bestand</th>
                    <th>Aangemaakt</th>
                    <th class="text-center">Regels</th>
                    <th class="text-end">Totaal</th>
                    <th>Status</th>
                    <th class="text-end">Acties</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($overzichten as $mo)
                    @php($taak = $mailTaken[$mo->id] ?? null)
                    <tr>
                        <td class="text-muted">{{ $mo->id }}</td>
                        <td>
                            <i class="bi bi-file-earmark-excel text-success me-1"></i>{{ $mo->bestandsnaam }}
                            @if ($mo->correctie_van_id)
                                <div class="small text-warning-emphasis"><i class="bi bi-file-earmark-diff me-1"></i>Correctie op overzicht #{{ $mo->correctie_van_id }}</div>
                            @endif
                            @if (! is_file($mo->bestandspad))
                                <div class="small text-danger"><i class="bi bi-x-circle me-1"></i>Bestand niet meer aanwezig op de server</div>
                            @endif
                        </td>
                        <td>
                            {{ $mo->created_at->format('d-m-Y H:i') }}
                            <div class="small text-muted">door {{ $mo->aangemaakt_door ?: '—' }}</div>
                        </td>
                        <td class="text-center">{{ $mo->aantal_regels }}</td>
                        <td class="text-end">{{ euro($mo->totaal_bedrag) }}</td>
                        <td>
                            @if ($mo->vergrendeld)
                                <span class="badge bg-dark"><i class="bi bi-lock-fill me-1"></i>Vergrendeld</span>
                            @else
                                <span class="badge bg-secondary">Concept</span>
                            @endif
                            @if ($mo->verzonden_op)
                                <div class="small text-success mt-1"><i class="bi bi-send-check me-1"></i>Verzonden op {{ $mo->verzonden_op->format('d-m-Y H:i') }}</div>
                            @elseif ($taak && $taak->status === 'verzonden')
                                <div class="small text-warning-emphasis mt-1"><i class="bi bi-send me-1"></i>Testmail verzonden op {{ $taak->verzonden_op?->format('d-m-Y H:i') }} (testmodus, niet naar HR)</div>
                            @elseif ($taak && $taak->status !== 'verzonden')
                                <div class="small text-danger mt-1"><i class="bi bi-send-x me-1"></i>Versturen {{ $taak->status }}: {{ $taak->fout }}</div>
                            @else
                                <div class="small text-muted mt-1">Nog niet verstuurd</div>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="d-flex flex-wrap justify-content-end align-items-center gap-2">
                                <a class="btn btn-sm btn-outline-boels" href="{{ route('admin.maand.download', $mo) }}"><i class="bi bi-download me-1"></i>Download</a>
                                <form method="post" action="{{ route('admin.maand.verstuur', $mo) }}" class="d-flex align-items-center gap-2"
                                      onsubmit="return confirm('Maandoverzicht {{ $maandnaam }} {{ $jaar }} (bestand #{{ $mo->id }}) versturen naar {{ $testModus ? 'het TESTADRES (testmodus)' : implode(', ', $adressen) }}?')">
                                    @csrf
                                    @if ($taak && $taak->status === 'verzonden')
                                        <label class="small text-muted d-flex align-items-center gap-1"><input type="checkbox" name="opnieuw" value="1" class="form-check-input mt-0">opnieuw</label>
                                    @endif
                                    <button type="submit" class="btn btn-sm btn-boels" {{ is_file($mo->bestandspad) ? '' : 'disabled' }}><i class="bi bi-send me-1"></i>Verstuur</button>
                                </form>
                                <form method="post" action="{{ route('admin.maand.vergrendel', $mo) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-sm {{ $mo->vergrendeld ? 'btn-outline-dark' : 'btn-outline-secondary' }}" title="{{ $mo->vergrendeld ? 'Ontgrendelen: nieuwe bestanden worden dan weer gewoon een versie, geen correctie' : 'Vergrendelen: markeer dit als het definitieve overzicht van de maand' }}">
                                        <i class="bi {{ $mo->vergrendeld ? 'bi-unlock' : 'bi-lock' }} me-1"></i>{{ $mo->vergrendeld ? 'Ontgrendel' : 'Vergrendel' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>
@endsection
