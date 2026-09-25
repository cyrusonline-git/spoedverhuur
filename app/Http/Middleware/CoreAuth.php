<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\CoreSso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Elke beveiligde pagina: is er een ingelogde CORE-gebruiker, welke rollen
 * heeft hij in deze app, en welke rol is nu actief?
 *
 * Sessie-sleutels: core_user (profiel), rollen (slugs), actieve_rol,
 * eigen_depot, eigen_area, app_user_id (rij in onze users-tabel).
 */
class CoreAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $sso = app(CoreSso::class);
        $session = $request->session();

        $vorigeAt = (int) $session->get('core_user_at', 0);
        $coreUser = $sso->user();
        if (! $coreUser) {
            $session->flush();

            return redirect()->away(config('core.url').'/login');
        }

        // Alleen bij een vers CORE-antwoord (max 1x per cache-periode) rollen
        // spiegelen en de gebruiker in onze eigen tabel bijwerken.
        $vers = (int) $session->get('core_user_at', 0) !== $vorigeAt || ! $session->has('rollen');
        if ($vers) {
            $rollen = $sso->rollen($sso->access());
            $session->put('rollen', $rollen);

            $depots = array_values(array_filter((array) ($coreUser['allowed_depots'] ?? [])));
            $areas = array_values(array_filter((array) ($coreUser['allowed_areas'] ?? [])));
            $session->put('eigen_depot', $depots[0] ?? null);
            $session->put('eigen_area', $areas[0] ?? null);
            $session->put('toegestane_depots', $depots);
            $session->put('toegestane_areas', $areas);

            $user = User::updateOrCreate(
                ['core_user_id' => (int) $coreUser['id']],
                [
                    'name' => $coreUser['name'] ?? ('Gebruiker '.$coreUser['id']),
                    'email' => $coreUser['email'] ?? null,
                    'is_super_admin' => (bool) ($coreUser['is_super_admin'] ?? false),
                    'depot' => $depots[0] ?? null,
                    'area' => $areas[0] ?? null,
                    'rollen' => $rollen,
                    'last_seen_at' => now(),
                ]
            );
            $session->put('app_user_id', $user->id);

            // Welke medewerker (roosterpersoon) is dit? Op CORE-employee_id, anders e-mail, anders naam.
            $mw = null;
            if (! empty($coreUser['employee_id'])) {
                $mw = \App\Models\Medewerker::where('core_employee_id', (int) $coreUser['employee_id'])->first();
            }
            if (! $mw && ! empty($coreUser['email'])) {
                $mw = \App\Models\Medewerker::whereRaw('lower(coalesce(email, email_handmatig)) = ?', [mb_strtolower($coreUser['email'])])->first();
            }
            if (! $mw && ! empty($coreUser['name'])) {
                $mw = \App\Services\NaamMatch::zoek($coreUser['name'])['medewerker'];
            }
            if ($mw && ! $mw->core_user_id) {
                $mw->update(['core_user_id' => (int) $coreUser['id']]);
            }
            $session->put('eigen_medewerker_id', $mw?->id);

            // Actieve rol vervallen als CORE die heeft ingetrokken
            if ($session->has('actieve_rol') && ! in_array($session->get('actieve_rol'), $rollen, true)) {
                $session->forget('actieve_rol');
            }
        }

        $rollen = (array) $session->get('rollen', []);
        if (empty($rollen)) {
            return $request->routeIs('geen-toegang') ? $next($request) : redirect()->route('geen-toegang');
        }
        if (count($rollen) === 1 && ! $session->has('actieve_rol')) {
            $session->put('actieve_rol', $rollen[0]);
        }
        if (! $session->has('actieve_rol') && ! $request->routeIs('kies-rol', 'kies-rol.opslaan')) {
            return redirect()->route('kies-rol');
        }

        return $next($request);
    }
}
