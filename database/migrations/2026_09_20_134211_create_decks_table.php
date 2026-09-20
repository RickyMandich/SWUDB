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
        Schema::create('decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users', 'id')->cascadeOnDelete();
            $table->string('name');
            $table->enum('format', ['premier', 'eternal', 'twin_suns'])->default('premier');
            $table->boolean('is_public')->default(false);
            $table->boolean('assembled')->default(false);
            $table->integer('version')->default(1);
            $table->bigInteger('previous_version_id')->nullable()->references('id')->on('decks')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('decks');
    }
};
