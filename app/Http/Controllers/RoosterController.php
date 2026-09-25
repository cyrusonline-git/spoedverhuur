<?php

namespace App\Http\Controllers;

use App\Models\DienstSoort;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Toewijzing;
use App\Services\NaamMatch;
use App\Services\Vergoeding;
use App\Services\Weekindeling;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Rooster inzien: jaaroverzicht, weekdetail, eigen diensten, vergoedingen en "wie draait wanneer". */
class RoosterController extends Controller
{
    /** Jaaroverzicht: rijen = ISO-weken, kolommen = dienstsoorten, inhoud = effectief rooster (toewijzingen). */
    public function index(Request $request)
    {
        [$huidigJaar, $huidigeWeek] = Weekindeling::weekVanDatum(now('Europe/Amsterdam'));
        $jaar = (int) $request->input('jaar', $huidigJaar);
        if ($request->filled('week') && ! $request->filled('jaar')) {
            return redirect()->route('rooster.week', [$huidigJaar, (int) $request->input('week')]);
        }
        $komend = $request->boolean('komend', $jaar === $huidigJaar && ! $request->has('jaar'));
        $vandaag = now('Europe/Amsterdam')->startOfDay();

        $jaren = RoosterWeek::query()->distinct()->orderBy('jaar')->pluck('jaar')->all();
        foreach ([$huidigJaar, $huidigJaar + 1] as $j) {
            if (! in_array($j, $jaren, true)) {
                $jaren[] = $j;
            }
        }
        sort($jaren);

        $soorten = DienstSoort::actief()->get();
        $weken = RoosterWeek::where('jaar', $jaar)->with(['toewijzingen.medewerker', 'toewijzingen.soort'])->get()->keyBy('weeknummer');

        $aantalWeken = Carbon::create($jaar, 6, 1)->isoWeeksInYear;
        $rijen = [];
        for ($w = 1; $w <= $aantalWeken; $w++) {
            [$van, $tm] = Weekindeling::datums($jaar, $w);
            if ($komend && $tm->lt($vandaag)) {
                continue;
            }
            $week = $weken->get($w);
            $cellen = [];
            if ($week) {
                foreach ($week->toewijzingen->sortBy('dag_van') as $t) {
                    $cellen[$t->dienst_soort_id][] = $t;
                }
            }
            $rijen[] = ['week' => $w, 'van' => $van, 'tm' => $tm, 'model' => $week, 'cellen' => $cellen, 'huidig' => $jaar === $huidigJaar && $w === $huidigeWeek];
        }

        return view('rooster.index', [
            'jaar' => $jaar, 'jaren' => $jaren, 'komend' => $komend, 'soorten' => $soorten, 'rijen' => $rijen,
            'eigenId' => eigen_medewerker()?->id, 'huidigJaar' => $huidigJaar, 'huidigeWeek' => $huidigeWeek,
        ]);
    }

    /** Weekdetail: alle diensten van die week met telefoonnummers. */
    public function week(int $jaar, int $week)
    {
        $aantalWeken = Carbon::create($jaar, 6, 1)->isoWeeksInYear;
        if ($week < 1 || $week > $aantalWeken || $jaar < 2000 || $jaar > 2100) {
            abort(404);
        }
        [$van, $tm] = Weekindeling::datums($jaar, $week);
        $model = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $week)->first();
        $soorten = DienstSoort::actief()->get();
        $toewijzingen = $model
            ? Toewijzing::with(['medewerker', 'soort', 'ruiling'])->where('rooster_week_id', $model->id)->get()->sortBy(fn ($t) => sprintf('%03d-%d', $t->soort?->volgorde ?? 0, $t->dag_van))
            : collect();
        $perSoort = $toewijzingen->groupBy('dienst_soort_id');

        // Navigatie vorige/volgende week
        $vorige = Weekindeling::weekVanDatum($van->copy()->subWeek());
        $volgende = Weekindeling::weekVanDatum($van->copy()->addWeek());
        $vandaag = now('Europe/Amsterdam')->startOfDay();

        return view('rooster.week', [
            'jaar' => $jaar, 'week' => $week, 'van' => $van, 'tm' => $tm, 'model' => $model, 'soorten' => $soorten,
            'perSoort' => $perSoort, 'vorige' => $vorige, 'volgende' => $volgende, 'verleden' => $tm->lt($vandaag),
            'eigenId' => eigen_medewerker()?->id, 'maand' => Weekindeling::maandVanWeek($jaar, $week),
        ]);
    }

    /** Mijn diensten: komende en afgelopen toewijzingen van de ingelogde roosterpersoon. */
    public function mijn(Request $request)
    {
        $mw = eigen_medewerker();
        if (! $mw) {
            return view('rooster.mijn', ['mw' => null]);
        }
        $vandaag = now('Europe/Amsterdam')->startOfDay();
        $alle = Toewijzing::with(['week', 'soort', 'ruiling'])->where('medewerker_id', $mw->id)->get()
            ->sortBy(fn ($t) => $t->week->van->format('Y-m-d').'-'.$t->dag_van);
        $komend = $alle->filter(fn ($t) => $t->week->tm->gte($vandaag))->values();
        $afgelopen = $alle->filter(fn ($t) => $t->week->tm->lt($vandaag))->sortByDesc(fn ($t) => $t->week->van->format('Y-m-d').'-'.$t->dag_van)->values();

        $jaar = (int) $request->input('jaar', now('Europe/Amsterdam')->year);
        $jaartotaal = $this->jaartotaal($mw, $jaar);

        return view('rooster.mijn', [
            'mw' => $mw, 'komend' => $komend, 'afgelopen' => $afgelopen, 'jaar' => $jaar,
            'jaartotaal' => $jaartotaal, 'vandaag' => $vandaag,
        ]);
    }

    /** Vergoedingen per maand van de ingelogde roosterpersoon. */
    public function mijnVergoedingen(int $jaar)
    {
        $mw = eigen_medewerker();
        if (! $mw) {
            return redirect()->route('mijn-diensten');
        }
        if ($jaar < 2000 || $jaar > 2100) {
            abort(404);
        }
        $maanden = [];
        $totaalDagen = 0;
        $totaalBedrag = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            $regels = array_values(array_filter(Vergoeding::regels($jaar, $m), fn ($r) => $r['medewerker_id'] === $mw->id));
            $dagen = array_sum(array_column($regels, 'dagen'));
            $bedrag = Vergoeding::totaal($regels);
            $totaalDagen += $dagen;
            $totaalBedrag += $bedrag;
            $maanden[] = ['maand' => $m, 'naam' => Weekindeling::maandNaam($m), 'regels' => $regels, 'dagen' => $dagen, 'bedrag' => $bedrag];
        }

        return view('rooster.vergoedingen', [
            'mw' => $mw, 'jaar' => $jaar, 'maanden' => $maanden, 'totaalDagen' => $totaalDagen, 'totaalBedrag' => round($totaalBedrag, 2),
            'weekbedrag' => Vergoeding::weekbedrag(),
        ]);
    }

    /** Wie draait wanneer: zoeken op naam, resultaat = toewijzingen komende 26 weken. */
    public function wie(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        $medewerkers = collect();
        $toewijzingen = collect();
        if ($q !== '') {
            $norm = NaamMatch::normaliseer($q);
            $medewerkers = Medewerker::query()->where('naam', 'like', '%'.$q.'%')->orderBy('naam')->get();
            $match = NaamMatch::zoek($q)['medewerker'] ?? null;
            if ($match && ! $medewerkers->contains('id', $match->id)) {
                $medewerkers->push($match);
            }
            $vanDatum = now('Europe/Amsterdam')->startOfWeek()->toDateString();
            $tmDatum = now('Europe/Amsterdam')->startOfWeek()->addWeeks(26)->toDateString();
            $weekIds = RoosterWeek::where('van', '>=', $vanDatum)->where('van', '<', $tmDatum)->pluck('id');
            $toewijzingen = Toewijzing::with(['week', 'soort', 'medewerker'])->whereIn('rooster_week_id', $weekIds)
                ->where(function ($sub) use ($medewerkers, $q) {
                    $sub->whereIn('medewerker_id', $medewerkers->pluck('id'))
                        ->orWhere(fn ($s) => $s->whereNull('medewerker_id')->where('rooster_naam', 'like', '%'.$q.'%'));
                })->get()
                ->sortBy(fn ($t) => $t->week->van->format('Y-m-d').'-'.sprintf('%03d', $t->soort?->volgorde ?? 0).'-'.$t->dag_van)->values();
        }

        return view('rooster.wie', ['q' => $q, 'medewerkers' => $medewerkers, 'toewijzingen' => $toewijzingen, 'eigenId' => eigen_medewerker()?->id]);
    }

    /** Jaartotaal vergoeding (betaalde, niet-vaste dienstsoorten) op basis van de maandregels. */
    private function jaartotaal(Medewerker $mw, int $jaar): array
    {
        $dagen = 0;
        $bedrag = 0.0;
        for ($m = 1; $m <= 12; $m++) {
            foreach (Vergoeding::regels($jaar, $m) as $r) {
                if ($r['medewerker_id'] === $mw->id) {
                    $dagen += $r['dagen'];
                    $bedrag += $r['bedrag'];
                }
            }
        }

        return ['dagen' => $dagen, 'bedrag' => round($bedrag, 2)];
    }
}
