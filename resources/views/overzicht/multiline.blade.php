@extends('layouts.app')
@section('titel', 'Weeklijst telefooncentrale week '.$week)
@push('head')
<style>
    @media print {
        nav, footer, .geen-print, .alert { display: none !important; }
        main { padding: 0 !important; }
        .card { box-shadow: none !important; border: 1px solid #ccc !important; }
        body { background: #fff !important; }
    }
    .ml-tabel td, .ml-tabel th { padding: .55rem .75rem; }
</style>
@endpush
@section('inhoud')
<div class="page-header d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
    <div>
        <h1><i class="bi bi-telephone me-2 text-boels"></i>Dienstdoende medewerkers week {{ $week }}</h1>
        <p>Boels Industrial · {{ $van->format('d-m-Y') }} t/m {{ $tm->format('d-m-Y') }} · lijst zoals die naar de telefooncentrale (Multiline) gaat.</p>
    </div>
    <div class="d-flex gap-2 geen-print">
        <div class="btn-group">
            <a href="{{ route('overzicht.multiline', [$vorige[0], $vorige[1]]) }}" class="btn btn-sm btn-outline-secondary" title="vorige week"><i class="bi bi-chevron-left"></i></a>
            <a href="{{ route('overzicht.multiline', [$volgende[0], $volgende[1]]) }}" class="btn btn-sm btn-outline-secondary" title="volgende week"><i class="bi bi-chevron-right"></i></a>
        </div>
        <a href="{{ route('rooster.week', [$jaar, $week]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-calendar3 me-1"></i>Rooster</a>
        @if(actieve_rol() === 'admin')<a href="{{ route('admin.mail', ['soort' => 'multiline', 'jaar' => $jaar, 'week' => $week]) }}" class="btn btn-sm btn-outline-boels"><i class="bi bi-envelope me-1"></i>Mailcentrum</a>@endif
        <button type="button" class="btn btn-sm btn-boels" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Week {{ $week }} ({{ $van->format('d-m-Y') }} t/m {{ $tm->format('d-m-Y') }})</span>
        @if($verzonden)
            <span class="badge {{ $verzonden->status === 'verzonden' ? 'bg-success' : 'bg-secondary' }} geen-print">Mail {{ $verzonden->status }}{{ $verzonden->verzonden_op ? ' op '.$verzonden->verzonden_op->format('d-m-Y H:i') : '' }}{{ $verzonden->test_modus ? ' (testmodus)' : '' }}</span>
        @else
            <span class="badge bg-light text-dark border geen-print">nog niet gemaild</span>
        @endif
    </div>
    <div class="card-body p-0">
        @if(! $rw)
            <p class="p-3 mb-0 text-danger"><i class="bi bi-exclamation-triangle me-1"></i>Voor week {{ $week }} van {{ $jaar }} is nog geen rooster. @if(actieve_rol() === 'admin')<a href="{{ route('admin.import') }}">Rooster importeren</a>@endif</p>
        @else
        <table class="table ml-tabel mb-0 align-middle">
            <thead><tr class="bg-boels"><th class="text-white">Dienst</th><th class="text-white">Naam</th><th class="text-white">Mobiel</th><th class="text-white geen-print">E-mail</th></tr></thead>
            <tbody>
            @forelse($rijen as $r)
                <tr>
                    <td>{{ $r['dienst'] }}@if($r['vast']) <span class="badge bg-secondary geen-print">vast</span>@endif</td>
                    <td class="{{ str_starts_with($r['naam'], '—') ? 'text-danger' : '' }}">{{ $r['naam'] }}@if($r['dagen']) <span class="text-muted">({{ $r['dagen'] }})</span>@endif</td>
                    <td class="{{ $r['telefoon'] ? '' : 'text-danger' }}">{{ $r['telefoon'] ?: 'onbekend' }}</td>
                    <td class="geen-print text-muted">{{ $r['email'] ?: '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-muted p-3">Geen dienstsoorten met "naar Multiline" aan.</td></tr>
            @endforelse
            </tbody>
        </table>
        @endif
    </div>
</div>
<p class="text-muted small mt-3 mb-0">Afgedrukt {{ now('Europe/Amsterdam')->format('d-m-Y H:i') }} · Spoedverhuur Boels Industrial</p>
@endsection
