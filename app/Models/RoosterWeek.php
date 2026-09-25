<?php

namespace App\Models;

use App\Services\Weekindeling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoosterWeek extends Model
{
    protected $table = 'rooster_weken';
    protected $fillable = ['jaar', 'weeknummer', 'van', 'tm'];
    protected $casts = ['van' => 'date', 'tm' => 'date'];

    public function diensten(): HasMany { return $this->hasMany(Dienst::class); }
    public function toewijzingen(): HasMany { return $this->hasMany(Toewijzing::class); }

    /** Week vinden of aanmaken op basis van ISO-jaar/-week. */
    public static function voor(int $jaar, int $week): self
    {
        [$van, $tm] = Weekindeling::datums($jaar, $week);
        return static::firstOrCreate(['jaar' => $jaar, 'weeknummer' => $week], ['van' => $van, 'tm' => $tm]);
    }

    public function label(): string { return 'Week '.$this->weeknummer.' ('.$this->van->format('d-m').' t/m '.$this->tm->format('d-m-Y').')'; }
    public function datumVanDag(int $dag): \Carbon\Carbon { return $this->van->copy()->addDays($dag - 1); }
    public function maand(): array { return Weekindeling::maandVanWeek($this->jaar, $this->weeknummer); }
    public function scopeToekomst($q) { return $q->where('tm', '>=', now()->toDateString()); }
}
