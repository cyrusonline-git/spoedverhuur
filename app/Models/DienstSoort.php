<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DienstSoort extends Model
{
    protected $table = 'dienst_soorten';
    protected $fillable = ['naam', 'kolom_kop', 'volgorde', 'betaald', 'naar_multiline', 'actief', 'vast', 'vaste_naam', 'vaste_telefoon', 'vaste_email', 'vaste_meldingen'];
    protected $casts = ['betaald' => 'boolean', 'naar_multiline' => 'boolean', 'actief' => 'boolean', 'vast' => 'boolean', 'vaste_meldingen' => 'boolean'];

    public function diensten(): HasMany { return $this->hasMany(Dienst::class); }

    public function scopeActief($q) { return $q->where('actief', true)->orderBy('volgorde'); }
    public function scopeUitRooster($q) { return $q->actief()->where('vast', false); }
}
