<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titel', 'Ruilverzoek') — {{ setting('app_titel', 'Spoedverhuur') }}</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%23FF6600'/%3E%3Ctext x='32' y='46' font-family='Arial,Helvetica,sans-serif' font-size='40' font-weight='bold' fill='%23fff' text-anchor='middle'%3EB%3C/text%3E%3C/svg%3E">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root { --boels-orange: #FF6600; --boels-orange-dark: #E55A00; --boels-grey: #333; --boels-light: #F5F5F5; }
        body { background: var(--boels-light); color: var(--boels-grey); }
        .kop-boels { background: var(--boels-orange); color: #fff; padding: 14px 0; box-shadow: 0 2px 6px rgba(0,0,0,.15); }
        .kop-boels .b-logo { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 8px; background: var(--boels-orange); border: 2px solid #fff; color: #fff; font-weight: 800; font-size: 1.6rem; font-family: Arial, Helvetica, sans-serif; margin-right: 12px; }
        .kop-boels .titel { font-size: 1.25rem; font-weight: 600; }
        .kop-boels small { opacity: .85; }
        .btn-boels { background: var(--boels-orange); border-color: var(--boels-orange); color: #fff; }
        .btn-boels:hover { background: var(--boels-orange-dark); border-color: var(--boels-orange-dark); color: #fff; }
        .text-boels { color: var(--boels-orange) !important; }
        .card { border: none; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
        .card-header { background: #fff; border-bottom: 1px solid #eee; font-weight: 600; border-radius: 12px 12px 0 0 !important; }
        .samenvatting th { width: 34%; color: #777; font-weight: 500; font-size: .85rem; text-transform: uppercase; letter-spacing: .3px; }
        .form-control:focus { border-color: var(--boels-orange); box-shadow: 0 0 0 .2rem rgba(255,102,0,.15); }
        footer { color: #999; font-size: .8rem; }
    </style>
</head>
<body>
<header class="kop-boels">
    <div class="container d-flex align-items-center">
        <span class="b-logo">B</span>
        <div>
            <div class="titel">{{ setting('app_titel', 'Spoedverhuur') }}</div>
            <small>Boels Industrial · dienstrooster spoedverhuur</small>
        </div>
    </div>
</header>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            @yield('inhoud')
        </div>
    </div>
</main>

<footer class="text-center py-3">Boels Industrial · Spoedverhuur · deze pagina werkt zonder inloggen via de link uit de mail</footer>
</body>
</html>
