<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void{
        if (!Schema::hasTable('expansions')) {
            Schema::create('expansions', function (Blueprint $table) {
                $table->string('espansione', 10)->primary();
                $table->string('uscita', 65);
                $table->string('rotazione', 1)->default('0');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expansions');
    }
};
