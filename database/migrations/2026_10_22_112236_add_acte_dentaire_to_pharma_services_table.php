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
        // Les services marqués « acte dentaire » forment la liste des soins que le dentiste peut envoyer en caisse.
        Schema::table('pharma_services', function (Blueprint $table) {
            $table->boolean('acte_dentaire')->default(false)->after('isConsult');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pharma_services', function (Blueprint $table) {
            $table->dropColumn('acte_dentaire');
        });
    }
};
