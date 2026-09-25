@extends('layouts.app')
@section('titel', 'Start')
@section('inhoud')
@php($naam = core_gebruiker()['name'] ?? 'daar')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 page-header">
    <div>
        <h1><i class="bi bi-speedometer2 me-2 text-boels"></i>Welkom, {{ $naam }}</h1>
        <p>Week {{ $weeknr }} · {{ now('Europe/Amsterdam')->format('d-m-Y') }} · rol: {{ rol_naam($rol) }}</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('rooster.week', [$jaar, $weeknr]) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-calendar-week me-1"></i>Deze week</a>
        <a href="{{ route('rooster') }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-calendar3 me-1"></i>Rooster</a>
        @if($mw)<a href="{{ route('mijn-diensten') }}" class="btn btn-sm btn-boels"><i class="bi bi-person-check me-1"></i>Mijn diensten</a>@endif
    </div>
</div>

@if(in_array($rol, ['manager', 'admin']))
    {{-- KPI-tegels --}}
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('overzicht.bezetting') }}" class="text-decoration-none text-reset">
            <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ count($openPlekken) ? '#dc3545' : '#198754' }}"><i class="bi bi-person-dash"></i></div><div><div class="kpi-value">{{ count($openPlekken) }}</div><div class="kpi-label">open plekken komende 8 weken</div></div></div></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('overzicht.ruilingen') }}" class="text-decoration-none text-reset">
            <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#0dcaf0"><i class="bi bi-hourglass-split"></i></div><div><div class="kpi-value">{{ $openRuilingen }}</div><div class="kpi-label">open ruilverzoeken</div></div></div></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('overzicht.ruilingen') }}" class="text-decoration-none text-reset">
            <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-arrow-left-right"></i></div><div><div class="kpi-value">{{ $ruilingen30 }}</div><div class="kpi-label">ruilingen laatste 30 dagen</div></div></div></div>
            </a>
        </div>
        <div class="col-sm-6 col-xl-3">
            <a href="{{ route('rooster') }}" class="text-decoration-none text-reset">
            <div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ count($zonderRooster) ? '#ffc107' : '#198754' }}"><i class="bi bi-calendar-x"></i></div><div><div class="kpi-value">{{ count($zonderRooster) }}</div><div class="kpi-label">weken zonder rooster (komende 8)</div></div></div></div>
            </a>
        </div>
    </div>
    @if(count($openPlekken) || count($zonderRooster))
    <div class="alert alert-warning small">
        @if(count($zonderRooster))<div><i class="bi bi-calendar-x me-1"></i><strong>Geen rooster:</strong> @foreach($zonderRooster as $z)<a href="{{ route('rooster.week', [$z['jaar'], $z['week']]) }}" class="alert-link">week {{ $z['week'] }}</a>@if(! $loop->last), @endif @endforeach</div>@endif
        @if(count($openPlekken))<div><i class="bi bi-person-dash me-1"></i><strong>Open plekken:</strong>
            @foreach(array_slice($openPlekken, 0, 12) as $p)<a href="{{ route('rooster.week', [$p['jaar'], $p['week']]) }}" class="alert-link">wk {{ $p['week'] }}</a> {{ $p['soort'] }} ({{ $p['reden'] }})@if(! $loop->last); @endif @endforeach
            @if(count($openPlekken) > 12) … en {{ count($openPlekken) - 12 }} meer — zie <a href="{{ route('overzicht.bezetting') }}" class="alert-link">bezetting</a>.@endif
        </div>@endif
    </div>
    @endif
    <p class="small"><a href="{{ route('overzicht.index') }}"><i class="bi bi-bar-chart-line me-1"></i>Managementoverzicht</a> · <a href="{{ route('overzicht.bezetting') }}">Bezetting</a> · <a href="{{ route('overzicht.ruilingen') }}">Ruilingen</a> · <a href="{{ route('overzicht.belasting') }}">Wie draait hoeveel</a> · <a href="{{ route('overzicht.audit') }}">Logboek</a></p>
@endif

@if($rol === 'admin')
    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-activity me-2 text-boels"></i>Status</div>
                <div class="card-body small">
                    @if($testModus)
                        <div class="alert alert-warning py-2 mb-2"><i class="bi bi-envelope-exclamation me-1"></i><strong>Testmodus staat aan:</strong> alle mails gaan naar het testadres. Zet dit uit bij <a href="{{ route('admin.instellingen') }}" class="alert-link">Instellingen</a> zodra de app live is.</div>
                    @endif
                    <div class="mb-2"><i class="bi bi-upload me-1 text-muted"></i><strong>Laatste import:</strong>
                        @if($laatsteImport) {{ $laatsteImport->bestandsnaam }} — {{ $laatsteImport->aantal_weken }} weken, {{ $laatsteImport->aantal_diensten }} diensten, {{ $laatsteImport->created_at->format('d-m-Y H:i') }} door {{ $laatsteImport->door_naam ?: '—' }}
                        @else <span class="text-danger">nog geen rooster geïmporteerd</span> — <a href="{{ route('admin.import') }}">importeren</a>@endif
                    </div>
                    <div><i class="bi bi-person-exclamation me-1 text-muted"></i><strong>Onvolledige medewerkers ({{ $onvolledig->count() }}):</strong>
                        @if($onvolledig->isEmpty()) <span class="text-success">alles compleet</span>
                        @else
                            <ul class="mb-0">
                                @foreach($onvolledig->take(8) as $m)<li>{{ $m->naam }} <span class="text-muted">— mist {{ implode(', ', $m->ontbreekt()) }}</span></li>@endforeach
                                @if($onvolledig->count() > 8)<li class="text-muted">… en {{ $onvolledig->count() - 8 }} meer</li>@endif
                            </ul>
                            <a href="{{ route('admin.medewerkers') }}">Medewerkers &amp; koppelingen</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-envelope me-2 text-boels"></i>Laatste mailtaken <a href="{{ route('admin.mail.log') }}" class="small fw-normal ms-2">alles</a></div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 small">
                        <thead><tr><th>Soort</th><th>Referentie</th><th>Status</th><th>Wanneer</th></tr></thead>
                        <tbody>
                            @forelse($mailTaken as $mt)
                                <tr>
                                    <td>{{ \App\Models\MailTaak::SOORTEN[$mt->soort] ?? $mt->soort }}</td>
                                    <td>{{ $mt->referentie }}</td>
                                    <td><span class="badge bg-{{ ['verzonden' => 'success', 'mislukt' => 'danger', 'gepland' => 'secondary', 'overgeslagen' => 'warning'][$mt->status] ?? 'secondary' }}">{{ $mt->status }}</span>@if($mt->test_modus) <span class="badge bg-warning text-dark">test</span>@endif</td>
                                    <td>{{ ($mt->verzonden_op ?? $mt->created_at)?->format('d-m H:i') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-muted">Nog geen mails verstuurd.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endif

@if($rol === 'medewerker' && ! $mw)
    <div class="alert alert-warning"><i class="bi bi-person-x me-2"></i>Je account is nog niet aan een roosterpersoon gekoppeld; vraag de beheerder om je te koppelen. Het rooster kun je wel inzien.</div>
@endif

<div class="row g-3">
    @if($mw)
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-check me-2 text-boels"></i>Jouw komende diensten <span class="text-muted small fw-normal">(8 weken)</span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr><th>Week</th><th>Dienst</th><th>Dagen</th><th></th></tr></thead>
                    <tbody>
                        @forelse($mijnKomend as $t)
                            <tr>
                                <td><a href="{{ route('rooster.week', [$t->week->jaar, $t->week->weeknummer]) }}">Week {{ $t->week->weeknummer }}</a><br><span class="text-muted small">{{ $t->week->datumVanDag($t->dag_van)->format('d-m') }} – {{ $t->week->datumVanDag($t->dag_tm)->format('d-m') }}</span></td>
                                <td>{{ $t->soort?->naam }} @include('rooster._oorsprong', ['t' => $t])</td>
                                <td>@if($t->heleWeek())hele week @else {{ \App\Services\Weekindeling::dagNaam($t->dag_van, true) }}–{{ \App\Services\Weekindeling::dagNaam($t->dag_tm, true) }} @endif</td>
                                <td class="text-end">@if($t->dienst_id)<a href="{{ route('ruilen.nieuw', ['dienst' => $t->dienst_id]) }}" class="btn btn-sm btn-outline-boels">Ruilen</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted">Geen diensten in de komende 8 weken.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-arrow-left-right me-2 text-boels"></i>Ruilverzoeken die op jou wachten</div>
            <div class="card-body">
                @forelse($mijnRuilingen as $r)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <div class="small">
                            <strong>{{ $r->typeLabel() }}</strong> — {{ $r->van?->naam }} → {{ $r->naar?->naam }}<br>
                            <span class="text-muted">{{ $r->dienst?->soort?->naam }} week {{ $r->dienst?->week?->weeknummer }}@if($r->tegenDienst) ⇄ {{ $r->tegenDienst->soort?->naam }} week {{ $r->tegenDienst->week?->weeknummer }}@endif · aangevraagd {{ $r->created_at->format('d-m') }}</span>
                        </div>
                        <a href="{{ route('ruilen.toon', $r) }}" class="btn btn-sm btn-boels">Bekijken</a>
                    </div>
                @empty
                    <p class="text-muted mb-0">Er wachten geen ruilverzoeken op jouw bevestiging.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-week me-2 text-boels"></i>Deze week (week {{ $weeknr }}) — wie heeft dienst <a href="{{ route('rooster.week', [$jaar, $weeknr]) }}" class="small fw-normal ms-2">details &amp; telefoon</a></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Dienstsoort</th><th>Wie</th><th>Dagen</th></tr></thead>
                    <tbody>
                        @forelse($dezeWeekToewijzingen as $t)
                            <tr class="{{ $mw && $t->medewerker_id === $mw->id ? 'table-warning' : '' }}">
                                <td>{{ $t->soort?->naam }}</td>
                                <td>@if($t->medewerker_id === null)<span class="text-boels fw-semibold">{{ $t->rooster_naam }}</span> <span class="badge bg-warning text-dark">niet gekoppeld</span>@else{{ $t->naam() }}@endif @include('rooster._oorsprong', ['t' => $t])</td>
                                <td>@if($t->heleWeek())hele week @else {{ \App\Services\Weekindeling::dagNaam($t->dag_van) }} t/m {{ \App\Services\Weekindeling::dagNaam($t->dag_tm) }} @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">Geen rooster voor deze week.</td></tr>
                        @endforelse
                        @foreach($vast as $s)
                            <tr><td>{{ $s->naam }}</td><td class="fst-italic">{{ $s->vaste_naam }}</td><td>hele week</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });</script>
@endpush
