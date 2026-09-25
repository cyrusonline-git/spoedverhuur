<?php

namespace App\Services;

use App\Models\DienstSoort;
use App\Models\RoosterWeek;
use App\Models\Toewijzing;

/**
 * Vergoedingen per maand: één regel per (medewerker, week, dienstsoort) op basis van
 * het effectieve rooster. Bedrag = weekbedrag / 7 × dagen, afgerond op 2 decimalen
 * (7 dagen = €100,00; 6 = 85,71; 4 = 57,14; 1 = 14,29 — zoals de bestaande lijsten).
 */
class Vergoeding
{
    public static function weekbedrag(): float
    {
        return (float) str_replace(',', '.', (string) setting('weekbedrag', '100'));
    }

    public static function bedrag(int $dagen, ?float $weekbedrag = null): float
    {
        $wb = $weekbedrag ?? self::weekbedrag();
        $dagen = max(0, min(7, $dagen));

        return round($wb / 7 * $dagen, 2);
    }

    /**
     * Regels voor een maand: [['naam','personeelsnummer','week','dagen','bedrag','medewerker_id','dienst_soort','ontbreekt'=>[]], ...]
     * Alleen betaalde dienstsoorten; de vaste tweede lijn nooit.
     */
    public static function regels(int $jaar, int $maand): array
    {
        $weken = Weekindeling::wekenInMaand($jaar, $maand);
        $betaald = DienstSoort::where('betaald', true)->where('vast', false)->pluck('id')->all();
        $regels = [];
        foreach ($weken as [$j, $w]) {
            $week = RoosterWeek::where('jaar', $j)->where('weeknummer', $w)->first();
            if (! $week) {
                continue;
            }
            $tw = Toewijzing::with(['medewerker', 'soort'])->where('rooster_week_id', $week->id)
                ->whereIn('dienst_soort_id', $betaald)->orderBy('dienst_soort_id')->get();
            foreach ($tw as $t) {
                $dagen = $t->dagen();
                $regels[] = [
                    'naam' => $t->naam(),
                    'personeelsnummer' => $t->medewerker?->personeelsnummerEffectief(),
                    'week' => $w,
                    'jaar' => $j,
                    'dagen' => $dagen,
                    'bedrag' => self::bedrag($dagen),
                    'medewerker_id' => $t->medewerker_id,
                    'dienst_soort' => $t->soort?->naam,
                    'oorsprong' => $t->oorsprong,
                    'ontbreekt' => $t->medewerker ? array_values(array_intersect($t->medewerker->ontbreekt(), ['personeelsnummer'])) : ['niet gekoppeld aan een medewerker'],
                ];
            }
        }
        usort($regels, fn ($a, $b) => [$a['jaar'], $a['week'], $a['naam']] <=> [$b['jaar'], $b['week'], $b['naam']]);

        return $regels;
    }

    public static function totaal(array $regels): float
    {
        return round(array_sum(array_column($regels, 'bedrag')), 2);
    }

    /** Eerste en laatste weeknummer van de maand (voor de kop van het Excel-bestand). */
    public static function weekBereik(int $jaar, int $maand): array
    {
        $weken = Weekindeling::wekenInMaand($jaar, $maand);
        if (! $weken) {
            return [0, 0];
        }

        return [$weken[0][1], $weken[count($weken) - 1][1]];
    }
}
