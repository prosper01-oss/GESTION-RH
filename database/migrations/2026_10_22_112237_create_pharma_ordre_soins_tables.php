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
        // Soins prescrits par le dentiste à un patient : le patient les règle ensuite en caisse.
        Schema::create('pharma_ordre_soins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained('pharma_consultations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // docteur qui a prescrit
            $table->string('statut', 20)->default('en_attente'); // en_attente | paye | annule
            $table->string('note')->nullable();
            $table->foreignId('vente_id')->nullable()->constrained('pharma_ventes')->nullOnDelete();
            $table->timestamp('paye_at')->nullable();
            $table->timestamps();

            $table->index('statut');
        });

        Schema::create('pharma_ordre_soins_lignes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ordre_soins_id')->constrained('pharma_ordre_soins')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('pharma_services')->restrictOnDelete();
            $table->unsignedInteger('qte')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pharma_ordre_soins_lignes');
        Schema::dropIfExists('pharma_ordre_soins');
    }
};
