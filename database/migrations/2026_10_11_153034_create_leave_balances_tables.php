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
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('period_year');
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'period_year']);
        });

        // Registre des mouvements : le solde n'est jamais stocké, il est la somme des mouvements.
        // reference_period rend les acquisitions automatiques idempotentes (une par mois et par solde).
        Schema::create('leave_balance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_balance_id')->constrained()->restrictOnDelete();
            $table->string('entry_type', 30);
            $table->decimal('days', 6, 2);
            $table->string('reference_period', 7)->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['leave_balance_id', 'entry_type', 'reference_period'], 'balance_entries_period_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_balance_entries');
        Schema::dropIfExists('leave_balances');
    }
};
