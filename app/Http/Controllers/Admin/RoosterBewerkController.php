<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dienst;
use App\Models\DienstSoort;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use App\Services\Toewijzingen;
use App\Services\Weekindeling;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Rooster handmatig bewerken per week (geplande diensten); daarna wordt het effectieve rooster herberekend. */
class RoosterBewerkController extends Controller
{
    public function week(int $jaar, int $week)
    {
        $this->controleer($jaar, $week);
        [$van, $tm] = Weekindeling::datums($jaar, $week);
        $model = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $week)->first();
        $soorten = DienstSoort::uitRooster()->get();
        $diensten = $model ? Dienst::with('medewerker')->where('rooster_week_id', $model->id)->orderBy('deel_van')->orderBy('id')->get() : collect();
        $ids = $diensten->pluck('id')->all();
        $ruilingen = $ids
            ? Ruiling::with(['van', 'naar', 'dienst.soort', 'tegenDienst.soort'])->whereIn('status', ['bevestigd', 'aangevraagd'])
                ->where(fn ($q) => $q->whereIn('dienst_id', $ids)->orWhereIn('tegen_dienst_id', $ids))->get()
            : collect();
        $metRuiling = [];
        foreach ($ruilingen as $r) {
            $metRuiling[$r->dienst_id][] = $r;
            if ($r->tegen_dienst_id) {
                $metRuiling[$r->tegen_dienst_id][] = $r;
            }
        }

        return view('admin.rooster.bewerk', [
            'jaar' => $jaar, 'week' => $week, 'van' => $van, 'tm' => $tm, 'model' => $model, 'soorten' => $soorten,
            'perSoort' => $diensten->groupBy('dienst_soort_id'), 'medewerkers' => Medewerker::actief()->get(),
            'ruilingen' => $ruilingen, 'metRuiling' => $metRuiling,
            'vorige' => Weekindeling::weekVanDatum($van->copy()->subWeek()), 'volgende' => Weekindeling::weekVanDatum($van->copy()->addWeek()),
        ]);
    }

    /**
     * Opslaan: per dienstsoort de regels uit het formulier. Bestaande regels (met id) worden bijgewerkt zodat
     * ruilingen eraan blijven hangen; weggehaalde regels worden verwijderd (inclusief hun ruilingen); nieuwe aangemaakt.
     */
    public function opslaan(Request $request, int $jaar, int $week, Toewijzingen $toewijzingen)
    {
        $this->controleer($jaar, $week);
        $model = RoosterWeek::voor($jaar, $week);
        $soorten = DienstSoort::uitRooster()->get()->keyBy('id');
        $medewerkerIds = Medewerker::pluck('id')->all();
        $regels = (array) $request->input('regels', []);
        $samenvatting = [];
        $verwijderdMetRuiling = 0;
        $nRegels = 0;

        \Illuminate\Support\Facades\DB::transaction(function () use ($regels, $soorten, $model, $medewerkerIds, &$samenvatting, &$verwijderdMetRuiling, &$nRegels) {
            foreach ($soorten as $soortId => $soort) {
                $bestaand = Dienst::where('rooster_week_id', $model->id)->where('dienst_soort_id', $soortId)->get()->keyBy('id');
                $behouden = [];
                foreach ((array) ($regels[$soortId] ?? []) as $rij) {
                    $mid = isset($rij['medewerker_id']) && $rij['medewerker_id'] !== '' && in_array((int) $rij['medewerker_id'], $medewerkerIds, true) ? (int) $rij['medewerker_id'] : null;
                    $naam = trim((string) ($rij['rooster_naam'] ?? ''));
                    if (! $mid && $naam === '') {
                        continue; // lege regel
                    }
                    $van = (int) ($rij['dag_van'] ?? 0);
                    $tm = (int) ($rij['dag_tm'] ?? 0);
                    if ($van < 1 || $van > 7 || $tm < 1 || $tm > 7) {
                        $van = $tm = null; // hele week
                    } elseif ($tm < $van) {
                        [$van, $tm] = [$tm, $van];
                    }
                    if ($van === 1 && $tm === 7) {
                        $van = $tm = null;
                    }
                    $velden = ['medewerker_id' => $mid, 'rooster_naam' => $naam !== '' ? $naam : null, 'deel_van' => $van, 'deel_tm' => $tm, 'bron' => 'handmatig'];
                    $id = (int) ($rij['id'] ?? 0);
                    if ($id && $bestaand->has($id)) {
                        $d = $bestaand->get($id);
                        $gewijzigd = false;
                        foreach ($velden as $k => $v) {
                            if ($k !== 'bron' && $d->$k != $v) {
                                $gewijzigd = true;
                            }
                        }
                        if ($gewijzigd) {
                            $d->update($velden);
                        } elseif ($d->bron !== 'handmatig') {
                            // ongewijzigd: importregel blijft zoals hij was
                        }
                        $behouden[] = $id;
                    } else {
                        $d = Dienst::create($velden + ['rooster_week_id' => $model->id, 'dienst_soort_id' => $soortId]);
                        $behouden[] = $d->id;
                    }
                    $nRegels++;
                    $samenvatting[$soort->naam][] = ($mid ? Medewerker::find($mid)?->naam : $naam).($van ? ' ('.Weekindeling::dagNaam($van, true).'-'.Weekindeling::dagNaam($tm, true).')' : '');
                }
                foreach ($bestaand as $id => $d) {
                    if (! in_array($id, $behouden, true)) {
                        if (Ruiling::where('dienst_id', $id)->orWhere('tegen_dienst_id', $id)->exists()) {
                            $verwijderdMetRuiling++;
                        }
                        $d->delete();
                    }
                }
            }
        });
        $toewijzingen->herbereken($model);
        audit('rooster.bewerkt', 'week '.$week.' ('.$jaar.')', ['jaar' => $jaar, 'week' => $week, 'regels' => $nRegels, 'diensten' => $samenvatting, 'verwijderd_met_ruiling' => $verwijderdMetRuiling]);

        $bericht = 'Week '.$week.' opgeslagen ('.$nRegels.' regels); het effectieve rooster is opnieuw berekend.';
        if ($verwijderdMetRuiling > 0) {
            $bericht .= ' Let op: '.$verwijderdMetRuiling.' verwijderde regel(s) hadden een ruiling; die ruilingen zijn mee verwijderd.';
        }

        return redirect()->route('rooster.week', [$jaar, $week])->with('ok', $bericht);
    }

    /** Alle weken opnieuw berekenen (diensten + bevestigde ruilingen → toewijzingen). */
    public function herbereken(Request $request, Toewijzingen $toewijzingen)
    {
        $n = $toewijzingen->herberekenAlles();
        audit('rooster.herberekend', 'alle weken', ['weken' => $n]);

        return redirect()->to($request->input('terug') ?: route('rooster'))->with('ok', 'Effectief rooster opnieuw berekend voor '.$n.' weken.');
    }

    private function controleer(int $jaar, int $week): void
    {
        if ($jaar < 2000 || $jaar > 2100 || $week < 1 || $week > Carbon::create($jaar, 6, 1)->isoWeeksInYear) {
            abort(404);
        }
    }
}
