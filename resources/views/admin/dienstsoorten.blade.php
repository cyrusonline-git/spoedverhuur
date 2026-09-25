@extends('layouts.app')
@section('titel', 'Dienstsoorten')
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-list-check me-2 text-boels"></i>Dienstsoorten</h1>
        <p>De kolommen van het Excel-rooster (kolomkop = exacte kop in het bestand, voor herkenning bij import) en de vaste tweede lijn h&amp;h.</p>
    </div>
</div>

<form method="post" action="{{ route('admin.dienstsoorten.opslaan') }}">@csrf
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-table me-2 text-boels"></i>Roosterkolommen</div>
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle" style="font-size:.85rem">
            <thead><tr><th style="width:70px">Volgorde</th><th>Naam</th><th>Kolomkop in Excel</th><th class="text-center">Betaald</th><th class="text-center">Naar Multiline</th><th class="text-center">Actief</th><th class="text-end">Diensten</th></tr></thead>
            <tbody>
            @foreach($soorten->where('vast', false) as $s)
                <tr class="{{ $s->actief ? '' : 'table-light text-muted' }}">
                    <td><input type="number" name="rij[{{ $s->id }}][volgorde]" value="{{ $s->volgorde }}" class="form-control form-control-sm" min="0"></td>
                    <td><input type="text" name="rij[{{ $s->id }}][naam]" value="{{ $s->naam }}" class="form-control form-control-sm" required></td>
                    <td><input type="text" name="rij[{{ $s->id }}][kolom_kop]" value="{{ $s->kolom_kop }}" class="form-control form-control-sm font-monospace" placeholder="(zelfde als naam)"></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="rij[{{ $s->id }}][betaald]" value="1" {{ $s->betaald ? 'checked' : '' }} title="telt mee in de vergoedingen"></div></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="rij[{{ $s->id }}][naar_multiline]" value="1" {{ $s->naar_multiline ? 'checked' : '' }} title="staat op de weeklijst naar de telefooncentrale"></div></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="rij[{{ $s->id }}][actief]" value="1" {{ $s->actief ? 'checked' : '' }}></div></td>
                    <td class="text-end text-muted">{{ $s->diensten_count }}</td>
                </tr>
            @endforeach
                <tr class="table-warning">
                    <td><input type="number" name="nieuw[volgorde]" class="form-control form-control-sm" min="0" placeholder="{{ (int) $soorten->where('vast', false)->max('volgorde') + 1 }}"></td>
                    <td><input type="text" name="nieuw[naam]" class="form-control form-control-sm" placeholder="Nieuwe dienstsoort…"></td>
                    <td><input type="text" name="nieuw[kolom_kop]" class="form-control form-control-sm font-monospace" placeholder="(zelfde als naam)"></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="nieuw[betaald]" value="1" checked></div></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="nieuw[naar_multiline]" value="1" checked></div></td>
                    <td class="text-center"><div class="form-check form-switch d-inline-block"><input class="form-check-input" type="checkbox" name="nieuw[actief]" value="1" checked></div></td>
                    <td class="text-end text-muted"><small>nieuw</small></td>
                </tr>
            </tbody>
        </table>
        </div>
    </div>
    <div class="card-footer bg-white small text-muted">
        <i class="bi bi-info-circle me-1"></i>Betaald = telt mee in het maandoverzicht vergoedingen (weekbedrag ÷ 7 × dagen). Naar Multiline = staat op de weeklijst voor de telefooncentrale. Inactief = niet meer in het rooster, historie blijft bewaard. De rij is verwijderbaar door hem inactief te zetten.
    </div>
</div>

@foreach($soorten->where('vast', true) as $s)
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-person-badge me-2 text-boels"></i>Vaste tweede lijn h&amp;h</div>
    <div class="card-body">
        <p class="small text-muted mb-3"><i class="bi bi-info-circle me-1"></i>Tweede lijn h&amp;h: staat niet in het rooster, wordt niet betaald, krijgt optioneel de meldingen (dezelfde aankondigings- en herinneringsmails als de dienstdoenden) en staat op de weeklijst naar de telefooncentrale als "Naar Multiline" aan staat.</p>
        <div class="row g-2">
            <div class="col-md-3"><label class="form-label small mb-1">Naam van de dienst</label><input type="text" name="rij[{{ $s->id }}][naam]" value="{{ $s->naam }}" class="form-control form-control-sm" required></div>
            <div class="col-md-3"><label class="form-label small mb-1">Vaste persoon</label><input type="text" name="rij[{{ $s->id }}][vaste_naam]" value="{{ $s->vaste_naam }}" class="form-control form-control-sm" placeholder="naam"></div>
            <div class="col-md-2"><label class="form-label small mb-1">Telefoon</label><input type="text" name="rij[{{ $s->id }}][vaste_telefoon]" value="{{ $s->vaste_telefoon }}" class="form-control form-control-sm" placeholder="06-…"></div>
            <div class="col-md-3"><label class="form-label small mb-1">E-mail</label><input type="email" name="rij[{{ $s->id }}][vaste_email]" value="{{ $s->vaste_email }}" class="form-control form-control-sm"></div>
            <div class="col-md-1"><label class="form-label small mb-1">Volgorde</label><input type="number" name="rij[{{ $s->id }}][volgorde]" value="{{ $s->volgorde }}" class="form-control form-control-sm" min="0"></div>
        </div>
        <div class="d-flex flex-wrap gap-4 mt-3">
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rij[{{ $s->id }}][vaste_meldingen]" value="1" id="vm{{ $s->id }}" {{ $s->vaste_meldingen ? 'checked' : '' }}><label class="form-check-label small" for="vm{{ $s->id }}">Krijgt de meldingen (aankondiging &amp; herinnering per mail)</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rij[{{ $s->id }}][naar_multiline]" value="1" id="nm{{ $s->id }}" {{ $s->naar_multiline ? 'checked' : '' }}><label class="form-check-label small" for="nm{{ $s->id }}">Naar Multiline (op de weeklijst)</label></div>
            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="rij[{{ $s->id }}][actief]" value="1" id="ac{{ $s->id }}" {{ $s->actief ? 'checked' : '' }}><label class="form-check-label small" for="ac{{ $s->id }}">Actief</label></div>
            <div class="small text-muted align-self-center"><i class="bi bi-cash-coin me-1"></i>Betaald: nee (vast)</div>
        </div>
    </div>
</div>
@endforeach

<div class="d-flex gap-2">
    <button class="btn btn-boels"><i class="bi bi-check me-1"></i>Alles opslaan</button>
    <a href="{{ route('admin.dienstsoorten') }}" class="btn btn-outline-secondary">Ongedaan maken</a>
</div>
</form>
@endsection
