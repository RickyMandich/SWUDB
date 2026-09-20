<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->string('expansion', 10);
            $table->unsignedInteger('number');
            $table->string('cid')->unique();
            $table->boolean('unique_card')->default(false);
            $table->string('name');
            $table->string('title')->nullable();
            $table->enum('type', [
                "Unit",
                "Upgrade",
                "Event",
                "Leader",
                "Base",
                "CreditToken",
                "ForceToken",
                "TokenUnit",
                "TokenUpgrade"
            ]);
            $table->enum('rarity', [
                "Common",
                "Uncommon",
                "Rare",
                "Legendary",
                "Special"
            ]);
            $table->unsignedTinyInteger('cost')->nullable();
            $table->unsignedTinyInteger('health')->nullable();
            $table->unsignedTinyInteger('power')->nullable();
            $table->text('text')->nullable();
            $table->string('arena')->nullable();
            $table->string('artist')->nullable();
            $table->string('front_art_path')->nullable()->comment("Path **relativo** nel disk `public` di Laravel (fisicamente `storage/app/public/...`), es. `cards/{expansion}/{number}-front.{ext}` — non l'URL diretto dell'API ufficiale: le immagini vengono scaricate in locale durante l'import (Step 4.6), così il sito non dipende dalla disponibilità del CDN ufficiale a runtime. L'estensione `{ext}` si determina al momento del download (content-type), non è detto sia sempre `.png`.");
            $table->string('back_art_path')->nullable()->comment("Path **relativo** nel disk `public` di Laravel (fisicamente `storage/app/public/...`), es. `cards/{expansion}/{number}-back.{ext}` — non l'URL diretto dell'API ufficiale: le immagini vengono scaricate in locale durante l'import (Step 4.6), così il sito non dipende dalla disponibilità del CDN ufficiale a runtime. L'estensione `{ext}` si determina al momento del download (content-type), non è detto sia sempre `.png`.");
            $table->unsignedTinyInteger('max_copies')->nullable()->comment("Il numero massimo di copie di questa carta che il giocatore può avere in un deck (valorizzato solo se non è il valore standard).")->default(null);
            $table->date('release_date')->nullable();
            $table->timestamps();

            # costraints
            $table->primary(['expansion', 'number']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
