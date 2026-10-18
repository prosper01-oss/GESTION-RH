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
        Schema::table('leave_types', function (Blueprint $table) {
            // R8 : délai minimal entre le dépôt et le premier jour, hors urgence.
            $table->unsignedSmallInteger('min_notice_days')->default(0)->after('allows_half_day');
            // R6 : justificatif à fournir dans ce délai après le premier jour (null = exigé avant l'envoi).
            $table->unsignedSmallInteger('attachment_deadline_hours')->nullable()->after('min_notice_days');
        });

        Schema::table('leave_request_details', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('ends_morning');
            $table->dateTime('attachment_due_at')->nullable()->after('reason');
            $table->dateTime('attachment_reminded_at')->nullable()->after('attachment_due_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_request_details', function (Blueprint $table) {
            $table->dropColumn(['is_urgent', 'attachment_due_at', 'attachment_reminded_at']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropColumn(['min_notice_days', 'attachment_deadline_hours']);
        });
    }
};
