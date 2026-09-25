<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Eén generieke mailable: platte tekst (wordt in de Boels-huisstijl gezet) of kant-en-klare HTML.
 * Opties: html (bool), ics + ics_naam (agenda-bijlage), bijlage_pad + bijlage_naam (bestand).
 */
class SpoedMail extends Mailable
{
    public function __construct(public string $onderwerp, public string $inhoud, public array $opties = [])
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->onderwerp, from: new \Illuminate\Mail\Mailables\Address(config('mail.from.address'), setting('mail_van_naam', 'Boels Industrial — Spoedverhuur')));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.basis', with: [
            'onderwerp' => $this->onderwerp,
            'html' => ! empty($this->opties['html']),
            'inhoud' => $this->inhoud,
        ]);
    }

    public function attachments(): array
    {
        $b = [];
        if (! empty($this->opties['ics'])) {
            $b[] = Attachment::fromData(fn () => $this->opties['ics'], $this->opties['ics_naam'] ?? 'dienst.ics')->withMime('text/calendar');
        }
        if (! empty($this->opties['bijlage_pad']) && is_file($this->opties['bijlage_pad'])) {
            $b[] = Attachment::fromPath($this->opties['bijlage_pad'])->as($this->opties['bijlage_naam'] ?? basename($this->opties['bijlage_pad']))
                ->withMime('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        }

        return $b;
    }
}
