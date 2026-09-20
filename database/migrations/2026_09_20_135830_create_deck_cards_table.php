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
        Schema::create('deck_cards', function (Blueprint $table) {
            $table->foreignId('deck_id')->constrained()->cascadeOnDelete();
            $table->string('cid');
            $table->foreign('cid')->references('cid')->on('cards')->cascadeOnDelete();
            $table->unsignedTinyInteger('quantity');
            $table->timestamps();

            $table->primary(['deck_id', 'cid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deck_cards');
    }
};
