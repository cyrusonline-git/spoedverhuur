@extends('layouts.app')
@section('titel', 'Mijn vergoedingen '.$jaar)
@section('inhoud')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 page-header">
    <div>
        <h1><i class="bi bi-cash-coin me-2 text-boels"></i>Mijn vergoedingen {{ $jaar }}</h1>
        <p>{{ $mw->naam }} · weekbedrag {{ euro($weekbedrag) }} (per dag {{ euro($weekbedrag / 7) }}) · alleen betaalde dienstsoorten, op basis van het effectieve rooster.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('mijn-vergoedingen', $jaar - 1) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> {{ $jaar - 1 }}</a>
        <a href="{{ route('mijn-vergoedingen', $jaar + 1) }}" class="btn btn-sm btn-outline-secondary">{{ $jaar + 1 }} <i class="bi bi-chevron-right"></i></a>
        <a href="{{ route('mijn-diensten') }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-person-check me-1"></i>Mijn diensten</a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-cash-stack"></i></div><div><div class="kpi-value">{{ euro($totaalBedrag) }}</div><div class="kpi-label">jaartotaal {{ $jaar }}</div></div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-calendar-day"></i></div><div><div class="kpi-value">{{ $totaalDagen }}</div><div class="kpi-label">dagen dienst</div></div></div></div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-2 text-boels"></i>Per maand</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Maand</th><th>Weken / dienstsoort</th><th class="text-end">Dagen</th><th class="text-end">Bedrag</th></tr></thead>
            <tbody>
                @foreach($maanden as $m)
                    <tr class="{{ $m['dagen'] === 0 ? 'text-muted' : '' }}">
                        <td class="text-capitalize">{{ $m['naam'] }}</td>
                        <td class="small">
                            @forelse($m['regels'] as $r)
                                <div><a href="{{ route('rooster.week', [$r['jaar'], $r['week']]) }}">wk {{ $r['week'] }}</a> · {{ $r['dienst_soort'] }} · {{ $r['dagen'] }} dg · {{ euro($r['bedrag']) }}@if($r['oorsprong'] !== 'rooster') <span class="badge bg-info">{{ $r['oorsprong'] }}</span>@endif</div>
                            @empty
                                —
                            @endforelse
                        </td>
                        <td class="text-end">{{ $m['dagen'] }}</td>
                        <td class="text-end">{{ euro($m['bedrag']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr class="fw-bold"><td colspan="2">Totaal {{ $jaar }}</td><td class="text-end">{{ $totaalDagen }}</td><td class="text-end">{{ euro($totaalBedrag) }}</td></tr></tfoot>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">Een week telt mee voor de maand waarin de donderdag valt. De definitieve uitbetaling volgt het maandoverzicht van de beheerder.</p>
@endsection
