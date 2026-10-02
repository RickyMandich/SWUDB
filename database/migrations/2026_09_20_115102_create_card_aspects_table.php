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
        Schema::create('card_aspect', function (Blueprint $table) {
            $table->string('card_id', 20);
            $table->foreignId('aspect_id')->constrained('aspects')->cascadeOnDelete();
            $table->timestamps();

            $table->foreign('card_id')->references('id')->on('cards')->cascadeOnDelete();
            $table->primary(['card_id', 'aspect_id']);
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
