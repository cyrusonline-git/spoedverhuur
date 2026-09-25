<?php

namespace App\Http\Controllers;

use App\Models\DienstSoort;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use App\Models\Toewijzing;
use App\Services\MailDienst;
use App\Services\NaamMatch;
use App\Services\Ruilen;
use App\Services\Vergoeding;
use App\Services\Weekindeling;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Overzichten voor manager en admin: dashboard, bezetting komende weken,
 * ruilingen, belasting (wie draait hoeveel), Multiline-weeklijst en CSV-export.
 * Alles leest het effectieve rooster (`toewijzingen`).
 */
class ManagerController extends Controller
{
    private const RUIL_RELATIES = ['dienst.soort', 'dienst.week', 'tegenDienst.soort', 'tegenDienst.week', 'van', 'naar', 'aangevraagdDoor'];

    // ---------- Dashboard ----------

    public function index()
    {
        $bez = $this->bezettingMatrix(8);
        $ontbreekt = Medewerker::actief()->get()->filter(fn ($m) => $m->ontbreekt())->values();
        $recent = Ruiling::with(self::RUIL_RELATIES)->orderByDesc('created_at')->orderByDesc('id')->limit(5)->get();
        $this->verrijkRuilingen($recent);

        return view('overzicht.index', [
            'openPlekken' => count($bez['open']),
            'openPlekkenLijst' => $bez['open'],
            'openRuil' => Ruiling::where('status', 'aangevraagd')->count(),
            'bevestigd30' => Ruiling::where('status', 'bevestigd')->where('bevestigd_op', '>=', now()->subDays(30))->count(),
            'bevestigd90' => Ruiling::where('status', 'bevestigd')->where('bevestigd_op', '>=', now()->subDays(90))->count(),
            'wekenZonderRooster' => array_values(array_filter($bez['weken'], fn ($w) => ! $w['heeft_rooster'])),
            'ontbreekt' => $ontbreekt,
            'komende' => array_slice($bez['weken'], 0, 4),
            'soorten' => $bez['soorten'],
            'recent' => $recent,
        ]);
    }

    // ---------- Bezetting ----------

    public function bezetting(Request $request)
    {
        $n = (int) $request->input('weken', 8);
        if (! in_array($n, [8, 13, 26], true)) {
            $n = 8;
        }
        $bez = $this->bezettingMatrix($n);

        return view('overzicht.bezetting', $bez + ['aantal' => $n]);
    }

    /**
     * Matrix komende $n weken × dienstsoorten uit het rooster.
     * Cel-status: ok | open (geen toewijzing) | ongekoppeld (toewijzing zonder medewerker).
     */
    private function bezettingMatrix(int $n): array
    {
        $weken = $this->komendeWeken($n);
        $soorten = DienstSoort::uitRooster()->get();
        $rw = RoosterWeek::where(function ($q) use ($weken) {
            foreach ($weken as $w) {
                $q->orWhere(fn ($x) => $x->where('jaar', $w['jaar'])->where('weeknummer', $w['week']));
            }
        })->get()->keyBy(fn ($w) => $w->jaar.'-'.$w->weeknummer);
        $tw = Toewijzing::with(['medewerker', 'soort'])->whereIn('rooster_week_id', $rw->pluck('id'))->orderBy('dag_van')->get();
        $perWeek = $tw->groupBy('rooster_week_id');
        $perCel = $tw->groupBy(fn ($t) => $t->rooster_week_id.'-'.$t->dienst_soort_id);
        $open = [];
        foreach ($weken as $i => $w) {
            $week = $rw->get($w['jaar'].'-'.$w['week']);
            $weken[$i]['rooster'] = $week;
            $weken[$i]['heeft_rooster'] = $week !== null && $perWeek->has($week->id);
            $cellen = [];
            foreach ($soorten as $s) {
                $lijst = $week ? $perCel->get($week->id.'-'.$s->id, collect()) : collect();
                $status = $lijst->isEmpty() ? 'open' : ($lijst->contains(fn ($t) => ! $t->medewerker_id) ? 'ongekoppeld' : 'ok');
                $cellen[$s->id] = ['status' => $status, 'toewijzingen' => $lijst];
                if ($status !== 'ok') {
                    $open[] = [
                        'jaar' => $w['jaar'], 'week' => $w['week'], 'van' => $w['van'], 'tm' => $w['tm'], 'soort' => $s->naam, 'status' => $status,
                        'naam' => $status === 'ongekoppeld' ? $lijst->map(fn ($t) => $t->naam())->unique()->implode(', ') : null,
                    ];
                }
            }
            $weken[$i]['cellen'] = $cellen;
        }

        return ['weken' => $weken, 'soorten' => $soorten, 'open' => $open];
    }

    /** Komende $n ISO-weken vanaf de huidige week: [['jaar','week','van','tm'], ...] */
    private function komendeWeken(int $n): array
    {
        [$j, $w] = Weekindeling::weekVanDatum(now('Europe/Amsterdam'));
        $d = Carbon::now('Europe/Amsterdam')->setISODate($j, $w, 1)->startOfDay();
        $uit = [];
        for ($i = 0; $i < $n; $i++) {
            $uit[] = ['jaar' => (int) $d->format('o'), 'week' => (int) $d->format('W'), 'van' => $d->copy(), 'tm' => $d->copy()->addDays(6)];
            $d->addWeek();
        }

        return $uit;
    }

    // ---------- Ruilingen ----------

    public function ruilingen(Request $request)
    {
        $lijst = $this->ruilingenQuery($request)->get();
        $this->verrijkRuilingen($lijst);

        return view('overzicht.ruilingen', [
            'lijst' => $lijst,
            'filters' => $this->ruilFilters($request),
            'medewerkers' => Medewerker::orderBy('naam')->get(),
            'statussen' => Ruiling::STATUSSEN,
            'stat' => $this->ruilStatistiek($lijst),
        ]);
    }

    private function ruilFilters(Request $request): array
    {
        return [
            'van' => $request->input('van', now()->subMonths(6)->startOfMonth()->toDateString()),
            'tot' => $request->input('tot', ''),
            'status' => $request->input('status', ''),
            'medewerker' => $request->input('medewerker', ''),
        ];
    }

    private function ruilingenQuery(Request $request)
    {
        $f = $this->ruilFilters($request);
        $q = Ruiling::with(self::RUIL_RELATIES)->orderByDesc('created_at')->orderByDesc('id');
        if ($f['van']) {
            $q->where('created_at', '>=', Carbon::parse($f['van'])->startOfDay());
        }
        if ($f['tot']) {
            $q->where('created_at', '<=', Carbon::parse($f['tot'])->endOfDay());
        }
        if ($f['status']) {
            $q->where('status', $f['status']);
        }
        if ($f['medewerker']) {
            $q->where(fn ($x) => $x->where('van_medewerker_id', $f['medewerker'])->orWhere('naar_medewerker_id', $f['medewerker']));
        }

        return $q;
    }

    /** Omschrijving en doorlooptijd (uren) als extra attributen op elke ruiling. */
    private function verrijkRuilingen($lijst): void
    {
        $ruilen = app(Ruilen::class);
        foreach ($lijst as $r) {
            $r->setAttribute('omschrijving', $ruilen->omschrijving($r));
            $eind = $r->bevestigd_op ?? ($r->status === 'aangevraagd' ? null : $r->updated_at);
            $r->setAttribute('doorlooptijd_uren', $eind ? round($r->created_at->diffInMinutes($eind) / 60, 1) : null);
        }
    }

    public static function uren(?float $uren): string
    {
        if ($uren === null) {
            return '—';
        }
        if ($uren < 1) {
            return round($uren * 60).' min';
        }
        if ($uren < 48) {
            return number_format($uren, 1, ',', '.').' uur';
        }

        return number_format($uren / 24, 1, ',', '.').' dagen';
    }

    private function ruilStatistiek($lijst): array
    {
        $per = [];
        $tel = function (?Medewerker $m, string $kolom) use (&$per) {
            if (! $m) {
                return;
            }
            $per[$m->id] ??= ['naam' => $m->naam, 'gegeven' => 0, 'ontvangen' => 0, 'geruild' => 0, 'totaal' => 0];
            $per[$m->id][$kolom]++;
            $per[$m->id]['totaal']++;
        };
        $bevestigd = $lijst->where('status', 'bevestigd');
        foreach ($bevestigd as $r) {
            if ($r->type === 'ruil') {
                $tel($r->van, 'geruild');
                $tel($r->naar, 'geruild');
            } else {
                $tel($r->van, 'gegeven');
                $tel($r->naar, 'ontvangen');
            }
        }
        uasort($per, fn ($a, $b) => [$b['totaal'], $a['naam']] <=> [$a['totaal'], $b['naam']]);
        $door = $bevestigd->pluck('doorlooptijd_uren')->filter(fn ($u) => $u !== null);
        $perMaand = [];
        foreach ($lijst as $r) {
            $k = $r->created_at->format('Y-m');
            $perMaand[$k] ??= ['label' => Weekindeling::maandNaam((int) $r->created_at->format('n')).' '.$r->created_at->format('Y'), 'aantal' => 0, 'bevestigd' => 0];
            $perMaand[$k]['aantal']++;
            if ($r->status === 'bevestigd') {
                $perMaand[$k]['bevestigd']++;
            }
        }
        ksort($perMaand);

        return [
            'per_medewerker' => $per,
            'max_medewerker' => $per ? max(array_column($per, 'totaal')) : 0,
            'gem_doorlooptijd' => $door->count() ? round($door->avg(), 1) : null,
            'aantal_bevestigd' => $bevestigd->count(),
            'per_status' => $lijst->countBy('status')->all(),
            'per_maand' => $perMaand,
            'max_maand' => $perMaand ? max(array_column($perMaand, 'aantal')) : 0,
        ];
    }

    // ---------- Belasting ----------

    public function belasting(Request $request)
    {
        $jaar = (int) $request->input('jaar', now('Europe/Amsterdam')->format('o'));
        $jaren = RoosterWeek::select('jaar')->distinct()->orderByDesc('jaar')->pluck('jaar')->push($jaar)->unique()->sortDesc()->values();

        return view('overzicht.belasting', $this->belastingData($jaar) + ['jaar' => $jaar, 'jaren' => $jaren]);
    }

    private function belastingData(int $jaar): array
    {
        $weekIds = RoosterWeek::where('jaar', $jaar)->pluck('id');
        $soorten = DienstSoort::where('vast', false)->orderBy('volgorde')->get();
        $wb = Vergoeding::weekbedrag();
        $rijen = [];
        foreach (Toewijzing::with('medewerker')->whereIn('rooster_week_id', $weekIds)->get() as $t) {
            $soort = $soorten->firstWhere('id', $t->dienst_soort_id);
            if (! $soort) {
                continue;
            }
            $key = $t->medewerker_id ? 'm'.$t->medewerker_id : 'n'.NaamMatch::normaliseer((string) $t->rooster_naam);
            if (! isset($rijen[$key])) {
                $rijen[$key] = ['medewerker_id' => $t->medewerker_id, 'naam' => $t->naam(), 'gekoppeld' => (bool) $t->medewerker_id, 'weken' => [], 'dagen' => 0, 'vergoeding' => 0.0, 'per_soort' => []];
            }
            $rijen[$key]['weken'][$t->rooster_week_id] = true;
            $rijen[$key]['dagen'] += $t->dagen();
            $rijen[$key]['per_soort'][$soort->id] ??= ['weken' => [], 'dagen' => 0];
            $rijen[$key]['per_soort'][$soort->id]['weken'][$t->rooster_week_id] = true;
            $rijen[$key]['per_soort'][$soort->id]['dagen'] += $t->dagen();
            if ($soort->betaald) {
                $rijen[$key]['vergoeding'] = round($rijen[$key]['vergoeding'] + Vergoeding::bedrag($t->dagen(), $wb), 2);
            }
        }
        foreach ($rijen as &$rij) {
            $rij['aantal_weken'] = count($rij['weken']);
            foreach ($rij['per_soort'] as &$ps) {
                $ps['aantal_weken'] = count($ps['weken']);
            }
            unset($ps);
        }
        unset($rij);
        uasort($rijen, fn ($a, $b) => [$b['dagen'], $b['aantal_weken'], $a['naam']] <=> [$a['dagen'], $a['aantal_weken'], $b['naam']]);
        $metDienst = array_filter(array_column($rijen, 'medewerker_id'));
        $zonder = Medewerker::actief()->whereNotIn('id', $metDienst)->get();

        return ['rijen' => array_values($rijen), 'soorten' => $soorten, 'zonder' => $zonder, 'maxDagen' => $rijen ? max(array_column($rijen, 'dagen')) : 0, 'aantalWeken' => $weekIds->count()];
    }

    // ---------- Multiline-weeklijst ----------

    public function multiline(int $jaar, int $week, MailDienst $mail)
    {
        $rw = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $week)->first();
        [$van, $tm] = Weekindeling::datums($jaar, $week);
        $rijen = $rw ? array_values(array_filter($mail->weekLijst($rw), fn ($r) => $r['multiline'])) : [];
        $vorige = $van->copy()->subWeek();
        $volgende = $van->copy()->addWeek();

        return view('overzicht.multiline', [
            'jaar' => $jaar, 'week' => $week, 'rw' => $rw, 'van' => $van, 'tm' => $tm, 'rijen' => $rijen,
            'vorige' => Weekindeling::weekVanDatum($vorige), 'volgende' => Weekindeling::weekVanDatum($volgende),
            'verzonden' => $rw ? \App\Models\MailTaak::where('soort', 'multiline')->where('referentie', $jaar.'-'.$week)->first() : null,
        ]);
    }

    // ---------- CSV-export ----------

    public function export(string $wat, Request $request): StreamedResponse
    {
        [$kop, $rijen, $naam] = match ($wat) {
            'bezetting' => $this->exportBezetting($request),
            'ruilingen' => $this->exportRuilingen($request),
            'belasting' => $this->exportBelasting($request),
            default => abort(404, 'Onbekende export.'),
        };

        return response()->streamDownload(function () use ($kop, $rijen) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, $kop, ';', '"', '\\', "\r\n");
            foreach ($rijen as $rij) {
                fputcsv($f, $rij, ';', '"', '\\', "\r\n");
            }
            fclose($f);
        }, $naam.'-'.now()->format('Ymd-Hi').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function exportBezetting(Request $request): array
    {
        $n = (int) $request->input('weken', 8);
        $bez = $this->bezettingMatrix(in_array($n, [8, 13, 26], true) ? $n : 8);
        $kop = ['Jaar', 'Week', 'Van', 'T/m'];
        foreach ($bez['soorten'] as $s) {
            $kop[] = $s->naam;
        }
        $rijen = [];
        foreach ($bez['weken'] as $w) {
            $rij = [$w['jaar'], $w['week'], $w['van']->format('d-m-Y'), $w['tm']->format('d-m-Y')];
            foreach ($bez['soorten'] as $s) {
                $cel = $w['cellen'][$s->id];
                $rij[] = $cel['status'] === 'open' ? 'OPEN' : $cel['toewijzingen']->map(fn ($t) => $t->naam().($t->heleWeek() ? '' : ' ('.Weekindeling::dagNaam($t->dag_van, true).'-'.Weekindeling::dagNaam($t->dag_tm, true).')').($t->medewerker_id ? '' : ' [niet gekoppeld]'))->implode(' / ');
            }
            $rijen[] = $rij;
        }

        return [$kop, $rijen, 'bezetting'];
    }

    private function exportRuilingen(Request $request): array
    {
        $lijst = $this->ruilingenQuery($request)->get();
        $this->verrijkRuilingen($lijst);
        $rijen = [];
        foreach ($lijst as $r) {
            $rijen[] = [$r->id, $r->typeLabel(), $r->van?->naam, $r->naar?->naam, $r->dienst?->soort?->naam, $r->dienst?->week?->jaar, $r->dienst?->week?->weeknummer,
                $r->omschrijving, $r->statusLabel(), $r->aangevraagdDoor?->naam, $r->created_at->format('d-m-Y H:i'), $r->bevestigd_op?->format('d-m-Y H:i'), $r->doorlooptijd_uren !== null ? str_replace('.', ',', (string) $r->doorlooptijd_uren) : '', $r->afgehandeld_door, $r->opmerking];
        }

        return [['Nr', 'Type', 'Van', 'Naar', 'Dienst', 'Jaar', 'Week', 'Omschrijving', 'Status', 'Aangevraagd door', 'Aangevraagd op', 'Bevestigd op', 'Doorlooptijd (uur)', 'Afgehandeld door', 'Opmerking'], $rijen, 'ruilingen'];
    }

    private function exportBelasting(Request $request): array
    {
        $jaar = (int) $request->input('jaar', now('Europe/Amsterdam')->format('o'));
        $d = $this->belastingData($jaar);
        $kop = ['Naam', 'Gekoppeld', 'Weken', 'Dagen', 'Vergoeding'];
        foreach ($d['soorten'] as $s) {
            $kop[] = $s->naam.' (weken)';
            $kop[] = $s->naam.' (dagen)';
        }
        $rijen = [];
        foreach ($d['rijen'] as $rij) {
            $r = [$rij['naam'], $rij['gekoppeld'] ? 'ja' : 'nee', $rij['aantal_weken'], $rij['dagen'], number_format($rij['vergoeding'], 2, ',', '')];
            foreach ($d['soorten'] as $s) {
                $r[] = $rij['per_soort'][$s->id]['aantal_weken'] ?? 0;
                $r[] = $rij['per_soort'][$s->id]['dagen'] ?? 0;
            }
            $rijen[] = $r;
        }
        foreach ($d['zonder'] as $m) {
            $rijen[] = array_merge([$m->naam, 'ja', 0, 0, '0,00'], array_fill(0, $d['soorten']->count() * 2, 0));
        }

        return [$kop, $rijen, 'belasting-'.$jaar];
    }
}
