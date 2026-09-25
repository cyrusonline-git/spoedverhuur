<?php

namespace App\Http\Controllers;

use App\Models\DienstSoort;
use App\Models\Import;
use App\Models\MailTaak;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use App\Models\Toewijzing;
use App\Services\MailDienst;
use App\Services\Weekindeling;

/** Startpagina per actieve rol: medewerker (eigen diensten), manager (KPI's), admin (KPI's + status). */
class DashboardController extends Controller
{
    public function index()
    {
        $rol = actieve_rol();
        $mw = eigen_medewerker();
        $nu = now('Europe/Amsterdam');
        $vandaag = $nu->copy()->startOfDay();
        [$jaar, $weeknr] = Weekindeling::weekVanDatum($nu);

        $data = ['rol' => $rol, 'mw' => $mw, 'jaar' => $jaar, 'weeknr' => $weeknr];

        // Deze week: wie heeft dienst (voor iedereen)
        $dezeWeek = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $weeknr)->first();
        $data['dezeWeek'] = $dezeWeek;
        $data['dezeWeekToewijzingen'] = $dezeWeek
            ? Toewijzing::with(['medewerker', 'soort'])->where('rooster_week_id', $dezeWeek->id)->get()->sortBy(fn ($t) => sprintf('%03d-%d', $t->soort?->volgorde ?? 0, $t->dag_van))->values()
            : collect();
        $data['vast'] = DienstSoort::actief()->where('vast', true)->get();

        // Eigen komende diensten (komende 8 weken) + ruilverzoeken die op mij wachten
        $start = $nu->copy()->startOfWeek()->toDateString();
        $eind = $nu->copy()->startOfWeek()->addWeeks(8)->toDateString();
        if ($mw) {
            $weekIds = RoosterWeek::where('van', '>=', $start)->where('van', '<', $eind)->pluck('id');
            $data['mijnKomend'] = Toewijzing::with(['week', 'soort'])->where('medewerker_id', $mw->id)->whereIn('rooster_week_id', $weekIds)->get()
                ->sortBy(fn ($t) => $t->week->van->format('Y-m-d').'-'.$t->dag_van)->values();
            $data['mijnRuilingen'] = Ruiling::with(['van', 'naar', 'dienst.week', 'dienst.soort', 'tegenDienst.week', 'tegenDienst.soort'])
                ->where('status', 'aangevraagd')
                ->where(fn ($q) => $q->where('van_medewerker_id', $mw->id)->orWhere('naar_medewerker_id', $mw->id))
                ->orderBy('created_at')->get()
                ->filter(fn ($r) => $r->moetBevestigen()?->id === $mw->id)->values();
        } else {
            $data['mijnKomend'] = collect();
            $data['mijnRuilingen'] = collect();
        }

        if (in_array($rol, ['manager', 'admin'], true)) {
            $data += $this->kpis($nu);
        }
        if ($rol === 'admin') {
            $data['laatsteImport'] = Import::latest()->first();
            $data['onvolledig'] = Medewerker::actief()->get()->filter(fn ($m) => count($m->ontbreekt()) > 0)->values();
            $data['testModus'] = MailDienst::testModus();
            $data['mailTaken'] = MailTaak::latest()->limit(5)->get();
        }

        return view('dashboard.index', $data);
    }

    /** KPI-tegels voor manager/admin. */
    private function kpis(\Carbon\Carbon $nu): array
    {
        $soorten = DienstSoort::uitRooster()->get();
        $maandag = $nu->copy()->startOfWeek();
        $openPlekken = [];
        $zonderRooster = [];
        for ($i = 0; $i < 8; $i++) {
            $d = $maandag->copy()->addWeeks($i);
            [$j, $w] = Weekindeling::weekVanDatum($d);
            $week = RoosterWeek::where('jaar', $j)->where('weeknummer', $w)->first();
            $tw = $week ? Toewijzing::where('rooster_week_id', $week->id)->get() : collect();
            if ($tw->isEmpty()) {
                $zonderRooster[] = ['jaar' => $j, 'week' => $w, 'van' => $d];
                continue;
            }
            foreach ($soorten as $s) {
                $vanSoort = $tw->where('dienst_soort_id', $s->id);
                if ($vanSoort->isEmpty()) {
                    $openPlekken[] = ['jaar' => $j, 'week' => $w, 'soort' => $s->naam, 'reden' => 'niemand ingeroosterd'];
                    continue;
                }
                foreach ($vanSoort->whereNull('medewerker_id') as $t) {
                    $openPlekken[] = ['jaar' => $j, 'week' => $w, 'soort' => $s->naam, 'reden' => 'niet gekoppeld: '.($t->rooster_naam ?: '—')];
                }
            }
        }

        return [
            'openPlekken' => $openPlekken,
            'zonderRooster' => $zonderRooster,
            'openRuilingen' => Ruiling::where('status', 'aangevraagd')->count(),
            'ruilingen30' => Ruiling::where('created_at', '>=', $nu->copy()->subDays(30))->count(),
        ];
    }
}
