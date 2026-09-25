<?php

namespace App\Services;

use App\Models\Dienst;
use App\Models\DienstSoort;
use App\Models\Import;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use Illuminate\Support\Facades\DB;

/**
 * Het Excel-dienstrooster inlezen ("Dienstrooster-2026 Industrial.xlsx"):
 *   rij 1 = koppen: Week # | Van | t/m | <dienstsoort> ... (9 kolommen)
 *   rij 2.. = "Week 12" | maandag | zondag | naam per dienstsoort
 * Stap 1 `lees()` geeft een voorbeeld (preview) met herkende weken, namen en onbekenden.
 * Stap 2 `verwerk()` schrijft alleen weken die nog niet voorbij zijn en die geen
 *   bevestigde ruiling hebben; bestaande toekomstige diensten worden vervangen.
 */
class RoosterImport
{
    /** Preview-structuur: ['jaar','blad','weken'=>[[jaar,week,van,tm,cellen=>[soortId=>[[naam,medewerker,zekerheid],...]]]], 'kolommen', 'onbekend'=>[naam=>aantal], 'meldingen'] */
    public function lees(string $pad): array
    {
        $lezer = new XlsxLezer($pad);
        $meldingen = [];
        $kolommen = [];   // kolomletter => DienstSoort
        $weken = [];
        $medewerkers = Medewerker::all()->all();
        $cache = [];
        $onbekend = [];
        $jaar = null;
        $kopGezien = false;
        foreach ($lezer->rijen() as $nr => $rij) {
            $a = trim((string) ($rij['A'] ?? ''));
            if (! $kopGezien) {
                if (preg_match('/^week/i', $a) && ! preg_match('/^week\s*\d/i', $a)) {
                    // koprij
                    foreach ($rij as $kol => $kop) {
                        if (in_array($kol, ['A', 'B', 'C'], true)) {
                            continue;
                        }
                        $kop = trim((string) $kop);
                        if ($kop === '') {
                            continue;
                        }
                        $kolommen[$kol] = $this->soortVoorKop($kop, count($kolommen));
                    }
                    $kopGezien = true;
                }
                continue;
            }
            if (! preg_match('/^week\s*(\d{1,2})/i', $a, $m)) {
                continue;
            }
            $weeknr = (int) $m[1];
            $van = $this->datum($rij['B'] ?? null);
            $tm = $this->datum($rij['C'] ?? null);
            if (! $van) {
                $meldingen[] = "Rij $nr: geen geldige begindatum bij week $weeknr, overgeslagen.";
                continue;
            }
            [$isoJaar, $isoWeek] = Weekindeling::weekVanDatum($van);
            if ($isoWeek !== $weeknr) {
                $meldingen[] = "Rij $nr: '$a' hoort volgens de datum ({$van->format('d-m-Y')}) bij ISO-week $isoWeek; de datum is aangehouden.";
            }
            $jaar ??= $isoJaar;
            $cellen = [];
            foreach ($kolommen as $kol => $soort) {
                $tekst = trim((string) ($rij[$kol] ?? ''));
                $cellen[$soort->id] = [];
                if ($tekst === '') {
                    continue;
                }
                $namen = NaamMatch::splits($tekst);
                foreach ($namen as $naam) {
                    $key = NaamMatch::normaliseer($naam);
                    if (! isset($cache[$key])) {
                        $cache[$key] = NaamMatch::zoek($naam, $medewerkers);
                    }
                    $res = $cache[$key];
                    if (! $res['medewerker']) {
                        $onbekend[$naam] = ($onbekend[$naam] ?? 0) + 1;
                    }
                    $cellen[$soort->id][] = [
                        'naam' => $naam, 'tekst' => $tekst, 'gedeeld' => count($namen) > 1,
                        'medewerker_id' => $res['medewerker']?->id, 'medewerker_naam' => $res['medewerker']?->naam, 'zekerheid' => $res['zekerheid'],
                    ];
                }
            }
            $weken[] = ['jaar' => $isoJaar, 'week' => $isoWeek, 'van' => $van->toDateString(), 'tm' => ($tm ?: $van->copy()->addDays(6))->toDateString(), 'cellen' => $cellen];
        }
        if (! $kopGezien) {
            $meldingen[] = 'Geen koprij gevonden (eerste cel moet "Week #" zijn).';
        }
        ksort($onbekend);

        return ['jaar' => $jaar, 'blad' => $lezer->bladnaam(), 'weken' => $weken, 'kolommen' => $kolommen, 'onbekend' => $onbekend, 'meldingen' => $meldingen];
    }

    /**
     * Verwerken. $koppelingen = [genormaliseerde naam => medewerker_id] (uit het koppelscherm),
     * $verdeling = ['jaar-week-soortId' => [[medewerker_id, dag_van, dag_tm], ...]] voor gedeelde cellen.
     * Geeft het Import-record terug.
     */
    public function verwerk(array $preview, string $bestandsnaam, array $koppelingen = [], array $verdeling = [], bool $ookVerleden = false): Import
    {
        $meldingen = $preview['meldingen'];
        $vandaag = now('Europe/Amsterdam')->startOfWeek();
        $import = Import::create(['bestandsnaam' => $bestandsnaam, 'blad' => $preview['blad'], 'jaar' => $preview['jaar'], 'door_naam' => core_gebruiker()['name'] ?? null]);
        $nWeken = 0;
        $nDiensten = 0;
        $toewijzingen = app(Toewijzingen::class);
        DB::transaction(function () use ($preview, $koppelingen, $verdeling, $ookVerleden, $vandaag, $import, &$meldingen, &$nWeken, &$nDiensten, $toewijzingen) {
            foreach ($preview['weken'] as $w) {
                $week = RoosterWeek::voor($w['jaar'], $w['week']);
                if (! $ookVerleden && $week->tm->lt($vandaag)) {
                    continue; // verleden blijft zoals het was
                }
                $bestaandeIds = Dienst::where('rooster_week_id', $week->id)->pluck('id')->all();
                $metRuiling = $bestaandeIds ? Ruiling::where('status', 'bevestigd')->where(fn ($q) => $q->whereIn('dienst_id', $bestaandeIds)->orWhereIn('tegen_dienst_id', $bestaandeIds))->exists() : false;
                if ($metRuiling) {
                    $meldingen[] = "Week {$w['week']} ({$w['van']}) heeft een bevestigde ruiling en is niet overschreven.";
                    continue;
                }
                Dienst::where('rooster_week_id', $week->id)->where('bron', 'import')->delete();
                foreach ($w['cellen'] as $soortId => $personen) {
                    $sleutel = $w['jaar'].'-'.$w['week'].'-'.$soortId;
                    if (isset($verdeling[$sleutel])) {
                        foreach ($verdeling[$sleutel] as $v) {
                            Dienst::create(['rooster_week_id' => $week->id, 'dienst_soort_id' => $soortId, 'medewerker_id' => $v[0] ?: null, 'rooster_naam' => $v[3] ?? null, 'deel_van' => $v[1], 'deel_tm' => $v[2], 'bron' => 'import', 'import_id' => $import->id]);
                            $nDiensten++;
                        }
                        continue;
                    }
                    $aantal = count($personen);
                    foreach ($personen as $i => $p) {
                        $mid = $p['medewerker_id'] ?? null;
                        $norm = NaamMatch::normaliseer($p['naam']);
                        if (! $mid && isset($koppelingen[$norm])) {
                            $mid = (int) $koppelingen[$norm] ?: null;
                        }
                        // Gedeelde cel zonder opgegeven verdeling: standaard ma-wo / do-zo
                        $van = $tm = null;
                        if ($aantal === 2) {
                            [$van, $tm] = $i === 0 ? [1, 3] : [4, 7];
                        } elseif ($aantal > 2) {
                            $per = intdiv(7, $aantal);
                            $van = 1 + $i * $per;
                            $tm = $i === $aantal - 1 ? 7 : $van + $per - 1;
                        }
                        Dienst::create(['rooster_week_id' => $week->id, 'dienst_soort_id' => $soortId, 'medewerker_id' => $mid, 'rooster_naam' => $p['naam'], 'deel_van' => $van, 'deel_tm' => $tm, 'bron' => 'import', 'import_id' => $import->id]);
                        $nDiensten++;
                    }
                }
                $toewijzingen->herbereken($week);
                $nWeken++;
            }
            // Koppelingen die de beheerder heeft gekozen als alias onthouden
            foreach ($koppelingen as $norm => $mid) {
                if ($mid && ($m = Medewerker::find($mid))) {
                    $orig = collect($preview['weken'])->flatMap(fn ($w) => collect($w['cellen'])->flatten(1))->first(fn ($p) => NaamMatch::normaliseer($p['naam']) === $norm);
                    NaamMatch::leerAlias($orig['naam'] ?? $norm, $m);
                }
            }
        });
        $import->update(['aantal_weken' => $nWeken, 'aantal_diensten' => $nDiensten, 'meldingen' => $meldingen]);

        return $import;
    }

    private function soortVoorKop(string $kop, int $volgorde): DienstSoort
    {
        $norm = NaamMatch::normaliseer($kop);
        $soort = DienstSoort::all()->first(fn ($s) => NaamMatch::normaliseer((string) $s->kolom_kop) === $norm || NaamMatch::normaliseer($s->naam) === $norm);
        if ($soort) {
            if ($soort->kolom_kop !== $kop) {
                $soort->update(['kolom_kop' => $kop]);
            }

            return $soort;
        }

        return DienstSoort::create(['naam' => $kop, 'kolom_kop' => $kop, 'volgorde' => $volgorde, 'betaald' => true, 'naar_multiline' => true, 'actief' => true]);
    }

    private function datum(mixed $v): ?\Carbon\Carbon
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_numeric($v)) { // Excel-serienummer
            return \Carbon\Carbon::create(1899, 12, 30, 0, 0, 0, 'Europe/Amsterdam')->addDays((int) $v);
        }
        try {
            return \Carbon\Carbon::parse((string) $v, 'Europe/Amsterdam')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
