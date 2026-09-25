@extends('layouts.app')
@section('titel', 'Bezetting komende weken')
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-calendar-check me-2 text-boels"></i>Bezetting komende {{ $aantal }} weken</h1>
        <p>Effectief rooster (na bevestigde ruilingen). <span class="text-danger fw-semibold">OPEN</span> = niemand ingepland, <span class="text-warning fw-semibold">oranje</span> = naam uit het rooster die niet aan een medewerker is gekoppeld.</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <div class="btn-group">
            @foreach([8, 13, 26] as $n)
                <a href="{{ route('overzicht.bezetting', ['weken' => $n]) }}" class="btn btn-sm {{ $aantal === $n ? 'btn-boels' : 'btn-outline-boels' }}">{{ $n }} weken</a>
            @endforeach
        </div>
        <a href="{{ route('overzicht.export', ['wat' => 'bezetting', 'weken' => $aantal]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>CSV</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table table-sm table-hover mb-0 align-middle" style="font-size:.85rem">
            <thead><tr><th class="text-nowrap">Week</th>
                @foreach($soorten as $s)<th>{{ $s->naam }}</th>@endforeach
            </tr></thead>
            <tbody>
            @foreach($weken as $w)
                <tr class="{{ $w['heeft_rooster'] ? '' : 'table-light' }}">
                    <td class="text-nowrap">
                        <a href="{{ route('rooster.week', [$w['jaar'], $w['week']]) }}" class="fw-semibold text-decoration-none">week {{ $w['week'] }}</a>
                        @if($w['jaar'] !== (int) now()->format('o')) <small class="text-muted">{{ $w['jaar'] }}</small> @endif
                        <br><span class="text-muted small">{{ $w['van']->format('d-m') }} – {{ $w['tm']->format('d-m') }}</span>
                        @if(! $w['heeft_rooster']) <br><span class="badge bg-danger">geen rooster</span> @endif
                        @if(actieve_rol() === 'admin') <br><a href="{{ route('admin.rooster.bewerk', [$w['jaar'], $w['week']]) }}" class="small text-muted"><i class="bi bi-pencil"></i> bewerken</a> @endif
                    </td>
                    @foreach($soorten as $s)
                        @php($cel = $w['cellen'][$s->id])
                        <td class="{{ $cel['status'] === 'open' ? 'bg-danger-subtle' : ($cel['status'] === 'ongekoppeld' ? 'bg-warning-subtle' : '') }}">
                            @if($cel['status'] === 'open')
                                <span class="text-danger fw-semibold">OPEN</span>
                            @else
                                @foreach($cel['toewijzingen'] as $t)
                                    <div class="{{ $t->medewerker_id ? '' : 'text-warning-emphasis fw-semibold' }}" title="{{ $t->medewerker_id ? ($t->oorsprong !== 'rooster' ? 'via '.$t->oorsprong : '') : 'niet gekoppeld aan een medewerker' }}">
                                        {{ $t->naam() }}
                                        @if(! $t->heleWeek()) <small class="text-muted">{{ \App\Services\Weekindeling::dagNaam($t->dag_van) }} t/m {{ \App\Services\Weekindeling::dagNaam($t->dag_tm) }}</small> @endif
                                        @if(in_array($t->oorsprong, ['ruil', 'overname', 'deel'])) <i class="bi bi-arrow-left-right text-muted" title="na ruiling"></i> @endif
                                    </div>
                                @endforeach
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

<div class="card" id="open">
    <div class="card-header"><i class="bi bi-exclamation-diamond me-2 {{ count($open) ? 'text-danger' : 'text-success' }}"></i>Open plekken ({{ count($open) }})</div>
    <div class="card-body">
        @if(! count($open))
            <p class="text-muted mb-0"><i class="bi bi-check-circle text-success me-1"></i>Alle diensten in de komende {{ $aantal }} weken zijn bezet en gekoppeld.</p>
        @else
        <table class="table table-sm mb-0">
            <thead><tr><th>Week</th><th>Periode</th><th>Dienst</th><th>Probleem</th><th></th></tr></thead>
            <tbody>
            @foreach($open as $o)
                <tr>
                    <td class="text-nowrap">week {{ $o['week'] }} @if($o['jaar'] !== (int) now()->format('o'))<small class="text-muted">{{ $o['jaar'] }}</small>@endif</td>
                    <td class="text-nowrap">{{ $o['van']->format('d-m') }} – {{ $o['tm']->format('d-m-Y') }}</td>
                    <td>{{ $o['soort'] }}</td>
                    <td>@if($o['status'] === 'open')<span class="badge bg-danger">niemand ingepland</span>@else<span class="badge bg-warning text-dark">"{{ $o['naam'] }}" niet gekoppeld aan een medewerker</span>@endif</td>
                    <td class="text-end">
                        @if(actieve_rol() === 'admin')
                            <a href="{{ route('admin.rooster.bewerk', [$o['jaar'], $o['week']]) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-pencil me-1"></i>Rooster bewerken</a>
                        @else
                            <a href="{{ route('rooster.week', [$o['jaar'], $o['week']]) }}" class="btn btn-sm btn-outline-secondary">Bekijk week</a>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
