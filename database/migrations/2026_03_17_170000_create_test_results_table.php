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
        if (!Schema::hasTable('test_results')) {
            Schema::create('test_results', function (Blueprint $table) {
                $table->id();
                $table->string('test_name');
                $table->boolean('status'); // true = pass, false = fail
                $table->text('output')->nullable();
                $table->float('duration')->nullable();
                $table->string('run_id')->index(); // Identifies a single test suite execution
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_results');
    }
};
