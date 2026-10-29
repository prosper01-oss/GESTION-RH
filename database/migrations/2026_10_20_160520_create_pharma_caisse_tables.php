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
        Schema::create('pharma_caisse_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('statut')->default('ouverte'); // ouverte | fermee
            $table->decimal('fond_de_caisse', 12, 2)->default(0);
            $table->timestamp('ouverture_at')->nullable();
            $table->timestamp('fermeture_at')->nullable();
            $table->decimal('total_ventes', 12, 2)->default(0);   // somme montant_brut
            $table->decimal('total_encaisse', 12, 2)->default(0); // somme montant_paye
            $table->decimal('total_monnaie', 12, 2)->default(0);  // somme monnaie
            $table->decimal('theorique', 12, 2)->default(0);      // fond + total_ventes
            $table->decimal('montant_reel', 12, 2)->nullable();   // comptage physique de fin
            $table->decimal('ecart', 12, 2)->default(0);          // reel - theorique
            $table->string('observations')->nullable();
            $table->timestamps();
        });

        // Panier de caisse, propre à chaque utilisateur.
        Schema::create('pharma_paniers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entree_detail_id')->nullable(); // lot réservé (articles)
            $table->string('type')->nullable();                          // Consultation|Service|Article|Analyse|Kit
            $table->unsignedBigInteger('article_id')->nullable();
            $table->string('analyse_id')->nullable();                    // ids d'analyses séparés par des virgules
            $table->unsignedBigInteger('service_id')->nullable();
            $table->unsignedBigInteger('consultation_id')->nullable();   // id du service de consultation
            $table->unsignedBigInteger('kit_id')->nullable();
            $table->string('nom_patient')->nullable();
            $table->boolean('sex_patient')->default(true);               // 1 = Homme, 0 = Femme
            $table->integer('age_patient')->nullable();
            $table->boolean('unite_age')->default(true);                 // 1 = ans, 0 = mois
            $table->string('type_docteur')->nullable();                  // generaliste | dentiste
            $table->string('docteur')->nullable();                       // id pharma_personnels
            $table->decimal('p_u_brut', 12, 2)->default(0);
            $table->integer('qte')->default(0);
            $table->decimal('montant_brut', 12, 2)->default(0);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->index('user_id', 'idx_pharma_panier_user');
            $table->index(['article_id', 'user_id'], 'idx_pharma_panier_article_user');
        });

        Schema::create('pharma_ventes', function (Blueprint $table) {
            $table->id();
            $table->string('reference_vente')->unique(); // V + AAAAMMJJHHIISS
            $table->string('type')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('caisse_session_id')->nullable()->constrained('pharma_caisse_sessions')->nullOnDelete();
            $table->string('client');
            $table->decimal('montant_brut', 12, 2);
            $table->decimal('montant_paye', 12, 2);
            $table->decimal('monnaie', 12, 2);
            $table->string('mode_paiement'); // cash
            $table->timestamps();
        });

        Schema::create('pharma_vente_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('pharma_ventes')->cascadeOnDelete();
            $table->string('type')->nullable();                         // Consultation|Service|Article|Analyse|Kit
            $table->unsignedBigInteger('article_id')->nullable();
            $table->unsignedBigInteger('entree_detail_id')->nullable(); // traçabilité du lot vendu
            $table->unsignedBigInteger('consultation_id')->nullable();
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('analyse_id')->nullable();                   // ids d'analyses séparés par des virgules
            $table->unsignedBigInteger('kit_id')->nullable();
            $table->decimal('p_u_brut', 12, 2);
            $table->integer('qte');
            $table->decimal('montant_brut', 12, 2);
            $table->decimal('montant_paye', 12, 2);                     // part imputée au prorata
            $table->timestamps();

            $table->index(['vente_id', 'type'], 'idx_pharma_vente_details_vente_type');
        });

        Schema::create('pharma_consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vente_id')->constrained('pharma_ventes')->cascadeOnDelete();
            $table->string('nom_patient');
            $table->boolean('sex_patient');   // 1 = Homme, 0 = Femme
            $table->integer('age_patient');
            $table->boolean('unite_age');     // 1 = ans, 0 = mois
            $table->string('type_docteur');
            $table->string('docteur');        // id pharma_personnels
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['consultations', 'vente_details', 'ventes', 'paniers', 'caisse_sessions'] as $table) {
            Schema::dropIfExists('pharma_'.$table);
        }
    }
};
