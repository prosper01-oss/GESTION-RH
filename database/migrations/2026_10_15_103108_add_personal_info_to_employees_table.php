<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('cin', 30)->nullable()->after('prenom');
            $table->date('date_naissance')->nullable()->after('cin');
            $table->string('numero_cnasp', 30)->nullable()->after('date_naissance');
            $table->string('adresse')->nullable()->after('numero_cnasp');
            $table->string('sexe', 20)->nullable()->after('adresse');
            $table->string('situation_familiale', 20)->nullable()->after('sexe');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['cin', 'date_naissance', 'numero_cnasp', 'adresse', 'sexe', 'situation_familiale']);
        });
    }
};
