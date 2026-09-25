<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DienstSoort;
use Illuminate\Http\Request;

/**
 * Dienstsoorten = de kolommen van het Excel-rooster + de vaste tweede lijn h&h
 * (staat niet in het rooster, wordt niet betaald, krijgt optioneel de meldingen).
 */
class DienstSoortController extends Controller
{
    public function index()
    {
        return view('admin.dienstsoorten', [
            'soorten' => DienstSoort::withCount('diensten')->orderBy('vast')->orderBy('volgorde')->orderBy('id')->get(),
        ]);
    }

    /** Eén POST slaat alle rijen op (rij[id][veld]) en maakt optioneel een nieuwe rij (nieuw[veld]). */
    public function opslaan(Request $request)
    {
        $rijen = (array) $request->input('rij', []);
        $gewijzigd = [];
        foreach (DienstSoort::all() as $s) {
            if (! isset($rijen[$s->id]) || ! is_array($rijen[$s->id])) {
                continue;
            }
            $in = $rijen[$s->id];
            $naam = trim((string) ($in['naam'] ?? ''));
            if ($naam === '') {
                return back()->with('fout', 'De naam van dienstsoort #'.$s->id.' mag niet leeg zijn.');
            }
            $velden = [
                'naam' => $naam,
                'kolom_kop' => $this->leeg($in['kolom_kop'] ?? null),
                'volgorde' => (int) ($in['volgorde'] ?? 0),
                'naar_multiline' => ! empty($in['naar_multiline']),
                'actief' => ! empty($in['actief']),
            ];
            if ($s->vast) {
                $velden['betaald'] = false;
                $velden['kolom_kop'] = null;
                $velden['vaste_naam'] = $this->leeg($in['vaste_naam'] ?? null);
                $velden['vaste_telefoon'] = $this->leeg($in['vaste_telefoon'] ?? null);
                $velden['vaste_email'] = $this->leeg($in['vaste_email'] ?? null);
                $velden['vaste_meldingen'] = ! empty($in['vaste_meldingen']);
                if ($velden['vaste_email'] !== null && ! filter_var($velden['vaste_email'], FILTER_VALIDATE_EMAIL)) {
                    return back()->with('fout', 'Het e-mailadres van de vaste tweede lijn is ongeldig.');
                }
            } else {
                $velden['betaald'] = ! empty($in['betaald']);
            }
            $s->fill($velden);
            if ($s->isDirty()) {
                $gewijzigd[$s->naam] = ['oud' => array_intersect_key($s->getOriginal(), $s->getDirty()), 'nieuw' => $s->getDirty()];
                $s->save();
            }
        }
        $nieuw = (array) $request->input('nieuw', []);
        $nieuweNaam = trim((string) ($nieuw['naam'] ?? ''));
        $aangemaakt = null;
        if ($nieuweNaam !== '') {
            $aangemaakt = DienstSoort::create([
                'naam' => $nieuweNaam,
                'kolom_kop' => $this->leeg($nieuw['kolom_kop'] ?? null) ?? $nieuweNaam,
                'volgorde' => ($nieuw['volgorde'] ?? '') !== '' ? (int) $nieuw['volgorde'] : ((int) DienstSoort::where('vast', false)->max('volgorde') + 1),
                'betaald' => ! empty($nieuw['betaald']),
                'naar_multiline' => ! empty($nieuw['naar_multiline']),
                'actief' => ! empty($nieuw['actief']),
                'vast' => false,
            ]);
        }
        if (! $gewijzigd && ! $aangemaakt) {
            return back()->with('ok', 'Geen wijzigingen.');
        }
        audit('dienstsoorten.opslaan', 'Dienstsoorten bijgewerkt', ['gewijzigd' => $gewijzigd, 'nieuw' => $aangemaakt?->only(['id', 'naam', 'kolom_kop', 'volgorde', 'betaald', 'naar_multiline', 'actief'])]);
        $tekst = [];
        if ($gewijzigd) {
            $tekst[] = count($gewijzigd).' dienstsoort(en) gewijzigd';
        }
        if ($aangemaakt) {
            $tekst[] = 'nieuwe dienstsoort "'.$aangemaakt->naam.'" toegevoegd';
        }

        return back()->with('ok', ucfirst(implode(', ', $tekst)).'.');
    }

    private function leeg(?string $s): ?string
    {
        $s = trim((string) $s);

        return $s === '' ? null : $s;
    }
}
