@extends('layouts.app')
@section('titel', 'Medewerkers & koppelingen')
@push('head')
<style>
    .alias-chip { display: inline-flex; align-items: center; gap: 4px; background: #f1f3f5; border: 1px solid #dee2e6; border-radius: 12px; padding: 1px 6px 1px 9px; font-size: .75rem; margin: 0 4px 4px 0; }
    .alias-chip button { border: 0; background: none; padding: 0 2px; line-height: 1; color: #999; }
    .alias-chip button:hover { color: #dc3545; }
    .handmatig { text-decoration: underline dotted #fd7e14; text-underline-offset: 3px; }
</style>
@endpush
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-people me-2 text-boels"></i>Medewerkers &amp; koppelingen</h1>
        <p>{{ $totaal }} medewerkers ({{ $aantalCore }} uit CORE), {{ $aantalOntbreekt }} met ontbrekende gegevens. Laatst opgehaald uit CORE: {{ $gesynct ? $gesynct->format('d-m-Y H:i') : 'nog nooit' }}.</p>
    </div>
    <div class="d-flex gap-2">
        <form method="post" action="{{ route('admin.medewerkers.sync') }}">@csrf<button class="btn btn-boels btn-sm"><i class="bi bi-cloud-download me-1"></i>Nu ophalen uit CORE</button></form>
        <button type="button" class="btn btn-outline-boels btn-sm" data-bs-toggle="modal" data-bs-target="#nieuw"><i class="bi bi-person-plus me-1"></i>Handmatig toevoegen</button>
    </div>
</div>

@if($errors->any())
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ $errors->first() }}</div>
@endif

<form method="get" class="card mb-3"><div class="card-body py-2">
    <div class="row g-2 align-items-center">
        <div class="col-md-4"><input type="search" name="zoek" value="{{ $zoek }}" class="form-control form-control-sm" placeholder="Zoek op naam, e-mail, personeelsnummer of schrijfwijze…"></div>
        <div class="col-auto form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="ontbreekt" value="1" id="f-ontbreekt" {{ request()->boolean('ontbreekt') ? 'checked' : '' }} onchange="this.form.submit()"><label class="form-check-label small" for="f-ontbreekt">alleen met ontbrekende gegevens</label></div>
        <div class="col-auto form-check form-switch ms-2"><input class="form-check-input" type="checkbox" name="rooster" value="1" id="f-rooster" {{ request()->boolean('rooster') ? 'checked' : '' }} onchange="this.form.submit()"><label class="form-check-label small" for="f-rooster">alleen roosterpersonen (met diensten)</label></div>
        <div class="col-auto"><button class="btn btn-sm btn-outline-secondary"><i class="bi bi-search"></i></button> @if($zoek !== '' || request()->boolean('ontbreekt') || request()->boolean('rooster'))<a href="{{ route('admin.medewerkers') }}" class="btn btn-sm btn-link">wis</a>@endif</div>
    </div>
</div></form>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
            <thead><tr><th>Naam</th><th>Bron</th><th>E-mail</th><th>Telefoon</th><th>Pers.nr</th><th>Ontbreekt</th><th>Actief</th><th class="text-end">Diensten {{ $jaar }}</th><th>Schrijfwijzen in het rooster</th><th></th></tr></thead>
            <tbody>
            @forelse($lijst as $m)
                <tr class="{{ $m->actief ? '' : 'text-muted' }}">
                    <td class="fw-semibold text-nowrap">{{ $m->naam }}</td>
                    <td>@if($m->uitCore())<span class="badge bg-boels">CORE</span>@else<span class="badge bg-secondary">handmatig</span>@endif</td>
                    <td>@if($m->email){{ $m->email }}@elseif($m->email_handmatig)<span class="handmatig" title="handmatig veld (staat niet in CORE)">{{ $m->email_handmatig }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td class="text-nowrap">@if($m->telefoon){{ $m->telefoon }}@elseif($m->telefoon_handmatig)<span class="handmatig" title="handmatig veld (staat niet in CORE)">{{ $m->telefoon_handmatig }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td>@if($m->personeelsnummer){{ $m->personeelsnummer }}@elseif($m->personeelsnummer_handmatig)<span class="handmatig" title="handmatig veld (staat niet in CORE)">{{ $m->personeelsnummer_handmatig }}</span>@else<span class="text-muted">—</span>@endif</td>
                    <td>@foreach($m->ontbreekt() as $o)<span class="badge bg-danger me-1">{{ $o }}</span>@endforeach</td>
                    <td>@if($m->actief)<i class="bi bi-check-circle-fill text-success"></i>@else<i class="bi bi-x-circle text-muted"></i> <small>nee</small>@endif</td>
                    <td class="text-end">{{ $m->diensten_dit_jaar }} <small class="text-muted">/ {{ $m->toewijzingen_count }} totaal</small></td>
                    <td>
                        @foreach($m->aliassen as $al)
                            <span class="alias-chip">{{ $al->alias_origineel }}
                                <form method="post" action="{{ route('admin.medewerkers.alias.verwijder', $al) }}" class="d-inline" onsubmit="return confirm('Schrijfwijze &quot;{{ $al->alias_origineel }}&quot; verwijderen?')">@csrf @method('DELETE')<button type="submit" title="verwijderen"><i class="bi bi-x"></i></button></form>
                            </span>
                        @endforeach
                        <form method="post" action="{{ route('admin.medewerkers.alias', $m) }}" class="d-inline-flex gap-1 mt-1">@csrf
                            <input type="text" name="alias" class="form-control form-control-sm" style="width: 150px; font-size:.75rem" placeholder="alias toevoegen…" required>
                            <button class="btn btn-sm btn-outline-boels py-0" title="schrijfwijze koppelen"><i class="bi bi-plus"></i></button>
                        </form>
                    </td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#bewerk{{ $m->id }}" title="bewerken"><i class="bi bi-pencil"></i></button></td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-muted p-3">Geen medewerkers gevonden. @if($totaal === 0)Klik op "Nu ophalen uit CORE" om de medewerkerkaarten op te halen.@endif</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>

{{-- Bewerk-modals --}}
@foreach($lijst as $m)
<div class="modal fade" id="bewerk{{ $m->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('admin.medewerkers.bijwerken', $m) }}" class="modal-content">@csrf
            <div class="modal-header"><h5 class="modal-title">{{ $m->naam }} @if($m->uitCore())<span class="badge bg-boels ms-1">CORE</span>@else<span class="badge bg-secondary ms-1">handmatig</span>@endif</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                @if($m->uitCore())
                    <div class="alert alert-info small py-2">
                        <i class="bi bi-info-circle me-1"></i>E-mail, telefoon en personeelsnummer komen uit de CORE-medewerkerkaart; pas ze daar aan:
                        <a href="https://databasehub.sorai.nl/admin/employees" target="_blank" rel="noopener">CORE → Medewerkers <i class="bi bi-box-arrow-up-right"></i></a>.
                        De handmatige velden hieronder gelden alleen zolang het CORE-veld leeg is.
                        <div class="mt-1 text-muted">CORE: {{ $m->email ?: '— geen e-mail' }} · {{ $m->telefoon ?: '— geen telefoon' }} · {{ $m->personeelsnummer ?: '— geen personeelsnummer' }}</div>
                    </div>
                @else
                    <div class="mb-2"><label class="form-label small mb-1">Naam</label><input type="text" name="naam" value="{{ $m->naam }}" class="form-control form-control-sm" required></div>
                @endif
                <div class="mb-2"><label class="form-label small mb-1">E-mail (handmatig)</label><input type="email" name="email_handmatig" value="{{ $m->email_handmatig }}" class="form-control form-control-sm" {{ $m->email ? 'placeholder=CORE: '.$m->email : '' }}></div>
                <div class="mb-2"><label class="form-label small mb-1">Telefoon (handmatig)</label><input type="text" name="telefoon_handmatig" value="{{ $m->telefoon_handmatig }}" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label small mb-1">Personeelsnummer (handmatig)</label><input type="text" name="personeelsnummer_handmatig" value="{{ $m->personeelsnummer_handmatig }}" class="form-control form-control-sm"></div>
                <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="actief" value="1" id="actief{{ $m->id }}" {{ $m->actief ? 'checked' : '' }}><label class="form-check-label" for="actief{{ $m->id }}">Actief (kan ingeroosterd worden en ruilen)</label></div>
                @if($m->uitCore())<div class="form-text">Let op: bij de volgende synchronisatie wordt "actief" weer overgenomen uit CORE.</div>@endif
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuleren</button><button class="btn btn-boels btn-sm"><i class="bi bi-check me-1"></i>Opslaan</button></div>
        </form>
    </div>
</div>
@endforeach

{{-- Nieuwe medewerker --}}
<div class="modal fade" id="nieuw" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="{{ route('admin.medewerkers.opslaan') }}" class="modal-content">@csrf
            <div class="modal-header"><h5 class="modal-title">Medewerker handmatig toevoegen</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Alleen voor wie (nog) niet in CORE staat. Zodra dezelfde naam in CORE verschijnt, wordt deze medewerker daaraan gekoppeld en gaan de CORE-gegevens voor.</p>
                <div class="mb-2"><label class="form-label small mb-1">Naam <span class="text-danger">*</span></label><input type="text" name="naam" value="{{ old('naam') }}" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label small mb-1">E-mail</label><input type="email" name="email_handmatig" value="{{ old('email_handmatig') }}" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label small mb-1">Telefoon</label><input type="text" name="telefoon_handmatig" value="{{ old('telefoon_handmatig') }}" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label small mb-1">Personeelsnummer</label><input type="text" name="personeelsnummer_handmatig" value="{{ old('personeelsnummer_handmatig') }}" class="form-control form-control-sm"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Annuleren</button><button class="btn btn-boels btn-sm"><i class="bi bi-person-plus me-1"></i>Toevoegen</button></div>
        </form>
    </div>
</div>
@if(old('naam') !== null)
@push('scripts')<script>new bootstrap.Modal(document.getElementById('nieuw')).show();</script>@endpush
@endif
@endsection
