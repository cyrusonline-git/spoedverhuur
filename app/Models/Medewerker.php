<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Medewerker: gespiegeld uit CORE, met handmatige terugval als iemand (nog) niet in CORE staat. */
class Medewerker extends Model
{
    protected $table = 'medewerkers';
    protected $fillable = ['core_employee_id', 'core_user_id', 'naam', 'email', 'telefoon', 'personeelsnummer', 'email_handmatig', 'telefoon_handmatig', 'personeelsnummer_handmatig', 'actief', 'laatst_gesynct_at'];
    protected $casts = ['actief' => 'boolean', 'laatst_gesynct_at' => 'datetime'];

    public function aliassen(): HasMany { return $this->hasMany(MedewerkerAlias::class); }
    public function diensten(): HasMany { return $this->hasMany(Dienst::class); }
    public function toewijzingen(): HasMany { return $this->hasMany(Toewijzing::class); }

    /** CORE-waarde gaat voor; anders de handmatige waarde. */
    public function emailEffectief(): ?string { return $this->email ?: $this->email_handmatig; }
    public function telefoonEffectief(): ?string { return $this->telefoon ?: $this->telefoon_handmatig; }
    public function personeelsnummerEffectief(): ?string { return $this->personeelsnummer ?: $this->personeelsnummer_handmatig; }
    public function uitCore(): bool { return $this->core_employee_id !== null; }
    public function ontbreekt(): array
    {
        $m = [];
        if (! $this->emailEffectief()) { $m[] = 'e-mail'; }
        if (! $this->telefoonEffectief()) { $m[] = 'telefoon'; }
        if (! $this->personeelsnummerEffectief()) { $m[] = 'personeelsnummer'; }
        return $m;
    }
    public function scopeActief($q) { return $q->where('actief', true)->orderBy('naam'); }
}
