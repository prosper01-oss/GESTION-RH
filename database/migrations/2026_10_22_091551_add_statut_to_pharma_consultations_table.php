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
        // File d'attente du docteur : le patient passé en caisse attend d'être reçu, puis est marqué « vu ».
        Schema::table('pharma_consultations', function (Blueprint $table) {
            $table->string('statut', 20)->default('en_attente')->after('docteur');
            $table->timestamp('vu_at')->nullable()->after('statut');
            $table->index(['docteur', 'statut'], 'idx_pharma_consultation_docteur');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharma_consultations', function (Blueprint $table) {
            $table->dropIndex('idx_pharma_consultation_docteur');
            $table->dropColumn(['statut', 'vu_at']);
        });
    }
};
