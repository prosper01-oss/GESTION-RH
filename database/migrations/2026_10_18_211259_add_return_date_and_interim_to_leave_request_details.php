<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date de reprise du travail et agent qui assure l'intérim du poste pendant le congé.
     * Obligatoires pour toute nouvelle demande ; nulles pour les demandes antérieures.
     */
    public function up(): void
    {
        Schema::table('leave_request_details', function (Blueprint $table) {
            $table->date('return_date')->nullable()->after('ends_morning');
            $table->foreignId('interim_employee_id')->nullable()->after('return_date')->constrained('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_request_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interim_employee_id');
            $table->dropColumn('return_date');
        });
    }
};
