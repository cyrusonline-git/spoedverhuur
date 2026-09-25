<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Spiegel van een CORE-gebruiker (identiteit komt uit Boels CORE). */
class User extends Model
{
    protected $fillable = [
        'core_user_id', 'name', 'email', 'is_super_admin', 'depot', 'area', 'rollen', 'last_seen_at',
    ];

    protected $casts = [
        'is_super_admin' => 'boolean',
        'rollen' => 'array',
        'last_seen_at' => 'datetime',
    ];
}
