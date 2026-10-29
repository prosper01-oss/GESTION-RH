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
        // Les docteurs viennent désormais des fiches du personnel RH (fonction « Docteur »).
        Schema::dropIfExists('pharma_personnels');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('pharma_personnels', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('fonction');
            $table->string('contact');
            $table->timestamps();
        });
    }
};
