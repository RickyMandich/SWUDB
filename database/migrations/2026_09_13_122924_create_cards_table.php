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
            $table->string('cid', 15)->unique();
            $table->string('expansion', 10);
            $table->decimal('number', 3, 0);
            $table->boolean('unique')->default(0);
            $table->string('name', 100);
            $table->string('title', 100)->default("");
            $table->string('type', 20);
            $table->string('rarity', 100);
            $table->decimal('cost', 2, 0)->default(0);
            $table->decimal('health', 2, 0)->nullable();
            $table->decimal('power', 2, 0)->nullable();
            $table->longText('description');
            $table->string('traits', 100);
            $table->string('arena', 100)->nullable();
            $table->string('artist', 100);
            $table->string('frontArt', 200)->nullable();
            $table->string('backArt', 200)->nullable();
            $table->integer('maxCopies')->default(3);
            $table->date('releaseDate')->nullable();
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
