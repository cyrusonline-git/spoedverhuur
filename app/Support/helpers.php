<?php

use App\Models\Setting;

if (! function_exists('setting')) {
    /** Instelling lezen (met standaardwaarde). */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('rol_naam')) {
    /** Weergavenaam van een rol-slug. */
    function rol_naam(?string $slug): string
    {
        return config('core.rollen')[$slug] ?? ucfirst((string) $slug);
    }
}

if (! function_exists('actieve_rol')) {
    function actieve_rol(): ?string
    {
        return session('actieve_rol');
    }
}

if (! function_exists('core_gebruiker')) {
    /** Profiel van de ingelogde CORE-gebruiker (array) of null. */
    function core_gebruiker(): ?array
    {
        $u = session('core_user');

        return is_array($u) ? $u : null;
    }
}

if (! function_exists('eigen_medewerker')) {
    /** De medewerker (roosterpersoon) die bij de ingelogde CORE-gebruiker hoort, of null. */
    function eigen_medewerker(): ?\App\Models\Medewerker
    {
        $id = session('eigen_medewerker_id');

        return $id ? \App\Models\Medewerker::find($id) : null;
    }
}

if (! function_exists('audit')) {
    function audit(string $actie, ?string $onderwerp = null, array $details = []): void
    {
        \App\Models\AuditLog::schrijf($actie, $onderwerp, $details);
    }
}

if (! function_exists('euro')) {
    function euro(float|int|string|null $bedrag): string
    {
        return '€ '.number_format((float) $bedrag, 2, ',', '.');
    }
}

if (! function_exists('dmy')) {
    function dmy(mixed $datum): string
    {
        if (! $datum) {
            return '—';
        }

        return ($datum instanceof \DateTimeInterface ? \Carbon\Carbon::instance($datum) : \Carbon\Carbon::parse($datum))->format('d-m-Y');
    }
}
