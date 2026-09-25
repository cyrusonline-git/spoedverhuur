<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alle tabellen van de Spoedverhuur-app. Dagen zijn 1 (maandag) t/m 7 (zondag).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Dienstsoorten = kolommen van het rooster (+ vaste tweede lijn h&h die niet in Excel staat)
        Schema::create('dienst_soorten', function (Blueprint $table) {
            $table->id();
            $table->string('naam');
            $table->string('kolom_kop')->nullable();      // exacte kop in het Excel-rooster (herkenning bij import)
            $table->unsignedInteger('volgorde')->default(0);
            $table->boolean('betaald')->default(true);
            $table->boolean('naar_multiline')->default(true);
            $table->boolean('actief')->default(true);
            $table->boolean('vast')->default(false);       // vaste persoon, komt niet uit Excel
            $table->string('vaste_naam')->nullable();
            $table->string('vaste_telefoon')->nullable();
            $table->string('vaste_email')->nullable();
            $table->boolean('vaste_meldingen')->default(false);
            $table->timestamps();
        });

        Schema::create('rooster_weken', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('jaar');
            $table->unsignedTinyInteger('weeknummer');
            $table->date('van');   // maandag
            $table->date('tm');    // zondag
            $table->timestamps();
            $table->unique(['jaar', 'weeknummer']);
            $table->index('van');
        });

        // Medewerkers: gespiegeld uit CORE (/api/employees), met handmatige terugvalvelden
        Schema::create('medewerkers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('core_employee_id')->nullable()->unique();
            $table->unsignedBigInteger('core_user_id')->nullable()->index();
            $table->string('naam');
            $table->string('email')->nullable();
            $table->string('telefoon')->nullable();
            $table->string('personeelsnummer')->nullable();
            $table->string('email_handmatig')->nullable();
            $table->string('telefoon_handmatig')->nullable();
            $table->string('personeelsnummer_handmatig')->nullable();
            $table->boolean('actief')->default(true);
            $table->timestamp('laatst_gesynct_at')->nullable();
            $table->timestamps();
            $table->index('naam');
        });

        // Schrijfwijzen uit het rooster ("Jefke Knubben", "jurgen jansema ") -> medewerker
        Schema::create('medewerker_aliassen', function (Blueprint $table) {
            $table->id();
            $table->string('alias');              // genormaliseerd (kleine letters, zonder accenten)
            $table->string('alias_origineel');
            $table->foreignId('medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->timestamps();
            $table->unique('alias');
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('bestandsnaam');
            $table->string('blad')->nullable();
            $table->unsignedSmallInteger('jaar')->nullable();
            $table->unsignedSmallInteger('aantal_weken')->default(0);
            $table->unsignedSmallInteger('aantal_diensten')->default(0);
            $table->json('meldingen')->nullable();
            $table->string('door_naam')->nullable();
            $table->timestamps();
        });

        // Het geplande rooster (zoals geïmporteerd/handmatig gezet), vóór ruilingen
        Schema::create('diensten', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rooster_week_id')->constrained('rooster_weken')->cascadeOnDelete();
            $table->foreignId('dienst_soort_id')->constrained('dienst_soorten')->cascadeOnDelete();
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->string('rooster_naam')->nullable();   // originele celtekst (of het deel ervan)
            $table->unsignedTinyInteger('deel_van')->nullable(); // 1-7, null = hele week
            $table->unsignedTinyInteger('deel_tm')->nullable();
            $table->string('bron', 20)->default('import'); // import | handmatig
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->timestamps();
            $table->index(['rooster_week_id', 'dienst_soort_id']);
            $table->index('medewerker_id');
        });

        Schema::create('ruilingen', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);                    // ruil | overname | deel
            $table->foreignId('dienst_id')->constrained('diensten')->cascadeOnDelete();
            $table->foreignId('tegen_dienst_id')->nullable()->constrained('diensten')->nullOnDelete();
            $table->foreignId('van_medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->foreignId('naar_medewerker_id')->constrained('medewerkers')->cascadeOnDelete();
            $table->unsignedTinyInteger('dagen_van')->nullable(); // bij 'deel': dagen die naar de ander gaan
            $table->unsignedTinyInteger('dagen_tm')->nullable();
            $table->string('status', 20)->default('aangevraagd'); // aangevraagd | bevestigd | afgewezen | ingetrokken | verlopen | teruggedraaid
            $table->foreignId('aangevraagd_door_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->timestamp('bevestigd_op')->nullable();
            $table->string('afgehandeld_door')->nullable();
            $table->text('opmerking')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->timestamp('token_verloopt_op')->nullable();
            $table->timestamp('herinnerd_op')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // Het effectieve rooster (afgeleid: diensten + bevestigde ruilingen) — enige bron voor mails en vergoedingen
        Schema::create('toewijzingen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rooster_week_id')->constrained('rooster_weken')->cascadeOnDelete();
            $table->foreignId('dienst_soort_id')->constrained('dienst_soorten')->cascadeOnDelete();
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->string('rooster_naam')->nullable();
            $table->unsignedTinyInteger('dag_van')->default(1);
            $table->unsignedTinyInteger('dag_tm')->default(7);
            $table->string('oorsprong', 20)->default('rooster');   // rooster | ruil | overname | deel | handmatig
            $table->foreignId('dienst_id')->nullable()->constrained('diensten')->nullOnDelete();
            $table->foreignId('ruiling_id')->nullable()->constrained('ruilingen')->nullOnDelete();
            $table->timestamps();
            $table->index(['rooster_week_id', 'dienst_soort_id']);
            $table->index('medewerker_id');
        });

        Schema::create('mail_taken', function (Blueprint $table) {
            $table->id();
            $table->string('soort', 40);         // aankondiging | dienst_vandaag | multiline | maandoverzicht | ruil_verzoek | ruil_bevestigd | ruil_afgewezen | ruil_ingetrokken | ruil_herinnering | beheer_signaal | test
            $table->string('referentie');        // uniek per soort, bv. "2026-40:12" (week:medewerker) of "2026-10" (maand)
            $table->timestamp('gepland_op')->nullable();
            $table->timestamp('verzonden_op')->nullable();
            $table->json('ontvangers')->nullable();
            $table->json('cc')->nullable();
            $table->string('onderwerp')->nullable();
            $table->string('status', 20)->default('gepland'); // gepland | verzonden | mislukt | overgeslagen
            $table->text('fout')->nullable();
            $table->boolean('test_modus')->default(false);
            $table->string('bijlage_pad')->nullable();
            $table->string('aangemaakt_door')->nullable();  // 'scheduler' of naam van de beheerder
            $table->timestamps();
            $table->unique(['soort', 'referentie']);
            $table->index(['status', 'gepland_op']);
        });

        Schema::create('maandoverzichten', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('jaar');
            $table->unsignedTinyInteger('maand');
            $table->string('bestandsnaam');
            $table->string('bestandspad');
            $table->unsignedSmallInteger('aantal_regels')->default(0);
            $table->decimal('totaal_bedrag', 10, 2)->default(0);
            $table->json('regels')->nullable();
            $table->string('aangemaakt_door')->nullable();
            $table->timestamp('verzonden_op')->nullable();
            $table->boolean('vergrendeld')->default(false);
            $table->foreignId('correctie_van_id')->nullable()->constrained('maandoverzichten')->nullOnDelete();
            $table->timestamps();
            $table->index(['jaar', 'maand']);
        });

        Schema::create('audit_log', function (Blueprint $table) {
            $table->id();
            $table->string('wie')->nullable();
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->string('actie', 60);
            $table->string('onderwerp')->nullable();
            $table->json('details')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index('actie');
        });

        // Fase 2: meldingen van de telefooncentrale (Multiline), gekoppeld aan de dienstdoende
        Schema::create('meldingen_multiline', function (Blueprint $table) {
            $table->id();
            $table->date('datum');
            $table->time('tijd')->nullable();
            $table->string('melding_nr')->nullable();
            $table->string('klant')->nullable();
            $table->text('tekst')->nullable();
            $table->string('doorgeschakeld_naar')->nullable();
            $table->foreignId('dienst_soort_id')->nullable()->constrained('dienst_soorten')->nullOnDelete();
            $table->foreignId('medewerker_id')->nullable()->constrained('medewerkers')->nullOnDelete();
            $table->foreignId('toewijzing_id')->nullable()->constrained('toewijzingen')->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('imports')->nullOnDelete();
            $table->timestamps();
            $table->index('datum');
        });
    }

    public function down(): void
    {
        foreach (['meldingen_multiline', 'audit_log', 'maandoverzichten', 'mail_taken', 'toewijzingen', 'ruilingen', 'diensten', 'imports', 'medewerker_aliassen', 'medewerkers', 'rooster_weken', 'dienst_soorten', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
