@extends('layouts.app')
@section('titel', 'Wie draait wanneer')
@section('inhoud')
<div class="page-header mb-3">
    <h1><i class="bi bi-search me-2 text-boels"></i>Wie draait wanneer?</h1>
    <p>Zoek op (een deel van) een naam; je ziet alle diensten van de komende 26 weken.</p>
</div>
<div class="card mb-3">
    <div class="card-body">
        <form method="get" action="{{ route('wie') }}" class="row g-2 align-items-center">
            <div class="col-sm-6 col-lg-4"><input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Naam, bijvoorbeeld 'Knubben'" autofocus></div>
            <div class="col-auto"><button class="btn btn-boels" type="submit"><i class="bi bi-search me-1"></i>Zoeken</button></div>
            <div class="col-auto"><a href="{{ route('rooster') }}" class="btn btn-outline-secondary"><i class="bi bi-calendar3 me-1"></i>Rooster</a></div>
        </form>
    </div>
</div>

@if($q !== '')
    @if($medewerkers->isEmpty() && $toewijzingen->isEmpty())
        <div class="alert alert-info">Geen medewerker gevonden die op "{{ $q }}" lijkt.</div>
    @else
        <p class="small text-muted">Gevonden: @foreach($medewerkers as $m)<span class="badge bg-secondary me-1">{{ $m->naam }}</span>@endforeach</p>
        <div class="card">
            <div class="card-header"><i class="bi bi-calendar-check me-2 text-boels"></i>Diensten komende 26 weken ({{ $toewijzingen->count() }})</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr><th>Week</th><th>Datums</th><th>Wie</th><th>Dienstsoort</th><th>Dagen</th><th>Telefoon</th></tr></thead>
                    <tbody>
                        @forelse($toewijzingen as $t)
                            <tr class="{{ $eigenId && $t->medewerker_id === $eigenId ? 'table-warning' : '' }}">
                                <td><a href="{{ route('rooster.week', [$t->week->jaar, $t->week->weeknummer]) }}">Week {{ $t->week->weeknummer }}</a> <span class="text-muted small">{{ $t->week->jaar }}</span></td>
                                <td>{{ $t->week->datumVanDag($t->dag_van)->format('d-m') }} – {{ $t->week->datumVanDag($t->dag_tm)->format('d-m-Y') }}</td>
                                <td>@if($t->medewerker_id === null)<span class="text-boels fw-semibold">{{ $t->rooster_naam }}</span> <span class="badge bg-warning text-dark">niet gekoppeld</span>@else{{ $t->naam() }}@endif @include('rooster._oorsprong', ['t' => $t])</td>
                                <td>{{ $t->soort?->naam }}</td>
                                <td>@if($t->heleWeek())hele week @else {{ \App\Services\Weekindeling::dagNaam($t->dag_van) }} t/m {{ \App\Services\Weekindeling::dagNaam($t->dag_tm) }} @endif</td>
                                <td>@if($t->medewerker?->telefoonEffectief())<a href="tel:{{ $t->medewerker->telefoonEffectief() }}">{{ $t->medewerker->telefoonEffectief() }}</a>@else <span class="text-muted">—</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted">Geen diensten in de komende 26 weken.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif
@endsection
@push('scripts')
<script>document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });</script>
@endpush
