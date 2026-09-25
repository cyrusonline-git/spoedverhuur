<?php

namespace App\Http\Controllers;

use App\Services\CoreSso;
use Illuminate\Http\Request;

class RolController extends Controller
{
    /** Rolkeuzescherm (bij meer dan één rol, of via "Wissel rol"). */
    public function kies(Request $request)
    {
        $rollen = (array) $request->session()->get('rollen', []);
        if (count($rollen) === 1 && ! $request->has('wissel')) {
            $request->session()->put('actieve_rol', $rollen[0]);

            return redirect()->route('dashboard');
        }

        return view('kies-rol', ['rollen' => $rollen, 'actief' => $request->session()->get('actieve_rol')]);
    }

    public function opslaan(Request $request)
    {
        $rol = (string) $request->input('rol');
        if (in_array($rol, (array) $request->session()->get('rollen', []), true)) {
            $request->session()->put('actieve_rol', $rol);
        }

        return redirect()->route('dashboard');
    }

    /** Uitloggen = uitloggen in Boels CORE (één sessie voor alle apps). */
    public function uitloggen(Request $request, CoreSso $sso)
    {
        $sso->vergeet();
        $request->session()->flush();

        return redirect()->away(config('core.url').'/logout');
    }

    public function geenToegang(Request $request)
    {
        return response()->view('geen-toegang', ['gebruiker' => $request->session()->get('core_user')], 403);
    }
}
