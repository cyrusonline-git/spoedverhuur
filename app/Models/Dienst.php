<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Geplande dienst (roosterregel), vóór ruilingen. deel_van/deel_tm null = hele week. */
class Dienst extends Model
{
    protected $table = 'diensten';
    protected $fillable = ['rooster_week_id', 'dienst_soort_id', 'medewerker_id', 'rooster_naam', 'deel_van', 'deel_tm', 'bron', 'import_id'];

    public function week(): BelongsTo { return $this->belongsTo(RoosterWeek::class, 'rooster_week_id'); }
    public function soort(): BelongsTo { return $this->belongsTo(DienstSoort::class, 'dienst_soort_id'); }
    public function medewerker(): BelongsTo { return $this->belongsTo(Medewerker::class); }

    public function dagVan(): int { return (int) ($this->deel_van ?: 1); }
    public function dagTm(): int { return (int) ($this->deel_tm ?: 7); }
    public function heleWeek(): bool { return $this->dagVan() === 1 && $this->dagTm() === 7; }
}
