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
        Schema::create('permission_request_details', function (Blueprint $table) {
            $table->foreignId('request_id')->primary()->constrained('requests')->cascadeOnDelete();
            $table->foreignId('permission_type_id')->constrained()->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->decimal('duration_hours', 6, 2);
            $table->text('reason')->nullable();

            $table->index(['start_at', 'end_at']);
        });

        Schema::create('exit_pass_details', function (Blueprint $table) {
            $table->foreignId('request_id')->primary()->constrained('requests')->cascadeOnDelete();
            $table->dateTime('departure_at');
            $table->dateTime('expected_return_at');
            $table->dateTime('actual_departure_at')->nullable();
            $table->dateTime('actual_return_at')->nullable();
            $table->string('destination');
            $table->text('reason');

            $table->index('departure_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_pass_details');
        Schema::dropIfExists('permission_request_details');
    }
};
