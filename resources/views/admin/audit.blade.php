@extends('layouts.app')
@section('titel', 'Logboek')
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-journal-text me-2 text-boels"></i>Logboek</h1>
        <p>Alle wijzigingen in rooster, ruilingen, medewerkers, instellingen en verzendingen — nieuwste eerst.</p>
    </div>
</div>

<form method="get" class="card mb-4"><div class="card-body">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Actie</label>
            <input type="text" name="actie" list="acties" value="{{ $filters['actie'] }}" class="form-control form-control-sm" placeholder="bv. ruiling.">
            <datalist id="acties">@foreach($acties as $a)<option value="{{ $a }}">@endforeach</datalist>
        </div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Wie</label>
            <input type="text" name="wie" list="namen" value="{{ $filters['wie'] }}" class="form-control form-control-sm">
            <datalist id="namen">@foreach($namen as $n)<option value="{{ $n }}">@endforeach</datalist>
        </div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Van</label><input type="date" name="van" value="{{ $filters['van'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">t/m</label><input type="date" name="tot" value="{{ $filters['tot'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1">Zoek in onderwerp/details</label><input type="text" name="zoek" value="{{ $filters['zoek'] }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2 d-flex gap-2">
            <button class="btn btn-sm btn-boels"><i class="bi bi-funnel me-1"></i>Filteren</button>
            <a href="{{ route('overzicht.audit') }}" class="btn btn-sm btn-outline-secondary">Wis</a>
        </div>
    </div>
</div></form>

<div class="card">
    <div class="card-header d-flex justify-content-between"><span>{{ $lijst->total() }} regels</span><span class="text-muted small fw-normal">pagina {{ $lijst->currentPage() }} van {{ max(1, $lijst->lastPage()) }}</span></div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
            <thead><tr><th>Wanneer</th><th>Wie</th><th>Actie</th><th>Onderwerp</th><th>Details</th></tr></thead>
            <tbody>
            @forelse($lijst as $a)
                <tr>
                    <td class="text-nowrap">{{ $a->created_at->format('d-m-Y H:i:s') }}</td>
                    <td class="text-nowrap">{{ $a->wie ?? '—' }}</td>
                    <td><a href="{{ route('overzicht.audit', ['actie' => $a->actie]) }}" class="badge bg-light text-dark border text-decoration-none">{{ $a->actie }}</a></td>
                    <td>{{ $a->onderwerp }}</td>
                    <td>
                        @if($a->details)
                            <a class="small text-decoration-none" data-bs-toggle="collapse" href="#det{{ $a->id }}"><i class="bi bi-chevron-down me-1"></i>toon</a>
                            <div class="collapse" id="det{{ $a->id }}"><pre class="bg-light p-2 rounded mt-1 mb-0" style="font-size:.75rem; max-width: 600px; white-space: pre-wrap;">{{ json_encode($a->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                        @if($a->ip)<span class="text-muted ms-2" style="font-size:.7rem">{{ $a->ip }}</span>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted p-3">Geen logregels gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @if($lijst->hasPages())
    <div class="card-footer bg-white d-flex justify-content-center">{{ $lijst->links() }}</div>
    @endif
</div>
@endsection
