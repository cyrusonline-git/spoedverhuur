<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maandoverzicht;
use App\Models\MailTaak;
use App\Services\MaandoverzichtDienst;
use App\Services\MailDienst;
use App\Services\Vergoeding;
use App\Services\Weekindeling;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Maandoverzicht vergoedingen: preview van de regels, Excel aanmaken (sjabloon
 * "24 uurs week vergoedingen"), downloaden, versturen naar HR/payroll, vergrendelen
 * en het archief met jaaroverzicht per medewerker.
 */
class MaandController extends Controller
{
    /** Overzicht van één maand (?jaar=&maand=, standaard de vorige maand). */
    public function index(Request $request)
    {
        [$jaar, $maand] = $this->periode($request);
        $regels = Vergoeding::regels($jaar, $maand);
        $ontbrekend = MaandoverzichtDienst::ontbrekend($regels);
        [$week1, $week2] = Vergoeding::weekBereik($jaar, $maand);
        $weken = Weekindeling::wekenInMaand($jaar, $maand);

        // Ontbrekende gegevens per persoon (één regel per naam, ook als die meerdere weken draait)
        $ontbrekendPerNaam = [];
        foreach ($ontbrekend as $r) {
            $naam = $r['naam'];
            $ontbrekendPerNaam[$naam] = array_values(array_unique(array_merge($ontbrekendPerNaam[$naam] ?? [], $r['ontbreekt'])));
        }

        // Weekdatums voor de scheidingsrijen in de preview
        $weekDatums = [];
        foreach ($weken as [$j, $w]) {
            [$van, $tm] = Weekindeling::datums($j, $w);
            $weekDatums[$j.'-'.$w] = $van->format('d-m').' t/m '.$tm->format('d-m-Y');
        }

        $overzichten = Maandoverzicht::where('jaar', $jaar)->where('maand', $maand)->orderByDesc('id')->get();
        $laatsteVergrendeld = $overzichten->first(fn ($mo) => $mo->vergrendeld);
        $mailTaken = $this->mailTaken($overzichten);

        $vorige = Carbon::create($jaar, $maand, 1)->subMonthNoOverflow();
        $volgende = Carbon::create($jaar, $maand, 1)->addMonthNoOverflow();

        return view('admin.maand.index', [
            'jaar' => $jaar,
            'maand' => $maand,
            'maandnaam' => ucfirst(Weekindeling::maandNaam($maand)),
            'week1' => $week1,
            'week2' => $week2,
            'weken' => $weken,
            'weekDatums' => $weekDatums,
            'regels' => $regels,
            'totaal' => Vergoeding::totaal($regels),
            'aantalPersonen' => count(array_unique(array_map(fn ($r) => $r['medewerker_id'] ?: 'naam:'.$r['naam'], $regels))),
            'ontbrekend' => $ontbrekend,
            'ontbrekendPerNaam' => $ontbrekendPerNaam,
            'overzichten' => $overzichten,
            'mailTaken' => $mailTaken,
            'laatsteVergrendeld' => $laatsteVergrendeld,
            'vorige' => ['jaar' => (int) $vorige->format('Y'), 'maand' => (int) $vorige->format('n'), 'naam' => ucfirst(Weekindeling::maandNaam((int) $vorige->format('n')))],
            'volgende' => ['jaar' => (int) $volgende->format('Y'), 'maand' => (int) $volgende->format('n'), 'naam' => ucfirst(Weekindeling::maandNaam((int) $volgende->format('n')))],
            'testModus' => MailDienst::testModus(),
            'adressen' => MailDienst::adressen('maand_adressen', 'hr@boels.nl, time@boels.com, payroll@boels.nl'),
        ]);
    }

    /** Excel-bestand aanmaken (snapshot van dit moment); bij een vergrendelde maand wordt het een correctie. */
    public function maak(Request $request, MaandoverzichtDienst $dienst)
    {
        $data = $request->validate(['jaar' => 'required|integer|min:2020|max:2100', 'maand' => 'required|integer|min:1|max:12']);
        $jaar = (int) $data['jaar'];
        $maand = (int) $data['maand'];
        $terug = redirect()->route('admin.maand', ['jaar' => $jaar, 'maand' => $maand]);

        $regels = Vergoeding::regels($jaar, $maand);
        if (! $regels) {
            return $terug->with('fout', 'Er zijn geen betaalde diensten gevonden voor '.Weekindeling::maandNaam($maand)." $jaar; er is geen bestand aangemaakt.");
        }
        $ontbrekend = MaandoverzichtDienst::ontbrekend($regels);
        if ($ontbrekend && ! $request->boolean('toch')) {
            return $terug->with('fout', 'Bij '.count($ontbrekend).' regel(s) ontbreekt een personeelsnummer of koppeling. Bevestig "toch aanmaken" om het bestand toch te maken.');
        }

        $correctieVan = Maandoverzicht::where('jaar', $jaar)->where('maand', $maand)->where('vergrendeld', true)->orderByDesc('id')->first();
        $mo = $dienst->maak($jaar, $maand, core_gebruiker()['name'] ?? null, $correctieVan);

        $melding = ($correctieVan ? 'Correctiebestand' : 'Bestand').' "'.$mo->bestandsnaam.'" aangemaakt: '.$mo->aantal_regels.' regels, totaal '.euro($mo->totaal_bedrag).'.';
        if ($ontbrekend) {
            $melding .= ' Let op: bij '.count($ontbrekend).' regel(s) ontbreekt het personeelsnummer (leeg in het bestand).';
        }

        return $terug->with('ok', $melding);
    }

    public function download(Maandoverzicht $overzicht)
    {
        abort_unless($overzicht->bestandspad && is_file($overzicht->bestandspad), 404, 'Het bestand staat niet (meer) op de server. Maak het overzicht opnieuw aan.');

        return response()->download($overzicht->bestandspad, $overzicht->bestandsnaam, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Versturen naar HR/payroll (adressen uit de instellingen); in testmodus naar het testadres. */
    public function verstuur(Request $request, Maandoverzicht $overzicht, MailDienst $mail)
    {
        $terug = redirect()->route('admin.maand', ['jaar' => $overzicht->jaar, 'maand' => $overzicht->maand]);
        if (! $overzicht->bestandspad || ! is_file($overzicht->bestandspad)) {
            return $terug->with('fout', 'Het bestand "'.$overzicht->bestandsnaam.'" staat niet (meer) op de server; maak het overzicht opnieuw aan voordat je verstuurt.');
        }
        $opnieuw = $request->boolean('opnieuw');
        $alVerzonden = MailTaak::where('soort', 'maandoverzicht')->where('referentie', $overzicht->jaar.'-'.$overzicht->maand.':'.$overzicht->id)->where('status', 'verzonden')->exists();
        if ($alVerzonden && ! $opnieuw) {
            return $terug->with('fout', 'Dit overzicht is al verstuurd. Vink "opnieuw versturen" aan als het nogmaals moet.');
        }

        $taak = $mail->maandoverzicht($overzicht, $opnieuw, core_gebruiker()['name'] ?? null);
        $label = ucfirst(Weekindeling::maandNaam($overzicht->maand)).' '.$overzicht->jaar;
        audit('maandoverzicht.verstuurd', $label, ['overzicht_id' => $overzicht->id, 'status' => $taak->status, 'opnieuw' => $opnieuw, 'test_modus' => (bool) $taak->test_modus, 'ontvangers' => $taak->ontvangers]);

        if ($taak->status === 'verzonden') {
            $aan = implode(', ', (array) $taak->ontvangers);
            if ($taak->test_modus) {
                return $terug->with('ok', "Maandoverzicht $label verstuurd in TESTMODUS: de mail (met bijlage \"{$overzicht->bestandsnaam}\") is naar het testadres gegaan in plaats van naar $aan. Het overzicht is daarom niet als verzonden gemarkeerd.");
            }

            return $terug->with('ok', "Maandoverzicht $label verstuurd naar $aan (met bijlage \"{$overzicht->bestandsnaam}\"). Het overzicht is nu vergrendeld.");
        }
        if ($taak->status === 'overgeslagen') {
            return $terug->with('fout', 'Niet verstuurd: '.($taak->fout ?: 'overgeslagen').' Controleer de instellingen (maandadressen / testadres).');
        }

        return $terug->with('fout', 'Versturen mislukt: '.($taak->fout ?: 'onbekende fout').' Zie het mailcentrum voor details.');
    }

    /** Vergrendelen / ontgrendelen (toggle). */
    public function vergrendel(Maandoverzicht $overzicht)
    {
        $nieuw = ! $overzicht->vergrendeld;
        $overzicht->update(['vergrendeld' => $nieuw]);
        $label = ucfirst(Weekindeling::maandNaam($overzicht->maand)).' '.$overzicht->jaar;
        audit($nieuw ? 'maandoverzicht.vergrendeld' : 'maandoverzicht.ontgrendeld', $label, ['overzicht_id' => $overzicht->id, 'bestand' => $overzicht->bestandsnaam]);

        return redirect()->route('admin.maand', ['jaar' => $overzicht->jaar, 'maand' => $overzicht->maand])
            ->with('ok', 'Overzicht "'.$overzicht->bestandsnaam.'" is '.($nieuw ? 'vergrendeld. Een nieuwe aanmaak voor deze maand wordt een correctie.' : 'ontgrendeld.'));
    }

    /** Archief: alle overzichten per jaar + jaaroverzicht per medewerker. */
    public function archief(Request $request)
    {
        $alle = Maandoverzicht::orderByDesc('jaar')->orderByDesc('maand')->orderByDesc('id')->get();
        $perJaar = $alle->groupBy('jaar');
        $mailTaken = $this->mailTaken($alle);

        $jaren = $alle->pluck('jaar')->unique()->push((int) now()->format('Y'))->unique()->sortDesc()->values()->all();
        $jaar = (int) $request->input('jaar', $jaren[0]);
        if (! in_array($jaar, $jaren, true)) {
            $jaren[] = $jaar;
            rsort($jaren);
        }

        // Jaaroverzicht: per medewerker het totaal per maand (regels per maand één keer berekenen)
        $regelsPerMaand = [];
        for ($m = 1; $m <= 12; $m++) {
            $regelsPerMaand[$m] = Vergoeding::regels($jaar, $m);
        }
        $matrix = [];       // naam => ['maanden' => [m => bedrag], 'totaal' => x, 'medewerker_id' => id]
        $kolomTotaal = array_fill(1, 12, 0.0);
        foreach ($regelsPerMaand as $m => $regels) {
            foreach ($regels as $r) {
                $naam = $r['naam'];
                $matrix[$naam]['medewerker_id'] = $r['medewerker_id'];
                $matrix[$naam]['maanden'][$m] = round(($matrix[$naam]['maanden'][$m] ?? 0) + $r['bedrag'], 2);
                $matrix[$naam]['totaal'] = round(($matrix[$naam]['totaal'] ?? 0) + $r['bedrag'], 2);
                $kolomTotaal[$m] = round($kolomTotaal[$m] + $r['bedrag'], 2);
            }
        }
        ksort($matrix, SORT_NATURAL | SORT_FLAG_CASE);
        $maandenMetData = array_keys(array_filter($regelsPerMaand));

        return view('admin.maand.archief', [
            'perJaar' => $perJaar,
            'mailTaken' => $mailTaken,
            'jaar' => $jaar,
            'jaren' => $jaren,
            'matrix' => $matrix,
            'kolomTotaal' => $kolomTotaal,
            'jaarTotaal' => round(array_sum($kolomTotaal), 2),
            'maandenMetData' => $maandenMetData,
            'aantalPerMaand' => array_map('count', $regelsPerMaand),
        ]);
    }

    // ---------- hulpjes ----------

    /** [jaar, maand] uit de query; standaard de vorige maand. */
    private function periode(Request $request): array
    {
        $standaard = now('Europe/Amsterdam')->subMonthNoOverflow();
        $jaar = (int) $request->input('jaar', $standaard->format('Y'));
        $maand = (int) $request->input('maand', $standaard->format('n'));
        if ($jaar < 2020 || $jaar > 2100) {
            $jaar = (int) $standaard->format('Y');
        }
        if ($maand < 1 || $maand > 12) {
            $maand = (int) $standaard->format('n');
        }

        return [$jaar, $maand];
    }

    /** Laatste mailtaak per overzicht (sleutel = overzicht-id). */
    private function mailTaken($overzichten): array
    {
        if ($overzichten->isEmpty()) {
            return [];
        }
        $refs = $overzichten->map(fn ($mo) => $mo->jaar.'-'.$mo->maand.':'.$mo->id)->all();
        $uit = [];
        foreach (MailTaak::where('soort', 'maandoverzicht')->whereIn('referentie', $refs)->get() as $t) {
            $id = (int) substr($t->referentie, strrpos($t->referentie, ':') + 1);
            $uit[$id] = $t;
        }

        return $uit;
    }
}
