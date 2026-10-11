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
        // Demande mère commune aux congés, permissions et billets de sortie (section 8.1).
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->nullable()->unique();
            $table->string('request_type', 20);
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('status', 30);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['request_type', 'status']);
            $table->index(['employee_id', 'status']);
        });

        Schema::create('leave_request_details', function (Blueprint $table) {
            $table->foreignId('request_id')->primary()->constrained('requests')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->boolean('starts_afternoon')->default(false);
            $table->date('end_date');
            $table->boolean('ends_morning')->default(false);
            $table->decimal('requested_days', 6, 2);
            $table->decimal('consumed_days', 6, 2)->nullable();
            $table->date('actual_end_date')->nullable();
            $table->text('reason')->nullable();

            $table->index(['start_date', 'end_date']);
        });

        // Historique en ajout seul : en production, ni UPDATE ni DELETE pour l'application.
        Schema::create('request_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('on_behalf_of_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['request_id', 'id']);
        });

        Schema::create('request_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('size');
            $table->boolean('is_medical')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('doc_type', 10);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['doc_type', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('request_attachments');
        Schema::dropIfExists('request_status_history');
        Schema::dropIfExists('leave_request_details');
        Schema::dropIfExists('requests');
    }
};
