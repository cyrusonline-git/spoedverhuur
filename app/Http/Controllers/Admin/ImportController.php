<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Import;
use App\Models\Medewerker;
use App\Services\NaamMatch;
use App\Services\RoosterImport;
use Illuminate\Http\Request;

/**
 * Rooster importeren in drie stappen: upload → voorbeeld + koppelscherm → verwerken.
 * Het voorbeeld (preview) wordt als JSON bewaard in storage/app/imports/<token>.json;
 * alleen het token gaat via het formulier en de sessie mee.
 */
class ImportController extends Controller
{
    public function index()
    {
        return view('admin.import.index', ['imports' => Import::latest()->limit(10)->get()]);
    }

    /** Stap 2: bestand opslaan, inlezen en het koppelscherm tonen. */
    public function lees(Request $request, RoosterImport $importer)
    {
        $request->validate([
            'bestand' => ['required', 'file', 'max:10240', 'mimes:xlsx', 'extensions:xlsx'],
        ], [
            'bestand.required' => 'Kies eerst een Excel-bestand (.xlsx).',
            'bestand.max' => 'Het bestand mag maximaal 10 MB zijn.',
            'bestand.mimes' => 'Alleen .xlsx-bestanden worden ondersteund.',
            'bestand.extensions' => 'Alleen .xlsx-bestanden worden ondersteund.',
        ]);
        $map = $this->map();
        $token = uniqid('imp', true);
        $token = preg_replace('/[^a-z0-9]/i', '', $token);
        $bestand = $request->file('bestand');
        $bestandsnaam = $bestand->getClientOriginalName();
        $bestand->move($map, $token.'.xlsx');
        $pad = $map.'/'.$token.'.xlsx';

        try {
            $preview = $importer->lees($pad);
        } catch (\Throwable $e) {
            @unlink($pad);

            return redirect()->route('admin.import')->with('fout', 'Het bestand kon niet gelezen worden: '.$e->getMessage());
        }
        if (empty($preview['weken'])) {
            @unlink($pad);

            return redirect()->route('admin.import')->with('fout', 'Geen weken gevonden in het bestand. '.implode(' ', $preview['meldingen'] ?? []));
        }

        // Alleen id/naam van de dienstsoorten bewaren (geen modellen in JSON)
        $kolommen = [];
        foreach ($preview['kolommen'] as $kol => $soort) {
            $kolommen[$kol] = ['id' => $soort->id, 'naam' => $soort->naam];
        }
        $preview['kolommen'] = $kolommen;
        $preview['bestandsnaam'] = $bestandsnaam;
        file_put_contents($map.'/'.$token.'.json', json_encode($preview, JSON_UNESCAPED_UNICODE));
        $request->session()->put('import_token', $token);
        $this->ruimOp($map);

        return view('admin.import.preview', $this->preview_data($preview, $token));
    }

    /** Stap 3: koppelingen en verdeling toepassen en het rooster wegschrijven. */
    public function verwerk(Request $request, RoosterImport $importer)
    {
        $token = preg_replace('/[^a-z0-9]/i', '', (string) $request->input('token'));
        $map = $this->map();
        $json = $map.'/'.$token.'.json';
        if ($token === '' || $token !== $request->session()->get('import_token') || ! is_file($json)) {
            return redirect()->route('admin.import')->with('fout', 'Het voorbeeld is verlopen of niet gevonden. Upload het bestand opnieuw.');
        }
        $preview = json_decode((string) file_get_contents($json), true);
        if (! is_array($preview) || empty($preview['weken'])) {
            return redirect()->route('admin.import')->with('fout', 'Het voorbeeld kon niet gelezen worden. Upload het bestand opnieuw.');
        }
        $bestandsnaam = (string) ($preview['bestandsnaam'] ?? 'rooster.xlsx');
        $medewerkerIds = Medewerker::pluck('id')->all();
        $geldig = fn ($v) => $v !== null && $v !== '' && in_array((int) $v, $medewerkerIds, true) ? (int) $v : null;

        // 1. Koppelingen voor onbekende namen: [genormaliseerde naam => medewerker_id]
        $koppelingen = [];
        foreach ((array) $request->input('koppel', []) as $rij) {
            $naam = trim((string) ($rij['naam'] ?? ''));
            $mid = $geldig($rij['medewerker_id'] ?? null);
            if ($naam !== '' && $mid) {
                $koppelingen[NaamMatch::normaliseer($naam)] = $mid;
            }
        }

        // 2. Vermoedelijke matches (achternaam/voornaam) die de beheerder heeft bevestigd of gewijzigd
        $vermoedelijk = [];
        foreach ((array) $request->input('vermoed', []) as $rij) {
            $naam = trim((string) ($rij['naam'] ?? ''));
            if ($naam !== '') {
                $vermoedelijk[NaamMatch::normaliseer($naam)] = $geldig($rij['medewerker_id'] ?? null); // null = niet koppelen
            }
        }
        $namen = Medewerker::pluck('naam', 'id')->all();
        foreach ($preview['weken'] as &$w) {
            foreach ($w['cellen'] as &$personen) {
                foreach ($personen as &$p) {
                    $norm = NaamMatch::normaliseer($p['naam']);
                    if (in_array($p['zekerheid'] ?? '', ['achternaam', 'voornaam'], true) && array_key_exists($norm, $vermoedelijk)) {
                        $keuze = $vermoedelijk[$norm];
                        if ($keuze && $keuze !== ($p['medewerker_id'] ?? null)) {
                            $koppelingen[$norm] = $keuze; // alias leren
                        }
                        $p['medewerker_id'] = $keuze;
                        $p['medewerker_naam'] = $keuze ? ($namen[$keuze] ?? null) : null;
                        $p['zekerheid'] = $keuze ? 'beheerder' : 'geen';
                    }
                }
                unset($p);
            }
            unset($personen);
        }
        unset($w);

        // 3. Verdeling van gedeelde cellen: ['jaar-week-soortId' => [[medewerker_id, dag_van, dag_tm, naam], ...]]
        $verdeling = [];
        foreach ((array) $request->input('verdeling', []) as $sleutel => $rijen) {
            if (! preg_match('/^\d{4}-\d{1,2}-\d+$/', (string) $sleutel)) {
                continue;
            }
            foreach ((array) $rijen as $rij) {
                $naam = trim((string) ($rij['naam'] ?? ''));
                $van = max(1, min(7, (int) ($rij['dag_van'] ?? 1)));
                $tm = max($van, min(7, (int) ($rij['dag_tm'] ?? 7)));
                $mid = $geldig($rij['medewerker_id'] ?? null) ?: ($koppelingen[NaamMatch::normaliseer($naam)] ?? null);
                if (! $mid) {
                    $mid = $this->medewerkerUitPreview($preview, $sleutel, $naam);
                }
                $verdeling[$sleutel][] = [$mid, $van, $tm, $naam !== '' ? $naam : null];
            }
        }
        $ookVerleden = $request->boolean('ook_verleden');

        try {
            $import = $importer->verwerk($preview, $bestandsnaam, $koppelingen, $verdeling, $ookVerleden);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.import')->with('fout', 'Verwerken mislukt: '.$e->getMessage());
        }
        audit('rooster.import', $bestandsnaam, [
            'import_id' => $import->id, 'jaar' => $import->jaar, 'blad' => $import->blad,
            'weken' => $import->aantal_weken, 'diensten' => $import->aantal_diensten,
            'koppelingen' => count($koppelingen), 'verdeling' => count($verdeling), 'ook_verleden' => $ookVerleden,
        ]);
        @unlink($json);
        @unlink($map.'/'.$token.'.xlsx');
        $request->session()->forget('import_token');

        return view('admin.import.resultaat', ['import' => $import, 'ookVerleden' => $ookVerleden, 'koppelingen' => count($koppelingen)]);
    }

    /** Gegevens voor het voorbeeld-/koppelscherm. */
    private function preview_data(array $preview, string $token): array
    {
        $medewerkers = Medewerker::actief()->get();
        $vermoedelijk = [];  // naam => [medewerker_id, medewerker_naam, zekerheid, aantal]
        $gedeeld = [];       // sleutel => ['jaar','week','van','soort','personen'=>[...]]
        $soortNamen = collect($preview['kolommen'])->pluck('naam', 'id')->all();
        $nDiensten = 0;
        foreach ($preview['weken'] as $w) {
            foreach ($w['cellen'] as $soortId => $personen) {
                $nDiensten += count($personen);
                $aantal = count($personen);
                foreach ($personen as $i => $p) {
                    if (in_array($p['zekerheid'], ['achternaam', 'voornaam'], true)) {
                        $vermoedelijk[$p['naam']] ??= ['medewerker_id' => $p['medewerker_id'], 'medewerker_naam' => $p['medewerker_naam'], 'zekerheid' => $p['zekerheid'], 'aantal' => 0];
                        $vermoedelijk[$p['naam']]['aantal']++;
                    }
                    if (! empty($p['gedeeld'])) {
                        $sleutel = $w['jaar'].'-'.$w['week'].'-'.$soortId;
                        $gedeeld[$sleutel] ??= ['jaar' => $w['jaar'], 'week' => $w['week'], 'van' => $w['van'], 'soort' => $soortNamen[$soortId] ?? ('#'.$soortId), 'tekst' => $p['tekst'], 'personen' => []];
                        if ($aantal === 2) {
                            [$dv, $dt] = $i === 0 ? [1, 3] : [4, 7];
                        } else {
                            $per = intdiv(7, $aantal);
                            $dv = 1 + $i * $per;
                            $dt = $i === $aantal - 1 ? 7 : $dv + $per - 1;
                        }
                        $gedeeld[$sleutel]['personen'][] = $p + ['dag_van' => $dv, 'dag_tm' => $dt];
                    }
                }
            }
        }
        ksort($vermoedelijk);
        $vandaag = now('Europe/Amsterdam')->startOfWeek();
        $verleden = count(array_filter($preview['weken'], fn ($w) => \Carbon\Carbon::parse($w['tm'])->lt($vandaag)));

        return [
            'preview' => $preview, 'token' => $token, 'medewerkers' => $medewerkers, 'vermoedelijk' => $vermoedelijk,
            'gedeeld' => $gedeeld, 'nDiensten' => $nDiensten, 'verleden' => $verleden,
            'eerste' => $preview['weken'][0] ?? null, 'laatste' => $preview['weken'][count($preview['weken']) - 1] ?? null,
        ];
    }

    private function medewerkerUitPreview(array $preview, string $sleutel, string $naam): ?int
    {
        [$jaar, $week, $soortId] = explode('-', $sleutel);
        foreach ($preview['weken'] as $w) {
            if ((int) $w['jaar'] === (int) $jaar && (int) $w['week'] === (int) $week) {
                foreach ($w['cellen'][$soortId] ?? [] as $p) {
                    if ($p['naam'] === $naam) {
                        return $p['medewerker_id'] ?: null;
                    }
                }
            }
        }

        return null;
    }

    private function map(): string
    {
        $map = storage_path('app/imports');
        if (! is_dir($map)) {
            @mkdir($map, 0775, true);
        }

        return $map;
    }

    /** Oude tijdelijke bestanden (> 1 dag) opruimen. */
    private function ruimOp(string $map): void
    {
        foreach (glob($map.'/imp*.{xlsx,json}', GLOB_BRACE) ?: [] as $f) {
            if (filemtime($f) < time() - 86400) {
                @unlink($f);
            }
        }
    }
}
