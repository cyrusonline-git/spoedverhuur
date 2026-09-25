<?php

namespace App\Services;

use App\Models\MailTaak;
use App\Models\Maandoverzicht;
use App\Models\RoosterWeek;
use Carbon\Carbon;

/**
 * Wat moet er nu (automatisch) verstuurd worden? Wordt elke minuut door de scheduler
 * aangeroepen (routes/console.php) en doet precies hetzelfde als de "Verstuur nu"-knoppen,
 * maar alleen op de ingestelde momenten. Elke verzending is idempotent via mail_taken.
 *
 * Instellingen (dag 1=ma..7=zo, tijd HH:MM):
 *   aankondiging_dag/aankondiging_tijd   standaard ma 09:00 in de week ervóór
 *   vandaag_tijd                          standaard 07:00 op de maandag van de dienst
 *   multiline_dag/multiline_tijd          standaard ma 07:00 van de dienstweek
 *   maand_dag/maand_tijd                  standaard dag 1, 08:00
 *   automatisch_versturen                 0/1: uitzetten = alleen handmatig
 */
class Planner
{
    public function __construct(private MailDienst $mail, private MaandoverzichtDienst $maand, private Ruilen $ruilen)
    {
    }

    /** Geeft een lijst met wat er gedaan is. */
    public function draai(?Carbon $nu = null): array
    {
        $nu = $nu ?? now('Europe/Amsterdam');
        $log = [];
        $this->ruilen->verwerkVervallen();
        if (! (int) setting('automatisch_versturen', 1)) {
            return ['automatisch versturen staat uit'];
        }
        [$jaar, $week] = Weekindeling::weekVanDatum($nu);
        $dezeWeek = RoosterWeek::where('jaar', $jaar)->where('weeknummer', $week)->first();
        $volgendeWeek = RoosterWeek::where('van', $nu->copy()->startOfWeek()->addWeek()->toDateString())->first();

        // Aankondiging: op de ingestelde dag/tijd van deze week, over volgende week
        if ($volgendeWeek && $this->moment($nu, (int) setting('aankondiging_dag', 1), (string) setting('aankondiging_tijd', '09:00'))) {
            $n = count(array_filter($this->mail->aankondigingen($volgendeWeek), fn ($t) => $t->wasRecentlyCreated || $t->verzonden_op?->gt($nu->copy()->subMinutes(5))));
            $log[] = "aankondigingen week {$volgendeWeek->weeknummer}: $n verzonden";
        }
        // Op de maandag van de dienstweek: herinnering + Multiline
        if ($dezeWeek && $nu->isoWeekday() === 1 && $this->naTijd($nu, (string) setting('vandaag_tijd', '07:00'))) {
            $n = count($this->mail->dienstVandaag($dezeWeek));
            $log[] = "dienst-vandaag week {$dezeWeek->weeknummer}: $n taken";
        }
        if ($dezeWeek && $this->moment($nu, (int) setting('multiline_dag', 1), (string) setting('multiline_tijd', '07:00'))) {
            $t = $this->mail->multiline($dezeWeek);
            $log[] = "multiline week {$dezeWeek->weeknummer}: {$t->status}";
        }
        // Maandoverzicht: op dag X van de maand, over de vorige maand
        if ((int) $nu->format('j') === (int) setting('maand_dag', 1) && $this->naTijd($nu, (string) setting('maand_tijd', '08:00'))) {
            $vorige = $nu->copy()->subMonthNoOverflow();
            $j = (int) $vorige->format('Y');
            $m = (int) $vorige->format('n');
            $al = MailTaak::where('soort', 'maandoverzicht')->where('referentie', 'like', "$j-$m:%")->where('status', 'verzonden')->exists();
            if (! $al && ! Maandoverzicht::where('jaar', $j)->where('maand', $m)->where('vergrendeld', true)->exists()) {
                $mo = $this->maand->maak($j, $m, 'scheduler');
                $t = $this->mail->maandoverzicht($mo, door: 'scheduler');
                $log[] = "maandoverzicht $m-$j: {$t->status}";
            }
        }

        return $log ?: ['niets te doen'];
    }

    /** Is het nu de gegeven weekdag en is de tijd (nog binnen dezelfde dag) gepasseerd? */
    private function moment(Carbon $nu, int $dag, string $tijd): bool
    {
        return $nu->isoWeekday() === $dag && $this->naTijd($nu, $tijd);
    }

    private function naTijd(Carbon $nu, string $tijd): bool
    {
        [$h, $m] = array_map('intval', explode(':', $tijd.':0'));

        return $nu->copy()->setTime($h, $m)->lte($nu);
    }
}
