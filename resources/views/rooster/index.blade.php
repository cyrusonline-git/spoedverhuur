@extends('layouts.app')
@section('titel', 'Rooster '.$jaar)
@push('head')
<style>
    .rooster-tabel td, .rooster-tabel th { vertical-align: top; font-size: .85rem; white-space: nowrap; }
    .rooster-tabel .week-huidig { background: #fff3e8 !important; }
    .rooster-tabel .week-huidig td:first-child { border-left: 4px solid var(--boels-orange); }
    .rooster-tabel .week-verleden { color: #999; }
    .rooster-tabel .week-leeg td { background: #fafafa; }
    .rooster-tabel .eigen { background: #ffe3cf; border-radius: 6px; padding: 1px 6px; font-weight: 600; }
    .rooster-tabel .ongekoppeld { color: #c85200; font-weight: 600; border-bottom: 1px dotted #c85200; cursor: help; }
    .rooster-tabel .dagen { color: #777; font-size: .75rem; }
    .rooster-tabel .vast { color: #555; font-style: italic; }
    .rooster-tabel th.kolom { white-space: normal; min-width: 120px; }
    .rooster-tabel td.weekcel a { text-decoration: none; font-weight: 600; }
</style>
@endpush
@section('inhoud')
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 page-header">
    <div>
        <h1><i class="bi bi-calendar3 me-2 text-boels"></i>Rooster {{ $jaar }}</h1>
        <p>Effectief rooster (na bevestigde ruilingen). Klik op een week voor details en telefoonnummers.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <form method="get" action="{{ route('rooster') }}" class="d-flex gap-2 align-items-center">
            <select name="jaar" class="form-select form-select-sm" onchange="this.form.submit()">
                @foreach($jaren as $j)
                    <option value="{{ $j }}" {{ $j === $jaar ? 'selected' : '' }}>{{ $j }}</option>
                @endforeach
            </select>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" role="switch" id="komend" name="komend" value="1" {{ $komend ? 'checked' : '' }} onchange="this.form.submit()">
                <label class="form-check-label small" for="komend">Alleen komende weken</label>
            </div>
        </form>
        <form method="get" action="{{ route('wie') }}" class="d-flex gap-1">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Wie draait wanneer? (naam)" style="min-width: 200px">
            <button class="btn btn-sm btn-boels" type="submit"><i class="bi bi-search"></i></button>
        </form>
        <a href="{{ route('rooster.week', [$huidigJaar, $huidigeWeek]) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-calendar-event me-1"></i>Deze week</a>
    </div>
</div>

@if(empty($rijen))
    <div class="alert alert-info">Geen weken om te tonen. Zet "Alleen komende weken" uit of kies een ander jaar.</div>
@else
<div class="card">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 rooster-tabel">
            <thead>
                <tr>
                    <th>Week</th>
                    <th>Van – t/m</th>
                    @foreach($soorten as $s)
                        <th class="kolom">{{ $s->naam }}@if($s->vast) <i class="bi bi-pin-angle text-muted" title="Vaste persoon (niet uit het Excel-rooster)"></i>@endif</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rijen as $r)
                    <tr class="{{ $r['huidig'] ? 'week-huidig' : '' }} {{ $r['tm']->lt(now('Europe/Amsterdam')->startOfDay()) ? 'week-verleden' : '' }} {{ $r['model'] ? '' : 'week-leeg' }}">
                        <td class="weekcel"><a href="{{ route('rooster.week', [$jaar, $r['week']]) }}">Week {{ $r['week'] }}</a>@if($r['huidig']) <span class="badge bg-boels">nu</span>@endif</td>
                        <td class="text-muted">{{ $r['van']->format('d-m') }} – {{ $r['tm']->format('d-m') }}</td>
                        @foreach($soorten as $s)
                            <td>
                                @if($s->vast)
                                    <span class="vast">{{ $s->vaste_naam ?: '—' }}</span>
                                @elseif(! $r['model'])
                                    <span class="text-muted">—</span>
                                @else
                                    @forelse($r['cellen'][$s->id] ?? [] as $t)
                                        <div>
                                            @if($t->medewerker_id === null)
                                                <span class="ongekoppeld" title="Niet gekoppeld aan een medewerker: '{{ $t->rooster_naam }}'" data-bs-toggle="tooltip">{{ $t->rooster_naam ?: '?' }}</span>
                                            @elseif($eigenId && $t->medewerker_id === $eigenId)
                                                <span class="eigen" title="Jouw dienst">{{ $t->naam() }}</span>
                                            @else
                                                {{ $t->naam() }}
                                            @endif
                                            @if(! $t->heleWeek())
                                                <span class="dagen">{{ \App\Services\Weekindeling::dagNaam($t->dag_van, true) }}–{{ \App\Services\Weekindeling::dagNaam($t->dag_tm, true) }}</span>
                                            @endif
                                            @include('rooster._oorsprong', ['t' => $t])
                                        </div>
                                    @empty
                                        <span class="text-danger" title="Niemand ingeroosterd">open</span>
                                    @endforelse
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<p class="small text-muted mt-2">
    <span class="eigen px-1" style="background:#ffe3cf;border-radius:6px">naam</span> = jouw dienst ·
    <span class="ongekoppeld" style="color:#c85200;font-weight:600">naam</span> = niet gekoppeld aan een medewerker ·
    <span class="badge bg-info"><i class="bi bi-arrow-left-right"></i></span> geruild ·
    <span class="badge bg-info"><i class="bi bi-person-down"></i></span> overgenomen ·
    <span class="badge bg-info"><i class="bi bi-scissors"></i></span> dagen gedeeld ·
    <span class="badge bg-secondary"><i class="bi bi-pencil"></i></span> handmatig
</p>
@endif
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });</script>
@endpush
