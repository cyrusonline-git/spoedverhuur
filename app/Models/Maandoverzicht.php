<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Maandoverzicht extends Model
{
    protected $table = 'maandoverzichten';
    protected $fillable = ['jaar', 'maand', 'bestandsnaam', 'bestandspad', 'aantal_regels', 'totaal_bedrag', 'regels', 'aangemaakt_door', 'verzonden_op', 'vergrendeld', 'correctie_van_id'];
    protected $casts = ['regels' => 'array', 'verzonden_op' => 'datetime', 'vergrendeld' => 'boolean'];
}
