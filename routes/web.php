<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\DienstSoortController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\InstellingenController;
use App\Http\Controllers\Admin\MaandController;
use App\Http\Controllers\Admin\MailController;
use App\Http\Controllers\Admin\MedewerkerController;
use App\Http\Controllers\Admin\RoosterBewerkController;
use App\Http\Controllers\Admin\RuilBeheerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\RoosterController;
use App\Http\Controllers\RuilController;
use App\Http\Controllers\RuilTokenController;
use Illuminate\Support\Facades\Route;

// Publiek (zonder login): bevestigen/afwijzen van een ruilverzoek via de link in de mail
Route::get('/ruil/{token}/{actie}', [RuilTokenController::class, 'toon'])->where('actie', 'bevestig|afwijs')->name('ruil.token');
Route::post('/ruil/{token}/{actie}', [RuilTokenController::class, 'verwerk'])->where('actie', 'bevestig|afwijs')->name('ruil.token.verwerk');

// Alles hieronder achter de CORE-login (middleware 'core' = cookie-relay naar Boels CORE)
Route::middleware('core')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/kies-rol', [RolController::class, 'kies'])->name('kies-rol');
    Route::post('/kies-rol', [RolController::class, 'opslaan'])->name('kies-rol.opslaan');
    Route::get('/geen-toegang', [RolController::class, 'geenToegang'])->name('geen-toegang');

    // Rooster (iedereen met een rol)
    Route::get('/rooster', [RoosterController::class, 'index'])->name('rooster');               // ?jaar=&week= of ?maand=
    Route::get('/rooster/week/{jaar}/{week}', [RoosterController::class, 'week'])->name('rooster.week');
    Route::get('/mijn-diensten', [RoosterController::class, 'mijn'])->name('mijn-diensten');
    Route::get('/mijn-diensten/{jaar}/vergoedingen', [RoosterController::class, 'mijnVergoedingen'])->name('mijn-vergoedingen');
    Route::get('/wie', [RoosterController::class, 'wie'])->name('wie');                          // zoeken op naam: wie draait wanneer

    // Ruilen (medewerker; manager/admin kunnen ook namens iemand indienen)
    Route::get('/ruilen', [RuilController::class, 'index'])->name('ruilen');
    Route::get('/ruilen/nieuw', [RuilController::class, 'nieuw'])->name('ruilen.nieuw');           // ?dienst=
    Route::post('/ruilen', [RuilController::class, 'aanvragen'])->name('ruilen.aanvragen');
    Route::get('/ruilen/{ruiling}', [RuilController::class, 'toon'])->name('ruilen.toon');
    Route::post('/ruilen/{ruiling}/bevestig', [RuilController::class, 'bevestig'])->name('ruilen.bevestig');
    Route::post('/ruilen/{ruiling}/afwijs', [RuilController::class, 'afwijs'])->name('ruilen.afwijs');
    Route::post('/ruilen/{ruiling}/intrek', [RuilController::class, 'intrek'])->name('ruilen.intrek');
    Route::get('/ruilen/diensten-van/{medewerker}', [RuilController::class, 'dienstenVan'])->name('ruilen.diensten-van'); // JSON voor het formulier

    // Manager + admin: overzichten
    Route::middleware('rol:manager,admin')->prefix('overzicht')->name('overzicht.')->group(function () {
        Route::get('/', [ManagerController::class, 'index'])->name('index');
        Route::get('/bezetting', [ManagerController::class, 'bezetting'])->name('bezetting');
        Route::get('/ruilingen', [ManagerController::class, 'ruilingen'])->name('ruilingen');
        Route::get('/belasting', [ManagerController::class, 'belasting'])->name('belasting');      // wie draait hoeveel
        Route::get('/multiline/{jaar}/{week}', [ManagerController::class, 'multiline'])->name('multiline');
        Route::get('/export/{wat}', [ManagerController::class, 'export'])->name('export');          // csv: bezetting|ruilingen|belasting
        Route::get('/audit', [AuditController::class, 'index'])->name('audit');
    });

    // Beheer (alleen actieve rol admin)
    Route::middleware('rol:admin')->prefix('beheer')->name('admin.')->group(function () {
        // Rooster importeren + handmatig bewerken
        Route::get('/import', [ImportController::class, 'index'])->name('import');
        Route::post('/import/lees', [ImportController::class, 'lees'])->name('import.lees');            // upload → preview/koppelscherm
        Route::post('/import/verwerk', [ImportController::class, 'verwerk'])->name('import.verwerk');
        Route::get('/rooster/bewerk/{jaar}/{week}', [RoosterBewerkController::class, 'week'])->name('rooster.bewerk');
        Route::post('/rooster/bewerk/{jaar}/{week}', [RoosterBewerkController::class, 'opslaan'])->name('rooster.bewerk.opslaan');
        Route::post('/rooster/herbereken', [RoosterBewerkController::class, 'herbereken'])->name('rooster.herbereken');
        // Medewerkers (uit CORE) + aliassen + handmatige velden
        Route::get('/medewerkers', [MedewerkerController::class, 'index'])->name('medewerkers');
        Route::post('/medewerkers/sync', [MedewerkerController::class, 'sync'])->name('medewerkers.sync');
        Route::post('/medewerkers', [MedewerkerController::class, 'opslaan'])->name('medewerkers.opslaan');           // nieuw (handmatig)
        Route::post('/medewerkers/lijst', [MedewerkerController::class, 'lijst'])->name('medewerkers.lijst');   // bulk: naam · telefoon · personeelsnummer
        Route::post('/medewerkers/{medewerker}', [MedewerkerController::class, 'bijwerken'])->name('medewerkers.bijwerken');
        Route::post('/medewerkers/{medewerker}/alias', [MedewerkerController::class, 'alias'])->name('medewerkers.alias');
        Route::delete('/medewerkers/alias/{alias}', [MedewerkerController::class, 'aliasVerwijder'])->name('medewerkers.alias.verwijder');
        // Dienstsoorten
        Route::get('/dienstsoorten', [DienstSoortController::class, 'index'])->name('dienstsoorten');
        Route::post('/dienstsoorten', [DienstSoortController::class, 'opslaan'])->name('dienstsoorten.opslaan');
        // Ruilingen beheren
        Route::get('/ruilingen', [RuilBeheerController::class, 'index'])->name('ruilingen');
        Route::post('/ruilingen/{ruiling}/terugdraai', [RuilBeheerController::class, 'terugdraai'])->name('ruilingen.terugdraai');
        Route::post('/ruilingen/{ruiling}/afwijs', [RuilBeheerController::class, 'afwijs'])->name('ruilingen.afwijs');
        // Mailcentrum
        Route::get('/mail', [MailController::class, 'index'])->name('mail');
        Route::post('/mail/verstuur', [MailController::class, 'verstuur'])->name('mail.verstuur');                     // soort + jaar/week of maand
        Route::post('/mail/opnieuw/{taak}', [MailController::class, 'opnieuw'])->name('mail.opnieuw');
        Route::get('/mail/preview', [MailController::class, 'preview'])->name('mail.preview');                         // soort + jaar/week
        Route::post('/mail/test', [MailController::class, 'test'])->name('mail.test');
        Route::post('/mail/planner', [MailController::class, 'planner'])->name('mail.planner');                       // planner nu draaien
        Route::get('/mail/log', [MailController::class, 'log'])->name('mail.log');
        // Maandoverzicht vergoedingen
        Route::get('/maand', [MaandController::class, 'index'])->name('maand');                                        // ?jaar=&maand=
        Route::post('/maand/maak', [MaandController::class, 'maak'])->name('maand.maak');
        Route::get('/maand/{overzicht}/download', [MaandController::class, 'download'])->name('maand.download');
        Route::post('/maand/{overzicht}/verstuur', [MaandController::class, 'verstuur'])->name('maand.verstuur');
        Route::post('/maand/{overzicht}/vergrendel', [MaandController::class, 'vergrendel'])->name('maand.vergrendel');
        Route::get('/maand/archief', [MaandController::class, 'archief'])->name('maand.archief');
        // Instellingen
        Route::get('/instellingen', [InstellingenController::class, 'index'])->name('instellingen');
        Route::post('/instellingen', [InstellingenController::class, 'opslaan'])->name('instellingen.opslaan');
    });
});

Route::get('/uitloggen', [RolController::class, 'uitloggen'])->name('uitloggen');
