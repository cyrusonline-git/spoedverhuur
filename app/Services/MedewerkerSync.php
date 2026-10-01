<?php

namespace App\Services;

use App\Models\Medewerker;
use Illuminate\Support\Facades\Cache;

/**
 * Medewerkers spiegelen uit de CORE-medewerkerkaarten (GET /api/employees).
 * CORE is leidend voor naam, e-mail, telefoon en personeelsnummer; handmatige
 * velden in deze app zijn alleen een terugval voor wie (nog) niet in CORE staat.
 */
class MedewerkerSync
{
    public function __construct(private CoreSso $sso)
    {
    }

    /** Geeft het aantal verwerkte kaarten, of null als CORE niet bereikbaar was. */
    public function sync(): ?int
    {
        $lijst = $this->sso->employees();
        if ($lijst === null) {
            return null;
        }
        $n = 0;
        $gezien = [];
        foreach ($lijst as $e) {
            $id = (int) ($e['id'] ?? 0);
            $naam = trim((string) ($e['name'] ?? ''));
            if (! $id || $naam === '') {
                continue;
            }
            $gezien[] = $id;
            $m = Medewerker::where('core_employee_id', $id)->first();
            // Al handmatig aangemaakt onder dezelfde naam? Dan koppelen i.p.v. dubbel aanmaken
            if (! $m) {
                $m = Medewerker::whereNull('core_employee_id')->get()->first(fn ($x) => NaamMatch::normaliseer($x->naam) === NaamMatch::normaliseer($naam));
            }
            $velden = [
                'core_employee_id' => $id,
                'naam' => $naam,
                'email' => $e['email'] ?: null,
                'telefoon' => $e['phone'] ?: null,
                'personeelsnummer' => isset($e['employee_number']) && $e['employee_number'] !== '' ? trim((string) $e['employee_number']) : null,
                'actief' => (bool) ($e['active'] ?? true),
                'laatst_gesynct_at' => now(),
            ];
            $m ? $m->update($velden) : Medewerker::create($velden);
            $n++;
        }
        Cache::put('medewerkers.gesynct_op', now()->timestamp, 86400);

        return $n;
    }

    /** Automatisch synchroniseren als het lang geleden is (max 1x per 6 uur). */
    public function syncIndienNodig(): void
    {
        $laatst = (int) Cache::get('medewerkers.gesynct_op', 0);
        if (time() - $laatst > 6 * 3600) {
            try {
                $this->sync();
            } catch (\Throwable $e) {
                report($e);
            }
            Cache::put('medewerkers.gesynct_op', now()->timestamp, 86400);
        }
    }

    /**
     * Loopt alle geplande diensten na die nog geen medewerker gekoppeld hebben en
     * probeert ze alsnog te koppelen aan een medewerker (zelfde naamherkenning als de
     * import). Herberekent daarna de toewijzingen van de geraakte weken.
     *
     * @return array{gekoppeld:int, open:int, namen:array<string,int>}
     */
    public function herkoppel(): array
    {
        $medewerkers = \App\Models\Medewerker::all()->all();
        $open = \App\Models\Dienst::whereNull('medewerker_id')->whereNotNull('rooster_naam')->get();
        $gekoppeld = 0;
        $weken = [];
        $onbekend = [];
        $cache = [];
        foreach ($open as $dienst) {
            $naam = trim((string) $dienst->rooster_naam);
            if ($naam === '') {
                continue;
            }
            $key = NaamMatch::normaliseer($naam);
            if (! array_key_exists($key, $cache)) {
                $cache[$key] = NaamMatch::zoek($naam, $medewerkers)['medewerker'];
            }
            $m = $cache[$key];
            if ($m) {
                $dienst->update(['medewerker_id' => $m->id]);
                $gekoppeld++;
                $weken[$dienst->rooster_week_id] = true;
            } else {
                $onbekend[$naam] = ($onbekend[$naam] ?? 0) + 1;
            }
        }
        if ($weken) {
            $toewijzingen = app(Toewijzingen::class);
            foreach (\App\Models\RoosterWeek::whereIn('id', array_keys($weken))->get() as $week) {
                $toewijzingen->herbereken($week);
            }
        }
        ksort($onbekend);

        return ['gekoppeld' => $gekoppeld, 'open' => array_sum($onbekend), 'namen' => $onbekend];
    }
}
