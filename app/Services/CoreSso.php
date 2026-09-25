<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cookie-relay naar Boels CORE.
 *
 * De browser stuurt zijn CORE-sessiecookie (domein .sorai.nl) ook naar
 * deze app mee; wij sturen die cookie door naar de CORE-API en krijgen zo
 * de ingelogde gebruiker, zijn rollen in deze app en de organisatiestructuur.
 * Antwoorden worden een paar minuten in de eigen sessie bewaard, zodat CORE
 * niet bij elke klik wordt belast.
 */
class CoreSso
{
    public function __construct(private Request $request)
    {
    }

    /** Ingelogde CORE-gebruiker (of null). */
    public function user(bool $force = false): ?array
    {
        if ($this->fakeUser()) {
            $this->request->session()->put('core_user', $this->fakeUser());

            return $this->fakeUser();
        }
        $user = $this->relay('/api/me', 'core_user', $force);

        return (is_array($user) && ! empty($user['id'])) ? $user : null;
    }

    /** Rollen/permissies van de gebruiker binnen déze app volgens CORE. */
    public function access(bool $force = false): ?array
    {
        if ($this->fakeUser()) {
            return [
                'is_super_admin' => true,
                'roles' => collect(config('core.rollen'))->keys()->map(fn ($s) => ['slug' => $s, 'scope' => 'app'])->all(),
            ];
        }

        return $this->relay('/api/access/'.config('core.slug'), 'core_access', $force);
    }

    /** Organisatiestructuur (business unit > area > depot) uit CORE. */
    public function infrastructure(): ?array
    {
        if ($this->fakeUser()) {
            return [[
                'name' => 'Boels Industrial',
                'areas' => [[
                    'name' => 'West', 'country' => 'NL',
                    'depots' => [
                        ['name' => 'Rotterdam', 'number' => '759', 'email' => 'rotterdam@example.test', 'city' => 'Rotterdam'],
                        ['name' => 'Chemelot', 'number' => '384, 769', 'email' => 'chemelot@example.test', 'city' => 'Geleen'],
                    ],
                ]],
            ]];
        }

        $data = $this->relay('/api/infrastructure', 'core_infra', true);

        return is_array($data) ? $data : null;
    }

    /** Alle actieve medewerkerkaarten uit CORE (GET /api/employees, gepagineerd). null bij storing. */
    public function employees(): ?array
    {
        if ($this->fakeUser()) {
            return [
                ['id' => 77, 'name' => 'Anne Vasseur', 'email' => 'anne.vasseur@boels.nl', 'phone' => '06-11111111', 'employee_number' => '17301', 'active' => true],
                ['id' => 42, 'name' => 'Justin Bikker', 'email' => 'justin.bikker@boels.nl', 'phone' => '06-22222222', 'employee_number' => '17375', 'active' => true],
                ['id' => 12, 'name' => 'Wim Kolker', 'email' => 'wim.kolker@boels.nl', 'phone' => '06-33333333', 'employee_number' => '17386', 'active' => true],
                ['id' => 13, 'name' => 'Jefke Knubben', 'email' => 'jefke.knubben@boels.nl', 'phone' => null, 'employee_number' => '23429', 'active' => true],
                ['id' => 1, 'name' => 'Test Gebruiker (lokaal)', 'email' => 'test@boels.nl', 'phone' => '06-00000000', 'employee_number' => '99999', 'active' => true],
            ];
        }
        $cookie = (string) $this->request->header('Cookie', '');
        $alles = [];
        $pagina = 1;
        do {
            $data = null;
            foreach ([config('core.app_url'), config('core.url')] as $referer) {
                $data = $this->callRaw('/api/employees?per_page=200&page='.$pagina, $cookie, $referer);
                if ($data !== null) {
                    break;
                }
            }
            if (! is_array($data)) {
                return $pagina === 1 ? null : $alles;
            }
            $rijen = $data['data'] ?? (array_is_list($data) ? $data : []);
            foreach ($rijen as $r) {
                $alles[] = $r;
            }
            $laatste = (int) ($data['last_page'] ?? 1);
            $pagina++;
        } while ($pagina <= $laatste && $pagina < 50);

        return $alles;
    }

    /** Zoals call(), maar zonder de 'data'-uitpak (paginatie-metadata blijft beschikbaar). */
    private function callRaw(string $endpoint, string $cookie, string $referer): ?array
    {
        try {
            $res = Http::timeout(10)->withHeaders(['Accept' => 'application/json', 'Referer' => $referer.'/', 'Cookie' => $this->coreCookies($cookie)])->get(config('core.url').$endpoint);
        } catch (\Throwable $e) {
            Log::warning('CORE-relay mislukt: '.$e->getMessage(), ['endpoint' => $endpoint]);

            return null;
        }
        if ($res->status() !== 200) {
            return null;
        }
        $data = $res->json();

        return is_array($data) ? $data : null;
    }

    private function coreCookies(string $cookie): string
    {
        $delen = [];
        foreach (explode(';', $cookie) as $deel) {
            $deel = trim($deel);
            $naam = strstr($deel, '=', true);
            if ($naam !== false && (str_starts_with($naam, 'boels_core') || $naam === 'XSRF-TOKEN')) {
                $delen[] = $deel;
            }
        }

        return implode('; ', $delen);
    }

    /**
     * Rol-slugs van deze gebruiker in deze app. Super-admins van CORE krijgen
     * alle rollen (zij kiezen op het rolkeuzescherm). Onbekende slugs worden
     * genegeerd; CORE gebruikt streepjes, wij accepteren beide schrijfwijzen.
     */
    public function rollen(?array $access): array
    {
        $bekend = array_keys(config('core.rollen'));
        if (! is_array($access)) {
            return [];
        }
        if (! empty($access['is_super_admin'])) {
            return $bekend;
        }
        $slugs = [];
        foreach ($access['roles'] ?? [] as $rol) {
            $slug = str_replace('-', '_', (string) ($rol['slug'] ?? ''));
            if (($rol['scope'] ?? 'app') === 'app' && in_array($slug, $bekend, true)) {
                $slugs[] = $slug;
            }
        }
        // Vaste volgorde (zoals in config), zonder dubbelen
        return array_values(array_filter($bekend, fn ($s) => in_array($s, $slugs, true)));
    }

    /** Alles wat over de CORE-gebruiker in de sessie staat weggooien. */
    public function vergeet(): void
    {
        foreach (['core_user', 'core_access', 'core_infra'] as $k) {
            $this->request->session()->forget([$k, $k.'_at']);
        }
    }

    // ------------------------------------------------------------------

    private function relay(string $endpoint, string $cacheKey, bool $force): ?array
    {
        $session = $this->request->session();
        $ttl = (int) config('core.cache_seconds', 300);
        if (! $force && $session->has($cacheKey) && $session->has($cacheKey.'_at')
            && time() - (int) $session->get($cacheKey.'_at') < $ttl) {
            return $session->get($cacheKey);
        }

        // Alleen de cookies van CORE doorsturen (sessie + XSRF), niet onze eigen sessiecookie
        $delen = [];
        foreach (explode(';', (string) $this->request->header('Cookie', '')) as $deel) {
            $deel = trim($deel);
            $naam = strstr($deel, '=', true);
            if ($naam !== false && (str_starts_with($naam, 'boels_core') || $naam === 'XSRF-TOKEN')) {
                $delen[] = $deel;
            }
        }
        $cookie = implode('; ', $delen);
        $data = null;
        if ($cookie !== '') {
            // Referer = onze eigen URL (staat in CORE's SANCTUM_STATEFUL_DOMAINS);
            // valt terug op CORE's eigen URL als dat (nog) niet zo is.
            foreach ([config('core.app_url'), config('core.url')] as $referer) {
                $data = $this->call($endpoint, $cookie, $referer);
                if ($data !== null) {
                    break;
                }
            }
        }

        $session->put($cacheKey, $data);
        $session->put($cacheKey.'_at', time());

        return $data;
    }

    private function call(string $endpoint, string $cookie, string $referer): ?array
    {
        try {
            $res = Http::timeout(6)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Referer' => $referer.'/',
                    'Cookie' => $cookie,
                ])
                ->get(config('core.url').$endpoint);
        } catch (\Throwable $e) {
            Log::warning('CORE-relay mislukt: '.$e->getMessage(), ['endpoint' => $endpoint]);

            return null;
        }
        if ($res->status() !== 200) {
            return null;
        }
        $data = $res->json();
        if (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        return is_array($data) ? $data : null;
    }

    private function fakeUser(): ?array
    {
        if (! app()->environment('local') || ! config('core.dev_fake_user')) {
            return null;
        }

        return [
            'id' => 1, 'name' => 'Test Gebruiker (lokaal)', 'email' => 'test@boels.nl',
            'is_super_admin' => true, 'allowed_areas' => ['West'], 'allowed_depots' => ['Rotterdam'],
        ];
    }
}
