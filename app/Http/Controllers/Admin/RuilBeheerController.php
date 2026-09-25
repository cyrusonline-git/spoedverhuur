<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Medewerker;
use App\Models\Ruiling;
use App\Services\Ruilen;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Beheer: alle ruilingen met filters, terugdraaien (bevestigd) en afwijzen (open). */
class RuilBeheerController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->input('status', '');
        $medewerkerId = (int) $request->input('medewerker', 0);
        $van = (string) $request->input('van', '');
        $tm = (string) $request->input('tm', '');

        $q = Ruiling::with(['dienst.week', 'dienst.soort', 'tegenDienst.week', 'tegenDienst.soort', 'van', 'naar', 'aangevraagdDoor'])->orderBy('created_at', 'desc');
        if ($status !== '' && isset(Ruiling::STATUSSEN[$status])) {
            $q->where('status', $status);
        }
        if ($medewerkerId) {
            $q->where(fn ($w) => $w->where('van_medewerker_id', $medewerkerId)->orWhere('naar_medewerker_id', $medewerkerId));
        }
        if ($van !== '' && strtotime($van)) {
            $q->whereDate('created_at', '>=', $van);
        }
        if ($tm !== '' && strtotime($tm)) {
            $q->whereDate('created_at', '<=', $tm);
        }
        $ruilingen = $q->paginate(50)->withQueryString();

        $stats = [];
        foreach (Ruiling::STATUSSEN as $slug => $label) {
            $stats[$slug] = 0;
        }
        foreach (Ruiling::selectRaw('status, count(*) as n')->groupBy('status')->get() as $rij) {
            $stats[$rij->status] = (int) $rij->n;
        }

        return view('admin.ruilingen.index', [
            'ruilingen' => $ruilingen,
            'stats' => $stats,
            'totaal' => array_sum($stats),
            'medewerkers' => Medewerker::orderBy('naam')->get(),
            'filter' => ['status' => $status, 'medewerker' => $medewerkerId, 'van' => $van, 'tm' => $tm],
            'service' => app(Ruilen::class),
        ]);
    }

    public function terugdraai(Request $request, Ruiling $ruiling)
    {
        $data = $request->validate(['reden' => 'required|string|min:3|max:500'], ['reden.required' => 'Geef een reden op voor het terugdraaien.', 'reden.min' => 'Geef een reden op voor het terugdraaien.']);
        try {
            app(Ruilen::class)->terugdraaien($ruiling, $this->naam(), trim($data['reden']));
        } catch (ValidationException $e) {
            return back()->with('fout', $this->fout($e));
        }

        return back()->with('ok', 'Ruiling #'.$ruiling->id.' teruggedraaid; het rooster staat weer zoals ervoor en beide partijen krijgen een mail.');
    }

    public function afwijs(Request $request, Ruiling $ruiling)
    {
        $reden = trim((string) $request->input('reden', ''));
        try {
            app(Ruilen::class)->afwijzen($ruiling, $this->naam().' (beheer)', $reden ?: null);
        } catch (ValidationException $e) {
            return back()->with('fout', $this->fout($e));
        }

        return back()->with('ok', 'Ruilverzoek #'.$ruiling->id.' afgewezen; de aanvrager krijgt een mail.');
    }

    private function naam(): string
    {
        return core_gebruiker()['name'] ?? 'beheerder';
    }

    private function fout(ValidationException $e): string
    {
        return implode(' ', array_map(fn ($m) => implode(' ', (array) $m), $e->errors()));
    }
}
