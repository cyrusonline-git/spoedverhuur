@extends('layouts.app')
@section('titel', 'Wie draait hoeveel')
@push('head')
<style>
    .staaf { display: flex; align-items: center; gap: 8px; }
    .staaf .balk { height: 10px; border-radius: 5px; background: var(--boels-orange); min-width: 2px; }
</style>
@endpush
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-people me-2 text-boels"></i>Wie draait hoeveel in {{ $jaar }}</h1>
        <p>Effectief rooster ({{ $aantalWeken }} roosterweken in {{ $jaar }}). Vergoeding = weekbedrag ÷ 7 × dagen, alleen voor betaalde dienstsoorten.</p>
    </div>
    <form method="get" class="d-flex gap-2 align-items-center">
        <select name="jaar" class="form-select form-select-sm" onchange="this.form.submit()">
            @foreach($jaren as $j)<option value="{{ $j }}" {{ $j === $jaar ? 'selected' : '' }}>{{ $j }}</option>@endforeach
        </select>
        <a href="{{ route('overzicht.export', ['wat' => 'belasting', 'jaar' => $jaar]) }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-download me-1"></i>CSV</a>
    </form>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
            <thead><tr>
                <th>#</th><th>Naam</th><th class="text-end">Weken</th><th class="text-end">Dagen</th><th style="min-width:140px">Belasting</th>
                @foreach($soorten as $s)<th class="text-center" title="{{ $s->naam }}{{ $s->betaald ? '' : ' (onbetaald)' }}">{{ \Illuminate\Support\Str::limit($s->naam, 22) }}@if(! $s->betaald)<br><small class="text-muted fw-normal">onbetaald</small>@endif</th>@endforeach
                <th class="text-end">Vergoeding</th>
            </tr></thead>
            <tbody>
            @forelse($rijen as $i => $rij)
                <tr>
                    <td class="text-muted">{{ $i + 1 }}</td>
                    <td>{{ $rij['naam'] }} @if(! $rij['gekoppeld'])<span class="badge bg-warning text-dark" title="naam uit het rooster, niet gekoppeld aan een medewerker">niet gekoppeld</span>@endif</td>
                    <td class="text-end">{{ $rij['aantal_weken'] }}</td>
                    <td class="text-end fw-semibold">{{ $rij['dagen'] }}</td>
                    <td><div class="staaf"><div class="balk" style="width: {{ $maxDagen ? round($rij['dagen'] / $maxDagen * 100) : 0 }}%"></div></div></td>
                    @foreach($soorten as $s)
                        @php($ps = $rij['per_soort'][$s->id] ?? null)
                        <td class="text-center {{ $ps ? '' : 'text-muted' }}">@if($ps){{ $ps['aantal_weken'] }} <small class="text-muted">wk / {{ $ps['dagen'] }} d</small>@else —@endif</td>
                    @endforeach
                    <td class="text-end text-nowrap">{{ euro($rij['vergoeding']) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ 6 + $soorten->count() }}" class="text-muted p-3">Geen toewijzingen in {{ $jaar }}. Is er al een rooster geïmporteerd?</td></tr>
            @endforelse
            </tbody>
            @if(count($rijen))
            <tfoot><tr class="fw-semibold">
                <td></td><td>Totaal</td>
                <td class="text-end">{{ array_sum(array_column($rijen, 'aantal_weken')) }}</td>
                <td class="text-end">{{ array_sum(array_column($rijen, 'dagen')) }}</td>
                <td></td>
                @foreach($soorten as $s)<td class="text-center">{{ array_sum(array_map(fn ($r) => $r['per_soort'][$s->id]['dagen'] ?? 0, $rijen)) }} <small class="text-muted fw-normal">d</small></td>@endforeach
                <td class="text-end text-nowrap">{{ euro(array_sum(array_column($rijen, 'vergoeding'))) }}</td>
            </tr></tfoot>
            @endif
        </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-person-dash me-2 text-muted"></i>Geen dienst in {{ $jaar }} ({{ $zonder->count() }})</div>
    <div class="card-body small">
        @if($zonder->isEmpty())
            <span class="text-muted">Alle actieve medewerkers hebben dit jaar minstens één dienst.</span>
        @else
            @foreach($zonder as $m)<span class="badge bg-light text-dark border me-1 mb-1">{{ $m->naam }}</span>@endforeach
        @endif
    </div>
</div>
@endsection
