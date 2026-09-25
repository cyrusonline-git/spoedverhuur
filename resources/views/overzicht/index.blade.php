@extends('layouts.app')
@section('titel', 'Overzicht')
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-grid me-2 text-boels"></i>Manager-dashboard</h1>
        <p>Stand van zaken van het rooster, de ruilingen en de medewerkergegevens.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('overzicht.bezetting') }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-calendar-check me-1"></i>Bezetting</a>
        <a href="{{ route('overzicht.ruilingen') }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-arrow-left-right me-1"></i>Ruilingen</a>
        <a href="{{ route('overzicht.belasting') }}" class="btn btn-outline-boels btn-sm"><i class="bi bi-people me-1"></i>Wie draait hoeveel</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('overzicht.bezetting') }}#open" class="text-decoration-none text-reset">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon" style="background: {{ $openPlekken ? '#dc3545' : '#198754' }}"><i class="bi bi-exclamation-diamond"></i></div>
            <div><div class="kpi-value">{{ $openPlekken }}</div><div class="kpi-label">open plekken<br>komende 8 weken</div></div>
        </div></div></a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('overzicht.ruilingen', ['status' => 'aangevraagd', 'van' => '']) }}" class="text-decoration-none text-reset">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon" style="background: {{ $openRuil ? '#fd7e14' : '#6c757d' }}"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="kpi-value">{{ $openRuil }}</div><div class="kpi-label">open<br>ruilverzoeken</div></div>
        </div></div></a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('overzicht.ruilingen', ['status' => 'bevestigd', 'van' => now()->subDays(90)->toDateString()]) }}" class="text-decoration-none text-reset">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div>
            <div><div class="kpi-value">{{ $bevestigd30 }} <span class="fs-6 fw-normal text-muted">/ {{ $bevestigd90 }}</span></div><div class="kpi-label">ruilingen bevestigd<br>laatste 30 / 90 dagen</div></div>
        </div></div></a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="{{ route('overzicht.bezetting') }}" class="text-decoration-none text-reset">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon" style="background: {{ count($wekenZonderRooster) ? '#dc3545' : '#198754' }}"><i class="bi bi-calendar-x"></i></div>
            <div><div class="kpi-value">{{ count($wekenZonderRooster) }}</div><div class="kpi-label">weken zonder rooster<br>komende 8 weken</div></div>
        </div></div></a>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        @if(actieve_rol() === 'admin')
        <a href="{{ route('admin.medewerkers', ['ontbreekt' => 1]) }}" class="text-decoration-none text-reset">
        @endif
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon" style="background: {{ $ontbreekt->count() ? '#fd7e14' : '#198754' }}"><i class="bi bi-person-exclamation"></i></div>
            <div><div class="kpi-value">{{ $ontbreekt->count() }}</div><div class="kpi-label">medewerkers met<br>ontbrekende gegevens</div></div>
        </div></div>
        @if(actieve_rol() === 'admin')
        </a>
        @endif
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar3 me-2 text-boels"></i>Komende 4 weken</span>
                <a href="{{ route('overzicht.bezetting') }}" class="small">Alle weken <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle" style="font-size:.85rem">
                    <thead><tr><th>Week</th>
                        @foreach($soorten as $s)<th>{{ $s->naam }}</th>@endforeach
                    </tr></thead>
                    <tbody>
                    @foreach($komende as $w)
                        <tr>
                            <td class="text-nowrap">
                                <a href="{{ route('rooster.week', [$w['jaar'], $w['week']]) }}" class="fw-semibold text-decoration-none">wk {{ $w['week'] }}</a><br>
                                <span class="text-muted small">{{ $w['van']->format('d-m') }} – {{ $w['tm']->format('d-m') }}</span>
                                @if(! $w['heeft_rooster']) <span class="badge bg-danger">geen rooster</span> @endif
                            </td>
                            @foreach($soorten as $s)
                                @php($cel = $w['cellen'][$s->id])
                                <td>
                                    @if($cel['status'] === 'open')
                                        <span class="text-danger fw-semibold">OPEN</span>
                                    @else
                                        @foreach($cel['toewijzingen'] as $t)
                                            <div class="{{ $t->medewerker_id ? '' : 'text-warning fw-semibold' }}" title="{{ $t->medewerker_id ? '' : 'niet gekoppeld aan een medewerker' }}">{{ $t->naam() }}@if(! $t->heleWeek()) <small class="text-muted">({{ \App\Services\Weekindeling::dagNaam($t->dag_van, true) }}–{{ \App\Services\Weekindeling::dagNaam($t->dag_tm, true) }})</small>@endif</div>
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-arrow-left-right me-2 text-boels"></i>Recentste ruilingen</span>
                <a href="{{ route('overzicht.ruilingen') }}" class="small">Alle ruilingen <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                @if($recent->isEmpty())
                    <p class="text-muted p-3 mb-0">Nog geen ruilingen.</p>
                @else
                <ul class="list-group list-group-flush">
                    @foreach($recent as $r)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="small">{{ $r->omschrijving }}</div>
                                @include('overzicht._ruilstatus', ['status' => $r->status, 'label' => $r->statusLabel()])
                            </div>
                            <div class="text-muted" style="font-size:.75rem">aangevraagd {{ $r->created_at->format('d-m-Y H:i') }} door {{ $r->aangevraagdDoor?->naam ?? '—' }}@if($r->bevestigd_op) · bevestigd {{ $r->bevestigd_op->format('d-m-Y H:i') }}@endif</div>
                        </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>
</div>

@if($ontbreekt->count() || count($wekenZonderRooster))
<div class="row g-3 mt-1">
    @if(count($wekenZonderRooster))
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-x me-2 text-danger"></i>Weken zonder rooster</div>
            <div class="card-body small">
                @foreach($wekenZonderRooster as $w)
                    <span class="badge bg-light text-dark border me-1 mb-1">week {{ $w['week'] }} ({{ $w['van']->format('d-m') }} – {{ $w['tm']->format('d-m-Y') }})</span>
                @endforeach
                @if(actieve_rol() === 'admin')<div class="mt-2"><a href="{{ route('admin.import') }}" class="btn btn-sm btn-boels"><i class="bi bi-upload me-1"></i>Rooster importeren</a></div>@endif
            </div>
        </div>
    </div>
    @endif
    @if($ontbreekt->count())
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-exclamation me-2 text-warning"></i>Ontbrekende gegevens</div>
            <div class="card-body small">
                <table class="table table-sm mb-0">
                    @foreach($ontbreekt as $m)
                        <tr><td>{{ $m->naam }}</td><td>@foreach($m->ontbreekt() as $o)<span class="badge bg-danger me-1">{{ $o }}</span>@endforeach</td><td class="text-muted">{{ $m->uitCore() ? 'CORE' : 'handmatig' }}</td></tr>
                    @endforeach
                </table>
                @if(actieve_rol() === 'admin')<div class="mt-2"><a href="{{ route('admin.medewerkers', ['ontbreekt' => 1]) }}" class="btn btn-sm btn-outline-boels">Naar medewerkers</a></div>@endif
            </div>
        </div>
    </div>
    @endif
</div>
@endif
@endsection
