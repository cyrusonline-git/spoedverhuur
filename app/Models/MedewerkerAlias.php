<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedewerkerAlias extends Model
{
    protected $table = 'medewerker_aliassen';
    protected $fillable = ['alias', 'alias_origineel', 'medewerker_id'];
    public function medewerker(): BelongsTo { return $this->belongsTo(Medewerker::class); }
}
