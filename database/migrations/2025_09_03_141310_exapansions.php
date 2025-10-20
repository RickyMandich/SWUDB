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
                $table->boolean('confermato')->default(false);
                $table->string('principale', 10)->default('0')->comment('ID espansione principale del gruppo, 0 se è principale');

                // Indice per performance sulle query di raggruppamento
                $table->index('principale');
            });
        } else {
            // Aggiungi la colonna se la tabella esiste già
            if (!Schema::hasColumn('expansions', 'principale')) {
                Schema::table('expansions', function (Blueprint $table) {
                    $table->string('principale', 10)->default('0')->comment('ID espansione principale del gruppo, 0 se è principale');
                    $table->index('principale');
                });
            }
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
