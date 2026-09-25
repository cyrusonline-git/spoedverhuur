<?php

namespace App\Http\Controllers;

use App\Models\Dienst;
use App\Models\Medewerker;
use App\Models\Ruiling;
use App\Services\Ruilen;
use App\Services\Weekindeling;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Ruilen voor de ingelogde medewerker: overzicht, nieuw verzoek, detail en
 * bevestigen/afwijzen/intrekken in de app. Alle logica zit in App\Services\Ruilen.
 */
class RuilController extends Controller
{
    private const MET = ['dienst.week', 'dienst.soort', 'tegenDienst.week', 'tegenDienst.soort', 'van', 'naar', 'aangevraagdDoor'];

    /** Overzicht met tabs: wacht op mij / door mij aangevraagd / afgehandeld (+ alle open voor manager/admin). */
    public function index()
    {
        $eigen = eigen_medewerker();
        $eigenId = $eigen?->id;
        $open = Ruiling::with(self::MET)->where('status', 'aangevraagd')->orderBy('created_at', 'desc')->get();

        $wachtOpMij = $open->filter(fn (Ruiling $r) => $eigenId && $r->moetBevestigen()?->id === $eigenId)->values();
        $doorMij = $open->filter(fn (Ruiling $r) => $eigenId && $r->aangevraagd_door_id === $eigenId)->values();
        $afgehandeld = $eigenId
            ? Ruiling::with(self::MET)->where('status', '!=', 'aangevraagd')
                ->where(fn ($q) => $q->where('van_medewerker_id', $eigenId)->orWhere('naar_medewerker_id', $eigenId)->orWhere('aangevraagd_door_id', $eigenId))
                ->orderBy('updated_at', 'desc')->limit(50)->get()
            : collect();

        return view('ruilen.index', [
            'eigen' => $eigen,
            'wachtOpMij' => $wachtOpMij,
            'doorMij' => $doorMij,
            'afgehandeld' => $afgehandeld,
            'alleOpen' => $this->isBeheer() ? $open : collect(),
            'beheer' => $this->isBeheer(),
            'tab' => request('tab', $wachtOpMij->isNotEmpty() ? 'wacht' : ($doorMij->isNotEmpty() ? 'mijn' : ($this->isBeheer() ? 'alle' : 'afgehandeld'))),
            'service' => app(Ruilen::class),
        ]);
    }

    /** Formulier nieuw ruilverzoek (?dienst=ID voorselecteert mijn dienst). */
    public function nieuw(Request $request)
    {
        $eigen = eigen_medewerker();
        if (! $eigen) {
            return redirect()->route('ruilen')->with('fout', 'Je CORE-account is niet gekoppeld aan een roosterpersoon; ruilen is daarom niet mogelijk. Vraag de beheerder om de koppeling.');
        }
        $mijnDiensten = self::toekomstigeDiensten($eigen->id);
        $collegas = Medewerker::actief()->where('id', '!=', $eigen->id)->get();
        $dagen = [];
        foreach ($mijnDiensten as $d) {
            $dagen[$d->id] = [$d->dagVan(), $d->dagTm()];
        }
        $dagNamen = [];
        for ($i = 1; $i <= 7; $i++) {
            $dagNamen[$i] = ucfirst(Weekindeling::dagNaam($i));
        }

        return view('ruilen.nieuw', [
            'eigen' => $eigen,
            'mijnDiensten' => $mijnDiensten,
            'collegas' => $collegas,
            'geselecteerd' => (int) $request->input('dienst', old('dienst_id', 0)),
            'dagenJson' => json_encode($dagen),
            'dagNamen' => $dagNamen,
            'types' => Ruiling::TYPES,
        ]);
    }

    /** POST: verzoek indienen via de service; validatiefouten netjes terug naar het formulier. */
    public function aanvragen(Request $request)
    {
        $eigen = eigen_medewerker();
        if (! $eigen) {
            return redirect()->route('ruilen')->with('fout', 'Je CORE-account is niet gekoppeld aan een roosterpersoon.');
        }
        $data = $request->validate([
            'richting' => 'required|in:geven,overnemen',
            'type' => 'required_if:richting,geven|nullable|in:ruil,overname,deel',
            'dienst_id' => 'required_if:richting,geven|nullable|integer',
            'naar_medewerker_id' => 'required_if:richting,geven|nullable|integer',
            'tegen_dienst_id' => 'nullable|integer',
            'collega_id' => 'required_if:richting,overnemen|nullable|integer',
            'collega_dienst_id' => 'required_if:richting,overnemen|nullable|integer',
            'dagen_van' => 'nullable|integer|min:1|max:7',
            'dagen_tm' => 'nullable|integer|min:1|max:7',
            'opmerking' => 'nullable|string|max:1000',
        ], [
            'type.required_if' => 'Kies het type ruiling.',
            'dienst_id.required_if' => 'Kies je eigen dienst.',
            'naar_medewerker_id.required_if' => 'Kies een collega.',
            'collega_id.required_if' => 'Kies een collega.',
            'collega_dienst_id.required_if' => 'Kies de dienst van de collega die je wilt overnemen.',
        ]);

        if ($data['richting'] === 'overnemen') {
            // Ik neem de dienst van een collega over: ik ben de 'naar'-partij, de service herkent dat
            $invoer = [
                'type' => 'overname',
                'dienst_id' => $data['collega_dienst_id'],
                'naar_medewerker_id' => $eigen->id,
                'opmerking' => $data['opmerking'] ?? null,
            ];
        } else {
            $invoer = [
                'type' => $data['type'],
                'dienst_id' => $data['dienst_id'],
                'naar_medewerker_id' => $data['naar_medewerker_id'],
                'tegen_dienst_id' => $data['type'] === 'ruil' ? ($data['tegen_dienst_id'] ?? null) : null,
                'dagen_van' => $data['type'] === 'deel' ? ($data['dagen_van'] ?? null) : null,
                'dagen_tm' => $data['type'] === 'deel' ? ($data['dagen_tm'] ?? null) : null,
                'opmerking' => $data['opmerking'] ?? null,
            ];
            if ($invoer['type'] === 'ruil' && ! $invoer['tegen_dienst_id']) {
                return back()->withInput()->withErrors(['tegen_dienst_id' => 'Kies de tegendienst van de collega.']);
            }
        }

        try {
            $r = app(Ruilen::class)->aanvragen($eigen, $invoer);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        $tegen = $r->moetBevestigen();

        return redirect()->route('ruilen')->with('ok', 'Ruilverzoek ingediend. '.($tegen?->naam ?? 'Je collega').' krijgt een mail met een bevestiglink; zodra die bevestigt, wordt het rooster aangepast. Het verzoek vervalt op '.$r->token_verloopt_op?->format('d-m-Y H:i').'.');
    }

    /** Detailpagina. */
    public function toon(Ruiling $ruiling)
    {
        $ruiling->load(self::MET);
        $eigen = eigen_medewerker();
        if (! $this->magZien($ruiling, $eigen)) {
            abort(403, 'Dit ruilverzoek is niet van jou.');
        }
        $eigenId = $eigen?->id;

        return view('ruilen.toon', [
            'r' => $ruiling,
            'eigen' => $eigen,
            'omschrijving' => app(Ruilen::class)->omschrijving($ruiling),
            'magBevestigen' => $ruiling->isOpen() && $eigenId && $ruiling->moetBevestigen()?->id === $eigenId,
            'magIntrekken' => $ruiling->isOpen() && (($eigenId && $ruiling->aangevraagd_door_id === $eigenId) || $this->isBeheer()),
            'magAfwijzenAlsBeheer' => $ruiling->isOpen() && $this->isBeheer() && ! ($eigenId && $ruiling->moetBevestigen()?->id === $eigenId),
            'tijdlijn' => $this->tijdlijn($ruiling),
        ]);
    }

    public function bevestig(Ruiling $ruiling)
    {
        $eigen = eigen_medewerker();
        if (! $eigen) {
            return redirect()->route('ruilen')->with('fout', 'Je bent niet gekoppeld aan een roosterpersoon.');
        }
        try {
            app(Ruilen::class)->bevestigen($ruiling, $eigen, $this->naam());
        } catch (ValidationException $e) {
            return $this->terug($ruiling, $e);
        }

        return redirect()->route('ruilen.toon', $ruiling)->with('ok', 'Bevestigd. Het rooster is aangepast en beide partijen krijgen een mail.');
    }

    public function afwijs(Request $request, Ruiling $ruiling)
    {
        $eigen = eigen_medewerker();
        $isTegenpartij = $eigen && $ruiling->moetBevestigen()?->id === $eigen->id;
        if (! $isTegenpartij && ! $this->isBeheer()) {
            abort(403, 'Alleen de collega die moet bevestigen (of een manager/beheerder) kan afwijzen.');
        }
        $reden = trim((string) $request->input('reden', ''));
        try {
            app(Ruilen::class)->afwijzen($ruiling, $this->naam(), $reden ?: null);
        } catch (ValidationException $e) {
            return $this->terug($ruiling, $e);
        }

        return redirect()->route('ruilen.toon', $ruiling)->with('ok', 'Afgewezen. De aanvrager krijgt een mail.');
    }

    public function intrek(Ruiling $ruiling)
    {
        $eigen = eigen_medewerker();
        $isAanvrager = $eigen && $ruiling->aangevraagd_door_id === $eigen->id;
        if (! $isAanvrager && ! $this->isBeheer()) {
            abort(403, 'Alleen de aanvrager (of een manager/beheerder) kan een verzoek intrekken.');
        }
        try {
            app(Ruilen::class)->intrekken($ruiling, $this->naam());
        } catch (ValidationException $e) {
            return $this->terug($ruiling, $e);
        }

        return redirect()->route('ruilen')->with('ok', 'Verzoek ingetrokken. De collega krijgt daarvan een mail.');
    }

    /** JSON: toekomstige diensten van een collega (voor de selects in het formulier). */
    public function dienstenVan(Medewerker $medewerker)
    {
        $uit = [];
        foreach (self::toekomstigeDiensten($medewerker->id) as $d) {
            $uit[] = ['id' => $d->id, 'label' => self::dienstLabel($d), 'dag_van' => $d->dagVan(), 'dag_tm' => $d->dagTm()];
        }

        return response()->json(['medewerker' => $medewerker->naam, 'diensten' => $uit]);
    }

    /** Toekomstige (nog niet afgelopen) diensten van een medewerker, oplopend op weekdatum. */
    public static function toekomstigeDiensten(int $medewerkerId)
    {
        return Dienst::with(['week', 'soort'])->where('medewerker_id', $medewerkerId)
            ->whereHas('week', fn ($q) => $q->toekomst())
            ->get()->sortBy(fn (Dienst $d) => $d->week->van->format('Y-m-d').'-'.$d->dagVan().'-'.$d->soort?->volgorde)->values();
    }

    /** "week 41 · Spoedverhuur · 05-10 t/m 11-10-2026" (+ dagen als het een deel is). */
    public static function dienstLabel(Dienst $d): string
    {
        $w = $d->week;
        $s = 'week '.$w->weeknummer.' · '.($d->soort?->naam ?? '?').' · '.$w->van->format('d-m').' t/m '.$w->tm->format('d-m-Y');
        if (! $d->heleWeek()) {
            $s .= ' ('.Weekindeling::dagNaam($d->dagVan(), true).' t/m '.Weekindeling::dagNaam($d->dagTm(), true).')';
        }

        return $s;
    }

    private function tijdlijn(Ruiling $r): array
    {
        $t = [['op' => $r->created_at, 'tekst' => 'Aangevraagd door '.($r->aangevraagdDoor?->naam ?? '?'), 'icoon' => 'send', 'kleur' => 'secondary']];
        if ($r->herinnerd_op) {
            $t[] = ['op' => $r->herinnerd_op, 'tekst' => 'Herinnering gemaild aan '.($r->moetBevestigen()?->naam ?? 'de collega'), 'icoon' => 'bell', 'kleur' => 'warning'];
        }
        if ($r->status === 'bevestigd' || $r->status === 'teruggedraaid') {
            $t[] = ['op' => $r->bevestigd_op, 'tekst' => 'Bevestigd'.($r->status === 'bevestigd' && $r->afgehandeld_door ? ' door '.$r->afgehandeld_door : '').' — rooster aangepast', 'icoon' => 'check-circle', 'kleur' => 'success'];
        }
        if ($r->status === 'teruggedraaid') {
            $t[] = ['op' => $r->updated_at, 'tekst' => 'Teruggedraaid door '.($r->afgehandeld_door ?: 'beheerder'), 'icoon' => 'arrow-counterclockwise', 'kleur' => 'danger'];
        } elseif ($r->status === 'afgewezen') {
            $t[] = ['op' => $r->updated_at, 'tekst' => 'Afgewezen door '.($r->afgehandeld_door ?: 'de collega'), 'icoon' => 'x-circle', 'kleur' => 'danger'];
        } elseif ($r->status === 'ingetrokken') {
            $t[] = ['op' => $r->updated_at, 'tekst' => 'Ingetrokken door '.($r->afgehandeld_door ?: 'de aanvrager'), 'icoon' => 'slash-circle', 'kleur' => 'secondary'];
        } elseif ($r->status === 'verlopen') {
            $t[] = ['op' => $r->updated_at, 'tekst' => 'Verlopen: niet op tijd bevestigd', 'icoon' => 'hourglass-bottom', 'kleur' => 'secondary'];
        } elseif ($r->isOpen()) {
            $t[] = ['op' => $r->token_verloopt_op, 'tekst' => 'Vervalt automatisch als er dan nog niet is bevestigd', 'icoon' => 'hourglass-split', 'kleur' => 'light', 'toekomst' => true];
        }

        return $t;
    }

    private function magZien(Ruiling $r, ?Medewerker $eigen): bool
    {
        if ($this->isBeheer()) {
            return true;
        }
        if (! $eigen) {
            return false;
        }

        return in_array($eigen->id, [$r->van_medewerker_id, $r->naar_medewerker_id, $r->aangevraagd_door_id], true);
    }

    private function isBeheer(): bool
    {
        return in_array(actieve_rol(), ['manager', 'admin'], true);
    }

    private function naam(): string
    {
        return core_gebruiker()['name'] ?? (eigen_medewerker()?->naam ?? 'onbekend');
    }

    private function terug(Ruiling $r, ValidationException $e)
    {
        return redirect()->route('ruilen.toon', $r)->with('fout', implode(' ', array_map(fn ($m) => implode(' ', (array) $m), $e->errors())));
    }
}
