@extends('layouts.app')
@section('titel', 'Mailcentrum')
@section('inhoud')
@php($wkOpties = $weken->map(fn ($w) => ['v' => $w->jaar.'-'.$w->weeknummer, 'l' => $w->label()]))
@php($volgendeV = $volgendeWeek ? $volgendeWeek->jaar.'-'.$volgendeWeek->weeknummer : ($weken->first() ? $weken->first()->jaar.'-'.$weken->first()->weeknummer : ''))
@php($dezeV = $dezeWeek ? $dezeWeek->jaar.'-'.$dezeWeek->weeknummer : ($weken->first() ? $weken->first()->jaar.'-'.$weken->first()->weeknummer : ''))

<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-envelope me-2 text-boels"></i>Mailcentrum</h1>
        <p>Automatische mails, handmatig versturen en het verzendlog.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.mail.log') }}" class="btn btn-outline-secondary"><i class="bi bi-journal-text me-1"></i>Verzendlog</a>
        <a href="{{ route('admin.instellingen') }}" class="btn btn-outline-secondary"><i class="bi bi-sliders me-1"></i>Instellingen</a>
    </div>
</div>

@if(session('mail_resultaat'))
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-list-check me-2 text-boels"></i>Resultaat van de verzending</div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Status</th><th>Ontvanger(s)</th><th>Onderwerp</th><th>Referentie</th><th>Fout / opmerking</th></tr></thead>
            <tbody>
            @foreach(session('mail_resultaat') as $r)
                <tr>
                    <td>
                        @if($r['status'] === 'verzonden')<span class="badge bg-success">verzonden</span>
                        @elseif($r['status'] === 'mislukt')<span class="badge bg-danger">mislukt</span>
                        @elseif($r['status'] === 'al_verzonden')<span class="badge bg-info text-dark">al verzonden</span>
                        @elseif($r['status'] === 'overgeslagen')<span class="badge bg-secondary">overgeslagen</span>
                        @else <span class="badge bg-light text-dark">{{ $r['status'] }}</span>
                        @endif
                        @if($r['test'])<span class="badge bg-warning text-dark ms-1">test</span>@endif
                    </td>
                    <td>{{ $r['ontvangers'] }}</td>
                    <td class="small">{{ $r['onderwerp'] }}</td>
                    <td class="small text-muted">{{ $r['referentie'] }}</td>
                    <td class="small {{ $r['status'] === 'al_verzonden' ? 'text-muted' : 'text-danger' }}">{{ $r['fout'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

@if(session('planner_log'))
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-play-circle me-2 text-boels"></i>Planner-log</div>
    <div class="card-body"><pre class="mb-0 small">{{ implode("\n", session('planner_log')) }}</pre></div>
</div>
@endif

{{-- Status --}}
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        @if($testModus)
        <div class="alert alert-warning border-warning mb-3" style="font-size:1.05rem;">
            <div class="d-flex align-items-start gap-3">
                <i class="bi bi-exclamation-triangle-fill fs-2"></i>
                <div>
                    <strong class="fs-5">TESTMODUS STAAT AAN</strong><br>
                    Alle mails gaan naar het testadres
                    @if($testAdres)<strong>{{ implode(', ', $testAdres) }}</strong>@else <strong class="text-danger">— GEEN TESTADRES INGESTELD: er wordt niets verstuurd</strong>@endif
                    met <code>[TEST → echte ontvanger]</code> in het onderwerp.<br>
                    <a href="{{ route('admin.instellingen') }}#groep-Verzenden" class="alert-link">Testmodus uitzetten of testadres wijzigen in de instellingen</a>
                </div>
            </div>
        </div>
        @else
        <div class="alert alert-success mb-3"><i class="bi bi-check-circle me-2"></i><strong>Testmodus staat uit</strong> — mails gaan naar de echte ontvangers. <a href="{{ route('admin.instellingen') }}#groep-Verzenden" class="alert-link">Instellingen</a></div>
        @endif

        <div class="card">
            <div class="card-header"><i class="bi bi-clock-history me-2 text-boels"></i>Planner (automatisch versturen)</div>
            <div class="card-body">
                <dl class="row mb-2">
                    <dt class="col-sm-5">Automatisch versturen</dt>
                    <dd class="col-sm-7">@if($automatisch)<span class="badge bg-success">AAN</span>@else <span class="badge bg-danger">UIT</span> <span class="small text-muted">— alleen handmatig via deze pagina</span>@endif</dd>
                    <dt class="col-sm-5">Laatste run via de knop</dt>
                    <dd class="col-sm-7">{{ $plannerLaatst ? \Carbon\Carbon::parse($plannerLaatst)->format('d-m-Y H:i:s') : 'nog nooit' }}</dd>
                    <dt class="col-sm-5">Laatste mail door de scheduler</dt>
                    <dd class="col-sm-7">
                        @if($laatsteScheduler){{ \Carbon\Carbon::parse($laatsteScheduler)->format('d-m-Y H:i') }}
                        @else <span class="text-muted">nog geen (draait de cronjob?)</span>
                        @endif
                    </dd>
                </dl>
                <form method="post" action="{{ route('admin.mail.planner') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-boels" onclick="return confirm('De planner doet nu precies wat de scheduler op dit moment zou doen. Doorgaan?')"><i class="bi bi-play-fill me-1"></i>Planner nu draaien</button>
                </form>
                <div class="mt-3 small">
                    <div class="fw-semibold mb-1">Cronjob in DirectAdmin (elke minuut):</div>
                    <div class="input-group input-group-sm">
                        <input type="text" class="form-control font-monospace" readonly value="{{ $cron }}" id="cronregel">
                        <button class="btn btn-outline-secondary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('cronregel').value)" title="Kopiëren"><i class="bi bi-clipboard"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-calendar-event me-2 text-boels"></i>Ingestelde momenten</span><a href="{{ route('admin.instellingen') }}#groep-Momenten" class="small">wijzigen</a></div>
            <table class="table table-sm mb-0">
                <thead><tr><th>Mail</th><th>Moment</th><th>Over</th></tr></thead>
                <tbody>
                @foreach($momenten as [$naam, $moment, $over])
                    <tr><td>{{ $naam }}</td><td class="fw-semibold">{{ $moment }}</td><td class="text-muted small">{{ $over }}</td></tr>
                @endforeach
                </tbody>
            </table>
            <div class="card-body pt-2 small text-muted">
                Tijdzone Europe/Amsterdam. Elke verzending is uniek per soort en week/medewerker: de planner en de knoppen hieronder versturen nooit dubbel, tenzij je "opnieuw versturen" aanvinkt.
            </div>
        </div>
    </div>
</div>

{{-- Verstuur nu --}}
<h2 class="h5 mb-3"><i class="bi bi-send me-2 text-boels"></i>Verstuur nu</h2>
@if($weken->isEmpty())
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Er staan nog geen weken in het rooster. <a href="{{ route('admin.import') }}" class="alert-link">Importeer eerst een rooster</a>.</div>
@endif
<div class="row g-3 mb-4">
    {{-- Aankondiging --}}
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-megaphone me-2 text-boels"></i>Aankondiging (week ervoor)</div>
            <div class="card-body">
                <p class="small text-muted">Aan iedereen die dienst heeft in de gekozen week, met agenda-item (.ics) als bijlage.</p>
                <form method="post" action="{{ route('admin.mail.verstuur') }}" id="f-aankondiging">
                    @csrf
                    <input type="hidden" name="soort" value="aankondiging">
                    <label class="form-label small">Week</label>
                    <select name="week" class="form-select mb-2" @disabled($weken->isEmpty())>
                        @foreach($wkOpties as $o)<option value="{{ $o['v'] }}" @selected($o['v'] === $volgendeV)>{{ $o['l'] }}</option>@endforeach
                    </select>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="opnieuw" value="1" id="opn-a"><label class="form-check-label small" for="opn-a">Opnieuw versturen als al verzonden</label></div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-boels" @disabled($weken->isEmpty()) onclick="return confirm('Aankondiging nu versturen?')"><i class="bi bi-send me-1"></i>Verstuur nu</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="previewVan('f-aankondiging','aankondiging')" @disabled($weken->isEmpty())><i class="bi bi-eye me-1"></i>Preview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- Dienst vandaag --}}
    <div class="col-md-6 col-xl-4">
        <div class="card h-100">
            <div class="card-header"><i class="bi bi-alarm me-2 text-boels"></i>Herinnering "dienst start vandaag"</div>
            <div class="card-body">
                <p class="small text-muted">Aan iedereen die dienst heeft in de gekozen week; normaal op de maandag om {{ setting('vandaag_tijd', '07:00') }}.</p>
                <form method="post" action="{{ route('admin.mail.verstuur') }}" id="f-vandaag">
                    @csrf
                    <input type="hidden" name="soort" value="dienst_vandaag">
                    <label class="form-label small">Week</label>
                    <select name="week" class="form-select mb-2" @disabled($weken->isEmpty())>
                        @foreach($wkOpties as $o)<option value="{{ $o['v'] }}" @selected($o['v'] === $dezeV)>{{ $o['l'] }}</option>@endforeach
                    </select>
                    <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="opnieuw" value="1" id="opn-v"><label class="form-check-label small" for="opn-v">Opnieuw versturen als al verzonden</label></div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-boels" @disabled($weken->isEmpty()) onclick="return confirm('Herinnering nu versturen?')"><i class="bi bi-send me-1"></i>Verstuur nu</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="previewVan('f-vandaag','dienst_vandaag')" @disabled($weken->isEmpty())><i class="bi bi-eye me-1"></i>Preview</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    {{-- Maandoverzicht + testmail --}}
    <div class="col-md-6 col-xl-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header"><i class="bi bi-file-earmark-spreadsheet me-2 text-boels"></i>Maandoverzicht vergoedingen</div>
            <div class="card-body">
                <p class="small text-muted mb-2">Naar {{ implode(', ', \App\Services\MailDienst::adressen('maand_adressen', 'hr@boels.nl, time@boels.com, payroll@boels.nl')) }}. Aanmaken, controleren en versturen doe je op de maandpagina.</p>
                @if($laatsteMaand)
                <div class="small mb-2">Laatste: <strong>{{ \App\Services\Weekindeling::maandNaam($laatsteMaand->maand) }} {{ $laatsteMaand->jaar }}</strong> — {{ $laatsteMaand->verzonden_op ? 'verzonden '.$laatsteMaand->verzonden_op->format('d-m-Y H:i') : 'nog niet verzonden' }}</div>
                @endif
                <a href="{{ route('admin.maand') }}" class="btn btn-outline-boels"><i class="bi bi-arrow-right me-1"></i>Naar maandoverzicht</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-bug me-2 text-boels"></i>Testmail</div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.mail.test') }}">
                    @csrf
                    <div class="input-group">
                        <input type="email" name="adres" class="form-control @error('adres') is-invalid @enderror" placeholder="naam@boels.nl" value="{{ old('adres', $testAdres[0] ?? '') }}" required>
                        <button class="btn btn-boels"><i class="bi bi-send me-1"></i>Stuur</button>
                    </div>
                    @error('adres')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-text">Controleert of de mailserver werkt.@if($testModus) In testmodus komt ook deze mail op het testadres aan.@endif</div>
                </form>
            </div>
        </div>
    </div>
    {{-- Multiline --}}
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-telephone me-2 text-boels"></i>Weeklijst naar de telefooncentrale (Multiline)</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-4">
                        <p class="small text-muted">Naar {{ implode(', ', \App\Services\MailDienst::adressen('multiline_adres', 'meldingenttr@multiline-antwoordservice.nl')) }}@if(\App\Services\MailDienst::adressen('multiline_cc')), cc {{ implode(', ', \App\Services\MailDienst::adressen('multiline_cc')) }}@endif. Normaal op {{ \App\Services\Weekindeling::dagNaam((int) setting('multiline_dag', 1)) }} {{ setting('multiline_tijd', '07:00') }}.</p>
                        <form method="get" action="{{ route('admin.mail') }}" class="mb-3" id="f-ml-kies">
                            <label class="form-label small">Week (tabel rechts toont deze week)</label>
                            <div class="input-group">
                                <select name="ml" class="form-select" onchange="mlKies(this)" @disabled($weken->isEmpty())>
                                    @foreach($wkOpties as $o)<option value="{{ $o['v'] }}" @selected($mlWeek && $o['v'] === $mlWeek->jaar.'-'.$mlWeek->weeknummer)>{{ $o['l'] }}</option>@endforeach
                                </select>
                            </div>
                            <input type="hidden" name="ml_jaar" value="{{ $mlWeek?->jaar }}"><input type="hidden" name="ml_week" value="{{ $mlWeek?->weeknummer }}">
                        </form>
                        <form method="post" action="{{ route('admin.mail.verstuur') }}" id="f-multiline">
                            @csrf
                            <input type="hidden" name="soort" value="multiline">
                            <input type="hidden" name="week" value="{{ $mlWeek ? $mlWeek->jaar.'-'.$mlWeek->weeknummer : '' }}">
                            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="opnieuw" value="1" id="opn-m"><label class="form-check-label small" for="opn-m">Opnieuw versturen als al verzonden</label></div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-boels" @disabled(! $mlWeek) onclick="return confirm('Weeklijst nu naar de telefooncentrale sturen?')"><i class="bi bi-send me-1"></i>Verstuur nu</button>
                                <a class="btn btn-outline-secondary @if(! $mlWeek) disabled @endif" href="{{ $mlWeek ? route('admin.mail.preview', ['soort' => 'multiline', 'jaar' => $mlWeek->jaar, 'week' => $mlWeek->weeknummer]) : '#' }}"><i class="bi bi-eye me-1"></i>Preview mail</a>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-8">
                        @if($mlWeek)
                        <div class="small fw-semibold mb-1">{{ $mlWeek->label() }}</div>
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead><tr><th>Dienst</th><th>Naam</th><th>Mobiel</th></tr></thead>
                                <tbody>
                                @forelse($mlLijst as $r)
                                    <tr>
                                        <td>{{ $r['dienst'] }}</td>
                                        <td>{{ $r['naam'] }}@if($r['dagen']) <span class="text-muted">({{ $r['dagen'] }})</span>@endif</td>
                                        <td>@if($r['telefoon']){{ $r['telefoon'] }}@else <span class="text-danger">onbekend</span>@endif</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="text-muted">Geen dienstsoorten die naar Multiline gaan.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @else
                        <div class="text-muted">Geen week beschikbaar.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Laatste verzendingen --}}
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center"><span><i class="bi bi-journal-text me-2 text-boels"></i>Laatste 10 verzendingen</span><a href="{{ route('admin.mail.log') }}" class="small">volledig log</a></div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Tijd</th><th>Soort</th><th>Ontvangers</th><th>Onderwerp</th><th>Status</th><th>Door</th></tr></thead>
            <tbody>
            @forelse($recent as $t)
                <tr>
                    <td class="small text-nowrap">{{ $t->created_at->format('d-m-Y H:i') }}</td>
                    <td class="small">{{ $soorten[$t->soort] ?? $t->soort }}</td>
                    <td class="small">{{ implode(', ', $t->ontvangers ?? []) }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($t->onderwerp, 70) }}</td>
                    <td>
                        @if($t->status === 'verzonden')<span class="badge bg-success">verzonden</span>
                        @elseif($t->status === 'mislukt')<span class="badge bg-danger" title="{{ $t->fout }}">mislukt</span>
                        @elseif($t->status === 'overgeslagen')<span class="badge bg-secondary" title="{{ $t->fout }}">overgeslagen</span>
                        @else <span class="badge bg-light text-dark">{{ $t->status }}</span>
                        @endif
                        @if($t->test_modus)<span class="badge bg-warning text-dark">test</span>@endif
                    </td>
                    <td class="small text-muted">{{ $t->aangemaakt_door }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted">Nog niets verzonden.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
function previewVan(formId, soort) {
    var f = document.getElementById(formId);
    var wk = f.querySelector('select[name=week]').value;
    window.location = '{{ route('admin.mail.preview') }}?soort=' + encodeURIComponent(soort) + '&wk=' + encodeURIComponent(wk);
}
function mlKies(sel) {
    var d = sel.value.split('-');
    var f = document.getElementById('f-ml-kies');
    f.querySelector('input[name=ml_jaar]').value = d[0];
    f.querySelector('input[name=ml_week]').value = d[1];
    sel.disabled = true; // niet meesturen
    f.submit();
}
</script>
@endpush
