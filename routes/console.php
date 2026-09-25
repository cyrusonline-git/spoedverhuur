<?php

use App\Services\MedewerkerSync;
use App\Services\Planner;
use App\Services\Toewijzingen;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Elke minuut: verstuurt wat op dat moment gepland staat (aankondigingen, herinneringen,
// Multiline-lijst, maandoverzicht) en laat verlopen ruilverzoeken vervallen.
// Vereist in DirectAdmin: * * * * * php /home/deb2003831/domains/spoedverhuur.sorai.nl/laravel_app/artisan schedule:run
Artisan::command('spoed:planner', function (Planner $planner) {
    foreach ($planner->draai() as $regel) {
        $this->info($regel);
    }
})->purpose('Geplande mails versturen en ruilverzoeken laten vervallen');

Artisan::command('spoed:sync-medewerkers', function (MedewerkerSync $sync) {
    $n = $sync->sync();
    $this->info($n === null ? 'CORE niet bereikbaar (geen sessie in de console; sync via de app)' : "$n medewerkers gesynchroniseerd");
})->purpose('Medewerkers ophalen uit Boels CORE');

Artisan::command('spoed:herbereken', function (Toewijzingen $t) {
    $this->info($t->herberekenAlles().' weken herberekend');
})->purpose('Effectieve rooster (toewijzingen) opnieuw berekenen');

Schedule::command('spoed:planner')->everyMinute()->withoutOverlapping();
