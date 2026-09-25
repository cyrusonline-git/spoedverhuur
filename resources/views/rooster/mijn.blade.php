@extends('layouts.app')
@section('titel', 'Mijn diensten')
@section('inhoud')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 page-header">
    <div>
        <h1><i class="bi bi-person-check me-2 text-boels"></i>Mijn diensten</h1>
        <p>@if($mw)Roosterpersoon: <strong>{{ $mw->naam }}</strong>@else Jouw account is nog niet aan een roosterpersoon gekoppeld.@endif</p>
    </div>
    @if($mw)
    <div class="d-flex gap-2">
        <a href="{{ route('mijn-vergoedingen', $jaar) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-cash-coin me-1"></i>Vergoedingen {{ $jaar }}</a>
        <a href="{{ route('ruilen') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left-right me-1"></i>Mijn ruilingen</a>
    </div>
    @endif
</div>

@if(! $mw)
    <div class="alert alert-warning"><i class="bi bi-person-x me-2"></i>Je account is nog niet aan een roosterpersoon gekoppeld; vraag de beheerder om je te koppelen bij <em>Medewerkers &amp; koppelingen</em>. Tot die tijd kun je het <a href="{{ route('rooster') }}" class="alert-link">rooster</a> wel inzien.</div>
@else
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-calendar-check"></i></div><div><div class="kpi-value">{{ $komend->count() }}</div><div class="kpi-label">komende diensten</div></div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#6c757d"><i class="bi bi-calendar-x"></i></div><div><div class="kpi-value">{{ $afgelopen->count() }}</div><div class="kpi-label">afgelopen diensten</div></div></div></div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-cash-coin"></i></div><div><div class="kpi-value">{{ euro($jaartotaal['bedrag']) }}</div><div class="kpi-label">vergoeding {{ $jaar }} ({{ $jaartotaal['dagen'] }} dagen)</div></div></div></div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><i class="bi bi-arrow-right-circle me-2 text-boels"></i>Komende diensten</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Week</th><th>Datums</th><th>Dienstsoort</th><th>Dagen</th><th>Oorsprong</th><th></th></tr></thead>
            <tbody>
                @forelse($komend as $t)
                    <tr>
                        <td><a href="{{ route('rooster.week', [$t->week->jaar, $t->week->weeknummer]) }}">Week {{ $t->week->weeknummer }}</a> <span class="text-muted small">{{ $t->week->jaar }}</span></td>
                        <td>{{ $t->week->datumVanDag($t->dag_van)->format('d-m-Y') }} t/m {{ $t->week->datumVanDag($t->dag_tm)->format('d-m-Y') }}</td>
                        <td>{{ $t->soort?->naam }}</td>
                        <td>@if($t->heleWeek())hele week @else {{ \App\Services\Weekindeling::dagNaam($t->dag_van) }} t/m {{ \App\Services\Weekindeling::dagNaam($t->dag_tm) }} ({{ $t->dagen() }}) @endif</td>
                        <td>{{ ['rooster' => 'rooster', 'ruil' => 'geruild', 'overname' => 'overgenomen', 'deel' => 'dagen gedeeld', 'handmatig' => 'handmatig'][$t->oorsprong] ?? $t->oorsprong }} @include('rooster._oorsprong', ['t' => $t])</td>
                        <td class="text-end">@if($t->dienst_id)<a href="{{ route('ruilen.nieuw', ['dienst' => $t->dienst_id]) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-arrow-left-right me-1"></i>Ruilen</a>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">Geen komende diensten gevonden.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-clock-history me-2 text-muted"></i>Afgelopen diensten</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Week</th><th>Datums</th><th>Dienstsoort</th><th>Dagen</th><th>Oorsprong</th></tr></thead>
            <tbody>
                @forelse($afgelopen as $t)
                    <tr class="text-muted">
                        <td><a href="{{ route('rooster.week', [$t->week->jaar, $t->week->weeknummer]) }}">Week {{ $t->week->weeknummer }}</a> <span class="small">{{ $t->week->jaar }}</span></td>
                        <td>{{ $t->week->datumVanDag($t->dag_van)->format('d-m-Y') }} t/m {{ $t->week->datumVanDag($t->dag_tm)->format('d-m-Y') }}</td>
                        <td>{{ $t->soort?->naam }}</td>
                        <td>@if($t->heleWeek())hele week @else {{ \App\Services\Weekindeling::dagNaam($t->dag_van) }} t/m {{ \App\Services\Weekindeling::dagNaam($t->dag_tm) }} ({{ $t->dagen() }}) @endif</td>
                        <td>{{ ['rooster' => 'rooster', 'ruil' => 'geruild', 'overname' => 'overgenomen', 'deel' => 'dagen gedeeld', 'handmatig' => 'handmatig'][$t->oorsprong] ?? $t->oorsprong }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">Nog geen afgelopen diensten.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });</script>
@endpush
