<?php

namespace App\Services;

use App\Models\Medewerker;
use App\Models\MedewerkerAlias;

/**
 * Namen uit het Excel-rooster herkennen als medewerkers.
 *  - "Jeffe Knubben", "Jefke Knubben", "Sjefke Knubben" -> dezelfde persoon (via alias of achternaam+voorletter)
 *  - "Wouter& Maurice", "Patrick/ David", "Raymond Servani/Matsu M" -> twee personen (gedeelde week)
 * Onzekere matches worden NIET automatisch gekoppeld; die komen in het koppelscherm.
 */
class NaamMatch
{
    /** Genormaliseerde vorm: kleine letters, geen accenten, één spatie. */
    public static function normaliseer(string $s): string
    {
        $s = trim(mb_strtolower($s));
        $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ó' => 'o', 'ö' => 'o', 'ô' => 'o', 'ú' => 'u', 'ü' => 'u', 'ï' => 'i', 'í' => 'i', 'ç' => 'c', 'ñ' => 'n']);
        $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Een celtekst splitsen in losse namen: "Wouter& Maurice" -> ["Wouter", "Maurice"]. */
    public static function splits(string $cel): array
    {
        $cel = trim($cel);
        if ($cel === '') {
            return [];
        }
        $delen = preg_split('/\s*(?:\/|&|\+|\bea\b|\ben\b)\s*/u', $cel);
        $delen = array_values(array_filter(array_map('trim', $delen), fn ($d) => $d !== ''));

        return $delen ?: [$cel];
    }

    /**
     * Beste medewerker voor een naam. Geeft ['medewerker' => ?Medewerker, 'zekerheid' => 'alias'|'exact'|'achternaam'|'voornaam'|'geen'].
     */
    public static function zoek(string $naam, ?array $medewerkers = null): array
    {
        $norm = self::normaliseer($naam);
        if ($norm === '') {
            return ['medewerker' => null, 'zekerheid' => 'geen'];
        }
        $alias = MedewerkerAlias::with('medewerker')->where('alias', $norm)->first();
        if ($alias && $alias->medewerker) {
            return ['medewerker' => $alias->medewerker, 'zekerheid' => 'alias'];
        }
        $lijst = $medewerkers ?? Medewerker::all()->all();
        // 1. exact op genormaliseerde volledige naam
        foreach ($lijst as $m) {
            if (self::normaliseer($m->naam) === $norm) {
                return ['medewerker' => $m, 'zekerheid' => 'exact'];
            }
        }
        $woorden = explode(' ', $norm);
        $voor = $woorden[0];
        $achter = count($woorden) > 1 ? implode(' ', array_slice($woorden, 1)) : '';
        // 2. zelfde achternaam (laatste woord(en)) + zelfde voorletter, of kleine spelfout in de voornaam
        if ($achter !== '') {
            $kandidaten = [];
            foreach ($lijst as $m) {
                $mn = self::normaliseer($m->naam);
                $mw = explode(' ', $mn);
                $mvoor = $mw[0];
                $machter = count($mw) > 1 ? implode(' ', array_slice($mw, 1)) : '';
                $achternaamOk = $machter !== '' && ($machter === $achter || str_ends_with($mn, ' '.$achter) || str_ends_with($norm, ' '.$machter));
                if ($achternaamOk && ($mvoor[0] === $voor[0] || levenshtein($mvoor, $voor) <= 2)) {
                    $kandidaten[] = $m;
                }
            }
            if (count($kandidaten) === 1) {
                return ['medewerker' => $kandidaten[0], 'zekerheid' => 'achternaam'];
            }
        }
        // 3. alleen een voornaam ("Wouter", "Maurice", "Matsu M"): uniek in de lijst?
        $kandidaten = [];
        foreach ($lijst as $m) {
            $mw = explode(' ', self::normaliseer($m->naam));
            if ($mw[0] === $voor && ($achter === '' || str_starts_with(implode(' ', array_slice($mw, 1)), rtrim($achter, '.')))) {
                $kandidaten[] = $m;
            }
        }
        if (count($kandidaten) === 1) {
            return ['medewerker' => $kandidaten[0], 'zekerheid' => 'voornaam'];
        }

        return ['medewerker' => null, 'zekerheid' => 'geen'];
    }

    /** Alias vastleggen zodat dezelfde schrijfwijze voortaan direct herkend wordt. */
    public static function leerAlias(string $origineel, Medewerker $m): void
    {
        $norm = self::normaliseer($origineel);
        if ($norm === '' || $norm === self::normaliseer($m->naam)) {
            return;
        }
        MedewerkerAlias::updateOrCreate(['alias' => $norm], ['alias_origineel' => trim($origineel), 'medewerker_id' => $m->id]);
    }
}
