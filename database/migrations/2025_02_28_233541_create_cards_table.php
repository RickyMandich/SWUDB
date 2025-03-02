<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('Cards', function (Blueprint $table) {
            $table->string('cid', 15)->unique();
            $table->string('espansione', 10);
            $table->decimal('numero', 3, 0);
            $table->string('aspettoPrimario', 100)->nullable();
            $table->string('aspettoSecondario', 100)->nullable();
            $table->boolean('unica')->default(0);
            $table->string('nome', 100)->nullable();
            $table->string('titolo', 100);
            $table->string('tipo', 20)->nullable();
            $table->string('rarita', 100)->nullable();
            $table->decimal('costo', 2, 0)->nullable();
            $table->decimal('vita', 2, 0)->nullable();
            $table->decimal('potenza', 2, 0)->nullable();
            $table->longText('descrizione')->nullable();
            $table->string('tratti', 100)->nullable();
            $table->string('arena', 100)->nullable();
            $table->string('artista', 100)->nullable();
            $table->string('uscita', 65)->nullable();
            $table->primary(['espansione', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Cards');
    }
};
