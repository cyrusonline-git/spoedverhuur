<?php

namespace App\Services;

use App\Mail\SpoedMail;
use App\Models\DienstSoort;
use App\Models\MailTaak;
use App\Models\Medewerker;
use App\Models\RoosterWeek;
use App\Models\Ruiling;
use App\Models\Toewijzing;
use Illuminate\Support\Facades\Mail;

/**
 * Alle uitgaande mail loopt hier doorheen:
 *  - elke verzending wordt vastgelegd in mail_taken (uniek per soort+referentie, dus nooit dubbel);
 *  - in testmodus gaan alle mails naar het testadres met "[TEST → echte ontvanger]" in het onderwerp;
 *  - onderwerpen en teksten komen uit de instellingen (placeholders in accolades).
 */
class MailDienst
{
    // ---------- Instellingen ----------

    public static function testModus(): bool
    {
        return (bool) (int) setting('test_modus', 1);
    }

    public static function adressen(string $key, string $standaard = ''): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[,;\s]+/', (string) setting($key, $standaard)))));
    }

    /** Standaardteksten (sleutel => [label, standaardwaarde]); de admin kan ze aanpassen. */
    public static function teksten(): array
    {
        return [
            'mail_aankondiging_onderwerp' => ['Onderwerp aankondiging', 'Spoedverhuur: je hebt dienst in week {week} ({van} t/m {tm})'],
            'mail_aankondiging_tekst' => ['Tekst aankondiging', "Beste {voornaam},\n\nVolgende week heb je dienst: {dienst}, van {van} t/m {tm} (week {week}).\n\nDe andere diensten die week:\n{overzicht}\n\nIn de bijlage staat een agenda-item. Ruilen? Dat regel je in de Spoedverhuur-app: {url}"],
            'mail_vandaag_onderwerp' => ['Onderwerp herinnering op de dag', 'Vandaag start je dienst: {dienst} (week {week})'],
            'mail_vandaag_tekst' => ['Tekst herinnering op de dag', "Beste {voornaam},\n\nVandaag ({van}) begint je dienst {dienst}, tot en met {tm}.\n\nTweede lijn: {tweede_lijn}\n\nSucces en bedankt!"],
            'mail_multiline_onderwerp' => ['Onderwerp weeklijst telefooncentrale', 'Boels Industrial — dienstdoende medewerkers week {week} ({van} t/m {tm})'],
            'mail_multiline_tekst' => ['Tekst weeklijst telefooncentrale', "Geachte heer/mevrouw,\n\nHieronder de dienstdoende medewerkers van Boels Industrial voor week {week} ({van} t/m {tm}). Meldingen graag doorzetten naar de persoon bij de betreffende dienst.\n\n{tabel}\n\nMet vriendelijke groet,\nBoels Industrial"],
            'mail_maand_onderwerp' => ['Onderwerp maandoverzicht', 'Uitroepvergoedingen {maandnaam} {jaar}'],
            'mail_maand_tekst' => ['Tekst maandoverzicht', "Beste collega's,\n\nBijgevoegd het overzicht van de uitroepvergoedingen (24-uurs weekdiensten) van Boels Industrial voor {maandnaam} {jaar}: {aantal} regels, totaal {totaal}.\n\nMet vriendelijke groet,\nBoels Industrial"],
            'mail_ruil_onderwerp' => ['Onderwerp ruilverzoek', 'Ruilverzoek van {aanvrager}: {omschrijving}'],
            'mail_ruil_tekst' => ['Tekst ruilverzoek', "Beste {voornaam},\n\n{aanvrager} vraagt je om een dienst te ruilen:\n{omschrijving}\n{opmerking}\nBevestig of wijs af via de knoppen hieronder (of in de app). Dit verzoek vervalt op {vervalt}."],
        ];
    }

    // ---------- Bouwstenen ----------

    private function vul(string $tekst, array $vars): string
    {
        $repl = [];
        foreach ($vars as $k => $v) {
            $repl['{'.$k.'}'] = (string) $v;
        }

        return strtr($tekst, $repl);
    }

    /**
     * Verzenden + vastleggen. Geeft de MailTaak terug (status verzonden/mislukt/overgeslagen).
     * $referentie maakt de mail idempotent: dezelfde soort+referentie wordt niet nog eens verstuurd (tenzij $opnieuw).
     */
    public function verstuur(string $soort, string $referentie, array $aan, string $onderwerp, string $tekstOfHtml, array $opties = [], bool $opnieuw = false): MailTaak
    {
        $aan = array_values(array_unique(array_filter(array_map('trim', $aan), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL))));
        $cc = array_values(array_filter(array_map('trim', $opties['cc'] ?? []), fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL)));
        $taak = MailTaak::firstOrNew(['soort' => $soort, 'referentie' => $referentie]);
        if ($taak->exists && $taak->status === 'verzonden' && ! $opnieuw) {
            return $taak;
        }
        $test = self::testModus();
        $taak->fill([
            'ontvangers' => $aan, 'cc' => $cc, 'onderwerp' => $onderwerp, 'test_modus' => $test,
            'gepland_op' => $taak->gepland_op ?? now(), 'bijlage_pad' => $opties['bijlage_pad'] ?? null,
            'aangemaakt_door' => $opties['door'] ?? (app()->runningInConsole() ? 'scheduler' : (core_gebruiker()['name'] ?? null)),
        ]);
        if (! $aan) {
            $taak->fill(['status' => 'overgeslagen', 'fout' => 'Geen (geldig) e-mailadres.'])->save();

            return $taak;
        }
        try {
            $echteAan = $aan;
            $echteCc = $cc;
            if ($test) {
                $testadres = self::adressen('test_adres');
                if (! $testadres) {
                    $taak->fill(['status' => 'overgeslagen', 'fout' => 'Testmodus staat aan maar er is geen testadres ingesteld.'])->save();

                    return $taak;
                }
                $onderwerp = '[TEST → '.implode(', ', $aan).($cc ? ' cc '.implode(', ', $cc) : '').'] '.$onderwerp;
                $echteAan = $testadres;
                $echteCc = [];
            }
            $mail = new SpoedMail($onderwerp, $tekstOfHtml, $opties);
            $m = Mail::to($echteAan);
            if ($echteCc) {
                $m->cc($echteCc);
            }
            $m->send($mail);
            $taak->fill(['status' => 'verzonden', 'verzonden_op' => now(), 'fout' => null])->save();
        } catch (\Throwable $e) {
            report($e);
            $taak->fill(['status' => 'mislukt', 'fout' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        return $taak;
    }

    private function url(string $pad = ''): string
    {
        return rtrim(config('app.url'), '/').$pad;
    }

    private function voornaam(?Medewerker $m): string
    {
        return $m ? explode(' ', trim($m->naam))[0] : 'collega';
    }

    /** Overzicht van alle diensten van een week als platte tekst (voor in de mails). */
    public function weekOverzichtTekst(RoosterWeek $week): string
    {
        $regels = [];
        foreach ($this->weekLijst($week) as $r) {
            $regels[] = '- '.$r['dienst'].': '.$r['naam'].($r['telefoon'] ? ' ('.$r['telefoon'].')' : '').($r['dagen'] ? ' ['.$r['dagen'].']' : '');
        }

        return implode("\n", $regels);
    }

    /** Lijst per dienstsoort voor een week (incl. vaste tweede lijn): [['dienst','naam','telefoon','email','dagen','multiline'=>bool]] */
    public function weekLijst(RoosterWeek $week): array
    {
        $uit = [];
        $soorten = DienstSoort::actief()->get();
        $tw = Toewijzing::with(['medewerker', 'soort'])->where('rooster_week_id', $week->id)->orderBy('dag_van')->get()->groupBy('dienst_soort_id');
        foreach ($soorten as $s) {
            if ($s->vast) {
                $uit[] = ['dienst' => $s->naam, 'naam' => $s->vaste_naam ?: '—', 'telefoon' => $s->vaste_telefoon, 'email' => $s->vaste_email, 'dagen' => '', 'multiline' => $s->naar_multiline, 'vast' => true];
                continue;
            }
            $lijst = $tw->get($s->id, collect());
            if ($lijst->isEmpty()) {
                $uit[] = ['dienst' => $s->naam, 'naam' => '— niet ingevuld —', 'telefoon' => null, 'email' => null, 'dagen' => '', 'multiline' => $s->naar_multiline, 'vast' => false];
                continue;
            }
            foreach ($lijst as $t) {
                $uit[] = [
                    'dienst' => $s->naam, 'naam' => $t->naam(), 'telefoon' => $t->medewerker?->telefoonEffectief(), 'email' => $t->medewerker?->emailEffectief(),
                    'dagen' => $t->heleWeek() ? '' : Weekindeling::dagNaam($t->dag_van).' t/m '.Weekindeling::dagNaam($t->dag_tm),
                    'multiline' => $s->naar_multiline, 'vast' => false,
                ];
            }
        }

        return $uit;
    }

    private function tweedeLijnTekst(): string
    {
        $vast = DienstSoort::actief()->where('vast', true)->get();

        return $vast->map(fn ($s) => $s->naam.': '.($s->vaste_naam ?: '—').($s->vaste_telefoon ? ' ('.$s->vaste_telefoon.')' : ''))->implode('; ') ?: '—';
    }

    // ---------- Mailsoorten ----------

    /** Aankondiging (week ervoor) aan alle dienstdoenden van een week. Geeft de verzonden taken terug. */
    public function aankondigingen(RoosterWeek $week, bool $opnieuw = false, ?string $door = null): array
    {
        return $this->perMedewerker($week, 'aankondiging', 'mail_aankondiging_onderwerp', 'mail_aankondiging_tekst', $opnieuw, $door, metIcs: true);
    }

    /** Herinnering op de maandag zelf. */
    public function dienstVandaag(RoosterWeek $week, bool $opnieuw = false, ?string $door = null): array
    {
        return $this->perMedewerker($week, 'dienst_vandaag', 'mail_vandaag_onderwerp', 'mail_vandaag_tekst', $opnieuw, $door);
    }

    private function perMedewerker(RoosterWeek $week, string $soort, string $onderwerpKey, string $tekstKey, bool $opnieuw, ?string $door, bool $metIcs = false): array
    {
        $taken = [];
        $t = self::teksten();
        $overzicht = $this->weekOverzichtTekst($week);
        $tw = Toewijzing::with(['medewerker', 'soort'])->where('rooster_week_id', $week->id)->get();
        $cc = self::adressen('mail_cc_medewerkers');
        foreach ($tw as $x) {
            $m = $x->medewerker;
            $vars = [
                'voornaam' => $this->voornaam($m), 'naam' => $x->naam(), 'dienst' => $x->soort?->naam,
                'week' => $week->weeknummer, 'van' => $week->datumVanDag($x->dag_van)->format('d-m-Y'), 'tm' => $week->datumVanDag($x->dag_tm)->format('d-m-Y'),
                'overzicht' => $overzicht, 'tweede_lijn' => $this->tweedeLijnTekst(), 'url' => $this->url('/mijn-diensten'),
            ];
            $opties = ['cc' => $cc];
            if ($metIcs) {
                $opties['ics'] = Ics::voorToewijzing($x);
                $opties['ics_naam'] = 'dienst-week-'.$week->weeknummer.'.ics';
            }
            $taken[] = $this->verstuur($soort, $week->jaar.'-'.$week->weeknummer.':'.($m?->id ?? 'x'.$x->id),
                [$m?->emailEffectief()], $this->vul(setting($onderwerpKey, $t[$onderwerpKey][1]), $vars), $this->vul(setting($tekstKey, $t[$tekstKey][1]), $vars), $opties + ['door' => $door], $opnieuw);
        }
        // Vaste tweede lijn: optioneel dezelfde meldingen
        foreach (DienstSoort::actief()->where('vast', true)->where('vaste_meldingen', true)->get() as $s) {
            if (! $s->vaste_email) {
                continue;
            }
            $vars = ['voornaam' => explode(' ', (string) $s->vaste_naam)[0] ?: 'collega', 'naam' => $s->vaste_naam, 'dienst' => $s->naam, 'week' => $week->weeknummer,
                'van' => $week->van->format('d-m-Y'), 'tm' => $week->tm->format('d-m-Y'), 'overzicht' => $overzicht, 'tweede_lijn' => $this->tweedeLijnTekst(), 'url' => $this->url('/rooster')];
            $taken[] = $this->verstuur($soort, $week->jaar.'-'.$week->weeknummer.':vast'.$s->id, [$s->vaste_email],
                $this->vul(setting($onderwerpKey, $t[$onderwerpKey][1]), $vars), $this->vul(setting($tekstKey, $t[$tekstKey][1]), $vars), ['door' => $door], $opnieuw);
        }

        return $taken;
    }

    /** Weeklijst naar de telefooncentrale (Multiline). */
    public function multiline(RoosterWeek $week, bool $opnieuw = false, ?string $door = null): MailTaak
    {
        $t = self::teksten();
        $rijen = array_values(array_filter($this->weekLijst($week), fn ($r) => $r['multiline']));
        $tabel = "Dienst | Naam | Mobiel\n".implode("\n", array_map(fn ($r) => $r['dienst'].' | '.$r['naam'].($r['dagen'] ? ' ('.$r['dagen'].')' : '').' | '.($r['telefoon'] ?: 'onbekend'), $rijen));
        $vars = ['week' => $week->weeknummer, 'van' => $week->van->format('d-m-Y'), 'tm' => $week->tm->format('d-m-Y'), 'tabel' => $tabel];
        $html = view('mail.multiline', ['rijen' => $rijen, 'week' => $week, 'intro' => $this->vul(setting('mail_multiline_tekst', $t['mail_multiline_tekst'][1]), $vars + ['tabel' => ''])])->render();

        return $this->verstuur('multiline', $week->jaar.'-'.$week->weeknummer, self::adressen('multiline_adres', 'meldingenttr@multiline-antwoordservice.nl'),
            $this->vul(setting('mail_multiline_onderwerp', $t['mail_multiline_onderwerp'][1]), $vars), $html, ['html' => true, 'cc' => self::adressen('multiline_cc'), 'door' => $door], $opnieuw);
    }

    /** Maandoverzicht met Excel-bijlage naar HR/time/payroll. */
    public function maandoverzicht(\App\Models\Maandoverzicht $mo, bool $opnieuw = false, ?string $door = null): MailTaak
    {
        $t = self::teksten();
        $vars = ['maandnaam' => Weekindeling::maandNaam($mo->maand), 'jaar' => $mo->jaar, 'aantal' => $mo->aantal_regels, 'totaal' => euro($mo->totaal_bedrag)];
        $taak = $this->verstuur('maandoverzicht', $mo->jaar.'-'.$mo->maand.':'.$mo->id, self::adressen('maand_adressen', 'hr@boels.nl, time@boels.com, payroll@boels.nl'),
            $this->vul(setting('mail_maand_onderwerp', $t['mail_maand_onderwerp'][1]), $vars), $this->vul(setting('mail_maand_tekst', $t['mail_maand_tekst'][1]), $vars),
            ['bijlage_pad' => $mo->bestandspad, 'bijlage_naam' => $mo->bestandsnaam, 'cc' => self::adressen('maand_cc'), 'door' => $door], $opnieuw);
        if ($taak->status === 'verzonden' && ! $taak->test_modus) {
            $mo->update(['verzonden_op' => now(), 'vergrendeld' => true]);
        }

        return $taak;
    }

    public function ruilVerzoek(Ruiling $r, bool $herinnering = false): MailTaak
    {
        $t = self::teksten();
        $ontvanger = $r->moetBevestigen();
        $aanvrager = $r->aangevraagdDoor;
        $vars = ['voornaam' => $this->voornaam($ontvanger), 'aanvrager' => $aanvrager?->naam, 'omschrijving' => app(Ruilen::class)->omschrijving($r),
            'opmerking' => $r->opmerking ? "\nOpmerking: ".$r->opmerking."\n" : '', 'vervalt' => $r->token_verloopt_op?->format('d-m-Y H:i')];
        $html = view('mail.ruil-verzoek', ['r' => $r, 'tekst' => $this->vul(setting('mail_ruil_tekst', $t['mail_ruil_tekst'][1]), $vars), 'herinnering' => $herinnering,
            'bevestigUrl' => $this->url('/ruil/'.$r->token.'/bevestig'), 'afwijsUrl' => $this->url('/ruil/'.$r->token.'/afwijs'), 'appUrl' => $this->url('/ruilen')])->render();

        return $this->verstuur($herinnering ? 'ruil_herinnering' : 'ruil_verzoek', (string) $r->id, [$ontvanger?->emailEffectief()],
            ($herinnering ? 'Herinnering: ' : '').$this->vul(setting('mail_ruil_onderwerp', $t['mail_ruil_onderwerp'][1]), $vars), $html, ['html' => true]);
    }

    public function ruilBevestigd(Ruiling $r, bool $teruggedraaid = false): MailTaak
    {
        $oms = app(Ruilen::class)->omschrijving($r);
        $onderwerp = ($teruggedraaid ? 'Ruiling teruggedraaid: ' : 'Ruiling bevestigd: ').$oms;
        $tekst = $teruggedraaid
            ? "De volgende ruiling is door de beheerder teruggedraaid:\n$oms\n\nReden: ".($r->opmerking ?: '—')."\n\nHet rooster staat weer zoals vóór de ruiling."
            : "De ruiling is door beide partijen bevestigd en verwerkt in het rooster:\n$oms\n\nDe nieuwe dienstdoende ontvangt voortaan de aankondigingen. Bekijk je diensten: ".$this->url('/mijn-diensten');

        return $this->verstuur($teruggedraaid ? 'ruil_afgewezen' : 'ruil_bevestigd', $r->id.($teruggedraaid ? ':terug' : ''), [$r->van?->emailEffectief(), $r->naar?->emailEffectief()], $onderwerp, $tekst, ['cc' => self::adressen('mail_cc_ruilingen')]);
    }

    public function ruilAfgewezen(Ruiling $r, bool $verlopen = false): MailTaak
    {
        $oms = app(Ruilen::class)->omschrijving($r);
        $onderwerp = ($verlopen ? 'Ruilverzoek verlopen: ' : 'Ruilverzoek afgewezen: ').$oms;
        $tekst = $verlopen ? "Het ruilverzoek is niet op tijd bevestigd en is vervallen:\n$oms" : "Het ruilverzoek is afgewezen door ".($r->afgehandeld_door ?: 'de collega').":\n$oms".($r->opmerking ? "\n\n".$r->opmerking : '');

        return $this->verstuur('ruil_afgewezen', $r->id.($verlopen ? ':verlopen' : ''), [$r->aangevraagdDoor?->emailEffectief()], $onderwerp, $tekst);
    }

    public function ruilIngetrokken(Ruiling $r): MailTaak
    {
        $oms = app(Ruilen::class)->omschrijving($r);

        return $this->verstuur('ruil_ingetrokken', (string) $r->id, [$r->moetBevestigen()?->emailEffectief()], 'Ruilverzoek ingetrokken: '.$oms, "Het ruilverzoek is ingetrokken door de aanvrager:\n$oms");
    }

    /** Signaal aan de beheerder(s), bv. ontbrekende gegevens. */
    public function beheerSignaal(string $referentie, string $onderwerp, string $tekst): MailTaak
    {
        return $this->verstuur('beheer_signaal', $referentie.':'.now()->format('Ymd'), self::adressen('beheer_adres'), $onderwerp, $tekst);
    }

    public function testmail(string $aan): MailTaak
    {
        return $this->verstuur('test', (string) now()->timestamp, [$aan], 'Testmail Spoedverhuur', "Dit is een testmail van de Spoedverhuur-app.\nTestmodus: ".(self::testModus() ? 'aan' : 'uit')."\nTijd: ".now('Europe/Amsterdam')->format('d-m-Y H:i'), ['door' => core_gebruiker()['name'] ?? null], true);
    }

    /**
     * Voorbeeld per ontvanger zónder te versturen (voor de preview in het mailcentrum).
     * $soort: aankondiging | dienst_vandaag. Geeft [['naam','email','referentie','onderwerp','tekst','ics'=>bool,'cc'=>[]]].
     */
    public function voorbeeld(string $soort, RoosterWeek $week): array
    {
        [$onderwerpKey, $tekstKey] = $soort === 'dienst_vandaag'
            ? ['mail_vandaag_onderwerp', 'mail_vandaag_tekst']
            : ['mail_aankondiging_onderwerp', 'mail_aankondiging_tekst'];
        $t = self::teksten();
        $overzicht = $this->weekOverzichtTekst($week);
        $cc = self::adressen('mail_cc_medewerkers');
        $uit = [];
        $tw = Toewijzing::with(['medewerker', 'soort'])->where('rooster_week_id', $week->id)->get();
        foreach ($tw as $x) {
            $m = $x->medewerker;
            $vars = [
                'voornaam' => $this->voornaam($m), 'naam' => $x->naam(), 'dienst' => $x->soort?->naam,
                'week' => $week->weeknummer, 'van' => $week->datumVanDag($x->dag_van)->format('d-m-Y'), 'tm' => $week->datumVanDag($x->dag_tm)->format('d-m-Y'),
                'overzicht' => $overzicht, 'tweede_lijn' => $this->tweedeLijnTekst(), 'url' => $this->url('/mijn-diensten'),
            ];
            $uit[] = [
                'naam' => $x->naam(), 'email' => $m?->emailEffectief(), 'dienst' => $x->soort?->naam,
                'referentie' => $week->jaar.'-'.$week->weeknummer.':'.($m?->id ?? 'x'.$x->id),
                'onderwerp' => $this->vul(setting($onderwerpKey, $t[$onderwerpKey][1]), $vars),
                'tekst' => $this->vul(setting($tekstKey, $t[$tekstKey][1]), $vars),
                'ics' => $soort === 'aankondiging', 'cc' => $cc,
            ];
        }
        foreach (DienstSoort::actief()->where('vast', true)->where('vaste_meldingen', true)->get() as $s) {
            if (! $s->vaste_email) {
                continue;
            }
            $vars = ['voornaam' => explode(' ', (string) $s->vaste_naam)[0] ?: 'collega', 'naam' => $s->vaste_naam, 'dienst' => $s->naam, 'week' => $week->weeknummer,
                'van' => $week->van->format('d-m-Y'), 'tm' => $week->tm->format('d-m-Y'), 'overzicht' => $overzicht, 'tweede_lijn' => $this->tweedeLijnTekst(), 'url' => $this->url('/rooster')];
            $uit[] = [
                'naam' => $s->vaste_naam.' (vast)', 'email' => $s->vaste_email, 'dienst' => $s->naam,
                'referentie' => $week->jaar.'-'.$week->weeknummer.':vast'.$s->id,
                'onderwerp' => $this->vul(setting($onderwerpKey, $t[$onderwerpKey][1]), $vars),
                'tekst' => $this->vul(setting($tekstKey, $t[$tekstKey][1]), $vars),
                'ics' => false, 'cc' => [],
            ];
        }

        return $uit;
    }
}
