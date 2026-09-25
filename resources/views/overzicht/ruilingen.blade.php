@extends('layouts.app')
@section('titel', 'Ruilingen')
@push('head')
<style>
    .staaf { display: flex; align-items: center; gap: 8px; }
    .staaf .balk { height: 10px; border-radius: 5px; background: var(--boels-orange); min-width: 2px; }
    .staaf .balk.grijs { background: #ced4da; }
    .kolommen { display: flex; align-items: flex-end; gap: 10px; height: 140px; padding-top: 10px; }
    .kolommen .kolom { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 40px; }
    .kolommen .kolom .balk { width: 100%; max-width: 46px; background: #ffd1b3; border-radius: 4px 4px 0 0; position: relative; }
    .kolommen .kolom .balk .vol { position: absolute; left: 0; right: 0; bottom: 0; background: var(--boels-orange); border-radius: 4px 4px 0 0; }
    .kolommen .kolom .lbl { font-size: .7rem; color: #6c757d; margin-top: 4px; white-space: nowrap; }
    .kolommen .kolom .num { font-size: .75rem; font-weight: 600; }
</style>
@endpush
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-arrow-left-right me-2 text-boels"></i>Ruilingen</h1>
        <p>Alle ruilverzoeken met status en doorlooptijd (van aanvraag tot bevestiging).</p>
    </div>
    <div class="d-flex gap-2">
        @if(actieve_rol() === 'admin')<a href="{{ route('admin.ruilingen') }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-gear me-1"></i>Ruilingen beheren</a>@endif
        <a href="{{ route('overzicht.export', ['wat' => 'ruilingen'] + array_filter($filters, fn ($v) => $v !== '')) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
</div>

<form method="get" class="card mb-4"><div class="card-body">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Aangevraagd van</label><input type="date" name="van" value="{{ $filters['van'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">t/m</label><input type="date" name="tot" value="{{ $filters['tot'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Status</label>
            <select name="status" class="form-select form-select-sm"><option value="">alle</option>
                @foreach($statussen as $k => $l)<option value="{{ $k }}" {{ $filters['status'] === $k ? 'selected' : '' }}>{{ $l }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-3"><label class="form-label small mb-1">Medewerker</label>
            <select name="medewerker" class="form-select form-select-sm"><option value="">alle</option>
                @foreach($medewerkers as $m)<option value="{{ $m->id }}" {{ (string) $filters['medewerker'] === (string) $m->id ? 'selected' : '' }}>{{ $m->naam }}</option>@endforeach
            </select></div>
        <div class="col-12 col-md-3 d-flex gap-2">
            <button class="btn btn-sm btn-boels"><i class="bi bi-funnel me-1"></i>Filteren</button>
            <a href="{{ route('overzicht.ruilingen', ['van' => '']) }}" class="btn btn-sm btn-outline-secondary">Alles</a>
        </div>
    </div>
</div></form>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon"><i class="bi bi-list-ol"></i></div>
            <div><div class="kpi-value">{{ $lijst->count() }}</div><div class="kpi-label">ruilverzoeken in selectie</div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon" style="background:#198754"><i class="bi bi-check2-circle"></i></div>
            <div><div class="kpi-value">{{ $stat['aantal_bevestigd'] }}</div><div class="kpi-label">bevestigd</div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card kpi-tile h-100"><div class="kpi-body">
            <div class="kpi-icon" style="background:#6c757d"><i class="bi bi-stopwatch"></i></div>
            <div><div class="kpi-value">{{ \App\Http\Controllers\ManagerController::uren($stat['gem_doorlooptijd']) }}</div><div class="kpi-label">gemiddelde doorlooptijd<br>tot bevestiging</div></div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body small">
            <div class="fw-semibold mb-1">Per status</div>
            @forelse($stat['per_status'] as $s => $n)
                <div class="d-flex justify-content-between"><span>@include('overzicht._ruilstatus', ['status' => $s, 'label' => $statussen[$s] ?? $s])</span><span class="fw-semibold">{{ $n }}</span></div>
            @empty
                <span class="text-muted">—</span>
            @endforelse
        </div></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-people me-2 text-boels"></i>Per medewerker (bevestigde ruilingen)</div>
            <div class="card-body">
                @if(! $stat['per_medewerker'])
                    <p class="text-muted mb-0">Geen bevestigde ruilingen in de selectie.</p>
                @else
                <table class="table table-sm mb-0 align-middle small">
                    <thead><tr><th>Naam</th><th class="text-end">Gegeven</th><th class="text-end">Ontvangen</th><th class="text-end">Geruild</th><th style="width:35%">Totaal</th></tr></thead>
                    <tbody>
                    @foreach($stat['per_medewerker'] as $p)
                        <tr>
                            <td>{{ $p['naam'] }}</td>
                            <td class="text-end">{{ $p['gegeven'] }}</td>
                            <td class="text-end">{{ $p['ontvangen'] }}</td>
                            <td class="text-end">{{ $p['geruild'] }}</td>
                            <td><div class="staaf"><div class="balk" style="width: {{ $stat['max_medewerker'] ? round($p['totaal'] / $stat['max_medewerker'] * 100) : 0 }}%"></div><span>{{ $p['totaal'] }}</span></div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="text-muted mt-2 mb-0" style="font-size:.75rem">Gegeven = dienst (deels) overgedragen aan een collega; ontvangen = dienst overgenomen; geruild = wederzijdse ruil.</p>
                @endif
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-bar-chart me-2 text-boels"></i>Per maand (aangevraagd, <span class="text-boels">bevestigd</span> gevuld)</div>
            <div class="card-body">
                @if(! $stat['per_maand'])
                    <p class="text-muted mb-0">Geen ruilingen in de selectie.</p>
                @else
                <div class="kolommen">
                    @foreach($stat['per_maand'] as $m)
                        <div class="kolom">
                            <div class="num">{{ $m['aantal'] }}</div>
                            <div class="balk" style="height: {{ $stat['max_maand'] ? max(4, round($m['aantal'] / $stat['max_maand'] * 100)) : 4 }}px" title="{{ $m['label'] }}: {{ $m['aantal'] }} aangevraagd, {{ $m['bevestigd'] }} bevestigd">
                                <div class="vol" style="height: {{ $m['aantal'] ? round($m['bevestigd'] / $m['aantal'] * 100) : 0 }}%"></div>
                            </div>
                            <div class="lbl">{{ $m['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-table me-2 text-boels"></i>Ruilverzoeken ({{ $lijst->count() }})</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
            <thead><tr><th>#</th><th>Omschrijving</th><th>Status</th><th>Aangevraagd op</th><th>Bevestigd op</th><th>Doorlooptijd</th><th></th></tr></thead>
            <tbody>
            @forelse($lijst as $r)
                <tr>
                    <td class="text-muted">{{ $r->id }}</td>
                    <td>{{ $r->omschrijving }}@if($r->opmerking)<br><small class="text-muted"><i class="bi bi-chat-left-text me-1"></i>{{ \Illuminate\Support\Str::limit($r->opmerking, 90) }}</small>@endif</td>
                    <td>@include('overzicht._ruilstatus', ['status' => $r->status, 'label' => $r->statusLabel()])</td>
                    <td class="text-nowrap">{{ $r->created_at->format('d-m-Y H:i') }}<br><small class="text-muted">door {{ $r->aangevraagdDoor?->naam ?? '—' }}</small></td>
                    <td class="text-nowrap">{{ $r->bevestigd_op?->format('d-m-Y H:i') ?? '—' }}@if($r->afgehandeld_door && $r->status !== 'aangevraagd')<br><small class="text-muted">door {{ $r->afgehandeld_door }}</small>@endif</td>
                    <td class="text-nowrap">{{ \App\Http\Controllers\ManagerController::uren($r->doorlooptijd_uren) }}</td>
                    <td class="text-end"><a href="{{ route('ruilen.toon', $r) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted p-3">Geen ruilingen gevonden voor deze filters.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
@endsection
