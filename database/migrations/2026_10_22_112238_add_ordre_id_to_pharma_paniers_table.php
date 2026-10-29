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
        // Ligne de panier venue d'un ordre de soins du dentiste : le paiement de la vente le marque comme réglé.
        Schema::table('pharma_paniers', function (Blueprint $table) {
            $table->unsignedBigInteger('ordre_soins_id')->nullable()->after('kit_id')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharma_paniers', function (Blueprint $table) {
            $table->dropColumn('ordre_soins_id');
        });
    }
};
