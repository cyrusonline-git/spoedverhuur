<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spiegel van CORE-gebruikers die deze app hebben bezocht (geen wachtwoorden:
 * inloggen gebeurt in Boels CORE). Plus de sessietabel van Laravel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('core_user_id')->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->boolean('is_super_admin')->default(false);
            $table->string('depot')->nullable();
            $table->string('area')->nullable();
            $table->json('rollen')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('sessions');
    }
};
