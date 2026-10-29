<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pharma_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorie_id')->constrained('pharma_categories')->cascadeOnDelete();
            $table->string('nom_service')->unique();
            $table->decimal('prix_service', 12, 2);
            $table->integer('isConsult')->default(0); // 1 = acte de consultation
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        Schema::create('pharma_kits', function (Blueprint $table) {
            $table->id();
            $table->string('nom_kit')->unique();
            $table->decimal('prix_kit', 12, 2);
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        // Référentiel produits + stock agrégé.
        Schema::create('pharma_articles', function (Blueprint $table) {
            $table->id();
            $table->string('designation');
            $table->integer('presentation');                // nombre d'unités par conditionnement
            $table->foreignId('unite')->constrained('pharma_unites')->cascadeOnDelete();
            $table->integer('stock')->default(0)->index('idx_pharma_article_stock');
            $table->integer('seuil')->default(10);          // seuil d'alerte de rupture
            $table->unsignedInteger('qte_max')->nullable();
            $table->integer('statut')->default(1);          // 1 = disponible, 0 = indisponible
            $table->integer('etat')->default(1);            // 1 = actif, 0 = supprimé (logique)
            $table->timestamps();
        });

        Schema::create('pharma_detail_kits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_kit')->constrained('pharma_kits')->cascadeOnDelete();
            $table->foreignId('id_article')->constrained('pharma_articles')->cascadeOnDelete();
            $table->integer('qte_kit');
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        // Le stock d'un article ne peut jamais devenir négatif (MySQL 8.0.16+ applique la contrainte).
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE pharma_articles ADD CONSTRAINT pharma_articles_stock_non_negative CHECK (stock >= 0)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['detail_kits', 'articles', 'kits', 'services'] as $table) {
            Schema::dropIfExists('pharma_'.$table);
        }
    }
};
