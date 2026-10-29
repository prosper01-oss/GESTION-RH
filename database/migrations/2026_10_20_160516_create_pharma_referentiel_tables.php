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
        Schema::create('pharma_unites', function (Blueprint $table) {
            $table->id();
            $table->string('nomUnite')->unique();
            $table->string('nomComplet');
            $table->boolean('supun')->default(false);
            $table->timestamps();
        });

        // Paramètre global : pourcentage de marge.
        Schema::create('pharma_pourcentages', function (Blueprint $table) {
            $table->id();
            $table->integer('pourcentage');
            $table->timestamps();
        });

        Schema::create('pharma_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom_cat')->unique();
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        // Personnel soignant (docteurs / praticiens) cité sur les consultations.
        Schema::create('pharma_personnels', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('fonction');
            $table->string('contact');
            $table->timestamps();
        });

        Schema::create('pharma_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->decimal('prix', 12, 2);
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        Schema::create('pharma_echographies', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->decimal('prix', 12, 2);
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        Schema::create('pharma_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->string('telephone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['nom', 'actif']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['fournisseurs', 'echographies', 'analyses', 'personnels', 'categories', 'pourcentages', 'unites'] as $table) {
            Schema::dropIfExists('pharma_'.$table);
        }
    }
};
