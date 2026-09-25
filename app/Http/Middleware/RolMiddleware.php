<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagina alleen voor bepaalde ACTIEVE rollen: ->middleware('rol:admin,manager').
 * Wie meerdere rollen heeft, wisselt via "Wissel rol" in het menu.
 */
class RolMiddleware
{
    public function handle(Request $request, Closure $next, string ...$rollen): Response
    {
        $actief = (string) $request->session()->get('actieve_rol', '');
        if (! in_array($actief, $rollen, true)) {
            abort(403, 'Deze pagina is niet beschikbaar voor de rol '.(config('core.rollen')[$actief] ?? $actief).'.');
        }

        return $next($request);
    }
}
