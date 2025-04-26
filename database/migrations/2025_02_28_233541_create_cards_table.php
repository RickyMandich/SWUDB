<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    protected array $tags = ['cards'];

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
            $table->string('nome', 100);
            $table->string('titolo', 100)->default("");
            $table->string('tipo', 20);
            $table->string('rarita', 100);
            $table->decimal('costo', 2, 0)->default(0);
            $table->decimal('vita', 2, 0)->nullable();
            $table->decimal('potenza', 2, 0)->nullable();
            $table->longText('descrizione');
            $table->string('tratti', 100);
            $table->string('arena', 100)->nullable();
            $table->string('artista', 100);
            $table->string('uscita', 65);
            $table->string('frontArt', 200)->nullable();
            $table->string('backArt', 200)->nullable();
            $table->integer('maxCopie')->default(3);
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
