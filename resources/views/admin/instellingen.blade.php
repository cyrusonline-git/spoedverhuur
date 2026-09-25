@extends('layouts.app')
@section('titel', 'Instellingen')
@section('inhoud')
@php($dagen = [1 => 'maandag', 2 => 'dinsdag', 3 => 'woensdag', 4 => 'donderdag', 5 => 'vrijdag', 6 => 'zaterdag', 7 => 'zondag'])
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-sliders me-2 text-boels"></i>Instellingen</h1>
        <p>Verzending, momenten, ontvangers en mailteksten. Wijzigingen gelden direct, ook voor de planner.</p>
    </div>
    <a href="{{ route('admin.mail') }}" class="btn btn-outline-secondary"><i class="bi bi-envelope me-1"></i>Mailcentrum</a>
</div>

@if($errors->any())
<div class="alert alert-danger">
    <strong>Niet opgeslagen.</strong> Controleer de volgende velden:
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
</div>
@endif

<div class="alert {{ $testModus ? 'alert-warning' : 'alert-light border' }} d-flex gap-3 align-items-start">
    <i class="bi bi-{{ $testModus ? 'exclamation-triangle-fill' : 'info-circle' }} fs-4"></i>
    <div>
        <strong>Testmodus</strong> — {{ $testModus ? 'staat AAN' : 'staat uit' }}.
        Zolang de testmodus aan staat gaan <u>alle</u> mails (aankondigingen, herinneringen, weeklijst, maandoverzicht, ruilverzoeken) naar het testadres
        @if($testAdres)(<strong>{{ implode(', ', $testAdres) }}</strong>)@else (<strong class="text-danger">nog niet ingesteld — er wordt dan niets verstuurd</strong>)@endif
        met <code>[TEST → echte ontvanger]</code> vóór het onderwerp. Zo controleer je de teksten en momenten zonder dat collega's of de telefooncentrale iets ontvangen.
        Zet de testmodus pas uit als alles klopt; in het verzendlog zie je per mail of hij in testmodus is verstuurd.
    </div>
</div>

<form method="post" action="{{ route('admin.instellingen.opslaan') }}" id="f-inst">
    @csrf
    <ul class="nav nav-tabs mb-3" role="tablist">
        @foreach($groepen as $groep => $velden)
        @php($fouten = collect(array_keys($velden))->filter(fn ($k) => $errors->has($k))->count())
        <li class="nav-item"><button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#groep-{{ $groep }}" type="button" role="tab">{{ $groep }}@if($fouten) <span class="badge bg-danger">{{ $fouten }}</span>@endif</button></li>
        @endforeach
    </ul>
    <div class="tab-content">
        @foreach($groepen as $groep => $velden)
        <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="groep-{{ $groep }}" role="tabpanel">
            <div class="card mb-3">
                <div class="card-body">
                    @foreach($velden as $key => [$label, $standaard, $uitleg, $type])
                    @php($huidig = old($key, $waarden[$key]))
                    @php($isMailtekst = $groep === 'Mailteksten')
                    <div class="row mb-3 pb-3 border-bottom">
                        <label class="col-lg-3 col-form-label fw-semibold" for="v-{{ $key }}">{{ $label }}</label>
                        <div class="col-lg-9">
                            @if($type === 'bool')
                                <div class="form-check form-switch fs-5 mt-1">
                                    <input type="hidden" name="{{ $key }}" value="0">
                                    <input class="form-check-input" type="checkbox" role="switch" id="v-{{ $key }}" name="{{ $key }}" value="1" @checked((int) $huidig === 1)>
                                    <label class="form-check-label fs-6" for="v-{{ $key }}">{{ (int) $huidig === 1 ? 'aan' : 'uit' }}</label>
                                </div>
                            @elseif($type === 'textarea')
                                <textarea class="form-control font-monospace @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" rows="{{ min(12, max(3, substr_count((string) $huidig, "\n") + 2)) }}">{{ $huidig }}</textarea>
                            @elseif($type === 'dag')
                                <select class="form-select @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" style="max-width:260px;">
                                    @foreach($dagen as $n => $naam)<option value="{{ $n }}" @selected((int) $huidig === $n)>{{ $naam }}</option>@endforeach
                                </select>
                            @elseif(str_starts_with($type, 'keuze:'))
                                <select class="form-select @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" style="max-width:260px;">
                                    @foreach(explode('|', substr($type, 6)) as $optie)<option value="{{ $optie }}" @selected((string) $huidig === $optie)>{{ $optie }}</option>@endforeach
                                </select>
                            @elseif($type === 'tijd')
                                <input type="time" class="form-control @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" value="{{ $huidig }}" style="max-width:160px;">
                            @elseif($type === 'getal')
                                <input type="text" inputmode="decimal" class="form-control @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" value="{{ $huidig }}" style="max-width:160px;">
                            @elseif($type === 'email_lijst')
                                <input type="text" class="form-control @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" value="{{ $huidig }}" placeholder="naam@boels.nl, ander@boels.nl">
                            @else
                                <input type="text" class="form-control @error($key) is-invalid @enderror" id="v-{{ $key }}" name="{{ $key }}" value="{{ $huidig }}">
                            @endif
                            @error($key)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            <div class="d-flex flex-wrap justify-content-between gap-2 mt-1">
                                <div class="form-text mt-0">{{ $uitleg }}@if(! $isMailtekst && $type !== 'bool' && $standaard !== '') <span class="text-muted">Standaard: <code>{{ $standaard }}</code></span>@endif</div>
                                @if($isMailtekst)
                                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="herstel('{{ $key }}')" title="Vult de oorspronkelijke tekst in (pas na Opslaan definitief)"><i class="bi bi-arrow-counterclockwise me-1"></i>Standaardtekst herstellen</button>
                                <textarea hidden id="std-{{ $key }}">{{ $standaard }}</textarea>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="sticky-bottom bg-white border-top py-3 px-3 rounded shadow-sm d-flex justify-content-between align-items-center">
        <span class="small text-muted">Alle tabbladen worden in één keer opgeslagen.</span>
        <button class="btn btn-boels btn-lg"><i class="bi bi-save me-1"></i>Opslaan</button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function herstel(key) {
    var veld = document.getElementById('v-' + key);
    var std = document.getElementById('std-' + key);
    if (veld && std) {
        veld.value = std.value;
        veld.classList.add('border-warning');
        veld.focus();
    }
}
// Schakelaars: label aan/uit meebewegen
document.querySelectorAll('.form-check-input[role=switch]').forEach(function (sw) {
    sw.addEventListener('change', function () {
        var l = sw.parentElement.querySelector('label');
        if (l) { l.textContent = sw.checked ? 'aan' : 'uit'; }
    });
});
// Tab openen via #groep-Naam in de URL (links vanuit het mailcentrum) of na een validatiefout
(function () {
    var doel = window.location.hash;
    @if($errors->any())
    var eerste = document.querySelector('.is-invalid');
    if (eerste) { doel = '#' + eerste.closest('.tab-pane').id; }
    @endif
    if (doel && doel.indexOf('#groep-') === 0) {
        var knop = document.querySelector('[data-bs-target="' + doel + '"]');
        if (knop) { bootstrap.Tab.getOrCreateInstance(knop).show(); }
    }
})();
</script>
@endpush
