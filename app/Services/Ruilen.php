<?php

namespace App\Services;

use App\Models\Dienst;
use App\Models\Medewerker;
use App\Models\Ruiling;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ruilingen: aanvragen door één van de twee, bevestigen door de ander (in de app
 * of via de token-link in de mail). Na bevestiging worden de toewijzingen
 * van de betrokken weken herberekend en krijgen beide partijen een mail.
 */
class Ruilen
{
    public function __construct(private Toewijzingen $toewijzingen, private MailDienst $mail)
    {
    }

    /**
     * @param  array  $data  type (ruil|overname|deel), dienst_id, tegen_dienst_id?, naar_medewerker_id, dagen_van?, dagen_tm?, opmerking?
     */
    public function aanvragen(Medewerker $aanvrager, array $data): Ruiling
    {
        $dienst = Dienst::with('week')->findOrFail($data['dienst_id']);
        $type = $data['type'];
        if (! isset(Ruiling::TYPES[$type])) {
            throw ValidationException::withMessages(['type' => 'Onbekend type ruiling.']);
        }
        if ($dienst->week->tm->lt(now('Europe/Amsterdam')->startOfDay())) {
            throw ValidationException::withMessages(['dienst_id' => 'Deze dienst is al voorbij.']);
        }
        $naar = Medewerker::findOrFail($data['naar_medewerker_id']);
        $eigenaar = $dienst->medewerker_id;
        // De aanvrager is óf de eigenaar van de dienst (geeft weg), óf degene die de dienst wil overnemen
        if ($eigenaar === $aanvrager->id) {
            $van = $aanvrager;
        } elseif ($naar->id === $aanvrager->id && $eigenaar) {
            $van = Medewerker::findOrFail($eigenaar);
        } else {
            throw ValidationException::withMessages(['dienst_id' => 'Je kunt alleen ruilen met je eigen dienst, of een dienst van een collega overnemen.']);
        }
        if ($van->id === $naar->id) {
            throw ValidationException::withMessages(['naar_medewerker_id' => 'Kies een andere collega.']);
        }
        if (Ruiling::where('dienst_id', $dienst->id)->where('status', 'aangevraagd')->exists()) {
            throw ValidationException::withMessages(['dienst_id' => 'Voor deze dienst staat al een ruilverzoek open.']);
        }
        $tegen = null;
        if ($type === 'ruil') {
            $tegen = Dienst::with('week')->findOrFail($data['tegen_dienst_id'] ?? 0);
            if ($tegen->medewerker_id !== $naar->id) {
                throw ValidationException::withMessages(['tegen_dienst_id' => 'De tegendienst is niet van de gekozen collega.']);
            }
            if ($tegen->week->tm->lt(now('Europe/Amsterdam')->startOfDay())) {
                throw ValidationException::withMessages(['tegen_dienst_id' => 'De tegendienst is al voorbij.']);
            }
        }
        $dv = $dt = null;
        if ($type === 'deel') {
            $dv = (int) ($data['dagen_van'] ?? 0);
            $dt = (int) ($data['dagen_tm'] ?? 0);
            if ($dv < $dienst->dagVan() || $dt > $dienst->dagTm() || $dv > $dt) {
                throw ValidationException::withMessages(['dagen_van' => 'Kies dagen binnen de dienst (van '.Weekindeling::dagNaam($dienst->dagVan()).' t/m '.Weekindeling::dagNaam($dienst->dagTm()).').']);
            }
            if ($dv === $dienst->dagVan() && $dt === $dienst->dagTm()) {
                $type = 'overname';
                $dv = $dt = null;
            }
        }
        $r = Ruiling::create([
            'type' => $type, 'dienst_id' => $dienst->id, 'tegen_dienst_id' => $tegen?->id,
            'van_medewerker_id' => $van->id, 'naar_medewerker_id' => $naar->id,
            'dagen_van' => $dv, 'dagen_tm' => $dt, 'status' => 'aangevraagd',
            'aangevraagd_door_id' => $aanvrager->id, 'opmerking' => trim((string) ($data['opmerking'] ?? '')) ?: null,
            'token' => Str::random(48), 'token_verloopt_op' => now()->addDays((int) setting('ruil_verval_dagen', 3)),
        ]);
        audit('ruiling.aangevraagd', $this->omschrijving($r), ['ruiling_id' => $r->id]);
        $this->mail->ruilVerzoek($r);

        return $r;
    }

    public function bevestigen(Ruiling $r, ?Medewerker $door, string $doorNaam): void
    {
        $this->controleerOpen($r);
        $tegen = $r->moetBevestigen();
        if ($door && $tegen && $door->id !== $tegen->id) {
            throw ValidationException::withMessages(['ruiling' => 'Alleen '.$tegen->naam.' kan dit verzoek bevestigen.']);
        }
        $r->update(['status' => 'bevestigd', 'bevestigd_op' => now(), 'afgehandeld_door' => $doorNaam, 'token' => null]);
        $this->toewijzingen->herberekenRuiling($r);
        audit('ruiling.bevestigd', $this->omschrijving($r), ['ruiling_id' => $r->id]);
        $this->mail->ruilBevestigd($r);
    }

    public function afwijzen(Ruiling $r, string $doorNaam, ?string $reden = null): void
    {
        $this->controleerOpen($r);
        $r->update(['status' => 'afgewezen', 'afgehandeld_door' => $doorNaam, 'token' => null, 'opmerking' => trim(($r->opmerking ? $r->opmerking."\n" : '').($reden ? 'Afgewezen: '.$reden : '')) ?: null]);
        audit('ruiling.afgewezen', $this->omschrijving($r), ['ruiling_id' => $r->id]);
        $this->mail->ruilAfgewezen($r);
    }

    public function intrekken(Ruiling $r, string $doorNaam): void
    {
        $this->controleerOpen($r);
        $r->update(['status' => 'ingetrokken', 'afgehandeld_door' => $doorNaam, 'token' => null]);
        audit('ruiling.ingetrokken', $this->omschrijving($r), ['ruiling_id' => $r->id]);
        $this->mail->ruilIngetrokken($r);
    }

    /** Beheerder draait een bevestigde ruiling terug. */
    public function terugdraaien(Ruiling $r, string $doorNaam, string $reden): void
    {
        if ($r->status !== 'bevestigd') {
            throw ValidationException::withMessages(['ruiling' => 'Alleen een bevestigde ruiling kan worden teruggedraaid.']);
        }
        $r->update(['status' => 'teruggedraaid', 'afgehandeld_door' => $doorNaam, 'opmerking' => trim(($r->opmerking ? $r->opmerking."\n" : '').'Teruggedraaid: '.$reden)]);
        $this->toewijzingen->herberekenRuiling($r);
        audit('ruiling.teruggedraaid', $this->omschrijving($r), ['ruiling_id' => $r->id, 'reden' => $reden]);
        $this->mail->ruilBevestigd($r, teruggedraaid: true);
    }

    /** Open verzoeken die over de vervaldatum zijn: verlopen; halverwege één herinnering. */
    public function verwerkVervallen(): void
    {
        foreach (Ruiling::where('status', 'aangevraagd')->get() as $r) {
            if ($r->token_verloopt_op && $r->token_verloopt_op->isPast()) {
                $r->update(['status' => 'verlopen', 'token' => null]);
                audit('ruiling.verlopen', $this->omschrijving($r), ['ruiling_id' => $r->id]);
                $this->mail->ruilAfgewezen($r, verlopen: true);
            } elseif (! $r->herinnerd_op && $r->created_at->addDay()->isPast()) {
                $r->update(['herinnerd_op' => now()]);
                $this->mail->ruilVerzoek($r, herinnering: true);
            }
        }
    }

    public function omschrijving(Ruiling $r): string
    {
        $d = $r->dienst;
        $s = $r->typeLabel().': '.$r->van?->naam.' → '.$r->naar?->naam.' · '.($d?->soort?->naam ?? '').' week '.($d?->week?->weeknummer ?? '?');
        if ($r->type === 'deel') {
            $s .= ' ('.Weekindeling::dagNaam((int) $r->dagen_van).' t/m '.Weekindeling::dagNaam((int) $r->dagen_tm).')';
        }
        if ($r->type === 'ruil' && $r->tegenDienst) {
            $s .= ' ⇄ '.$r->tegenDienst->soort?->naam.' week '.$r->tegenDienst->week?->weeknummer;
        }

        return $s;
    }

    private function controleerOpen(Ruiling $r): void
    {
        if (! $r->isOpen()) {
            throw ValidationException::withMessages(['ruiling' => 'Dit verzoek is al afgehandeld ('.$r->statusLabel().').']);
        }
    }
}
