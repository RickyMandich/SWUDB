<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    protected array $tags = ['compositions'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compositions', function (Blueprint $table) {
            $table->string('espansione', 10);
            $table->integer('numero');
            $table->integer('idMazzo');
            $table->foreign('idMazzo')->references('id')->on('decks')->onDelete('cascade');
            $table->boolean('foil')->default(0);
            $table->integer('copie')->default(1);
            $table->string('id', 20)->primary();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compositions');
    }
};
