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
        Schema::table('card_aspect', function (Blueprint $table) {
            $table->unsignedTinyInteger('position')->default(0)->after('aspect_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('card_aspect', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};
