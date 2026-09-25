<?php

namespace App\Support;

use App\Services\MailDienst;

/**
 * Alle instellingen die de beheerder kan zetten, gegroepeerd.
 * sleutel => [label, standaardwaarde, uitleg, type]  (type: text | textarea | email_lijst | tijd | dag | getal | bool | keuze:a|b)
 */
class Instellingen
{
    public static function groepen(): array
    {
        $mail = [];
        foreach (MailDienst::teksten() as $key => [$label, $standaard]) {
            $mail[$key] = [$label, $standaard, 'Placeholders tussen accolades blijven staan, bv. {voornaam} {week} {van} {tm} {dienst} {overzicht} {tweede_lijn} {url} {tabel} {maandnaam} {jaar} {aantal} {totaal} {aanvrager} {omschrijving} {opmerking} {vervalt}.', str_contains($key, 'onderwerp') ? 'text' : 'textarea'];
        }

        return [
            'Algemeen' => [
                'app_titel' => ['Naam in de kop', 'Spoedverhuur', 'Wordt bovenin elke pagina getoond.', 'text'],
                'afdeling_naam' => ['Afdelingsnaam', 'Boels Industrial', 'Komt in het maandoverzicht (cel "Naam Afdeling").', 'text'],
                'weekbedrag' => ['Vergoeding per dienstweek (€)', '100', 'Bij een gedeelde week: bedrag ÷ 7 × dagen.', 'getal'],
                'maandregel' => ['Maandindeling van weken', 'donderdag', 'donderdag = week telt bij de maand waarin de donderdag valt (zoals de bestaande lijsten); maandag = maand van de maandag.', 'keuze:donderdag|maandag'],
                'maand_bestandsnaam' => ['Bestandsnaam maandoverzicht', '24 uurs week vergoedingen {jaar} - {maandnaam}.xlsx', 'Placeholders: {jaar} {maand} {maandnaam}.', 'text'],
                'ruil_verval_dagen' => ['Ruilverzoek vervalt na (dagen)', '3', 'Onbeantwoorde verzoeken vervallen automatisch; halverwege gaat één herinnering.', 'getal'],
            ],
            'Verzenden' => [
                'test_modus' => ['Testmodus', '1', 'AAN = alle mails gaan naar het testadres met [TEST → echte ontvanger] in het onderwerp. Zet UIT als alles klopt.', 'bool'],
                'test_adres' => ['Testadres', '', 'Ontvanger van alle mails in testmodus.', 'email_lijst'],
                'automatisch_versturen' => ['Automatisch versturen (planner)', '1', 'UIT = alleen handmatig via het mailcentrum. Vereist een cronjob op de server (zie mailcentrum).', 'bool'],
                'mail_van_naam' => ['Afzendernaam', 'Boels Industrial — Spoedverhuur', 'Naam bij het afzenderadres noreply@sorai.nl.', 'text'],
                'beheer_adres' => ['E-mail beheerder(s)', '', 'Ontvangt signalen: onbekende namen na import, ontbrekende gegevens, mislukte verzendingen.', 'email_lijst'],
            ],
            'Momenten' => [
                'aankondiging_dag' => ['Aankondiging: dag (week ervoor)', '1', '1 = maandag … 7 = zondag, in de week vóór de dienst.', 'dag'],
                'aankondiging_tijd' => ['Aankondiging: tijd', '09:00', '', 'tijd'],
                'vandaag_tijd' => ['Herinnering op de maandag: tijd', '07:00', 'Op de maandag waarop de dienst begint.', 'tijd'],
                'multiline_dag' => ['Weeklijst telefooncentrale: dag', '1', '1 = maandag van de dienstweek.', 'dag'],
                'multiline_tijd' => ['Weeklijst telefooncentrale: tijd', '07:00', '', 'tijd'],
                'maand_dag' => ['Maandoverzicht: dag van de maand', '1', 'Over de vorige maand.', 'getal'],
                'maand_tijd' => ['Maandoverzicht: tijd', '08:00', '', 'tijd'],
            ],
            'Ontvangers' => [
                'multiline_adres' => ['Telefooncentrale (Multiline)', 'meldingenttr@multiline-antwoordservice.nl', 'Eén of meer adressen, gescheiden door komma.', 'email_lijst'],
                'multiline_cc' => ['CC weeklijst', '', 'Bijvoorbeeld het depotadres.', 'email_lijst'],
                'maand_adressen' => ['Maandoverzicht naar', 'hr@boels.nl, time@boels.com, payroll@boels.nl', '', 'email_lijst'],
                'maand_cc' => ['CC maandoverzicht', '', '', 'email_lijst'],
                'mail_cc_medewerkers' => ['CC bij aankondiging/herinnering', '', 'Optioneel, bv. de teamleider.', 'email_lijst'],
                'mail_cc_ruilingen' => ['CC bij bevestigde ruilingen', '', 'Optioneel, bv. de planner.', 'email_lijst'],
            ],
            'Mailteksten' => $mail,
        ];
    }

    /** Platte lijst sleutel => standaardwaarde. */
    public static function standaard(): array
    {
        $uit = [];
        foreach (self::groepen() as $velden) {
            foreach ($velden as $k => $v) {
                $uit[$k] = $v[1];
            }
        }

        return $uit;
    }
}
