<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $table = 'imports';
    protected $fillable = ['bestandsnaam', 'blad', 'jaar', 'aantal_weken', 'aantal_diensten', 'meldingen', 'door_naam'];
    protected $casts = ['meldingen' => 'array'];
}
