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
        Schema::create('card_aspect', function (Blueprint $table) {
            $table->string('id');
            $table->foreign('id')->references('id')->on('cards')->cascadeOnDelete();
            $table->foreignId('aspect_id')->constrained('aspects')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['id', 'aspect_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_aspect');
    }
};
