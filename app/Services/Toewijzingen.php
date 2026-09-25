<?php

namespace App\Services;

use App\Models\Dienst;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use App\Models\Toewijzing;
use Illuminate\Support\Facades\DB;

/**
 * Het effectieve rooster berekenen: geplande diensten + bevestigde ruilingen
 * (in volgorde van bevestiging). Resultaat staat in `toewijzingen`, de enige
 * bron voor mails, de Multiline-lijst en de vergoedingen.
 */
class Toewijzingen
{
    /** Eén week opnieuw berekenen. */
    public function herbereken(RoosterWeek $week): void
    {
        DB::transaction(function () use ($week) {
            // Basis: per dienst één blok (dag_van..dag_tm) met de geplande persoon
            $blokken = []; // dienst_id => lijst van [medewerker_id, rooster_naam, dag_van, dag_tm, oorsprong, ruiling_id]
            $diensten = Dienst::where('rooster_week_id', $week->id)->get();
            foreach ($diensten as $d) {
                $blokken[$d->id] = [[
                    'medewerker_id' => $d->medewerker_id, 'rooster_naam' => $d->rooster_naam,
                    'dag_van' => $d->dagVan(), 'dag_tm' => $d->dagTm(), 'oorsprong' => $d->bron === 'handmatig' ? 'handmatig' : 'rooster', 'ruiling_id' => null,
                ]];
            }
            // Bevestigde ruilingen toepassen, in volgorde van bevestiging
            $ruilingen = Ruiling::where('status', 'bevestigd')
                ->where(function ($q) use ($diensten) {
                    $ids = $diensten->pluck('id')->all();
                    $q->whereIn('dienst_id', $ids)->orWhereIn('tegen_dienst_id', $ids);
                })
                ->orderBy('bevestigd_op')->orderBy('id')->get();
            foreach ($ruilingen as $r) {
                if ($r->type === 'ruil') {
                    // A's dienst gaat naar B, B's dienst (mogelijk in een andere week) naar A
                    if (isset($blokken[$r->dienst_id])) {
                        $blokken[$r->dienst_id] = $this->vervang($blokken[$r->dienst_id], $r->van_medewerker_id, $r->naar_medewerker_id, 'ruil', $r->id);
                    }
                    if ($r->tegen_dienst_id && isset($blokken[$r->tegen_dienst_id])) {
                        $blokken[$r->tegen_dienst_id] = $this->vervang($blokken[$r->tegen_dienst_id], $r->naar_medewerker_id, $r->van_medewerker_id, 'ruil', $r->id);
                    }
                } elseif ($r->type === 'overname') {
                    if (isset($blokken[$r->dienst_id])) {
                        $blokken[$r->dienst_id] = $this->vervang($blokken[$r->dienst_id], $r->van_medewerker_id, $r->naar_medewerker_id, 'overname', $r->id);
                    }
                } elseif ($r->type === 'deel') {
                    if (isset($blokken[$r->dienst_id])) {
                        $blokken[$r->dienst_id] = $this->deel($blokken[$r->dienst_id], $r->van_medewerker_id, $r->naar_medewerker_id, (int) $r->dagen_van, (int) $r->dagen_tm, $r->id);
                    }
                }
            }
            Toewijzing::where('rooster_week_id', $week->id)->delete();
            foreach ($diensten as $d) {
                foreach ($blokken[$d->id] ?? [] as $b) {
                    Toewijzing::create([
                        'rooster_week_id' => $week->id, 'dienst_soort_id' => $d->dienst_soort_id,
                        'medewerker_id' => $b['medewerker_id'], 'rooster_naam' => $b['rooster_naam'],
                        'dag_van' => $b['dag_van'], 'dag_tm' => $b['dag_tm'], 'oorsprong' => $b['oorsprong'],
                        'dienst_id' => $d->id, 'ruiling_id' => $b['ruiling_id'],
                    ]);
                }
            }
        });
    }

    /** Alle weken (of alleen toekomstige) opnieuw berekenen. */
    public function herberekenAlles(bool $alleenToekomst = false): int
    {
        $q = RoosterWeek::query();
        if ($alleenToekomst) {
            $q->toekomst();
        }
        $n = 0;
        foreach ($q->get() as $w) {
            $this->herbereken($w);
            $n++;
        }

        return $n;
    }

    /** Weken van een ruiling herberekenen (beide diensten). */
    public function herberekenRuiling(Ruiling $r): void
    {
        $weken = [];
        if ($r->dienst) {
            $weken[$r->dienst->rooster_week_id] = $r->dienst->week;
        }
        if ($r->tegenDienst) {
            $weken[$r->tegenDienst->rooster_week_id] = $r->tegenDienst->week;
        }
        foreach ($weken as $w) {
            $this->herbereken($w);
        }
    }

    /** Alle blokken van $vanId binnen een dienst aan $naarId geven. */
    private function vervang(array $blokken, ?int $vanId, int $naarId, string $oorsprong, int $ruilingId): array
    {
        foreach ($blokken as &$b) {
            if ($b['medewerker_id'] === $vanId || $vanId === null) {
                $b['medewerker_id'] = $naarId;
                $b['rooster_naam'] = null;
                $b['oorsprong'] = $oorsprong;
                $b['ruiling_id'] = $ruilingId;
            }
        }

        return $blokken;
    }

    /** Dagen dagVan..dagTm van $vanId afsplitsen naar $naarId; de rest blijft bij $vanId. */
    private function deel(array $blokken, ?int $vanId, int $naarId, int $dagVan, int $dagTm, int $ruilingId): array
    {
        $uit = [];
        foreach ($blokken as $b) {
            if ($b['medewerker_id'] !== $vanId || $dagTm < $b['dag_van'] || $dagVan > $b['dag_tm']) {
                $uit[] = $b;
                continue;
            }
            $sv = max($dagVan, $b['dag_van']);
            $st = min($dagTm, $b['dag_tm']);
            if ($b['dag_van'] < $sv) {
                $uit[] = array_merge($b, ['dag_tm' => $sv - 1]);
            }
            $uit[] = array_merge($b, ['medewerker_id' => $naarId, 'rooster_naam' => null, 'dag_van' => $sv, 'dag_tm' => $st, 'oorsprong' => 'deel', 'ruiling_id' => $ruilingId]);
            if ($b['dag_tm'] > $st) {
                $uit[] = array_merge($b, ['dag_van' => $st + 1]);
            }
        }
        usort($uit, fn ($a, $b) => $a['dag_van'] <=> $b['dag_van']);

        return $uit;
    }
}
