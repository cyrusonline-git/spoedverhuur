@extends('layouts.app')
@section('titel', 'Nieuw ruilverzoek')
@section('inhoud')
@php($richting = old('richting', 'geven'))
@php($type = old('type', 'ruil'))
<div class="page-header mb-3">
    <h1><i class="bi bi-arrow-left-right me-2 text-boels"></i>Nieuw ruilverzoek</h1>
    <p>Je collega krijgt een mail met een bevestiglink. Pas na bevestiging wordt het rooster aangepast.</p>
</div>

@if($errors->any())
    <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i><strong>Het verzoek is niet ingediend:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <ul class="nav nav-pills" id="richting-tabs">
                    <li class="nav-item"><button type="button" class="nav-link {{ $richting === 'geven' ? 'active' : '' }}" data-richting="geven"><i class="bi bi-box-arrow-right me-1"></i>Mijn dienst ruilen of afstaan</button></li>
                    <li class="nav-item"><button type="button" class="nav-link {{ $richting === 'overnemen' ? 'active' : '' }}" data-richting="overnemen"><i class="bi bi-box-arrow-in-left me-1"></i>Dienst van collega overnemen</button></li>
                </ul>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('ruilen.aanvragen') }}" id="ruilform">
                    @csrf
                    <input type="hidden" name="richting" id="richting" value="{{ $richting }}">

                    {{-- ===== Richting: ik geef mijn dienst (ruil / overname / deel) ===== --}}
                    <div id="blok-geven">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Mijn dienst</label>
                            @if($mijnDiensten->isEmpty())
                                <div class="alert alert-warning mb-0">Je hebt geen toekomstige diensten in het rooster.</div>
                            @else
                                <select name="dienst_id" id="dienst_id" class="form-select">
                                    <option value="">— kies je dienst —</option>
                                    @foreach($mijnDiensten as $d)
                                        <option value="{{ $d->id }}" {{ $geselecteerd === $d->id ? 'selected' : '' }}>{{ \App\Http\Controllers\RuilController::dienstLabel($d) }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Wat wil je?</label>
                            <div class="list-group">
                                <label class="list-group-item d-flex gap-3 {{ $type === 'ruil' ? 'border-warning' : '' }}">
                                    <input type="radio" name="type" value="ruil" class="form-check-input flex-shrink-0 type-radio" {{ $type === 'ruil' ? 'checked' : '' }}>
                                    <span><strong>Ruilen</strong> <span class="text-muted">— wij wisselen twee diensten: de collega neemt mijn week, ik neem een week van de collega.</span></span>
                                </label>
                                <label class="list-group-item d-flex gap-3">
                                    <input type="radio" name="type" value="overname" class="form-check-input flex-shrink-0 type-radio" {{ $type === 'overname' ? 'checked' : '' }}>
                                    <span><strong>Overname</strong> <span class="text-muted">— de collega neemt mijn hele dienst over; ik krijg er niets voor terug.</span></span>
                                </label>
                                <label class="list-group-item d-flex gap-3">
                                    <input type="radio" name="type" value="deel" class="form-check-input flex-shrink-0 type-radio" {{ $type === 'deel' ? 'checked' : '' }}>
                                    <span><strong>Dagen delen</strong> <span class="text-muted">— de collega neemt een deel van de dagen over (bijv. het weekend); de rest blijft bij mij.</span></span>
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Collega</label>
                            <select name="naar_medewerker_id" id="naar_medewerker_id" class="form-select">
                                <option value="">— kies een collega —</option>
                                @foreach($collegas as $c)
                                    <option value="{{ $c->id }}" {{ (int) old('naar_medewerker_id') === $c->id ? 'selected' : '' }}>{{ $c->naam }}{{ $c->emailEffectief() ? ' · '.$c->emailEffectief() : ' · (geen e-mail bekend!)' }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">De collega ontvangt het verzoek per mail op dit adres.</div>
                        </div>

                        <div class="mb-3" id="blok-tegen">
                            <label class="form-label fw-semibold">Tegendienst van de collega <span class="text-muted fw-normal">(alleen bij ruilen)</span></label>
                            <select name="tegen_dienst_id" id="tegen_dienst_id" class="form-select" data-old="{{ old('tegen_dienst_id') }}">
                                <option value="">— kies eerst een collega —</option>
                            </select>
                            <div class="form-text" id="tegen-hint">De dienst van de collega die jij in ruil overneemt.</div>
                        </div>

                        <div class="mb-3" id="blok-deel">
                            <label class="form-label fw-semibold">Welke dagen gaan naar de collega? <span class="text-muted fw-normal">(alleen bij dagen delen)</span></label>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <div class="input-group"><span class="input-group-text">van</span>
                                        <select name="dagen_van" id="dagen_van" class="form-select">
                                            @foreach($dagNamen as $nr => $naam)<option value="{{ $nr }}" {{ (int) old('dagen_van', 6) === $nr ? 'selected' : '' }}>{{ $naam }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-6">
                                    <div class="input-group"><span class="input-group-text">t/m</span>
                                        <select name="dagen_tm" id="dagen_tm" class="form-select">
                                            @foreach($dagNamen as $nr => $naam)<option value="{{ $nr }}" {{ (int) old('dagen_tm', 7) === $nr ? 'selected' : '' }}>{{ $naam }}</option>@endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text" id="deel-hint">Alleen dagen binnen je dienst zijn te kiezen. Kies je alle dagen, dan wordt het een overname.</div>
                        </div>
                    </div>

                    {{-- ===== Richting: ik neem een dienst van een collega over ===== --}}
                    <div id="blok-overnemen">
                        <div class="alert alert-light border small"><i class="bi bi-info-circle me-1 text-boels"></i>Je vraagt hiermee aan een collega of jij zijn/haar dienst mag overnemen. De collega bevestigt; daarna staat de dienst op jouw naam.</div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Collega</label>
                            <select name="collega_id" id="collega_id" class="form-select">
                                <option value="">— kies een collega —</option>
                                @foreach($collegas as $c)
                                    <option value="{{ $c->id }}" {{ (int) old('collega_id') === $c->id ? 'selected' : '' }}>{{ $c->naam }}{{ $c->emailEffectief() ? ' · '.$c->emailEffectief() : ' · (geen e-mail bekend!)' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Dienst van de collega die ik wil overnemen</label>
                            <select name="collega_dienst_id" id="collega_dienst_id" class="form-select" data-old="{{ old('collega_dienst_id') }}">
                                <option value="">— kies eerst een collega —</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Opmerking <span class="text-muted fw-normal">(optioneel, gaat mee in de mail)</span></label>
                        <textarea name="opmerking" class="form-control" rows="2" maxlength="1000" placeholder="Bijv. reden of afspraak">{{ old('opmerking') }}</textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button class="btn btn-boels" {{ $mijnDiensten->isEmpty() && $richting === 'geven' ? 'disabled' : '' }} id="verstuur"><i class="bi bi-send me-1"></i>Verzoek versturen</button>
                        <a href="{{ route('ruilen') }}" class="btn btn-light">Annuleren</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mt-3 mt-lg-0">
        <div class="card">
            <div class="card-header"><i class="bi bi-question-circle me-2 text-boels"></i>Hoe werkt het?</div>
            <div class="card-body small">
                <ol class="ps-3 mb-2">
                    <li>Jij dient het verzoek in.</li>
                    <li>Je collega krijgt een mail met de knoppen <em>Bevestigen</em> en <em>Afwijzen</em> (of doet het in de app onder Ruilen).</li>
                    <li>Na bevestiging past het rooster zich direct aan; de nieuwe dienstdoende krijgt voortaan de aankondigingen en de vergoeding.</li>
                </ol>
                <p class="mb-1">Een verzoek vervalt automatisch na {{ setting('ruil_verval_dagen', 3) }} dagen zonder antwoord. Zolang het open staat kun je het intrekken.</p>
                <p class="mb-0 text-muted">Ingelogd als roosterpersoon <strong>{{ $eigen->naam }}</strong>.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var dagen = {!! $dagenJson !!};
    var dienstenUrl = '{{ route('ruilen.diensten-van', ['medewerker' => '__ID__']) }}';
    var dagNamen = ['', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];

    var richtingInput = document.getElementById('richting');
    var blokGeven = document.getElementById('blok-geven');
    var blokOvernemen = document.getElementById('blok-overnemen');
    var blokTegen = document.getElementById('blok-tegen');
    var blokDeel = document.getElementById('blok-deel');
    var verstuur = document.getElementById('verstuur');
    var heeftDiensten = {{ $mijnDiensten->isEmpty() ? 'false' : 'true' }};

    function typeWaarde() {
        var r = document.querySelector('.type-radio:checked');
        return r ? r.value : 'ruil';
    }

    function toonRichting(richting) {
        richtingInput.value = richting;
        blokGeven.style.display = richting === 'geven' ? '' : 'none';
        blokOvernemen.style.display = richting === 'overnemen' ? '' : 'none';
        verstuur.disabled = (richting === 'geven' && !heeftDiensten);
        document.querySelectorAll('#richting-tabs .nav-link').forEach(function (b) {
            b.classList.toggle('active', b.getAttribute('data-richting') === richting);
        });
        toonType();
    }

    function toonType() {
        var t = typeWaarde();
        blokTegen.style.display = t === 'ruil' ? '' : 'none';
        blokDeel.style.display = t === 'deel' ? '' : 'none';
        document.querySelectorAll('.type-radio').forEach(function (r) {
            r.closest('.list-group-item').classList.toggle('border-warning', r.checked);
        });
        beperkDagen();
    }

    // Dagen-selects beperken tot de dagen van de gekozen eigen dienst
    function beperkDagen() {
        var sel = document.getElementById('dienst_id');
        if (!sel) { return; }
        var grens = dagen[sel.value] || [1, 7];
        ['dagen_van', 'dagen_tm'].forEach(function (id) {
            var s = document.getElementById(id);
            Array.prototype.forEach.call(s.options, function (o) {
                var nr = parseInt(o.value, 10);
                o.disabled = nr < grens[0] || nr > grens[1];
            });
            var v = parseInt(s.value, 10);
            if (v < grens[0]) { s.value = String(grens[0]); }
            if (v > grens[1]) { s.value = String(grens[1]); }
        });
        var hint = document.getElementById('deel-hint');
        hint.textContent = 'Je dienst loopt van ' + dagNamen[grens[0]] + ' t/m ' + dagNamen[grens[1]] + '. Kies je alle dagen, dan wordt het een overname.';
    }

    // Diensten van een collega ophalen (JSON) en in een select zetten
    function laadDiensten(medewerkerId, select, leeg) {
        select.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = medewerkerId ? 'laden…' : leeg;
        select.appendChild(opt);
        if (!medewerkerId) { return; }
        fetch(dienstenUrl.replace('__ID__', medewerkerId), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                select.innerHTML = '';
                var o0 = document.createElement('option');
                o0.value = '';
                o0.textContent = data.diensten.length ? '— kies een dienst —' : 'Deze collega heeft geen toekomstige diensten';
                select.appendChild(o0);
                var oud = select.getAttribute('data-old') || '';
                data.diensten.forEach(function (d) {
                    var o = document.createElement('option');
                    o.value = d.id;
                    o.textContent = d.label;
                    if (String(d.id) === oud) { o.selected = true; }
                    select.appendChild(o);
                });
                select.setAttribute('data-old', '');
            })
            .catch(function () {
                select.innerHTML = '<option value="">Ophalen mislukt; probeer opnieuw</option>';
            });
    }

    document.querySelectorAll('#richting-tabs .nav-link').forEach(function (b) {
        b.addEventListener('click', function () { toonRichting(b.getAttribute('data-richting')); });
    });
    document.querySelectorAll('.type-radio').forEach(function (r) { r.addEventListener('change', toonType); });
    var dienstSel = document.getElementById('dienst_id');
    if (dienstSel) { dienstSel.addEventListener('change', beperkDagen); }

    var naarSel = document.getElementById('naar_medewerker_id');
    var tegenSel = document.getElementById('tegen_dienst_id');
    naarSel.addEventListener('change', function () { laadDiensten(naarSel.value, tegenSel, '— kies eerst een collega —'); });
    if (naarSel.value) { laadDiensten(naarSel.value, tegenSel, '— kies eerst een collega —'); }

    var collegaSel = document.getElementById('collega_id');
    var collegaDienstSel = document.getElementById('collega_dienst_id');
    collegaSel.addEventListener('change', function () { laadDiensten(collegaSel.value, collegaDienstSel, '— kies eerst een collega —'); });
    if (collegaSel.value) { laadDiensten(collegaSel.value, collegaDienstSel, '— kies eerst een collega —'); }

    toonRichting(richtingInput.value || 'geven');
})();
</script>
@endpush
