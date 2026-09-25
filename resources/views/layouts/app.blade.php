<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titel', 'Dashboard') — {{ setting('app_titel', 'Spoedverhuur') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%23FF6600'/%3E%3Ctext x='32' y='46' font-family='Arial,Helvetica,sans-serif' font-size='40' font-weight='bold' fill='%23fff' text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --boels-orange: #FF6600; --boels-orange-dark: #E55A00; --boels-grey: #333; --boels-light: #F5F5F5; }
        body { background: var(--boels-light); color: var(--boels-grey); }
        .navbar-boels { background: var(--boels-orange); }
        .navbar-boels .navbar-brand, .navbar-boels .nav-link, .navbar-boels .dropdown-toggle { color: #fff !important; }
        .navbar-boels .nav-link:hover { color: #ffe3cf !important; }
        .navbar-boels .nav-link.active { font-weight: 600; text-decoration: underline; text-underline-offset: 6px; }
        .brand-logo { height: 44px; width: auto; border-radius: 6px; margin-right: 12px; background: #fff; padding: 5px 7px; box-shadow: 0 1px 4px rgba(0,0,0,.2); }
        .rol-badge { background: rgba(255,255,255,.22); color: #fff; border-radius: 12px; padding: 3px 10px; font-size: .8rem; }
        .btn-boels { background: var(--boels-orange); border-color: var(--boels-orange); color: #fff; }
        .btn-boels:hover { background: var(--boels-orange-dark); border-color: var(--boels-orange-dark); color: #fff; }
        .btn-outline-boels { border-color: var(--boels-orange); color: var(--boels-orange); }
        .btn-outline-boels:hover { background: var(--boels-orange); color: #fff; }
        .text-boels { color: var(--boels-orange) !important; }
        .bg-boels { background: var(--boels-orange) !important; color: #fff; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .card-header { background: #fff; border-bottom: 1px solid #eee; font-weight: 600; border-radius: 12px 12px 0 0 !important; }
        .kpi-tile .kpi-body { display: flex; align-items: center; gap: 14px; padding: 18px 20px; }
        .kpi-tile .kpi-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #fff; flex-shrink: 0; background: var(--boels-orange); }
        .kpi-tile .kpi-value { font-size: 1.6rem; font-weight: 700; line-height: 1.1; }
        .kpi-tile .kpi-label { font-size: .8rem; color: #6c757d; }
        .page-header h1 { font-size: 1.5rem; font-weight: 700; margin: 0; }
        .page-header p { color: #6c757d; margin: 0; }
        .fase-badge { font-size: .7rem; background: #e9ecef; color: #555; border-radius: 10px; padding: 2px 8px; vertical-align: middle; }
        footer { color: #999; font-size: .8rem; }
        .table thead th { font-size: .8rem; text-transform: uppercase; letter-spacing: .3px; color: #777; border-bottom: 2px solid #eee; }
        .form-control:focus, .form-select:focus { border-color: var(--boels-orange); box-shadow: 0 0 0 .2rem rgba(255,102,0,.15); }
    </style>
    @stack('head')
</head>
<body>
@php($rol = actieve_rol())
@php($rollen = (array) session('rollen', []))
@php($gebruiker = core_gebruiker())
<nav class="navbar navbar-expand-lg navbar-boels shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
            <img src="{{ asset('images/boels-industrial.png') }}" alt="Boels Industrial" class="brand-logo">{{ setting('app_titel', 'Spoedverhuur') }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            @if($rol)
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2 me-1"></i>Start</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('rooster', 'rooster.*', 'wie') ? 'active' : '' }}" href="{{ route('rooster') }}"><i class="bi bi-calendar3 me-1"></i>Rooster</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('mijn-diensten', 'mijn-vergoedingen') ? 'active' : '' }}" href="{{ route('mijn-diensten') }}"><i class="bi bi-person-check me-1"></i>Mijn diensten</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('ruilen', 'ruilen.*') ? 'active' : '' }}" href="{{ route('ruilen') }}"><i class="bi bi-arrow-left-right me-1"></i>Ruilen</a></li>
                @if(in_array($rol, ['manager', 'admin']))
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('overzicht.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown"><i class="bi bi-bar-chart-line me-1"></i>Overzicht</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('overzicht.index') }}"><i class="bi bi-grid me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="{{ route('overzicht.bezetting') }}"><i class="bi bi-calendar-check me-2"></i>Bezetting komende weken</a></li>
                        <li><a class="dropdown-item" href="{{ route('overzicht.ruilingen') }}"><i class="bi bi-arrow-left-right me-2"></i>Ruilingen</a></li>
                        <li><a class="dropdown-item" href="{{ route('overzicht.belasting') }}"><i class="bi bi-people me-2"></i>Wie draait hoeveel</a></li>
                        <li><a class="dropdown-item" href="{{ route('overzicht.audit') }}"><i class="bi bi-journal-text me-2"></i>Logboek</a></li>
                    </ul>
                </li>
                @endif
                @if($rol === 'admin')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown"><i class="bi bi-gear me-1"></i>Beheer</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ route('admin.import') }}"><i class="bi bi-upload me-2"></i>Rooster importeren</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.medewerkers') }}"><i class="bi bi-people me-2"></i>Medewerkers &amp; koppelingen</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.dienstsoorten') }}"><i class="bi bi-list-check me-2"></i>Dienstsoorten</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.ruilingen') }}"><i class="bi bi-arrow-left-right me-2"></i>Ruilingen beheren</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('admin.mail') }}"><i class="bi bi-envelope me-2"></i>Mailcentrum</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.maand') }}"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Maandoverzicht vergoedingen</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.instellingen') }}"><i class="bi bi-sliders me-2"></i>Instellingen</a></li>
                    </ul>
                </li>
                @endif
            </ul>
            @endif
            <ul class="navbar-nav ms-auto align-items-lg-center">
                @if($gebruiker)
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i>{{ $gebruiker['name'] ?? 'Gebruiker' }}
                        @if($rol)<span class="rol-badge ms-2">{{ rol_naam($rol) }}</span>@endif
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">{{ $gebruiker['email'] ?? '' }}<br><small>Roosterpersoon: {{ eigen_medewerker()?->naam ?? 'niet gekoppeld' }}</small></h6></li>
                        @if(count($rollen) > 1)
                        <li><a class="dropdown-item" href="{{ route('kies-rol', ['wissel' => 1]) }}"><i class="bi bi-arrow-left-right me-2"></i>Wissel rol</a></li>
                        @endif
                        <li><a class="dropdown-item" href="{{ config('core.url') }}"><i class="bi bi-grid-3x3-gap me-2"></i>Naar Boels CORE</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="{{ route('uitloggen') }}"><i class="bi bi-box-arrow-right me-2"></i>Uitloggen</a></li>
                    </ul>
                </li>
                @endif
            </ul>
        </div>
    </div>
</nav>

<main class="container-fluid py-4">
    @if(session('ok'))<div class="alert alert-success"><i class="bi bi-check-circle me-2"></i>{{ session('ok') }}</div>@endif
    @if(session('fout'))<div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('fout') }}</div>@endif
    @yield('inhoud')
</main>

<footer class="text-center py-3">Boels Industrial · Spoedverhuur · ingelogd via Boels CORE @if(\App\Services\MailDienst::testModus()) · <span class="badge bg-warning text-dark">TESTMODUS: alle mails gaan naar het testadres</span> @endif</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
