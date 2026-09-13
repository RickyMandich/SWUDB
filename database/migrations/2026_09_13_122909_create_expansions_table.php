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
        Schema::create('expansions', function (Blueprint $table) {
            $table->string('expansion', 10)->primary();
            $table->string('releaseDate', 65);
            $table->string('rotation', 1)->default('0');
            $table->boolean('confirmed')->default(false);
            $table->string('mainExpansion', 10)->default('-1')->comment('ID espansione principale del gruppo, 0 se è principale, -1 se è standalone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expansions');
    }
};
