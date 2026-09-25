<?php

namespace App\Services;

use App\Models\Toewijzing;

/** Agenda-item (.ics) voor een dienstweek, als bijlage bij de aankondigingsmail. */
class Ics
{
    public static function voorToewijzing(Toewijzing $t): string
    {
        $week = $t->week;
        $start = $week->datumVanDag($t->dag_van);
        $eind = $week->datumVanDag($t->dag_tm)->addDay(); // DTEND exclusief
        $titel = 'Dienst: '.($t->soort?->naam ?? 'Spoedverhuur');
        $uid = 'spoedverhuur-'.$t->id.'-'.$t->updated_at?->timestamp.'@'.parse_url(config('app.url'), PHP_URL_HOST);
        $beschrijving = $titel.' — week '.$week->weeknummer.' ('.$start->format('d-m-Y').' t/m '.$week->datumVanDag($t->dag_tm)->format('d-m-Y').'). Rooster: '.config('app.url');
        $regels = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Boels Industrial//Spoedverhuur//NL', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.gmdate('Ymd\THis\Z'),
            'DTSTART;VALUE=DATE:'.$start->format('Ymd'),
            'DTEND;VALUE=DATE:'.$eind->format('Ymd'),
            'SUMMARY:'.self::esc($titel),
            'DESCRIPTION:'.self::esc($beschrijving),
            'STATUS:CONFIRMED', 'TRANSP:TRANSPARENT',
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", $regels)."\r\n";
    }

    private static function esc(string $s): string
    {
        return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $s);
    }
}
