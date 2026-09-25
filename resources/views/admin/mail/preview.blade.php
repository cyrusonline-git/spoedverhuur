@extends('layouts.app')
@section('titel', 'Preview '.($soorten[$soort] ?? $soort))
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-eye me-2 text-boels"></i>Preview: {{ $soorten[$soort] ?? $soort }}</h1>
        <p>{{ $week->label() }} — dit is wat er verstuurd zou worden. Er is niets verzonden.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.mail') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Mailcentrum</a>
        <form method="post" action="{{ route('admin.mail.verstuur') }}" class="d-flex gap-2 align-items-center">
            @csrf
            <input type="hidden" name="soort" value="{{ $soort }}">
            <input type="hidden" name="week" value="{{ $week->jaar }}-{{ $week->weeknummer }}">
            <div class="form-check mb-0"><input class="form-check-input" type="checkbox" name="opnieuw" value="1" id="opn"><label class="form-check-label small" for="opn">Opnieuw als al verzonden</label></div>
            <button class="btn btn-boels" onclick="return confirm('Nu versturen?')"><i class="bi bi-send me-1"></i>Verstuur nu</button>
        </form>
    </div>
</div>

@if($testModus)
<div class="alert alert-warning"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Testmodus:</strong> in werkelijkheid gaan al deze mails naar {{ $testAdres ? implode(', ', $testAdres) : 'het testadres (NIET INGESTELD — er wordt niets verstuurd)' }} met <code>[TEST → echte ontvanger]</code> in het onderwerp.</div>
@endif

@if($soort === 'multiline')
<div class="card mb-4">
    <div class="card-header"><i class="bi bi-envelope me-2 text-boels"></i>Mail aan de telefooncentrale</div>
    <div class="card-body">
        <dl class="row mb-3">
            <dt class="col-sm-2">Aan</dt><dd class="col-sm-10">{{ implode(', ', $aan) ?: '— GEEN ADRES —' }}</dd>
            @if($cc)<dt class="col-sm-2">CC</dt><dd class="col-sm-10">{{ implode(', ', $cc) }}</dd>@endif
            <dt class="col-sm-2">Onderwerp</dt><dd class="col-sm-10">{{ $onderwerp }}</dd>
            <dt class="col-sm-2">Status</dt>
            <dd class="col-sm-10">
                @if($bestaand)
                    @if($bestaand->status === 'verzonden')<span class="badge bg-success">al verzonden</span> op {{ $bestaand->verzonden_op?->format('d-m-Y H:i') }} @if($bestaand->test_modus)<span class="badge bg-warning text-dark">test</span>@endif — wordt overgeslagen tenzij "opnieuw" aangevinkt
                    @else <span class="badge bg-secondary">{{ $bestaand->status }}</span> {{ $bestaand->fout }}
                    @endif
                @else <span class="badge bg-light text-dark">nog niet verzonden</span>
                @endif
            </dd>
        </dl>
        <div class="border rounded p-3 bg-white">{!! $html !!}</div>
    </div>
</div>
@else
@if(empty($ontvangers))
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Geen toewijzingen in deze week: er zou niets verstuurd worden.</div>
@endif
<div class="mb-3 small text-muted">{{ count($ontvangers) }} ontvanger(s). @if(! empty($ontvangers) && $ontvangers[0]['cc'])CC bij elke mail: {{ implode(', ', $ontvangers[0]['cc']) }}.@endif</div>
<div class="accordion" id="preview-acc">
@foreach($ontvangers as $i => $o)
    @php($b = $bestaand[$o['referentie']] ?? null)
    <div class="accordion-item">
        <h2 class="accordion-header">
            <button class="accordion-button {{ $i > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#pv-{{ $i }}">
                <span class="me-3 fw-semibold">{{ $o['naam'] }}</span>
                <span class="me-3 text-muted small">{{ $o['dienst'] }}</span>
                @if($o['email'])<span class="me-3 small">{{ $o['email'] }}</span>@else <span class="badge bg-danger me-3">GEEN E-MAIL</span>@endif
                @if($b && $b->status === 'verzonden')<span class="badge bg-success me-2" title="{{ $b->verzonden_op?->format('d-m-Y H:i') }}">al verzonden</span>
                @elseif($b)<span class="badge bg-secondary me-2" title="{{ $b->fout }}">{{ $b->status }}</span>
                @endif
                @if($o['ics'])<span class="badge bg-light text-dark border"><i class="bi bi-calendar-plus me-1"></i>.ics</span>@endif
            </button>
        </h2>
        <div id="pv-{{ $i }}" class="accordion-collapse collapse {{ $i === 0 ? 'show' : '' }}" data-bs-parent="#preview-acc">
            <div class="accordion-body">
                <div class="mb-2"><span class="text-muted small">Onderwerp:</span> <strong>{{ $o['onderwerp'] }}</strong></div>
                <div class="text-muted small mb-1">Referentie: {{ $o['referentie'] }}</div>
                <pre class="bg-light rounded p-3 mb-0" style="white-space:pre-wrap;font-family:inherit;">{{ $o['tekst'] }}</pre>
            </div>
        </div>
    </div>
@endforeach
</div>
@endif
@endsection
