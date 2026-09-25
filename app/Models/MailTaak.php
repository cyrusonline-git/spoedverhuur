<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTaak extends Model
{
    protected $table = 'mail_taken';
    protected $fillable = ['soort', 'referentie', 'gepland_op', 'verzonden_op', 'ontvangers', 'cc', 'onderwerp', 'status', 'fout', 'test_modus', 'bijlage_pad', 'aangemaakt_door'];
    protected $casts = ['gepland_op' => 'datetime', 'verzonden_op' => 'datetime', 'ontvangers' => 'array', 'cc' => 'array', 'test_modus' => 'boolean'];

    public const SOORTEN = [
        'aankondiging' => 'Aankondiging dienst (week ervoor)',
        'dienst_vandaag' => 'Herinnering: dienst start vandaag',
        'multiline' => 'Weeklijst naar telefooncentrale',
        'maandoverzicht' => 'Maandoverzicht vergoedingen',
        'ruil_verzoek' => 'Ruilverzoek',
        'ruil_bevestigd' => 'Ruiling bevestigd',
        'ruil_afgewezen' => 'Ruiling afgewezen',
        'ruil_ingetrokken' => 'Ruiling ingetrokken',
        'ruil_herinnering' => 'Herinnering ruilverzoek',
        'beheer_signaal' => 'Signaal aan beheerder',
        'test' => 'Testmail',
    ];
}
