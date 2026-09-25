<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Maandoverzicht;
use App\Models\MailTaak;
use App\Models\RoosterWeek;
use App\Models\Setting;
use App\Services\MailDienst;
use App\Services\Planner;
use App\Services\Weekindeling;
use Illuminate\Http\Request;

/**
 * Mailcentrum: status van de planner/testmodus, handmatig versturen ("Verstuur nu"),
 * preview per ontvanger, verzendlog met "Opnieuw".
 */
class MailController extends Controller
{
    public const CRON = '* * * * * php /home/deb2003831/domains/spoedverhuur.sorai.nl/laravel_app/artisan schedule:run >/dev/null 2>&1';

    /** Soorten die je hier handmatig per week kunt versturen. */
    private const WEEK_SOORTEN = ['aankondiging', 'dienst_vandaag', 'multiline'];

    public function index(Request $request, MailDienst $mail)
    {
        $nu = now('Europe/Amsterdam');
        [$jaar, $week] = Weekindeling::weekVanDatum($nu);
        $dezeWeek = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $week)->first();
        $volgendeWeek = RoosterWeek::where('van', $nu->copy()->startOfWeek()->addWeek()->toDateString())->first();
        $weken = RoosterWeek::toekomst()->orderBy('van')->limit(8)->get();
        if ($weken->isEmpty()) {
            // Geen toekomstige weken: laat de laatste 8 zien zodat er toch iets te kiezen valt
            $weken = RoosterWeek::orderByDesc('van')->limit(8)->get()->sortBy('van')->values();
        }

        // Multiline-preview: gekozen week of deze week (of de eerste beschikbare)
        $mlWeek = null;
        if ($request->filled('ml_jaar') && $request->filled('ml_week')) {
            $mlWeek = RoosterWeek::where('jaar', (int) $request->ml_jaar)->where('weeknummer', (int) $request->ml_week)->first();
        }
        $mlWeek = $mlWeek ?? $dezeWeek ?? $weken->first();
        $mlLijst = $mlWeek ? array_values(array_filter($mail->weekLijst($mlWeek), fn ($r) => $r['multiline'])) : [];

        $laatsteScheduler = MailTaak::where('aangemaakt_door', 'scheduler')->max('created_at');

        return view('admin.mail.index', [
            'testModus' => MailDienst::testModus(),
            'testAdres' => MailDienst::adressen('test_adres'),
            'automatisch' => (bool) (int) setting('automatisch_versturen', 1),
            'plannerLaatst' => setting('planner_laatst'),
            'laatsteScheduler' => $laatsteScheduler,
            'cron' => self::CRON,
            'momenten' => $this->momenten(),
            'weken' => $weken,
            'dezeWeek' => $dezeWeek,
            'volgendeWeek' => $volgendeWeek,
            'mlWeek' => $mlWeek,
            'mlLijst' => $mlLijst,
            'recent' => MailTaak::orderByDesc('id')->limit(10)->get(),
            'laatsteMaand' => Maandoverzicht::orderByDesc('id')->first(),
            'soorten' => MailTaak::SOORTEN,
        ]);
    }

    /** "Verstuur nu": soort + jaar/week (+ opnieuw). */
    public function verstuur(Request $request, MailDienst $mail)
    {
        $data = $request->validate([
            'soort' => 'required|in:aankondiging,dienst_vandaag,multiline',
            'week' => 'required|string',      // "jaar-week"
            'opnieuw' => 'nullable|boolean',
        ]);
        [$jaar, $wk] = array_map('intval', explode('-', $data['week'].'-0'));
        $week = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $wk)->first();
        if (! $week) {
            return back()->with('fout', "Week $wk van $jaar staat niet in het rooster.");
        }
        $opnieuw = (bool) ($data['opnieuw'] ?? false);
        $door = core_gebruiker()['name'] ?? null;

        $taken = $this->voerUit($mail, $data['soort'], $week, $opnieuw, $door);
        $res = $this->samenvatting($taken, $opnieuw);
        audit('mail.verstuurd', $data['soort'], ['week' => $week->jaar.'-'.$week->weeknummer, 'opnieuw' => $opnieuw] + $res['tellingen']);

        return redirect()->route('admin.mail')
            ->with($res['mislukt'] > 0 ? 'fout' : 'ok', MailTaak::SOORTEN[$data['soort']].' — '.$week->label().': '.$res['tekst'])
            ->with('mail_resultaat', $res['regels']);
    }

    /** Dezelfde soort/referentie nog eens versturen vanuit het log. */
    public function opnieuw(MailTaak $taak, MailDienst $mail)
    {
        $door = core_gebruiker()['name'] ?? null;
        $terug = redirect()->route('admin.mail.log', request()->query());

        if (in_array($taak->soort, self::WEEK_SOORTEN, true)) {
            // referentie "jaar-week" of "jaar-week:medewerker"
            if (! preg_match('/^(\d{4})-(\d{1,2})/', $taak->referentie, $m)) {
                return $terug->with('fout', 'Referentie niet herkend; handmatig opnieuw niet mogelijk.');
            }
            $week = RoosterWeek::where('jaar', (int) $m[1])->where('weeknummer', (int) $m[2])->first();
            if (! $week) {
                return $terug->with('fout', "Week {$m[2]} van {$m[1]} staat niet (meer) in het rooster.");
            }
            $taken = $this->voerUit($mail, $taak->soort, $week, true, $door);
            $res = $this->samenvatting($taken, true);
            audit('mail.verstuurd', $taak->soort, ['week' => $week->jaar.'-'.$week->weeknummer, 'opnieuw' => true, 'via' => 'log:'.$taak->id] + $res['tellingen']);

            return $terug->with($res['mislukt'] > 0 ? 'fout' : 'ok', MailTaak::SOORTEN[$taak->soort].' — '.$week->label().': '.$res['tekst']);
        }

        if ($taak->soort === 'maandoverzicht') {
            // referentie "jaar-maand:id"
            $id = (int) substr((string) strrchr($taak->referentie, ':'), 1);
            $mo = $id ? Maandoverzicht::find($id) : null;
            if (! $mo) {
                return $terug->with('fout', 'Maandoverzicht niet gevonden; maak het opnieuw via Maandoverzicht vergoedingen.');
            }
            if (! is_file($mo->bestandspad)) {
                return $terug->with('fout', 'Het Excel-bestand van dit maandoverzicht bestaat niet meer; maak het opnieuw aan.');
            }
            $t = $mail->maandoverzicht($mo, true, $door);
            audit('mail.verstuurd', 'maandoverzicht', ['maandoverzicht_id' => $mo->id, 'opnieuw' => true, 'status' => $t->status]);

            return $terug->with($t->status === 'verzonden' ? 'ok' : 'fout', 'Maandoverzicht '.Weekindeling::maandNaam($mo->maand).' '.$mo->jaar.': '.$t->status.($t->fout ? ' — '.$t->fout : ''));
        }

        if ($taak->soort === 'test') {
            $aan = $taak->ontvangers[0] ?? null;
            if (! $aan) {
                return $terug->with('fout', 'Geen ontvanger bekend.');
            }
            $t = $mail->testmail($aan);

            return $terug->with($t->status === 'verzonden' ? 'ok' : 'fout', "Testmail naar $aan: {$t->status}".($t->fout ? ' — '.$t->fout : ''));
        }

        return $terug->with('fout', 'Voor '.(MailTaak::SOORTEN[$taak->soort] ?? $taak->soort).' is handmatig opnieuw versturen niet mogelijk (die mails worden vanuit het ruilproces zelf verstuurd).');
    }

    /** Preview: wat zou er verstuurd worden? */
    public function preview(Request $request, MailDienst $mail)
    {
        $soort = (string) $request->query('soort', 'aankondiging');
        if (! in_array($soort, self::WEEK_SOORTEN, true)) {
            return redirect()->route('admin.mail')->with('fout', 'Onbekende mailsoort voor preview.');
        }
        $jaar = (int) $request->query('jaar');
        $wk = (int) $request->query('week');
        if ($request->filled('wk') && ! $jaar) {
            [$jaar, $wk] = array_map('intval', explode('-', $request->query('wk').'-0'));
        }
        $week = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $wk)->first();
        if (! $week) {
            return redirect()->route('admin.mail')->with('fout', "Week $wk van $jaar staat niet in het rooster.");
        }

        $data = ['soort' => $soort, 'week' => $week, 'testModus' => MailDienst::testModus(), 'testAdres' => MailDienst::adressen('test_adres'), 'soorten' => MailTaak::SOORTEN];
        if ($soort === 'multiline') {
            $t = MailDienst::teksten();
            $rijen = array_values(array_filter($mail->weekLijst($week), fn ($r) => $r['multiline']));
            $vars = ['week' => $week->weeknummer, 'van' => $week->van->format('d-m-Y'), 'tm' => $week->tm->format('d-m-Y'), 'tabel' => ''];
            $intro = strtr((string) setting('mail_multiline_tekst', $t['mail_multiline_tekst'][1]), $this->accolades($vars));
            $data += [
                'onderwerp' => strtr((string) setting('mail_multiline_onderwerp', $t['mail_multiline_onderwerp'][1]), $this->accolades($vars)),
                'aan' => MailDienst::adressen('multiline_adres', 'meldingenttr@multiline-antwoordservice.nl'),
                'cc' => MailDienst::adressen('multiline_cc'),
                'html' => view('mail.multiline', ['rijen' => $rijen, 'week' => $week, 'intro' => $intro])->render(),
                'bestaand' => MailTaak::where('soort', 'multiline')->where('referentie', $week->jaar.'-'.$week->weeknummer)->first(),
            ];
        } else {
            $ontvangers = $mail->voorbeeld($soort, $week);
            $refs = array_column($ontvangers, 'referentie');
            $bestaand = MailTaak::where('soort', $soort)->whereIn('referentie', $refs)->get()->keyBy('referentie');
            $data += ['ontvangers' => $ontvangers, 'bestaand' => $bestaand];
        }

        return view('admin.mail.preview', $data);
    }

    /** Testmail naar een opgegeven adres. */
    public function test(Request $request, MailDienst $mail)
    {
        $data = $request->validate(['adres' => 'required|email'], ['adres.email' => 'Vul een geldig e-mailadres in.']);
        $t = $mail->testmail($data['adres']);
        audit('mail.verstuurd', 'test', ['aan' => $data['adres'], 'status' => $t->status]);
        $msg = 'Testmail naar '.$data['adres'].': '.$t->status.($t->test_modus ? ' (testmodus: afgeleverd op '.implode(', ', $t->ontvangers ?? []).' → '.implode(', ', MailDienst::adressen('test_adres')).')' : '').($t->fout ? ' — '.$t->fout : '');

        return redirect()->route('admin.mail')->with($t->status === 'verzonden' ? 'ok' : 'fout', $msg);
    }

    /** Planner nu draaien (zelfde als de scheduler elke minuut doet). */
    public function planner(Planner $planner)
    {
        try {
            $log = $planner->draai();
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('admin.mail')->with('fout', 'Planner mislukt: '.$e->getMessage());
        }
        Setting::set('planner_laatst', now()->toDateTimeString());
        audit('planner.gedraaid', 'handmatig', ['log' => $log]);

        return redirect()->route('admin.mail')->with('ok', 'Planner gedraaid.')->with('planner_log', $log);
    }

    /** Verzendlog met filters. */
    public function log(Request $request)
    {
        $q = MailTaak::query()->orderByDesc('id');
        if ($request->filled('soort')) {
            $q->where('soort', $request->soort);
        }
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        if ($request->filled('van')) {
            $q->where('created_at', '>=', $request->van.' 00:00:00');
        }
        if ($request->filled('tm')) {
            $q->where('created_at', '<=', $request->tm.' 23:59:59');
        }
        if ($request->filled('zoek')) {
            $z = '%'.$request->zoek.'%';
            $q->where(fn ($w) => $w->where('referentie', 'like', $z)->orWhere('onderwerp', 'like', $z)->orWhere('ontvangers', 'like', $z));
        }

        return view('admin.mail.log', [
            'taken' => $q->paginate(50)->withQueryString(),
            'soorten' => MailTaak::SOORTEN,
            'statussen' => ['gepland' => 'Gepland', 'verzonden' => 'Verzonden', 'mislukt' => 'Mislukt', 'overgeslagen' => 'Overgeslagen'],
            'filter' => $request->only('soort', 'status', 'van', 'tm', 'zoek'),
            'herhaalbaar' => array_merge(self::WEEK_SOORTEN, ['maandoverzicht', 'test']),
        ]);
    }

    // ---------- hulpmethodes ----------

    /** @return MailTaak[] */
    private function voerUit(MailDienst $mail, string $soort, RoosterWeek $week, bool $opnieuw, ?string $door): array
    {
        return match ($soort) {
            'aankondiging' => $mail->aankondigingen($week, $opnieuw, $door),
            'dienst_vandaag' => $mail->dienstVandaag($week, $opnieuw, $door),
            'multiline' => [$mail->multiline($week, $opnieuw, $door)],
        };
    }

    /** Tellingen + leesbare samenvatting van een lijst taken. */
    private function samenvatting(array $taken, bool $opnieuw = false): array
    {
        $tel = ['verzonden' => 0, 'al_verzonden' => 0, 'overgeslagen' => 0, 'mislukt' => 0, 'gepland' => 0];
        $regels = [];
        foreach ($taken as $t) {
            // Zonder "opnieuw" geeft MailDienst een al verzonden taak ongewijzigd terug (idempotent): dan is er nu niets verstuurd
            $status = (! $opnieuw && $t->status === 'verzonden' && ! $t->wasRecentlyCreated && ! $t->wasChanged()) ? 'al_verzonden' : $t->status;
            $tel[$status] = ($tel[$status] ?? 0) + 1;
            $regels[] = ['status' => $status, 'ontvangers' => implode(', ', $t->ontvangers ?? []) ?: '(geen adres)', 'onderwerp' => $t->onderwerp, 'referentie' => $t->referentie, 'test' => $t->test_modus,
                'fout' => $status === 'al_verzonden' ? 'Al verzonden op '.$t->verzonden_op?->format('d-m-Y H:i').' — niet nog eens verstuurd (vink "opnieuw" aan om te herhalen).' : $t->fout];
        }
        $tekst = $tel['verzonden'].' verzonden, '.$tel['al_verzonden'].' al eerder verzonden (overgeslagen), '.$tel['overgeslagen'].' overgeslagen (geen adres), '.$tel['mislukt'].' mislukt';
        if (! $taken) {
            $tekst = 'niets te versturen (geen toewijzingen in deze week)';
        }

        return ['tellingen' => $tel, 'tekst' => $tekst, 'regels' => $regels, 'mislukt' => $tel['mislukt']];
    }

    private function accolades(array $vars): array
    {
        $r = [];
        foreach ($vars as $k => $v) {
            $r['{'.$k.'}'] = (string) $v;
        }

        return $r;
    }

    /** Ingestelde momenten, leesbaar. */
    private function momenten(): array
    {
        return [
            ['Aankondiging (week ervoor)', Weekindeling::dagNaam((int) setting('aankondiging_dag', 1)).' '.setting('aankondiging_tijd', '09:00'), 'over de week erna'],
            ['Herinnering "dienst vandaag"', 'maandag '.setting('vandaag_tijd', '07:00'), 'op de maandag van de dienstweek'],
            ['Weeklijst telefooncentrale (Multiline)', Weekindeling::dagNaam((int) setting('multiline_dag', 1)).' '.setting('multiline_tijd', '07:00'), 'over de lopende week'],
            ['Maandoverzicht vergoedingen', 'dag '.setting('maand_dag', 1).' van de maand, '.setting('maand_tijd', '08:00'), 'over de vorige maand'],
        ];
    }
}
