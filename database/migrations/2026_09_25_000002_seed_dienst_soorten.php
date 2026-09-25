<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Standaard dienstsoorten (de 9 roosterkolommen) + de vaste tweede lijn h&h. */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $kolommen = ['Storingsdienst Energy West', 'Storingdienst Tools + Picken West', 'Spoedverhuur 1e lijns', 'Spoedverhuur Backup', 'Storingsdienst Zuid', 'Storingsdienst Noord', 'Storingsdienst Antwerpen', 'Spoeddienst h&h', 'Storingsdienst h&h'];
        foreach ($kolommen as $i => $kop) {
            if (! DB::table('dienst_soorten')->where('kolom_kop', $kop)->exists()) {
                DB::table('dienst_soorten')->insert(['naam' => $kop, 'kolom_kop' => $kop, 'volgorde' => $i, 'betaald' => true, 'naar_multiline' => true, 'actief' => true, 'vast' => false, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (! DB::table('dienst_soorten')->where('vast', true)->exists()) {
            DB::table('dienst_soorten')->insert(['naam' => 'Tweede lijn h&h (vast)', 'kolom_kop' => null, 'volgorde' => 99, 'betaald' => false, 'naar_multiline' => true, 'actief' => true, 'vast' => true, 'vaste_naam' => 'Michiel Thijs', 'vaste_meldingen' => false, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void {}
};
