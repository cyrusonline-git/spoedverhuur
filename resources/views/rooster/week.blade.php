@extends('layouts.app')
@section('titel', 'Week '.$week.' ('.$jaar.')')
@section('inhoud')
@php($rol = actieve_rol())
<div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3 page-header">
    <div>
        <h1><i class="bi bi-calendar-week me-2 text-boels"></i>Week {{ $week }} · {{ $van->format('d-m-Y') }} t/m {{ $tm->format('d-m-Y') }}</h1>
        <p>Jaar {{ $jaar }} · telt mee voor {{ \App\Services\Weekindeling::maandNaam($maand['maand']) }} {{ $maand['jaar'] }}@if($verleden) · <span class="badge bg-secondary">afgelopen</span>@endif</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('rooster.week', [$vorige[0], $vorige[1]]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left"></i> Week {{ $vorige[1] }}</a>
        <a href="{{ route('rooster', ['jaar' => $jaar, 'komend' => 0]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar3 me-1"></i>Jaaroverzicht</a>
        <a href="{{ route('rooster.week', [$volgende[0], $volgende[1]]) }}" class="btn btn-sm btn-outline-secondary">Week {{ $volgende[1] }} <i class="bi bi-chevron-right"></i></a>
        @if($rol === 'admin')
            <a href="{{ route('admin.rooster.bewerk', [$jaar, $week]) }}" class="btn btn-sm btn-boels"><i class="bi bi-pencil me-1"></i>Bewerken</a>
        @endif
    </div>
</div>

@if(! $model)
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>Voor deze week is nog geen rooster geïmporteerd.
        @if($rol === 'admin') <a href="{{ route('admin.import') }}" class="alert-link">Rooster importeren</a> of <a href="{{ route('admin.rooster.bewerk', [$jaar, $week]) }}" class="alert-link">handmatig invullen</a>.@endif
    </div>
@endif

<div class="card">
    <div class="card-header"><i class="bi bi-people me-2 text-boels"></i>Diensten deze week</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Dienstsoort</th><th>Wie</th><th>Dagen</th><th>Telefoon</th><th>E-mail</th><th></th></tr></thead>
            <tbody>
                @foreach($soorten as $s)
                    @if($s->vast)
                        <tr>
                            <td><strong>{{ $s->naam }}</strong> <i class="bi bi-pin-angle text-muted" title="Vaste persoon"></i></td>
                            <td class="fst-italic">{{ $s->vaste_naam ?: '—' }}</td>
                            <td class="text-muted small">hele week</td>
                            <td>@if($s->vaste_telefoon)<a href="tel:{{ $s->vaste_telefoon }}">{{ $s->vaste_telefoon }}</a>@else <span class="text-muted">—</span>@endif</td>
                            <td>@if($s->vaste_email)<a href="mailto:{{ $s->vaste_email }}">{{ $s->vaste_email }}</a>@else <span class="text-muted">—</span>@endif</td>
                            <td></td>
                        </tr>
                    @else
                        @forelse($perSoort->get($s->id, collect()) as $t)
                            <tr class="{{ $eigenId && $t->medewerker_id === $eigenId ? 'table-warning' : '' }}">
                                <td>@if($loop->first)<strong>{{ $s->naam }}</strong>@endif</td>
                                <td>
                                    @if($t->medewerker_id === null)
                                        <span class="text-boels fw-semibold" title="Niet gekoppeld aan een medewerker">{{ $t->rooster_naam ?: '?' }}</span> <span class="badge bg-warning text-dark">niet gekoppeld</span>
                                    @else
                                        {{ $t->naam() }}@if($eigenId && $t->medewerker_id === $eigenId) <span class="badge bg-boels">jij</span>@endif
                                    @endif
                                    @include('rooster._oorsprong', ['t' => $t])
                                </td>
                                <td>
                                    @if($t->heleWeek())<span class="text-muted small">hele week</span>
                                    @else {{ \App\Services\Weekindeling::dagNaam($t->dag_van) }} t/m {{ \App\Services\Weekindeling::dagNaam($t->dag_tm) }} <span class="text-muted small">({{ $model->datumVanDag($t->dag_van)->format('d-m') }} – {{ $model->datumVanDag($t->dag_tm)->format('d-m') }}, {{ $t->dagen() }} dg)</span>
                                    @endif
                                </td>
                                <td>@if($t->medewerker?->telefoonEffectief())<a href="tel:{{ $t->medewerker->telefoonEffectief() }}">{{ $t->medewerker->telefoonEffectief() }}</a>@else <span class="text-muted">—</span>@endif</td>
                                <td>@if($t->medewerker?->emailEffectief())<a href="mailto:{{ $t->medewerker->emailEffectief() }}">{{ $t->medewerker->emailEffectief() }}</a>@else <span class="text-muted">—</span>@endif</td>
                                <td class="text-end">
                                    @if($eigenId && $t->medewerker_id === $eigenId && ! $verleden && $t->dienst_id)
                                        <a href="{{ route('ruilen.nieuw', ['dienst' => $t->dienst_id]) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-arrow-left-right me-1"></i>Ruilen</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td><strong>{{ $s->naam }}</strong></td>
                                <td colspan="5" class="text-danger">@if($model)Niemand ingeroosterd @else <span class="text-muted">—</span>@endif</td>
                            </tr>
                        @endforelse
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });</script>
@endpush
