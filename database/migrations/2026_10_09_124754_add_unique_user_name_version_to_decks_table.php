<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * This migration is used to add the unique constraint on user_id, name and version columns in the decks table.
 * This constraint is needed to prevent the creation of duplicate decks with the same name and version for the same user.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->unique(['user_id', 'name', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('decks', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'name', 'version']);
        });
    }
};
