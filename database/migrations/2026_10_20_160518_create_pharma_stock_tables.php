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
        Schema::create('pharma_entree_indices', function (Blueprint $table) {
            $table->id();
            $table->string('ref_entree')->unique();
            $table->text('motif')->nullable();
            $table->foreignId('fournisseur_id')->nullable()->constrained('pharma_fournisseurs')->nullOnDelete();
            $table->integer('nb_article')->default(0);
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        // Lignes d'entrée : lots de stock.
        Schema::create('pharma_entree_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entree_indices_id')->constrained('pharma_entree_indices')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('pharma_articles')->cascadeOnDelete();
            $table->string('lot');
            $table->string('source', 10)->default('manuel'); // manuel | import
            $table->integer('qte_initial')->default(0);
            $table->integer('qte_entree')->default(0);
            $table->integer('stock_restant_lot')->default(0);
            $table->integer('stock_dispo')->default(0);
            $table->decimal('prix_achat_boite', 12, 2);
            $table->decimal('prix_unitaire', 12, 2);         // PU de vente brut (+X %)
            $table->decimal('montant_entree', 12, 2);
            $table->decimal('montant_gain_brut', 12, 2)->default(0);
            $table->integer('nb_lot_dispo')->default(0);
            $table->date('date_peremption')->nullable();
            $table->boolean('isValide')->default(false);
            $table->boolean('isVendus')->default(false);
            $table->boolean('etat')->default(true);
            $table->timestamps();

            $table->index(['article_id', 'isValide', 'stock_restant_lot', 'date_peremption'], 'idx_pharma_fifo_lot');
            $table->index('date_peremption', 'idx_pharma_peremption');
        });

        Schema::create('pharma_sortie_indices', function (Blueprint $table) {
            $table->id();
            $table->string('ref_sortie')->unique();
            $table->string('motif'); // ajustement | perte | casse | perime
            $table->integer('nb_article')->default(0);
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        Schema::create('pharma_sortie_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_entree_detail')->constrained('pharma_entree_details')->cascadeOnDelete();
            $table->foreignId('sortie_indices_id')->constrained('pharma_sortie_indices')->cascadeOnDelete();
            $table->integer('qte_initial')->default(0);
            $table->integer('qte_sortie')->default(0);
            $table->integer('stock_restant_lot')->default(0);
            $table->integer('stock_dispo')->default(0);
            $table->decimal('montant_perte', 12, 2)->default(0);
            $table->boolean('etat')->default(true);
            $table->timestamps();
        });

        // Journal de toutes les écritures de stock.
        Schema::create('pharma_stock_mouvements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained('pharma_articles')->nullOnDelete();
            $table->foreignId('entree_detail_id')->nullable()->constrained('pharma_entree_details')->nullOnDelete();
            $table->string('type', 40)->index(); // entree | entree_annulation | sortie | sortie_restauration | panier | panier_retrait | inventaire | inventaire_annulation
            $table->integer('qte');              // signé : + entrée, − sortie
            $table->unsignedInteger('stock_avant')->nullable();
            $table->unsignedInteger('stock_apres')->nullable();
            $table->string('reference', 80)->nullable();
            $table->string('libelle', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pharma_inventaires', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->date('date_inventaire');
            $table->string('statut', 20)->default('brouillon'); // brouillon | valide | annule
            $table->string('motif', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pharma_inventaire_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventaire_id')->constrained('pharma_inventaires')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('pharma_articles')->cascadeOnDelete();
            $table->unsignedInteger('theorique');
            $table->unsignedInteger('compte')->nullable();  // comptage physique
            $table->integer('ecart')->default(0);           // compte - theorique
            $table->foreignId('entree_detail_id')->nullable()->constrained('pharma_entree_details')->nullOnDelete(); // lot créé par un écart positif appliqué

            $table->unique(['inventaire_id', 'article_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['inventaire_details', 'inventaires', 'stock_mouvements', 'sortie_details', 'sortie_indices', 'entree_details', 'entree_indices'] as $table) {
            Schema::dropIfExists('pharma_'.$table);
        }
    }
};
