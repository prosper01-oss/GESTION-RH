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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 30)->unique();
            $table->string('nom');
            $table->string('prenom');
            $table->string('photo_path')->nullable();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('position');
            $table->date('hire_date');
            $table->string('contract_type', 20);
            $table->string('status', 20)->default('actif');
            $table->string('phone_pro', 30)->nullable();
            $table->string('email_pro')->nullable();
            $table->timestamps();

            $table->index(['status', 'nom']);
            $table->index('nom');
        });

        // Le responsable d'un service est un employé : la colonne est ajoutée une fois la table employees créée.
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('manager_employee_id')->nullable()->after('name')->constrained('employees')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->unique()->after('id')->constrained()->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropUnique(['employee_id']);
            $table->dropColumn('employee_id');
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_employee_id');
        });

        Schema::dropIfExists('employees');
    }
};
