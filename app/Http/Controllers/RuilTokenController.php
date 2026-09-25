<?php

namespace App\Http\Controllers;

use App\Models\Ruiling;
use App\Services\Ruilen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Publieke pagina's (zonder login) achter de knoppen in de ruilverzoek-mail:
 * GET toont alleen een samenvatting + één knop; pas de POST voert uit. Zo kan
 * een mailscanner die de link opent nooit per ongeluk bevestigen.
 */
class RuilTokenController extends Controller
{
    public function toon(string $token, string $actie)
    {
        $r = $this->zoek($token);
        if (! $r) {
            return $this->ongeldig($token);
        }

        return view('ruil-token.toon', $this->gegevens($r, $actie));
    }

    public function verwerk(Request $request, string $token, string $actie)
    {
        $sleutel = 'ruil-token:'.$request->ip();
        if (RateLimiter::tooManyAttempts($sleutel, 10)) {
            return response()->view('ruil-token.ongeldig', ['titel' => 'Te veel pogingen', 'tekst' => 'Probeer het over een minuut opnieuw.'], 429);
        }
        RateLimiter::hit($sleutel, 60);

        $r = $this->zoek($token);
        if (! $r) {
            return $this->ongeldig($token);
        }
        $naam = ($r->moetBevestigen()?->naam ?? 'collega').' (via maillink)';
        try {
            if ($actie === 'bevestig') {
                app(Ruilen::class)->bevestigen($r, null, $naam);
                $titel = 'Bevestigd';
                $tekst = 'Bedankt! De ruiling is verwerkt in het rooster. Jij en '.($r->aangevraagdDoor?->naam ?? 'de aanvrager').' krijgen daarvan een mail.';
            } else {
                $reden = trim((string) $request->input('reden', ''));
                app(Ruilen::class)->afwijzen($r, $naam, $reden ?: null);
                $titel = 'Afgewezen';
                $tekst = 'Het verzoek is afgewezen. '.($r->aangevraagdDoor?->naam ?? 'De aanvrager').' krijgt daarvan een mail.';
            }
        } catch (ValidationException $e) {
            return response()->view('ruil-token.ongeldig', ['titel' => 'Niet gelukt', 'tekst' => implode(' ', array_map(fn ($m) => implode(' ', (array) $m), $e->errors()))], 422);
        }

        return view('ruil-token.klaar', ['titel' => $titel, 'tekst' => $tekst, 'bevestigd' => $actie === 'bevestig', 'omschrijving' => app(Ruilen::class)->omschrijving($r)]);
    }

    /** Open ruiling met dit token, of null als onbekend/afgehandeld/verlopen. */
    private function zoek(string $token): ?Ruiling
    {
        if (strlen($token) < 20) {
            return null;
        }
        $r = Ruiling::with(['dienst.week', 'dienst.soort', 'tegenDienst.week', 'tegenDienst.soort', 'van', 'naar', 'aangevraagdDoor'])
            ->where('token', $token)->where('status', 'aangevraagd')->first();
        if (! $r) {
            return null;
        }
        if ($r->token_verloopt_op && $r->token_verloopt_op->isPast()) {
            return null;
        }

        return $r;
    }

    private function ongeldig(string $token)
    {
        // Bestaat het verzoek wel maar is het al afgehandeld of verlopen? Dan dat vertellen.
        $r = strlen($token) >= 20 ? Ruiling::where('token', $token)->first() : null;
        if ($r && $r->isOpen()) {
            $tekst = 'De link is verlopen (geldig tot '.$r->token_verloopt_op?->format('d-m-Y H:i').'). Vraag je collega om het verzoek opnieuw in te dienen.';
        } elseif ($r) {
            $tekst = 'Dit verzoek is al afgehandeld: '.$r->statusLabel().'.';
        } else {
            $tekst = 'Deze link is niet (meer) geldig. Het verzoek is al afgehandeld, ingetrokken of verlopen. Kijk in de app onder Ruilen voor de actuele stand.';
        }

        return response()->view('ruil-token.ongeldig', ['titel' => 'Link niet geldig', 'tekst' => $tekst], 410);
    }

    private function gegevens(Ruiling $r, string $actie): array
    {
        $regels = [];
        $regels[] = ['Wie vraagt', $r->aangevraagdDoor?->naam ?? '?'];
        $regels[] = ['Type', $r->typeLabel()];
        if ($r->dienst) {
            $regels[] = ['Dienst van '.($r->van?->naam ?? '?'), RuilController::dienstLabel($r->dienst)];
        }
        if ($r->type === 'ruil' && $r->tegenDienst) {
            $regels[] = ['Dienst van '.($r->naar?->naam ?? '?'), RuilController::dienstLabel($r->tegenDienst)];
        }
        if ($r->type === 'deel' && $r->dienst) {
            $w = $r->dienst->week;
            $regels[] = ['Dagen die overgaan', ucfirst(\App\Services\Weekindeling::dagNaam((int) $r->dagen_van)).' '.$w->datumVanDag((int) $r->dagen_van)->format('d-m').' t/m '.\App\Services\Weekindeling::dagNaam((int) $r->dagen_tm).' '.$w->datumVanDag((int) $r->dagen_tm)->format('d-m-Y')];
        }
        $regels[] = ['Resultaat', $r->type === 'ruil'
            ? ($r->van?->naam ?? '?').' en '.($r->naar?->naam ?? '?').' wisselen van dienst'
            : ($r->naar?->naam ?? '?').' neemt '.($r->type === 'deel' ? 'de genoemde dagen' : 'de hele dienst').' over van '.($r->van?->naam ?? '?')];
        if ($r->opmerking) {
            $regels[] = ['Opmerking', $r->opmerking];
        }
        $regels[] = ['Geldig tot', $r->token_verloopt_op?->format('d-m-Y H:i') ?? '—'];

        return [
            'r' => $r,
            'actie' => $actie,
            'ontvanger' => $r->moetBevestigen(),
            'omschrijving' => app(Ruilen::class)->omschrijving($r),
            'regels' => $regels,
        ];
    }
}
