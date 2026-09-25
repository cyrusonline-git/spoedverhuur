<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * ISO-weken (ma t/m zo) en de maandregel: een week hoort bij de maand waarin
 * de donderdag valt (= de maand met de meeste dagen van die week). Dit sluit
 * aan op de bestaande vergoedingenlijsten: sept 2025 = wk 36-39, okt 2025 = wk 40-44.
 */
class Weekindeling
{
    /** [maandag, zondag] van ISO-week $week in ISO-jaar $jaar. */
    public static function datums(int $jaar, int $week): array
    {
        $ma = Carbon::now('Europe/Amsterdam')->setISODate($jaar, $week, 1)->startOfDay();

        return [$ma->copy(), $ma->copy()->addDays(6)];
    }

    /** ['jaar' => 2025, 'maand' => 10] voor een week. */
    public static function maandVanWeek(int $jaar, int $week): array
    {
        $regel = function_exists('setting') ? (string) setting('maandregel', 'donderdag') : 'donderdag';
        [$ma] = self::datums($jaar, $week);
        $peil = $regel === 'maandag' ? $ma : $ma->copy()->addDays(3);

        return ['jaar' => (int) $peil->format('Y'), 'maand' => (int) $peil->format('n')];
    }

    /** Alle [jaar, week]-paren die bij een kalendermaand horen, oplopend. */
    public static function wekenInMaand(int $jaar, int $maand): array
    {
        $uit = [];
        $d = Carbon::create($jaar, $maand, 1, 0, 0, 0, 'Europe/Amsterdam')->subDays(7);
        $eind = Carbon::create($jaar, $maand, 1, 0, 0, 0, 'Europe/Amsterdam')->addMonth()->addDays(7);
        $gezien = [];
        while ($d <= $eind) {
            $j = (int) $d->format('o');
            $w = (int) $d->format('W');
            $sleutel = "$j-$w";
            if (! isset($gezien[$sleutel])) {
                $gezien[$sleutel] = true;
                $m = self::maandVanWeek($j, $w);
                if ($m['jaar'] === $jaar && $m['maand'] === $maand) {
                    $uit[] = [$j, $w];
                }
            }
            $d->addDays(7);
        }

        return $uit;
    }

    /** ISO-jaar en -week van een datum. */
    public static function weekVanDatum(Carbon|string $datum): array
    {
        $d = $datum instanceof Carbon ? $datum : Carbon::parse($datum, 'Europe/Amsterdam');

        return [(int) $d->format('o'), (int) $d->format('W')];
    }

    public static function maandNaam(int $maand): string
    {
        return ['', 'januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'][$maand] ?? '';
    }

    public static function dagNaam(int $dag, bool $kort = false): string
    {
        $namen = ['', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag'];
        $n = $namen[$dag] ?? '';

        return $kort ? substr($n, 0, 2) : $n;
    }
}
