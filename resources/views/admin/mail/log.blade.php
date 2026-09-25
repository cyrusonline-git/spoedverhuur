@extends('layouts.app')
@section('titel', 'Verzendlog')
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-journal-text me-2 text-boels"></i>Verzendlog</h1>
        <p>Alle mails die de app heeft verstuurd (of geprobeerd te versturen).</p>
    </div>
    <a href="{{ route('admin.mail') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Mailcentrum</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="{{ route('admin.mail.log') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Soort</label>
                <select name="soort" class="form-select form-select-sm">
                    <option value="">— alle —</option>
                    @foreach($soorten as $k => $l)<option value="{{ $k }}" @selected(($filter['soort'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">— alle —</option>
                    @foreach($statussen as $k => $l)<option value="{{ $k }}" @selected(($filter['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2"><label class="form-label small mb-1">Van</label><input type="date" name="van" class="form-control form-control-sm" value="{{ $filter['van'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label small mb-1">T/m</label><input type="date" name="tm" class="form-control form-control-sm" value="{{ $filter['tm'] ?? '' }}"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Zoek (adres/onderwerp/referentie)</label><input type="text" name="zoek" class="form-control form-control-sm" value="{{ $filter['zoek'] ?? '' }}"></div>
            <div class="col-md-1 d-flex gap-1">
                <button class="btn btn-sm btn-boels w-100"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('admin.mail.log') }}" class="btn btn-sm btn-outline-secondary" title="Wis filters"><i class="bi bi-x"></i></a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle">
            <thead><tr><th>Tijd</th><th>Soort</th><th>Referentie</th><th>Ontvangers</th><th>Onderwerp</th><th>Status</th><th>Fout</th><th>Door</th><th></th></tr></thead>
            <tbody>
            @forelse($taken as $t)
                <tr>
                    <td class="small text-nowrap">{{ $t->created_at->format('d-m-Y H:i') }}@if($t->verzonden_op && $t->verzonden_op->ne($t->created_at))<br><span class="text-muted">verz. {{ $t->verzonden_op->format('d-m H:i') }}</span>@endif</td>
                    <td class="small">{{ $soorten[$t->soort] ?? $t->soort }}</td>
                    <td class="small text-muted font-monospace">{{ $t->referentie }}</td>
                    <td class="small">{{ implode(', ', $t->ontvangers ?? []) ?: '—' }}@if($t->cc)<br><span class="text-muted">cc {{ implode(', ', $t->cc) }}</span>@endif</td>
                    <td class="small" title="{{ $t->onderwerp }}">{{ \Illuminate\Support\Str::limit($t->onderwerp, 60) }}@if($t->bijlage_pad) <i class="bi bi-paperclip text-muted" title="{{ basename($t->bijlage_pad) }}"></i>@endif</td>
                    <td class="text-nowrap">
                        @if($t->status === 'verzonden')<span class="badge bg-success">verzonden</span>
                        @elseif($t->status === 'mislukt')<span class="badge bg-danger">mislukt</span>
                        @elseif($t->status === 'overgeslagen')<span class="badge bg-secondary">overgeslagen</span>
                        @else <span class="badge bg-light text-dark">{{ $t->status }}</span>
                        @endif
                        @if($t->test_modus)<span class="badge bg-warning text-dark" title="In testmodus verstuurd: naar het testadres">test</span>@endif
                    </td>
                    <td class="small text-danger" style="max-width:220px;">{{ $t->fout }}</td>
                    <td class="small text-muted">{{ $t->aangemaakt_door }}</td>
                    <td class="text-end">
                        @if(in_array($t->soort, $herhaalbaar, true))
                        <form method="post" action="{{ route('admin.mail.opnieuw', ['taak' => $t->id] + request()->query()) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-boels" title="Dezelfde mail nog eens versturen" onclick="return confirm('Deze mail opnieuw versturen?')"><i class="bi bi-arrow-repeat"></i> Opnieuw</button>
                        </form>
                        @else
                        <span class="text-muted small" title="Wordt vanuit het ruilproces verstuurd">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-muted p-3">Geen verzendingen gevonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($taken->hasPages())
    <div class="card-body pt-2">{{ $taken->links() }}</div>
    @endif
</div>
<div class="small text-muted mt-2">{{ $taken->total() }} regel(s).</div>
@endsection
