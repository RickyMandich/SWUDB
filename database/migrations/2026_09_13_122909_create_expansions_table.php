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
            $table->string('expansion', 10)->primary()->comment('expansion\'s natural code');
            $table->date('legal_date')->nullable()->comment('date from which tis expansion became legal in premier');
            $table->string('rotation', 1)->default('0')->comment('the rotation of this set, the last 2 set are legal in premier');
            $table->boolean('confirmed')->default(false)->comment('if the expansion is confirmed (an admin have checked the data)');
            $table->string('group_main_expansion', 10)->nullable()->comment('the core set of the game are main expansion and reference themselfs, the subset (like the token from a set) reference the main expansion and the standalone set (like some promo sets) is null');
            $table->timestamps();

            # costraints
            $table->foreign('group_main_expansion')->references('expansion')->on('expansions')->onDelete('cascade');
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
