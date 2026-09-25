<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Effectieve dienst (na ruilingen). Enige bron voor mails, Multiline-lijst en vergoedingen. */
class Toewijzing extends Model
{
    protected $table = 'toewijzingen';
    protected $fillable = ['rooster_week_id', 'dienst_soort_id', 'medewerker_id', 'rooster_naam', 'dag_van', 'dag_tm', 'oorsprong', 'dienst_id', 'ruiling_id'];

    public function week(): BelongsTo { return $this->belongsTo(RoosterWeek::class, 'rooster_week_id'); }
    public function soort(): BelongsTo { return $this->belongsTo(DienstSoort::class, 'dienst_soort_id'); }
    public function medewerker(): BelongsTo { return $this->belongsTo(Medewerker::class); }
    public function dienst(): BelongsTo { return $this->belongsTo(Dienst::class); }
    public function ruiling(): BelongsTo { return $this->belongsTo(Ruiling::class); }

    public function dagen(): int { return $this->dag_tm - $this->dag_van + 1; }
    public function heleWeek(): bool { return $this->dag_van === 1 && $this->dag_tm === 7; }
    public function naam(): string { return $this->medewerker?->naam ?? ($this->rooster_naam ?: '—'); }
}
