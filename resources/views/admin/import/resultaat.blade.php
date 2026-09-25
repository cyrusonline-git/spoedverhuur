@extends('layouts.app')
@section('titel', 'Import verwerkt')
@section('inhoud')
<div class="page-header mb-3">
    <h1><i class="bi bi-check2-circle me-2 text-success"></i>Rooster geïmporteerd — stap 3 van 3</h1>
    <p>{{ $import->bestandsnaam }} · blad "{{ $import->blad ?: '—' }}" · {{ $import->created_at->format('d-m-Y H:i') }}</p>
</div>
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-calendar-week"></i></div><div><div class="kpi-value">{{ $import->aantal_weken }}</div><div class="kpi-label">weken geschreven</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:#198754"><i class="bi bi-people"></i></div><div><div class="kpi-value">{{ $import->aantal_diensten }}</div><div class="kpi-label">diensten geschreven</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon"><i class="bi bi-link-45deg"></i></div><div><div class="kpi-value">{{ $koppelingen }}</div><div class="kpi-label">koppelingen onthouden</div></div></div></div></div>
    <div class="col-sm-6 col-xl-3"><div class="card kpi-tile"><div class="kpi-body"><div class="kpi-icon" style="background:{{ $ookVerleden ? '#ffc107' : '#6c757d' }}"><i class="bi bi-clock-history"></i></div><div><div class="kpi-value">{{ $ookVerleden ? 'ja' : 'nee' }}</div><div class="kpi-label">verleden overschreven</div></div></div></div></div>
</div>
<div class="card mb-3">
    <div class="card-header"><i class="bi bi-chat-left-text me-2 text-boels"></i>Meldingen</div>
    <div class="card-body small">
        @if(empty($import->meldingen))<p class="text-success mb-0"><i class="bi bi-check-circle me-1"></i>Geen meldingen; alles is zonder opmerkingen verwerkt.</p>
        @else<ul class="mb-0">@foreach($import->meldingen as $m)<li>{{ $m }}</li>@endforeach</ul>@endif
    </div>
</div>
<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('rooster', ['jaar' => $import->jaar]) }}" class="btn btn-boels"><i class="bi bi-calendar3 me-1"></i>Naar het rooster {{ $import->jaar }}</a>
    <a href="{{ route('admin.import') }}" class="btn btn-outline-secondary"><i class="bi bi-upload me-1"></i>Nog een bestand importeren</a>
    <a href="{{ route('admin.medewerkers') }}" class="btn btn-outline-secondary"><i class="bi bi-people me-1"></i>Medewerkers &amp; koppelingen</a>
</div>
@endsection
