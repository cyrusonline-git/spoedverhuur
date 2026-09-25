<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeldingMultiline extends Model
{
    protected $table = 'meldingen_multiline';
    protected $fillable = ['datum', 'tijd', 'melding_nr', 'klant', 'tekst', 'doorgeschakeld_naar', 'dienst_soort_id', 'medewerker_id', 'toewijzing_id', 'import_id'];
    protected $casts = ['datum' => 'date'];
}
