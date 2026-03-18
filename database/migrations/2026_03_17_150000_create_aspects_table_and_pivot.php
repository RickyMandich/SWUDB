<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Crea la tabella aspects
        if (!Schema::hasTable('aspects')) {
            Schema::create('aspects', function (Blueprint $table) {
                $table->id();
                $table->string('nome', 50)->unique();
                $table->string('colore', 20)->nullable();
                $table->string('slug', 50)->unique();
                $table->integer('order')->default(0);
                $table->boolean('primary')->default(true);
                $table->timestamps();
            });
        }

        // 2. Crea la tabella pivot card_aspect con ORDINE
        if (!Schema::hasTable('card_aspect')) {
            Schema::create('card_aspect', function (Blueprint $table) {
                $table->id();
                $table->string('card_cid', 15);
                $table->unsignedBigInteger('aspect_id');
                $table->integer('sort_order')->default(0);

                $table->foreign('card_cid')->references('cid')->on('Cards')->onDelete('cascade');
                $table->foreign('aspect_id')->references('id')->on('aspects')->onDelete('cascade');

                $table->unique(['card_cid', 'aspect_id']);
            });
        }

        // 3. Popola gli aspetti predefiniti (se non esistono)
        $defaultAspects = [
            ['nome' => 'Vigilanza', 'colore' => '#4073d4', 'slug' => 'vigilanza', 'order' => 1, 'primary' => true],
            ['nome' => 'Autorità', 'colore' => '#6faf2f', 'slug' => 'autorita', 'order' => 2, 'primary' => true],
            ['nome' => 'Aggressione', 'colore' => '#d72323', 'slug' => 'aggressione', 'order' => 3, 'primary' => true],
            ['nome' => 'Astuzia', 'colore' => '#f2e82b', 'slug' => 'astuzia', 'order' => 4, 'primary' => true],
            ['nome' => 'Malvagità', 'colore' => '#000000', 'slug' => 'malvagita', 'order' => 5, 'primary' => false],
            ['nome' => 'Eroismo', 'colore' => '#ffffff', 'slug' => 'eroismo', 'order' => 6, 'primary' => false],
        ];

        foreach ($defaultAspects as $aspect) {
            DB::table('aspects')->updateOrInsert(['slug' => $aspect['slug']], $aspect);
        }

        // 4. Migra i dati esistenti PRESERVANDO L'ORDINE
        $cards = DB::table('Cards')->get();
        foreach ($cards as $card) {
            $aspectsToSync = [];

            if ($card->aspettoPrimario) {
                $aspectId = DB::table('aspects')->where('nome', $card->aspettoPrimario)->value('id');
                if ($aspectId)
                    $aspectsToSync[0] = $aspectId;
            }

            if ($card->aspettoSecondario) {
                $aspectId = DB::table('aspects')->where('nome', $card->aspettoSecondario)->value('id');
                if ($aspectId && !isset($aspectsToSync[0]) || ($aspectId && $aspectsToSync[0] != $aspectId)) {
                    $aspectsToSync[1] = $aspectId;
                }
            }

            foreach ($aspectsToSync as $order => $aId) {
                DB::table('card_aspect')->insertOrIgnore([
                    'card_cid' => $card->cid,
                    'aspect_id' => $aId,
                    'sort_order' => $order
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('card_aspect');
        Schema::dropIfExists('aspects');
    }
};
