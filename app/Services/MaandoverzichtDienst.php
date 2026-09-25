<?php

namespace App\Services;

use App\Models\Maandoverzicht;

/**
 * Maandoverzicht (Excel) in exact de opmaak van "NL 24 uurs week vergoedingen":
 *   A2 Naam Afdeling | C2 <afdeling>
 *   A4 Week | C4 eerste week | D4 tot en met | E4 laatste week | F4 <Maand jaar>
 *   rij 6: Naam Medewerker | Personeelsnummer | Weeknummer | Dagen | Betaling | Gewerkt | Data | Te vergoeden uren uitbetalen
 *   rij 7..: regels
 */
class MaandoverzichtDienst
{
    /** Bestand aanmaken (snapshot van de regels van dit moment). */
    public function maak(int $jaar, int $maand, ?string $door = null, ?Maandoverzicht $correctieVan = null): Maandoverzicht
    {
        $regels = Vergoeding::regels($jaar, $maand);
        [$w1, $w2] = Vergoeding::weekBereik($jaar, $maand);
        $maandnaam = ucfirst(Weekindeling::maandNaam($maand));
        $afdeling = (string) setting('afdeling_naam', 'Boels Industrial');
        $x = new XlsxSchrijver($maandnaam.' '.$jaar);
        $x->breedte('A', 28)->breedte('B', 18)->breedte('C', 13)->breedte('D', 8)->breedte('E', 11)->breedte('F', 10)->breedte('G', 12)->breedte('H', 30)->bedragKolom('E');
        $x->cel('A2', 'Naam Afdeling')->cel('C2', $afdeling);
        $x->cel('A4', 'Week ')->cel('C4', $w1)->cel('D4', 'tot en met ')->cel('E4', $w2)->cel('F4', $maandnaam.' '.$jaar);
        $x->cel('C5', 1)->cel('D5', 'tot en met ')->cel('E5', (int) \Carbon\Carbon::create($jaar, $maand, 1)->endOfMonth()->format('j'));
        $x->rij(6, ['Naam Medewerker ', 'Personeelsnummer', 'Weeknummer', 'Dagen', 'Betaling', 'Gewerkt', 'Data', 'Te vergoeden uren uitbetalen'], true);
        $rij = 7;
        foreach ($regels as $r) {
            $x->rij($rij++, [$r['naam'], $r['personeelsnummer'] ?: '', $r['week'], $r['dagen'], (float) $r['bedrag']]);
        }
        $naam = str_replace(['{jaar}', '{maand}', '{maandnaam}'], [$jaar, str_pad((string) $maand, 2, '0', STR_PAD_LEFT), $maandnaam], (string) setting('maand_bestandsnaam', '24 uurs week vergoedingen {jaar} - {maandnaam}.xlsx'));
        if ($correctieVan) {
            $naam = preg_replace('/\.xlsx$/i', ' - correctie.xlsx', $naam);
        }
        $map = storage_path('app/maandoverzichten');
        if (! is_dir($map)) {
            mkdir($map, 0755, true);
        }
        $pad = $map.'/'.$jaar.'-'.str_pad((string) $maand, 2, '0', STR_PAD_LEFT).'-'.now()->format('YmdHis').'.xlsx';
        $x->schrijf($pad);
        $mo = Maandoverzicht::create([
            'jaar' => $jaar, 'maand' => $maand, 'bestandsnaam' => $naam, 'bestandspad' => $pad,
            'aantal_regels' => count($regels), 'totaal_bedrag' => Vergoeding::totaal($regels), 'regels' => $regels,
            'aangemaakt_door' => $door, 'correctie_van_id' => $correctieVan?->id,
        ]);
        audit('maandoverzicht.aangemaakt', "$maandnaam $jaar", ['regels' => count($regels), 'totaal' => $mo->totaal_bedrag]);

        return $mo;
    }

    /** Regels waarvan iets ontbreekt (personeelsnummer / niet gekoppeld). */
    public static function ontbrekend(array $regels): array
    {
        return array_values(array_filter($regels, fn ($r) => ! empty($r['ontbreekt'])));
    }
}
