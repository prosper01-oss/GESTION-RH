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
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->boolean('is_paid')->default(true);
            $table->boolean('has_balance')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('is_medical')->default(false);
            $table->boolean('allows_half_day')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Une ligne par version des règles : une modification crée une nouvelle ligne datée.
        Schema::create('leave_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_type_id')->constrained()->cascadeOnDelete();
            $table->string('accrual_rule', 20);
            $table->decimal('annual_entitlement', 5, 2)->default(0);
            $table->string('counting_mode', 20);
            $table->decimal('carry_over_max', 5, 2)->nullable();
            $table->date('effective_from');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['leave_type_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_policies');
        Schema::dropIfExists('leave_types');
    }
};
