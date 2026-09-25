<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MailDienst;
use App\Support\Instellingen;
use Illuminate\Http\Request;

class InstellingenController extends Controller
{
    public function index()
    {
        $groepen = Instellingen::groepen();
        $waarden = [];
        foreach ($groepen as $velden) {
            foreach ($velden as $key => [$label, $standaard]) {
                $waarden[$key] = setting($key, $standaard);
            }
        }

        return view('admin.instellingen', [
            'groepen' => $groepen,
            'waarden' => $waarden,
            'testModus' => MailDienst::testModus(),
            'testAdres' => MailDienst::adressen('test_adres'),
        ]);
    }

    public function opslaan(Request $request)
    {
        $groepen = Instellingen::groepen();
        $fouten = [];
        $nieuw = [];
        $gewijzigd = [];
        foreach ($groepen as $groep => $velden) {
            foreach ($velden as $key => [$label, $standaard, $uitleg, $type]) {
                $ruw = $request->input($key);
                $waarde = $this->normaliseer($type, $ruw, $fout);
                if ($fout) {
                    $fouten[$key] = $label.': '.$fout;
                    continue;
                }
                $nieuw[$key] = $waarde;
            }
        }
        if ($fouten) {
            return back()->withInput()->withErrors($fouten)->with('fout', 'Niet opgeslagen: '.count($fouten).' veld(en) zijn ongeldig.');
        }
        foreach ($nieuw as $key => $waarde) {
            $oud = setting($key);
            if ((string) $oud !== (string) $waarde) {
                $gewijzigd[$key] = ['van' => $oud, 'naar' => $waarde];
            }
            Setting::set($key, $waarde);
        }
        audit('instellingen.opgeslagen', count($gewijzigd).' gewijzigd', ['gewijzigd' => $gewijzigd]);

        $msg = 'Instellingen opgeslagen ('.count($gewijzigd).' gewijzigd).';
        if ((int) $nieuw['test_modus'] === 1 && $nieuw['test_adres'] === '') {
            $msg .= ' Let op: testmodus staat aan zonder testadres — er wordt nu niets verstuurd.';
        }

        return redirect()->route('admin.instellingen')->with('ok', $msg);
    }

    /** Waarde volgens veldtype opschonen en controleren; $fout wordt gevuld bij een ongeldige waarde. */
    private function normaliseer(string $type, mixed $ruw, ?string &$fout): string
    {
        $fout = null;
        $v = is_array($ruw) ? '' : trim((string) $ruw);
        if ($type === 'bool') {
            return $ruw ? '1' : '0';
        }
        if ($type === 'email_lijst') {
            $adressen = array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', $v))));
            foreach ($adressen as $a) {
                if (! filter_var($a, FILTER_VALIDATE_EMAIL)) {
                    $fout = "\"$a\" is geen geldig e-mailadres (meerdere adressen scheiden met een komma).";

                    return $v;
                }
            }

            return implode(', ', $adressen);
        }
        if ($type === 'tijd') {
            if (! preg_match('/^(\d{1,2}):(\d{2})$/', $v, $m) || (int) $m[1] > 23 || (int) $m[2] > 59) {
                $fout = 'Vul een tijd in als UU:MM (bv. 07:00).';

                return $v;
            }

            return sprintf('%02d:%02d', $m[1], $m[2]);
        }
        if ($type === 'getal') {
            $n = str_replace(',', '.', $v);
            if ($v === '' || ! is_numeric($n)) {
                $fout = 'Vul een getal in.';

                return $v;
            }

            return (string) ($n + 0);
        }
        if ($type === 'dag') {
            if (! in_array((int) $v, [1, 2, 3, 4, 5, 6, 7], true)) {
                $fout = 'Kies een dag.';

                return $v;
            }

            return (string) (int) $v;
        }
        if (str_starts_with($type, 'keuze:')) {
            $opties = explode('|', substr($type, 6));
            if (! in_array($v, $opties, true)) {
                $fout = 'Kies een van: '.implode(', ', $opties).'.';

                return $v;
            }

            return $v;
        }
        // text: getrimd; textarea: regeleinden normaliseren, alleen trailing witruimte weg
        return $type === 'textarea' ? rtrim(str_replace("\r\n", "\n", (string) (is_array($ruw) ? '' : $ruw))) : $v;
    }
}
