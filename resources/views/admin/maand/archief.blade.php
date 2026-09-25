@extends('layouts.app')
@section('titel', 'Archief maandoverzichten')
@section('inhoud')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 page-header">
    <div>
        <h1><i class="bi bi-archive text-boels me-2"></i>Archief maandoverzichten</h1>
        <p>Alle aangemaakte Excel-bestanden (nieuwste eerst) en het jaaroverzicht van de vergoedingen per medewerker.</p>
    </div>
    <div>
        <a class="btn btn-outline-boels" href="{{ route('admin.maand') }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Naar het maandoverzicht</a>
    </div>
</div>

{{-- Jaaroverzicht per medewerker --}}
<div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-people me-2 text-boels"></i>Jaaroverzicht {{ $jaar }} — vergoeding per medewerker per maand</span>
        <form method="get" action="{{ route('admin.maand.archief') }}" class="d-flex align-items-center gap-2 fw-normal">
            <label class="small text-muted" for="jaarkeuze">Jaar</label>
            <select id="jaarkeuze" name="jaar" class="form-select form-select-sm" style="width: auto" onchange="this.form.submit()">
                @foreach ($jaren as $j)
                    <option value="{{ $j }}" {{ $j === $jaar ? 'selected' : '' }}>{{ $j }}</option>
                @endforeach
            </select>
        </form>
    </div>
    @if (! $matrix)
        <div class="card-body text-muted">Geen betaalde diensten gevonden in {{ $jaar }}.</div>
    @else
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" style="font-size: .85rem">
            <thead>
                <tr>
                    <th>Medewerker</th>
                    @for ($m = 1; $m <= 12; $m++)
                        <th class="text-end {{ in_array($m, $maandenMetData, true) ? '' : 'text-muted opacity-50' }}">
                            <a href="{{ route('admin.maand', ['jaar' => $jaar, 'maand' => $m]) }}" class="text-reset text-decoration-none" title="{{ ucfirst(\App\Services\Weekindeling::maandNaam($m)) }} {{ $jaar }} openen">{{ mb_substr(\App\Services\Weekindeling::maandNaam($m), 0, 3) }}</a>
                        </th>
                    @endfor
                    <th class="text-end border-start">Totaal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($matrix as $naam => $rij)
                    <tr>
                        <td class="text-nowrap">
                            {{ $naam }}
                            @if (! $rij['medewerker_id'])
                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1" title="Niet gekoppeld aan een medewerker"></i>
                            @endif
                        </td>
                        @for ($m = 1; $m <= 12; $m++)
                            <td class="text-end text-nowrap {{ isset($rij['maanden'][$m]) ? '' : 'text-muted opacity-50' }}">{{ isset($rij['maanden'][$m]) ? euro($rij['maanden'][$m]) : '—' }}</td>
                        @endfor
                        <td class="text-end text-nowrap fw-semibold border-start">{{ euro($rij['totaal']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td>Totaal ({{ count($matrix) }} medewerkers)</td>
                    @for ($m = 1; $m <= 12; $m++)
                        <td class="text-end text-nowrap">{{ $kolomTotaal[$m] > 0 ? euro($kolomTotaal[$m]) : '—' }}</td>
                    @endfor
                    <td class="text-end text-nowrap border-start">{{ euro($jaarTotaal) }}</td>
                </tr>
                <tr class="small text-muted">
                    <td>Aantal regels</td>
                    @for ($m = 1; $m <= 12; $m++)
                        <td class="text-end">{{ $aantalPerMaand[$m] ?: '—' }}</td>
                    @endfor
                    <td class="text-end border-start">{{ array_sum($aantalPerMaand) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-body pt-2 small text-muted">Berekend uit het effectieve rooster van dit moment (na bevestigde ruilingen), niet uit de aangemaakte bestanden. Klik op een maandnaam om die maand te openen.</div>
    @endif
</div>

{{-- Alle bestanden per jaar --}}
@forelse ($perJaar as $j => $overzichten)
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-folder2-open me-2 text-boels"></i>{{ $j }} <span class="text-muted fw-normal small ms-2">{{ $overzichten->count() }} bestand(en)</span></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Maand</th>
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
                        <td><a href="{{ route('admin.maand', ['jaar' => $mo->jaar, 'maand' => $mo->maand]) }}">{{ ucfirst(\App\Services\Weekindeling::maandNaam($mo->maand)) }} {{ $mo->jaar }}</a></td>
                        <td>
                            <i class="bi bi-file-earmark-excel text-success me-1"></i>{{ $mo->bestandsnaam }}
                            @if ($mo->correctie_van_id)
                                <div class="small text-warning-emphasis"><i class="bi bi-file-earmark-diff me-1"></i>Correctie op #{{ $mo->correctie_van_id }}</div>
                            @endif
                            @if (! is_file($mo->bestandspad))
                                <div class="small text-danger"><i class="bi bi-x-circle me-1"></i>Bestand niet meer aanwezig</div>
                            @endif
                        </td>
                        <td>{{ $mo->created_at->format('d-m-Y H:i') }}<div class="small text-muted">door {{ $mo->aangemaakt_door ?: '—' }}</div></td>
                        <td class="text-center">{{ $mo->aantal_regels }}</td>
                        <td class="text-end">{{ euro($mo->totaal_bedrag) }}</td>
                        <td>
                            @if ($mo->vergrendeld)
                                <span class="badge bg-dark"><i class="bi bi-lock-fill me-1"></i>Vergrendeld</span>
                            @else
                                <span class="badge bg-secondary">Concept</span>
                            @endif
                            @if ($mo->verzonden_op)
                                <div class="small text-success mt-1"><i class="bi bi-send-check me-1"></i>Verzonden {{ $mo->verzonden_op->format('d-m-Y H:i') }}</div>
                            @elseif ($taak && $taak->status === 'verzonden')
                                <div class="small text-warning-emphasis mt-1"><i class="bi bi-send me-1"></i>Testmail {{ $taak->verzonden_op?->format('d-m-Y H:i') }} (testmodus)</div>
                            @elseif ($taak)
                                <div class="small text-danger mt-1"><i class="bi bi-send-x me-1"></i>{{ ucfirst($taak->status) }}: {{ $taak->fout }}</div>
                            @else
                                <div class="small text-muted mt-1">Niet verstuurd</div>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            @if (is_file($mo->bestandspad))
                                <a class="btn btn-sm btn-outline-boels" href="{{ route('admin.maand.download', $mo) }}"><i class="bi bi-download me-1"></i>Download</a>
                            @endif
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.maand', ['jaar' => $mo->jaar, 'maand' => $mo->maand]) }}"><i class="bi bi-box-arrow-up-right me-1"></i>Open maand</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@empty
<div class="card mb-4"><div class="card-body text-muted">Er zijn nog geen maandoverzichten aangemaakt.</div></div>
@endforelse
@endsection
