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
        Schema::create('mazzi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nome', 500);
            $table->boolean('public')->default(0);
            $table->integer('codUtente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mazzi');
    }
};
