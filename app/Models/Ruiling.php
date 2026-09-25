<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ruiling tussen twee medewerkers.
 *  - ruil:     A (dienst) <-> B (tegen_dienst), beide hele diensten wisselen van persoon
 *  - overname: B neemt de dienst van A over (A houdt niets)
 *  - deel:     B neemt de dagen dagen_van..dagen_tm van A's dienst over; A houdt de rest
 */
class Ruiling extends Model
{
    protected $table = 'ruilingen';
    protected $fillable = ['type', 'dienst_id', 'tegen_dienst_id', 'van_medewerker_id', 'naar_medewerker_id', 'dagen_van', 'dagen_tm', 'status', 'aangevraagd_door_id', 'bevestigd_op', 'afgehandeld_door', 'opmerking', 'token', 'token_verloopt_op', 'herinnerd_op'];
    protected $casts = ['bevestigd_op' => 'datetime', 'token_verloopt_op' => 'datetime', 'herinnerd_op' => 'datetime'];

    public const TYPES = ['ruil' => 'Ruilen (wederzijds)', 'overname' => 'Overnemen', 'deel' => 'Dagen delen'];
    public const STATUSSEN = ['aangevraagd' => 'Wacht op bevestiging', 'bevestigd' => 'Bevestigd', 'afgewezen' => 'Afgewezen', 'ingetrokken' => 'Ingetrokken', 'verlopen' => 'Verlopen', 'teruggedraaid' => 'Teruggedraaid'];

    public function dienst(): BelongsTo { return $this->belongsTo(Dienst::class); }
    public function tegenDienst(): BelongsTo { return $this->belongsTo(Dienst::class, 'tegen_dienst_id'); }
    public function van(): BelongsTo { return $this->belongsTo(Medewerker::class, 'van_medewerker_id'); }
    public function naar(): BelongsTo { return $this->belongsTo(Medewerker::class, 'naar_medewerker_id'); }
    public function aangevraagdDoor(): BelongsTo { return $this->belongsTo(Medewerker::class, 'aangevraagd_door_id'); }

    public function isOpen(): bool { return $this->status === 'aangevraagd'; }
    public function typeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }
    public function statusLabel(): string { return self::STATUSSEN[$this->status] ?? $this->status; }
    /** Wie moet nog bevestigen: de tegenpartij van de aanvrager. */
    public function moetBevestigen(): ?Medewerker
    {
        return $this->aangevraagd_door_id === $this->van_medewerker_id ? $this->naar : $this->van;
    }
}
