<?php

/*
|--------------------------------------------------------------------------
| Boels CORE — koppeling
|--------------------------------------------------------------------------
| Deze app logt NIET zelf in. Boels CORE (databasehub.sorai.nl) is de
| identity provider: de bezoeker deelt zijn CORE-sessiecookie over
| *.sorai.nl, en deze app stuurt die cookie door naar GET /api/me en
| GET /api/access/{slug} (cookie-relay). CORE is leidend voor rollen.
*/

return [
    'url' => rtrim(env('CORE_URL', 'https://databasehub.sorai.nl'), '/'),
    'slug' => env('CORE_APP_SLUG', 'spoedverhuur'),
    'app_url' => rtrim(env('APP_URL', 'https://spoedverhuur.sorai.nl'), '/'),

    // Hoe lang (seconden) het CORE-antwoord in de eigen sessie wordt bewaard
    'cache_seconds' => (int) env('CORE_CACHE_SECONDS', 300),

    // Rollen van deze app zoals ze in CORE (Beheer → Applicaties) staan.
    // Slug => weergavenaam. Volgorde = volgorde op het rolkeuzescherm.
    'rollen' => [
        'medewerker' => 'Medewerker',
        'manager'    => 'Manager',
        'admin'      => 'Beheerder',
    ],

    // Alleen lokaal ontwikkelen (APP_ENV=local): nep-gebruiker met alle rollen,
    // zodat de app zonder CORE-cookie te testen is. Nooit op de server zetten.
    'dev_fake_user' => env('CORE_DEV_FAKE_USER', false),
];
