<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medewerker;
use App\Models\MedewerkerAlias;
use App\Models\RoosterWeek;
use App\Services\MedewerkerSync;
use App\Services\NaamMatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Medewerkers: gespiegeld uit de CORE-medewerkerkaart, met handmatige terugvalvelden
 * voor wie (nog) niet in CORE staat, en aliassen (schrijfwijzen uit het Excel-rooster).
 */
class MedewerkerController extends Controller
{
    private const MELDINGEN = ['required' => 'Vul :attribute in.', 'email' => 'Vul een geldig e-mailadres in.', 'max' => ':attribute mag maximaal :max tekens zijn.'];

    public function index(Request $request)
    {
        $zoek = trim((string) $request->input('zoek', ''));
        $jaar = (int) now('Europe/Amsterdam')->format('o');
        $weekIds = RoosterWeek::where('jaar', $jaar)->select('id');
        $q = Medewerker::with('aliassen')
            ->withCount(['toewijzingen', 'toewijzingen as diensten_dit_jaar' => fn ($x) => $x->whereIn('rooster_week_id', $weekIds)])
            ->orderByDesc('actief')->orderBy('naam');
        if ($zoek !== '') {
            $like = '%'.$zoek.'%';
            $q->where(fn ($x) => $x->where('naam', 'like', $like)->orWhere('email', 'like', $like)->orWhere('email_handmatig', 'like', $like)
                ->orWhere('personeelsnummer', 'like', $like)->orWhere('personeelsnummer_handmatig', 'like', $like)
                ->orWhereHas('aliassen', fn ($a) => $a->where('alias', 'like', '%'.NaamMatch::normaliseer($zoek).'%')));
        }
        $lijst = $q->get();
        if ($request->boolean('ontbreekt')) {
            $lijst = $lijst->filter(fn ($m) => $m->ontbreekt())->values();
        }
        if ($request->boolean('rooster')) {
            $lijst = $lijst->where('toewijzingen_count', '>', 0)->values();
        }
        $gesynct = (int) Cache::get('medewerkers.gesynct_op', 0);

        return view('admin.medewerkers.index', [
            'lijst' => $lijst,
            'zoek' => $zoek,
            'jaar' => $jaar,
            'gesynct' => $gesynct ? \Carbon\Carbon::createFromTimestamp($gesynct, 'Europe/Amsterdam') : null,
            'totaal' => Medewerker::count(),
            'aantalCore' => Medewerker::whereNotNull('core_employee_id')->count(),
            'aantalOntbreekt' => Medewerker::actief()->get()->filter(fn ($m) => $m->ontbreekt())->count(),
        ]);
    }

    /** Knop "Nu ophalen uit CORE". */
    public function sync(MedewerkerSync $sync)
    {
        try {
            $n = $sync->sync();
        } catch (\Throwable $e) {
            report($e);
            $n = null;
        }
        if ($n === null) {
            audit('medewerkers.sync', 'CORE niet bereikbaar', ['resultaat' => 'mislukt']);

            return back()->with('fout', 'CORE niet bereikbaar: de medewerkers zijn niet bijgewerkt. Probeer het later opnieuw.');
        }
        audit('medewerkers.sync', 'Medewerkers opgehaald uit CORE', ['aantal' => $n]);

        return back()->with('ok', $n.' medewerkerkaarten opgehaald uit CORE.');
    }

    /** Nieuwe medewerker handmatig (staat niet in CORE). */
    public function opslaan(Request $request)
    {
        $data = $request->validate([
            'naam' => ['required', 'string', 'max:120'],
            'email_handmatig' => ['nullable', 'email', 'max:190'],
            'telefoon_handmatig' => ['nullable', 'string', 'max:40'],
            'personeelsnummer_handmatig' => ['nullable', 'string', 'max:40'],
        ], self::MELDINGEN, ['naam' => 'naam', 'email_handmatig' => 'e-mail', 'telefoon_handmatig' => 'telefoon', 'personeelsnummer_handmatig' => 'personeelsnummer']);
        $naam = trim($data['naam']);
        $bestaand = Medewerker::all()->first(fn ($m) => NaamMatch::normaliseer($m->naam) === NaamMatch::normaliseer($naam));
        if ($bestaand) {
            return back()->with('fout', 'Er bestaat al een medewerker met de naam "'.$bestaand->naam.'".')->withInput();
        }
        $m = Medewerker::create([
            'naam' => $naam,
            'email_handmatig' => $this->leeg($data['email_handmatig'] ?? null),
            'telefoon_handmatig' => $this->leeg($data['telefoon_handmatig'] ?? null),
            'personeelsnummer_handmatig' => $this->leeg($data['personeelsnummer_handmatig'] ?? null),
            'actief' => true,
        ]);
        audit('medewerker.aangemaakt', $m->naam, ['medewerker_id' => $m->id, 'bron' => 'handmatig', 'ontbreekt' => $m->ontbreekt()]);

        return redirect()->route('admin.medewerkers', ['zoek' => $m->naam])->with('ok', 'Medewerker "'.$m->naam.'" handmatig aangemaakt.');
    }

    /** Handmatige velden + actief bijwerken (naam alleen bij niet-CORE-medewerkers). */
    public function bijwerken(Request $request, Medewerker $medewerker)
    {
        $data = $request->validate([
            'naam' => ['nullable', 'string', 'max:120'],
            'email_handmatig' => ['nullable', 'email', 'max:190'],
            'telefoon_handmatig' => ['nullable', 'string', 'max:40'],
            'personeelsnummer_handmatig' => ['nullable', 'string', 'max:40'],
        ], self::MELDINGEN, ['naam' => 'naam', 'email_handmatig' => 'e-mail', 'telefoon_handmatig' => 'telefoon', 'personeelsnummer_handmatig' => 'personeelsnummer']);
        $velden = [
            'email_handmatig' => $this->leeg($data['email_handmatig'] ?? null),
            'telefoon_handmatig' => $this->leeg($data['telefoon_handmatig'] ?? null),
            'personeelsnummer_handmatig' => $this->leeg($data['personeelsnummer_handmatig'] ?? null),
            'actief' => $request->boolean('actief'),
        ];
        if (! $medewerker->uitCore() && trim((string) ($data['naam'] ?? '')) !== '') {
            $velden['naam'] = trim($data['naam']);
        }
        $medewerker->fill($velden);
        $wijzigingen = $medewerker->getDirty();
        if ($wijzigingen) {
            $oud = array_intersect_key($medewerker->getOriginal(), $wijzigingen);
            $medewerker->save();
            audit('medewerker.bijgewerkt', $medewerker->naam, ['medewerker_id' => $medewerker->id, 'oud' => $oud, 'nieuw' => $wijzigingen]);

            return back()->with('ok', 'Gegevens van '.$medewerker->naam.' opgeslagen.');
        }

        return back()->with('ok', 'Geen wijzigingen voor '.$medewerker->naam.'.');
    }

    /** Alias (schrijfwijze uit het rooster) koppelen. */
    /**
     * Lijst inlezen (geplakt of xlsx): per regel naam + 06-nummer en/of personeelsnummer.
     * CORE-waarden blijven leidend; alleen lege velden worden (handmatig) gevuld.
     */
    public function lijst(Request $request)
    {
        $regels = [];
        if ($request->hasFile('bestand')) {
            $pad = $request->file('bestand')->getRealPath();
            try {
                foreach ((new \App\Services\XlsxLezer($pad))->rijen() as $rij) {
                    $regels[] = array_values(array_map(fn ($v) => trim((string) $v), $rij));
                }
            } catch (\Throwable $e) {
                return back()->with('fout', 'Excel niet leesbaar: '.$e->getMessage());
            }
        }
        foreach (preg_split('/\r?\n/', (string) $request->input('tekst', '')) as $regel) {
            $regel = trim($regel);
            if ($regel !== '') {
                $regels[] = array_map('trim', preg_split('/\s*[;,\t|]\s*/', $regel));
            }
        }
        if (! $regels) {
            return back()->with('fout', 'Geen regels gevonden. Plak per regel: naam; 06-nummer; personeelsnummer (of upload een Excel).');
        }
        $aanmaken = $request->boolean('aanmaken', true);
        $gekoppeld = $aangemaakt = 0;
        $onbekend = [];
        $overgeslagen = 0;
        $alle = Medewerker::all()->all();
        foreach ($regels as $velden) {
            $naam = (string) ($velden[0] ?? '');
            if ($naam === '' || preg_match('/^(naam|medewerker|name)$/i', $naam)) {
                continue; // lege regel of kopregel
            }
            $telefoon = null;
            $nummer = null;
            foreach (array_slice($velden, 1) as $v) {
                $cijfers = preg_replace('/\D+/', '', $v);
                if ($cijfers === '') {
                    continue;
                }
                if (preg_match('/^(\+|00)?(31|32)?0?6\d{8}$/', $cijfers) || (str_starts_with($cijfers, '0') && strlen($cijfers) >= 10)) {
                    $telefoon = $telefoon ?? $v;
                } elseif (strlen($cijfers) <= 8) {
                    $nummer = $nummer ?? ltrim($v);
                }
            }
            $m = NaamMatch::zoek($naam, $alle)['medewerker'];
            if (! $m) {
                if (! $aanmaken) {
                    $onbekend[] = $naam;
                    continue;
                }
                $m = Medewerker::create(['naam' => trim($naam), 'actief' => true]);
                $alle[] = $m;
                $aangemaakt++;
            } else {
                $gekoppeld++;
            }
            if (NaamMatch::normaliseer($naam) !== NaamMatch::normaliseer($m->naam)) {
                NaamMatch::leerAlias($naam, $m);
            }
            $upd = [];
            if ($telefoon && ! $m->telefoon) {
                $upd['telefoon_handmatig'] = $telefoon;
            }
            if ($nummer && ! $m->personeelsnummer) {
                $upd['personeelsnummer_handmatig'] = $nummer;
            }
            if ($upd) {
                $m->update($upd);
            } elseif (! $telefoon && ! $nummer) {
                $overgeslagen++;
            }
        }
        audit('medewerkers.lijst', 'Lijst ingelezen', ['gekoppeld' => $gekoppeld, 'aangemaakt' => $aangemaakt, 'onbekend' => $onbekend]);
        $msg = "Lijst verwerkt: $gekoppeld bestaande medewerkers bijgewerkt, $aangemaakt nieuw aangemaakt".($overgeslagen ? ", $overgeslagen regels zonder nummer" : '').'.';
        if ($onbekend) {
            $msg .= ' Niet gevonden: '.implode(', ', $onbekend).'.';
        }

        return redirect()->route('admin.medewerkers')->with('ok', $msg);
    }

    public function alias(Request $request, Medewerker $medewerker)
    {
        $alias = trim((string) $request->input('alias', ''));
        $norm = NaamMatch::normaliseer($alias);
        if ($norm === '') {
            return back()->with('fout', 'Vul een schrijfwijze in.');
        }
        if ($norm === NaamMatch::normaliseer($medewerker->naam)) {
            return back()->with('fout', 'Dat is al de eigen naam van '.$medewerker->naam.'; een alias is niet nodig.');
        }
        $bestaand = MedewerkerAlias::with('medewerker')->where('alias', $norm)->first();
        NaamMatch::leerAlias($alias, $medewerker);
        audit('medewerker.alias', $medewerker->naam, ['medewerker_id' => $medewerker->id, 'alias' => $alias, 'verplaatst_van' => $bestaand && $bestaand->medewerker_id !== $medewerker->id ? $bestaand->medewerker?->naam : null]);

        return back()->with('ok', 'Schrijfwijze "'.$alias.'" wordt voortaan herkend als '.$medewerker->naam.'.'.($bestaand && $bestaand->medewerker_id !== $medewerker->id ? ' (Was gekoppeld aan '.$bestaand->medewerker?->naam.'.)' : ''));
    }

    public function aliasVerwijder(MedewerkerAlias $alias)
    {
        $naam = $alias->medewerker?->naam;
        audit('medewerker.alias_verwijderd', $naam, ['medewerker_id' => $alias->medewerker_id, 'alias' => $alias->alias_origineel]);
        $alias->delete();

        return back()->with('ok', 'Schrijfwijze "'.$alias->alias_origineel.'" verwijderd bij '.$naam.'.');
    }

    private function leeg(?string $s): ?string
    {
        $s = trim((string) $s);

        return $s === '' ? null : $s;
    }
}
