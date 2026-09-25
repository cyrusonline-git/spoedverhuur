<?php

namespace Tests\Feature;

use App\Models\Dienst;
use App\Models\DienstSoort;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use App\Models\Setting;
use App\Models\Toewijzing;
use App\Services\NaamMatch;
use App\Services\RoosterImport;
use App\Services\Toewijzingen;
use App\Services\Vergoeding;
use App\Services\Weekindeling;
use App\Services\XlsxSchrijver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekenkernTest extends TestCase
{
    use RefreshDatabase;

    public function test_maandregel_donderdag_klopt_met_lijsten_2025(): void
    {
        // september 2025 = weken 36-39, oktober 2025 = weken 40-44 (zoals de bestaande vergoedingenlijsten)
        $this->assertSame([[2025, 36], [2025, 37], [2025, 38], [2025, 39]], Weekindeling::wekenInMaand(2025, 9));
        $this->assertSame([[2025, 40], [2025, 41], [2025, 42], [2025, 43], [2025, 44]], Weekindeling::wekenInMaand(2025, 10));
        $this->assertSame([[2025, 32], [2025, 33], [2025, 34], [2025, 35]], Weekindeling::wekenInMaand(2025, 8));
        $this->assertSame(['jaar' => 2025, 'maand' => 10], Weekindeling::maandVanWeek(2025, 40));
        $this->assertSame(['jaar' => 2025, 'maand' => 10], Weekindeling::maandVanWeek(2025, 44));
        // jaargrens: week 1 van 2026 (29-12-2025 t/m 4-1-2026) hoort bij januari 2026
        $this->assertSame(['jaar' => 2026, 'maand' => 1], Weekindeling::maandVanWeek(2026, 1));
        [$ma, $zo] = Weekindeling::datums(2026, 1);
        $this->assertSame('2025-12-29', $ma->toDateString());
        $this->assertSame('2026-01-04', $zo->toDateString());
    }

    public function test_bedragen_per_dagen(): void
    {
        $this->assertSame(100.0, Vergoeding::bedrag(7, 100));
        $this->assertSame(85.71, Vergoeding::bedrag(6, 100));
        $this->assertSame(57.14, Vergoeding::bedrag(4, 100));
        $this->assertSame(14.29, Vergoeding::bedrag(1, 100));
        $this->assertSame(28.57, Vergoeding::bedrag(2, 100));
    }

    public function test_naamherkenning_varianten_en_gedeelde_cellen(): void
    {
        $k = Medewerker::create(['naam' => 'Jefke Knubben']);
        $w = Medewerker::create(['naam' => 'Khachig Wanes']);
        Medewerker::create(['naam' => 'Wouter Zwackhalen']);
        Medewerker::create(['naam' => 'Maurice Daaleman']);
        Medewerker::create(['naam' => 'Maurice Brons']);
        $this->assertSame($k->id, NaamMatch::zoek('Jeffe Knubben')['medewerker']?->id);
        $this->assertSame($k->id, NaamMatch::zoek('Sjefke Knubben')['medewerker']?->id);
        $this->assertSame($w->id, NaamMatch::zoek('Khasing Wanes')['medewerker']?->id);
        $this->assertSame($w->id, NaamMatch::zoek('khachig wanes ')['medewerker']?->id);
        $this->assertSame(['Wouter', 'Maurice'], NaamMatch::splits('Wouter& Maurice'));
        $this->assertSame(['Patrick', 'David'], NaamMatch::splits('Patrick/ David'));
        $this->assertSame(['Raymond Servani', 'Matsu M'], NaamMatch::splits('Raymond Servani/Matsu M'));
        $this->assertSame('Wouter Zwackhalen', NaamMatch::zoek('Wouter')['medewerker']?->naam);
        $this->assertNull(NaamMatch::zoek('Maurice')['medewerker'], 'twee Maurices: niet automatisch koppelen');
        NaamMatch::leerAlias('Maurice', Medewerker::where('naam', 'Maurice Brons')->first());
        $this->assertSame('alias', NaamMatch::zoek('Maurice')['zekerheid']);
    }

    public function test_ruil_overname_en_deel_geven_juiste_toewijzingen_en_vergoeding(): void
    {
        Setting::set('weekbedrag', '100');
        $a = Medewerker::create(['naam' => 'Anne Vasseur', 'personeelsnummer' => '17301']);
        $b = Medewerker::create(['naam' => 'Daniel Marques', 'personeelsnummer' => '17376']);
        $s1 = DienstSoort::create(['naam' => 'Spoedverhuur 1e lijns', 'volgorde' => 0]);
        $s2 = DienstSoort::create(['naam' => 'Spoedverhuur Backup', 'volgorde' => 1]);
        $sv = DienstSoort::create(['naam' => 'Tweede lijn', 'volgorde' => 9, 'vast' => true, 'betaald' => false]);
        $w40 = RoosterWeek::voor(2025, 40);
        $w41 = RoosterWeek::voor(2025, 41);
        $d1 = Dienst::create(['rooster_week_id' => $w40->id, 'dienst_soort_id' => $s1->id, 'medewerker_id' => $a->id]);
        $d2 = Dienst::create(['rooster_week_id' => $w41->id, 'dienst_soort_id' => $s1->id, 'medewerker_id' => $b->id]);
        $d3 = Dienst::create(['rooster_week_id' => $w40->id, 'dienst_soort_id' => $s2->id, 'medewerker_id' => $b->id]);
        $t = app(Toewijzingen::class);
        $t->herbereken($w40);
        $t->herbereken($w41);
        $this->assertSame($a->id, Toewijzing::where('dienst_id', $d1->id)->first()->medewerker_id);

        // Ruil: A (wk40 1e lijns) <-> B (wk41 1e lijns)
        $r = Ruiling::create(['type' => 'ruil', 'dienst_id' => $d1->id, 'tegen_dienst_id' => $d2->id, 'van_medewerker_id' => $a->id, 'naar_medewerker_id' => $b->id, 'status' => 'bevestigd', 'bevestigd_op' => now()]);
        $t->herberekenRuiling($r);
        $this->assertSame($b->id, Toewijzing::where('dienst_id', $d1->id)->first()->medewerker_id);
        $this->assertSame($a->id, Toewijzing::where('dienst_id', $d2->id)->first()->medewerker_id);
        $this->assertSame('ruil', Toewijzing::where('dienst_id', $d1->id)->first()->oorsprong);

        // Deel: B geeft do-zo (4-7) van de backup wk40 aan A
        $r2 = Ruiling::create(['type' => 'deel', 'dienst_id' => $d3->id, 'van_medewerker_id' => $b->id, 'naar_medewerker_id' => $a->id, 'dagen_van' => 4, 'dagen_tm' => 7, 'status' => 'bevestigd', 'bevestigd_op' => now()]);
        $t->herbereken($w40);
        $blokken = Toewijzing::where('dienst_id', $d3->id)->orderBy('dag_van')->get();
        $this->assertCount(2, $blokken);
        $this->assertSame([$b->id, 1, 3], [$blokken[0]->medewerker_id, $blokken[0]->dag_van, $blokken[0]->dag_tm]);
        $this->assertSame([$a->id, 4, 7], [$blokken[1]->medewerker_id, $blokken[1]->dag_van, $blokken[1]->dag_tm]);

        // Vergoeding oktober 2025: B week 40 1e lijns (7d=100), B backup 1-3 (3d=42,86), A backup 4-7 (4d=57,14), A week 41 (100)
        $regels = Vergoeding::regels(2025, 10);
        $this->assertCount(4, $regels);
        $per = [];
        foreach ($regels as $x) {
            $per[$x['naam'].'|'.$x['week'].'|'.$x['dienst_soort']] = $x['bedrag'];
        }
        $this->assertSame(100.0, $per['Daniel Marques|40|Spoedverhuur 1e lijns']);
        $this->assertSame(42.86, $per['Daniel Marques|40|Spoedverhuur Backup']);
        $this->assertSame(57.14, $per['Anne Vasseur|40|Spoedverhuur Backup']);
        $this->assertSame(100.0, $per['Anne Vasseur|41|Spoedverhuur 1e lijns']);
        $this->assertSame(300.0, Vergoeding::totaal($regels));

        // Terugdraaien van de ruil: A weer op wk40
        $r->update(['status' => 'teruggedraaid']);
        $t->herberekenRuiling($r);
        $this->assertSame($a->id, Toewijzing::where('dienst_id', $d1->id)->first()->medewerker_id);
    }

    public function test_xlsx_schrijver_maakt_leesbaar_bestand(): void
    {
        $pad = sys_get_temp_dir().'/test-'.uniqid().'.xlsx';
        (new XlsxSchrijver('Test'))->cel('A1', 'Naam', true)->cel('B1', 42)->cel('C1', 85.71)->cel('A2', 'Jürgen & co')->bedragKolom('C')->schrijf($pad);
        $lezer = new \App\Services\XlsxLezer($pad);
        $rijen = iterator_to_array($lezer->rijen());
        $this->assertSame('Naam', $rijen[1]['A']);
        $this->assertSame(42.0, $rijen[1]['B']);
        $this->assertSame(85.71, $rijen[1]['C']);
        $this->assertSame('Jürgen & co', $rijen[2]['A']);
        unlink($pad);
    }

    public function test_import_van_echt_rooster(): void
    {
        $bestand = '/Users/Wim/Downloads/Dienstrooster-2026 Industrial 22-09-2026.xlsx';
        if (! is_file($bestand)) {
            $this->markTestSkipped('Voorbeeldrooster niet aanwezig');
        }
        Medewerker::create(['naam' => 'Jose Alves Almeida']);
        Medewerker::create(['naam' => 'Wouter Zwackhalen']);
        Medewerker::create(['naam' => 'Maurice Daaleman']);
        $imp = app(RoosterImport::class);
        $p = $imp->lees($bestand);
        $this->assertSame(2026, $p['jaar']);
        $this->assertCount(53, $p['weken']);
        $this->assertCount(9, $p['kolommen']);
        $this->assertSame('2025-12-29', $p['weken'][0]['van']);
        // gedeelde cel week 18 (rij 'Week 18', 27-04 t/m 03-05): "Wouter& Maurice"
        $wk19 = collect($p['weken'])->firstWhere('week', 18);
        $soort1e = DienstSoort::where('kolom_kop', 'Spoedverhuur 1e lijns')->first();
        $this->assertCount(2, $wk19['cellen'][$soort1e->id]);
        $this->assertTrue($wk19['cellen'][$soort1e->id][0]['gedeeld']);
        $this->assertArrayHasKey('Stefanos Krommydas', $p['onbekend']);
        $import = $imp->verwerk($p, 'test.xlsx', [], [], ookVerleden: true);
        $this->assertSame(53, $import->aantal_weken);
        $this->assertGreaterThan(53 * 9, $import->aantal_diensten);
        $this->assertSame(2, Dienst::whereHas('week', fn ($q) => $q->where('weeknummer', 18))->where('dienst_soort_id', $soort1e->id)->count());
        $deel = Dienst::whereHas('week', fn ($q) => $q->where('weeknummer', 18))->where('dienst_soort_id', $soort1e->id)->orderBy('deel_van')->get();
        $this->assertSame([1, 3], [$deel[0]->deel_van, $deel[0]->deel_tm]);
        $this->assertSame([4, 7], [$deel[1]->deel_van, $deel[1]->deel_tm]);
        $this->assertSame(53, RoosterWeek::count());
        $this->assertGreaterThan(400, Toewijzing::count());
    }
}
