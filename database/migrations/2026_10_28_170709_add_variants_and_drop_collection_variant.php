<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds variant fields to cards, drops the unique (expansion, number) constraint,
     * and removes the obsolete collection_cards.variant column.
     *
     * Aggiunge i campi variante a cards, rimuove il vincolo unique su (expansion, number)
     * e droppa la colonna collection_cards.variant ora superflua.
     */
    public function up(): void
    {
        // 1. Modifiche sulla tabella cards
        Schema::table('cards', function (Blueprint $table) {
            // Sostituisce l'indice unique con un indice normale
            // (sicuro in caso di refusi di numerazione nei dati Strapi come SEC 571)
            $table->index(['expansion', 'number'], 'cards_expansion_number_index');

            // Nuovi campi variante
            $table->string('original_card_id', 20)->nullable()->after('id')
                ->comment('cards.id della carta originale di cui questa è una variante. NULL = è lei l\'originale.');
            $table->string('variant_type', 50)->nullable()->after('original_card_id')
                ->comment('Nome della variante (Hyperspace, Showcase, Weekly Play...). NULL per gli originali.');
            $table->string('variant_code', 10)->nullable()->after('variant_type')
                ->comment('Codice variante dell\'API (02 = Hyperspace, 03 = Showcase, 04 = Prerelease...). NULL per gli originali.');

            $table->foreign('original_card_id')->references('id')->on('cards')->cascadeOnDelete();
        });

        // 2. collection_cards: drop variant, aggiorna vincolo unico.
        // L'ordine conta: il vecchio unique (user_id, card_id, variant) può essere l'unico indice che
        // sostiene la FK su user_id, quindi il nuovo unique va creato PRIMA di togliere il vecchio.
        Schema::table('collection_cards', function (Blueprint $table) {
            // Ogni carta (anche variante/promo) può stare una sola volta nella collezione dell'utente
            $table->unique(['user_id', 'card_id']);
        });

        Schema::table('collection_cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id', 'variant']);
            $table->dropColumn('variant');
        });
    }

    public function down(): void
    {
        Schema::table('collection_cards', function (Blueprint $table) {
            $table->enum('variant', ['normal', 'foil', 'hyper', 'prestige', 'hyper_foil'])
                ->default('normal')->after('card_id');
            $table->unique(['user_id', 'card_id', 'variant']);
        });

        Schema::table('collection_cards', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'card_id']);
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->dropForeign(['original_card_id']);
            $table->dropColumn(['original_card_id', 'variant_type', 'variant_code']);
            $table->unique(['expansion', 'number']);
            $table->dropIndex('cards_expansion_number_index');
        });
    }
};
