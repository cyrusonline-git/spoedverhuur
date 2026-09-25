@extends('layouts.app')
@section('titel', 'Ruilingen beheren')
@section('inhoud')
<div class="d-flex flex-wrap justify-content-between align-items-center page-header mb-3 gap-2">
    <div>
        <h1><i class="bi bi-arrow-left-right me-2 text-boels"></i>Ruilingen beheren</h1>
        <p>Alle ruilverzoeken. Een bevestigde ruiling terugdraaien zet het rooster terug zoals ervoor (reden verplicht); een open verzoek kun je namens de organisatie afwijzen.</p>
    </div>
    <a href="{{ route('ruilen.nieuw') }}" class="btn btn-outline-boels"><i class="bi bi-plus-lg me-1"></i>Nieuw ruilverzoek</a>
</div>

@if($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row g-3 mb-3">
    @php($iconen = ['aangevraagd' => 'hourglass-split', 'bevestigd' => 'check-circle', 'afgewezen' => 'x-circle', 'ingetrokken' => 'slash-circle', 'verlopen' => 'hourglass-bottom', 'teruggedraaid' => 'arrow-counterclockwise'])
    @php($kleuren = ['aangevraagd' => '#e0a800', 'bevestigd' => '#198754', 'afgewezen' => '#dc3545', 'ingetrokken' => '#6c757d', 'verlopen' => '#6c757d', 'teruggedraaid' => '#212529'])
    @foreach(\App\Models\Ruiling::STATUSSEN as $slug => $label)
        <div class="col-6 col-md-4 col-xl-2">
            <a href="{{ route('admin.ruilingen', array_merge($filter, ['status' => $filter['status'] === $slug ? '' : $slug])) }}" class="text-decoration-none">
                <div class="card kpi-tile h-100 {{ $filter['status'] === $slug ? 'border border-2' : '' }}" style="{{ $filter['status'] === $slug ? 'border-color:'.$kleuren[$slug].' !important' : '' }}">
                    <div class="kpi-body">
                        <div class="kpi-icon" style="background: {{ $kleuren[$slug] }}"><i class="bi bi-{{ $iconen[$slug] }}"></i></div>
                        <div><div class="kpi-value text-dark">{{ $stats[$slug] }}</div><div class="kpi-label">{{ $label }}</div></div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Alle ({{ $totaal }})</option>
                    @foreach(\App\Models\Ruiling::STATUSSEN as $slug => $label)<option value="{{ $slug }}" {{ $filter['status'] === $slug ? 'selected' : '' }}>{{ $label }} ({{ $stats[$slug] }})</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Medewerker (partij)</label>
                <select name="medewerker" class="form-select form-select-sm">
                    <option value="">Alle medewerkers</option>
                    @foreach($medewerkers as $m)<option value="{{ $m->id }}" {{ $filter['medewerker'] === $m->id ? 'selected' : '' }}>{{ $m->naam }}{{ $m->actief ? '' : ' (inactief)' }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Aangevraagd van</label>
                <input type="date" name="van" value="{{ $filter['van'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">t/m</label>
                <input type="date" name="tm" value="{{ $filter['tm'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-boels"><i class="bi bi-funnel me-1"></i>Filter</button>
                <a href="{{ route('admin.ruilingen') }}" class="btn btn-sm btn-light">Wis</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2 text-boels"></i>{{ $ruilingen->total() }} ruiling{{ $ruilingen->total() === 1 ? '' : 'en' }}</span>
        <small class="text-muted">nieuwste eerst</small>
    </div>
    @if($ruilingen->isEmpty())
        <div class="card-body text-muted">Geen ruilingen gevonden met deze filters.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>#</th><th>Aangevraagd</th><th>Ruiling</th><th>Door</th><th>Wacht op / afgehandeld door</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @foreach($ruilingen as $r)
                <tr>
                    <td class="text-muted small">{{ $r->id }}</td>
                    <td class="text-nowrap small text-muted">{{ $r->created_at?->format('d-m-Y H:i') }}</td>
                    <td>
                        <a href="{{ route('ruilen.toon', $r) }}" class="text-decoration-none fw-semibold text-dark">{{ $service->omschrijving($r) }}</a>
                        @if($r->dienst?->week)<div class="small text-muted">{{ $r->dienst->week->van->format('d-m') }} t/m {{ $r->dienst->week->tm->format('d-m-Y') }}</div>@endif
                        @if($r->opmerking)<div class="small text-muted fst-italic"><i class="bi bi-chat-left-text me-1"></i>{{ \Illuminate\Support\Str::limit($r->opmerking, 120) }}</div>@endif
                    </td>
                    <td class="small">{{ $r->aangevraagdDoor?->naam ?? '—' }}</td>
                    <td class="small">
                        @if($r->isOpen())
                            {{ $r->moetBevestigen()?->naam ?? '—' }}<div class="text-muted">tot {{ $r->token_verloopt_op?->format('d-m H:i') }}</div>
                        @else
                            {{ $r->afgehandeld_door ?: '—' }}
                            @if($r->bevestigd_op)<div class="text-muted">bevestigd {{ $r->bevestigd_op->format('d-m-Y H:i') }}</div>@endif
                        @endif
                    </td>
                    <td>@include('ruilen._badge', ['r' => $r])</td>
                    <td class="text-end text-nowrap">
                        @if($r->status === 'bevestigd')
                            <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#terug-{{ $r->id }}"><i class="bi bi-arrow-counterclockwise me-1"></i>Terugdraaien</button>
                            <div class="modal fade" id="terug-{{ $r->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                <form method="post" action="{{ route('admin.ruilingen.terugdraai', $r) }}">@csrf
                                    <div class="modal-header"><h5 class="modal-title">Ruiling #{{ $r->id }} terugdraaien</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body text-start">
                                        <p class="small text-muted">{{ $service->omschrijving($r) }}</p>
                                        <p class="small">Het rooster wordt teruggezet zoals vóór deze ruiling. Beide partijen krijgen een mail met de reden.</p>
                                        <label class="form-label">Reden <span class="text-danger">*</span></label>
                                        <textarea name="reden" class="form-control" rows="3" maxlength="500" required minlength="3"></textarea>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuleren</button><button class="btn btn-dark">Terugdraaien</button></div>
                                </form>
                            </div></div></div>
                        @elseif($r->isOpen())
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#afwijs-{{ $r->id }}"><i class="bi bi-x-lg me-1"></i>Afwijzen</button>
                            <div class="modal fade" id="afwijs-{{ $r->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                                <form method="post" action="{{ route('admin.ruilingen.afwijs', $r) }}">@csrf
                                    <div class="modal-header"><h5 class="modal-title">Verzoek #{{ $r->id }} afwijzen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body text-start">
                                        <p class="small text-muted">{{ $service->omschrijving($r) }}</p>
                                        <label class="form-label">Reden (optioneel, gaat mee in de mail naar de aanvrager)</label>
                                        <textarea name="reden" class="form-control" rows="3" maxlength="500"></textarea>
                                    </div>
                                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuleren</button><button class="btn btn-danger">Afwijzen</button></div>
                                </form>
                            </div></div></div>
                        @endif
                        <a href="{{ route('ruilen.toon', $r) }}" class="btn btn-sm btn-light" title="Details"><i class="bi bi-eye"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if($ruilingen->hasPages())<div class="card-body">{{ $ruilingen->links() }}</div>@endif
    @endif
</div>
@endsection
