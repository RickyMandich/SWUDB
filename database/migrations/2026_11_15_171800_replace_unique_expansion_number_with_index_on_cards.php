<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces UNIQUE(expansion, number) on cards with a normal index.
     * Variants (foil, hyperspace, promos...) share expansion and number with their original card,
     * so the unique constraint rejected about 1400 of them during the import (Duplicate entry 'SOR-N').
     *
     * Sostituisce UNIQUE(expansion, number) su cards con un indice normale.
     * Le varianti (foil, hyperspace, promo...) hanno lo stesso espansione e numero dell'originale,
     * quindi il vincolo unico ne scartava circa 1400 durante l'import (Duplicate entry 'SOR-N').
     */
    public function up(): void
    {
        // L'ordine conta: il vecchio unique può essere l'unico indice che sostiene la FK su `expansion`,
        // quindi il nuovo indice va creato PRIMA di togliere il vecchio.
        Schema::table('cards', function (Blueprint $table) {
            $table->index(['expansion', 'number']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropUnique(['expansion', 'number']);
        });
    }

    /**
     * Fails if the table already holds variants with the same expansion and number as their original.
     * Fallisce se la tabella contiene già varianti con lo stesso espansione e numero dell'originale.
     */
    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->unique(['expansion', 'number']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropIndex(['expansion', 'number']);
        });
    }
};
